<?php
/**
 * public/checkout.php
 * ------------------------------------------------
 * Requires login (an order needs a user_id).
 *
 * Card payments use Stripe Checkout in test mode; Cash on Delivery remains available.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payments.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';
require_once __DIR__ . '/../core/Validator.php';

$db = new Database($conn);
Session::start();

Auth::requireLogin('login.php');
$customer = $db->fetchOne(
    'SELECT name, email FROM users WHERE id = ?',
    [(int) Session::get('customer_id')],
    'i'
);
$billingName = $customer['name'] ?? Auth::name('customer') ?? '';
$billingEmail = $customer['email'] ?? '';
$billingPhone = '';
$billingAddress = '';
$billingAddress2 = '';
$billingCity = '';
$billingState = '';
$billingPostcode = '';
$billingCountry = '';

if (isset($_GET['cancelled'], $_GET['order'])) {
    $cancelledOrder = $db->fetchOne(
        'SELECT id FROM orders WHERE order_number = ? AND user_id = ? AND payment_method = ? AND payment_status = ?',
        [trim($_GET['order']), Session::get('customer_id'), 'stripe', 'pending'],
        'siss'
    );
    if ($cancelledOrder) {
        releasePendingStripeOrder($db, $conn, $cancelledOrder['id']);
        Session::flash('error', 'Payment was cancelled. Your cart is unchanged.');
    }
    header('Location: checkout.php');
    exit;
}

$cartItems = Cart::getItems($db);

if (empty($cartItems)) {
    Session::flash('error', 'Your cart is empty.');
    header('Location: cart.php');
    exit;
}

$cartTotal = Cart::getTotal($cartItems);
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $billingPhone = trim($_POST['billing_phone'] ?? '');
    $billingAddress = trim($_POST['billing_address'] ?? '');
    $billingAddress2 = trim($_POST['billing_address_2'] ?? '');
    $billingCity = trim($_POST['billing_city'] ?? '');
    $billingState = trim($_POST['billing_state'] ?? '');
    $billingPostcode = trim($_POST['billing_postcode'] ?? '');
    $billingCountry = trim($_POST['billing_country'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    $v = new Validator();
    $v->required($billingPhone, 'billing_phone', 'phone number')
        ->required($billingAddress, 'billing_address', 'street address')
        ->required($billingCity, 'billing_city', 'city')
        ->required($billingState, 'billing_state', 'state or province')
        ->required($billingPostcode, 'billing_postcode', 'postal code')
        ->required($billingCountry, 'billing_country', 'country')
        ->required($paymentMethod, 'payment_method', 'payment method');

    if ($billingPhone !== '' && !preg_match('/^[+0-9().\-\s]{7,25}$/', $billingPhone)) {
        $errors['billing_phone'] = 'Enter a valid phone number.';
    }

    if ($paymentMethod !== '' && !isSupportedPaymentMethod($paymentMethod)) {
        $errors['payment_method'] = 'Please choose a supported payment method.';
    }
    if ($paymentMethod === 'stripe' && !isStripeSandboxConfigured()) {
        $errors['payment_method'] = 'Stripe sandbox is not configured. Add your sk_test key to .env.';
    }

    if ($v->passes() && empty($errors)) {
        $addressLines = [
            'Name: ' . $billingName,
            'Email: ' . $billingEmail,
            'Phone: ' . $billingPhone,
            'Address: ' . $billingAddress,
        ];
        if ($billingAddress2 !== '') {
            $addressLines[] = 'Address line 2: ' . $billingAddress2;
        }
        $addressLines[] = 'City: ' . $billingCity;
        $addressLines[] = 'State/Province: ' . $billingState;
        $addressLines[] = 'Postal code: ' . $billingPostcode;
        $addressLines[] = 'Country: ' . $billingCountry;
        $shippingAddress = implode("\n", $addressLines);

        // Re-check stock right before placing the order — it may have
        // changed since the cart page was loaded.
        $outOfStock = [];
        foreach ($cartItems as $item) {
            $fresh = $db->fetchOne('SELECT stock FROM products WHERE id = ?', [$item['id']], 'i');
            if (!$fresh || (int) $fresh['stock'] < $item['quantity']) {
                $outOfStock[] = $item['name'];
            }
        }

        if (!empty($outOfStock)) {
            $errors['stock'] = 'Not enough stock for: ' . implode(', ', $outOfStock) . '. Please update your cart.';
        } else {
            mysqli_begin_transaction($conn);
            $transactionOpen = true;
            $orderId = 0;

            try {
                $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(5)));
                $orderStatus = $paymentMethod === 'cod' ? 'processing' : 'pending';
                $orderId = $db->insert(
                    'INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, transaction_id, shipping_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        Session::get('customer_id'),
                        $orderNumber,
                        $cartTotal,
                        $paymentMethod,
                        'pending',
                        $orderStatus,
                        null,
                        $shippingAddress,
                    ],
                    'isdsssss'
                );

                foreach ($cartItems as $item) {
                    $reserved = $db->execute(
                        'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?',
                        [$item['quantity'], $item['id'], $item['quantity']],
                        'iii'
                    );
                    if ($reserved !== 1) {
                        throw new RuntimeException('Stock changed during checkout.');
                    }

                    $db->insert(
                        'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)',
                        [$orderId, $item['id'], $item['quantity'], $item['price'], $item['subtotal']],
                        'iiidd'
                    );

                }

                mysqli_commit($conn);
                $transactionOpen = false;

                if ($paymentMethod === 'cod') {
                    Cart::clearCart($db);
                    sendOrderEmail($db, $orderId);
                    header('Location: order-confirmation.php?order=' . urlencode($orderNumber));
                    exit;
                }

                $scriptDirectory = rtrim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/public/checkout.php')), '/');
                if ($scriptDirectory === '.') {
                    $scriptDirectory = '';
                }
                $returnUrl = stripeReturnBaseUrl() . $scriptDirectory;
                $lineItems = [];
                foreach ($cartItems as $item) {
                    $lineItems[] = [
                        'price_data' => [
                            'currency' => 'usd',
                            'unit_amount' => (int) round((float) $item['price'] * 100),
                            'product_data' => ['name' => $item['name']],
                        ],
                        'quantity' => (int) $item['quantity'],
                    ];
                }

                try {
                    $stripeSession = stripeApiRequest('POST', 'checkout/sessions', [
                        'mode' => 'payment',
                        'payment_method_types' => ['card'],
                        'line_items' => $lineItems,
                        'metadata' => [
                            'order_number' => $orderNumber,
                            'user_id' => (string) Session::get('customer_id'),
                        ],
                        'success_url' => $returnUrl . '/order-confirmation.php?order=' . urlencode($orderNumber) . '&session_id={CHECKOUT_SESSION_ID}',
                        'cancel_url' => $returnUrl . '/checkout.php?cancelled=1&order=' . urlencode($orderNumber),
                    ], $orderNumber);

                    $db->execute(
                        'UPDATE orders SET transaction_id = ? WHERE id = ? AND payment_method = ?',
                        [$stripeSession['id'], $orderId, 'stripe'],
                        'sis'
                    );
                } catch (Throwable $exception) {
                    releasePendingStripeOrder($db, $conn, $orderId);
                    throw $exception;
                }

                header('Location: ' . $stripeSession['url'], true, 303);
                exit;
            } catch (Throwable $e) {
                if ($transactionOpen) {
                    mysqli_rollback($conn);
                }
                if (!$transactionOpen && $orderId > 0 && $paymentMethod === 'stripe') {
                    releasePendingStripeOrder($db, $conn, $orderId);
                }
                $errors['general'] = 'Checkout could not be started. Please try again.';
            }
        }
    } else {
        $errors = array_merge($errors, $v->errors());
    }
}

$pageTitle = 'Checkout';
require __DIR__ . '/../includes/header.php';
?>

<main class="main">
    <div class="page-header text-center">
        <div class="container">
            <h1 class="page-title">Checkout<span>Shop</span></h1>
        </div>
    </div>
    <nav aria-label="breadcrumb" class="breadcrumb-nav">
        <div class="container">
            <ol class="breadcrumb">
                <li class="breadcrumb-item"><a href="index.php">Home</a></li>
                <li class="breadcrumb-item"><a href="products.php">Shop</a></li>
                <li class="breadcrumb-item active" aria-current="page">Checkout</li>
            </ol>
        </div>
    </nav>

    <div class="page-content">
        <div class="checkout">
            <div class="container">
                <?php if (!empty($errors)): ?>
                    <?php foreach (['stock', 'general'] as $summaryField): ?>
                        <?php if (isset($errors[$summaryField])): ?>
                            <p class="form-error-message" role="alert">
                                <?php echo htmlspecialchars($errors[$summaryField]); ?>
                            </p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form action="checkout.php" method="post">
                    <div class="row">
                        <div class="col-lg-8">
                            <h2 class="checkout-title">Billing Details</h2>
                            <div class="row">
                                <div class="col-sm-6 form-group">
                                    <label for="billing_name">Full name</label>
                                    <input id="billing_name" type="text" class="form-control"
                                        value="<?php echo htmlspecialchars($billingName); ?>" readonly>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_email">Email address</label>
                                    <input id="billing_email" type="email" class="form-control"
                                        value="<?php echo htmlspecialchars($billingEmail); ?>" readonly>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_phone">Phone number *</label>
                                    <input id="billing_phone" name="billing_phone" type="tel"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_phone'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_phone'); ?>
                                        autocomplete="tel" value="<?php echo htmlspecialchars($billingPhone); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_phone'); ?>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_country">Country *</label>
                                    <input id="billing_country" name="billing_country" type="text"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_country'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_country'); ?>
                                        autocomplete="country-name"
                                        value="<?php echo htmlspecialchars($billingCountry); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_country'); ?>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="billing_address">Street address *</label>
                                    <input id="billing_address" name="billing_address" type="text"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_address'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_address'); ?>
                                        autocomplete="address-line1" placeholder="House number and street name"
                                        value="<?php echo htmlspecialchars($billingAddress); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_address'); ?>
                                </div>
                                <div class="col-12 form-group">
                                    <label for="billing_address_2">Apartment, suite, unit (optional)</label>
                                    <input id="billing_address_2" name="billing_address_2" type="text"
                                        class="form-control" autocomplete="address-line2"
                                        value="<?php echo htmlspecialchars($billingAddress2); ?>">
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_city">Town / City *</label>
                                    <input id="billing_city" name="billing_city" type="text"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_city'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_city'); ?>
                                        autocomplete="address-level2"
                                        value="<?php echo htmlspecialchars($billingCity); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_city'); ?>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_state">State / Province *</label>
                                    <input id="billing_state" name="billing_state" type="text"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_state'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_state'); ?>
                                        autocomplete="address-level1"
                                        value="<?php echo htmlspecialchars($billingState); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_state'); ?>
                                </div>
                                <div class="col-sm-6 form-group">
                                    <label for="billing_postcode">Postcode / ZIP *</label>
                                    <input id="billing_postcode" name="billing_postcode" type="text"
                                        class="form-control<?php echo Validator::fieldClass($errors, 'billing_postcode'); ?>"<?php echo Validator::fieldAttributes($errors, 'billing_postcode'); ?> autocomplete="postal-code"
                                        value="<?php echo htmlspecialchars($billingPostcode); ?>">
                                    <?php echo Validator::fieldErrorMarkup($errors, 'billing_postcode'); ?>
                                </div>
                            </div>
                        </div>

                        <aside class="col-lg-4">
                            <div class="summary">
                                <h3 class="summary-title">Your Order</h3>
                                <table class="table table-summary">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($cartItems as $item): ?>
                                            <tr>
                                                <td>
                                                    <a
                                                        href="product-detail.php?slug=<?php echo urlencode($item['slug']); ?>">
                                                        <?php echo htmlspecialchars($item['name']); ?>
                                                    </a>
                                                    <span> &times; <?php echo (int) $item['quantity']; ?></span>
                                                </td>
                                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                            </tr>
                                        <?php endforeach; ?>
                                        <tr class="summary-subtotal">
                                            <td>Subtotal</td>
                                            <td>$<?php echo number_format($cartTotal, 2); ?></td>
                                        </tr>
                                        <tr>
                                            <td>Shipping</td>
                                            <td>Free</td>
                                        </tr>
                                        <tr class="summary-total">
                                            <td>Total</td>
                                            <td>$<?php echo number_format($cartTotal, 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>

                                <h4 class="checkout-title">Payment</h4>
                                <div class="accordion-summary<?php echo Validator::fieldClass($errors, 'payment_method'); ?>"
                                    data-validation-group="payment_method"<?php echo Validator::fieldAttributes($errors, 'payment_method'); ?>>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="pay_cod" name="payment_method" value="cod"
                                            class="custom-control-input" <?php echo ($_POST['payment_method'] ?? 'cod') === 'cod' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="pay_cod">Cash on delivery</label>
                                    </div>
                                    <div class="custom-control custom-radio">
                                        <input type="radio" id="pay_stripe" name="payment_method" value="stripe"
                                            class="custom-control-input" <?php echo ($_POST['payment_method'] ?? '') === 'stripe' ? 'checked' : ''; ?>>
                                        <label class="custom-control-label" for="pay_stripe">Credit or debit card
                                            (Stripe)</label>
                                    </div>
                                </div>
                                <?php echo Validator::fieldErrorMarkup($errors, 'payment_method'); ?>
                                <p class="small text-muted">Stripe sandbox only. No real money is charged.</p>
                                <button type="submit" class="btn btn-outline-primary-2 btn-order btn-block">
                                    <span class="btn-text">Place Order</span>
                                    <span class="btn-hover-text">Continue to payment</span>
                                </button>
                            </div>
                        </aside>
                    </div>
                </form>
            </div>
        </div>
    </div>
</main>

<?php require __DIR__ . '/../includes/footer.php'; ?>
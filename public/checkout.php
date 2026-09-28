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

if (isset($_GET['cancelled'], $_GET['order'])) {
    $cancelledOrder = $db->fetchOne(
        'SELECT id FROM orders WHERE order_number = ? AND user_id = ? AND payment_method = ? AND payment_status = ?',
        [trim($_GET['order']), Session::get('user_id'), 'stripe', 'pending'],
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
    $shippingAddress = trim($_POST['shipping_address'] ?? '');
    $paymentMethod = $_POST['payment_method'] ?? '';

    $v = new Validator();
    $v->required($shippingAddress, 'shipping_address', 'shipping address')
        ->required($paymentMethod, 'payment_method', 'payment method');

    if ($paymentMethod !== '' && !isSupportedPaymentMethod($paymentMethod)) {
        $errors['payment_method'] = 'Please choose a supported payment method.';
    }
    if ($paymentMethod === 'stripe' && !isStripeSandboxConfigured()) {
        $errors['payment_method'] = 'Stripe sandbox is not configured. Add your sk_test key to .env.';
    }

    if ($v->passes() && empty($errors)) {
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
                        Session::get('user_id'),
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
                    Cart::clearCart();
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
                            'user_id' => (string) Session::get('user_id'),
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

<div class="storefront-page storefront-checkout-page">
    <div class="container mb-5 mt-4">
        <div class="storefront-heading"><span class="eyebrow">Almost there</span>
            <h1 class="title">Checkout</h1>
            <p>Securely confirm your delivery and payment details.</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                        <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <div class="row checkout-layout">
            <div class="col-md-7 mb-4">
                <div class="checkout-panel">
                    <h3>Shipping details</h3>
                    <form action="checkout.php" method="post">
                        <div class="form-group">
                            <label for="shipping_address">Shipping Address</label>
                            <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4"
                                required><?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?></textarea>
                        </div>

                        <div class="form-group">
                            <label>Payment Method</label>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="pay_cod" name="payment_method" value="cod"
                                    class="custom-control-input" checked>
                                <label class="custom-control-label" for="pay_cod">Cash on Delivery</label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="pay_stripe" name="payment_method" value="stripe"
                                    class="custom-control-input">
                                <label class="custom-control-label" for="pay_stripe">Credit or debit card
                                    (Stripe)</label>
                            </div>
                        </div>

                        <p class="small text-muted mb-3">Stripe sandbox: use test card details only. No real money is
                            charged.</p>
                        <button type="submit" class="btn btn-primary btn-round">
                            <span>Place Order</span><i class="icon-long-arrow-right"></i>
                        </button>
                    </form>
                </div>
            </div>

            <div class="col-md-5">
                <div class="checkout-summary">
                    <h3>Order summary</h3>
                    <p class="summary-caption">Your selected items</p>
                    <table class="table checkout-summary-table">
                        <?php foreach ($cartItems as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?> &times;
                                    <?php echo (int) $item['quantity']; ?>
                                </td>
                                <td class="text-right">$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                        <tr>
                            <th>Total</th>
                            <th class="text-right">$<?php echo number_format($cartTotal, 2); ?></th>
                        </tr>
                    </table>
                </div>
            </div>
        </div>
    </div><!-- End .container -->
</div><!-- End .storefront-page -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
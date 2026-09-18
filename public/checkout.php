<?php
/**
 * public/checkout.php
 * ------------------------------------------------
 * Requires login (an order needs a user_id).
 *
 * All payment methods are handled here. JazzCash and Easypaisa are local
 * sandbox simulations and do not contact a real payment gateway.
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
            // Everything checks out — place the order inside a transaction so
            // a failure partway through can't leave a half-written order.
            mysqli_begin_transaction($conn);

            try {
                $orderNumber = 'ORD-' . strtoupper(bin2hex(random_bytes(5)));

                $isSandboxPayment = in_array($paymentMethod, ['jazzcash', 'easypaisa'], true);
                $transactionId = $isSandboxPayment ? createSandboxTransactionId($paymentMethod) : null;
                $orderId = $db->insert(
                    'INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, transaction_id, shipping_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                    [
                        Session::get('user_id'),
                        $orderNumber,
                        $cartTotal,
                        $paymentMethod,
                        $isSandboxPayment ? 'completed' : 'pending',
                        'processing',
                        $transactionId,
                        $shippingAddress,
                    ],
                    'isdsssss'
                );

                foreach ($cartItems as $item) {
                    $db->insert(
                        'INSERT INTO order_items (order_id, product_id, quantity, unit_price, subtotal) VALUES (?, ?, ?, ?, ?)',
                        [$orderId, $item['id'], $item['quantity'], $item['price'], $item['subtotal']],
                        'iiidd'
                    );

                    $db->execute(
                        'UPDATE products SET stock = stock - ? WHERE id = ?',
                        [$item['quantity'], $item['id']],
                        'ii'
                    );
                }

                mysqli_commit($conn);

                Cart::clearCart();

                header('Location: order-confirmation.php?order=' . urlencode($orderNumber));
                exit;
            } catch (Exception $e) {
                mysqli_rollback($conn);
                $errors['general'] = 'Something went wrong placing your order. Please try again.';
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
                                <input type="radio" id="pay_jazzcash" name="payment_method" value="jazzcash"
                                    class="custom-control-input">
                                <label class="custom-control-label" for="pay_jazzcash">JazzCash
                                </label>
                            </div>
                            <div class="custom-control custom-radio">
                                <input type="radio" id="pay_easypaisa" name="payment_method" value="easypaisa"
                                    class="custom-control-input">
                                <label class="custom-control-label" for="pay_easypaisa">Easypaisa
                                </label>
                            </div>
                        </div>

                        <p class="small text-muted mb-3">Sandbox mode: JazzCash and Easypaisa payments are simulated for
                            testing. No real money is charged.</p>
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
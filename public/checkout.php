<?php
/**
 * public/checkout.php
 * ------------------------------------------------
 * Requires login (an order needs a user_id).
 *
 * COD is handled right here via a normal form POST.
 * PayPal is handled by JS calling paypal-create-order.php
 * and paypal-capture-order.php — this page's own POST handler
 * below only ever runs for COD orders.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/paypal.php';
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

    // This form only ever handles COD — PayPal orders are placed via the
    // PayPal buttons below, which call paypal-capture-order.php directly.
    if ($paymentMethod !== '' && $paymentMethod !== 'cod') {
        $errors['payment_method'] = 'Please use the PayPal button to pay with PayPal, or choose Cash on Delivery.';
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

                $orderId = $db->insert(
                    'INSERT INTO orders (user_id, order_number, total_amount, payment_method, payment_status, order_status, shipping_address)
                     VALUES (?, ?, ?, ?, ?, ?, ?)',
                    [
                        Session::get('user_id'),
                        $orderNumber,
                        $cartTotal,
                        'cod',
                        'pending',
                        'processing',
                        $shippingAddress,
                    ],
                    'isdssss'
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

<div class="container mb-5 mt-4">
    <h1 class="title text-center mb-4">Checkout</h1>

    <?php if (!empty($errors)): ?>
        <div class="alert alert-danger">
            <ul class="mb-0">
                <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                <?php endforeach; ?>
            </ul>
        </div>
    <?php endif; ?>

    <div class="row">
        <div class="col-md-7 mb-4">
            <h3>Shipping Details</h3>
            <form action="checkout.php" method="post">
                <div class="form-group">
                    <label for="shipping_address">Shipping Address</label>
                    <textarea id="shipping_address" name="shipping_address" class="form-control" rows="4"
                        required><?php echo htmlspecialchars($_POST['shipping_address'] ?? ''); ?></textarea>
                </div>

                <div class="form-group">
                    <label>Payment Method</label>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="pay_cod" name="payment_method" value="cod" class="custom-control-input"
                            checked>
                        <label class="custom-control-label" for="pay_cod">Cash on Delivery</label>
                    </div>
                    <div class="custom-control custom-radio">
                        <input type="radio" id="pay_paypal" name="payment_method" value="paypal"
                            class="custom-control-input">
                        <label class="custom-control-label" for="pay_paypal">PayPal</label>
                    </div>
                </div>

                <button type="submit" id="cod-submit-btn" class="btn btn-primary btn-round">
                    <span>Place Order</span><i class="icon-long-arrow-right"></i>
                </button>

                <div id="paypal-button-container" class="mt-3" style="display: none; max-width: 300px;"></div>
            </form>
        </div>

        <div class="col-md-5">
            <h3>Order Summary</h3>
            <table class="table">
                <?php foreach ($cartItems as $item): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($item['name']); ?> &times; <?php echo (int) $item['quantity']; ?>
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
</div><!-- End .container -->

<?php if (!empty($cartItems)): ?>
    <script src="https://www.paypal.com/sdk/js?client-id=<?php echo urlencode(PAYPAL_CLIENT_ID); ?>&currency=USD"></script>
    <script>
        (function () {
            var codRadio = document.getElementById('pay_cod');
            var paypalRadio = document.getElementById('pay_paypal');
            var codBtn = document.getElementById('cod-submit-btn');
            var paypalContainer = document.getElementById('paypal-button-container');
            var addressField = document.getElementById('shipping_address');

            function togglePaymentUI() {
                if (paypalRadio.checked) {
                    codBtn.style.display = 'none';
                    paypalContainer.style.display = 'block';
                } else {
                    codBtn.style.display = 'inline-block';
                    paypalContainer.style.display = 'none';
                }
            }

            codRadio.addEventListener('change', togglePaymentUI);
            paypalRadio.addEventListener('change', togglePaymentUI);
            togglePaymentUI();

            if (window.paypal) {
                paypal.Buttons({
                    createOrder: function () {
                        return fetch('paypal-create-order.php', { method: 'POST' })
                            .then(function (res) { return res.json(); })
                            .then(function (data) {
                                if (data.error) {
                                    alert(data.error);
                                    throw new Error(data.error);
                                }
                                return data.id;
                            });
                    },
                    onApprove: function (data) {
                        if (!addressField.value.trim()) {
                            alert('Please enter a shipping address before paying.');
                            throw new Error('Missing shipping address');
                        }
                        return fetch('paypal-capture-order.php', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({
                                orderID: data.orderID,
                                shipping_address: addressField.value
                            })
                        })
                            .then(function (res) { return res.json(); })
                            .then(function (result) {
                                if (result.error) {
                                    alert(result.error);
                                    return;
                                }
                                window.location.href = result.redirect;
                            });
                    },
                    onError: function (err) {
                        alert('PayPal encountered an error. Please try again.');
                        console.error(err);
                    }
                }).render('#paypal-button-container');
            }
        })();
    </script>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
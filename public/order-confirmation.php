<?php
/**
 * public/order-confirmation.php
 * ------------------------------------------------
 * Shown right after checkout.php places an order: ?order=ORD-XXXXX
 * Only the customer who placed the order (or an admin) can view it.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../config/payments.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Cart.php';

$db = new Database($conn);
Session::start();

Auth::requireLogin('login.php');

$orderNumber = trim($_GET['order'] ?? '');

$order = $orderNumber !== '' ? $db->fetchOne(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
    [$orderNumber, Session::get('customer_id')],
    'si'
) : null;

$checkoutSessionId = trim($_GET['session_id'] ?? '');
if ($order && $order['payment_method'] === 'stripe' && $order['payment_status'] === 'pending' && $checkoutSessionId !== '') {
    try {
        if (!hash_equals((string) $order['transaction_id'], $checkoutSessionId)) {
            throw new RuntimeException('Stripe session does not match this order.');
        }

        $checkoutSession = stripeApiRequest('GET', 'checkout/sessions/' . rawurlencode($checkoutSessionId));
        $metadata = $checkoutSession['metadata'] ?? [];
        $expectedAmount = (int) round((float) $order['total_amount'] * 100);

        if (
            ($metadata['order_number'] ?? '') === $orderNumber
            && (string) ($metadata['user_id'] ?? '') === (string) Session::get('customer_id')
            && (int) ($checkoutSession['amount_total'] ?? 0) === $expectedAmount
            && ($checkoutSession['currency'] ?? '') === 'usd'
            && completeStripeOrder($db, $conn, $checkoutSession)
        ) {
            Cart::clearCart();
            $order = $db->fetchOne(
                'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
                [$orderNumber, Session::get('customer_id')],
                'si'
            );
        }
    } catch (Throwable $exception) {
        error_log('Stripe return verification failed for order ' . $orderNumber . '.');
    }
}

$items = [];
if ($order) {
    $items = $db->fetchAll(
        'SELECT oi.*, p.name, p.slug, p.image
         FROM order_items oi
         JOIN products p ON p.id = oi.product_id
         WHERE oi.order_id = ?',
        [$order['id']],
        'i'
    );
}

$pageTitle = 'Order Confirmation';
require __DIR__ . '/../includes/header.php';
?>

<div class="storefront-page storefront-confirmation-page">
    <div class="container mb-5 mt-4">
        <?php if (!$order): ?>

            <div class="text-center py-5">
                <h2>Order not found</h2>
                <p>We couldn't find that order under your account.</p>
                <a href="index.php" class="btn btn-primary">Back to Home</a>
            </div>

        <?php else: ?>

            <?php
            $orderCancelled = $order['order_status'] === 'cancelled';
            $paymentConfirmed = !$orderCancelled && ($order['payment_method'] === 'cod' || $order['payment_status'] === 'completed');
            ?>

            <div class="confirmation-hero text-center mb-4">
                <div class="confirmation-check"><i class="<?php echo $orderCancelled ? 'icon-close' : 'icon-check'; ?>"></i>
                </div>
                <span
                    class="eyebrow"><?php echo $orderCancelled ? 'Order cancelled' : ($paymentConfirmed ? 'Order confirmed' : 'Payment pending'); ?></span>
                <h1 class="title">
                    <?php echo $orderCancelled ? 'This order was cancelled' : ($paymentConfirmed ? 'Thank you for your order!' : 'Waiting for payment confirmation'); ?>
                </h1>
                <p>Order <strong><?php echo htmlspecialchars($order['order_number']); ?></strong>
                    <?php echo $orderCancelled ? 'was cancelled. No payment is due.' : ($paymentConfirmed ? 'has been placed successfully.' : 'is not confirmed yet. Refresh this page after completing payment.'); ?>
                </p>
                <div class="confirmation-meta"><span>Payment:
                        <strong><?php echo htmlspecialchars(paymentMethodLabel($order['payment_method'])); ?></strong>
                        (<?php echo htmlspecialchars(paymentStatusLabel($order['payment_method'], $order['payment_status'], $order['order_status'])); ?>)</span><span>Status:
                        <strong><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></strong></span></div>
            </div>

            <div class="confirmation-panel">
                <table class="table confirmation-table">
                    <thead>
                        <tr>
                            <th>Product</th>
                            <th>Quantity</th>
                            <th>Unit Price</th>
                            <th>Subtotal</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo (int) $item['quantity']; ?></td>
                                <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <th colspan="3" class="text-right">Total</th>
                            <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                        </tr>
                    </tfoot>
                </table>

                <div class="confirmation-shipping mb-4">
                    <strong>Shipping to:</strong>
                    <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
                </div>

                <div class="text-center">
                    <a href="products.php" class="primary-action">
                        <span>Continue Shopping</span><i class="icon-long-arrow-right"></i>
                    </a>
                </div>

            <?php endif; ?>
        </div><!-- End .container -->
    </div><!-- End .storefront-page -->

    <?php require __DIR__ . '/../includes/footer.php'; ?>
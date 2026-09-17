<?php
/**
 * public/order-confirmation.php
 * ------------------------------------------------
 * Shown right after checkout.php places an order: ?order=ORD-XXXXX
 * Only the customer who placed the order (or an admin) can view it.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

$db = new Database($conn);
Session::start();

Auth::requireLogin('login.php');

$orderNumber = trim($_GET['order'] ?? '');

$order = $orderNumber !== '' ? $db->fetchOne(
    'SELECT * FROM orders WHERE order_number = ? AND user_id = ?',
    [$orderNumber, Session::get('user_id')],
    'si'
) : null;

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

<div class="container mb-5 mt-4">
    <?php if (!$order): ?>

        <div class="text-center py-5">
            <h2>Order not found</h2>
            <p>We couldn't find that order under your account.</p>
            <a href="index.php" class="btn btn-primary">Back to Home</a>
        </div>

    <?php else: ?>

        <div class="text-center mb-4">
            <h1 class="title">Thank you for your order!</h1>
            <p>Order <strong><?php echo htmlspecialchars($order['order_number']); ?></strong> has been placed.</p>
            <p>Payment method: <strong><?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?></strong>
                &middot; Status: <strong><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></strong></p>
        </div>

        <table class="table table-bordered">
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

        <div class="mb-4">
            <strong>Shipping to:</strong>
            <p><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
        </div>

        <div class="text-center">
            <a href="products.php" class="btn btn-primary btn-round">
                <span>Continue Shopping</span><i class="icon-long-arrow-right"></i>
            </a>
        </div>

    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');
$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$order = $db->fetchOne('SELECT o.*, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?', [$id], 'i');
if (!$order) {
    Session::flash('error', 'Order not found.');
    header('Location: index.php');
    exit;
}

$allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $orderStatus = $_POST['order_status'] ?? '';
    if (in_array($orderStatus, $allowedStatuses, true)) {
        $db->execute('UPDATE orders SET order_status = ? WHERE id = ?', [$orderStatus, $id], 'si');
        Session::flash('success', 'Order status updated.');
        header('Location: detail.php?id=' . $id);
        exit;
    }
    Session::flash('error', 'Invalid order status.');
}

$items = $db->fetchAll('SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name FROM order_items oi JOIN products p ON p.id = oi.product_id WHERE oi.order_id = ?', [$id], 'i');
$pageTitle = 'Order ' . $order['order_number'];
$activeNav = 'orders';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h4 font-weight-bolder mb-0">Order <?php echo htmlspecialchars($order['order_number']); ?></h3><a
        href="index.php" class="btn btn-outline-secondary">Back to Orders</a>
</div>
<?php $flashSuccess = Session::flash('success');
$flashError = Session::flash('error');
if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
<?php endif; ?><?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>
<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <h6>Items</h6>
            </div>
            <div class="card-body px-0 pt-0">
                <div class="table-responsive">
                    <table class="table mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Product</th>
                                <th>Quantity</th>
                                <th>Unit price</th>
                                <th>Subtotal</th>
                            </tr>
                        </thead>
                        <tbody><?php foreach ($items as $item): ?>
                                <tr>
                                    <td class="ps-3"><?php echo htmlspecialchars($item['name']); ?></td>
                                    <td><?php echo (int) $item['quantity']; ?></td>
                                    <td>$<?php echo number_format($item['unit_price'], 2); ?></td>
                                    <td>$<?php echo number_format($item['subtotal'], 2); ?></td>
                                </tr><?php endforeach; ?>
                        </tbody>
                        <tfoot>
                            <tr>
                                <th colspan="3" class="text-end">Total</th>
                                <th>$<?php echo number_format($order['total_amount'], 2); ?></th>
                            </tr>
                        </tfoot>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5">
        <div class="card mb-4">
            <div class="card-header pb-0">
                <h6>Update Status</h6>
            </div>
            <div class="card-body">
                <form method="post" action="detail.php?id=<?php echo $id; ?>"><input type="hidden" name="id"
                        value="<?php echo $id; ?>"><select name="order_status"
                        class="form-control mb-3"><?php foreach ($allowedStatuses as $status): ?>
                            <option value="<?php echo $status; ?>" <?php echo $status === $order['order_status'] ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?>
                    </select><button type="submit" class="btn bg-gradient-dark">Save Status</button></form>
            </div>
        </div>
        <div class="card">
            <div class="card-header pb-0">
                <h6>Customer and Shipping</h6>
            </div>
            <div class="card-body">
                <p class="mb-1"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                <p><?php echo htmlspecialchars($order['customer_email']); ?></p>
                <p class="mb-1"><strong>Payment:</strong>
                    <?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?>
                    (<?php echo htmlspecialchars($order['payment_status']); ?>)</p>
                <p class="mb-0"><strong>Ship
                        to:</strong><br><?php echo nl2br(htmlspecialchars($order['shipping_address'])); ?></p>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
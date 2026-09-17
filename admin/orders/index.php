<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

$allowedStatuses = ['pending', 'processing', 'shipped', 'delivered', 'cancelled'];
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'update_status') {
    $orderId = (int) ($_POST['id'] ?? 0);
    $newStatus = $_POST['order_status'] ?? '';
    if ($orderId > 0 && in_array($newStatus, $allowedStatuses, true)) {
        $db->execute('UPDATE orders SET order_status = ? WHERE id = ?', [$newStatus, $orderId], 'si');
        Session::flash('success', 'Order status updated.');
    } else {
        Session::flash('error', 'Invalid order status.');
    }
    header('Location: index.php');
    exit;
}

$statusFilter = trim($_GET['status'] ?? '');
$search = trim($_GET['q'] ?? '');
$where = 'WHERE 1=1';
$params = [];
$types = '';
if ($statusFilter !== '') {
    $where .= ' AND o.order_status = ?';
    $params[] = $statusFilter;
    $types .= 's';
}
if ($search !== '') {
    $where .= ' AND (o.order_number LIKE ? OR u.name LIKE ? OR u.email LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $params[] = $term;
    $types .= 'sss';
}

$orders = $db->fetchAll("SELECT o.id, o.order_number, o.total_amount, o.payment_method, o.payment_status, o.order_status, o.created_at, u.name AS customer_name, u.email AS customer_email FROM orders o JOIN users u ON u.id = o.user_id $where ORDER BY o.created_at DESC", $params, $types);
$pageTitle = 'Orders';
$activeNav = 'orders';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h4 font-weight-bolder mb-0">Orders</h3>
</div>
<?php $flashSuccess = Session::flash('success');
$flashError = Session::flash('error');
if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
<?php endif; ?><?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>
<div class="card mb-3">
    <div class="card-body py-3">
        <form action="index.php" method="get" class="row g-2">
            <div class="col-md-6"><label for="order-search" class="form-label text-sm mb-1">Search orders</label><input
                    id="order-search" type="text" name="q" class="form-control px-3"
                    style="border: 1px solid #d2d6da; border-radius: 0.5rem; min-height: 42px;"
                    placeholder="Search order or customer..." value="<?php echo htmlspecialchars($search); ?>"></div>
            <div class="col-md-4"><label for="order-status-filter" class="form-label text-sm mb-1">Filter by
                    status</label><select id="order-status-filter" name="status" class="form-control px-3"
                    style="border: 1px solid #d2d6da; border-radius: 0.5rem; min-height: 42px;">
                    <option value="">All statuses</option>
                    <?php foreach (['pending', 'processing', 'shipped', 'delivered', 'cancelled'] as $status): ?>
                        <option value="<?php echo $status; ?>" <?php echo $status === $statusFilter ? 'selected' : ''; ?>>
                            <?php echo ucfirst($status); ?>
                        </option><?php endforeach; ?>
                </select></div>
            <div class="col-md-2 d-flex align-items-end"><button type="submit"
                    class="btn bg-gradient-dark w-100 mb-0">Filter</button></div>
        </form>
    </div>
</div>
<div class="card">
    <div class="card-body px-0 pt-0 pb-2">
        <div class="table-responsive p-0"><?php if (empty($orders)): ?>
                <p class="px-3 py-3">No orders found.</p><?php else: ?>
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-3">Order</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Customer</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Date</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Total</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Payment</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Status</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($orders as $order): ?>
                            <tr>
                                <td class="ps-3"><a href="detail.php?id=<?php echo (int) $order['id']; ?>"
                                        class="text-xs font-weight-bold"><?php echo htmlspecialchars($order['order_number']); ?></a>
                                </td>
                                <td><span
                                        class="text-xs"><?php echo htmlspecialchars($order['customer_name']); ?></span><br><span
                                        class="text-xs text-secondary"><?php echo htmlspecialchars($order['customer_email']); ?></span>
                                </td>
                                <td class="text-xs"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td class="text-xs">
                                    <?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?><br><span
                                        class="text-secondary"><?php echo htmlspecialchars($order['payment_status']); ?></span>
                                </td>
                                <td>
                                    <form action="index.php" method="post" class="d-flex align-items-center gap-2"><input
                                            type="hidden" name="action" value="update_status"><input type="hidden" name="id"
                                            value="<?php echo (int) $order['id']; ?>"><select name="order_status"
                                            class="form-control form-control-sm px-2"
                                            style="border: 1px solid #d2d6da; border-radius: 0.35rem; min-width: 120px;"><?php foreach ($allowedStatuses as $status): ?>
                                                <option value="<?php echo $status; ?>" <?php echo $status === $order['order_status'] ? 'selected' : ''; ?>><?php echo ucfirst($status); ?></option><?php endforeach; ?>
                                        </select><button type="submit"
                                            class="btn btn-link text-primary text-xs font-weight-bold p-0 m-0">Save</button>
                                    </form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table><?php endif; ?>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
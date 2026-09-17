<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('login.php');

$salesRow = $db->fetchOne("SELECT COALESCE(SUM(total_amount), 0) AS total FROM orders WHERE payment_status = 'completed' OR payment_method = 'cod'");
$totalSales = (float) ($salesRow['total'] ?? 0);
$totalOrders = (int) $db->fetchOne('SELECT COUNT(*) AS total FROM orders')['total'];
$activeProducts = (int) $db->fetchOne('SELECT COUNT(*) AS total FROM products WHERE status = 1')['total'];
$customers = (int) $db->fetchOne("SELECT COUNT(*) AS total FROM users WHERE role = 'customer'")['total'];
$pendingOrders = (int) $db->fetchOne("SELECT COUNT(*) AS total FROM orders WHERE order_status IN ('pending', 'processing')")['total'];
$lowStockCount = (int) $db->fetchOne('SELECT COUNT(*) AS total FROM products WHERE status = 1 AND stock <= 5')['total'];
$hiddenProducts = (int) $db->fetchOne('SELECT COUNT(*) AS total FROM products WHERE status = 0')['total'];
$inactiveCustomers = (int) $db->fetchOne("SELECT COUNT(*) AS total FROM users WHERE role = 'customer' AND is_active = 0")['total'];

$weeklyRows = $db->fetchAll("SELECT DATE(created_at) AS day, SUM(total_amount) AS sales FROM orders WHERE created_at >= NOW() - INTERVAL 7 DAY GROUP BY day ORDER BY day ASC");
$salesByDate = [];
foreach ($weeklyRows as $row) {
    $salesByDate[$row['day']] = (float) $row['sales'];
}
$chartLabels = [];
$chartData = [];
for ($i = 6; $i >= 0; $i--) {
    $date = date('Y-m-d', strtotime("-$i days"));
    $chartLabels[] = date('D', strtotime($date));
    $chartData[] = $salesByDate[$date] ?? 0;
}

$statusRows = $db->fetchAll('SELECT order_status, COUNT(*) AS total FROM orders GROUP BY order_status');
$statusLabels = [];
$statusData = [];
foreach ($statusRows as $row) {
    $statusLabels[] = ucfirst($row['order_status']);
    $statusData[] = (int) $row['total'];
}

$recentOrders = $db->fetchAll("SELECT o.id, o.order_number, o.total_amount, o.order_status, o.created_at, u.name AS customer_name FROM orders o JOIN users u ON u.id = o.user_id ORDER BY o.created_at DESC LIMIT 6");
$lowStockProducts = $db->fetchAll('SELECT id, name, stock FROM products WHERE status = 1 AND stock <= 5 ORDER BY stock ASC, name ASC LIMIT 6');
$topProducts = $db->fetchAll('SELECT p.id, p.name, SUM(oi.quantity) AS units_sold FROM order_items oi JOIN products p ON p.id = oi.product_id GROUP BY p.id, p.name ORDER BY units_sold DESC LIMIT 5');
$categories = $db->fetchAll('SELECT c.id, c.name, c.status, COUNT(p.id) AS product_count FROM categories c LEFT JOIN products p ON p.category_id = c.id GROUP BY c.id, c.name, c.status ORDER BY product_count DESC, c.name ASC LIMIT 6');
$recentCustomers = $db->fetchAll("SELECT name, email, created_at, is_active FROM users WHERE role = 'customer' ORDER BY created_at DESC LIMIT 5");

$pageTitle = 'Dashboard';
$activeNav = 'dashboard';
$adminRoot = '';
$includeCharts = true;
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h3 class="mb-1 h4 font-weight-bolder">Store overview</h3>
        <p class="mb-0 text-sm">A live view of orders, inventory, customers, and store health.</p>
    </div>
    <div><a href="products/create.php" class="btn bg-gradient-dark mb-0">Add Product</a> <a href="orders/index.php"
            class="btn btn-outline-dark mb-0">View Orders</a></div>
</div>

<div class="row">
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <p class="text-sm mb-1">Total sales</p>
                <h4 class="mb-1">$<?php echo number_format($totalSales, 2); ?></h4><a href="reports.php"
                    class="text-xs text-success">Open reports</a>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <p class="text-sm mb-1">Total orders</p>
                <h4 class="mb-1"><?php echo $totalOrders; ?></h4><a href="orders/index.php"
                    class="text-xs text-primary"><?php echo $pendingOrders; ?> need attention</a>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <p class="text-sm mb-1">Active products</p>
                <h4 class="mb-1"><?php echo $activeProducts; ?></h4><a href="products/index.php"
                    class="text-xs text-warning"><?php echo $lowStockCount; ?> low stock</a>
            </div>
        </div>
    </div>
    <div class="col-xl-3 col-sm-6 mb-4">
        <div class="card">
            <div class="card-body p-3">
                <p class="text-sm mb-1">Customers</p>
                <h4 class="mb-1"><?php echo $customers; ?></h4><a href="users/index.php"
                    class="text-xs text-secondary"><?php echo $inactiveCustomers; ?> inactive</a>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-8 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-0">Sales, last 7 days</h6>
                <p class="text-sm mb-3">Revenue recorded from completed and cash-on-delivery orders.</p>
                <div class="chart"><canvas id="chart-weekly-sales" height="150"></canvas></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-body">
                <h6 class="mb-0">Order status</h6>
                <p class="text-sm mb-3">Where current orders are in the process.</p>
                <div class="chart"><canvas id="chart-order-status" height="210"></canvas></div>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-7 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>Recent orders</h6>
                        <p class="text-sm">Latest activity from your store.</p>
                    </div><a href="orders/index.php" class="text-sm">View all</a>
                </div>
            </div>
            <div class="card-body px-0 pt-0 pb-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <thead>
                            <tr>
                                <th class="ps-3">Order</th>
                                <th>Customer</th>
                                <th>Total</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody><?php if (empty($recentOrders)): ?>
                                <tr>
                                    <td colspan="4" class="ps-3">No orders yet.</td>
                                </tr><?php else:
                            foreach ($recentOrders as $order): ?>
                                    <tr>
                                        <td class="ps-3"><a href="orders/detail.php?id=<?php echo (int) $order['id']; ?>"
                                                class="text-xs font-weight-bold"><?php echo htmlspecialchars($order['order_number']); ?></a><br><span
                                                class="text-xs text-secondary"><?php echo date('M j, Y', strtotime($order['created_at'])); ?></span>
                                        </td>
                                        <td class="text-xs"><?php echo htmlspecialchars($order['customer_name']); ?></td>
                                        <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                        <td><span
                                                class="badge badge-sm bg-gradient-secondary"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span>
                                        </td>
                                    </tr><?php endforeach; endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    <div class="col-lg-5 mb-4">
        <div class="card">
            <div class="card-header pb-0">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>Inventory alerts</h6>
                        <p class="text-sm">Products with five or fewer units.</p>
                    </div><a href="products/index.php" class="text-sm">Manage</a>
                </div>
            </div>
            <div class="card-body pt-2"><?php if (empty($lowStockProducts)): ?>
                    <p class="text-sm mb-0">Inventory looks healthy.</p>
                <?php else:
                foreach ($lowStockProducts as $product): ?>
                        <div class="d-flex justify-content-between align-items-center border-bottom py-2"><a
                                href="products/edit.php?id=<?php echo (int) $product['id']; ?>"
                                class="text-sm"><?php echo htmlspecialchars($product['name']); ?></a><span
                                class="badge badge-sm <?php echo (int) $product['stock'] === 0 ? 'bg-gradient-danger' : 'bg-gradient-warning'; ?>"><?php echo (int) $product['stock']; ?>
                                left</span></div><?php endforeach; endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Top products</h6>
                <p class="text-sm">Most units sold across orders.</p>
            </div>
            <div class="card-body pt-2"><?php if (empty($topProducts)): ?>
                    <p class="text-sm">No sales data yet.</p><?php else:
                foreach ($topProducts as $product): ?>
                        <div class="d-flex justify-content-between border-bottom py-2"><span
                                class="text-sm"><?php echo htmlspecialchars($product['name']); ?></span><strong
                                class="text-sm"><?php echo (int) $product['units_sold']; ?></strong></div>
                    <?php endforeach; endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>Categories</h6>
                        <p class="text-sm">Catalog coverage by category.</p>
                    </div><a href="categories/index.php" class="text-sm">Manage</a>
                </div>
            </div>
            <div class="card-body pt-2"><?php foreach ($categories as $category): ?>
                    <div class="d-flex justify-content-between border-bottom py-2"><span
                            class="text-sm"><?php echo htmlspecialchars($category['name']); ?></span><span
                            class="text-xs text-secondary"><?php echo (int) $category['product_count']; ?> products</span>
                    </div><?php endforeach;
            if (empty($categories)): ?>
                    <p class="text-sm">No categories yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <div class="d-flex justify-content-between">
                    <div>
                        <h6>New customers</h6>
                        <p class="text-sm">Most recently registered accounts.</p>
                    </div><a href="users/index.php" class="text-sm">Manage</a>
                </div>
            </div>
            <div class="card-body pt-2"><?php foreach ($recentCustomers as $customer): ?>
                    <div class="d-flex justify-content-between border-bottom py-2">
                        <div><span class="text-sm d-block"><?php echo htmlspecialchars($customer['name']); ?></span><span
                                class="text-xs text-secondary"><?php echo htmlspecialchars($customer['email']); ?></span>
                        </div><?php if (!$customer['is_active']): ?><span
                                class="badge badge-sm bg-gradient-secondary">Inactive</span><?php endif; ?>
                    </div><?php endforeach;
            if (empty($recentCustomers)): ?>
                    <p class="text-sm">No customers yet.</p><?php endif; ?>
            </div>
        </div>
    </div>
</div>

<div class="row">
    <div class="col-12">
        <div class="card">
            <div class="card-body">
                <h6 class="mb-3">Store health</h6>
                <div class="row">
                    <div class="col-md-4"><a href="products/index.php"
                            class="d-flex justify-content-between border-bottom py-2"><span class="text-sm">Hidden
                                products</span><strong><?php echo $hiddenProducts; ?></strong></a></div>
                    <div class="col-md-4"><a href="orders/index.php?status=processing"
                            class="d-flex justify-content-between border-bottom py-2"><span class="text-sm">Processing
                                orders</span><strong><?php echo $pendingOrders; ?></strong></a></div>
                    <div class="col-md-4"><a href="reports.php"
                            class="d-flex justify-content-between border-bottom py-2"><span class="text-sm">Detailed
                                reports</span><strong>Open</strong></a></div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
    new Chart(document.getElementById('chart-weekly-sales'), { type: 'bar', data: { labels: <?php echo json_encode($chartLabels); ?>, datasets: [{ label: 'Sales', data: <?php echo json_encode($chartData); ?>, backgroundColor: '#344767', borderRadius: 4, maxBarThickness: 38 }] }, options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true }, x: { grid: { display: false } } } } });
    new Chart(document.getElementById('chart-order-status'), { type: 'doughnut', data: { labels: <?php echo json_encode($statusLabels ?: ['No orders']); ?>, datasets: [{ data: <?php echo json_encode($statusData ?: [1]); ?>, backgroundColor: ['#344767', '#49a078', '#5e72e4', '#fbcf33', '#f5365c'] }] }, options: { responsive: true, plugins: { legend: { position: 'bottom' } } } });
</script>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
<?php
/**
 * admin/reports.php
 * ------------------------------------------------
 * Sales Analytics & Reports: three tabbed summaries — Weekly,
 * Monthly, and Yearly — each with its own chart + table, plus
 * supporting breakdowns (category, payment method, low stock)
 * below.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('login.php');

// ---------------------------------------------------------------
// WEEKLY — last 7 days, one row per day, filling in $0 / 0 orders
// for any day that had no orders at all.
// ---------------------------------------------------------------
$weeklyRows = $db->fetchAll(
  "SELECT DATE(created_at) AS day, COUNT(*) AS orders, SUM(total_amount) AS sales
     FROM orders
     WHERE created_at >= NOW() - INTERVAL 7 DAY
     GROUP BY day"
);
$weeklyByDate = [];
foreach ($weeklyRows as $row) {
  $weeklyByDate[$row['day']] = $row;
}

$weeklyLabels = [];
$weeklySales = [];
$weeklyTable = [];
for ($i = 6; $i >= 0; $i--) {
  $date = date('Y-m-d', strtotime("-$i days"));
  $label = date('D, M j', strtotime($date));
  $row = $weeklyByDate[$date] ?? ['orders' => 0, 'sales' => 0];
  $weeklyLabels[] = date('D', strtotime($date));
  $weeklySales[] = (float) $row['sales'];
  $weeklyTable[] = ['label' => $label, 'orders' => (int) $row['orders'], 'sales' => (float) $row['sales']];
}
$weeklyTotal = array_sum($weeklySales);

// ---------------------------------------------------------------
// MONTHLY — current calendar year, one row per month, filling in
// $0 / 0 orders for months with no orders (Jan through current month).
// ---------------------------------------------------------------
$monthlyRows = $db->fetchAll(
  "SELECT MONTH(created_at) AS month_num, COUNT(*) AS orders, SUM(total_amount) AS sales
     FROM orders
     WHERE YEAR(created_at) = YEAR(CURDATE())
     GROUP BY MONTH(created_at)"
);
$monthlyByNum = [];
foreach ($monthlyRows as $row) {
  $monthlyByNum[(int) $row['month_num']] = $row;
}

$currentMonth = (int) date('n');
$monthlyLabels = [];
$monthlySales = [];
$monthlyTable = [];
for ($m = 1; $m <= $currentMonth; $m++) {
  $row = $monthlyByNum[$m] ?? ['orders' => 0, 'sales' => 0];
  $label = date('F', mktime(0, 0, 0, $m, 1));
  $monthlyLabels[] = date('M', mktime(0, 0, 0, $m, 1));
  $monthlySales[] = (float) $row['sales'];
  $monthlyTable[] = ['label' => $label, 'orders' => (int) $row['orders'], 'sales' => (float) $row['sales']];
}
$monthlyTotal = array_sum($monthlySales);

// ---------------------------------------------------------------
// YEARLY — every year that has at least one order.
// ---------------------------------------------------------------
$yearlyRows = $db->fetchAll(
  "SELECT YEAR(created_at) AS year, COUNT(*) AS orders, SUM(total_amount) AS sales
     FROM orders
     GROUP BY year
     ORDER BY year ASC"
);
$yearlyLabels = [];
$yearlySales = [];
$yearlyTable = [];
foreach ($yearlyRows as $row) {
  $yearlyLabels[] = (string) $row['year'];
  $yearlySales[] = (float) $row['sales'];
  $yearlyTable[] = ['label' => (string) $row['year'], 'orders' => (int) $row['orders'], 'sales' => (float) $row['sales']];
}
$yearlyTotal = array_sum($yearlySales);

// ---------------------------------------------------------------
// Supporting breakdowns (unchanged from before)
// ---------------------------------------------------------------
$categorySales = $db->fetchAll('SELECT c.name, COALESCE(SUM(oi.subtotal), 0) AS sales FROM categories c LEFT JOIN products p ON p.category_id = c.id LEFT JOIN order_items oi ON oi.product_id = p.id GROUP BY c.id, c.name ORDER BY sales DESC');
$orderStatuses = $db->fetchAll('SELECT order_status, COUNT(*) AS total FROM orders GROUP BY order_status ORDER BY total DESC');
$paymentMethods = $db->fetchAll('SELECT payment_method, COUNT(*) AS total, COALESCE(SUM(total_amount), 0) AS sales FROM orders GROUP BY payment_method ORDER BY sales DESC');
$lowStock = $db->fetchAll('SELECT id, name, stock FROM products WHERE status = 1 AND stock <= 5 ORDER BY stock ASC, name ASC');

$pageTitle = 'Reports';
$activeNav = 'reports';
$adminRoot = '';
$includeCharts = true;
require __DIR__ . '/../includes/admin-header.php';
?>

<div class="mb-4">
    <h3 class="mb-1 h4 font-weight-bolder">Sales Analytics &amp; Reports</h3>
    <p class="mb-0 text-sm">Weekly, monthly, and yearly sales summaries, plus category, payment, and inventory breakdowns.</p>
</div>

<div class="card mb-4">
    <div class="card-header pb-0">
        <ul class="nav nav-tabs" id="salesReportTabs" role="tablist">
            <li class="nav-item" role="presentation">
                <a class="nav-link active" id="weekly-tab" data-bs-toggle="tab" href="#weekly-pane" role="tab" aria-selected="true">Weekly</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="monthly-tab" data-bs-toggle="tab" href="#monthly-pane" role="tab" aria-selected="false">Monthly</a>
            </li>
            <li class="nav-item" role="presentation">
                <a class="nav-link" id="yearly-tab" data-bs-toggle="tab" href="#yearly-pane" role="tab" aria-selected="false">Yearly</a>
            </li>
        </ul>
    </div>

    <div class="card-body">
        <div class="tab-content" id="salesReportTabsContent">

            <!-- WEEKLY -->
            <div class="tab-pane fade show active" id="weekly-pane" role="tabpanel">
                <div class="row">
                    <div class="col-lg-7">
                        <p class="text-sm mb-2">Last 7 days &middot; Total: <strong>$<?php echo number_format($weeklyTotal, 2); ?></strong></p>
                        <div class="chart"><canvas id="weekly-chart" height="180"></canvas></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-2">Day</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder">Orders</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder text-end pe-2">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($weeklyTable as $row): ?>
                                      <tr>
                                          <td class="ps-2 text-xs"><?php echo htmlspecialchars($row['label']); ?></td>
                                          <td class="text-xs"><?php echo $row['orders']; ?></td>
                                          <td class="text-xs text-end pe-2">$<?php echo number_format($row['sales'], 2); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <th class="ps-2">Total</th>
                                        <th></th>
                                        <th class="text-end pe-2">$<?php echo number_format($weeklyTotal, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- MONTHLY -->
            <div class="tab-pane fade" id="monthly-pane" role="tabpanel">
                <div class="row">
                    <div class="col-lg-7">
                        <p class="text-sm mb-2"><?php echo date('Y'); ?> so far &middot; Total: <strong>$<?php echo number_format($monthlyTotal, 2); ?></strong></p>
                        <div class="chart"><canvas id="monthly-chart" height="180"></canvas></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-2">Month</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder">Orders</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder text-end pe-2">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($monthlyTable as $row): ?>
                                      <tr>
                                          <td class="ps-2 text-xs"><?php echo htmlspecialchars($row['label']); ?></td>
                                          <td class="text-xs"><?php echo $row['orders']; ?></td>
                                          <td class="text-xs text-end pe-2">$<?php echo number_format($row['sales'], 2); ?></td>
                                      </tr>
                                    <?php endforeach; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <th class="ps-2">Total</th>
                                        <th></th>
                                        <th class="text-end pe-2">$<?php echo number_format($monthlyTotal, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

            <!-- YEARLY -->
            <div class="tab-pane fade" id="yearly-pane" role="tabpanel">
                <div class="row">
                    <div class="col-lg-7">
                        <p class="text-sm mb-2">All time &middot; Total: <strong>$<?php echo number_format($yearlyTotal, 2); ?></strong></p>
                        <div class="chart"><canvas id="yearly-chart" height="180"></canvas></div>
                    </div>
                    <div class="col-lg-5">
                        <div class="table-responsive">
                            <table class="table align-items-center mb-0">
                                <thead>
                                    <tr>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-2">Year</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder">Orders</th>
                                        <th class="text-uppercase text-secondary text-xs font-weight-bolder text-end pe-2">Sales</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php if (empty($yearlyTable)): ?>
                                      <tr><td class="ps-2 text-xs" colspan="3">No orders yet.</td></tr>
                                    <?php else: ?>
                                      <?php foreach ($yearlyTable as $row): ?>
                                        <tr>
                                            <td class="ps-2 text-xs"><?php echo htmlspecialchars($row['label']); ?></td>
                                            <td class="text-xs"><?php echo $row['orders']; ?></td>
                                            <td class="text-xs text-end pe-2">$<?php echo number_format($row['sales'], 2); ?></td>
                                        </tr>
                                      <?php endforeach; ?>
                                    <?php endif; ?>
                                </tbody>
                                <tfoot>
                                    <tr class="border-top">
                                        <th class="ps-2">Total</th>
                                        <th></th>
                                        <th class="text-end pe-2">$<?php echo number_format($yearlyTotal, 2); ?></th>
                                    </tr>
                                </tfoot>
                            </table>
                        </div>
                    </div>
                </div>
            </div>

        </div>
    </div>
</div>

<div class="row">
    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Order statuses</h6>
            </div>
            <div class="card-body px-0 pt-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <tbody>
                            <?php foreach ($orderStatuses as $row): ?>
                              <tr>
                                  <td class="ps-3 text-xs"><?php echo htmlspecialchars(ucfirst($row['order_status'])); ?></td>
                                  <td class="text-end pe-3 font-weight-bold text-xs"><?php echo (int) $row['total']; ?></td>
                              </tr>
                            <?php endforeach; ?>
                            <?php if (empty($orderStatuses)): ?>
                              <tr><td class="ps-3 text-xs">No orders yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Sales by category</h6>
            </div>
            <div class="card-body px-0 pt-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <tbody>
                            <?php foreach ($categorySales as $row): ?>
                              <tr>
                                  <td class="ps-3 text-xs"><?php echo htmlspecialchars($row['name']); ?></td>
                                  <td class="text-end pe-3 text-xs">$<?php echo number_format($row['sales'], 2); ?></td>
                              </tr>
                            <?php endforeach; ?>
                            <?php if (empty($categorySales)): ?>
                              <tr><td class="ps-3 text-xs">No categories yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>

    <div class="col-lg-4 mb-4">
        <div class="card h-100">
            <div class="card-header pb-0">
                <h6>Payment methods</h6>
            </div>
            <div class="card-body px-0 pt-2">
                <div class="table-responsive">
                    <table class="table align-items-center mb-0">
                        <tbody>
                            <?php foreach ($paymentMethods as $row): ?>
                              <tr>
                                  <td class="ps-3 text-xs"><?php echo strtoupper(htmlspecialchars($row['payment_method'])); ?> (<?php echo (int) $row['total']; ?>)</td>
                                  <td class="text-end pe-3 text-xs">$<?php echo number_format($row['sales'], 2); ?></td>
                              </tr>
                            <?php endforeach; ?>
                            <?php if (empty($paymentMethods)): ?>
                              <tr><td class="ps-3 text-xs">No payment data yet.</td></tr>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<div class="card mb-4">
    <div class="card-header pb-0">
        <h6>Inventory requiring attention</h6>
        <p class="text-sm">Active products with five or fewer units remaining.</p>
    </div>
    <div class="card-body px-0 pt-2">
        <div class="table-responsive">
            <table class="table align-items-center mb-0">
                <thead>
                    <tr>
                        <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-3">Product</th>
                        <th class="text-uppercase text-secondary text-xs font-weight-bolder">Stock</th>
                        <th class="text-uppercase text-secondary text-xs font-weight-bolder">Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($lowStock as $product): ?>
                      <tr>
                          <td class="ps-3 text-xs"><?php echo htmlspecialchars($product['name']); ?></td>
                          <td class="text-xs"><?php echo (int) $product['stock']; ?></td>
                          <td><a href="products/edit.php?id=<?php echo (int) $product['id']; ?>" class="text-xs font-weight-bold">Edit product</a></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (empty($lowStock)): ?>
                      <tr><td class="ps-3 text-xs">Inventory looks healthy.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    new Chart(document.getElementById('weekly-chart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($weeklyLabels); ?>,
            datasets: [{ label: 'Sales', data: <?php echo json_encode($weeklySales); ?>, backgroundColor: '#344767', borderRadius: 4, maxBarThickness: 32 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('monthly-chart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($monthlyLabels); ?>,
            datasets: [{ label: 'Sales', data: <?php echo json_encode($monthlySales); ?>, backgroundColor: '#5e72e4', borderRadius: 4, maxBarThickness: 32 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });

    new Chart(document.getElementById('yearly-chart'), {
        type: 'bar',
        data: {
            labels: <?php echo json_encode($yearlyLabels ?: ['No data']); ?>,
            datasets: [{ label: 'Sales', data: <?php echo json_encode($yearlySales ?: [0]); ?>, backgroundColor: '#49a078', borderRadius: 4, maxBarThickness: 60 }]
        },
        options: { responsive: true, plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } }
    });
</script>

<?php require __DIR__ . '/../includes/admin-footer.php'; ?>
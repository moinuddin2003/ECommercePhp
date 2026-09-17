<?php
/**
 * public/account.php
 * ------------------------------------------------
 * Basic "My Account": who you are + your order history.
 * header.php already links here for logged-in users.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

Session::start();
$db = new Database($conn);

Auth::requireLogin('login.php');

$user = $db->fetchOne('SELECT id, name, email, created_at FROM users WHERE id = ?', [Session::get('user_id')], 'i');

$orders = $db->fetchAll(
    'SELECT id, order_number, total_amount, payment_method, payment_status, order_status, created_at
     FROM orders
     WHERE user_id = ?
     ORDER BY created_at DESC',
    [Session::get('user_id')],
    'i'
);

$pageTitle = 'My Account';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <h1 class="title text-center mb-4">My Account</h1>

    <div class="row justify-content-center mb-4">
        <div class="col-md-8">
            <p><strong>Name:</strong> <?php echo htmlspecialchars($user['name']); ?></p>
            <p><strong>Email:</strong> <?php echo htmlspecialchars($user['email']); ?></p>
            <p><strong>Member since:</strong> <?php echo date('F j, Y', strtotime($user['created_at'])); ?></p>
        </div>
    </div>

    <div class="row justify-content-center">
        <div class="col-md-8">
            <h3>Order History</h3>

            <?php if (empty($orders)): ?>
                <p>You haven't placed any orders yet. <a href="products.php">Start shopping</a>.</p>
            <?php else: ?>
                <table class="table">
                    <thead>
                        <tr>
                            <th>Order #</th>
                            <th>Date</th>
                            <th>Total</th>
                            <th>Payment</th>
                            <th>Status</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($orders as $order): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($order['order_number']); ?></td>
                                <td><?php echo date('M j, Y', strtotime($order['created_at'])); ?></td>
                                <td>$<?php echo number_format($order['total_amount'], 2); ?></td>
                                <td><?php echo strtoupper(htmlspecialchars($order['payment_method'])); ?></td>
                                <td><span
                                        class="badge badge-secondary"><?php echo htmlspecialchars(ucfirst($order['order_status'])); ?></span>
                                </td>
                                <td><a
                                        href="order-confirmation.php?order=<?php echo urlencode($order['order_number']); ?>">View</a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
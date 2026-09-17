<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_status') {
    $id = (int) ($_POST['id'] ?? 0);
    if ($id === (int) Session::get('user_id')) {
        Session::flash('error', 'You cannot deactivate your own account.');
    } else {
        $user = $db->fetchOne('SELECT is_active, role FROM users WHERE id = ?', [$id], 'i');
        if ($user && $user['role'] === 'customer') {
            $newStatus = (int) $user['is_active'] === 1 ? 0 : 1;
            $db->execute('UPDATE users SET is_active = ? WHERE id = ? AND role = ?', [$newStatus, $id, 'customer'], 'iis');
            Session::flash('success', $newStatus ? 'Customer activated.' : 'Customer deactivated.');
        }
    }
    header('Location: index.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$params = [];
$types = '';
$where = "WHERE role = 'customer'";
if ($search !== '') {
    $where .= ' AND (name LIKE ? OR email LIKE ?)';
    $term = '%' . $search . '%';
    $params = [$term, $term];
    $types = 'ss';
}
$users = $db->fetchAll("SELECT id, name, email, is_active, created_at FROM users $where ORDER BY created_at DESC", $params, $types);

$pageTitle = 'Customers';
$activeNav = 'users';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h4 font-weight-bolder mb-0">Customers</h3>
</div>

<?php $flashSuccess = Session::flash('success');
$flashError = Session::flash('error'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div><?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div><?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-3">
        <form action="index.php" method="get" class="row g-2">
            <div class="col-md-10"><input type="text" name="q" class="form-control" placeholder="Search customers..."
                    value="<?php echo htmlspecialchars($search); ?>"></div>
            <div class="col-md-2"><button type="submit" class="btn bg-gradient-dark w-100 mb-0">Search</button></div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body px-0 pt-0 pb-2">
        <div class="table-responsive p-0">
            <?php if (empty($users)): ?>
                <p class="px-3 py-3">No customers found.</p>
            <?php else: ?>
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-3">Name</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Email</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Joined</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Status</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($users as $user): ?>
                            <tr>
                                <td class="ps-3"><?php echo htmlspecialchars($user['name']); ?></td>
                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                <td class="text-xs"><?php echo date('M j, Y', strtotime($user['created_at'])); ?></td>
                                <td><?php if ($user['is_active']): ?><span
                                            class="badge badge-sm bg-gradient-success">Active</span><?php else: ?><span
                                            class="badge badge-sm bg-gradient-secondary">Inactive</span><?php endif; ?></td>
                                <td>
                                    <form action="index.php" method="post" class="d-inline">
                                        <input type="hidden" name="action" value="toggle_status">
                                        <input type="hidden" name="id" value="<?php echo (int) $user['id']; ?>">
                                        <button type="submit"
                                            class="btn btn-link text-<?php echo $user['is_active'] ? 'danger' : 'success'; ?> text-xs font-weight-bold p-0 m-0"><?php echo $user['is_active'] ? 'Deactivate' : 'Activate'; ?></button>
                                    </form>
                                </td>
                            </tr><?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
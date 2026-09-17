<?php
/**
 * admin/categories/index.php
 * ------------------------------------------------
 * Lists all categories with edit/delete actions.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Upload.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

// Handle delete (POST, with a confirm() dialog in the JS below)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $category = $db->fetchOne('SELECT image FROM categories WHERE id = ?', [$id], 'i');

    // Deleting a category CASCADEs and deletes its products too (per your schema's
    // ON DELETE CASCADE) — so we warn clearly about that in the confirm dialog below.
    $db->execute('DELETE FROM categories WHERE id = ?', [$id], 'i');

    if ($category) {
        Upload::delete(__DIR__ . '/../../public/uploads/categories', $category['image']);
    }

    Session::flash('success', 'Category deleted.');
    header('Location: index.php');
    exit;
}

$categories = $db->fetchAll(
    "SELECT c.id, c.name, c.slug, c.image, c.status,
            (SELECT COUNT(*) FROM products p WHERE p.category_id = c.id) AS product_count
     FROM categories c
     ORDER BY c.name ASC"
);

$pageTitle = 'Categories';
$activeNav = 'categories';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h4 font-weight-bolder mb-0">Categories</h3>
    <a href="create.php" class="btn bg-gradient-dark mb-0">+ Add Category</a>
</div>

<?php $flash = Session::flash('success'); ?>
<?php if ($flash): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flash); ?></div>
<?php endif; ?>

<div class="card">
    <div class="card-body px-0 pt-0 pb-2">
        <div class="table-responsive p-0">
            <?php if (empty($categories)): ?>
                <p class="px-3 py-3">No categories yet. <a href="create.php">Create your first one</a>.</p>
            <?php else: ?>
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-3">Image</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Name</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Slug</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Products</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Status</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($categories as $cat): ?>
                            <tr>
                                <td class="ps-3">
                                    <?php if ($cat['image']): ?>
                                        <img src="../../public/uploads/categories/<?php echo htmlspecialchars($cat['image']); ?>"
                                            alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px;">
                                    <?php else: ?>
                                        <span class="text-secondary text-xs">No image</span>
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td class="text-xs text-secondary"><?php echo htmlspecialchars($cat['slug']); ?></td>
                                <td><?php echo (int) $cat['product_count']; ?></td>
                                <td>
                                    <?php if ($cat['status']): ?>
                                        <span class="badge badge-sm bg-gradient-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-sm bg-gradient-secondary">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo (int) $cat['id']; ?>"
                                        class="text-secondary font-weight-bold text-xs me-2">Edit</a>
                                    <form action="index.php" method="post" class="d-inline"
                                        onsubmit="return confirm('Delete &quot;<?php echo htmlspecialchars(addslashes($cat['name'])); ?>&quot;? This will also delete its <?php echo (int) $cat['product_count']; ?> product(s). This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $cat['id']; ?>">
                                        <button type="submit"
                                            class="btn btn-link text-danger text-xs font-weight-bold p-0 m-0">Delete</button>
                                    </form>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
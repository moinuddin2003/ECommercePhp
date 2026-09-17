<?php
/**
 * admin/products/index.php
 * ------------------------------------------------
 * Lists products with search + category filter + pagination.
 *
 * Deleting a product can FAIL on purpose: your schema has
 * order_items.product_id -> products.id ON DELETE RESTRICT,
 * meaning the database itself refuses to delete a product that
 * appears in any past order (so order history never breaks).
 * We catch that and tell the admin to deactivate it instead.
 */

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Upload.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int) ($_POST['id'] ?? 0);
    $product = $db->fetchOne('SELECT image FROM products WHERE id = ?', [$id], 'i');

    try {
        $db->execute('DELETE FROM products WHERE id = ?', [$id], 'i');

        if ($product) {
            Upload::delete(__DIR__ . '/../../public/uploads/products', $product['image']);
        }

        Session::flash('success', 'Product deleted.');
    } catch (mysqli_sql_exception $e) {
        // This product exists in at least one past order — the database
        // correctly refuses to delete it so order history stays intact.
        Session::flash('error', 'Cannot delete this product because it appears in existing orders. Try deactivating it instead (uncheck "Active" on the edit page).');
    }

    header('Location: index.php');
    exit;
}

$search = trim($_GET['q'] ?? '');
$categoryFilter = (int) ($_GET['category'] ?? 0);
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 15;
$offset = ($page - 1) * $perPage;

$where = 'WHERE 1=1';
$params = [];
$types = '';

if ($search !== '') {
    $where .= ' AND p.name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

if ($categoryFilter > 0) {
    $where .= ' AND p.category_id = ?';
    $params[] = $categoryFilter;
    $types .= 'i';
}

$countRow = $db->fetchOne("SELECT COUNT(*) AS total FROM products p $where", $params, $types);
$totalProducts = (int) $countRow['total'];
$totalPages = max(1, (int) ceil($totalProducts / $perPage));

$listParams = $params;
$listTypes = $types . 'ii';
$listParams[] = $offset;
$listParams[] = $perPage;

$products = $db->fetchAll(
    "SELECT p.id, p.name, p.slug, p.price, p.stock, p.image, p.status, c.name AS category_name
     FROM products p
     JOIN categories c ON c.id = p.category_id
     $where
     ORDER BY p.created_at DESC
     LIMIT ?, ?",
    $listParams,
    $listTypes
);

$allCategories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');

$pageTitle = 'Products';
$activeNav = 'products';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h3 class="h4 font-weight-bolder mb-0">Products</h3>
    <a href="create.php" class="btn bg-gradient-dark mb-0">+ Add Product</a>
</div>

<?php $flashSuccess = Session::flash('success'); ?>
<?php $flashError = Session::flash('error'); ?>
<?php if ($flashSuccess): ?>
    <div class="alert alert-success"><?php echo htmlspecialchars($flashSuccess); ?></div>
<?php endif; ?>
<?php if ($flashError): ?>
    <div class="alert alert-danger"><?php echo htmlspecialchars($flashError); ?></div>
<?php endif; ?>

<div class="card mb-3">
    <div class="card-body py-3">
        <form action="index.php" method="get" class="row g-2">
            <div class="col-md-5">
                <label for="product-search" class="form-label text-sm mb-1">Search products</label>
                <input id="product-search" type="text" name="q" class="form-control px-3"
                    style="border: 1px solid #d2d6da; border-radius: 0.5rem; min-height: 42px;"
                    placeholder="Search by product name..." value="<?php echo htmlspecialchars($search); ?>">
            </div>
            <div class="col-md-4">
                <label for="product-category" class="form-label text-sm mb-1">Filter by category</label>
                <select id="product-category" name="category" class="form-control px-3"
                    style="border: 1px solid #d2d6da; border-radius: 0.5rem; min-height: 42px;">
                    <option value="0">All Categories</option>
                    <?php foreach ($allCategories as $cat): ?>
                        <option value="<?php echo (int) $cat['id']; ?>" <?php echo $cat['id'] == $categoryFilter ? 'selected' : ''; ?>><?php echo htmlspecialchars($cat['name']); ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3 d-flex align-items-end">
                <button type="submit" class="btn bg-gradient-dark w-100 mb-0">Filter</button>
            </div>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-body px-0 pt-0 pb-2">
        <div class="table-responsive p-0">
            <?php if (empty($products)): ?>
                <p class="px-3 py-3">No products found.</p>
            <?php else: ?>
                <table class="table align-items-center mb-0">
                    <thead>
                        <tr>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder ps-3">Image</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Name</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Category</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Price</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Stock</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Status</th>
                            <th class="text-uppercase text-secondary text-xs font-weight-bolder">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($products as $product): ?>
                            <tr>
                                <td class="ps-3">
                                    <img src="../../public/uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                                        alt="" style="width: 45px; height: 45px; object-fit: cover; border-radius: 6px;">
                                </td>
                                <td><?php echo htmlspecialchars($product['name']); ?></td>
                                <td class="text-xs"><?php echo htmlspecialchars($product['category_name']); ?></td>
                                <td>$<?php echo number_format($product['price'], 2); ?></td>
                                <td>
                                    <?php if ($product['stock'] == 0): ?>
                                        <span class="badge badge-sm bg-gradient-danger">Out of stock</span>
                                    <?php else: ?>
                                        <?php echo (int) $product['stock']; ?>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php if ($product['status']): ?>
                                        <span class="badge badge-sm bg-gradient-success">Active</span>
                                    <?php else: ?>
                                        <span class="badge badge-sm bg-gradient-secondary">Hidden</span>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <a href="edit.php?id=<?php echo (int) $product['id']; ?>"
                                        class="text-secondary font-weight-bold text-xs me-2">Edit</a>
                                    <form action="index.php" method="post" class="d-inline"
                                        onsubmit="return confirm('Delete &quot;<?php echo htmlspecialchars(addslashes($product['name'])); ?>&quot;? This cannot be undone.');">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="id" value="<?php echo (int) $product['id']; ?>">
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

<?php if ($totalPages > 1): ?>
    <nav class="mt-3">
        <ul class="pagination">
            <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                    <a class="page-link"
                        href="index.php?<?php echo http_build_query(['q' => $search, 'category' => $categoryFilter, 'page' => $i]); ?>"><?php echo $i; ?></a>
                </li>
            <?php endfor; ?>
        </ul>
    </nav>
<?php endif; ?>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
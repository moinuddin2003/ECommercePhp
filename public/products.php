<?php
/**
 * public/products.php
 * ------------------------------------------------
 * Full catalog listing. Supports:
 *   ?category=slug   filter by category
 *   ?q=keyword       search by product name
 *   ?page=2          pagination
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

$db = new Database($conn);

$categorySlug = trim($_GET['category'] ?? '');
$search = trim($_GET['q'] ?? '');
$page = max(1, (int) ($_GET['page'] ?? 1));
$perPage = 12;
$offset = ($page - 1) * $perPage;

// Build the WHERE clause + params dynamically based on which filters are active
$where = 'WHERE p.status = 1';
$params = [];
$types = '';

if ($categorySlug !== '') {
    $where .= ' AND c.slug = ?';
    $params[] = $categorySlug;
    $types .= 's';
}

if ($search !== '') {
    $where .= ' AND p.name LIKE ?';
    $params[] = '%' . $search . '%';
    $types .= 's';
}

// Count total matches (for pagination) using the same filters
$countRow = $db->fetchOne(
    "SELECT COUNT(*) AS total FROM products p JOIN categories c ON c.id = p.category_id $where",
    $params,
    $types
);
$totalProducts = (int) ($countRow['total'] ?? 0);
$totalPages = max(1, (int) ceil($totalProducts / $perPage));

// Fetch this page's products
$listParams = $params;
$listTypes = $types . 'ii';
$listParams[] = $offset;
$listParams[] = $perPage;

$products = $db->fetchAll(
    "SELECT p.id, p.name, p.slug, p.price, p.image, p.stock, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     $where
     ORDER BY p.created_at DESC
     LIMIT ?, ?",
    $listParams,
    $listTypes
);

// For the category filter dropdown
$allCategories = $db->fetchAll("SELECT id, name, slug FROM categories WHERE status = 1 ORDER BY name ASC");

// For the page heading — show the category name if one is selected
$activeCategoryName = 'All Products';
if ($categorySlug !== '') {
    foreach ($allCategories as $cat) {
        if ($cat['slug'] === $categorySlug) {
            $activeCategoryName = $cat['name'];
            break;
        }
    }
}

$pageTitle = 'Shop';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5">
    <h1 class="title text-center mt-4 mb-4"><?php echo htmlspecialchars($activeCategoryName); ?></h1>

    <form action="products.php" method="get" class="row justify-content-center mb-4">
        <div class="col-md-4 mb-2">
            <select name="category" class="form-control" onchange="this.form.submit()">
                <option value="">All Categories</option>
                <?php foreach ($allCategories as $cat): ?>
                    <option value="<?php echo htmlspecialchars($cat['slug']); ?>" <?php echo $cat['slug'] === $categorySlug ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($cat['name']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="col-md-4 mb-2">
            <input type="text" name="q" class="form-control" placeholder="Search products..."
                value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="col-md-2 mb-2">
            <button type="submit" class="btn btn-primary btn-block">Search</button>
        </div>
    </form>

    <?php if (empty($products)): ?>
        <p class="text-center">No products found. Try a different search or category.</p>
    <?php else: ?>
        <div class="row">
            <?php foreach ($products as $product): ?>
                <div class="col-6 col-md-4 col-lg-3 mb-4">
                    <?php require __DIR__ . '/../includes/product-card.php'; ?>
                </div>
            <?php endforeach; ?>
        </div>

        <?php if ($totalPages > 1): ?>
            <nav aria-label="Product pagination">
                <ul class="pagination justify-content-center">
                    <?php for ($i = 1; $i <= $totalPages; $i++): ?>
                        <li class="page-item <?php echo $i === $page ? 'active' : ''; ?>">
                            <a class="page-link"
                                href="products.php?<?php echo http_build_query(['category' => $categorySlug, 'q' => $search, 'page' => $i]); ?>">
                                <?php echo $i; ?>
                            </a>
                        </li>
                    <?php endfor; ?>
                </ul>
            </nav>
        <?php endif; ?>

    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
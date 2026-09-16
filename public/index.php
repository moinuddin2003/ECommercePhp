<?php
/**
 * public/index.php
 * ------------------------------------------------
 * Store homepage: promo banner (static) + category
 * showcase + newest products, all pulled from the DB.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

$db = new Database($conn);
$pageTitle = 'Home';

// Categories for the "Shop by Category" section (reuse the same list header.php builds,
// but re-fetch here since we also want the image column for the banner cards).
$showcaseCategories = $db->fetchAll(
    "SELECT id, name, slug, image FROM categories WHERE status = 1 ORDER BY name ASC LIMIT 6"
);

// Newest active products for "New Arrivals"
$newArrivals = $db->fetchAll(
    "SELECT p.id, p.name, p.slug, p.price, p.image, p.stock, c.name AS category_name, c.slug AS category_slug
     FROM products p
     JOIN categories c ON c.id = p.category_id
     WHERE p.status = 1
     ORDER BY p.created_at DESC
     LIMIT 8"
);

require __DIR__ . '/../includes/header.php';
?>

<div class="intro-slider-container mb-5">
    <div class="intro-slider owl-carousel owl-theme owl-nav-inside owl-light" data-toggle="owl"
        data-owl-options='{
            "dots": true,
            "nav": false,
            "responsive": {
                "1200": {
                    "nav": true,
                    "dots": false
                }
            }
        }'>
        <div class="intro-slide" style="background-image: url(assets/images/demos/demo-4/slider/slide-1.png);">
            <div class="container intro-content">
                <div class="row justify-content-end">
                    <div class="col-auto col-sm-7 col-md-6 col-lg-5">
                        <h3 class="intro-subtitle text-third">Deals and Promotions</h3>
                        <h1 class="intro-title">Welcome to</h1>
                        <h1 class="intro-title">MyStore</h1>

                        <a href="products.php" class="btn btn-primary btn-round">
                            <span>Shop Now</span>
                            <i class="icon-long-arrow-right"></i>
                        </a>
                    </div>
                </div>
            </div>
        </div><!-- End .intro-slide -->
    </div>
</div><!-- End .intro-slider-container -->

<div class="container mb-5">
    <h2 class="title text-center mb-4">Shop by Category</h2>

    <?php if (empty($showcaseCategories)): ?>
    <p class="text-center">No categories yet — add some from the admin panel.</p>
    <?php else: ?>
    <div class="row justify-content-center">
        <?php foreach ($showcaseCategories as $cat): ?>
        <div class="col-6 col-md-4 col-lg-2 mb-4 text-center">
            <a href="products.php?category=<?php echo urlencode($cat['slug']); ?>" class="d-block">
                <?php if (!empty($cat['image'])): ?>
                <img src="uploads/categories/<?php echo htmlspecialchars($cat['image']); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>" class="img-fluid rounded mb-2" style="aspect-ratio: 1 / 1; object-fit: cover;">
                <?php endif; ?>
                <span><?php echo htmlspecialchars($cat['name']); ?></span>
            </a>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
</div><!-- End .container -->

<div class="container mb-5">
    <h2 class="title text-center mb-4">New Arrivals</h2>

    <?php if (empty($newArrivals)): ?>
    <p class="text-center">No products yet — add some from the admin panel.</p>
    <?php else: ?>
    <div class="row">
        <?php foreach ($newArrivals as $product): ?>
        <div class="col-6 col-md-4 col-lg-3 mb-4">
            <?php require __DIR__ . '/../includes/product-card.php'; ?>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="text-center">
        <a href="products.php" class="btn btn-outline-primary-2 btn-round">
            <span>View All Products</span><i class="icon-long-arrow-right"></i>
        </a>
    </div>
    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
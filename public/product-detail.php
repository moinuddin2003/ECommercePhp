<?php
/**
 * public/product-detail.php
 * ------------------------------------------------
 * Shows one product by its slug: ?slug=product-name
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';

$db = new Database($conn);

$slug = trim($_GET['slug'] ?? '');

$product = null;
if ($slug !== '') {
    $product = $db->fetchOne(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = 1",
        [$slug],
        's'
    );
}

$pageTitle = $product ? $product['name'] : 'Product Not Found';
require __DIR__ . '/../includes/header.php';
?>

<div class="storefront-page storefront-detail-page">
    <div class="container mb-5 mt-4">
        <?php if (!$product): ?>

            <div class="text-center py-5">
                <h2>Product not found</h2>
                <p>The product you're looking for doesn't exist or is no longer available.</p>
                <a href="products.php" class="btn btn-primary">Back to Shop</a>
            </div>

        <?php else: ?>

            <?php $inStock = (int) $product['stock'] > 0; ?>

            <div class="row align-items-center">
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="detail-image-panel">
                        <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                            alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid">
                    </div>
                </div>

                <div class="col-md-6 detail-copy">
                    <div class="product-cat detail-category mb-3">
                        <a
                            href="products.php?category=<?php echo urlencode($product['category_slug']); ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
                    </div>

                    <h1 class="detail-title mb-3"><?php echo htmlspecialchars($product['name']); ?></h1>

                    <div class="detail-price mb-4">
                        $<?php echo number_format($product['price'], 2); ?>
                    </div>

                    <?php if ($inStock): ?>
                        <p class="detail-stock detail-stock-available mb-4"><i class="icon-check"></i> In stock
                            <span>(<?php echo (int) $product['stock']; ?> available)</span></p>
                    <?php else: ?>
                        <p class="detail-stock detail-stock-unavailable mb-4"><i class="icon-close"></i> Out of stock</p>
                    <?php endif; ?>

                    <div class="detail-description mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>

                    <?php if ($inStock): ?>
                        <form action="cart.php" method="post" class="detail-cart-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">

                            <label for="quantity" class="detail-quantity-label">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="detail-quantity-input" value="1" min="1"
                                max="<?php echo (int) $product['stock']; ?>">

                            <button type="submit" class="detail-cart-button">
                                <i class="icon-shopping-cart"></i><span>Add to cart</span><i class="icon-long-arrow-right"></i>
                            </button>
                        </form>
                    <?php else: ?>
                        <button type="button" class="detail-cart-button" disabled>Out of stock</button>
                    <?php endif; ?>
                </div>
            </div>

        <?php endif; ?>
    </div><!-- End .container -->
</div><!-- End .storefront-page -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
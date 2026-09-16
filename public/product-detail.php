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

<div class="container mb-5 mt-4">
    <?php if (!$product): ?>

        <div class="text-center py-5">
            <h2>Product not found</h2>
            <p>The product you're looking for doesn't exist or is no longer available.</p>
            <a href="products.php" class="btn btn-primary">Back to Shop</a>
        </div>

    <?php else: ?>

        <?php $inStock = (int) $product['stock'] > 0; ?>

        <div class="row">
            <div class="col-md-6 mb-4">
                <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                    alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid rounded">
            </div>

            <div class="col-md-6">
                <div class="product-cat mb-2">
                    <a
                        href="products.php?category=<?php echo urlencode($product['category_slug']); ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
                </div>

                <h1 class="product-title mb-2"><?php echo htmlspecialchars($product['name']); ?></h1>

                <div class="product-price mb-3" style="font-size: 1.5rem;">
                    $<?php echo number_format($product['price'], 2); ?>
                </div>

                <?php if ($inStock): ?>
                    <p class="text-success mb-3">In stock (<?php echo (int) $product['stock']; ?> available)</p>
                <?php else: ?>
                    <p class="text-danger mb-3">Out of stock</p>
                <?php endif; ?>

                <p class="mb-4"><?php echo nl2br(htmlspecialchars($product['description'])); ?></p>

                <?php if ($inStock): ?>
                    <form action="cart.php" method="post" class="d-flex align-items-center">
                        <input type="hidden" name="action" value="add">
                        <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">

                        <label for="quantity" class="sr-only">Quantity</label>
                        <input type="number" id="quantity" name="quantity" class="form-control mr-3" style="width: 90px;"
                            value="1" min="1" max="<?php echo (int) $product['stock']; ?>">

                        <button type="submit" class="btn btn-primary btn-round">
                            <span>Add to Cart</span><i class="icon-long-arrow-right"></i>
                        </button>
                    </form>
                <?php else: ?>
                    <button type="button" class="btn btn-primary btn-round" disabled>Out of Stock</button>
                <?php endif; ?>
            </div>
        </div>

    <?php endif; ?>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
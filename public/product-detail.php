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
require_once __DIR__ . '/../core/ProductImages.php';

$db = new Database($conn);

$slug = trim($_GET['slug'] ?? '');

$product = null;
$gallery = [];

if ($slug !== '') {
    $product = $db->fetchOne(
        "SELECT p.*, c.name AS category_name, c.slug AS category_slug
         FROM products p
         JOIN categories c ON c.id = p.category_id
         WHERE p.slug = ? AND p.status = 1",
        [$slug],
        's'
    );

    // All images for this product, main one first.
    if ($product) {
        $gallery = ProductImages::forProduct($db, $product['id']);

        // A product created before the gallery existed might have an
        // empty images column, so fall back to the single cover image.
        if (empty($gallery) && !empty($product['image'])) {
            $gallery = [['file' => $product['image'], 'sort' => 1]];
        }
    }
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

            <?php $inStock = (int) $product['stock'] > 0; ?>x

            <div class="row align-items-start">

                <!-- Image gallery -->
                <div class="col-md-6 mb-4 mb-md-0">
                    <div class="detail-image-panel">
                        <?php if (!empty($gallery)): ?>
                            <?php $mainImage = $gallery[0]['file']; ?>
                            <img id="gallery-main-image"
                                src="<?php echo htmlspecialchars(ProductImages::url($product['id'], $mainImage)); ?>"
                                alt="<?php echo htmlspecialchars($product['name']); ?>" class="img-fluid">
                        <?php else: ?>
                            <div class="d-flex align-items-center justify-content-center bg-gray-100"
                                style="aspect-ratio: 1/1; border-radius: 8px;">
                                <span class="text-secondary">No image available</span>
                            </div>
                        <?php endif; ?>
                    </div>

                    <?php if (count($gallery) > 1): ?>
                        <div id="gallery-thumbs" class="mt-3"
                            style="display: grid; gap: 8px; grid-template-columns: repeat(5, 1fr);">
                            <?php foreach ($gallery as $index => $img): ?>
                                <button type="button" class="gallery-thumb border-0 p-0 bg-transparent"
                                    data-full="<?php echo htmlspecialchars(ProductImages::url($product['id'], $img['file'])); ?>"
                                    data-index="<?php echo (int) $index; ?>"
                                    style="border-radius: 6px; overflow: hidden; <?php echo $index === 0 ? 'outline: 2px solid #333;' : ''; ?>">
                                    <img src="<?php echo htmlspecialchars(ProductImages::url($product['id'], $img['file'])); ?>"
                                        alt="<?php echo htmlspecialchars($product['name'] . ' image ' . ($index + 1)); ?>"
                                        style="width: 100%; aspect-ratio: 1/1; object-fit: cover; display: block;">
                                </button>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Product info -->
                <div class="col-md-6 detail-copy">
                    <div class="product-cat detail-category mb-3">
                        <a href="products.php?category=<?php echo urlencode($product['category_slug']); ?>">
                            <?php echo htmlspecialchars($product['category_name']); ?>
                        </a>
                    </div>

                    <h1 class="detail-title mb-3">
                        <?php echo htmlspecialchars($product['name']); ?>
                    </h1>

                    <div class="detail-price mb-4">
                        $<?php echo number_format($product['price'], 2); ?>
                    </div>

                    <?php if ($inStock): ?>
                        <p class="detail-stock detail-stock-available mb-4">
                            <i class="icon-check"></i> In stock
                            <span>(<?php echo (int) $product['stock']; ?> available)</span>
                        </p>
                    <?php else: ?>
                        <p class="detail-stock detail-stock-unavailable mb-4">
                            <i class="icon-close"></i> Out of stock
                        </p>
                    <?php endif; ?>

                    <div class="detail-description mb-4">
                        <?php echo nl2br(htmlspecialchars($product['description'])); ?>
                    </div>

                    <?php if ($inStock): ?>
                        <form action="cart.php" method="post" class="detail-cart-form">
                            <input type="hidden" name="action" value="add">
                            <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">

                            <label for="quantity" class="detail-quantity-label">Quantity</label>
                            <input type="number" id="quantity" name="quantity" class="detail-quantity-input" value="1" min="1"
                                max="<?php echo (int) $product['stock']; ?>">

                            <button type="submit" class="detail-cart-button">
                                <i class="icon-shopping-cart"></i>
                                <span>Add to cart</span>
                                <i class="icon-long-arrow-right"></i>
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

<script>
    // Clicking a small thumbnail swaps it into the big picture.
    // The whole list of images is already in the page, so this needs no
    // extra loading from the server.
    const mainImage = document.getElementById('gallery-main-image');
    const thumbRow = document.getElementById('gallery-thumbs');

    if (mainImage && thumbRow) {
        const thumbs = thumbRow.querySelectorAll('.gallery-thumb');

        thumbs.forEach(thumb => {
            thumb.addEventListener('click', () => {
                mainImage.src = thumb.dataset.full;

                // Move the outline to the thumbnail that is now showing.
                thumbs.forEach(t => {
                    t.style.outline = 'none';
                });
                thumb.style.outline = '2px solid #333';
            });
        });
    }
</script>

<?php require __DIR__ . '/../includes/footer.php'; ?>
<?php
/**
 * includes/product-card.php
 * ------------------------------------------------
 * Renders ONE product card. Used by index.php and products.php
 * inside a foreach loop, like this:
 *
 *   <div class="row">
 *   <?php foreach ($products as $product): ?>
 *       <div class="col-6 col-md-4 col-lg-3 mb-4">
 *           <?php require __DIR__ . '/product-card.php'; ?>
 *       </div>
 *   <?php endforeach; ?>
 *   </div>
 *
 * Expects $product to have: id, name, slug, price, image, stock
 * (category name is optional — pass 'category_name' if you have it).
 */

$inStock = (int) $product['stock'] > 0;
?>
<div class="product product-2">
    <figure class="product-media">
        <?php if (!$inStock): ?>
            <span class="product-label label-out">Out of stock</span>
        <?php endif; ?>

        <a href="product-detail.php?slug=<?php echo urlencode($product['slug']); ?>" class="product-image-link">
            <img src="uploads/products/<?php echo htmlspecialchars($product['image']); ?>"
                alt="<?php echo htmlspecialchars($product['name']); ?>" class="product-image">
        </a>
    </figure><!-- End .product-media -->

    <div class="product-body">
        <?php if (!empty($product['category_name'])): ?>
            <div class="product-cat">
                <a
                    href="products.php?category=<?php echo urlencode($product['category_slug'] ?? ''); ?>"><?php echo htmlspecialchars($product['category_name']); ?></a>
            </div><!-- End .product-cat -->
        <?php endif; ?>

        <h3 class="product-title">
            <a
                href="product-detail.php?slug=<?php echo urlencode($product['slug']); ?>"><?php echo htmlspecialchars($product['name']); ?></a>
        </h3><!-- End .product-title -->

        <div class="product-price">
            $<?php echo number_format($product['price'], 2); ?>
        </div><!-- End .product-price -->

        <?php if ($inStock): ?>
            <form action="cart.php" method="post" class="mt-2">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="product_id" value="<?php echo (int) $product['id']; ?>">
                <input type="hidden" name="quantity" value="1">
                <button type="submit" class="btn btn-outline-primary-2 btn-sm">
                    <span>Add to cart</span><i class="icon-long-arrow-right"></i>
                </button>
            </form>
        <?php else: ?>
            <button type="button" class="btn btn-outline-primary-2 btn-sm" disabled>Out of stock</button>
        <?php endif; ?>
    </div><!-- End .product-body -->
</div><!-- End .product -->
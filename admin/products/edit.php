<?php
/**
 * admin/products/edit.php
 * ------------------------------------------------
 * Edit one product, INCLUDING its images.
 *
 * This page handles several jobs, told apart by the "action" field.
 * The image actions are handled FIRST and finish immediately, because
 * deleting an image should not require re-submitting the whole form.
 *
 *   action=save          -> save the product details
 *   action=add_images    -> upload more images
 *   action=delete_image  -> delete one image
 *   action=set_main      -> choose a different main image
 *   action=move_up       -> move an image earlier in the list
 *   action=move_down     -> move an image later in the list
 */
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Upload.php';
require_once __DIR__ . '/../../core/ProductImages.php';
require_once __DIR__ . '/../../core/helpers.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

$uploadDir = __DIR__ . '/../../public/uploads/products';

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $db->fetchOne('SELECT * FROM products WHERE id = ?', [$id]);
if (!$product) {
    Session::flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

$errors = [];

/* ===================================================================
   PART A -- the image actions
   Each one finishes and goes straight back to this page, so the admin
   sees the result immediately.
   =================================================================== */
$action = $_POST['action'] ?? ($_GET['action'] ?? '');

if ($action !== '' && $action !== 'save') {
    // Images are identified by their FILENAME now, not by a row id,
    // because they live in a JSON column instead of their own table.
    $filename = (string) ($_POST['filename'] ?? $_GET['filename'] ?? '');

    if ($action === 'add_images') {
        // ONLY the headroom is offered, never the full maximum. This is
        // what makes "max 5 per product" real: a product with 5 images
        // gets 0 slots and cannot be pushed to 10 by uploading twice.
        $slots = ProductImages::remainingSlots($db, $id);

        $result = Upload::images('images', $uploadDir, $slots);
        foreach ($result['saved'] as $uploadedFilename) {
            ProductImages::add($db, $id, $uploadedFilename, $uploadDir);
        }
        if (!empty($result['errors'])) {
            Session::flash('error', implode(' ', $result['errors']));
        } elseif (!empty($result['saved'])) {
            Session::flash('success', count($result['saved']) . ' image(s) added. This product now has '
                . ProductImages::countForProduct($db, $id) . '.');
        }
    }

    if ($action === 'delete_image') {
        $result = ProductImages::deleteOne($db, $id, $filename);
        if ($result['ok']) {
            Session::flash('success', 'Image deleted. ' . ProductImages::countForProduct($db, $id) . ' remaining.');
        } else {
            Session::flash('error', $result['message']);
        }
    }

    if ($action === 'set_main') {
        $result = ProductImages::setMain($db, $id, $filename);
        Session::flash($result['ok'] ? 'success' : 'error', $result['message']);
    }

    if ($action === 'move_up' || $action === 'move_down') {
        $result = ProductImages::move($db, $id, $filename, $action === 'move_up' ? 'up' : 'down');
        if (!$result['ok']) {
            Session::flash('error', $result['message']);
        }
    }

    header('Location: edit.php?id=' . $id);
    exit;
}
/* ===================================================================
   PART B -- saving the product details
   =================================================================== */
$name = $product['name'];
$description = $product['description'];
$categoryId = (int) $product['category_id'];
$price = $product['price'];
$stock = (int) $product['stock'];
$status = (int) $product['status'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name = trim($_POST['name'] ?? '');
    $description = trim($_POST['description'] ?? '');
    $categoryId = (int) ($_POST['category_id'] ?? 0);
    $price = trim($_POST['price'] ?? '');
    $stock = trim($_POST['stock'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    $v = new Validator();
    $v->required($name, 'name')
        ->required($description, 'description')
        ->required($price, 'price')
        ->required($stock, 'stock')
        ->numeric($price, 'price')
        ->numeric($stock, 'stock');

    if ($categoryId <= 0) {
        $errors['category_id'] = 'Please select a category';
    }
    if ($price !== '' && is_numeric($price) && (float) $price < 0) {
        $errors['price'] = 'Price cannot be negative';
    }
    if ($stock !== '' && is_numeric($stock) && (int) $stock < 0) {
        $errors['stock'] = 'Stock cannot be negative';
    }

    // Optional: add more images in the same submit as the details.
    // Again we pass the REMAINING slots so the 5-per-product cap holds
    // even when images are added together with the product details.
    $uploadResult = ['saved' => [], 'errors' => []];
    if (!empty($_FILES['images']['name'][0])) {
        $slots = ProductImages::remainingSlots($db, $id);
        $uploadResult = Upload::images('images', $uploadDir, $slots);
        foreach ($uploadResult['errors'] as $imageError) {
            $errors['images'][] = $imageError;
        }
    }

    if ($v->passes() && empty($errors)) {
        $slug = ensureUniqueSlug($db, 'products', slugify($name), $id);

        $db->execute(
            'UPDATE products SET name = ?, slug = ?, description = ?, category_id = ?, price = ?, stock = ?, status = ? WHERE id = ?',
            [$name, $slug, $description, $categoryId, (float) $price, (int) $stock, $status, $id]
        );

        foreach ($uploadResult['saved'] as $uploadedFilename) {
            ProductImages::add($db, $id, $uploadedFilename, $uploadDir);
        }

        $imageCount = ProductImages::countForProduct($db, $id);
        if ($imageCount < ProductImages::MIN_IMAGES) {
            Session::flash('error', 'Product saved, but it only has ' . $imageCount
                . ' image(s). Please add at least ' . ProductImages::MIN_IMAGES . '.');
        } else {
            Session::flash('success', 'Product "' . $name . '" updated.');
        }

        header('Location: edit.php?id=' . $id);
        exit;
    }

    $errors = array_merge($v->errors(), $errors);
}

// The image list for the gallery manager below.
$images = ProductImages::forProduct($db, $id);
$imageCount = count($images);
$belowMinimum = $imageCount < ProductImages::MIN_IMAGES;
$slotsLeft = ProductImages::remainingSlots($db, $id);
$isFull = $slotsLeft <= 0;

$categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
$pageTitle = 'Edit Product';
$activeNav = 'products';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
<style>
    .product-edit-form .input-group.input-group-outline .form-control {
        border-color: #cbd0d5;
    }

    .product-edit-form .input-group.input-group-outline.is-focused .form-label,
    .product-edit-form .input-group.input-group-outline.is-filled .form-label {
        color: #515a63;
    }

    .product-edit-form .input-group.input-group-outline.is-focused .form-label:before,
    .product-edit-form .input-group.input-group-outline.is-focused .form-label:after,
    .product-edit-form .input-group.input-group-outline.is-filled .form-label:before,
    .product-edit-form .input-group.input-group-outline.is-filled .form-label:after {
        border-top-color: #aeb5bc;
        box-shadow: none;
    }

    .product-edit-form .input-group.input-group-outline.is-focused .form-label+.form-control,
    .product-edit-form .input-group.input-group-outline.is-filled .form-label+.form-control {
        border-color: #aeb5bc !important;
        border-top-color: transparent !important;
        box-shadow: inset 1px 0 #aeb5bc, inset -1px 0 #aeb5bc, inset 0 -1px #aeb5bc;
    }

    .product-image-manager .product-image-card-body {
        display: flex;
        flex-direction: column;
    }

    .product-image-manager .product-image-filename {
        height: 1.25rem;
        line-height: 1.25rem;
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }

    .product-image-manager .product-image-main-action {
        display: flex;
        align-items: center;
        height: 2rem;
        margin-bottom: 0.5rem;
    }

    .product-image-manager .product-image-main-action .btn,
    .product-image-manager .product-image-main-action .badge {
        margin-bottom: 0;
    }

    .product-image-manager .product-image-file::file-selector-button {
        border: 0 !important;
        margin-inline-end: 0.75rem !important;
        padding: 0.5rem 0.75rem !important;
        border-radius: 0.25rem !important;
        background-color: #f1f2f3 !important;
    }
</style>
<div class="row">
    <div class="col-12">

        <?php if ($belowMinimum): ?>
            <div class="alert alert-warning">
                This product has <strong><?php echo (int) $imageCount; ?></strong> image(s).
                It needs at least <strong><?php echo (int) ProductImages::MIN_IMAGES; ?></strong>.
                Add <?php echo (int) (ProductImages::MIN_IMAGES - $imageCount); ?> more below.
            </div>
        <?php endif; ?>

        <div class="card mb-4">
            <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                <h5 class="mb-0">Edit Product</h5>
                <a href="index.php" class="btn admin-form-button admin-form-button-secondary">Back to products</a>
            </div>
            <div class="card-body">
                <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data"
                    class="product-edit-form admin-fields">
                    <input type="hidden" name="action" value="save">
                    <input type="hidden" name="id" value="<?php echo $id; ?>">

                    <div class="mb-3">
                        <label class="form-label" for="product-name">Product name</label>
                        <input type="text" name="name" id="product-name" class="form-control<?php echo Validator::fieldClass($errors, 'name'); ?>"<?php echo Validator::fieldAttributes($errors, 'name'); ?>
                            value="<?php echo htmlspecialchars($name); ?>">
                        <?php echo Validator::fieldErrorMarkup($errors, 'name'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="product-description">Description</label>
                        <textarea name="description" id="product-description" class="form-control<?php echo Validator::fieldClass($errors, 'description'); ?>" rows="7"<?php echo Validator::fieldAttributes($errors, 'description'); ?>><?php echo htmlspecialchars($description); ?></textarea>
                        <?php echo Validator::fieldErrorMarkup($errors, 'description'); ?>
                    </div>

                    <div class="row g-3">
                        <div class="col-md-6 mb-3">
                            <label class="form-label" for="product-category">Category</label>
                            <select name="category_id" id="product-category" class="form-control form-select<?php echo Validator::fieldClass($errors, 'category_id'); ?>"<?php echo Validator::fieldAttributes($errors, 'category_id'); ?>>
                                <option value="0">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['id']; ?>"
                                        <?php echo (int) $category['id'] === $categoryId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php echo Validator::fieldErrorMarkup($errors, 'category_id'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="product-price">Price (USD)</label>
                            <input type="number" name="price" id="product-price" class="form-control<?php echo Validator::fieldClass($errors, 'price'); ?>" step="0.01" min="0"<?php echo Validator::fieldAttributes($errors, 'price'); ?>
                                value="<?php echo htmlspecialchars($price); ?>">
                            <?php echo Validator::fieldErrorMarkup($errors, 'price'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label class="form-label" for="product-stock">Stock quantity</label>
                            <input type="number" name="stock" id="product-stock" class="form-control<?php echo Validator::fieldClass($errors, 'stock'); ?>" min="0" step="1"<?php echo Validator::fieldAttributes($errors, 'stock'); ?>
                                value="<?php echo htmlspecialchars($stock); ?>">
                            <?php echo Validator::fieldErrorMarkup($errors, 'stock'); ?>
                        </div>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="status"
                            <?php echo $status ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Active (visible in store)</label>
                    </div>

                    <button type="submit" class="btn admin-form-button admin-form-button-primary">Save changes</button>
                </form>
            </div>
        </div>

        <!-- ============ IMAGE MANAGER ============ -->
        <div class="card mb-4 product-image-manager">
            <div class="card-header pb-0">
                <h5 class="mb-1">Product images</h5>
                <p class="text-sm mb-0">
                    This product has <strong><?php echo (int) $imageCount; ?></strong> of
                    <?php echo (int) ProductImages::MAX_IMAGES; ?> images.
                    You must keep at least <?php echo (int) ProductImages::MIN_IMAGES; ?>.
                    Image number 1 is the MAIN picture (used on cards, in the cart and search).
                    Files are named PRODUCTID-NUMBER.jpg, e.g.
                    <code><?php echo (int) $id; ?>-1.jpg</code>.
                </p>
            </div>
            <div class="card-body">

                <?php if (empty($images)): ?>
                    <div class="alert alert-info mb-3">This product has no images yet.</div>
                <?php else: ?>
                    <div class="row g-3">
                        <?php foreach ($images as $index => $img): ?>
                            <?php $isMain = ((int) $img['sort'] === 1); ?>
                            <div class="col-6 col-md-4 col-lg-3">
                                <div class="card h-100 product-image-card" style="border: <?php echo $isMain ? '2px solid #2dce89' : '1px solid #dee2e6'; ?>;">
                                    <div style="aspect-ratio: 1/1; overflow: hidden; border-radius: 6px 6px 0 0;">
                                        <img src="<?php echo htmlspecialchars(ProductImages::url($id, $img['file'])); ?>"
                                            alt="<?php echo htmlspecialchars($img['file']); ?>"
                                            style="width:100%;height:100%;object-fit:cover;display:block;">
                                    </div>
                                    <div class="card-body p-2 product-image-card-body">
                                        <p class="text-xs text-secondary mb-1 product-image-filename"
                                            title="<?php echo htmlspecialchars($img['file']); ?>">
                                            <?php echo htmlspecialchars($img['file']); ?>
                                        </p>
                                        <div class="product-image-main-action">
                                            <?php if ($isMain): ?>
                                                <span class="badge badge-sm bg-gradient-success">Main image</span>
                                            <?php else: ?>
                                                <form action="edit.php?id=<?php echo $id; ?>" method="post" class="w-100 mb-0">
                                                    <input type="hidden" name="action" value="set_main">
                                                    <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img['file']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-outline-success w-100">Make main</button>
                                                </form>
                                            <?php endif; ?>
                                        </div>

                                        <div class="d-flex gap-1">
                                            <form action="edit.php?id=<?php echo $id; ?>" method="post" class="flex-fill">
                                                <input type="hidden" name="action" value="move_up">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img['file']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                                                    title="Move earlier" <?php echo $index === 0 ? 'disabled' : ''; ?>>&#9650;</button>
                                            </form>
                                            <form action="edit.php?id=<?php echo $id; ?>" method="post" class="flex-fill">
                                                <input type="hidden" name="action" value="move_down">
                                                <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img['file']); ?>">
                                                <button type="submit" class="btn btn-sm btn-outline-secondary w-100"
                                                    title="Move later" <?php echo $index === count($images) - 1 ? 'disabled' : ''; ?>>&#9660;</button>
                                            </form>
                                        </div>

                                        <form action="edit.php?id=<?php echo $id; ?>" method="post" class="mt-1"
                                            onsubmit="return confirm('Delete this image? This cannot be undone.');">
                                            <input type="hidden" name="action" value="delete_image">
                                            <input type="hidden" name="filename" value="<?php echo htmlspecialchars($img['file']); ?>">
                                            <button type="submit" class="btn btn-sm btn-outline-danger w-100"
                                                <?php if ($imageCount <= ProductImages::MIN_IMAGES): ?>
                                                disabled title="A product must keep at least one image"
                                            <?php endif; ?>>
                                                Delete
                                            </button>
                                        </form>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <hr class="my-4">

<?php if ($isFull): ?>
                    <div class="alert alert-success mb-0">
                        This product already has the maximum of
                        <strong><?php echo (int) ProductImages::MAX_IMAGES; ?></strong> images.
                        Delete one if you want to add a different picture.
                    </div>
                <?php else: ?>
                    <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="add_images">
                        <input type="hidden" name="id" value="<?php echo $id; ?>">
                        <label class="form-label">Add more images</label>
                        <input type="file" name="images[]" class="form-control product-image-file<?php echo Validator::fieldClass($errors, 'images'); ?>"<?php echo Validator::fieldAttributes($errors, 'images'); ?> multiple
                            accept="image/jpeg,image/png,image/webp,image/gif">
                        <?php echo Validator::fieldErrorMarkup($errors, 'images'); ?>
                        <small class="text-muted">
                            JPG, PNG, WEBP or GIF, max <?php echo Upload::formatBytes(Upload::MAX_IMAGE_BYTES); ?> each.
                            You can add up to <strong><?php echo (int) $slotsLeft; ?></strong> more
                            (<?php echo (int) ProductImages::MAX_IMAGES; ?> max per product).
                            New images are added to the end.
                        </small>
                        <div id="quick-preview-grid" class="d-grid gap-2 mt-3"
                            style="grid-template-columns: repeat(6, 1fr);"></div>
                        <button type="submit" class="btn bg-gradient-dark mt-3">Upload images</button>
                    </form>
                <?php endif; ?>

            </div>
        </div>

    </div>
</div>

<script>
    // Live thumbnails for the "add more images" box.
    // The box is not rendered at all when the product already has the
    // maximum number of images, so guard against it being missing.
    const quickInput = document.getElementById('quick-preview-grid');
    const quickFile = document.querySelector('input[name="images[]"]');

    if (quickInput && quickFile) {
    quickFile.addEventListener('change', function () {
        const box = quickInput;
        box.innerHTML = '';
        Array.from(this.files || []).forEach(file => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.style.cssText = 'width:100%;aspect-ratio:1/1;object-fit:cover;border-radius:6px;';
            box.appendChild(img);
        });
    });
    }
</script>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>

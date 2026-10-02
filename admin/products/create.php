<?php
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

$errors = [];
$name = '';
$description = '';
$categoryId = 0;
$price = '';
$stock = '';
$status = 1;

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

    // ---- upload the 5+ images ----
    // Upload::images() returns the files it managed to save AND a list
    // of complaints about any it had to skip.
    $uploadResult = Upload::images('images', $uploadDir, ProductImages::MAX_IMAGES);
    foreach ($uploadResult['errors'] as $imageError) {
        $errors['images'][] = $imageError;
    }

    // The 5-image rule. We count what was actually saved, not what the
    // visitor thinks they chose.
    if (count($uploadResult['saved']) < ProductImages::MIN_IMAGES) {
        $errors['images'][] = 'Please choose at least ' . ProductImages::MIN_IMAGES
            . ' images. You chose ' . count($uploadResult['saved']) . '.';
    }

    // If anything at all is wrong, clean up the files we just saved so
    // we do not leave rubbish behind for a product that was never created.
    if ($v->fails() || !empty($errors)) {
        foreach ($uploadResult['saved'] as $orphan) {
            Upload::delete($uploadDir, $orphan);
        }
        $errors = array_merge($v->errors(), $errors);
    } else {
        $slug = ensureUniqueSlug($db, 'products', slugify($name));

        // products.image is filled in straight away by ProductImages
        // once the first image is added, so we insert NULL here.
        $productId = $db->insert(
            'INSERT INTO products (name, slug, description, category_id, price, stock, image, status)
             VALUES (?, ?, ?, ?, ?, ?, NULL, ?)',
            [$name, $slug, $description, $categoryId, (float) $price, (int) $stock, $status]
        );

        // Now we know the product id, so every file can be renamed to
        // the project convention: PRODUCTID-NUMBER.jpg
        foreach ($uploadResult['saved'] as $uploadedFilename) {
            ProductImages::add($db, $productId, $uploadedFilename, $uploadDir);
        }

        Session::flash('success', 'Product "' . $name . '" created with ' . count($uploadResult['saved']) . ' images.');
        header('Location: index.php');
        exit;
    }
}

$categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
$pageTitle = 'Add Product';
$activeNav = 'products';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="d-flex justify-content-between align-items-start flex-wrap gap-3 mb-4">
    <div>
        <p class="text-sm text-secondary mb-1">Catalog / Products</p>
        <h3 class="h4 font-weight-bolder mb-1">Add product</h3>
        <p class="text-sm mb-0">Add the product details, price, stock, and storefront image.</p>
    </div>
    <a href="index.php" class="btn admin-form-button admin-form-button-secondary mb-0">Back to products</a>
</div>

<form action="create.php" method="post" enctype="multipart/form-data" class="admin-fields">
    <div class="row g-4">
        <div class="col-lg-8">
            <section class="card h-100">
                <div class="card-header pb-0">
                    <h6 class="mb-1">Product details</h6>
                    <p class="text-sm mb-0">The information customers see in your store.</p>
                </div>
                <div class="card-body">
                    <div class="mb-4">
                        <label for="product-name" class="form-label">Product name</label>
                        <input id="product-name" type="text" name="name" class="form-control<?php echo Validator::fieldClass($errors, 'name'); ?>"<?php echo Validator::fieldAttributes($errors, 'name'); ?>
                            value="<?php echo htmlspecialchars($name); ?>" autocomplete="off">
                        <?php echo Validator::fieldErrorMarkup($errors, 'name'); ?>
                    </div>

                    <div class="mb-4">
                        <label for="product-description" class="form-label">Description</label>
                        <textarea id="product-description" name="description" class="form-control<?php echo Validator::fieldClass($errors, 'description'); ?>" rows="7"<?php echo Validator::fieldAttributes($errors, 'description'); ?>><?php echo htmlspecialchars($description); ?></textarea>
                        <?php echo Validator::fieldErrorMarkup($errors, 'description'); ?>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product-category" class="form-label">Category</label>
                            <select id="product-category" name="category_id" class="form-control form-select<?php echo Validator::fieldClass($errors, 'category_id'); ?>"<?php echo Validator::fieldAttributes($errors, 'category_id'); ?>>
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
                            <label for="product-price" class="form-label">Price (USD)</label>
                            <input id="product-price" type="number" name="price" class="form-control<?php echo Validator::fieldClass($errors, 'price'); ?>" step="0.01"<?php echo Validator::fieldAttributes($errors, 'price'); ?>
                                min="0" inputmode="decimal" value="<?php echo htmlspecialchars($price); ?>">
                            <?php echo Validator::fieldErrorMarkup($errors, 'price'); ?>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="product-stock" class="form-label">Stock quantity</label>
                            <input id="product-stock" type="number" name="stock" class="form-control<?php echo Validator::fieldClass($errors, 'stock'); ?>" min="0"
                                step="1" inputmode="numeric" value="<?php echo htmlspecialchars($stock); ?>"<?php echo Validator::fieldAttributes($errors, 'stock'); ?>>
                            <?php echo Validator::fieldErrorMarkup($errors, 'stock'); ?>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="card mb-4">
                <div class="card-header pb-0">
                    <h6 class="mb-1">Product images</h6>
                    <p class="text-sm mb-0">
                        Choose at least <?php echo (int) ProductImages::MIN_IMAGES; ?> images
                        (up to <?php echo (int) ProductImages::MAX_IMAGES; ?>). JPG, PNG, WEBP or GIF, max 2MB each.
                        The first one becomes the main picture.
                    </p>
                </div>
                <div class="card-body">
                    <label for="product-images" class="form-label">Choose images</label>
                    <input id="product-images" type="file" name="images[]" class="form-control<?php echo Validator::fieldClass($errors, 'images'); ?>" multiple
                        accept="image/jpeg,image/png,image/webp,image/gif"<?php echo Validator::fieldAttributes($errors, 'images'); ?>>
                    <?php echo Validator::fieldErrorMarkup($errors, 'images'); ?>

                    <div id="image-preview-grid"
                        class="d-grid gap-2 mt-3"
                        style="grid-template-columns: repeat(3, 1fr);"></div>

                    <p id="image-counter" class="text-sm text-secondary mt-2 mb-0"></p>
                </div>
            </section>

            <section class="card">
                <div class="card-header pb-0">
                    <h6 class="mb-1">Store visibility</h6>
                    <p class="text-sm mb-0">Choose whether customers can see this product.</p>
                </div>
                <div class="card-body">
                    <div class="form-check form-switch mb-4">
                        <input class="form-check-input" type="checkbox" name="status" id="product-status"
                            <?php echo $status ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="product-status">Active</label>
                    </div>
                    <div class="d-flex flex-wrap gap-2">
                        <button type="submit" class="btn admin-form-button admin-form-button-primary mb-0">Create product</button>
                        <a href="index.php" class="btn admin-form-button admin-form-button-secondary mb-0">Cancel</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

<script>
    // Shows a small thumbnail of every image the admin picked, and
    // tells them straight away if they are under the 5-image minimum.
    const MIN_IMAGES = <?php echo (int) ProductImages::MIN_IMAGES; ?>;
    const MAX_IMAGES = <?php echo (int) ProductImages::MAX_IMAGES; ?>;

    const imagesInput = document.getElementById('product-images');
    const previewGrid = document.getElementById('image-preview-grid');
    const counter = document.getElementById('image-counter');
    let objectUrls = [];

    imagesInput.addEventListener('change', () => {
        // Free the old previews so the browser does not run out of memory
        objectUrls.forEach(url => URL.revokeObjectURL(url));
        objectUrls = [];
        previewGrid.innerHTML = '';

        const files = Array.from(imagesInput.files || []);
        const MIN = MIN_IMAGES;
        const MAX = MAX_IMAGES;

        files.forEach((file, index) => {
            const url = URL.createObjectURL(file);
            objectUrls.push(url);

            const box = document.createElement('div');
            box.style.position = 'relative';

            const img = document.createElement('img');
            img.src = url;
            img.alt = file.name;
            img.style.width = '100%';
            img.style.aspectRatio = '1 / 1';
            img.style.objectFit = 'cover';
            img.style.borderRadius = '8px';
            img.style.display = 'block';

            const badge = document.createElement('span');
            badge.textContent = index === 0 ? 'Main' : (index + 1);
            badge.style.cssText =
                'position:absolute;top:4px;left:4px;background:rgba(0,0,0,.65);color:#fff;' +
                'font-size:11px;padding:2px 6px;border-radius:4px;';

            box.appendChild(img);
            box.appendChild(badge);
            previewGrid.appendChild(box);
        });

        if (files.length < MIN) {
            counter.textContent = files.length + ' chosen — you need at least ' + MIN + '.';
            counter.style.color = '#d63384';
        } else if (files.length > MAX) {
            counter.textContent = files.length + ' chosen — only the first ' + MAX + ' will be saved.';
            counter.style.color = '#fd7e14';
        } else {
            counter.textContent = files.length + ' chosen. The first one is the main picture.';
            counter.style.color = '#2dce89';
        }
    });
</script>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
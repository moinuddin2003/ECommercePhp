<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../core/Database.php';
require_once __DIR__ . '/../../core/Session.php';
require_once __DIR__ . '/../../core/Auth.php';
require_once __DIR__ . '/../../core/Validator.php';
require_once __DIR__ . '/../../core/Upload.php';
require_once __DIR__ . '/../../core/helpers.php';

Session::start();
$db = new Database($conn);
Auth::requireAdmin('../login.php');

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

    $uploadResult = Upload::image('image', __DIR__ . '/../../public/uploads/products');
    if ($uploadResult['error']) {
        $errors['image'] = $uploadResult['error'];
    }

    if ($v->passes() && empty($errors)) {
        $slug = ensureUniqueSlug($db, 'products', slugify($name));
        $db->insert(
            'INSERT INTO products (name, slug, description, category_id, price, stock, image, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [$name, $slug, $description, $categoryId, (float) $price, (int) $stock, $uploadResult['filename'], $status],
            'sssidisi'
        );

        Session::flash('success', 'Product "' . $name . '" created.');
        header('Location: index.php');
        exit;
    }

    $errors = array_merge($v->errors(), $errors);
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
    <a href="index.php" class="btn btn-outline-secondary mb-0">Back to products</a>
</div>

<?php if (!empty($errors)): ?>
    <div class="alert alert-danger" role="alert">
        <strong>Please check these fields:</strong>
        <ul class="mb-0 mt-2">
            <?php foreach ($errors as $error): ?>
                <li><?php echo htmlspecialchars($error); ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
<?php endif; ?>

<form action="create.php" method="post" enctype="multipart/form-data">
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
                        <input id="product-name" type="text" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($name); ?>" autocomplete="off" required>
                    </div>

                    <div class="mb-4">
                        <label for="product-description" class="form-label">Description</label>
                        <textarea id="product-description" name="description" class="form-control" rows="7"
                            required><?php echo htmlspecialchars($description); ?></textarea>
                    </div>

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label for="product-category" class="form-label">Category</label>
                            <select id="product-category" name="category_id" class="form-control" required>
                                <option value="0">Select category</option>
                                <?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['id']; ?>"
                                        <?php echo (int) $category['id'] === $categoryId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="product-price" class="form-label">Price (USD)</label>
                            <input id="product-price" type="number" name="price" class="form-control" step="0.01"
                                min="0" inputmode="decimal" value="<?php echo htmlspecialchars($price); ?>" required>
                        </div>
                        <div class="col-md-3 mb-3">
                            <label for="product-stock" class="form-label">Stock quantity</label>
                            <input id="product-stock" type="number" name="stock" class="form-control" min="0"
                                step="1" inputmode="numeric" value="<?php echo htmlspecialchars($stock); ?>" required>
                        </div>
                    </div>
                </div>
            </section>
        </div>

        <div class="col-lg-4">
            <section class="card mb-4">
                <div class="card-header pb-0">
                    <h6 class="mb-1">Product image</h6>
                    <p class="text-sm mb-0">JPG, PNG, WEBP, or GIF. Maximum 2MB.</p>
                </div>
                <div class="card-body">
                    <div class="mb-3 d-flex align-items-center justify-content-center bg-gray-100 border-radius-lg"
                        style="aspect-ratio: 4 / 3; overflow: hidden;">
                        <img id="product-image-preview" src="" alt="Selected product preview"
                            class="d-none w-100 h-100" style="object-fit: contain;">
                        <div id="product-image-empty" class="text-center text-secondary px-3">
                            <span class="material-symbols-rounded d-block mb-2" aria-hidden="true">image</span>
                            <span class="text-sm">Image preview appears here</span>
                        </div>
                    </div>
                    <label for="product-image" class="form-label">Choose image</label>
                    <input id="product-image" type="file" name="image" class="form-control"
                        accept="image/jpeg,image/png,image/webp,image/gif">
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
                        <button type="submit" class="btn bg-gradient-dark mb-0">Create product</button>
                        <a href="index.php" class="btn btn-outline-secondary mb-0">Cancel</a>
                    </div>
                </div>
            </section>
        </div>
    </div>
</form>

<script>
    const productImageInput = document.getElementById('product-image');
    const productImagePreview = document.getElementById('product-image-preview');
    const productImageEmpty = document.getElementById('product-image-empty');
    let productImagePreviewUrl = null;

    productImageInput.addEventListener('change', () => {
        if (productImagePreviewUrl) {
            URL.revokeObjectURL(productImagePreviewUrl);
            productImagePreviewUrl = null;
        }

        const [file] = productImageInput.files;
        if (!file) {
            productImagePreview.removeAttribute('src');
            productImagePreview.classList.add('d-none');
            productImageEmpty.classList.remove('d-none');
            return;
        }

        productImagePreviewUrl = URL.createObjectURL(file);
        productImagePreview.src = productImagePreviewUrl;
        productImagePreview.classList.remove('d-none');
        productImageEmpty.classList.add('d-none');
    });
</script>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
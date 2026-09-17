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

<div class="row">
    <div class="col-md-9 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Add Product</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                        </ul>
                    </div><?php endif; ?>
                <form action="create.php" method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline my-3"><label class="form-label">Product
                            Name</label><input type="text" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($name); ?>" required></div>
                    <div class="mb-3"><label class="form-label">Description</label><textarea name="description"
                            class="form-control" rows="4"
                            required><?php echo htmlspecialchars($description); ?></textarea></div>
                    <div class="row">
                        <div class="col-md-6 mb-3"><label class="form-label">Category</label><select name="category_id"
                                class="form-control" required>
                                <option value="0">Select category</option><?php foreach ($categories as $category): ?>
                                    <option value="<?php echo (int) $category['id']; ?>" <?php echo (int) $category['id'] === $categoryId ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($category['name']); ?>
                                    </option><?php endforeach; ?>
                            </select></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Price</label><input type="number"
                                name="price" class="form-control" step="0.01" min="0"
                                value="<?php echo htmlspecialchars($price); ?>" required></div>
                        <div class="col-md-3 mb-3"><label class="form-label">Stock</label><input type="number"
                                name="stock" class="form-control" min="0"
                                value="<?php echo htmlspecialchars($stock); ?>" required></div>
                    </div>
                    <div class="mb-3"><label class="form-label">Image (optional, max 2MB)</label><input type="file"
                            name="image" class="form-control" accept="image/*"></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                            name="status" id="status" <?php echo $status ? 'checked' : ''; ?>><label
                            class="form-check-label" for="status">Active (visible in store)</label></div>
                    <button type="submit" class="btn bg-gradient-dark">Create Product</button> <a href="index.php"
                        class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
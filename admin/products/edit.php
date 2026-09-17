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

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$product = $db->fetchOne('SELECT * FROM products WHERE id = ?', [$id], 'i');
if (!$product) {
    Session::flash('error', 'Product not found.');
    header('Location: index.php');
    exit;
}

$errors = [];
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
    $v->required($name, 'name')->required($description, 'description')->required($price, 'price')->required($stock, 'stock')->numeric($price, 'price')->numeric($stock, 'stock');
    if ($categoryId <= 0)
        $errors['category_id'] = 'Please select a category';
    if ($price !== '' && is_numeric($price) && (float) $price < 0)
        $errors['price'] = 'Price cannot be negative';
    if ($stock !== '' && is_numeric($stock) && (int) $stock < 0)
        $errors['stock'] = 'Stock cannot be negative';

    $uploadResult = Upload::image('image', __DIR__ . '/../../public/uploads/products');
    if ($uploadResult['error'])
        $errors['image'] = $uploadResult['error'];

    if ($v->passes() && empty($errors)) {
        $slug = ensureUniqueSlug($db, 'products', slugify($name), $id);
        $imageFilename = $product['image'];
        if ($uploadResult['filename']) {
            Upload::delete(__DIR__ . '/../../public/uploads/products', $product['image']);
            $imageFilename = $uploadResult['filename'];
        }
        $db->execute('UPDATE products SET name = ?, slug = ?, description = ?, category_id = ?, price = ?, stock = ?, image = ?, status = ? WHERE id = ?', [$name, $slug, $description, $categoryId, (float) $price, (int) $stock, $imageFilename, $status, $id], 'sssidisii');
        Session::flash('success', 'Product "' . $name . '" updated.');
        header('Location: index.php');
        exit;
    }
    $errors = array_merge($v->errors(), $errors);
}

$categories = $db->fetchAll('SELECT id, name FROM categories ORDER BY name ASC');
$pageTitle = 'Edit Product';
$activeNav = 'products';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>
<div class="row">
    <div class="col-md-9 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Edit Product</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0"><?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li><?php endforeach; ?>
                        </ul>
                    </div><?php endif; ?>
                <?php if ($product['image']): ?><img
                        src="../../public/uploads/products/<?php echo htmlspecialchars($product['image']); ?>" alt=""
                        style="width:100px;height:100px;object-fit:cover;border-radius:8px" class="mb-3"><?php endif; ?>
                <form action="edit.php?id=<?php echo $id; ?>" method="post" enctype="multipart/form-data"><input
                        type="hidden" name="id" value="<?php echo $id; ?>">
                    <div class="input-group input-group-outline my-3 <?php echo !empty($name) ? 'is-filled' : ''; ?>">
                        <label class="form-label">Product Name</label>
                        <input type="text" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>
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
                    <div class="mb-3"><label class="form-label">Replace Image (optional, max 2MB)</label><input
                            type="file" name="image" class="form-control" accept="image/*"><small
                            class="text-muted">Leave empty to keep the current image.</small></div>
                    <div class="form-check form-switch mb-3"><input class="form-check-input" type="checkbox"
                            name="status" id="status" <?php echo $status ? 'checked' : ''; ?>><label
                            class="form-check-label" for="status">Active (visible in store)</label></div>
                    <button type="submit" class="btn bg-gradient-dark">Save Changes</button> <a href="index.php"
                        class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>
<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
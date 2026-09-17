<?php
/**
 * admin/categories/create.php
 */

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
$status = 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $status = isset($_POST['status']) ? 1 : 0;

    $v = new Validator();
    $v->required($name, 'name');

    $uploadResult = Upload::image('image', __DIR__ . '/../../public/uploads/categories');
    if ($uploadResult['error']) {
        $errors['image'] = $uploadResult['error'];
    }

    if ($v->passes() && empty($errors)) {
        $slug = ensureUniqueSlug($db, 'categories', slugify($name));

        $db->insert(
            'INSERT INTO categories (name, slug, image, status) VALUES (?, ?, ?, ?)',
            [$name, $slug, $uploadResult['filename'], $status],
            'sssi'
        );

        Session::flash('success', 'Category "' . $name . '" created.');
        header('Location: index.php');
        exit;
    }

    $errors = array_merge($v->errors(), $errors);
}

$pageTitle = 'Add Category';
$activeNav = 'categories';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Add Category</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <div class="alert alert-danger">
                        <ul class="mb-0">
                            <?php foreach ($errors as $error): ?>
                                <li><?php echo htmlspecialchars($error); ?></li>
                            <?php endforeach; ?>
                        </ul>
                    </div>
                <?php endif; ?>

                <form action="create.php" method="post" enctype="multipart/form-data">
                    <div class="input-group input-group-outline my-3">
                        <label class="form-label">Category Name</label>
                        <input type="text" name="name" class="form-control"
                            value="<?php echo htmlspecialchars($name); ?>" required>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Image (optional, max 2MB)</label>
                        <input type="file" name="image" class="form-control" accept="image/*">
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $status ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Active (visible in store)</label>
                    </div>

                    <button type="submit" class="btn bg-gradient-dark">Create Category</button>
                    <a href="index.php" class="btn btn-outline-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
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
                    <?php foreach (['general'] as $summaryField): ?>
                        <?php if (isset($errors[$summaryField])): ?>
                            <p class="form-error-message" role="alert"><?php echo htmlspecialchars($errors[$summaryField]); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <form action="create.php" method="post" enctype="multipart/form-data" class="admin-fields">
                    <div class="mb-3">
                        <label class="form-label" for="category-name">Category name</label>
                        <input type="text" name="name" class="form-control<?php echo Validator::fieldClass($errors, 'name'); ?>" id="category-name"<?php echo Validator::fieldAttributes($errors, 'name'); ?>
                            value="<?php echo htmlspecialchars($name); ?>">
                        <?php echo Validator::fieldErrorMarkup($errors, 'name'); ?>
                    </div>

                    <div class="mb-3">
                        <label class="form-label" for="category-image">Image (optional, max 2MB)</label>
                        <input type="file" name="image" id="category-image" class="form-control<?php echo Validator::fieldClass($errors, 'image'); ?>" accept="image/*"<?php echo Validator::fieldAttributes($errors, 'image'); ?>>
                        <?php echo Validator::fieldErrorMarkup($errors, 'image'); ?>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $status ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Active (visible in store)</label>
                    </div>

                    <button type="submit" class="btn admin-form-button admin-form-button-primary">Create
                        category</button>
                    <a href="index.php" class="btn admin-form-button admin-form-button-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
<?php
/**
 * admin/categories/edit.php
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

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
$category = $db->fetchOne('SELECT * FROM categories WHERE id = ?', [$id], 'i');

if (!$category) {
    Session::flash('error', 'Category not found.');
    header('Location: index.php');
    exit;
}

$errors = [];
$name = $category['name'];
$status = (int) $category['status'];

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
        $slug = ensureUniqueSlug($db, 'categories', slugify($name), $id);

        // Only touch the image if a new one was uploaded — otherwise keep the current one
        $imageFilename = $category['image'];
        if ($uploadResult['filename']) {
            Upload::delete(__DIR__ . '/../../public/uploads/categories', $category['image']);
            $imageFilename = $uploadResult['filename'];
        }

        $db->execute(
            'UPDATE categories SET name = ?, slug = ?, image = ?, status = ? WHERE id = ?',
            [$name, $slug, $imageFilename, $status, $id],
            'sssii'
        );

        Session::flash('success', 'Category "' . $name . '" updated.');
        header('Location: index.php');
        exit;
    }

    $errors = array_merge($v->errors(), $errors);
}

$pageTitle = 'Edit Category';
$activeNav = 'categories';
$adminRoot = '../';
require __DIR__ . '/../../includes/admin-header.php';
?>

<div class="row">
    <div class="col-md-8 mx-auto">
        <div class="card">
            <div class="card-header pb-0">
                <h5>Edit Category</h5>
            </div>
            <div class="card-body">
                <?php if (!empty($errors)): ?>
                    <?php foreach (['general'] as $summaryField): ?>
                        <?php if (isset($errors[$summaryField])): ?>
                            <p class="form-error-message" role="alert"><?php echo htmlspecialchars($errors[$summaryField]); ?></p>
                        <?php endif; ?>
                    <?php endforeach; ?>
                <?php endif; ?>

                <?php if ($category['image']): ?>
                    <img src="../../public/uploads/categories/<?php echo htmlspecialchars($category['image']); ?>" alt=""
                        style="width: 100px; height: 100px; object-fit: cover; border-radius: 8px;" class="mb-3">
                <?php endif; ?>

                <form action="edit.php?id=<?php echo (int) $id; ?>" method="post" enctype="multipart/form-data"
                    class="admin-fields">
                    <input type="hidden" name="id" value="<?php echo (int) $id; ?>">


                    <div class="mb-3">
                        <label class="form-label" for="category-name">Category name</label>
                        <input type="text" name="name" id="category-name" class="form-control<?php echo Validator::fieldClass($errors, 'name'); ?>"<?php echo Validator::fieldAttributes($errors, 'name'); ?>
                            value="<?php echo htmlspecialchars($name); ?>">
                        <?php echo Validator::fieldErrorMarkup($errors, 'name'); ?>
                    </div>


                    <div class="mb-3">
                        <label class="form-label" for="category-image">Replace image (optional, max 2MB)</label>
                        <input type="file" name="image" id="category-image" class="form-control<?php echo Validator::fieldClass($errors, 'image'); ?>" accept="image/*"<?php echo Validator::fieldAttributes($errors, 'image'); ?>>
                        <?php echo Validator::fieldErrorMarkup($errors, 'image'); ?>
                        <small class="text-muted">Leave empty to keep the current image.</small>
                    </div>

                    <div class="form-check form-switch mb-3">
                        <input class="form-check-input" type="checkbox" name="status" id="status" <?php echo $status ? 'checked' : ''; ?>>
                        <label class="form-check-label" for="status">Active (visible in store)</label>
                    </div>

                    <button type="submit" class="btn admin-form-button admin-form-button-primary">Save changes</button>
                    <a href="index.php" class="btn admin-form-button admin-form-button-secondary">Cancel</a>
                </form>
            </div>
        </div>
    </div>
</div>

<?php require __DIR__ . '/../../includes/admin-footer.php'; ?>
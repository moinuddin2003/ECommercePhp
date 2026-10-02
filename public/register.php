<?php
/**
 * public/register.php
 * ------------------------------------------------
 * New customer signup. On success, logs the user in
 * immediately (no separate "verify your email" step here)
 * and redirects to ?redirect= if one was passed in, else index.php.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';

Session::start();
$db = new Database($conn);
$auth = new Auth($db);

// Already logged in? Nothing to do here.
if (Auth::isLoggedIn('customer')) {
    header('Location: index.php');
    exit;
}

$redirectTo = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirmPassword = $_POST['confirm_password'] ?? '';
    if (!isset($_POST['accept_policy'])) {
        $errors['accept_policy'] = 'Please accept the privacy policy.';
    }

    $v = new Validator();
    $v->required($name, 'name')
        ->required($email, 'email')
        ->email($email, 'email')
        ->required($password, 'password')
        ->minLength($password, 'password', 6)
        ->matches($confirmPassword, $password, 'confirm_password', 'Passwords do not match');

    if ($v->passes() && empty($errors)) {
        $result = $auth->register($name, $email, $password);

        if ($result['success']) {
            // Auto-login right after registering
            $auth->login($email, $password);
            Session::flash('success', 'Welcome, ' . $name . '! Your account has been created.');
            header('Location: ' . $redirectTo);
            exit;
        }

        $errors['email'] = $result['message'];
    } else {
        $errors = array_merge($v->errors(), $errors);
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="title text-center mb-4">Create an Account</h1>

            <form
                action="register.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>"
                method="post">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control<?php echo Validator::fieldClass($errors, 'name'); ?>"<?php echo Validator::fieldAttributes($errors, 'name'); ?>
                        value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>">
                    <?php echo Validator::fieldErrorMarkup($errors, 'name'); ?>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control<?php echo Validator::fieldClass($errors, 'email'); ?>"<?php echo Validator::fieldAttributes($errors, 'email'); ?>
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                    <?php echo Validator::fieldErrorMarkup($errors, 'email'); ?>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control<?php echo Validator::fieldClass($errors, 'password'); ?>"<?php echo Validator::fieldAttributes($errors, 'password'); ?>>
                    <?php echo Validator::fieldErrorMarkup($errors, 'password'); ?>
                    <small class="mb-3 mt-2 form-text text-muted">At least 6 characters.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control<?php echo Validator::fieldClass($errors, 'confirm_password'); ?>"<?php echo Validator::fieldAttributes($errors, 'confirm_password'); ?>>
                    <?php echo Validator::fieldErrorMarkup($errors, 'confirm_password'); ?>
                </div>

                <div class="form-group custom-control custom-checkbox">
                    <input type="checkbox" class="custom-control-input<?php echo Validator::fieldClass($errors, 'accept_policy'); ?>" id="accept-policy" name="accept_policy"<?php echo Validator::fieldAttributes($errors, 'accept_policy'); ?>
                        <?php echo isset($_POST['accept_policy']) ? 'checked' : ''; ?>>
                    <label class="custom-control-label" for="accept-policy">I agree to the privacy policy.</label>
                    <?php echo Validator::fieldErrorMarkup($errors, 'accept_policy'); ?>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-round">
                    <span>Create Account</span><i class="icon-long-arrow-right"></i>
                </button>
            </form>

            <p class="text-center mt-3">
                Already have an account?
                <a
                    href="login.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>">Sign
                    in</a>
            </p>
        </div>
    </div>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
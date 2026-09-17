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
if (Auth::isLoggedIn()) {
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

    $v = new Validator();
    $v->required($name, 'name')
      ->required($email, 'email')
      ->email($email, 'email')
      ->required($password, 'password')
      ->minLength($password, 'password', 6)
      ->matches($confirmPassword, $password, 'confirm_password', 'Passwords do not match');

    if ($v->passes()) {
        $result = $auth->register($name, $email, $password);

        if ($result['success']) {
            // Auto-login right after registering
            $auth->login($email, $password);
            Session::flash('success', 'Welcome, ' . $name . '! Your account has been created.');
            header('Location: ' . $redirectTo);
            exit;
        }

        $errors['general'] = $result['message'];
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="title text-center mb-4">Create an Account</h1>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form action="register.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>" method="post">
                <div class="form-group">
                    <label for="name">Full Name</label>
                    <input type="text" id="name" name="name" class="form-control" value="<?php echo htmlspecialchars($_POST['name'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                    <small class="form-text text-muted">At least 6 characters.</small>
                </div>

                <div class="form-group">
                    <label for="confirm_password">Confirm Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-round">
                    <span>Create Account</span><i class="icon-long-arrow-right"></i>
                </button>
            </form>

            <p class="text-center mt-3">
                Already have an account?
                <a href="login.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>">Sign in</a>
            </p>
        </div>
    </div>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
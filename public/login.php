<?php
/**
 * public/login.php
 * ------------------------------------------------
 * If a page redirected here (e.g. checkout.php via
 * Auth::requireLogin), the original destination is
 * preserved via ?redirect= and used after a successful login.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';

Session::start();
$db = new Database($conn);
$auth = new Auth($db);

if (Auth::isLoggedIn()) {
    header('Location: index.php');
    exit;
}

$redirectTo = $_GET['redirect'] ?? ($_POST['redirect'] ?? 'index.php');
$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $v = new Validator();
    $v->required($email, 'email')
      ->required($password, 'password');

    if ($v->passes()) {
        $result = $auth->login($email, $password);

        if ($result['success']) {
            Session::flash('success', 'Welcome back!');
            header('Location: ' . $redirectTo);
            exit;
        }

        $errors['general'] = $result['message'];
    } else {
        $errors = $v->errors();
    }
}

$pageTitle = 'Sign In';
require __DIR__ . '/../includes/header.php';
?>

<div class="container mb-5 mt-4">
    <div class="row justify-content-center">
        <div class="col-md-6">
            <h1 class="title text-center mb-4">Sign In</h1>

            <?php if (!empty($errors)): ?>
            <div class="alert alert-danger">
                <ul class="mb-0">
                    <?php foreach ($errors as $error): ?>
                    <li><?php echo htmlspecialchars($error); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
            <?php endif; ?>

            <form action="login.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>" method="post">
                <div class="form-group">
                    <label for="email">Email</label>
                    <input type="email" id="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" required>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" class="form-control" required>
                </div>

                <button type="submit" class="btn btn-primary btn-block btn-round">
                    <span>Sign In</span><i class="icon-long-arrow-right"></i>
                </button>
            </form>

            <p class="text-center mt-3">
                Don't have an account?
                <a href="register.php<?php echo $redirectTo !== 'index.php' ? '?redirect=' . urlencode($redirectTo) : ''; ?>">Create one</a>
            </p>
        </div>
    </div>
</div><!-- End .container -->

<?php require __DIR__ . '/../includes/footer.php'; ?>
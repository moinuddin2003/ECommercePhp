<?php
/**
 * admin/login.php
 * ------------------------------------------------
 * Separate from the storefront login — this checks that the
 * account's role is specifically 'admin', not just any logged-in user.
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../core/Database.php';
require_once __DIR__ . '/../core/Session.php';
require_once __DIR__ . '/../core/Auth.php';
require_once __DIR__ . '/../core/Validator.php';

Session::start();
$db = new Database($conn);
$auth = new Auth($db);

if (Auth::isLoggedIn() && Auth::isAdmin()) {
    header('Location: index.php');
    exit;
}

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $v = new Validator();
    $v->required($email, 'email')->required($password, 'password');

    if ($v->passes()) {
        $result = $auth->login($email, $password);

        if ($result['success'] && Auth::isAdmin()) {
            header('Location: index.php');
            exit;
        }

        if ($result['success'] && !Auth::isAdmin()) {
            // Logged into a real account, but it's not an admin — log back out.
            $auth->logout();
            Session::start();
            $errors['general'] = 'That account does not have admin access.';
        } else {
            $errors['general'] = $result['message'];
        }
    } else {
        $errors = $v->errors();
    }
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title>Admin Login - MyStore</title>
    <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="assets/css/nucleo-svg.css" rel="stylesheet" />
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
</head>

<body class="bg-gray-100">
    <main class="main-content mt-0">
        <div class="page-header align-items-start min-vh-100" style="background-image: linear-gradient(135deg, #42424a 0%, #191919 100%);">
            <span class="mask bg-gradient-dark opacity-6"></span>
            <div class="container my-auto">
                <div class="row">
                    <div class="col-lg-4 col-md-8 col-12 mx-auto">
                        <div class="card z-index-0 fadeIn3 fadeInBottom mt-5">
                            <div class="card-header p-0 position-relative mt-n4 mx-3 z-index-2">
                                <div class="bg-gradient-dark shadow-dark border-radius-lg py-3 pe-1 text-center">
                                    <h4 class="text-white font-weight-bolder mb-0">Admin Sign In</h4>
                                </div>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($errors)): ?>
                                <div class="alert alert-danger text-white">
                                    <?php foreach ($errors as $error): ?>
                                    <div><?php echo htmlspecialchars($error); ?></div>
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>

                                <form role="form" method="post" action="login.php">
                                    <div class="input-group input-group-outline my-3">
                                        <label class="form-label">Email</label>
                                        <input type="email" name="email" class="form-control" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                    </div>
                                    <div class="input-group input-group-outline mb-3">
                                        <label class="form-label">Password</label>
                                        <input type="password" name="password" class="form-control">
                                    </div>
                                    <div class="text-center">
                                        <button type="submit" class="btn bg-gradient-dark w-100 my-4 mb-2">Sign in</button>
                                    </div>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="assets/js/core/popper.min.js"></script>
    <script src="assets/js/core/bootstrap.min.js"></script>
    <script src="assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
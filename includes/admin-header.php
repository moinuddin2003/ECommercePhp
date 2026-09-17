<?php
/**
 * includes/admin-header.php
 * ------------------------------------------------
 * Expects the INCLUDING admin page to have already:
 *   1. Required config/database.php, core/Database.php, core/Session.php, core/Auth.php
 *   2. Created $db = new Database($conn);
 *   3. Called Auth::requireAdmin('login.php') (or the right relative path to admin/login.php)
 *   4. Set $pageTitle (shown in the browser title + breadcrumb)
 *   5. Set $adminRoot — how many folders "up" this page is from admin/, as a
 *      relative path prefix. This is needed because admin pages live at
 *      different depths (admin/index.php vs admin/categories/index.php),
 *      so a single hardcoded "assets/..." path can't work for both.
 *
 *      admin/index.php              -> $adminRoot = '';
 *      admin/categories/index.php   -> $adminRoot = '../';
 *      admin/categories/create.php  -> $adminRoot = '../';
 *
 * Every asset link and every nav link below is prefixed with $adminRoot
 * so the same include works correctly no matter how deep the page is.
 */

$adminRoot = $adminRoot ?? '';

// Which nav item should be highlighted as "active" — set $activeNav
// before including this file, e.g. $activeNav = 'products';
$activeNav = $activeNav ?? '';

function navActive($key, $activeNav)
{
    return $key === $activeNav ? 'active bg-gradient-dark text-white' : 'text-dark';
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <title><?php echo htmlspecialchars($pageTitle ?? 'Admin'); ?> - MyStore Admin</title>

    <link rel="stylesheet" type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="<?php echo $adminRoot; ?>assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="<?php echo $adminRoot; ?>assets/css/nucleo-svg.css" rel="stylesheet" />
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="<?php echo $adminRoot; ?>assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
    <?php if (!empty($includeCharts)): ?>
        <script src="<?php echo $adminRoot; ?>assets/js/plugins/chartjs.min.js"></script>
    <?php endif; ?>
</head>

<body class="g-sidenav-show bg-gray-100">

    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2"
        id="sidenav-main">
        <div class="sidenav-header">
            <a class="navbar-brand px-4 py-3 m-0" href="<?php echo $adminRoot; ?>index.php">
                <span class="ms-1 font-weight-bold">MyStore Admin</span>
            </a>
        </div>

        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('dashboard', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>index.php">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('categories', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>categories/index.php">
                        <i class="material-symbols-rounded opacity-5">category</i>
                        <span class="nav-link-text ms-1">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('products', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>products/index.php">
                        <i class="material-symbols-rounded opacity-5">inventory_2</i>
                        <span class="nav-link-text ms-1">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('orders', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>orders/index.php">
                        <i class="material-symbols-rounded opacity-5">shopping_bag</i>
                        <span class="nav-link-text ms-1">Orders</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('users', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>users/index.php">
                        <i class="material-symbols-rounded opacity-5">group</i>
                        <span class="nav-link-text ms-1">Customers</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link <?php echo navActive('reports', $activeNav); ?>"
                        href="<?php echo $adminRoot; ?>reports.php">
                        <i class="material-symbols-rounded opacity-5">bar_chart</i>
                        <span class="nav-link-text ms-1">Reports</span>
                    </a>
                </li>
            </ul>
        </div>

        <div class="sidenav-footer position-absolute w-100 bottom-0">
            <div class="mx-3 mb-3">
                <a class="btn btn-outline-dark w-100" href="<?php echo $adminRoot; ?>../public/index.php"
                    target="_blank">View Store</a>
                <a class="btn bg-gradient-dark w-100 text-white" href="<?php echo $adminRoot; ?>logout.php">Logout</a>
            </div>
        </div>
    </aside>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur"
            data-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark"
                                href="<?php echo $adminRoot; ?>index.php">Admin</a></li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">
                            <?php echo htmlspecialchars($pageTitle ?? ''); ?>
                        </li>
                    </ol>
                </nav>

                <div class="collapse navbar-collapse mt-sm-0 mt-2 me-md-0 me-sm-4" id="navbar">
                    <ul class="navbar-nav d-flex align-items-center justify-content-end ms-auto">
                        <li class="nav-item d-xl-none ps-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body p-0" id="iconNavbarSidenav">
                                <div class="sidenav-toggler-inner">
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                    <i class="sidenav-toggler-line"></i>
                                </div>
                            </a>
                        </li>
                        <li class="nav-item dropdown pe-3 d-flex align-items-center">
                            <a href="javascript:;" class="nav-link text-body font-weight-bold px-0" id="adminMenuButton"
                                data-bs-toggle="dropdown" aria-expanded="false">
                                <i class="material-symbols-rounded me-1">account_circle</i>
                                <?php echo htmlspecialchars(Session::get('user_name')); ?>
                            </a>
                            <ul class="dropdown-menu dropdown-menu-end px-2 py-3" aria-labelledby="adminMenuButton">
                                <li>
                                    <a class="dropdown-item border-radius-md"
                                        href="<?php echo $adminRoot; ?>logout.php">Logout</a>
                                </li>
                            </ul>
                        </li>
                    </ul>
                </div>
            </div>
        </nav>
        <!-- End Navbar -->

        <div class="container-fluid py-2">
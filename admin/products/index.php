<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
    <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
    <link rel="icon" type="image/png" href="../assets/img/favicon.png">
    <title>Products Management - Material Dashboard 3</title>
    <link rel="stylesheet" type="text/css"
        href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
    <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
    <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
    <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
    <link rel="stylesheet"
        href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
    <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
</head>

<body class="g-sidenav-show bg-gray-100">
    <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2"
        id="sidenav-main">
        <div class="sidenav-header">
            <a class="navbar-brand px-4 py-3 m-0" href="dashboard.html">
                <img src="../assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26"
                    alt="main_logo">
                <span class="ms-1 text-sm text-dark">Admin Portal</span>
            </a>
        </div>
        <hr class="horizontal dark mt-0 mb-2">
        <div class="collapse navbar-collapse w-auto" id="sidenav-collapse-main">
            <ul class="navbar-nav">
                <li class="nav-item">
                    <a class="nav-link text-dark" href="dashboard.html">
                        <i class="material-symbols-rounded opacity-5">dashboard</i>
                        <span class="nav-link-text ms-1">Dashboard</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link active bg-gradient-dark text-white" href="products.html">
                        <i class="material-symbols-rounded opacity-5">inventory_2</i>
                        <span class="nav-link-text ms-1">Products</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="categories.html">
                        <i class="material-symbols-rounded opacity-5">category</i>
                        <span class="nav-link-text ms-1">Categories</span>
                    </a>
                </li>
                <li class="nav-item">
                    <a class="nav-link text-dark" href="reports.html">
                        <i class="material-symbols-rounded opacity-5">analytics</i>
                        <span class="nav-link-text ms-1">Reports</span>
                    </a>
                </li>
            </ul>
        </div>
    </aside>

    <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
        <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur"
            data-scroll="true">
            <div class="container-fluid py-1 px-3">
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
                        <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a>
                        </li>
                        <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Products</li>
                    </ol>
                </nav>
            </div>
        </nav>

        <div class="container-fluid py-2">
            <!-- Add New Product Modal Trigger / Form Header -->
            <div class="row mb-4">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0 d-flex justify-content-between align-items-center">
                            <h6>Add New Product</h6>
                        </div>
                        <div class="card-body">
                            <form role="form" class="row">
                                <div class="col-md-6 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>Product Name</label>
                                        <input type="text" class="form-control" placeholder="Wireless Headphones">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>SKU</label>
                                        <input type="text" class="form-control" placeholder="SKU-8921">
                                    </div>
                                </div>
                                <div class="col-md-3 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>Category</label>
                                        <select class="form-control">
                                            <option>Electronics</option>
                                            <option>Fashion & Apparel</option>
                                        </select>
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>Price ($)</label>
                                        <input type="number" step="0.01" class="form-control" placeholder="99.99">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>Stock Quantity</label>
                                        <input type="number" class="form-control" placeholder="50">
                                    </div>
                                </div>
                                <div class="col-md-4 mb-3">
                                    <div class="input-group input-group-static">
                                        <label>Product Image</label>
                                        <input type="file" class="form-control">
                                    </div>
                                </div>
                                <div class="col-12 text-end">
                                    <button type="button" class="btn bg-gradient-dark mb-0">Publish Product</button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Inventory List -->
            <div class="row">
                <div class="col-12">
                    <div class="card">
                        <div class="card-header pb-0">
                            <h6>Product Inventory</h6>
                        </div>
                        <div class="card-body px-0 pb-2">
                            <div class="table-responsive p-0">
                                <table class="table align-items-center mb-0">
                                    <thead>
                                        <tr>
                                            <th
                                                class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Product</th>
                                            <th
                                                class="text-uppercase text-secondary text-xxs font-weight-bolder opacity-7 ps-2">
                                                Category</th>
                                            <th
                                                class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Price</th>
                                            <th
                                                class="text-center text-uppercase text-secondary text-xxs font-weight-bolder opacity-7">
                                                Stock</th>
                                            <th class="text-secondary opacity-7">Action</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr>
                                            <td>
                                                <div class="d-flex px-3 py-1">
                                                    <div class="d-flex flex-column justify-content-center">
                                                        <h6 class="mb-0 text-sm">Sony WH-1000XM4</h6>
                                                        <p class="text-xs text-secondary mb-0">SKU: HEAD-001</p>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <p class="text-xs font-weight-bold mb-0">Electronics</p>
                                            </td>
                                            <td class="align-middle text-center text-sm">
                                                <span class="text-xs font-weight-bold">$348.00</span>
                                            </td>
                                            <td class="align-middle text-center">
                                                <span class="badge badge-sm bg-gradient-success">45 in stock</span>
                                            </td>
                                            <td class="align-middle">
                                                <a href="javascript:;" class="text-secondary font-weight-bold text-xs"
                                                    data-toggle="tooltip" title="Edit product">Edit</a>
                                            </td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <script src="../assets/js/core/popper.min.js"></script>
    <script src="../assets/js/core/bootstrap.min.js"></script>
    <script src="../assets/js/plugins/perfect-scrollbar.min.js"></script>
    <script src="../assets/js/plugins/smooth-scrollbar.min.js"></script>
    <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
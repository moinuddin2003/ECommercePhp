<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1, shrink-to-fit=no">
  <link rel="apple-touch-icon" sizes="76x76" href="../assets/img/apple-icon.png">
  <link rel="icon" type="image/png" href="../assets/img/favicon.png">
  <title>Sales Reports - Material Dashboard 3</title>
  <link rel="stylesheet" type="text/css" href="https://fonts.googleapis.com/css?family=Inter:300,400,500,600,700,900" />
  <link href="../assets/css/nucleo-icons.css" rel="stylesheet" />
  <link href="../assets/css/nucleo-svg.css" rel="stylesheet" />
  <script src="https://kit.fontawesome.com/42d5adcbca.js" crossorigin="anonymous"></script>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Material+Symbols+Rounded:opsz,wght,FILL,GRAD@24,400,0,0" />
  <link id="pagestyle" href="../assets/css/material-dashboard.css?v=3.2.0" rel="stylesheet" />
</head>

<body class="g-sidenav-show bg-gray-100">
  <aside class="sidenav navbar navbar-vertical navbar-expand-xs border-radius-lg fixed-start ms-2 bg-white my-2" id="sidenav-main">
    <div class="sidenav-header">
      <a class="navbar-brand px-4 py-3 m-0" href="dashboard.html">
        <img src="../assets/img/logo-ct-dark.png" class="navbar-brand-img" width="26" height="26" alt="main_logo">
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
          <a class="nav-link text-dark" href="products.html">
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
          <a class="nav-link active bg-gradient-dark text-white" href="reports.html">
            <i class="material-symbols-rounded opacity-5">analytics</i>
            <span class="nav-link-text ms-1">Reports</span>
          </a>
        </li>
      </ul>
    </div>
  </aside>

  <main class="main-content position-relative max-height-vh-100 h-100 border-radius-lg">
    <nav class="navbar navbar-main navbar-expand-lg px-0 mx-3 shadow-none border-radius-xl" id="navbarBlur" data-scroll="true">
      <div class="container-fluid py-1 px-3">
        <nav aria-label="breadcrumb">
          <ol class="breadcrumb bg-transparent mb-0 pb-0 pt-1 px-0 me-sm-6 me-5">
            <li class="breadcrumb-item text-sm"><a class="opacity-5 text-dark" href="javascript:;">Pages</a></li>
            <li class="breadcrumb-item text-sm text-dark active" aria-current="page">Reports</li>
          </ol>
        </nav>
      </div>
    </nav>

    <div class="container-fluid py-2">
      <!-- Report Filter Card -->
      <div class="row mb-4">
        <div class="col-12">
          <div class="card p-3">
            <div class="row align-items-center">
              <div class="col-md-4">
                <div class="input-group input-group-static">
                  <label>Start Date</label>
                  <input type="date" class="form-control">
                </div>
              </div>
              <div class="col-md-4">
                <div class="input-group input-group-static">
                  <label>End Date</label>
                  <input type="date" class="form-control">
                </div>
              </div>
              <div class="col-md-4 mt-3 mt-md-0 d-flex gap-2">
                <button class="btn bg-gradient-dark mb-0 w-100">Filter Report</button>
                <button class="btn btn-outline-dark mb-0 w-100">Export CSV</button>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Chart Visuals -->
      <div class="row mb-4">
        <div class="col-lg-8 mb-4">
          <div class="card">
            <div class="card-body">
              <h6 class="mb-0">Category Sales Breakdown</h6>
              <p class="text-sm">Monthly Revenue by Top Categories</p>
              <div class="chart">
                <canvas id="chart-reports" class="chart-canvas" height="220"></canvas>
              </div>
            </div>
          </div>
        </div>
        <div class="col-lg-4 mb-4">
          <div class="card h-100">
            <div class="card-header pb-0">
              <h6>Top Performing Categories</h6>
            </div>
            <div class="card-body">
              <ul class="list-group">
                <li class="list-group-item border-0 d-flex justify-content-between ps-0 mb-2 border-radius-lg">
                  <div class="d-flex align-items-center">
                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center border-radius-md">
                      <i class="material-symbols-rounded opacity-10">devices</i>
                    </div>
                    <div class="d-flex flex-column">
                      <h6 class="mb-1 text-dark text-sm">Electronics</h6>
                      <span class="text-xs">1,240 Sales</span>
                    </div>
                  </div>
                  <div class="d-flex align-items-center text-dark text-sm font-weight-bold">
                    $42,800
                  </div>
                </li>
                <li class="list-group-item border-0 d-flex justify-content-between ps-0 border-radius-lg">
                  <div class="d-flex align-items-center">
                    <div class="icon icon-shape icon-sm me-3 bg-gradient-dark shadow text-center border-radius-md">
                      <i class="material-symbols-rounded opacity-10">apparel</i>
                    </div>
                    <div class="d-flex flex-column">
                      <h6 class="mb-1 text-dark text-sm">Apparel</h6>
                      <span class="text-xs">830 Sales</span>
                    </div>
                  </div>
                  <div class="d-flex align-items-center text-dark text-sm font-weight-bold">
                    $18,400
                  </div>
                </li>
              </ul>
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
  <script src="../assets/js/plugins/chartjs.min.js"></script>
  <script>
    var ctx = document.getElementById("chart-reports").getContext("2d");
    new Chart(ctx, {
      type: "bar",
      data: {
        labels: ["Jan", "Feb", "Mar", "Apr", "May", "Jun"],
        datasets: [{
          label: "Sales ($)",
          tension: 0.4,
          borderWidth: 0,
          borderRadius: 4,
          borderSkipped: false,
          backgroundColor: "#43A047",
          data: [12000, 19000, 15000, 25000, 22000, 30000],
          barThickness: 'flex'
        }],
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
          y: { grid: { drawBorder: false, display: true, drawOnChartArea: true } },
          x: { grid: { drawBorder: false, display: false } }
        }
      }
    });
  </script>
  <script src="../assets/js/material-dashboard.min.js?v=3.2.0"></script>
</body>

</html>
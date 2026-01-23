<?php
require_once '../../includes/check_auth.php';
check_auth(['accountant']);
?>
<!-- Similar structure to admin dashboard but with accountant features -->
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Accountant Dashboard - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #28a745 0%, #20c997 100%);
            color: white;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link {
            color: white;
            padding: 10px 15px;
            margin: 5px 0;
        }
        .sidebar .nav-link:hover {
            background: rgba(255,255,255,0.1);
        }
        .sidebar .nav-link.active {
            background: rgba(255,255,255,0.2);
        }
        .content-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <i class="bi bi-calculator" style="font-size: 48px;"></i>
                        <h4 class="mt-2">Accountant Dashboard</h4>
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars(get_user_name()); ?></p>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-cash-stack"></i> Financial Reports
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-graph-up"></i> Depreciation
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-receipt"></i> Expenses
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-danger" href="../../auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-header">
                    <h1>Accountant Dashboard</h1>
                    <p>Financial management and reporting</p>
                </div>
                
                <div class="container-fluid mt-4">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="card bg-primary text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Total Asset Value</h5>
                                    <h2>$0</h2>
                                    <i class="bi bi-currency-dollar" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-success text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Monthly Depreciation</h5>
                                    <h2>$0</h2>
                                    <i class="bi bi-graph-down" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-warning text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Maintenance Cost</h5>
                                    <h2>$0</h2>
                                    <i class="bi bi-tools" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-info text-white">
                                <div class="card-body">
                                    <h5 class="card-title">Audit Reports</h5>
                                    <h2>0</h2>
                                    <i class="bi bi-clipboard-check" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
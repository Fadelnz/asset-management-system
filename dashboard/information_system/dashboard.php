<?php
require_once '../../includes/check_auth.php';
check_auth(['it_operation']); // Only admin can access
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>IT Operation Dashboard - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .dashboard-card {
            transition: transform 0.3s;
            height: 100%;
        }
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 20px rgba(0,0,0,0.1);
        }
        .card-icon {
            font-size: 3rem;
            margin-bottom: 1rem;
        }
    </style>
</head>
<body>
    <!-- Navigation Bar -->
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark">
        <div class="container-fluid">
            <a class="navbar-brand" href="#">
                <i class="bi bi-hdd-stack"></i> IT Operation Dashboard
            </a>
            <div class="navbar-nav ms-auto">
                <span class="navbar-text me-3">
                    <i class="bi bi-person-circle"></i> <?php echo htmlspecialchars($_SESSION['full_name']); ?>
                </span>
                <a href="../../auth/logout.php" class="btn btn-outline-light">
                    <i class="bi bi-box-arrow-right"></i> Logout
                </a>
            </div>
        </div>
    </nav>

    <div class="container-fluid mt-4">
        <div class="row mb-4">
            <div class="col">
                <div class="card bg-primary text-white">
                    <div class="card-body">
                        <h2><i class="bi bi-shield-check"></i> System Administration</h2>
                        <p class="mb-0">Full system access and control</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="row g-4">
            <!-- Admin Access -->
            <div class="col-md-3">
                <a href="../admin/dashboard.php" class="text-decoration-none">
                    <div class="card dashboard-card">
                        <div class="card-body text-center">
                            <div class="card-icon text-primary">
                                <i class="bi bi-person-badge"></i>
                            </div>
                            <h5 class="card-title">Admin Dashboard</h5>
                            <p class="card-text text-muted">User management and system configuration</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Accountant Access -->
            <div class="col-md-3">
                <a href="../accountant/dashboard.php" class="text-decoration-none">
                    <div class="card dashboard-card">
                        <div class="card-body text-center">
                            <div class="card-icon text-success">
                                <i class="bi bi-calculator"></i>
                            </div>
                            <h5 class="card-title">Accountant Dashboard</h5>
                            <p class="card-text text-muted">Financial reports and asset valuation</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Logistics Access -->
            <div class="col-md-3">
                <a href="../logistic_operation/dashboard.php" class="text-decoration-none">
                    <div class="card dashboard-card">
                        <div class="card-body text-center">
                            <div class="card-icon text-warning">
                                <i class="bi bi-truck"></i>
                            </div>
                            <h5 class="card-title">Logistics Dashboard</h5>
                            <p class="card-text text-muted">Asset tracking and movement management</p>
                        </div>
                    </div>
                </a>
            </div>

            <!-- Operations Access -->
            <div class="col-md-3">
                <a href="../operations/manager_dashboard.php" class="text-decoration-none">
                    <div class="card dashboard-card">
                        <div class="card-body text-center">
                            <div class="card-icon text-info">
                                <i class="bi bi-gear"></i>
                            </div>
                            <h5 class="card-title">Operations Dashboard</h5>
                            <p class="card-text text-muted">Operational management and team coordination</p>
                        </div>
                    </div>
                </a>
            </div>
        </div>

        <div class="row g-4 mt-2">
            <!-- System Tools -->
            <div class="col-md-4">
                <div class="card dashboard-card">
                    <div class="card-body">
                        <h5 class="card-title"><i class="bi bi-tools text-danger"></i> System Tools</h5>
                        <ul class="list-unstyled">
                            <li><a href="#" class="text-decoration-none">Database Backup</a></li>
                            <li><a href="#" class="text-decoration-none">System Logs</a></li>
                            <li><a href="#" class="text-decoration-none">User Activity Monitor</a></li>
                            <li><a href="#" class="text-decoration-none">System Settings</a></li>
                        </ul>
                    </div>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="col-md-8">
                <div class="card">
                    <div class="card-header">
                        <h5 class="mb-0"><i class="bi bi-speedometer2"></i> System Overview</h5>
                    </div>
                    <div class="card-body">
                        <div class="row text-center">
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3>150</h3>
                                    <p class="text-muted mb-0">Total Users</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3>2,450</h3>
                                    <p class="text-muted mb-0">Total Assets</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3>98%</h3>
                                    <p class="text-muted mb-0">System Uptime</p>
                                </div>
                            </div>
                            <div class="col-md-3">
                                <div class="border rounded p-3">
                                    <h3>12</h3>
                                    <p class="text-muted mb-0">Active Sessions</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
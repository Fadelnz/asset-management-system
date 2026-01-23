<?php
require_once '../../includes/check_auth.php';
check_auth(['warehouse_coordinator']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Warehouse Coordinator Dashboard - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #ff6b6b 0%, #ee5a52 100%);
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
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .asset-card {
            transition: transform 0.2s;
            border-left: 4px solid #ff6b6b;
        }
        .asset-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
            <nav class="col-md-3 col-lg-2 d-md-block sidebar">
                <div class="position-sticky">
                    <div class="text-center mb-4">
                        <i class="bi bi-warehouse" style="font-size: 48px;"></i>
                        <h4 class="mt-2">Warehouse Dashboard</h4>
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars(get_user_name()); ?></p>
                        <small class="text-light">Coordinator</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="bi bi-speedometer2"></i> Dashboard
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-box-seam"></i> Inventory
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-barcode"></i> Scan Assets
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-arrow-left-right"></i> Check In/Out
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-truck"></i> Receiving
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-clipboard-check"></i> Audit
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-map"></i> Location Tracking
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="bi bi-printer"></i> Labels & Reports
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="#">
                                <i class="bi bi-bell"></i> Notifications
                                <span class="badge bg-danger rounded-pill">3</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../../auth/logout.php">
                                <i class="bi bi-box-arrow-right"></i> Logout
                            </a>
                        </li>
                    </ul>
                </div>
            </nav>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>Warehouse Dashboard</h1>
                            <p>Manage inventory, track assets, and oversee warehouse operations</p>
                        </div>
                        <div>
                            <button class="btn btn-danger">
                                <i class="bi bi-plus-circle"></i> New Receiving
                            </button>
                            <button class="btn btn-outline-danger">
                                <i class="bi bi-qr-code-scan"></i> Scan
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-3 mb-4">
                        <div class="card border-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Total Assets</h6>
                                        <h3 class="mb-0">1,247</h3>
                                        <small class="text-success">+12 this week</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box text-danger" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Checked Out</h6>
                                        <h3 class="mb-0">189</h3>
                                        <small class="text-warning">18 pending return</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-arrow-up-right text-warning" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card border-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Low Stock</h6>
                                        <h3 class="mb-0">23</h3>
                                        <small class="text-danger">Needs attention</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-exclamation-triangle text-info" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card border-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Ready for Issue</h6>
                                        <h3 class="mb-0">856</h3>
                                        <small class="text-success">Available</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle text-success" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Recent Activity and Pending Actions -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <!-- Recent Asset Movements -->
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-clock-history"></i> Recent Asset Movements
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Asset ID</th>
                                                <th>Description</th>
                                                <th>Action</th>
                                                <th>User</th>
                                                <th>Time</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr>
                                                <td>AST-2024-00123</td>
                                                <td>Laptop - Dell XPS 15</td>
                                                <td>
                                                    <span class="badge bg-warning">Checked Out</span>
                                                </td>
                                                <td>John Smith</td>
                                                <td>10:30 AM</td>
                                                <td><span class="badge bg-success">Active</span></td>
                                            </tr>
                                            <tr>
                                                <td>AST-2024-00124</td>
                                                <td>Monitor - LG 27" 4K</td>
                                                <td>
                                                    <span class="badge bg-info">Checked In</span>
                                                </td>
                                                <td>Sarah Johnson</td>
                                                <td>09:45 AM</td>
                                                <td><span class="badge bg-success">Active</span></td>
                                            </tr>
                                            <tr>
                                                <td>AST-2024-00125</td>
                                                <td>Projector - Epson EB-U05</td>
                                                <td>
                                                    <span class="badge bg-primary">Maintenance</span>
                                                </td>
                                                <td>Warehouse</td>
                                                <td>Yesterday</td>
                                                <td><span class="badge bg-warning">In Repair</span></td>
                                            </tr>
                                            <tr>
                                                <td>AST-2024-00126</td>
                                                <td>Tablet - iPad Pro</td>
                                                <td>
                                                    <span class="badge bg-danger">Returned Damaged</span>
                                                </td>
                                                <td>Mike Wilson</td>
                                                <td>Yesterday</td>
                                                <td><span class="badge bg-danger">Damaged</span></td>
                                            </tr>
                                            <tr>
                                                <td>AST-2024-00127</td>
                                                <td>Chair - Ergonomic Office</td>
                                                <td>
                                                    <span class="badge bg-success">New Entry</span>
                                                </td>
                                                <td>Warehouse</td>
                                                <td>2 days ago</td>
                                                <td><span class="badge bg-success">Available</span></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <a href="#" class="btn btn-outline-danger btn-sm">View All Movements</a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions & Pending Tasks -->
                    <div class="col-md-4">
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-list-check"></i> Pending Actions
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="list-group">
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-exclamation-triangle text-danger"></i>
                                            Asset Returns Due Today
                                        </div>
                                        <span class="badge bg-danger rounded-pill">5</span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-tools text-warning"></i>
                                            Maintenance Requests
                                        </div>
                                        <span class="badge bg-warning rounded-pill">3</span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-truck text-info"></i>
                                            New Deliveries
                                        </div>
                                        <span class="badge bg-info rounded-pill">2</span>
                                    </a>
                                    <a href="#" class="list-group-item list-group-item-action d-flex justify-content-between align-items-center">
                                        <div>
                                            <i class="bi bi-clipboard-check text-success"></i>
                                            Audit Scheduled
                                        </div>
                                        <span class="badge bg-success rounded-pill">1</span>
                                    </a>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Quick Scan -->
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="bi bi-qr-code-scan"></i> Quick Scan
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="text-center">
                                    <i class="bi bi-upc-scan" style="font-size: 60px; color: #6c757d;"></i>
                                    <p class="mt-3">Scan asset barcode to check in/out</p>
                                    <div class="input-group mb-3">
                                        <input type="text" class="form-control" placeholder="Enter barcode or scan">
                                        <button class="btn btn-info" type="button">
                                            <i class="bi bi-search"></i>
                                        </button>
                                    </div>
                                    <button class="btn btn-outline-info w-100">
                                        <i class="bi bi-camera"></i> Use Camera
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Low Stock Alert -->
                <div class="card mt-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="bi bi-exclamation-triangle"></i> Low Stock Alert
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="asset-card card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Mouse - Wireless</h6>
                                                <p class="text-muted mb-1">Logitech MX Master 3</p>
                                                <small>Stock: <span class="text-danger">5 units</span></p>
                                            </div>
                                            <div>
                                                <span class="badge bg-danger">Reorder</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="asset-card card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Keyboard Covers</h6>
                                                <p class="text-muted mb-1">MacBook Pro 16"</p>
                                                <small>Stock: <span class="text-warning">8 units</span></p>
                                            </div>
                                            <div>
                                                <span class="badge bg-warning">Low</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="asset-card card mb-3">
                                    <div class="card-body">
                                        <div class="d-flex justify-content-between">
                                            <div>
                                                <h6>Docking Stations</h6>
                                                <p class="text-muted mb-1">Dell WD19</p>
                                                <small>Stock: <span class="text-danger">3 units</span></p>
                                            </div>
                                            <div>
                                                <span class="badge bg-danger">Critical</span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div class="text-center">
                            <a href="#" class="btn btn-outline-danger">
                                <i class="bi bi-cart-plus"></i> Create Purchase Orders
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
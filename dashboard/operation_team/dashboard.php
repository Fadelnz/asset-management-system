<?php
require_once '../../includes/check_auth.php';
check_auth(['operation_team']);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Operation Manager Dashboard - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        .sidebar {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
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
        .metric-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        .metric-card:before {
            content: '';
            position: absolute;
            top: 0;
            right: 0;
            width: 100px;
            height: 100px;
            background: rgba(255,255,255,0.1);
            border-radius: 50%;
            transform: translate(30px, -30px);
        }
        .metric-card h2 {
            font-size: 2.5rem;
            font-weight: bold;
            margin-bottom: 5px;
        }
        .kpi-badge {
            font-size: 0.8rem;
            padding: 0.3rem 0.8rem;
            border-radius: 20px;
        }
        .timeline {
            position: relative;
            padding-left: 30px;
        }
        .timeline:before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0;
            bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item {
            position: relative;
            margin-bottom: 20px;
        }
        .timeline-item:before {
            content: '';
            position: absolute;
            left: -23px;
            top: 5px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #007bff;
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
                        <i class="fas fa-chart-line" style="font-size: 48px;"></i>
                        <h4 class="mt-2">Operations Dashboard</h4>
                        <p class="mb-0">Welcome, <?php echo htmlspecialchars(get_user_name()); ?></p>
                        <small class="text-light">Manager</small>
                    </div>
                    
                    <ul class="nav flex-column">
                        <li class="nav-item">
                            <a class="nav-link active" href="#">
                                <i class="fas fa-tachometer-alt"></i> Overview
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-project-diagram"></i> Process Flow
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-balance-scale"></i> Performance Metrics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-users"></i> Team Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-clipboard-list"></i> Work Orders
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-tasks"></i> Task Management
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-chart-pie"></i> Analytics
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link" href="#">
                                <i class="fas fa-cogs"></i> Process Optimization
                            </a>
                        </li>
                        <li class="nav-item mt-4">
                            <a class="nav-link text-warning" href="#">
                                <i class="fas fa-bell"></i> Alerts
                                <span class="badge bg-danger rounded-pill">5</span>
                            </a>
                        </li>
                        <li class="nav-item">
                            <a class="nav-link text-danger" href="../../auth/logout.php">
                                <i class="fas fa-sign-out-alt"></i> Logout
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
                            <h1>Operations Management Dashboard</h1>
                            <p>Monitor performance, optimize processes, and manage operations</p>
                        </div>
                        <div class="btn-group">
                            <button class="btn btn-primary">
                                <i class="fas fa-download"></i> Export Report
                            </button>
                            <button class="btn btn-outline-primary">
                                <i class="fas fa-cog"></i> Settings
                            </button>
                        </div>
                    </div>
                </div>
                
                <!-- KPI Metrics -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Asset Utilization</h6>
                                    <h2>78%</h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">+2.5%</span>
                                </div>
                                <i class="fas fa-chart-bar" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Operational Efficiency</h6>
                                    <h2>92%</h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">+1.8%</span>
                                </div>
                                <i class="fas fa-bolt" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Maintenance Uptime</h6>
                                    <h2>96.5%</h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">+0.5%</span>
                                </div>
                                <i class="fas fa-wrench" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Downtime Rate</h6>
                                    <h2>3.5%</h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">-1.2%</span>
                                </div>
                                <i class="fas fa-exclamation-triangle" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Charts and Analytics -->
                <div class="row mt-4">
                    <div class="col-md-8">
                        <!-- Performance Chart -->
                        <div class="card">
                            <div class="card-header bg-primary text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-chart-line"></i> Operational Performance - Last 30 Days
                                </h5>
                            </div>
                            <div class="card-body">
                                <!-- Placeholder for chart -->
                                <div style="height: 300px; background: #f8f9fa; border-radius: 5px; display: flex; align-items: center; justify-content: center;">
                                    <div class="text-center">
                                        <i class="fas fa-chart-line" style="font-size: 60px; color: #6c757d;"></i>
                                        <p class="mt-2">Performance chart would be displayed here</p>
                                        <p class="text-muted">(Utilization vs Efficiency vs Downtime)</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Process Status -->
                        <div class="card mt-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-project-diagram"></i> Current Process Status
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="row text-center">
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-play-circle fa-2x text-primary"></i>
                                            <h5 class="mt-2">45</h5>
                                            <small>In Progress</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-check-circle fa-2x text-success"></i>
                                            <h5 class="mt-2">128</h5>
                                            <small>Completed</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-pause-circle fa-2x text-warning"></i>
                                            <h5 class="mt-2">12</h5>
                                            <small>On Hold</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-exclamation-circle fa-2x text-danger"></i>
                                            <h5 class="mt-2">7</h5>
                                            <small>Delayed</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Sidebar - Quick Stats -->
                    <div class="col-md-4">
                        <!-- Team Performance -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users"></i> Team Performance
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Warehouse Team</span>
                                        <span>85%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: 85%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Maintenance Team</span>
                                        <span>92%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-primary" style="width: 92%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Operations Team</span>
                                        <span>78%</span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-info" style="width: 78%"></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Recent Activity Timeline -->
                        <div class="card">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-history"></i> Recent Activity
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="timeline">
                                    <div class="timeline-item">
                                        <small class="text-muted">10:30 AM</small>
                                        <p class="mb-1">Process optimization approved</p>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">9:45 AM</small>
                                        <p class="mb-1">New workflow implemented</p>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">Yesterday, 3:15 PM</small>
                                        <p class="mb-1">Performance review completed</p>
                                    </div>
                                    <div class="timeline-item">
                                        <small class="text-muted">Yesterday, 11:00 AM</small>
                                        <p class="mb-1">Team meeting conducted</p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Operational Alerts -->
                <div class="card mt-4">
                    <div class="card-header bg-danger text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-exclamation-triangle"></i> Critical Operational Alerts
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-clock me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Process Delay - Asset Transfer</h6>
                                <p class="mb-0">Asset transfer from Warehouse A to B delayed by 2 hours</p>
                            </div>
                            <button class="btn btn-outline-warning btn-sm">Resolve</button>
                        </div>
                        
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-tools me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Maintenance Overdue</h6>
                                <p class="mb-0">3 critical assets have overdue maintenance schedules</p>
                            </div>
                            <button class="btn btn-outline-danger btn-sm">Schedule</button>
                        </div>
                        
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-chart-line me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Performance Drop Detected</h6>
                                <p class="mb-0">Warehouse throughput decreased by 15% this week</p>
                            </div>
                            <button class="btn btn-outline-info btn-sm">Analyze</button>
                        </div>
                    </div>
                </div>
                
                <!-- Process Improvement Suggestions -->
                <div class="card mt-4">
                    <div class="card-header bg-success text-white">
                        <h5 class="card-title mb-0">
                            <i class="fas fa-lightbulb"></i> Process Improvement Suggestions
                        </h5>
                    </div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-4">
                                <div class="card border-success mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-robot text-success"></i> Automate Check-in</h6>
                                        <p class="small">Implement barcode scanning automation to reduce manual entry by 40%</p>
                                        <span class="badge bg-success">High Impact</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-info mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-route text-info"></i> Optimize Routing</h6>
                                        <p class="small">Redesign warehouse layout to reduce asset retrieval time by 25%</p>
                                        <span class="badge bg-info">Medium Impact</span>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4">
                                <div class="card border-warning mb-3">
                                    <div class="card-body">
                                        <h6><i class="fas fa-clipboard-check text-warning"></i> Standardize Procedures</h6>
                                        <p class="small">Create standard operating procedures for asset handling</p>
                                        <span class="badge bg-warning">Low Impact</span>
                                    </div>
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
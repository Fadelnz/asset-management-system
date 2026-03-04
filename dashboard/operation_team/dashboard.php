<?php
require_once '../../includes/check_auth.php';
check_auth(['operation_team']);
require_once '../../includes/db.php';

$conn    = $db->conn;
$user_id = $_SESSION['user_id'];

$s = $conn->prepare("SELECT COUNT(*) as c FROM assets WHERE assigned_to_user_id = ? AND is_active = 1");
$s->bind_param("s", $user_id); $s->execute();
$my_assets_count = $s->get_result()->fetch_assoc()['c']; $s->close();

$total_assets      = $conn->query("SELECT COUNT(*) as c FROM assets WHERE is_active = 1")->fetch_assoc()['c'];
$assigned_count    = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_status = 'Assigned' AND is_active = 1")->fetch_assoc()['c'];
$in_stock_count    = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_status = 'In Stock' AND is_active = 1")->fetch_assoc()['c'];
$maintenance_count = $conn->query("SELECT COUNT(*) as c FROM assets WHERE asset_status = 'Maintenance' AND is_active = 1")->fetch_assoc()['c'];
$utilization       = $total_assets > 0 ? round(($assigned_count / $total_assets) * 100, 1) : 0;

$s = $conn->prepare("SELECT COUNT(*) as c FROM asset_movements WHERE performed_by_user_id = ? AND status = 'Pending'");
$s->bind_param("s", $user_id); $s->execute();
$pending_count = $s->get_result()->fetch_assoc()['c']; $s->close();

$s = $conn->prepare("SELECT COUNT(*) as c FROM asset_movements WHERE performed_by_user_id = ? AND status = 'Approved'");
$s->bind_param("s", $user_id); $s->execute();
$approved_count = $s->get_result()->fetch_assoc()['c']; $s->close();

$s = $conn->prepare("SELECT COUNT(*) as c FROM asset_movements WHERE performed_by_user_id = ? AND status = 'Rejected'");
$s->bind_param("s", $user_id); $s->execute();
$rejected_count = $s->get_result()->fetch_assoc()['c']; $s->close();

$total_req    = $approved_count + $pending_count + $rejected_count;
$approved_pct = $total_req > 0 ? round(($approved_count / $total_req) * 100) : 0;
$pending_pct  = $total_req > 0 ? round(($pending_count  / $total_req) * 100) : 0;
$rejected_pct = $total_req > 0 ? round(($rejected_count / $total_req) * 100) : 0;

$s = $conn->prepare("SELECT m.*, a.asset_name FROM asset_movements m LEFT JOIN assets a ON m.asset_id = a.asset_id WHERE m.performed_by_user_id = ? ORDER BY m.movement_date DESC LIMIT 4");
$s->bind_param("s", $user_id); $s->execute();
$recent_movements = $s->get_result(); $s->close();
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
            <?php include 'opsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1>Operations Management Dashboard</h1>
                            <p>Monitor performance, optimize processes, and manage operations</p>
                        </div>
                        <div class="btn-group">
                            <a href="issue_return.php" class="btn btn-primary">
                                <i class="fas fa-exchange-alt"></i> Issue / Return
                            </a>
                            <a href="my_requests.php" class="btn btn-outline-primary">
                                <i class="fas fa-clipboard-check"></i> My Requests
                                <?php if ($pending_count > 0): ?>
                                    <span class="badge bg-warning text-dark ms-1"><?php echo $pending_count; ?></span>
                                <?php endif; ?>
                            </a>
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
                                    <h2><?php echo $utilization; ?>%</h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);"><?php echo $assigned_count; ?>/<?php echo $total_assets; ?> assigned</span>
                                </div>
                                <i class="fas fa-chart-bar" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #28a745 0%, #1e7e34 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>My Assigned Assets</h6>
                                    <h2><?php echo $my_assets_count; ?></h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">Currently held</span>
                                </div>
                                <i class="fas fa-bolt" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #ffc107 0%, #e0a800 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Pending Requests</h6>
                                    <h2><?php echo $pending_count; ?></h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">Awaiting approval</span>
                                </div>
                                <i class="fas fa-wrench" style="font-size: 40px; opacity: 0.5;"></i>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3">
                        <div class="metric-card" style="background: linear-gradient(135deg, #dc3545 0%, #c82333 100%);">
                            <div class="d-flex justify-content-between align-items-start">
                                <div>
                                    <h6>Under Maintenance</h6>
                                    <h2><?php echo $maintenance_count; ?></h2>
                                    <span class="kpi-badge" style="background: rgba(255,255,255,0.3);">Assets affected</span>
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
                                            <h5 class="mt-2"><?php echo $assigned_count; ?></h5>
                                            <small>Assigned</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-check-circle fa-2x text-success"></i>
                                            <h5 class="mt-2"><?php echo $in_stock_count; ?></h5>
                                            <small>In Stock</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-pause-circle fa-2x text-warning"></i>
                                            <h5 class="mt-2"><?php echo $maintenance_count; ?></h5>
                                            <small>Maintenance</small>
                                        </div>
                                    </div>
                                    <div class="col-md-3 mb-3">
                                        <div class="border rounded p-3">
                                            <i class="fas fa-exclamation-circle fa-2x text-danger"></i>
                                            <h5 class="mt-2"><?php echo $pending_count; ?></h5>
                                            <small>My Pending</small>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Right Sidebar - Quick Stats -->
                    <div class="col-md-4">
                        <!-- My Request Summary -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="card-title mb-0">
                                    <i class="fas fa-users"></i> My Request Summary
                                </h5>
                            </div>
                            <div class="card-body">
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Approved</span>
                                        <span><?php echo $approved_count; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-success" style="width: <?php echo $approved_pct; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Pending</span>
                                        <span><?php echo $pending_count; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-warning" style="width: <?php echo $pending_pct; ?>%"></div>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <div class="d-flex justify-content-between mb-1">
                                        <span>Rejected</span>
                                        <span><?php echo $rejected_count; ?></span>
                                    </div>
                                    <div class="progress" style="height: 8px;">
                                        <div class="progress-bar bg-danger" style="width: <?php echo $rejected_pct; ?>%"></div>
                                    </div>
                                </div>
                                <a href="my_requests.php" class="btn btn-outline-success btn-sm w-100 mt-1">
                                    <i class="fas fa-list me-1"></i> View All My Requests
                                </a>
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
                                    <?php if ($recent_movements && $recent_movements->num_rows > 0): ?>
                                        <?php while ($m = $recent_movements->fetch_assoc()): ?>
                                        <div class="timeline-item">
                                            <small class="text-muted"><?php echo date('d/m/Y H:i', strtotime($m['movement_date'])); ?></small>
                                            <p class="mb-1">
                                                <span class="badge <?php echo $m['movement_type'] === 'Issue' ? 'bg-primary' : 'bg-secondary'; ?> me-1">
                                                    <?php echo $m['movement_type']; ?>
                                                </span>
                                                <?php echo htmlspecialchars($m['asset_name'] ?? $m['asset_id']); ?>
                                            </p>
                                            <small class="<?php echo $m['status'] === 'Approved' ? 'text-success' : ($m['status'] === 'Rejected' ? 'text-danger' : 'text-warning'); ?>">
                                                &#9679; <?php echo $m['status']; ?>
                                            </small>
                                        </div>
                                        <?php endwhile; ?>
                                    <?php else: ?>
                                        <p class="text-muted small">No recent activity yet.</p>
                                    <?php endif; ?>
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
                        <?php if ($pending_count > 0): ?>
                        <div class="alert alert-warning d-flex align-items-center" role="alert">
                            <i class="fas fa-clock me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Pending Requests</h6>
                                <p class="mb-0">You have <strong><?php echo $pending_count; ?></strong> request(s) awaiting approval from the Logistics team.</p>
                            </div>
                            <a href="my_requests.php" class="btn btn-outline-warning btn-sm">View</a>
                        </div>
                        <?php endif; ?>
                        <?php if ($my_assets_count > 0): ?>
                        <div class="alert alert-info d-flex align-items-center" role="alert">
                            <i class="fas fa-box me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Assets in Your Possession</h6>
                                <p class="mb-0">You currently hold <strong><?php echo $my_assets_count; ?></strong> asset(s). Return them when no longer needed.</p>
                            </div>
                            <a href="issue_return.php" class="btn btn-outline-info btn-sm">Return</a>
                        </div>
                        <?php endif; ?>
                        <?php if ($maintenance_count > 0): ?>
                        <div class="alert alert-danger d-flex align-items-center" role="alert">
                            <i class="fas fa-tools me-3 fa-2x"></i>
                            <div class="flex-grow-1">
                                <h6 class="alert-heading mb-1">Maintenance Overdue</h6>
                                <p class="mb-0"><strong><?php echo $maintenance_count; ?></strong> critical asset(s) have overdue maintenance schedules.</p>
                            </div>
                            <button class="btn btn-outline-danger btn-sm">Schedule</button>
                        </div>
                        <?php endif; ?>
                        <?php if ($pending_count === 0 && $my_assets_count === 0 && $maintenance_count === 0): ?>
                        <div class="alert alert-success d-flex align-items-center mb-0" role="alert">
                            <i class="fas fa-check-circle me-3 fa-2x"></i>
                            <div>
                                <h6 class="alert-heading mb-1">All Clear!</h6>
                                <p class="mb-0">No pending actions. Everything is up to date.</p>
                            </div>
                        </div>
                        <?php endif; ?>
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
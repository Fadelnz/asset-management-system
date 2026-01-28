<?php
require_once '../../includes/check_auth.php';
check_auth(['admin']); // Only admin can access

// Database connection for stats
require_once '../../includes/db.php';
$conn = $db->conn;

// Fetch stats
$total_users = 0;
$active_assets = 0;
$pending_requests = 0;
$total_departments = 0;

// Fetch total users
$sql_users = "SELECT COUNT(*) as total FROM users";
$result_users = $conn->query($sql_users);
if ($result_users && $result_users->num_rows > 0) {
    $row = $result_users->fetch_assoc();
    $total_users = $row['total'];
}

// Fetch total departments
$sql_dept = "SELECT COUNT(*) as total FROM departments";
$result_dept = $conn->query($sql_dept);
if ($result_dept && $result_dept->num_rows > 0) {
    $row = $result_dept->fetch_assoc();
    $total_departments = $row['total'];
}

// Fetch recent activity logs
$recent_activities = [];
$sql_logs = "SELECT al.*, u.username, u.full_name 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.user_id 
             ORDER BY al.created_at DESC 
             LIMIT 10";
$result_logs = $conn->query($sql_logs);
if ($result_logs && $result_logs->num_rows > 0) {
    $recent_activities = $result_logs->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'adsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-header">
                    <h1>Admin Dashboard</h1>
                    <p>Manage system users, assets, and configurations</p>
                </div>
                
                <div class="container-fluid mt-4">
                    <div class="row">
                        <div class="col-md-3 mb-4">
                            <div class="card bg-primary text-white card-stat">
                                <div class="card-body">
                                    <h5 class="card-title">Total Users</h5>
                                    <h2><?php echo $total_users; ?></h2>
                                    <i class="bi bi-people" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-success text-white card-stat">
                                <div class="card-body">
                                    <h5 class="card-title">Active Assets</h5>
                                    <h2><?php echo $active_assets; ?></h2>
                                    <i class="bi bi-box" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-warning text-white card-stat">
                                <div class="card-body">
                                    <h5 class="card-title">Pending Requests</h5>
                                    <h2><?php echo $pending_requests; ?></h2>
                                    <i class="bi bi-clock-history" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                        <div class="col-md-3 mb-4">
                            <div class="card bg-info text-white card-stat">
                                <div class="card-body">
                                    <h5 class="card-title">Departments</h5>
                                    <h2><?php echo $total_departments; ?></h2>
                                    <i class="bi bi-building" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="card mt-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0">Recent Activity</h5>
                        </div>
                        <div class="card-body">
                            <?php if (!empty($recent_activities)): ?>
                                <div class="list-group">
                                    <?php foreach ($recent_activities as $activity): ?>
                                        <div class="list-group-item list-group-item-action">
                                            <div class="d-flex w-100 justify-content-between">
                                                <h6 class="mb-1">
                                                    <strong><?php echo htmlspecialchars($activity['username'] ?? 'System'); ?></strong> 
                                                    - <?php echo htmlspecialchars($activity['activity']); ?>
                                                </h6>
                                                <small class="text-muted"><?php echo date('M d, H:i', strtotime($activity['created_at'])); ?></small>
                                            </div>
                                            <p class="mb-1"><?php echo htmlspecialchars(substr($activity['details'] ?? 'No details', 0, 100)); ?>...</p>
                                            <small class="text-muted">IP: <?php echo htmlspecialchars($activity['ip_address'] ?? 'N/A'); ?></small>
                                        </div>
                                    <?php endforeach; ?>
                                </div>
                            <?php else: ?>
                                <p class="text-muted">No recent activity</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
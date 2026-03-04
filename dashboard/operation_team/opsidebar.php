<?php
$current_page = basename($_SERVER['PHP_SELF']);

require_once '../../includes/db.php';
$uid = $_SESSION['user_id'];
$pending_count = 0;
$pq = $db->conn->prepare("SELECT COUNT(*) as c FROM asset_movements WHERE performed_by_user_id = ? AND status = 'Pending'");
if ($pq) {
    $pq->bind_param("s", $uid);
    $pq->execute();
    $pending_count = $pq->get_result()->fetch_assoc()['c'];
    $pq->close();
}
?>

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
</style>
<nav class="col-md-3 col-lg-2 d-md-block sidebar">
    <div class="position-sticky">
        <div class="text-center mb-4">
            <i class="fas fa-chart-line" style="font-size: 48px;"></i>
            <h4 class="mt-2">Operations Dashboard</h4>
            <p class="mb-0">Welcome, <?php echo htmlspecialchars($_SESSION['full_name'] ?? 'User'); ?></p>
            <small class="text-light">Operation Team</small>
        </div>

        <ul class="nav flex-column">
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'dashboard.php' ? 'active' : ''; ?>" href="dashboard.php">
                    <i class="fas fa-tachometer-alt"></i> Overview
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'my_assets.php' ? 'active' : ''; ?>" href="my_assets.php">
                    <i class="fas fa-boxes"></i> My Assets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'issue_return.php' ? 'active' : ''; ?>" href="issue_return.php">
                    <i class="fas fa-exchange-alt"></i> Issue / Return
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link <?php echo $current_page === 'history.php' ? 'active' : ''; ?>" href="history.php">
                    <i class="fas fa-clipboard-list"></i> My Requests
                    <?php if ($pending_count > 0): ?>
                        <span class="badge bg-warning text-dark ms-2"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
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
            <li class="nav-item mt-4">
                <a class="nav-link text-warning" href="#">
                    <i class="fas fa-bell"></i> Alerts
                    <?php if ($pending_count > 0): ?>
                        <span class="badge bg-danger rounded-pill"><?php echo $pending_count; ?></span>
                    <?php endif; ?>
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
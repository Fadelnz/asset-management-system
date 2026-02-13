<style>
    .sidebar {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
    
    /* Common dashboard styles */
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

<!-- Sidebar HTML -->
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
                <a class="nav-link active" href="dashboard.php">
                    <i class="bi bi-speedometer2"></i> Dashboard
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="register_asset.php">
                    <i class="bi bi-plus-circle"></i> Register Assets
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-clipboard-data"></i> Inventory / Asset Reports
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-arrow-left-right"></i> Issue / Return 
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="#">
                    <i class="bi bi-check-circle"></i> Approvals / Requests
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="vehicle_management.php">
                    <i class="bi bi-truck"></i> Vehicle Management
                </a>
            </li>
            <li class="nav-item">
                <a class="nav-link" href="maintenance.php">
                    <i class="bi bi-tools"></i> Maintenance
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
<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation', 'operation_manager', 'admin', 'accountant']);

require_once '../../includes/db.php';

$conn = $db->conn;
$user_id = $_SESSION['user_id'];

// Handle Export Requests
if (isset($_GET['export']) && $_GET['export'] == 'csv') {
    exportToCSV($conn);
    exit();
}

// Get filter parameters
$filter_class = $_GET['filter_class'] ?? '';
$filter_status = $_GET['filter_status'] ?? '';
$filter_location = $_GET['filter_location'] ?? '';
$filter_department = $_GET['filter_department'] ?? '';
$filter_date_from = $_GET['date_from'] ?? '';
$filter_date_to = $_GET['date_to'] ?? '';
$filter_warranty = $_GET['warranty_status'] ?? '';
$search = $_GET['search'] ?? '';

// Show success message after registration redirect
$reg_success = isset($_GET['registered']) ? htmlspecialchars($_GET['asset'] ?? 'Asset') : '';

// Build the query with filters
$sql = "SELECT a.*, 
               ac.class_name,
               l.name as location_name,
               d.name as department_name,
               u.full_name as assigned_to_name,
               u2.full_name as created_by_name,
               vd.license_plate, vd.vehicle_type, vd.current_mileage, vd.last_service_date,
               CASE 
                   WHEN a.warranty_expiry IS NULL THEN 'No Warranty'
                   WHEN a.warranty_expiry < CURDATE() THEN 'Expired'
                   WHEN a.warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
                   ELSE 'Active'
               END as warranty_status,
               DATEDIFF(a.warranty_expiry, CURDATE()) as days_until_warranty_expiry,
               CASE
                   WHEN a.asset_status = 'In Stock' THEN 'success'
                   WHEN a.asset_status = 'Assigned' THEN 'primary'
                   WHEN a.asset_status = 'Maintenance' THEN 'warning'
                   WHEN a.asset_status = 'Retired' THEN 'secondary'
                   WHEN a.asset_status = 'Disposed' THEN 'danger'
                   ELSE 'info'
               END as status_color
        FROM assets a
        LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN departments d ON a.owner_department_id = d.department_id
        LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
        LEFT JOIN users u2 ON a.created_by = u2.user_id
        LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
        WHERE a.is_active = 1";

$params = [];
$types = "";

if (!empty($filter_class)) {
    $sql .= " AND a.asset_class = ?";
    $params[] = $filter_class;
    $types .= "s";
}

if (!empty($filter_status)) {
    $sql .= " AND a.asset_status = ?";
    $params[] = $filter_status;
    $types .= "s";
}

if (!empty($filter_location)) {
    $sql .= " AND a.location_id = ?";
    $params[] = $filter_location;
    $types .= "s";
}

if (!empty($filter_department)) {
    $sql .= " AND a.owner_department_id = ?";
    $params[] = $filter_department;
    $types .= "s";
}

if (!empty($filter_date_from)) {
    $sql .= " AND DATE(a.acquisition_date) >= ?";
    $params[] = $filter_date_from;
    $types .= "s";
}

if (!empty($filter_date_to)) {
    $sql .= " AND DATE(a.acquisition_date) <= ?";
    $params[] = $filter_date_to;
    $types .= "s";
}

if (!empty($filter_warranty)) {
    switch($filter_warranty) {
        case 'active':
            $sql .= " AND a.warranty_expiry >= CURDATE()";
            break;
        case 'expired':
            $sql .= " AND a.warranty_expiry < CURDATE()";
            break;
        case 'expiring_soon':
            $sql .= " AND a.warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)";
            break;
        case 'no_warranty':
            $sql .= " AND a.warranty_expiry IS NULL";
            break;
    }
}

if (!empty($search)) {
    $sql .= " AND (a.asset_name LIKE ? OR a.asset_tag LIKE ? OR a.model LIKE ? OR a.serial_number LIKE ? OR a.asset_id LIKE ?)";
    $search_term = "%$search%";
    for ($i = 0; $i < 5; $i++) {
        $params[] = $search_term;
    }
    $types .= "sssss";
}

$sql .= " ORDER BY a.created_at DESC";

// Prepare and execute
$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$result = $stmt->get_result();

$assets = [];
$total_value = 0;
$total_assets = 0;

if ($result) {
    while ($row = $result->fetch_assoc()) {
        $assets[] = $row;
        $total_value += $row['cost'] ?? 0;
        $total_assets++;
    }
}

// Get filter dropdown data
$asset_classes = $conn->query("SELECT * FROM asset_classes ORDER BY class_name");
$locations = $conn->query("SELECT * FROM locations ORDER BY name");
$departments = $conn->query("SELECT * FROM departments ORDER BY name");

// Get statistics
$stats_sql = "SELECT 
                COUNT(*) as total_assets,
                SUM(CASE WHEN asset_status = 'In Stock' THEN 1 ELSE 0 END) as in_stock,
                SUM(CASE WHEN asset_status = 'Assigned' THEN 1 ELSE 0 END) as assigned,
                SUM(CASE WHEN asset_status = 'Maintenance' THEN 1 ELSE 0 END) as maintenance,
                SUM(CASE WHEN asset_status = 'Retired' THEN 1 ELSE 0 END) as retired,
                SUM(CASE WHEN asset_status = 'Disposed' THEN 1 ELSE 0 END) as disposed,
                SUM(cost) as total_value,
                AVG(cost) as avg_cost,
                COUNT(DISTINCT asset_class) as asset_classes_count
              FROM assets WHERE is_active = 1";
$stats_result = $conn->query($stats_sql);
$stats = $stats_result->fetch_assoc();

// Get warranty statistics
$warranty_stats_sql = "SELECT 
                        SUM(CASE WHEN warranty_expiry >= CURDATE() THEN 1 ELSE 0 END) as active_warranty,
                        SUM(CASE WHEN warranty_expiry < CURDATE() THEN 1 ELSE 0 END) as expired_warranty,
                        SUM(CASE WHEN warranty_expiry BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 1 ELSE 0 END) as expiring_soon,
                        SUM(CASE WHEN warranty_expiry IS NULL THEN 1 ELSE 0 END) as no_warranty
                       FROM assets WHERE is_active = 1";
$warranty_result = $conn->query($warranty_stats_sql);
$warranty_stats = $warranty_result->fetch_assoc();

// Get assets by class for chart
$class_stats_sql = "SELECT ac.class_name, COUNT(a.asset_id) as count, SUM(a.cost) as total_cost
                    FROM assets a
                    JOIN asset_classes ac ON a.asset_class = ac.class_id
                    WHERE a.is_active = 1
                    GROUP BY ac.class_name
                    ORDER BY count DESC";
$class_stats = $conn->query($class_stats_sql);

// Get recent acquisitions
$recent_sql = "SELECT a.*, ac.class_name, l.name as location_name
               FROM assets a
               LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
               LEFT JOIN locations l ON a.location_id = l.location_id
               WHERE a.is_active = 1
               ORDER BY a.acquisition_date DESC
               LIMIT 10";
$recent_assets = $conn->query($recent_sql);

// Export function
function exportToCSV($conn) {
    // Get all assets for export
    $sql = "SELECT a.asset_id, a.asset_tag, a.asset_name, ac.class_name, a.model, 
                   a.manufacturer, a.serial_number, a.acquisition_date, a.warranty_expiry,
                   a.cost, a.asset_status, l.name as location_name, d.name as department_name,
                   u.full_name as assigned_to, a.remarks
            FROM assets a
            LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
            LEFT JOIN locations l ON a.location_id = l.location_id
            LEFT JOIN departments d ON a.owner_department_id = d.department_id
            LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
            WHERE a.is_active = 1
            ORDER BY a.created_at DESC";
    
    $result = $conn->query($sql);
    
    // Set headers for CSV download
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="inventory_report_' . date('Y-m-d') . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Add headers
    fputcsv($output, [
        'Asset ID', 'Asset Tag', 'Asset Name', 'Class', 'Model', 'Manufacturer',
        'Serial Number', 'Acquisition Date', 'Warranty Expiry', 'Cost (RM)',
        'Status', 'Location', 'Department', 'Assigned To', 'Remarks'
    ]);
    
    // Add data rows
    while ($row = $result->fetch_assoc()) {
        fputcsv($output, [
            $row['asset_id'],
            $row['asset_tag'],
            $row['asset_name'],
            $row['class_name'],
            $row['model'],
            $row['manufacturer'],
            $row['serial_number'],
            $row['acquisition_date'],
            $row['warranty_expiry'],
            $row['cost'],
            $row['asset_status'],
            $row['location_name'],
            $row['department_name'],
            $row['assigned_to'],
            $row['remarks']
        ]);
    }
    
    fclose($output);
    exit();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Inventory / Asset Reports - Warehouse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        .content-header {
            background: #f8f9fa;
            padding: 20px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 25px;
        }
        .status-badge {
            font-size: 0.75rem;
            padding: 0.25rem 0.5rem;
        }
        .filter-section {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 25px;
        }
        .stat-card {
            transition: transform 0.2s;
            border-left: 4px solid;
            margin-bottom: 15px;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .warranty-expiring {
            background-color: #fff3cd;
            color: #856404;
        }
        .warranty-expired {
            background-color: #f8d7da;
            color: #721c24;
        }
        .asset-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .summary-card {
            background: linear-gradient(45deg, #667eea, #764ba2);
            color: white;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'whsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <!-- Content Header -->
                <div class="content-header d-flex justify-content-between align-items-center">
                    <div>
                        <h1>Inventory / Asset Reports</h1>
                        <p>View and manage all assets in the system with detailed reporting</p>
                    </div>
                    <div class="btn-group">
                        <a href="register_asset.php" class="btn btn-info">
                            <i class="bi bi-plus-circle"></i> Register New Asset
                        </a>
                        <button type="button" class="btn btn-outline-info dropdown-toggle" data-bs-toggle="dropdown">
                            <i class="bi bi-download"></i> Export
                        </button>
                        <ul class="dropdown-menu">
                            <li><a class="dropdown-item" href="?export=csv"><i class="bi bi-file-earmark-spreadsheet"></i> CSV Export</a></li>
                            <li><a class="dropdown-item" href="#" onclick="printReport()"><i class="bi bi-printer"></i> Print Report</a></li>
                        </ul>
                    </div>
                </div>

                <!-- Summary Cards -->
                <div class="row mt-4">
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card border-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Total Assets</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['total_assets'] ?? 0); ?></h3>
                                        <small class="text-info">Active inventory</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-box-seam text-info" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card border-success">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">In Stock</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['in_stock'] ?? 0); ?></h3>
                                        <small class="text-success">Available</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle text-success" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Assigned</h6>
                                        <h3 class="mb-0"><?php echo number_format($stats['assigned'] ?? 0); ?></h3>
                                        <small class="text-primary">In use</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-person-check text-primary" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card stat-card border-warning">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Total Value</h6>
                                        <h3 class="mb-0">RM <?php echo number_format($stats['total_value'] ?? 0, 2); ?></h3>
                                        <small class="text-warning">Asset value</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-currency-dollar text-warning" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Warranty Alert Section -->
                <?php if (($warranty_stats['expiring_soon'] ?? 0) > 0 || ($warranty_stats['expired_warranty'] ?? 0) > 0): ?>
                <div class="alert alert-warning alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                        <div>
                            <strong>Warranty Alerts!</strong> 
                            <?php if (($warranty_stats['expiring_soon'] ?? 0) > 0): ?>
                                <span class="badge bg-warning me-2"><?php echo $warranty_stats['expiring_soon']; ?> assets warranty expiring soon</span>
                            <?php endif; ?>
                            <?php if (($warranty_stats['expired_warranty'] ?? 0) > 0): ?>
                                <span class="badge bg-danger"><?php echo $warranty_stats['expired_warranty']; ?> assets warranty expired</span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Registration Success Alert -->
                <?php if ($reg_success): ?>
                <div class="alert alert-success alert-dismissible fade show" role="alert">
                    <i class="bi bi-check-circle-fill me-2"></i>
                    <strong>"<?php echo $reg_success; ?>"</strong> has been registered successfully!
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Filter Section -->
                <div class="filter-section">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Filter Assets</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Asset Class</label>
                            <select name="filter_class" class="form-select">
                                <option value="">All Classes</option>
                                <?php while($class = $asset_classes->fetch_assoc()): ?>
                                    <option value="<?php echo $class['class_id']; ?>" <?php echo $filter_class == $class['class_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Status</label>
                            <select name="filter_status" class="form-select">
                                <option value="">All Status</option>
                                <option value="In Stock" <?php echo $filter_status == 'In Stock' ? 'selected' : ''; ?>>In Stock</option>
                                <option value="Assigned" <?php echo $filter_status == 'Assigned' ? 'selected' : ''; ?>>Assigned</option>
                                <option value="Maintenance" <?php echo $filter_status == 'Maintenance' ? 'selected' : ''; ?>>Maintenance</option>
                                <option value="Retired" <?php echo $filter_status == 'Retired' ? 'selected' : ''; ?>>Retired</option>
                                <option value="Disposed" <?php echo $filter_status == 'Disposed' ? 'selected' : ''; ?>>Disposed</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Location</label>
                            <select name="filter_location" class="form-select">
                                <option value="">All Locations</option>
                                <?php 
                                $locations->data_seek(0);
                                while($loc = $locations->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $loc['location_id']; ?>" <?php echo $filter_location == $loc['location_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($loc['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Department</label>
                            <select name="filter_department" class="form-select">
                                <option value="">All Departments</option>
                                <?php 
                                $departments->data_seek(0);
                                while($dept = $departments->fetch_assoc()): 
                                ?>
                                    <option value="<?php echo $dept['department_id']; ?>" <?php echo $filter_department == $dept['department_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($dept['name']); ?>
                                    </option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Acquisition From</label>
                            <input type="date" name="date_from" class="form-control" value="<?php echo $filter_date_from; ?>">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Acquisition To</label>
                            <input type="date" name="date_to" class="form-control" value="<?php echo $filter_date_to; ?>">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Warranty Status</label>
                            <select name="warranty_status" class="form-select">
                                <option value="">All</option>
                                <option value="active" <?php echo $filter_warranty == 'active' ? 'selected' : ''; ?>>Active</option>
                                <option value="expiring_soon" <?php echo $filter_warranty == 'expiring_soon' ? 'selected' : ''; ?>>Expiring Soon</option>
                                <option value="expired" <?php echo $filter_warranty == 'expired' ? 'selected' : ''; ?>>Expired</option>
                                <option value="no_warranty" <?php echo $filter_warranty == 'no_warranty' ? 'selected' : ''; ?>>No Warranty</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Search</label>
                            <input type="text" name="search" class="form-control" placeholder="Asset name, tag, model..." value="<?php echo htmlspecialchars($search); ?>">
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="bi bi-search"></i> Apply Filters
                            </button>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <a href="inventory_report.php" class="btn btn-secondary w-100">
                                <i class="bi bi-eraser"></i> Clear Filters
                            </a>
                        </div>
                    </form>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="inventoryTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" type="button" role="tab">
                            <i class="bi bi-list-ul"></i> All Assets (<?php echo $total_assets; ?>)
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="summary-tab" data-bs-toggle="tab" data-bs-target="#summary" type="button" role="tab">
                            <i class="bi bi-pie-chart"></i> Summary & Charts
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="warranty-tab" data-bs-toggle="tab" data-bs-target="#warranty" type="button" role="tab">
                            <i class="bi bi-shield-check"></i> Warranty Status
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="recent-tab" data-bs-toggle="tab" data-bs-target="#recent" type="button" role="tab">
                            <i class="bi bi-clock-history"></i> Recent Acquisitions
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- All Assets Tab -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-boxes"></i> Asset Inventory List</h5>
                                <span class="badge bg-light text-dark">Total: <?php echo $total_assets; ?> assets | Value: RM <?php echo number_format($total_value, 2); ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="assetsTable">
                                        <thead>
                                            <tr>
                                                <th>Asset ID</th>
                                                <th>Asset Info</th>
                                                <th>Class</th>
                                                <th>Status</th>
                                                <th>Location</th>
                                                <th>Acquisition</th>
                                                <th>Cost (RM)</th>
                                                <th>Warranty</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($assets) > 0): ?>
                                                <?php foreach ($assets as $asset): ?>
                                                    <tr class="<?php 
                                                        echo $asset['warranty_status'] == 'Expired' ? 'warranty-expired' : 
                                                            ($asset['warranty_status'] == 'Expiring Soon' ? 'warranty-expiring' : ''); 
                                                    ?>">
                                                        <td>
                                                            <strong><?php echo $asset['asset_id']; ?></strong>
                                                            <?php if (!empty($asset['asset_tag'])): ?>
                                                                <br><small class="text-muted">Tag: <?php echo $asset['asset_tag']; ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="d-flex align-items-center">
                                                                <?php if (!empty($asset['image_path'])): ?>
                                                                    <img src="<?php echo htmlspecialchars($asset['image_path']); ?>" class="asset-image me-2" alt="Asset">
                                                                <?php else: ?>
                                                                    <div class="asset-image bg-secondary text-white d-flex align-items-center justify-content-center me-2">
                                                                        <i class="bi bi-image"></i>
                                                                    </div>
                                                                <?php endif; ?>
                                                                <div>
                                                                    <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong>
                                                                    <br><small class="text-muted"><?php echo $asset['model'] ?? 'N/A'; ?></small>
                                                                    <?php if (!empty($asset['license_plate'])): ?>
                                                                        <br><span class="badge bg-secondary"><?php echo $asset['license_plate']; ?></span>
                                                                    <?php endif; ?>
                                                                </div>
                                                            </div>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-info"><?php echo $asset['class_name'] ?? 'N/A'; ?></span>
                                                            <br><small><?php echo $asset['manufacturer'] ?? ''; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php echo $asset['status_color']; ?>">
                                                                <?php echo $asset['asset_status']; ?>
                                                            </span>
                                                            <?php if (!empty($asset['assigned_to_name'])): ?>
                                                                <br><small><i class="bi bi-person"></i> <?php echo htmlspecialchars($asset['assigned_to_name']); ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?>
                                                            <?php if (!empty($asset['department_name'])): ?>
                                                                <br><small class="text-muted"><?php echo $asset['department_name']; ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo $asset['acquisition_date'] ? date('d/m/Y', strtotime($asset['acquisition_date'])) : 'N/A'; ?>
                                                            <br><small class="text-muted">PO: <?php echo $asset['purchase_order_number'] ?? 'N/A'; ?></small>
                                                        </td>
                                                        <td class="text-end">
                                                            <strong>RM <?php echo number_format($asset['cost'] ?? 0, 2); ?></strong>
                                                        </td>
                                                        <td>
                                                            <?php if ($asset['warranty_expiry']): ?>
                                                                <span class="badge <?php 
                                                                    echo $asset['warranty_status'] == 'Active' ? 'bg-success' : 
                                                                        ($asset['warranty_status'] == 'Expiring Soon' ? 'bg-warning' : 
                                                                        ($asset['warranty_status'] == 'Expired' ? 'bg-danger' : 'bg-secondary')); 
                                                                ?>">
                                                                    <?php echo date('d/m/Y', strtotime($asset['warranty_expiry'])); ?>
                                                                </span>
                                                                <?php if ($asset['days_until_warranty_expiry'] <= 30 && $asset['days_until_warranty_expiry'] > 0): ?>
                                                                    <br><small class="text-warning"><?php echo $asset['days_until_warranty_expiry']; ?> days left</small>
                                                                <?php elseif ($asset['days_until_warranty_expiry'] < 0): ?>
                                                                    <br><small class="text-danger">Expired</small>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                <span class="badge bg-secondary">No Warranty</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <a href="view_asset.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-info" title="View Details">
                                                                    <i class="bi bi-eye"></i>
                                                                </a>
                                                                <a href="edit_asset.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-primary" title="Edit Asset">
                                                                    <i class="bi bi-pencil"></i>
                                                                </a>
                                                                <button type="button" class="btn btn-sm btn-outline-secondary" title="View History" onclick="viewHistory('<?php echo $asset['asset_id']; ?>')">
                                                                    <i class="bi bi-clock-history"></i>
                                                                </button>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <i class="bi bi-box fs-1 d-block mb-3 text-muted"></i>
                                                        <h6 class="text-muted">No assets found matching your criteria</h6>
                                                        <a href="register_asset.php" class="btn btn-info btn-sm mt-2">
                                                            <i class="bi bi-plus-circle"></i> Register New Asset
                                                        </a>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Summary Tab -->
                    <div class="tab-pane fade" id="summary" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Assets by Class</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="assetClassChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6 mb-4">
                                <div class="card h-100">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Assets by Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-table"></i> Asset Class Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-striped">
                                                <thead>
                                                    <tr>
                                                        <th>Asset Class</th>
                                                        <th class="text-center">Count</th>
                                                        <th class="text-end">Total Value (RM)</th>
                                                        <th class="text-end">Average Value (RM)</th>
                                                        <th>% of Total</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $class_stats->data_seek(0);
                                                    while($class = $class_stats->fetch_assoc()): 
                                                        $percentage = ($stats['total_value'] > 0) ? ($class['total_cost'] / $stats['total_value']) * 100 : 0;
                                                    ?>
                                                        <tr>
                                                            <td><strong><?php echo htmlspecialchars($class['class_name']); ?></strong></td>
                                                            <td class="text-center"><?php echo number_format($class['count']); ?></td>
                                                            <td class="text-end">RM <?php echo number_format($class['total_cost'] ?? 0, 2); ?></td>
                                                            <td class="text-end">RM <?php echo number_format(($class['total_cost'] ?? 0) / $class['count'], 2); ?></td>
                                                            <td>
                                                                <div class="progress" style="height: 20px;">
                                                                    <div class="progress-bar bg-info" role="progressbar" style="width: <?php echo $percentage; ?>%;" 
                                                                         aria-valuenow="<?php echo $percentage; ?>" aria-valuemin="0" aria-valuemax="100">
                                                                        <?php echo number_format($percentage, 1); ?>%
                                                                    </div>
                                                                </div>
                                                            </td>
                                                        </tr>
                                                    <?php endwhile; ?>
                                                </tbody>
                                                <tfoot class="table-info">
                                                    <tr>
                                                        <th>Total</th>
                                                        <th class="text-center"><?php echo number_format($stats['total_assets'] ?? 0); ?></th>
                                                        <th class="text-end">RM <?php echo number_format($stats['total_value'] ?? 0, 2); ?></th>
                                                        <th class="text-end">RM <?php echo number_format($stats['avg_cost'] ?? 0, 2); ?></th>
                                                        <th>100%</th>
                                                    </tr>
                                                </tfoot>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Warranty Tab -->
                    <div class="tab-pane fade" id="warranty" role="tabpanel">
                        <div class="row">
                            <div class="col-md-4 mb-4">
                                <div class="card text-white bg-success">
                                    <div class="card-body">
                                        <h5 class="card-title">Active Warranty</h5>
                                        <h2><?php echo $warranty_stats['active_warranty'] ?? 0; ?></h2>
                                        <p class="mb-0">Assets with valid warranty</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card text-white bg-warning">
                                    <div class="card-body">
                                        <h5 class="card-title">Expiring Soon</h5>
                                        <h2><?php echo $warranty_stats['expiring_soon'] ?? 0; ?></h2>
                                        <p class="mb-0">Within next 30 days</p>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-4 mb-4">
                                <div class="card text-white bg-danger">
                                    <div class="card-body">
                                        <h5 class="card-title">Expired Warranty</h5>
                                        <h2><?php echo $warranty_stats['expired_warranty'] ?? 0; ?></h2>
                                        <p class="mb-0">Need attention</p>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-shield-exclamation"></i> Warranty Status Details</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Asset ID</th>
                                                <th>Asset Name</th>
                                                <th>Class</th>
                                                <th>Acquisition Date</th>
                                                <th>Warranty Expiry</th>
                                                <th>Days Left</th>
                                                <th>Status</th>
                                                <th>Action</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $warranty_assets = array_filter($assets, function($a) {
                                                return !empty($a['warranty_expiry']);
                                            });
                                            usort($warranty_assets, function($a, $b) {
                                                return strtotime($a['warranty_expiry']) - strtotime($b['warranty_expiry']);
                                            });
                                            ?>
                                            <?php if (count($warranty_assets) > 0): ?>
                                                <?php foreach ($warranty_assets as $asset): ?>
                                                    <tr class="<?php 
                                                        echo $asset['warranty_status'] == 'Expired' ? 'table-danger' : 
                                                            ($asset['warranty_status'] == 'Expiring Soon' ? 'table-warning' : ''); 
                                                    ?>">
                                                        <td><?php echo $asset['asset_id']; ?></td>
                                                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                                        <td><?php echo $asset['class_name'] ?? 'N/A'; ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($asset['acquisition_date'])); ?></td>
                                                        <td><strong><?php echo date('d/m/Y', strtotime($asset['warranty_expiry'])); ?></strong></td>
                                                        <td>
                                                            <?php if ($asset['days_until_warranty_expiry'] > 0): ?>
                                                                <span class="badge bg-success"><?php echo $asset['days_until_warranty_expiry']; ?> days</span>
                                                            <?php elseif ($asset['days_until_warranty_expiry'] == 0): ?>
                                                                <span class="badge bg-warning">Today</span>
                                                            <?php else: ?>
                                                                <span class="badge bg-danger"><?php echo abs($asset['days_until_warranty_expiry']); ?> days ago</span>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $asset['warranty_status'] == 'Active' ? 'success' : 
                                                                    ($asset['warranty_status'] == 'Expiring Soon' ? 'warning' : 
                                                                    ($asset['warranty_status'] == 'Expired' ? 'danger' : 'secondary')); 
                                                            ?>">
                                                                <?php echo $asset['warranty_status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <a href="edit_asset.php?id=<?php echo $asset['asset_id']; ?>" class="btn btn-sm btn-outline-primary">
                                                                Update Warranty
                                                            </a>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center py-4">
                                                        <i class="bi bi-shield fs-1 d-block mb-3 text-muted"></i>
                                                        <h6 class="text-muted">No warranty information available</h6>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Acquisitions Tab -->
                    <div class="tab-pane fade" id="recent" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-clock"></i> Recently Acquired Assets (Last 10)</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Asset ID</th>
                                                <th>Asset Name</th>
                                                <th>Class</th>
                                                <th>Location</th>
                                                <th>Cost (RM)</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if ($recent_assets && $recent_assets->num_rows > 0): ?>
                                                <?php while($asset = $recent_assets->fetch_assoc()): ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y', strtotime($asset['acquisition_date'])); ?></td>
                                                        <td><?php echo $asset['asset_id']; ?></td>
                                                        <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                                        <td><?php echo $asset['class_name'] ?? 'N/A'; ?></td>
                                                        <td><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></td>
                                                        <td class="text-end">RM <?php echo number_format($asset['cost'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <span class="badge bg-<?php 
                                                                echo $asset['asset_status'] == 'In Stock' ? 'success' : 
                                                                    ($asset['asset_status'] == 'Assigned' ? 'primary' : 
                                                                    ($asset['asset_status'] == 'Maintenance' ? 'warning' : 'secondary')); 
                                                            ?>">
                                                                <?php echo $asset['asset_status']; ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endwhile; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center py-4">
                                                        <i class="bi bi-clock fs-1 d-block mb-3 text-muted"></i>
                                                        <h6 class="text-muted">No recent acquisitions found</h6>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Asset History Modal -->
    <div class="modal fade" id="historyModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-clock-history"></i> Asset History</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body" id="historyContent">
                    Loading...
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#assetsTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']],
                language: {
                    search: "Search assets:",
                    lengthMenu: "Show _MENU_ assets per page",
                    info: "Showing _START_ to _END_ of _TOTAL_ assets"
                }
            });
        });

        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // View asset history
        function viewHistory(assetId) {
            document.getElementById('historyContent').innerHTML = 'Loading...';
            var modal = new bootstrap.Modal(document.getElementById('historyModal'));
            modal.show();
            
            // Fetch history via AJAX
            fetch('get_asset_history.php?asset_id=' + assetId)
                .then(response => response.text())
                .then(data => {
                    document.getElementById('historyContent').innerHTML = data;
                })
                .catch(error => {
                    document.getElementById('historyContent').innerHTML = 'Error loading history';
                });
        }

        // Print report
        function printReport() {
            window.print();
        }

        // Initialize Charts
        <?php 
        // Prepare data for charts
        $class_stats->data_seek(0);
        $class_labels = [];
        $class_counts = [];
        $class_colors = ['#667eea', '#764ba2', '#ff6b6b', '#4ecdc4', '#45b7d1', '#96ceb4', '#ffeaa7', '#dfe6e9'];
        while($class = $class_stats->fetch_assoc()) {
            $class_labels[] = $class['class_name'];
            $class_counts[] = $class['count'];
        }
        ?>

        // Asset Class Chart
        const classCtx = document.getElementById('assetClassChart')?.getContext('2d');
        if (classCtx) {
            new Chart(classCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode($class_labels); ?>,
                    datasets: [{
                        data: <?php echo json_encode($class_counts); ?>,
                        backgroundColor: <?php echo json_encode(array_slice($class_colors, 0, count($class_labels))); ?>,
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }

        // Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['In Stock', 'Assigned', 'Maintenance', 'Retired', 'Disposed'],
                    datasets: [{
                        data: [
                            <?php echo $stats['in_stock'] ?? 0; ?>,
                            <?php echo $stats['assigned'] ?? 0; ?>,
                            <?php echo $stats['maintenance'] ?? 0; ?>,
                            <?php echo $stats['retired'] ?? 0; ?>,
                            <?php echo $stats['disposed'] ?? 0; ?>
                        ],
                        backgroundColor: ['#28a745', '#007bff', '#ffc107', '#6c757d', '#dc3545'],
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: {
                            position: 'bottom'
                        }
                    }
                }
            });
        }
    </script>
</body>
</html>
<?php $conn->close(); ?>
<?php
// maintenance.php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation', 'operation_manager']);

require_once '../../includes/db.php';

$conn = $db->conn;
$user_id = $_SESSION['user_id'];

// Handle Add Maintenance Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_maintenance') {
        $maintenance_id = 'MNT' . date('Ymd') . rand(100, 999);
        
        $sql = "INSERT INTO maintenance (maintenance_id, asset_id, start_date, end_date, 
                maintenance_type, provider, cost, status, notes) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssdss", $maintenance_id, $_POST['asset_id'], $_POST['start_date'], 
                         $_POST['end_date'], $_POST['maintenance_type'], $_POST['provider'], 
                         $_POST['cost'], $_POST['status'], $_POST['notes']);
        
        if ($stmt->execute()) {
            // Update asset status to Maintenance
            $sql_update = "UPDATE assets SET asset_status = 'Maintenance' WHERE asset_id = ?";
            $stmt2 = $conn->prepare($sql_update);
            $stmt2->bind_param("s", $_POST['asset_id']);
            $stmt2->execute();
            
            // Update vehicle next service date if it's a vehicle
            if (isset($_POST['is_vehicle']) && $_POST['is_vehicle'] == 1) {
                $sql_vehicle = "UPDATE vehicle_details SET next_service_date = ? WHERE asset_id = ?";
                $stmt3 = $conn->prepare($sql_vehicle);
                $next_service = date('Y-m-d', strtotime($_POST['end_date'] . ' + 6 months'));
                $stmt3->bind_param("ss", $next_service, $_POST['asset_id']);
                $stmt3->execute();
            }
            
            $_SESSION['success_message'] = "Maintenance record added successfully!";
        } else {
            $_SESSION['error_message'] = "Error adding maintenance record";
        }
        
        header('Location: maintenance.php');
        exit();
    }
    
    // Handle Update Maintenance Status
    if ($_POST['action'] == 'update_status') {
        $sql = "UPDATE maintenance SET status = ? WHERE maintenance_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ss", $_POST['status'], $_POST['maintenance_id']);
        
        if ($stmt->execute()) {
            // If completed, update asset status back to Available
            if ($_POST['status'] == 'Completed') {
                $sql_asset = "UPDATE assets SET asset_status = 'Available' 
                             WHERE asset_id = (SELECT asset_id FROM maintenance WHERE maintenance_id = ?)";
                $stmt2 = $conn->prepare($sql_asset);
                $stmt2->bind_param("s", $_POST['maintenance_id']);
                $stmt2->execute();
            }
            
            $_SESSION['success_message'] = "Maintenance status updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating maintenance status";
        }
        
        header('Location: maintenance.php');
        exit();
    }
}

// Get all maintenance records
$maintenance_records = [];
$sql = "SELECT m.*, a.asset_name, a.asset_tag, a.asset_class, a.model, a.manufacturer,
               a.acquisition_date, a.cost as asset_cost,
               vd.license_plate, vd.vehicle_type, vd.current_mileage, vd.last_service_date, vd.next_service_date,
               l.name as location_name,
               u.full_name as created_by_name
        FROM maintenance m
        JOIN assets a ON m.asset_id = a.asset_id
        LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN users u ON a.created_by = u.user_id
        ORDER BY m.start_date DESC, m.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $maintenance_records[] = $row;
    }
}

// Get assets available for maintenance (all asset classes)
$assets = [];
$sql_assets = "SELECT a.*, 
                      vd.license_plate, vd.vehicle_type, vd.current_mileage,
                      ac.class_name
               FROM assets a
               LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
               LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
               WHERE a.is_active = 1 
               AND a.asset_status NOT IN ('Retired', 'Disposed')
               ORDER BY a.asset_name ASC";
$result_assets = $conn->query($sql_assets);
if ($result_assets) {
    while ($row = $result_assets->fetch_assoc()) {
        $assets[] = $row;
    }
}

// Get maintenance statistics
$total_maintenance = count($maintenance_records);
$scheduled_maintenance = 0;
$in_progress_maintenance = 0;
$completed_maintenance = 0;
$cancelled_maintenance = 0;
$total_cost = 0;

foreach ($maintenance_records as $record) {
    if ($record['status'] == 'Scheduled') $scheduled_maintenance++;
    if ($record['status'] == 'In Progress') $in_progress_maintenance++;
    if ($record['status'] == 'Completed') $completed_maintenance++;
    if ($record['status'] == 'Cancelled') $cancelled_maintenance++;
    $total_cost += $record['cost'] ?? 0;
}

// Get upcoming maintenance (next 30 days)
$upcoming_maintenance = [];
$sql_upcoming = "SELECT m.*, a.asset_name, a.model, 
                        vd.license_plate, vd.vehicle_type
                 FROM maintenance m
                 JOIN assets a ON m.asset_id = a.asset_id
                 LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
                 WHERE m.status IN ('Scheduled', 'In Progress')
                 AND m.start_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
                 ORDER BY m.start_date ASC";
$result_upcoming = $conn->query($sql_upcoming);
if ($result_upcoming) {
    while ($row = $result_upcoming->fetch_assoc()) {
        $upcoming_maintenance[] = $row;
    }
}

// Get overdue maintenance
$overdue_maintenance = [];
$sql_overdue = "SELECT m.*, a.asset_name, a.model, 
                       vd.license_plate, vd.vehicle_type
                FROM maintenance m
                JOIN assets a ON m.asset_id = a.asset_id
                LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
                WHERE m.status IN ('Scheduled', 'In Progress')
                AND m.start_date < CURDATE()
                ORDER BY m.start_date ASC";
$result_overdue = $conn->query($sql_overdue);
if ($result_overdue) {
    while ($row = $result_overdue->fetch_assoc()) {
        $overdue_maintenance[] = $row;
    }
}

// Get maintenance by asset class
$maintenance_by_class = [];
$sql_class = "SELECT ac.class_name, COUNT(m.maintenance_id) as count
              FROM maintenance m
              JOIN assets a ON m.asset_id = a.asset_id
              JOIN asset_classes ac ON a.asset_class = ac.class_id
              GROUP BY ac.class_name
              ORDER BY count DESC";
$result_class = $conn->query($sql_class);
if ($result_class) {
    while ($row = $result_class->fetch_assoc()) {
        $maintenance_by_class[] = $row;
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Maintenance Management - Warehouse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
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
        .maintenance-card {
            transition: transform 0.2s;
            border-left: 4px solid #17a2b8;
            margin-bottom: 15px;
        }
        .maintenance-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
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
        .timeline-item {
            padding: 15px;
            border-left: 3px solid #17a2b8;
            margin-bottom: 15px;
            background: #f8f9fa;
        }
        .overdue-item {
            border-left-color: #dc3545;
        }
        .upcoming-item {
            border-left-color: #28a745;
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
                        <h1>Maintenance Management</h1>
                        <p>Schedule and track asset maintenance, repairs, and servicing</p>
                    </div>
                    <div>
                        <button class="btn btn-info" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                            <i class="bi bi-plus-circle"></i> Schedule Maintenance
                        </button>
                        <button class="btn btn-outline-info" onclick="window.print()">
                            <i class="bi bi-printer"></i> Print Report
                        </button>
                    </div>
                </div>

                <!-- Alert Messages -->
                <?php if (isset($_SESSION['success_message'])): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle"></i> <?php echo $_SESSION['success_message']; unset($_SESSION['success_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if (isset($_SESSION['error_message'])): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle"></i> <?php echo $_SESSION['error_message']; unset($_SESSION['error_message']); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Quick Stats -->
                <div class="row mt-4">
                    <div class="col-md-3 mb-4">
                        <div class="card border-info">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Total Maintenance</h6>
                                        <h3 class="mb-0"><?php echo $total_maintenance; ?></h3>
                                        <small class="text-info">All records</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-tools text-info" style="font-size: 40px;"></i>
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
                                        <h6 class="text-muted">Scheduled</h6>
                                        <h3 class="mb-0"><?php echo $scheduled_maintenance; ?></h3>
                                        <small class="text-warning">Pending</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-calendar-check text-warning" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="col-md-3 mb-4">
                        <div class="card border-primary">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">In Progress</h6>
                                        <h3 class="mb-0"><?php echo $in_progress_maintenance; ?></h3>
                                        <small class="text-primary">Active</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-gear text-primary" style="font-size: 40px;"></i>
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
                                        <h6 class="text-muted">Completed</h6>
                                        <h3 class="mb-0"><?php echo $completed_maintenance; ?></h3>
                                        <small class="text-success">This year</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle text-success" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Alert Section for Overdue Maintenance -->
                <?php if (count($overdue_maintenance) > 0): ?>
                <div class="alert alert-danger alert-dismissible fade show" role="alert">
                    <div class="d-flex align-items-center">
                        <i class="bi bi-exclamation-triangle-fill fs-3 me-3"></i>
                        <div>
                            <strong>Overdue Maintenance!</strong> There are <?php echo count($overdue_maintenance); ?> maintenance tasks that are overdue.
                            <a href="#overdue-section" class="alert-link">View details</a>
                        </div>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Maintenance Filter Section -->
                <div class="filter-section">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Maintenance Report Filter</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Asset/Vehicle</label>
                            <select name="filter_asset" class="form-select">
                                <option value="">All Assets</option>
                                <?php foreach ($maintenance_records as $record): ?>
                                    <option value="<?php echo $record['asset_id']; ?>">
                                        <?php 
                                        $display = $record['asset_name'];
                                        if (!empty($record['license_plate'])) {
                                            $display .= ' - ' . $record['license_plate'];
                                        }
                                        echo htmlspecialchars($display);
                                        ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Status</label>
                            <select name="filter_status" class="form-select">
                                <option value="">All Status</option>
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Maintenance Type</label>
                            <select name="filter_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="Preventive">Preventive</option>
                                <option value="Corrective">Corrective</option>
                                <option value="Predictive">Predictive</option>
                                <option value="Emergency">Emergency</option>
                                <option value="Routine">Routine</option>
                                <option value="Vehicle Service">Vehicle Service</option>
                                <option value="Repair">Repair</option>
                                <option value="Inspection">Inspection</option>
                                <option value="Calibration">Calibration</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">From Date</label>
                            <input type="date" name="date_from" class="form-control">
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">To Date</label>
                            <input type="date" name="date_to" class="form-control">
                        </div>
                        <div class="col-md-1 d-flex align-items-end">
                            <button type="submit" class="btn btn-info w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div class="col-md-12 text-end mt-3">
                            <button type="button" class="btn btn-success" onclick="exportReport()">
                                <i class="bi bi-file-pdf"></i> Export PDF
                            </button>
                            <button type="button" class="btn btn-secondary" onclick="exportExcel()">
                                <i class="bi bi-file-spreadsheet"></i> Export Excel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="maintenanceTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="all-tab" data-bs-toggle="tab" data-bs-target="#all" 
                                type="button" role="tab">
                            <i class="bi bi-list-ul"></i> All Maintenance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="schedule-tab" data-bs-toggle="tab" data-bs-target="#schedule" 
                                type="button" role="tab">
                            <i class="bi bi-calendar"></i> Schedule
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" 
                                type="button" role="tab">
                            <i class="bi bi-truck"></i> Vehicle Maintenance
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="reports-tab" data-bs-toggle="tab" data-bs-target="#reports" 
                                type="button" role="tab">
                            <i class="bi bi-graph-up"></i> Reports
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- All Maintenance Tab -->
                    <div class="tab-pane fade show active" id="all" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-info text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-list-ul"></i> All Maintenance Records</h5>
                                <span class="badge bg-light text-dark">Total: <?php echo $total_maintenance; ?></span>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Maintenance ID</th>
                                                <th>Asset</th>
                                                <th>Type</th>
                                                <th>Start Date</th>
                                                <th>End Date</th>
                                                <th>Provider</th>
                                                <th>Cost</th>
                                                <th>Status</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($maintenance_records) > 0): ?>
                                                <?php foreach ($maintenance_records as $record): ?>
                                                    <tr>
                                                        <td>
                                                            <strong><?php echo $record['maintenance_id']; ?></strong>
                                                            <?php if (!empty($record['license_plate'])): ?>
                                                                <br><small class="text-muted"><?php echo $record['license_plate']; ?></small>
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php echo htmlspecialchars($record['asset_name']); ?>
                                                            <br><small class="text-muted"><?php echo $record['model'] ?? ''; ?></small>
                                                        </td>
                                                        <td>
                                                            <span class="badge bg-secondary"><?php echo $record['maintenance_type']; ?></span>
                                                        </td>
                                                        <td><?php echo date('d/m/Y', strtotime($record['start_date'])); ?></td>
                                                        <td><?php echo $record['end_date'] ? date('d/m/Y', strtotime($record['end_date'])) : '-'; ?></td>
                                                        <td><?php echo htmlspecialchars($record['provider'] ?? '-'); ?></td>
                                                        <td>
                                                            <?php if ($record['cost'] > 0): ?>
                                                                RM <?php echo number_format($record['cost'], 2); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <?php
                                                            $status_class = '';
                                                            switch($record['status']) {
                                                                case 'Scheduled':
                                                                    $status_class = 'bg-warning';
                                                                    break;
                                                                case 'In Progress':
                                                                    $status_class = 'bg-primary';
                                                                    break;
                                                                case 'Completed':
                                                                    $status_class = 'bg-success';
                                                                    break;
                                                                case 'Cancelled':
                                                                    $status_class = 'bg-secondary';
                                                                    break;
                                                                default:
                                                                    $status_class = 'bg-info';
                                                            }
                                                            ?>
                                                            <span class="badge <?php echo $status_class; ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <div class="btn-group">
                                                                <button type="button" class="btn btn-sm btn-outline-info" 
                                                                        onclick="viewMaintenance('<?php echo $record['maintenance_id']; ?>')"
                                                                        data-bs-toggle="tooltip" title="View Details">
                                                                    <i class="bi bi-eye"></i>
                                                                </button>
                                                                <?php if ($record['status'] != 'Completed' && $record['status'] != 'Cancelled'): ?>
                                                                <button type="button" class="btn btn-sm btn-outline-success" 
                                                                        onclick="updateStatus('<?php echo $record['maintenance_id']; ?>', 'Completed')"
                                                                        data-bs-toggle="tooltip" title="Mark as Completed">
                                                                    <i class="bi bi-check-lg"></i>
                                                                </button>
                                                                <?php endif; ?>
                                                            </div>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <i class="bi bi-tools fs-1 d-block mb-3 text-muted"></i>
                                                        <h6 class="text-muted">No maintenance records found</h6>
                                                        <button class="btn btn-info btn-sm mt-2" data-bs-toggle="modal" data-bs-target="#addMaintenanceModal">
                                                            <i class="bi bi-plus-circle"></i> Schedule Maintenance
                                                        </button>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Schedule Tab -->
                    <div class="tab-pane fade" id="schedule" role="tabpanel">
                        <div class="row">
                            <!-- Overdue Section -->
                            <?php if (count($overdue_maintenance) > 0): ?>
                            <div class="col-12 mb-4" id="overdue-section">
                                <div class="card border-danger">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-exclamation-triangle"></i> Overdue Maintenance</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php foreach ($overdue_maintenance as $task): ?>
                                            <div class="timeline-item overdue-item">
                                                <div class="d-flex justify-content-between align-items-start">
                                                    <div>
                                                        <h6 class="mb-1">
                                                            <?php echo htmlspecialchars($task['asset_name']); ?>
                                                            <?php if (!empty($task['license_plate'])): ?>
                                                                <small class="text-muted">(<?php echo $task['license_plate']; ?>)</small>
                                                            <?php endif; ?>
                                                        </h6>
                                                        <p class="mb-1">
                                                            <span class="badge bg-secondary"><?php echo $task['maintenance_type']; ?></span>
                                                            <span class="badge bg-warning"><?php echo $task['status']; ?></span>
                                                        </p>
                                                        <small class="text-muted">
                                                            Scheduled: <?php echo date('d/m/Y', strtotime($task['start_date'])); ?>
                                                            <?php if ($task['end_date']): ?>
                                                                | Target: <?php echo date('d/m/Y', strtotime($task['end_date'])); ?>
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <div>
                                                        <span class="text-danger">
                                                            <i class="bi bi-clock-history"></i> 
                                                            <?php 
                                                            $days = floor((time() - strtotime($task['start_date'])) / (60 * 60 * 24));
                                                            echo $days . ' days overdue';
                                                            ?>
                                                        </span>
                                                    </div>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                            <?php endif; ?>

                            <!-- Upcoming Section -->
                            <div class="col-12">
                                <div class="card border-success">
                                    <div class="card-header bg-success text-white">
                                        <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Upcoming Maintenance (Next 30 Days)</h5>
                                    </div>
                                    <div class="card-body">
                                        <?php if (count($upcoming_maintenance) > 0): ?>
                                            <?php foreach ($upcoming_maintenance as $task): ?>
                                                <div class="timeline-item upcoming-item">
                                                    <div class="d-flex justify-content-between align-items-start">
                                                        <div>
                                                            <h6 class="mb-1">
                                                                <?php echo htmlspecialchars($task['asset_name']); ?>
                                                                <?php if (!empty($task['license_plate'])): ?>
                                                                    <small class="text-muted">(<?php echo $task['license_plate']; ?>)</small>
                                                                <?php endif; ?>
                                                            </h6>
                                                            <p class="mb-1">
                                                                <span class="badge bg-secondary"><?php echo $task['maintenance_type']; ?></span>
                                                                <span class="badge bg-warning"><?php echo $task['status']; ?></span>
                                                            </p>
                                                            <small class="text-muted">
                                                                Start: <?php echo date('d/m/Y', strtotime($task['start_date'])); ?>
                                                                <?php if ($task['end_date']): ?>
                                                                    | End: <?php echo date('d/m/Y', strtotime($task['end_date'])); ?>
                                                                <?php endif; ?>
                                                            </small>
                                                        </div>
                                                        <div>
                                                            <span class="text-success">
                                                                <i class="bi bi-calendar"></i> 
                                                                <?php 
                                                                $days = floor((strtotime($task['start_date']) - time()) / (60 * 60 * 24));
                                                                echo $days . ' days left';
                                                                ?>
                                                            </span>
                                                        </div>
                                                    </div>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <p class="text-muted text-center py-4 mb-0">
                                                <i class="bi bi-calendar fs-1 d-block mb-3"></i>
                                                No upcoming maintenance scheduled
                                            </p>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Vehicle Maintenance Tab -->
                    <div class="tab-pane fade" id="vehicles" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-truck"></i> Vehicle Maintenance Records</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>License Plate</th>
                                                <th>Vehicle Model</th>
                                                <th>Maintenance Type</th>
                                                <th>Service Date</th>
                                                <th>Current Mileage</th>
                                                <th>Provider</th>
                                                <th>Cost</th>
                                                <th>Status</th>
                                                <th>Next Service</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php 
                                            $vehicle_maintenance = array_filter($maintenance_records, function($r) {
                                                return !empty($r['license_plate']);
                                            });
                                            ?>
                                            <?php if (count($vehicle_maintenance) > 0): ?>
                                                <?php foreach ($vehicle_maintenance as $record): ?>
                                                    <tr>
                                                        <td><strong><?php echo $record['license_plate']; ?></strong></td>
                                                        <td><?php echo $record['model'] ?? $record['asset_name']; ?></td>
                                                        <td><?php echo $record['maintenance_type']; ?></td>
                                                        <td><?php echo date('d/m/Y', strtotime($record['start_date'])); ?></td>
                                                        <td><?php echo number_format($record['current_mileage'] ?? 0); ?> km</td>
                                                        <td><?php echo $record['provider'] ?? '-'; ?></td>
                                                        <td>RM <?php echo number_format($record['cost'] ?? 0, 2); ?></td>
                                                        <td>
                                                            <span class="badge <?php 
                                                                echo $record['status'] == 'Completed' ? 'bg-success' : 
                                                                    ($record['status'] == 'In Progress' ? 'bg-primary' : 'bg-warning'); 
                                                            ?>">
                                                                <?php echo $record['status']; ?>
                                                            </span>
                                                        </td>
                                                        <td>
                                                            <?php if ($record['next_service_date']): ?>
                                                                <?php echo date('d/m/Y', strtotime($record['next_service_date'])); ?>
                                                            <?php else: ?>
                                                                -
                                                            <?php endif; ?>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="9" class="text-center py-4">
                                                        <i class="bi bi-truck fs-1 d-block mb-3 text-muted"></i>
                                                        <h6 class="text-muted">No vehicle maintenance records found</h6>
                                                    </td>
                                                </tr>
                                            <?php endif; ?>
                                        </tbody>
                                    </table>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Reports Tab -->
                    <div class="tab-pane fade" id="reports" role="tabpanel">
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Maintenance by Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Maintenance by Asset Class</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="assetClassChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-info text-white">
                                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Maintenance Cost Summary</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="row">
                                            <div class="col-md-4">
                                                <div class="text-center p-3">
                                                    <h6 class="text-muted">Total Maintenance Cost</h6>
                                                    <h2 class="text-info">RM <?php echo number_format($total_cost, 2); ?></h2>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center p-3">
                                                    <h6 class="text-muted">Average Cost per Task</h6>
                                                    <h2 class="text-primary">
                                                        RM <?php echo $total_maintenance > 0 ? number_format($total_cost / $total_maintenance, 2) : '0.00'; ?>
                                                    </h2>
                                                </div>
                                            </div>
                                            <div class="col-md-4">
                                                <div class="text-center p-3">
                                                    <h6 class="text-muted">Completion Rate</h6>
                                                    <h2 class="text-success">
                                                        <?php echo $total_maintenance > 0 ? round(($completed_maintenance / $total_maintenance) * 100) : 0; ?>%
                                                    </h2>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Maintenance Modal -->
    <div class="modal fade" id="addMaintenanceModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Schedule Maintenance</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_maintenance">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Select Asset/Vehicle *</label>
                                <select name="asset_id" id="maintenanceAssetSelect" class="form-select" required>
                                    <option value="">Choose asset...</option>
                                    <?php foreach ($assets as $asset): ?>
                                        <option value="<?php echo $asset['asset_id']; ?>"
                                                data-class="<?php echo $asset['class_name']; ?>"
                                                data-plate="<?php echo htmlspecialchars($asset['license_plate'] ?? ''); ?>"
                                                data-vehicle="<?php echo !empty($asset['license_plate']) ? 1 : 0; ?>">
                                            <?php 
                                            $display = $asset['asset_name'];
                                            if (!empty($asset['license_plate'])) {
                                                $display .= ' - ' . $asset['license_plate'];
                                            }
                                            if (!empty($asset['model'])) {
                                                $display .= ' (' . $asset['model'] . ')';
                                            }
                                            echo htmlspecialchars($display);
                                            ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Maintenance Type *</label>
                                <select name="maintenance_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Preventive">Preventive</option>
                                    <option value="Corrective">Corrective</option>
                                    <option value="Predictive">Predictive</option>
                                    <option value="Emergency">Emergency</option>
                                    <option value="Routine">Routine</option>
                                    <option value="Vehicle Service">Vehicle Service</option>
                                    <option value="Repair">Repair</option>
                                    <option value="Inspection">Inspection</option>
                                    <option value="Calibration">Calibration</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Start Date *</label>
                                <input type="date" name="start_date" class="form-control" required 
                                       value="<?php echo date('Y-m-d'); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">End Date</label>
                                <input type="date" name="end_date" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Service Provider</label>
                                <input type="text" name="provider" class="form-control" 
                                       placeholder="e.g., Service Center, Vendor">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost (RM)</label>
                                <input type="number" step="0.01" name="cost" class="form-control" value="0.00">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="Scheduled">Scheduled</option>
                                    <option value="In Progress">In Progress</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3" id="vehicleServiceField" style="display: none;">
                                <label class="form-label">Update Next Service Date</label>
                                <input type="checkbox" name="update_service_date" class="form-check-input ms-2" value="1">
                                <small class="text-muted d-block">Auto-calculated from end date + 6 months</small>
                                <input type="hidden" name="is_vehicle" id="isVehicle" value="0">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Notes</label>
                                <textarea name="notes" class="form-control" rows="3" 
                                          placeholder="Enter maintenance details, parts replaced, etc."></textarea>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Schedule Maintenance</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateStatusModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-arrow-repeat"></i> Update Maintenance Status</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_status">
                    <input type="hidden" name="maintenance_id" id="update_maintenance_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Status *</label>
                            <select name="status" class="form-select" required>
                                <option value="Scheduled">Scheduled</option>
                                <option value="In Progress">In Progress</option>
                                <option value="Completed">Completed</option>
                                <option value="Cancelled">Cancelled</option>
                            </select>
                        </div>
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> Setting status to "Completed" will automatically update the asset status to "Available".
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Initialize tooltips
        var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
        var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
            return new bootstrap.Tooltip(tooltipTriggerEl)
        });

        // Show/hide vehicle service field based on selected asset
        document.getElementById('maintenanceAssetSelect')?.addEventListener('change', function() {
            const selected = this.options[this.selectedIndex];
            const isVehicle = selected.dataset.vehicle == '1';
            const vehicleField = document.getElementById('vehicleServiceField');
            
            if (vehicleField) {
                vehicleField.style.display = isVehicle ? 'block' : 'none';
                document.getElementById('isVehicle').value = isVehicle ? '1' : '0';
            }
        });

        // Open update status modal
        function updateStatus(maintenanceId, status) {
            document.getElementById('update_maintenance_id').value = maintenanceId;
            const modal = new bootstrap.Modal(document.getElementById('updateStatusModal'));
            modal.show();
        }

        // View maintenance details
        function viewMaintenance(maintenanceId) {
            // You can implement this to show detailed maintenance info
            alert('Maintenance details for ID: ' + maintenanceId);
        }

        // Export functions
        function exportReport() {
            alert('Export PDF functionality - In development');
        }

        function exportExcel() {
            alert('Export Excel functionality - In development');
        }

        // Initialize Charts
        <?php if ($total_maintenance > 0): ?>
        // Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Scheduled', 'In Progress', 'Completed', 'Cancelled'],
                    datasets: [{
                        data: [
                            <?php echo $scheduled_maintenance; ?>,
                            <?php echo $in_progress_maintenance; ?>,
                            <?php echo $completed_maintenance; ?>,
                            <?php echo $cancelled_maintenance; ?>
                        ],
                        backgroundColor: ['#ffc107', '#0d6efd', '#28a745', '#6c757d']
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

        // Asset Class Chart
        const classCtx = document.getElementById('assetClassChart')?.getContext('2d');
        if (classCtx) {
            const classLabels = [];
            const classCounts = [];
            <?php foreach ($maintenance_by_class as $class): ?>
                classLabels.push('<?php echo $class['class_name']; ?>');
                classCounts.push(<?php echo $class['count']; ?>);
            <?php endforeach; ?>
            
            new Chart(classCtx, {
                type: 'bar',
                data: {
                    labels: classLabels,
                    datasets: [{
                        label: 'Number of Maintenance Tasks',
                        data: classCounts,
                        backgroundColor: '#17a2b8'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    }
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>
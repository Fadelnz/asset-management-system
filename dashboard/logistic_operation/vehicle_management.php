<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation']);

require_once '../../includes/db.php';

$conn = $db->conn;
$user_id = $_SESSION['user_id'];

// Handle Add Vehicle Form Submission
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    if ($_POST['action'] == 'add_vehicle') {
        $asset_id = 'VEH' . date('Ymd') . rand(100, 999);
        $asset_tag = 'VH-' . date('Y') . '-' . rand(1000, 9999);
        
        // Insert into assets table
        $sql_asset = "INSERT INTO assets (asset_id, asset_tag, asset_name, asset_class, model, manufacturer, 
                     serial_number, acquisition_date, cost, asset_status, location_id, created_by) 
                     VALUES (?, ?, ?, 'CLASS006', ?, ?, ?, ?, ?, ?, 'LOC001', ?)";
        
        $stmt = $conn->prepare($sql_asset);
        $vehicle_name = $_POST['model'] . ' - ' . $_POST['registration'];
        $stmt->bind_param("sssssssdss", $asset_id, $asset_tag, $vehicle_name, $_POST['model'], 
                         $_POST['manufacturer'], $_POST['serial_number'], $_POST['acquisition_date'], 
                         $_POST['cost'], $_POST['condition'], $user_id);
        
        if ($stmt->execute()) {
            // Insert into vehicle_details table
            $sql_vehicle = "INSERT INTO vehicle_details (asset_id, license_plate, vehicle_type, fuel_type, 
                          engine_capacity, chassis_number, color, current_mileage, last_service_date, next_service_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt2 = $conn->prepare($sql_vehicle);
            $next_service = date('Y-m-d', strtotime($_POST['acquisition_date'] . ' + 6 months'));
            $stmt2->bind_param("sssssssiss", $asset_id, $_POST['registration'], $_POST['vehicle_type'], 
                             $_POST['fuel_type'], $_POST['engine_capacity'], $_POST['chassis_number'], 
                             $_POST['color'], $_POST['current_mileage'], $_POST['acquisition_date'], $next_service);
            
            if ($stmt2->execute()) {
                // Handle image upload
                if (isset($_FILES['vehicle_image']) && $_FILES['vehicle_image']['error'] == 0) {
                    $target_dir = "../../uploads/vehicles/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    $image_name = $asset_id . '_' . basename($_FILES['vehicle_image']['name']);
                    $target_file = $target_dir . $image_name;
                    
                    if (move_uploaded_file($_FILES['vehicle_image']['tmp_name'], $target_file)) {
                        $sql_img = "UPDATE assets SET image_path = ? WHERE asset_id = ?";
                        $stmt3 = $conn->prepare($sql_img);
                        $stmt3->bind_param("ss", $target_file, $asset_id);
                        $stmt3->execute();
                    }
                }
                
                $_SESSION['success_message'] = "Vehicle added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding vehicle details";
            }
        } else {
            $_SESSION['error_message'] = "Error adding vehicle";
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // Handle Vehicle Movement
    if ($_POST['action'] == 'add_movement') {
        $movement_id = 'MOV' . date('YmdHis') . rand(10, 99);
        $sql = "INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, 
                from_location_id, to_location_id, remarks, status) 
                VALUES (?, ?, 'Trip', ?, 'LOC001', 'LOC002', ?, 'Completed')";
        
        $stmt = $conn->prepare($sql);
        $remarks = "Destination: " . $_POST['destination'] . " | Purpose: " . $_POST['purpose'] . 
                  " | Current Location: " . $_POST['current_location'];
        $stmt->bind_param("ssss", $movement_id, $_POST['asset_id'], $user_id, $remarks);
        
        if ($stmt->execute()) {
            // Update vehicle location and status
            $sql_update = "UPDATE assets SET location_id = 'LOC002', asset_status = 'In Use' WHERE asset_id = ?";
            $stmt2 = $conn->prepare($sql_update);
            $stmt2->bind_param("s", $_POST['asset_id']);
            $stmt2->execute();
            
            $_SESSION['success_message'] = "Vehicle movement recorded successfully!";
        } else {
            $_SESSION['error_message'] = "Error recording movement";
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
}

// Get all vehicles
$vehicles = [];
$sql = "SELECT a.*, vd.license_plate, vd.vehicle_type, vd.fuel_type, vd.engine_capacity, 
        vd.chassis_number, vd.color, vd.last_service_date, vd.next_service_date, vd.current_mileage,
        l.name as location_name
        FROM assets a 
        LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        WHERE a.asset_class = 'CLASS006' 
        ORDER BY a.created_at DESC";
$result = $conn->query($sql);
if ($result) {
    while ($row = $result->fetch_assoc()) {
        $vehicles[] = $row;
    }
}

// Get vehicle movements
$movements = [];
$sql_movements = "SELECT am.*, a.asset_name, a.asset_tag, vd.license_plate, u.full_name as performed_by
                 FROM asset_movements am
                 JOIN assets a ON am.asset_id = a.asset_id
                 LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
                 LEFT JOIN users u ON am.performed_by_user_id = u.user_id
                 WHERE a.asset_class = 'CLASS006'
                 ORDER BY am.movement_date DESC
                 LIMIT 50";
$result_movements = $conn->query($sql_movements);
if ($result_movements) {
    while ($row = $result_movements->fetch_assoc()) {
        $movements[] = $row;
    }
}

// Get counts for stats
$total_vehicles = count($vehicles);
$available_vehicles = 0;
$in_use_vehicles = 0;
$maintenance_vehicles = 0;

foreach ($vehicles as $vehicle) {
    if ($vehicle['asset_status'] == 'Available') $available_vehicles++;
    if ($vehicle['asset_status'] == 'In Use') $in_use_vehicles++;
    if ($vehicle['asset_status'] == 'Maintenance') $maintenance_vehicles++;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Management - Warehouse Dashboard</title>
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
        .vehicle-card {
            transition: transform 0.2s;
            border-left: 4px solid #dc3545;
            margin-bottom: 15px;
        }
        .vehicle-card:hover {
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
                        <h1>Vehicle Management</h1>
                        <p>Manage fleet vehicles, track movements, and generate reports</p>
                    </div>
                    <div>
                        <button class="btn btn-danger" data-bs-toggle="modal" data-bs-target="#addVehicleModal">
                            <i class="bi bi-plus-circle"></i> Add New Vehicle
                        </button>
                        <button class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#movementModal">
                            <i class="bi bi-arrow-left-right"></i> Record Movement
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
                        <div class="card border-danger">
                            <div class="card-body">
                                <div class="d-flex justify-content-between">
                                    <div>
                                        <h6 class="text-muted">Total Vehicles</h6>
                                        <h3 class="mb-0"><?php echo $total_vehicles; ?></h3>
                                        <small class="text-success">Active fleet</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-truck text-danger" style="font-size: 40px;"></i>
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
                                        <h6 class="text-muted">In Use</h6>
                                        <h3 class="mb-0"><?php echo $in_use_vehicles; ?></h3>
                                        <small class="text-warning">Currently on trip</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-play-circle text-warning" style="font-size: 40px;"></i>
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
                                        <h6 class="text-muted">Available</h6>
                                        <h3 class="mb-0"><?php echo $available_vehicles; ?></h3>
                                        <small class="text-info">Ready to use</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-check-circle text-info" style="font-size: 40px;"></i>
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
                                        <h6 class="text-muted">Maintenance</h6>
                                        <h3 class="mb-0"><?php echo $maintenance_vehicles; ?></h3>
                                        <small class="text-success">In service</small>
                                    </div>
                                    <div class="align-self-center">
                                        <i class="bi bi-tools text-success" style="font-size: 40px;"></i>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Vehicle Report Filter -->
                <div class="filter-section">
                    <h5 class="mb-3"><i class="bi bi-funnel"></i> Vehicle Report Filter</h5>
                    <form method="GET" class="row g-3">
                        <div class="col-md-3">
                            <label class="form-label">Vehicle</label>
                            <select name="filter_vehicle" class="form-select">
                                <option value="">All Vehicles</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <option value="<?php echo $vehicle['asset_id']; ?>">
                                        <?php echo htmlspecialchars($vehicle['license_plate'] . ' - ' . ($vehicle['model'] ?? 'N/A')); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Condition/Status</label>
                            <select name="filter_status" class="form-select">
                                <option value="">All Status</option>
                                <option value="Available">Available</option>
                                <option value="In Use">In Use</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Retired">Retired</option>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label class="form-label">Vehicle Type</label>
                            <select name="filter_type" class="form-select">
                                <option value="">All Types</option>
                                <option value="Sedan">Sedan</option>
                                <option value="SUV">SUV</option>
                                <option value="MPV">MPV</option>
                                <option value="Van">Van</option>
                                <option value="Truck">Truck</option>
                                <option value="Bus">Bus</option>
                                <option value="Motorcycle">Motorcycle</option>
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
                            <button type="submit" class="btn btn-danger w-100">
                                <i class="bi bi-search"></i>
                            </button>
                        </div>
                        <div class="col-md-12 text-end mt-3">
                            <button type="button" class="btn btn-success" onclick="window.print()">
                                <i class="bi bi-file-pdf"></i> Export Report
                            </button>
                            <button type="button" class="btn btn-secondary">
                                <i class="bi bi-file-spreadsheet"></i> Export Excel
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Tabs -->
                <ul class="nav nav-tabs mb-4" id="vehicleTabs" role="tablist">
                    <li class="nav-item" role="presentation">
                        <button class="nav-link active" id="vehicles-tab" data-bs-toggle="tab" data-bs-target="#vehicles" 
                                type="button" role="tab">
                            <i class="bi bi-truck"></i> Vehicles List
                        </button>
                    </li>
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="movements-tab" data-bs-toggle="tab" data-bs-target="#movements" 
                                type="button" role="tab">
                            <i class="bi bi-arrow-left-right"></i> Vehicle Movements
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
                    <!-- Vehicles List Tab -->
                    <div class="tab-pane fade show active" id="vehicles" role="tabpanel">
                        <div class="row">
                            <?php if (count($vehicles) > 0): ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="col-md-6 col-lg-4">
                                        <div class="card vehicle-card">
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge <?php 
                                                        echo $vehicle['asset_status'] == 'Available' ? 'bg-success' : 
                                                            ($vehicle['asset_status'] == 'In Use' ? 'bg-warning' : 
                                                            ($vehicle['asset_status'] == 'Maintenance' ? 'bg-info' : 'bg-secondary')); 
                                                    ?> status-badge">
                                                        <?php echo $vehicle['asset_status'] ?? 'Unknown'; ?>
                                                    </span>
                                                </div>
                                                
                                                <?php if (!empty($vehicle['image_path'])): ?>
                                                    <div class="text-center mb-3">
                                                        <img src="<?php echo $vehicle['image_path']; ?>" alt="Vehicle" style="max-height: 100px; object-fit: contain;">
                                                    </div>
                                                <?php endif; ?>
                                                
                                                <div class="row mt-2">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Type</small>
                                                        <strong><?php echo $vehicle['vehicle_type'] ?? 'N/A'; ?></strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Fuel</small>
                                                        <strong><?php echo $vehicle['fuel_type'] ?? 'N/A'; ?></strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Year</small>
                                                        <strong><?php echo $vehicle['acquisition_date'] ? date('Y', strtotime($vehicle['acquisition_date'])) : 'N/A'; ?></strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Mileage</small>
                                                        <strong><?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km</strong>
                                                    </div>
                                                    <div class="col-12 mt-2">
                                                        <small class="text-muted d-block">Purchase Value</small>
                                                        <strong>RM <?php echo number_format($vehicle['cost'] ?? 0, 2); ?></strong>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-3 d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-danger" onclick="viewVehicleDetails('<?php echo $vehicle['asset_id']; ?>')">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="openMovementModal('<?php echo $vehicle['asset_id']; ?>', '<?php echo htmlspecialchars($vehicle['license_plate'] ?? ''); ?>')"
                                                            <?php echo $vehicle['asset_status'] != 'Available' ? 'disabled' : ''; ?>>
                                                        <i class="bi bi-arrow-right"></i> Move
                                                    </button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <div class="col-12">
                                    <div class="alert alert-info">
                                        <i class="bi bi-info-circle"></i> No vehicles found. Click "Add New Vehicle" to get started.
                                    </div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Vehicle Movements Tab -->
                    <div class="tab-pane fade" id="movements" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-danger text-white d-flex justify-content-between align-items-center">
                                <h5 class="mb-0"><i class="bi bi-arrow-left-right"></i> Vehicle Movement History</h5>
                                <button class="btn btn-light btn-sm" data-bs-toggle="modal" data-bs-target="#movementModal">
                                    <i class="bi bi-plus-circle"></i> New Movement
                                </button>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Vehicle</th>
                                                <th>License Plate</th>
                                                <th>Destination</th>
                                                <th>Purpose</th>
                                                <th>Performed By</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($movements) > 0): ?>
                                                <?php foreach ($movements as $movement): ?>
                                                    <?php 
                                                    $remarks = explode(' | ', $movement['remarks'] ?? '');
                                                    $destination = str_replace('Destination: ', '', $remarks[0] ?? '');
                                                    $purpose = str_replace('Purpose: ', '', $remarks[1] ?? '');
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['movement_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['asset_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['license_plate'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($destination); ?></td>
                                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($purpose); ?></span></td>
                                                        <td><?php echo htmlspecialchars($movement['performed_by'] ?? 'N/A'); ?></td>
                                                        <td><span class="badge bg-success"><?php echo $movement['status'] ?? 'Completed'; ?></span></td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="7" class="text-center">No movement records found.</td>
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
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-pie-chart"></i> Vehicles by Status</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="statusChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-bar-chart"></i> Vehicles by Type</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="typeChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-12">
                                <div class="card">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-graph-up"></i> Monthly Movements</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="movementChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Add Vehicle Modal -->
    <div class="modal fade" id="addVehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-plus-circle"></i> Add New Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="add_vehicle">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" name="manufacturer" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vehicle Type *</label>
                                <select name="vehicle_type" class="form-select" required>
                                    <option value="">Select Type</option>
                                    <option value="Sedan">Sedan</option>
                                    <option value="SUV">SUV</option>
                                    <option value="MPV">MPV</option>
                                    <option value="Van">Van</option>
                                    <option value="Truck">Truck</option>
                                    <option value="Bus">Bus</option>
                                    <option value="Motorcycle">Motorcycle</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Condition *</label>
                                <select name="condition" class="form-select" required>
                                    <option value="Available">Available</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration/License Plate *</label>
                                <input type="text" name="registration" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Year *</label>
                                <input type="date" name="acquisition_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Purchase Value (RM) *</label>
                                <input type="number" step="0.01" name="cost" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chassis Number</label>
                                <input type="text" name="chassis_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fuel Type</label>
                                <select name="fuel_type" class="form-select">
                                    <option value="Petrol">Petrol</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Electric">Electric</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Engine Capacity</label>
                                <input type="text" name="engine_capacity" class="form-control" placeholder="e.g., 2000cc">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Mileage</label>
                                <input type="number" name="current_mileage" class="form-control" value="0">
                            </div>
                            <div class="col-12 mb-3">
                                <label class="form-label">Vehicle Image</label>
                                <input type="file" name="vehicle_image" class="form-control" accept="image/*">
                                <small class="text-muted">Accepted formats: JPG, PNG, GIF</small>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Add Vehicle</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Vehicle Movement Modal -->
    <div class="modal fade" id="movementModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-warning text-dark">
                    <h5 class="modal-title"><i class="bi bi-arrow-left-right"></i> Record Vehicle Movement</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="add_movement">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Select Vehicle *</label>
                            <select name="asset_id" id="movementVehicleSelect" class="form-select" required>
                                <option value="">Choose vehicle...</option>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <?php if ($vehicle['asset_status'] == 'Available'): ?>
                                        <option value="<?php echo $vehicle['asset_id']; ?>" 
                                                data-plate="<?php echo htmlspecialchars($vehicle['license_plate'] ?? ''); ?>"
                                                data-model="<?php echo htmlspecialchars($vehicle['model'] ?? ''); ?>"
                                                data-type="<?php echo htmlspecialchars($vehicle['vehicle_type'] ?? ''); ?>"
                                                data-mileage="<?php echo $vehicle['current_mileage'] ?? 0; ?>">
                                            <?php echo htmlspecialchars(($vehicle['license_plate'] ?? 'N/A') . ' - ' . ($vehicle['model'] ?? 'N/A')); ?>
                                        </option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div id="vehicleDetails" class="mb-3 p-3 bg-light rounded" style="display: none;">
                            <h6 class="mb-2">Vehicle Details</h6>
                            <div class="row">
                                <div class="col-6">
                                    <small class="text-muted">License Plate</small>
                                    <p id="detailPlate" class="fw-bold mb-1"></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Model</small>
                                    <p id="detailModel" class="fw-bold mb-1"></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Type</small>
                                    <p id="detailType" class="mb-1"></p>
                                </div>
                                <div class="col-6">
                                    <small class="text-muted">Mileage</small>
                                    <p id="detailMileage" class="mb-1"></p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Destination *</label>
                            <input type="text" name="destination" class="form-control" required 
                                   placeholder="e.g., Kuala Lumpur">
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Purpose *</label>
                            <select name="purpose" class="form-select" required>
                                <option value="">Select Purpose</option>
                                <option value="Delivery">Delivery</option>
                                <option value="Pickup">Pickup</option>
                                <option value="Maintenance">Maintenance</option>
                                <option value="Meeting">Meeting</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">Current Location *</label>
                            <input type="text" name="current_location" class="form-control" required 
                                   placeholder="Current position or location">
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-warning">Record Movement</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script>
        // Update vehicle details when selection changes
        document.getElementById('movementVehicleSelect')?.addEventListener('change', function() {
            const select = this;
            const selected = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('vehicleDetails');
            
            if (select.value) {
                document.getElementById('detailPlate').textContent = selected.dataset.plate || 'N/A';
                document.getElementById('detailModel').textContent = selected.dataset.model || 'N/A';
                document.getElementById('detailType').textContent = selected.dataset.type || 'N/A';
                document.getElementById('detailMileage').textContent = selected.dataset.mileage + ' km';
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        });

        function openMovementModal(assetId, licensePlate) {
            const modal = new bootstrap.Modal(document.getElementById('movementModal'));
            const select = document.getElementById('movementVehicleSelect');
            select.value = assetId;
            select.dispatchEvent(new Event('change'));
            modal.show();
        }

        function viewVehicleDetails(assetId) {
            // You can implement this to show detailed vehicle info
            alert('Vehicle details view for ID: ' + assetId);
        }

        // Initialize charts
        <?php if ($total_vehicles > 0): ?>
        // Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available', 'In Use', 'Maintenance', 'Retired'],
                    datasets: [{
                        data: [
                            <?php echo $available_vehicles; ?>,
                            <?php echo $in_use_vehicles; ?>,
                            <?php echo $maintenance_vehicles; ?>,
                            <?php echo $total_vehicles - ($available_vehicles + $in_use_vehicles + $maintenance_vehicles); ?>
                        ],
                        backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#6c757d']
                    }]
                }
            });
        }

        // Type Chart
        const typeData = {};
        <?php foreach ($vehicles as $vehicle): ?>
            <?php $type = $vehicle['vehicle_type'] ?? 'Others'; ?>
            typeData['<?php echo $type; ?>'] = (typeData['<?php echo $type; ?>'] || 0) + 1;
        <?php endforeach; ?>
        
        const typeCtx = document.getElementById('typeChart')?.getContext('2d');
        if (typeCtx) {
            new Chart(typeCtx, {
                type: 'bar',
                data: {
                    labels: Object.keys(typeData),
                    datasets: [{
                        label: 'Number of Vehicles',
                        data: Object.values(typeData),
                        backgroundColor: '#dc3545'
                    }]
                },
                options: {
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

        <?php if (count($movements) > 0): ?>
        // Movement Chart
        const movementData = {};
        <?php foreach ($movements as $movement): ?>
            const date = '<?php echo date('M Y', strtotime($movement['movement_date'])); ?>';
            movementData[date] = (movementData[date] || 0) + 1;
        <?php endforeach; ?>
        
        const movementCtx = document.getElementById('movementChart')?.getContext('2d');
        if (movementCtx) {
            new Chart(movementCtx, {
                type: 'line',
                data: {
                    labels: Object.keys(movementData).slice(0, 6),
                    datasets: [{
                        label: 'Vehicle Movements',
                        data: Object.values(movementData).slice(0, 6),
                        borderColor: '#dc3545',
                        backgroundColor: 'rgba(220, 53, 69, 0.1)',
                        tension: 0.1,
                        fill: true
                    }]
                }
            });
        }
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>
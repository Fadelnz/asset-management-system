<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation']);

require_once '../../includes/db.php';

$conn = $db->conn;
$user_id = $_SESSION['user_id'];

// Handle POST Requests (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {
    
    // CREATE - Add New Vehicle
    if ($_POST['action'] == 'add_vehicle') {
        $asset_id = 'VEH' . date('Ymd') . rand(100, 999);
        $asset_tag = 'VH-' . date('Y') . '-' . rand(1000, 9999);
        
        $model = $_POST['model'] ?? '';
        $manufacturer = $_POST['manufacturer'] ?? '';
        $serial_number = $_POST['serial_number'] ?? '';
        $acquisition_date = $_POST['acquisition_date'] ?? '';
        $status = $_POST['condition'] ?? 'Available';
        $location_id = $_POST['location_id'] ?? 'LOC001';
        $asset_class = 'CLASS006';
        
        // Insert into assets table
        $sql_asset = "INSERT INTO assets (asset_id, asset_tag, asset_name, asset_class, model, manufacturer, 
                     serial_number, acquisition_date, asset_status, location_id) 
                     VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql_asset);
        $vehicle_name = $model . ' - ' . ($_POST['registration'] ?? '');
        
        $stmt->bind_param("ssssssssss", 
            $asset_id, 
            $asset_tag, 
            $vehicle_name, 
            $asset_class,
            $model, 
            $manufacturer, 
            $serial_number, 
            $acquisition_date, 
            $status,
            $location_id
        );
        
        if ($stmt->execute()) {
            $registration = $_POST['registration'] ?? '';
            $vehicle_type = $_POST['vehicle_type'] ?? '';
            $fuel_type = $_POST['fuel_type'] ?? 'Petrol';
            $engine_capacity = $_POST['engine_capacity'] ?? '';
            $chassis_number = $_POST['chassis_number'] ?? '';
            $color = $_POST['color'] ?? '';
            $current_mileage = (int)($_POST['current_mileage'] ?? 0);
            $last_service_date = $acquisition_date;
            $next_service = date('Y-m-d', strtotime($acquisition_date . ' + 6 months'));
            
            // Insert into vehicle_details table
            $sql_vehicle = "INSERT INTO vehicle_details (asset_id, license_plate, vehicle_type, fuel_type, 
                          engine_capacity, chassis_number, color, current_mileage, last_service_date, next_service_date) 
                          VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
            
            $stmt2 = $conn->prepare($sql_vehicle);
            $stmt2->bind_param("sssssssiss", 
                $asset_id, 
                $registration, 
                $vehicle_type, 
                $fuel_type, 
                $engine_capacity, 
                $chassis_number, 
                $color, 
                $current_mileage,
                $last_service_date, 
                $next_service
            );
            
            if ($stmt2->execute()) {
                // Handle image upload
                if (isset($_FILES['vehicle_images']) && !empty($_FILES['vehicle_images']['name'][0])) {
                    $target_dir = "../../uploads/vehicles/";
                    if (!file_exists($target_dir)) {
                        mkdir($target_dir, 0777, true);
                    }
                    
                    $files = $_FILES['vehicle_images'];
                    $file_count = count($files['name']);
                    $is_primary = true;
                    
                    for ($i = 0; $i < $file_count; $i++) {
                        if ($files['error'][$i] == 0) {
                            $file_extension = pathinfo($files['name'][$i], PATHINFO_EXTENSION);
                            $image_name = $asset_id . '_' . uniqid() . '.' . $file_extension;
                            $target_file = $target_dir . $image_name;
                            
                            $allowed_types = ['jpg', 'jpeg', 'png', 'gif'];
                            if (in_array(strtolower($file_extension), $allowed_types)) {
                                if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
                                    $sql_img = "INSERT INTO vehicle_images (asset_id, image_path, is_primary) VALUES (?, ?, ?)";
                                    $stmt3 = $conn->prepare($sql_img);
                                    $primary_flag = $is_primary ? 1 : 0;
                                    $stmt3->bind_param("ssi", $asset_id, $target_file, $primary_flag);
                                    $stmt3->execute();
                                    
                                    $is_primary = false;
                                }
                            }
                        }
                    }
                }
                
                $_SESSION['success_message'] = "Vehicle added successfully!";
            } else {
                $_SESSION['error_message'] = "Error adding vehicle details: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Error adding vehicle: " . $conn->error;
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // UPDATE - Edit Vehicle
    if ($_POST['action'] == 'edit_vehicle') {
        $asset_id = $_POST['asset_id'];
        
        // Update assets table
        $sql_asset = "UPDATE assets SET 
                     model = ?, 
                     manufacturer = ?, 
                     serial_number = ?, 
                     acquisition_date = ?, 
                     asset_status = ?,
                     location_id = ?
                     WHERE asset_id = ?";
        
        $stmt = $conn->prepare($sql_asset);
        $stmt->bind_param("sssssss", 
            $_POST['model'],
            $_POST['manufacturer'],
            $_POST['serial_number'],
            $_POST['acquisition_date'],
            $_POST['condition'],
            $_POST['location_id'],
            $asset_id
        );
        
        if ($stmt->execute()) {
            // Update vehicle_details table
            $sql_vehicle = "UPDATE vehicle_details SET 
                          license_plate = ?,
                          vehicle_type = ?,
                          fuel_type = ?,
                          engine_capacity = ?,
                          chassis_number = ?,
                          color = ?,
                          current_mileage = ?
                          WHERE asset_id = ?";
            
            $stmt2 = $conn->prepare($sql_vehicle);
            $stmt2->bind_param("ssssssis", 
                $_POST['registration'],
                $_POST['vehicle_type'],
                $_POST['fuel_type'],
                $_POST['engine_capacity'],
                $_POST['chassis_number'],
                $_POST['color'],
                $_POST['current_mileage'],
                $asset_id
            );
            
            if ($stmt2->execute()) {
                $_SESSION['success_message'] = "Vehicle updated successfully!";
            } else {
                $_SESSION['error_message'] = "Error updating vehicle details: " . $conn->error;
            }
        } else {
            $_SESSION['error_message'] = "Error updating vehicle: " . $conn->error;
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // DELETE - Delete Vehicle
    if ($_POST['action'] == 'delete_vehicle') {
        $asset_id = $_POST['asset_id'];
        
        // Start transaction
        $conn->begin_transaction();
        
        try {
            // Delete vehicle images first
            $sql_images = "SELECT image_path FROM vehicle_images WHERE asset_id = ?";
            $stmt = $conn->prepare($sql_images);
            $stmt->bind_param("s", $asset_id);
            $stmt->execute();
            $result = $stmt->get_result();
            
            while ($row = $result->fetch_assoc()) {
                if (file_exists($row['image_path'])) {
                    unlink($row['image_path']);
                }
            }
            
            // Delete from vehicle_images
            $sql_del_images = "DELETE FROM vehicle_images WHERE asset_id = ?";
            $stmt = $conn->prepare($sql_del_images);
            $stmt->bind_param("s", $asset_id);
            $stmt->execute();
            
            // Delete from vehicle_details
            $sql_del_details = "DELETE FROM vehicle_details WHERE asset_id = ?";
            $stmt = $conn->prepare($sql_del_details);
            $stmt->bind_param("s", $asset_id);
            $stmt->execute();
            
            // Delete from asset_movements
            $sql_del_movements = "DELETE FROM asset_movements WHERE asset_id = ?";
            $stmt = $conn->prepare($sql_del_movements);
            $stmt->bind_param("s", $asset_id);
            $stmt->execute();
            
            // Delete from assets
            $sql_del_asset = "DELETE FROM assets WHERE asset_id = ?";
            $stmt = $conn->prepare($sql_del_asset);
            $stmt->bind_param("s", $asset_id);
            $stmt->execute();
            
            $conn->commit();
            $_SESSION['success_message'] = "Vehicle deleted successfully!";
            
        } catch (Exception $e) {
            $conn->rollback();
            $_SESSION['error_message'] = "Error deleting vehicle: " . $e->getMessage();
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // UPDATE - Update Service Record
    if ($_POST['action'] == 'update_service') {
        $asset_id = $_POST['asset_id'];
        $last_service_date = $_POST['last_service_date'];
        $next_service_date = $_POST['next_service_date'];
        $service_notes = $_POST['service_notes'];
        
        $sql = "UPDATE vehicle_details SET 
                last_service_date = ?,
                next_service_date = ?
                WHERE asset_id = ?";
        
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sss", $last_service_date, $next_service_date, $asset_id);
        
        if ($stmt->execute()) {
            // Log service in movements
            $movement_id = 'SVC' . date('YmdHis') . rand(10, 99);
            $sql_movement = "INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, 
                           remarks, status) VALUES (?, ?, 'Maintenance', ?, ?, 'Completed')";
            
            $stmt2 = $conn->prepare($sql_movement);
            $remarks = "Service performed on " . $last_service_date . ". Next service: " . $next_service_date . ". Notes: " . $service_notes;
            $stmt2->bind_param("ssss", $movement_id, $asset_id, $user_id, $remarks);
            $stmt2->execute();
            
            $_SESSION['success_message'] = "Service record updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating service record: " . $conn->error;
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // CREATE - Add Vehicle Movement
    if ($_POST['action'] == 'add_movement') {
        $movement_id = 'MOV' . date('YmdHis') . rand(10, 99);
        
        $asset_id = $_POST['asset_id'] ?? '';
        $destination = $_POST['destination'] ?? '';
        $purpose = $_POST['purpose'] ?? '';
        $current_location = $_POST['current_location'] ?? '';
        
        $sql = "INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, 
                from_location_id, to_location_id, remarks, status) 
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
        
        $stmt = $conn->prepare($sql);
        $movement_type = 'Trip';
        $from_location = $_POST['from_location'] ?? 'LOC001';
        $to_location = $_POST['to_location'] ?? 'LOC002';
        $status = 'Completed';
        $remarks = "Destination: " . $destination . " | Purpose: " . $purpose . 
                  " | Current Location: " . $current_location;
        
        $stmt->bind_param("ssssssss", 
            $movement_id, 
            $asset_id, 
            $movement_type, 
            $user_id, 
            $from_location, 
            $to_location, 
            $remarks, 
            $status
        );
        
        if ($stmt->execute()) {
            $sql_update = "UPDATE assets SET location_id = ?, asset_status = 'In Use' WHERE asset_id = ?";
            $stmt2 = $conn->prepare($sql_update);
            $new_location = $_POST['to_location'] ?? 'LOC002';
            $stmt2->bind_param("ss", $new_location, $asset_id);
            $stmt2->execute();
            
            $_SESSION['success_message'] = "Vehicle movement recorded successfully!";
        } else {
            $_SESSION['error_message'] = "Error recording movement: " . $conn->error;
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
    
    // UPDATE - Update Mileage
    if ($_POST['action'] == 'update_mileage') {
        $asset_id = $_POST['asset_id'];
        $new_mileage = $_POST['new_mileage'];
        
        $sql = "UPDATE vehicle_details SET current_mileage = ? WHERE asset_id = ?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("is", $new_mileage, $asset_id);
        
        if ($stmt->execute()) {
            $_SESSION['success_message'] = "Mileage updated successfully!";
        } else {
            $_SESSION['error_message'] = "Error updating mileage: " . $conn->error;
        }
        
        header('Location: vehicle_management.php');
        exit();
    }
}

// READ - Get all vehicles with details
$vehicles = [];
$sql = "SELECT a.*, vd.license_plate, vd.vehicle_type, vd.fuel_type, vd.engine_capacity, 
        vd.chassis_number, vd.color, vd.last_service_date, vd.next_service_date, vd.current_mileage,
        l.name as location_name, l.location_id,
        (SELECT image_path FROM vehicle_images WHERE asset_id = a.asset_id AND is_primary = 1 LIMIT 1) as primary_image
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

// READ - Get single vehicle for editing
$edit_vehicle = null;
if (isset($_GET['edit_id'])) {
    $edit_id = $_GET['edit_id'];
    $sql_edit = "SELECT a.*, vd.* FROM assets a 
                LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
                WHERE a.asset_id = ?";
    $stmt = $conn->prepare($sql_edit);
    $stmt->bind_param("s", $edit_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $edit_vehicle = $result->fetch_assoc();
}

// READ - Get vehicle movements
$movements = [];
$sql_movements = "SELECT am.*, a.asset_name, a.asset_tag, vd.license_plate, 
                 u.full_name as performed_by,
                 fl.name as from_location_name,
                 tl.name as to_location_name
                 FROM asset_movements am
                 JOIN assets a ON am.asset_id = a.asset_id
                 LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
                 LEFT JOIN users u ON am.performed_by_user_id = u.user_id
                 LEFT JOIN locations fl ON am.from_location_id = fl.location_id
                 LEFT JOIN locations tl ON am.to_location_id = tl.location_id
                 WHERE a.asset_class = 'CLASS006'
                 ORDER BY am.movement_date DESC
                 LIMIT 50";

$result_movements = $conn->query($sql_movements);
if ($result_movements) {
    while ($row = $result_movements->fetch_assoc()) {
        $movements[] = $row;
    }
}

// READ - Get locations for dropdowns
$locations = [];
$sql_locations = "SELECT location_id, name FROM locations ORDER BY name";
$result_locations = $conn->query($sql_locations);
if ($result_locations) {
    while ($row = $result_locations->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Calculate stats
$total_vehicles = count($vehicles);
$available_vehicles = 0;
$in_use_vehicles = 0;
$maintenance_vehicles = 0;
$retired_vehicles = 0;

foreach ($vehicles as $vehicle) {
    $status = $vehicle['asset_status'] ?? '';
    if ($status == 'Available' || $status == 'In Stock') $available_vehicles++;
    elseif ($status == 'In Use') $in_use_vehicles++;
    elseif ($status == 'Maintenance') $maintenance_vehicles++;
    elseif ($status == 'Retired') $retired_vehicles++;
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
        .vehicle-card {
            transition: transform 0.2s;
            border-left: 4px solid #dc3545;
            margin-bottom: 15px;
            height: 100%;
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
        .vehicle-image-container {
            height: 150px;
            overflow: hidden;
            border-radius: 8px;
            margin-bottom: 10px;
            background: #f8f9fa;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .vehicle-image {
            max-width: 100%;
            max-height: 150px;
            object-fit: contain;
        }
        .image-placeholder {
            width: 100%;
            height: 150px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 3rem;
            border-radius: 8px;
        }
        .upload-area {
            border: 2px dashed #dee2e6;
            border-radius: 8px;
            padding: 20px;
            text-align: center;
            background: #f8f9fa;
            cursor: pointer;
            transition: all 0.3s;
        }
        .upload-area:hover {
            border-color: #dc3545;
            background: #fff;
        }
        .upload-area i {
            font-size: 2rem;
            color: #6c757d;
        }
        .image-preview {
            display: flex;
            flex-wrap: wrap;
            gap: 10px;
            margin-top: 15px;
        }
        .preview-item {
            position: relative;
            width: 100px;
            height: 100px;
            border-radius: 5px;
            overflow: hidden;
            border: 2px solid #dee2e6;
        }
        .preview-item img {
            width: 100%;
            height: 100%;
            object-fit: cover;
        }
        .preview-item .remove-preview {
            position: absolute;
            top: 2px;
            right: 2px;
            background: rgba(220, 53, 69, 0.9);
            color: white;
            border: none;
            border-radius: 50%;
            width: 20px;
            height: 20px;
            font-size: 12px;
            line-height: 1;
            cursor: pointer;
        }
        .action-buttons {
            position: absolute;
            top: 10px;
            right: 10px;
            z-index: 10;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.75rem;
        }
        .table-actions {
            white-space: nowrap;
        }
        .table-actions .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 2px;
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
                        <button class="btn btn-outline-secondary" onclick="exportToExcel()">
                            <i class="bi bi-file-excel"></i> Export
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
                    <li class="nav-item" role="presentation">
                        <button class="nav-link" id="maintenance-tab" data-bs-toggle="tab" data-bs-target="#maintenance" 
                                type="button" role="tab">
                            <i class="bi bi-tools"></i> Maintenance
                        </button>
                    </li>
                </ul>

                <!-- Tab Content -->
                <div class="tab-content">
                    <!-- Vehicles List Tab -->
                    <div class="tab-pane fade show active" id="vehicles" role="tabpanel">
                        <!-- Grid/List Toggle -->
                        <div class="mb-3">
                            <div class="btn-group" role="group">
                                <button type="button" class="btn btn-outline-secondary active" id="gridViewBtn">
                                    <i class="bi bi-grid-3x3-gap-fill"></i> Grid
                                </button>
                                <button type="button" class="btn btn-outline-secondary" id="listViewBtn">
                                    <i class="bi bi-list-ul"></i> List
                                </button>
                            </div>
                            <div class="float-end">
                                <input type="text" id="vehicleSearch" class="form-control form-control-sm" placeholder="Search vehicles..." style="width: 250px;">
                            </div>
                        </div>
                        
                        <!-- Grid View -->
                        <div id="gridView" class="row">
                            <?php if (count($vehicles) > 0): ?>
                                <?php foreach ($vehicles as $vehicle): ?>
                                    <div class="col-md-6 col-lg-4 vehicle-item" 
                                         data-name="<?php echo strtolower($vehicle['model'] ?? ''); ?>"
                                         data-plate="<?php echo strtolower($vehicle['license_plate'] ?? ''); ?>"
                                         data-type="<?php echo strtolower($vehicle['vehicle_type'] ?? ''); ?>">
                                        <div class="card vehicle-card">
                                            <div class="position-relative">
                                                <!-- Action Buttons -->
                                                <div class="action-buttons">
                                                    <button class="btn btn-sm btn-light" onclick="editVehicle('<?php echo $vehicle['asset_id']; ?>')" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-light" onclick="updateMileage('<?php echo $vehicle['asset_id']; ?>', <?php echo $vehicle['current_mileage'] ?? 0; ?>)" title="Update Mileage">
                                                        <i class="bi bi-speedometer2"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-light" onclick="recordService('<?php echo $vehicle['asset_id']; ?>')" title="Record Service">
                                                        <i class="bi bi-tools"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteVehicle('<?php echo $vehicle['asset_id']; ?>')" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </div>
                                                
                                                <!-- Vehicle Image -->
                                                <div class="vehicle-image-container">
                                                    <?php if (!empty($vehicle['primary_image']) && file_exists($vehicle['primary_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($vehicle['primary_image']); ?>" 
                                                             alt="Vehicle" class="vehicle-image">
                                                    <?php else: ?>
                                                        <div class="image-placeholder">
                                                            <i class="bi bi-truck"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                            
                                            <div class="card-body">
                                                <div class="d-flex justify-content-between align-items-start mb-2">
                                                    <div>
                                                        <h6 class="card-title mb-1">
                                                            <?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?>
                                                        </h6>
                                                        <small class="text-muted">
                                                            <?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?>
                                                            <?php if (!empty($vehicle['manufacturer'])): ?>
                                                                (<?php echo htmlspecialchars($vehicle['manufacturer']); ?>)
                                                            <?php endif; ?>
                                                        </small>
                                                    </div>
                                                    <span class="badge <?php 
                                                        $status = $vehicle['asset_status'] ?? 'Unknown';
                                                        if ($status == 'Available' || $status == 'In Stock') echo 'bg-success';
                                                        elseif ($status == 'In Use') echo 'bg-warning';
                                                        elseif ($status == 'Maintenance') echo 'bg-info';
                                                        elseif ($status == 'Retired') echo 'bg-secondary';
                                                        else echo 'bg-secondary';
                                                    ?> status-badge">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>
                                                </div>
                                                
                                                <div class="row mt-2">
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Type</small>
                                                        <strong><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/A'); ?></strong>
                                                    </div>
                                                    <div class="col-6">
                                                        <small class="text-muted d-block">Fuel</small>
                                                        <strong><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Year</small>
                                                        <strong><?php echo $vehicle['acquisition_date'] ? date('Y', strtotime($vehicle['acquisition_date'])) : 'N/A'; ?></strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Mileage</small>
                                                        <strong><?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km</strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Location</small>
                                                        <strong><?php echo htmlspecialchars($vehicle['location_name'] ?? 'N/A'); ?></strong>
                                                    </div>
                                                    <div class="col-6 mt-2">
                                                        <small class="text-muted d-block">Next Service</small>
                                                        <strong class="<?php 
                                                            $next_service = $vehicle['next_service_date'] ?? '';
                                                            if ($next_service && strtotime($next_service) < time()) {
                                                                echo 'text-danger';
                                                            }
                                                        ?>">
                                                            <?php echo $next_service ? date('d/m/Y', strtotime($next_service)) : 'N/A'; ?>
                                                        </strong>
                                                    </div>
                                                </div>
                                                
                                                <div class="mt-3 d-flex gap-2">
                                                    <button class="btn btn-sm btn-outline-danger" onclick="viewVehicleDetails('<?php echo htmlspecialchars($vehicle['asset_id']); ?>')">
                                                        <i class="bi bi-eye"></i> View
                                                    </button>
                                                    <button class="btn btn-sm btn-outline-warning" 
                                                            onclick="openMovementModal('<?php echo htmlspecialchars($vehicle['asset_id']); ?>', '<?php echo htmlspecialchars($vehicle['license_plate'] ?? ''); ?>')"
                                                            <?php echo ($vehicle['asset_status'] != 'Available' && $vehicle['asset_status'] != 'In Stock') ? 'disabled' : ''; ?>>
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
                        
                        <!-- List View (Table) -->
                        <div id="listView" style="display: none;">
                            <div class="table-responsive">
                                <table class="table table-hover" id="vehiclesTable">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>License Plate</th>
                                            <th>Model</th>
                                            <th>Type</th>
                                            <th>Status</th>
                                            <th>Mileage</th>
                                            <th>Location</th>
                                            <th>Next Service</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($vehicles as $vehicle): ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($vehicle['primary_image']) && file_exists($vehicle['primary_image'])): ?>
                                                        <img src="<?php echo htmlspecialchars($vehicle['primary_image']); ?>" 
                                                             alt="Vehicle" style="width: 50px; height: 50px; object-fit: cover; border-radius: 5px;">
                                                    <?php else: ?>
                                                        <div style="width: 50px; height: 50px; background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); border-radius: 5px; display: flex; align-items: center; justify-content: center; color: white;">
                                                            <i class="bi bi-truck"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="badge <?php 
                                                        $status = $vehicle['asset_status'] ?? 'Unknown';
                                                        if ($status == 'Available' || $status == 'In Stock') echo 'bg-success';
                                                        elseif ($status == 'In Use') echo 'bg-warning';
                                                        elseif ($status == 'Maintenance') echo 'bg-info';
                                                        else echo 'bg-secondary';
                                                    ?>">
                                                        <?php echo htmlspecialchars($status); ?>
                                                    </span>
                                                </td>
                                                <td><?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km</td>
                                                <td><?php echo htmlspecialchars($vehicle['location_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <span class="<?php 
                                                        $next_service = $vehicle['next_service_date'] ?? '';
                                                        if ($next_service && strtotime($next_service) < time()) {
                                                            echo 'text-danger fw-bold';
                                                        }
                                                    ?>">
                                                        <?php echo $next_service ? date('d/m/Y', strtotime($next_service)) : 'N/A'; ?>
                                                    </span>
                                                </td>
                                                <td class="table-actions">
                                                    <button class="btn btn-sm btn-info" onclick="viewVehicleDetails('<?php echo $vehicle['asset_id']; ?>')" title="View">
                                                        <i class="bi bi-eye"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-primary" onclick="editVehicle('<?php echo $vehicle['asset_id']; ?>')" title="Edit">
                                                        <i class="bi bi-pencil"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-warning" onclick="updateMileage('<?php echo $vehicle['asset_id']; ?>', <?php echo $vehicle['current_mileage'] ?? 0; ?>)" title="Update Mileage">
                                                        <i class="bi bi-speedometer2"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-success" onclick="recordService('<?php echo $vehicle['asset_id']; ?>')" title="Service">
                                                        <i class="bi bi-tools"></i>
                                                    </button>
                                                    <button class="btn btn-sm btn-danger" onclick="deleteVehicle('<?php echo $vehicle['asset_id']; ?>')" title="Delete">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
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
                                    <table class="table table-hover" id="movementsTable">
                                        <thead>
                                            <tr>
                                                <th>Date & Time</th>
                                                <th>Vehicle</th>
                                                <th>License Plate</th>
                                                <th>From Location</th>
                                                <th>To Location</th>
                                                <th>Purpose</th>
                                                <th>Performed By</th>
                                                <th>Status</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php if (count($movements) > 0): ?>
                                                <?php foreach ($movements as $movement): ?>
                                                    <?php 
                                                    $remarks = $movement['remarks'] ?? '';
                                                    $purpose = 'N/A';
                                                    if (strpos($remarks, 'Purpose:') !== false) {
                                                        preg_match('/Purpose: ([^|]+)/', $remarks, $matches);
                                                        $purpose = $matches[1] ?? 'N/A';
                                                    }
                                                    ?>
                                                    <tr>
                                                        <td><?php echo date('d/m/Y H:i', strtotime($movement['movement_date'])); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['asset_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['license_plate'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['from_location_name'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($movement['to_location_name'] ?? 'N/A'); ?></td>
                                                        <td><span class="badge bg-info"><?php echo htmlspecialchars($purpose); ?></span></td>
                                                        <td><?php echo htmlspecialchars($movement['performed_by'] ?? 'N/A'); ?></td>
                                                        <td>
                                                            <span class="badge <?php echo $movement['status'] == 'Completed' ? 'bg-success' : 'bg-warning'; ?>">
                                                                <?php echo htmlspecialchars($movement['status'] ?? 'Pending'); ?>
                                                            </span>
                                                        </td>
                                                    </tr>
                                                <?php endforeach; ?>
                                            <?php else: ?>
                                                <tr>
                                                    <td colspan="8" class="text-center">No movement records found.</td>
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
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-fuel-pump"></i> Fuel Type Distribution</h5>
                                    </div>
                                    <div class="card-body">
                                        <canvas id="fuelChart" style="height: 300px;"></canvas>
                                    </div>
                                </div>
                            </div>
                            <div class="col-md-6">
                                <div class="card mb-4">
                                    <div class="card-header bg-danger text-white">
                                        <h5 class="mb-0"><i class="bi bi-calendar-check"></i> Upcoming Services</h5>
                                    </div>
                                    <div class="card-body">
                                        <div class="table-responsive">
                                            <table class="table table-sm">
                                                <thead>
                                                    <tr>
                                                        <th>Vehicle</th>
                                                        <th>Last Service</th>
                                                        <th>Next Service</th>
                                                        <th>Status</th>
                                                    </tr>
                                                </thead>
                                                <tbody>
                                                    <?php 
                                                    $upcoming_services = array_filter($vehicles, function($v) {
                                                        return !empty($v['next_service_date']) && strtotime($v['next_service_date']) <= strtotime('+30 days');
                                                    });
                                                    usort($upcoming_services, function($a, $b) {
                                                        return strtotime($a['next_service_date']) - strtotime($b['next_service_date']);
                                                    });
                                                    ?>
                                                    <?php if (count($upcoming_services) > 0): ?>
                                                        <?php foreach ($upcoming_services as $vehicle): ?>
                                                            <tr>
                                                                <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?></td>
                                                                <td><?php echo $vehicle['last_service_date'] ? date('d/m/Y', strtotime($vehicle['last_service_date'])) : 'N/A'; ?></td>
                                                                <td>
                                                                    <span class="<?php echo strtotime($vehicle['next_service_date']) < time() ? 'text-danger fw-bold' : ''; ?>">
                                                                        <?php echo date('d/m/Y', strtotime($vehicle['next_service_date'])); ?>
                                                                    </span>
                                                                </td>
                                                                <td>
                                                                    <?php if (strtotime($vehicle['next_service_date']) < time()): ?>
                                                                        <span class="badge bg-danger">Overdue</span>
                                                                    <?php elseif (strtotime($vehicle['next_service_date']) <= strtotime('+7 days')): ?>
                                                                        <span class="badge bg-warning">Due Soon</span>
                                                                    <?php else: ?>
                                                                        <span class="badge bg-info">Upcoming</span>
                                                                    <?php endif; ?>
                                                                </td>
                                                            </tr>
                                                        <?php endforeach; ?>
                                                    <?php else: ?>
                                                        <tr>
                                                            <td colspan="4" class="text-center">No upcoming services</td>
                                                        </tr>
                                                    <?php endif; ?>
                                                </tbody>
                                            </table>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Maintenance Tab -->
                    <div class="tab-pane fade" id="maintenance" role="tabpanel">
                        <div class="card">
                            <div class="card-header bg-danger text-white">
                                <h5 class="mb-0"><i class="bi bi-tools"></i> Vehicle Maintenance Records</h5>
                            </div>
                            <div class="card-body">
                                <div class="table-responsive">
                                    <table class="table table-hover" id="maintenanceTable">
                                        <thead>
                                            <tr>
                                                <th>Date</th>
                                                <th>Vehicle</th>
                                                <th>License Plate</th>
                                                <th>Type</th>
                                                <th>Mileage</th>
                                                <th>Service Due</th>
                                                <th>Actions</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($vehicles as $vehicle): ?>
                                                <?php if ($vehicle['asset_status'] == 'Maintenance' || (isset($vehicle['next_service_date']) && strtotime($vehicle['next_service_date']) <= strtotime('+7 days'))): ?>
                                                    <tr>
                                                        <td><?php echo $vehicle['last_service_date'] ? date('d/m/Y', strtotime($vehicle['last_service_date'])) : 'N/A'; ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?></td>
                                                        <td><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/A'); ?></td>
                                                        <td><?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km</td>
                                                        <td>
                                                            <?php if (!empty($vehicle['next_service_date'])): ?>
                                                                <?php echo date('d/m/Y', strtotime($vehicle['next_service_date'])); ?>
                                                                <?php if (strtotime($vehicle['next_service_date']) < time()): ?>
                                                                    <span class="badge bg-danger">Overdue</span>
                                                                <?php endif; ?>
                                                            <?php else: ?>
                                                                N/A
                                                            <?php endif; ?>
                                                        </td>
                                                        <td>
                                                            <button class="btn btn-sm btn-success" onclick="recordService('<?php echo $vehicle['asset_id']; ?>')">
                                                                <i class="bi bi-check-circle"></i> Record Service
                                                            </button>
                                                        </td>
                                                    </tr>
                                                <?php endif; ?>
                                            <?php endforeach; ?>
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
                                <label class="form-label">Status *</label>
                                <select name="condition" class="form-select" required>
                                    <option value="Available">Available</option>
                                    <option value="In Stock">In Stock</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration/License Plate *</label>
                                <input type="text" name="registration" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Acquisition Date *</label>
                                <input type="date" name="acquisition_date" class="form-control" required>
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
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <select name="location_id" class="form-select">
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['location_id']; ?>">
                                            <?php echo htmlspecialchars($location['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            
                            <!-- Image Upload Section -->
                            <div class="col-12 mb-3">
                                <label class="form-label">Vehicle Images</label>
                                <div class="upload-area" onclick="document.getElementById('vehicleImages').click()">
                                    <i class="bi bi-cloud-upload"></i>
                                    <p class="mb-1">Click to upload vehicle images</p>
                                    <small class="text-muted">Supported formats: JPG, PNG, GIF (Max 5 images)</small>
                                </div>
                                <input type="file" name="vehicle_images[]" id="vehicleImages" 
                                       class="d-none" accept="image/*" multiple onchange="previewImages(this)">
                                <div class="image-preview" id="imagePreview"></div>
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

    <!-- Edit Vehicle Modal -->
    <div class="modal fade" id="editVehicleModal" tabindex="-1">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header bg-primary text-white">
                    <h5 class="modal-title"><i class="bi bi-pencil"></i> Edit Vehicle</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="edit_vehicle">
                    <input type="hidden" name="asset_id" id="edit_asset_id">
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model *</label>
                                <input type="text" name="model" id="edit_model" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" name="manufacturer" id="edit_manufacturer" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vehicle Type *</label>
                                <select name="vehicle_type" id="edit_vehicle_type" class="form-select" required>
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
                                <label class="form-label">Status *</label>
                                <select name="condition" id="edit_condition" class="form-select" required>
                                    <option value="Available">Available</option>
                                    <option value="In Stock">In Stock</option>
                                    <option value="In Use">In Use</option>
                                    <option value="Maintenance">Maintenance</option>
                                    <option value="Retired">Retired</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Registration/License Plate *</label>
                                <input type="text" name="registration" id="edit_registration" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Acquisition Date *</label>
                                <input type="date" name="acquisition_date" id="edit_acquisition_date" class="form-control" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" name="serial_number" id="edit_serial_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Chassis Number</label>
                                <input type="text" name="chassis_number" id="edit_chassis_number" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Fuel Type</label>
                                <select name="fuel_type" id="edit_fuel_type" class="form-select">
                                    <option value="Petrol">Petrol</option>
                                    <option value="Diesel">Diesel</option>
                                    <option value="Electric">Electric</option>
                                    <option value="Hybrid">Hybrid</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Engine Capacity</label>
                                <input type="text" name="engine_capacity" id="edit_engine_capacity" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Color</label>
                                <input type="text" name="color" id="edit_color" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Current Mileage</label>
                                <input type="number" name="current_mileage" id="edit_current_mileage" class="form-control">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <select name="location_id" id="edit_location_id" class="form-select">
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo $location['location_id']; ?>">
                                            <?php echo htmlspecialchars($location['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update Vehicle</button>
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
                                    <?php if ($vehicle['asset_status'] == 'Available' || $vehicle['asset_status'] == 'In Stock'): ?>
                                        <option value="<?php echo htmlspecialchars($vehicle['asset_id']); ?>" 
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
                            <label class="form-label">From Location</label>
                            <select name="from_location" class="form-select">
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['location_id']; ?>">
                                        <?php echo htmlspecialchars($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        
                        <div class="mb-3">
                            <label class="form-label">To Location</label>
                            <select name="to_location" class="form-select">
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo $location['location_id']; ?>">
                                        <?php echo htmlspecialchars($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
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

    <!-- Service Record Modal -->
    <div class="modal fade" id="serviceModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="bi bi-tools"></i> Record Service</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_service">
                    <input type="hidden" name="asset_id" id="service_asset_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Last Service Date *</label>
                            <input type="date" name="last_service_date" id="service_last_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Next Service Date *</label>
                            <input type="date" name="next_service_date" id="service_next_date" class="form-control" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Service Notes</label>
                            <textarea name="service_notes" class="form-control" rows="3" placeholder="Enter service details..."></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Record Service</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Mileage Update Modal -->
    <div class="modal fade" id="mileageModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-info text-white">
                    <h5 class="modal-title"><i class="bi bi-speedometer2"></i> Update Mileage</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form method="POST">
                    <input type="hidden" name="action" value="update_mileage">
                    <input type="hidden" name="asset_id" id="mileage_asset_id">
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Current Mileage</label>
                            <input type="text" class="form-control" id="current_mileage_display" readonly>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">New Mileage *</label>
                            <input type="number" name="new_mileage" class="form-control" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-info">Update Mileage</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="bi bi-exclamation-triangle"></i> Confirm Delete</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this vehicle? This action cannot be undone and will remove all associated records (movements, images, etc.).</p>
                </div>
                <div class="modal-footer">
                    <form method="POST">
                        <input type="hidden" name="action" value="delete_vehicle">
                        <input type="hidden" name="asset_id" id="delete_asset_id">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete Vehicle</button>
                    </form>
                </div>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    <script>
        // Initialize DataTables
        $(document).ready(function() {
            $('#movementsTable').DataTable({
                pageLength: 25,
                order: [[0, 'desc']]
            });
            
            $('#maintenanceTable').DataTable({
                pageLength: 25
            });
            
            $('#vehiclesTable').DataTable({
                pageLength: 25,
                order: [[1, 'asc']]
            });
        });

        // Image preview function
        function previewImages(input) {
            const preview = document.getElementById('imagePreview');
            preview.innerHTML = '';
            
            if (input.files) {
                const filesAmount = input.files.length;
                
                for (let i = 0; i < filesAmount; i++) {
                    const reader = new FileReader();
                    
                    reader.onload = function(event) {
                        const previewItem = document.createElement('div');
                        previewItem.className = 'preview-item';
                        
                        const img = document.createElement('img');
                        img.src = event.target.result;
                        
                        const removeBtn = document.createElement('span');
                        removeBtn.className = 'remove-preview';
                        removeBtn.innerHTML = '×';
                        removeBtn.onclick = function() {
                            previewItem.remove();
                        };
                        
                        previewItem.appendChild(img);
                        previewItem.appendChild(removeBtn);
                        preview.appendChild(previewItem);
                    }
                    
                    reader.readAsDataURL(input.files[i]);
                }
            }
        }

        // Grid/List view toggle
        document.getElementById('gridViewBtn')?.addEventListener('click', function() {
            document.getElementById('gridView').style.display = 'flex';
            document.getElementById('listView').style.display = 'none';
            this.classList.add('active');
            document.getElementById('listViewBtn').classList.remove('active');
        });

        document.getElementById('listViewBtn')?.addEventListener('click', function() {
            document.getElementById('gridView').style.display = 'none';
            document.getElementById('listView').style.display = 'block';
            this.classList.add('active');
            document.getElementById('gridViewBtn').classList.remove('active');
        });

        // Vehicle search in grid view
        document.getElementById('vehicleSearch')?.addEventListener('keyup', function() {
            const searchText = this.value.toLowerCase();
            const vehicles = document.querySelectorAll('.vehicle-item');
            
            vehicles.forEach(vehicle => {
                const name = vehicle.dataset.name || '';
                const plate = vehicle.dataset.plate || '';
                const type = vehicle.dataset.type || '';
                
                if (name.includes(searchText) || plate.includes(searchText) || type.includes(searchText)) {
                    vehicle.style.display = 'block';
                } else {
                    vehicle.style.display = 'none';
                }
            });
        });

        // Update vehicle details when selection changes
        document.getElementById('movementVehicleSelect')?.addEventListener('change', function() {
            const select = this;
            const selected = select.options[select.selectedIndex];
            const detailsDiv = document.getElementById('vehicleDetails');
            
            if (select.value) {
                document.getElementById('detailPlate').textContent = selected.dataset.plate || 'N/A';
                document.getElementById('detailModel').textContent = selected.dataset.model || 'N/A';
                document.getElementById('detailType').textContent = selected.dataset.type || 'N/A';
                document.getElementById('detailMileage').textContent = (selected.dataset.mileage || '0') + ' km';
                detailsDiv.style.display = 'block';
            } else {
                detailsDiv.style.display = 'none';
            }
        });

        // CRUD Functions
        function openMovementModal(assetId, licensePlate) {
            const modal = new bootstrap.Modal(document.getElementById('movementModal'));
            const select = document.getElementById('movementVehicleSelect');
            if (select) {
                select.value = assetId;
                select.dispatchEvent(new Event('change'));
            }
            modal.show();
        }

        function viewVehicleDetails(assetId) {
            window.location.href = 'vehicle_details.php?id=' + assetId;
        }

        function editVehicle(assetId) {
            // Fetch vehicle data via AJAX
            fetch('get_vehicle.php?id=' + assetId)
                .then(response => response.json())
                .then(data => {
                    document.getElementById('edit_asset_id').value = data.asset_id;
                    document.getElementById('edit_model').value = data.model || '';
                    document.getElementById('edit_manufacturer').value = data.manufacturer || '';
                    document.getElementById('edit_vehicle_type').value = data.vehicle_type || '';
                    document.getElementById('edit_condition').value = data.asset_status || '';
                    document.getElementById('edit_registration').value = data.license_plate || '';
                    document.getElementById('edit_acquisition_date').value = data.acquisition_date || '';
                    document.getElementById('edit_serial_number').value = data.serial_number || '';
                    document.getElementById('edit_chassis_number').value = data.chassis_number || '';
                    document.getElementById('edit_fuel_type').value = data.fuel_type || 'Petrol';
                    document.getElementById('edit_engine_capacity').value = data.engine_capacity || '';
                    document.getElementById('edit_color').value = data.color || '';
                    document.getElementById('edit_current_mileage').value = data.current_mileage || 0;
                    document.getElementById('edit_location_id').value = data.location_id || 'LOC001';
                    
                    const modal = new bootstrap.Modal(document.getElementById('editVehicleModal'));
                    modal.show();
                })
                .catch(error => {
                    console.error('Error:', error);
                    alert('Error loading vehicle data');
                });
        }

        function deleteVehicle(assetId) {
            document.getElementById('delete_asset_id').value = assetId;
            const modal = new bootstrap.Modal(document.getElementById('deleteModal'));
            modal.show();
        }

        function updateMileage(assetId, currentMileage) {
            document.getElementById('mileage_asset_id').value = assetId;
            document.getElementById('current_mileage_display').value = currentMileage + ' km';
            const modal = new bootstrap.Modal(document.getElementById('mileageModal'));
            modal.show();
        }

        function recordService(assetId) {
            document.getElementById('service_asset_id').value = assetId;
            const today = new Date().toISOString().split('T')[0];
            const nextService = new Date();
            nextService.setMonth(nextService.getMonth() + 6);
            const nextServiceDate = nextService.toISOString().split('T')[0];
            
            document.getElementById('service_last_date').value = today;
            document.getElementById('service_next_date').value = nextServiceDate;
            
            const modal = new bootstrap.Modal(document.getElementById('serviceModal'));
            modal.show();
        }

        function exportToExcel() {
            window.location.href = 'export_vehicles.php';
        }

        // Initialize charts
        <?php if ($total_vehicles > 0): ?>
        // Status Chart
        const statusCtx = document.getElementById('statusChart')?.getContext('2d');
        if (statusCtx) {
            new Chart(statusCtx, {
                type: 'doughnut',
                data: {
                    labels: ['Available/In Stock', 'In Use', 'Maintenance', 'Retired'],
                    datasets: [{
                        data: [
                            <?php echo $available_vehicles; ?>,
                            <?php echo $in_use_vehicles; ?>,
                            <?php echo $maintenance_vehicles; ?>,
                            <?php echo $retired_vehicles; ?>
                        ],
                        backgroundColor: ['#28a745', '#ffc107', '#17a2b8', '#6c757d']
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

        // Type Chart
        <?php
        $typeData = [];
        foreach ($vehicles as $vehicle) {
            $type = $vehicle['vehicle_type'] ?? 'Others';
            $typeData[$type] = isset($typeData[$type]) ? $typeData[$type] + 1 : 1;
        }
        ?>
        
        const typeCtx = document.getElementById('typeChart')?.getContext('2d');
        if (typeCtx && <?php echo count($typeData); ?> > 0) {
            new Chart(typeCtx, {
                type: 'bar',
                data: {
                    labels: <?php echo json_encode(array_keys($typeData)); ?>,
                    datasets: [{
                        label: 'Number of Vehicles',
                        data: <?php echo json_encode(array_values($typeData)); ?>,
                        backgroundColor: '#dc3545'
                    }]
                },
                options: {
                    responsive: true,
                    scales: {
                        y: {
                            beginAtZero: true,
                            stepSize: 1
                        }
                    },
                    plugins: {
                        legend: {
                            display: false
                        }
                    }
                }
            });
        }

        // Fuel Type Chart
        <?php
        $fuelData = [];
        foreach ($vehicles as $vehicle) {
            $fuel = $vehicle['fuel_type'] ?? 'Other';
            $fuelData[$fuel] = isset($fuelData[$fuel]) ? $fuelData[$fuel] + 1 : 1;
        }
        ?>
        
        const fuelCtx = document.getElementById('fuelChart')?.getContext('2d');
        if (fuelCtx && <?php echo count($fuelData); ?> > 0) {
            new Chart(fuelCtx, {
                type: 'pie',
                data: {
                    labels: <?php echo json_encode(array_keys($fuelData)); ?>,
                    datasets: [{
                        data: <?php echo json_encode(array_values($fuelData)); ?>,
                        backgroundColor: ['#dc3545', '#ffc107', '#28a745', '#17a2b8']
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
        <?php endif; ?>
    </script>
</body>
</html>
<?php $conn->close(); ?>
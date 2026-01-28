<?php
require_once '../../includes/check_auth.php';
check_auth(['admin']); // Only admin can access

// Database connection
require_once '../../includes/db.php';
$conn = $db->conn;

// Get department ID from URL
$department_id = isset($_GET['dept_id']) ? $_GET['dept_id'] : '';

// Fetch department details
$department = null;
if ($department_id) {
    $stmt = $conn->prepare("SELECT d.*, u.full_name as head_name, u.email as head_email FROM departments d LEFT JOIN users u ON d.department_head_id = u.user_id WHERE d.department_id = ?");
    $stmt->bind_param("s", $department_id);
    $stmt->execute();
    $result = $stmt->get_result();
    $department = $result->fetch_assoc();
    $stmt->close();
}

// If no department found, redirect to departments list
if (!$department) {
    header("Location: departments.php");
    exit();
}

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['assign_asset'])) {
        $asset_id = $_POST['asset_id'];
        $assigned_to = $_POST['assigned_to_user_id'] ?? null;
        
        // Handle empty assignment (convert empty string to NULL)
        if ($assigned_to === '') {
            $assigned_to = null;
        }
        
        // Update asset assignment
        $stmt = $conn->prepare("UPDATE assets SET owner_department_id = ?, assigned_to_user_id = ?, asset_status = 'Assigned' WHERE asset_id = ?");
        $stmt->bind_param("sss", $department_id, $assigned_to, $asset_id);
        
        if ($stmt->execute()) {
            // Log the assignment in asset_history
            $history_id = uniqid('HIS_');
            $change_type = 'Department Assignment';
            $change_summary = "Asset assigned to " . $department['name'];
            
            $stmt_history = $conn->prepare("INSERT INTO asset_history (asset_history_id, asset_id, changed_by_user_id, change_type, change_summary) VALUES (?, ?, ?, ?, ?)");
            $stmt_history->bind_param("sssss", $history_id, $asset_id, $_SESSION['user_id'], $change_type, $change_summary);
            $stmt_history->execute();
            $stmt_history->close();
            
            // Record movement
            $movement_id = uniqid('MOV_');
            $movement_type = 'Department Transfer';
            $remarks = "Asset assigned to " . $department['name'] . " by " . $_SESSION['username'];
            
            $stmt_movement = $conn->prepare("INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, remarks, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
            $stmt_movement->bind_param("sssss", $movement_id, $asset_id, $movement_type, $_SESSION['user_id'], $remarks);
            $stmt_movement->execute();
            $stmt_movement->close();
            
            $success_msg = "Asset successfully assigned to department!";
        } else {
            $error_msg = "Failed to assign asset: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['add_new_asset'])) {
        // Add new asset directly to this department
        $asset_id = 'AST_' . strtoupper(uniqid());
        $asset_tag = $_POST['asset_tag'] ?? null;
        $asset_name = $_POST['asset_name'];
        $asset_class = $_POST['asset_class'];
        $model = $_POST['model'] ?? null;
        $manufacturer = $_POST['manufacturer'] ?? null;
        $serial_number = $_POST['serial_number'] ?? null;
        $purchase_order_number = $_POST['purchase_order_number'] ?? null;
        $acquisition_date = $_POST['acquisition_date'] ?? null;
        $warranty_expiry = $_POST['warranty_expiry'] ?? null;
        $vendor = $_POST['vendor'] ?? null;
        $cost = $_POST['cost'] ?? null;
        $depreciation_method = $_POST['depreciation_method'] ?? null;
        $depreciation_rate = $_POST['depreciation_rate'] ?? null;
        $depreciation_start_date = $_POST['depreciation_start_date'] ?? null;
        $life_expectancy_years = $_POST['life_expectancy_years'] ?? null;
        $location_id = $_POST['location_id'] ?? null;
        $assigned_to = $_POST['assigned_to_user_id'] ?? null;
        $remarks = $_POST['remarks'] ?? null;
        
        // Handle empty assignment (convert empty string to NULL)
        if ($assigned_to === '') {
            $assigned_to = null;
        }
        
        // Set status based on assignment
        $asset_status = $assigned_to ? 'Assigned' : 'In Stock';
        
        // Prepare statement without created_by column
        // Note: We need to handle NULL values properly in the bind_param
        $stmt = $conn->prepare("INSERT INTO assets (asset_id, asset_tag, asset_name, asset_class, model, manufacturer, serial_number, purchase_order_number, acquisition_date, warranty_expiry, vendor, cost, depreciation_method, depreciation_rate, depreciation_start_date, life_expectancy_years, asset_status, location_id, owner_department_id, assigned_to_user_id, remarks) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        
        // Handle NULL values for dates
        $acquisition_date = empty($acquisition_date) ? null : $acquisition_date;
        $warranty_expiry = empty($warranty_expiry) ? null : $warranty_expiry;
        $depreciation_start_date = empty($depreciation_start_date) ? null : $depreciation_start_date;
        
        // Convert empty strings to NULL for foreign key constraints
        $location_id = empty($location_id) ? null : $location_id;
        
        $stmt->bind_param("sssssssssssdssdisssss", 
            $asset_id, $asset_tag, $asset_name, $asset_class, $model, $manufacturer, $serial_number, 
            $purchase_order_number, $acquisition_date, $warranty_expiry, $vendor, $cost, 
            $depreciation_method, $depreciation_rate, $depreciation_start_date, $life_expectancy_years,
            $asset_status, $location_id, $department_id, $assigned_to, $remarks
        );
        
        if ($stmt->execute()) {
            // Log the creation in asset_history with user info
            $history_id = uniqid('HIS_');
            $change_type = 'Asset Creation';
            $change_summary = "New asset '$asset_name' created by " . $_SESSION['username'] . " and assigned to " . $department['name'];
            
            $stmt_history = $conn->prepare("INSERT INTO asset_history (asset_history_id, asset_id, changed_by_user_id, change_type, change_summary) VALUES (?, ?, ?, ?, ?)");
            $stmt_history->bind_param("sssss", $history_id, $asset_id, $_SESSION['user_id'], $change_type, $change_summary);
            $stmt_history->execute();
            $stmt_history->close();
            
            // Record movement
            $movement_id = uniqid('MOV_');
            $movement_type = 'Asset Creation';
            $movement_remarks = "New asset '$asset_name' created in " . $department['name'] . " by " . $_SESSION['username'];
            
            $stmt_movement = $conn->prepare("INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, remarks, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
            $stmt_movement->bind_param("sssss", $movement_id, $asset_id, $movement_type, $_SESSION['user_id'], $movement_remarks);
            $stmt_movement->execute();
            $stmt_movement->close();
            
            $success_msg = "New asset '$asset_name' added successfully to department!";
        } else {
            $error_msg = "Failed to add new asset: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['remove_asset'])) {
        $asset_id = $_POST['asset_id'];
        
        // Remove asset from department (return to stock)
        $stmt = $conn->prepare("UPDATE assets SET owner_department_id = NULL, assigned_to_user_id = NULL, asset_status = 'In Stock' WHERE asset_id = ?");
        $stmt->bind_param("s", $asset_id);
        
        if ($stmt->execute()) {
            // Log the removal
            $history_id = uniqid('HIS_');
            $change_type = 'Department Removal';
            $change_summary = "Asset removed from " . $department['name'] . " and returned to stock by " . $_SESSION['username'];
            
            $stmt_history = $conn->prepare("INSERT INTO asset_history (asset_history_id, asset_id, changed_by_user_id, change_type, change_summary) VALUES (?, ?, ?, ?, ?)");
            $stmt_history->bind_param("sssss", $history_id, $asset_id, $_SESSION['user_id'], $change_type, $change_summary);
            $stmt_history->execute();
            $stmt_history->close();
            
            // Record movement
            $movement_id = uniqid('MOV_');
            $movement_type = 'Department Removal';
            $movement_remarks = "Asset removed from " . $department['name'] . " and returned to stock by " . $_SESSION['username'];
            
            $stmt_movement = $conn->prepare("INSERT INTO asset_movements (movement_id, asset_id, movement_type, performed_by_user_id, remarks, status) VALUES (?, ?, ?, ?, ?, 'Completed')");
            $stmt_movement->bind_param("sssss", $movement_id, $asset_id, $movement_type, $_SESSION['user_id'], $movement_remarks);
            $stmt_movement->execute();
            $stmt_movement->close();
            
            $success_msg = "Asset successfully removed from department!";
        } else {
            $error_msg = "Failed to remove asset: " . $conn->error;
        }
        $stmt->close();
    }
}

// Fetch assets assigned to this department
$assets = [];
$stmt = $conn->prepare("
    SELECT a.*, ac.class_name, u.full_name as assigned_to_name 
    FROM assets a 
    LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
    LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
    WHERE a.owner_department_id = ? AND a.is_active = 1
    ORDER BY a.asset_name
");
$stmt->bind_param("s", $department_id);
$stmt->execute();
$result = $stmt->get_result();
$assets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch available assets (not assigned to any department)
$available_assets = [];
$stmt = $conn->prepare("
    SELECT a.*, ac.class_name 
    FROM assets a 
    LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
    WHERE (a.owner_department_id IS NULL OR a.owner_department_id = '') 
    AND a.asset_status = 'In Stock' 
    AND a.is_active = 1
    ORDER BY a.asset_name
");
$stmt->execute();
$result = $stmt->get_result();
$available_assets = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch department users
$department_users = [];
$stmt = $conn->prepare("
    SELECT user_id, username, full_name, email 
    FROM users 
    WHERE department_id = ? AND is_active = 1
    ORDER BY full_name
");
$stmt->bind_param("s", $department_id);
$stmt->execute();
$result = $stmt->get_result();
$department_users = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch asset classes for dropdown
$asset_classes = [];
$stmt = $conn->prepare("SELECT class_id, class_name FROM asset_classes ORDER BY class_name");
$stmt->execute();
$result = $stmt->get_result();
$asset_classes = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Fetch locations for dropdown
$locations = [];
$stmt = $conn->prepare("SELECT location_id, name FROM locations ORDER BY name");
$stmt->execute();
$result = $stmt->get_result();
$locations = $result->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Get department asset statistics
$total_assets = count($assets);
$asset_values = array_column($assets, 'cost');
$total_value = array_sum($asset_values);
$average_value = $total_assets > 0 ? $total_value / $total_assets : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo htmlspecialchars($department['name']); ?> Assets - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'adsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-building me-2"></i>
                        <?php echo htmlspecialchars($department['name']); ?> - Assets Management
                    </h1>
                    <a href="departments.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left"></i> Back to Departments
                    </a>
                </div>
                
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Department Info Card -->
                <div class="card mb-4">
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-3">
                                <h5>Department Details</h5>
                                <p class="mb-1"><strong>Name:</strong> <?php echo htmlspecialchars($department['name']); ?></p>
                                <?php if ($department['head_name']): ?>
                                    <p class="mb-1"><strong>Head:</strong> <?php echo htmlspecialchars($department['head_name']); ?></p>
                                <?php endif; ?>
                            </div>
                            <div class="col-md-3">
                                <h5>Asset Statistics</h5>
                                <p class="mb-1"><strong>Total Assets:</strong> <?php echo $total_assets; ?></p>
                                <p class="mb-1"><strong>Total Value:</strong> $<?php echo number_format($total_value, 2); ?></p>
                                <p class="mb-1"><strong>Avg Value:</strong> $<?php echo number_format($average_value, 2); ?></p>
                            </div>
                            <div class="col-md-6">
                                <h5>Quick Actions</h5>
                                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                                    <i class="bi bi-plus-circle"></i> Assign Existing Asset
                                </button>
                                <button type="button" class="btn btn-success me-2" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                                    <i class="bi bi-plus-square"></i> Add New Asset
                                </button>
                                <button type="button" class="btn btn-outline-primary" onclick="exportAssets()">
                                    <i class="bi bi-download"></i> Export List
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Department Assets Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">Assigned Assets (<?php echo $total_assets; ?>)</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="assetsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>Asset ID</th>
                                        <th>Asset Name</th>
                                        <th>Class</th>
                                        <th>Model</th>
                                        <th>Serial No.</th>
                                        <th>Assigned To</th>
                                        <th>Status</th>
                                        <th>Cost</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($assets as $asset): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($asset['asset_id']); ?></td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong>
                                                <?php if ($asset['asset_tag']): ?>
                                                    <br><small class="text-muted">Tag: <?php echo htmlspecialchars($asset['asset_tag']); ?></small>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($asset['class_name']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['model']); ?></td>
                                            <td><?php echo htmlspecialchars($asset['serial_number']); ?></td>
                                            <td>
                                                <?php if ($asset['assigned_to_name']): ?>
                                                    <?php echo htmlspecialchars($asset['assigned_to_name']); ?>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned to individual</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <span class="badge bg-<?php echo $asset['asset_status'] === 'Assigned' ? 'success' : 'warning'; ?>">
                                                    <?php echo htmlspecialchars($asset['asset_status']); ?>
                                                </span>
                                            </td>
                                            <td>$<?php echo number_format($asset['cost'], 2); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-outline-info" 
                                                        onclick="viewAssetDetails('<?php echo $asset['asset_id']; ?>')"
                                                        title="View Details">
                                                    <i class="bi bi-eye"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="reassignAsset('<?php echo $asset['asset_id']; ?>')"
                                                        title="Reassign">
                                                    <i class="bi bi-arrow-repeat"></i>
                                                </button>
                                                <form method="POST" style="display:inline;" 
                                                      onsubmit="return confirm('Remove this asset from department?')">
                                                    <input type="hidden" name="asset_id" value="<?php echo $asset['asset_id']; ?>">
                                                    <button type="submit" name="remove_asset" class="btn btn-sm btn-outline-danger" title="Remove">
                                                        <i class="bi bi-x-circle"></i>
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($assets)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">No Assets Assigned</h5>
                                <p class="text-muted">This department doesn't have any assets assigned yet.</p>
                                <button type="button" class="btn btn-primary me-2" data-bs-toggle="modal" data-bs-target="#assignAssetModal">
                                    <i class="bi bi-plus-circle"></i> Assign Existing Asset
                                </button>
                                <button type="button" class="btn btn-success" data-bs-toggle="modal" data-bs-target="#addAssetModal">
                                    <i class="bi bi-plus-square"></i> Add New Asset
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Assign Existing Asset Modal -->
    <div class="modal fade" id="assignAssetModal" tabindex="-1" aria-labelledby="assignAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="assignAssetModalLabel">Assign Existing Asset to Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="asset_id" class="form-label">Select Asset *</label>
                            <select class="form-select" id="asset_id" name="asset_id" required>
                                <option value="">-- Select an asset --</option>
                                <?php foreach ($available_assets as $asset): ?>
                                    <option value="<?php echo htmlspecialchars($asset['asset_id']); ?>">
                                        <?php echo htmlspecialchars($asset['asset_name']); ?> 
                                        (<?php echo htmlspecialchars($asset['class_name']); ?> - 
                                        <?php echo htmlspecialchars($asset['model']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <?php if (empty($available_assets)): ?>
                                <div class="alert alert-warning mt-2">
                                    <i class="bi bi-exclamation-triangle"></i> No available assets in stock.
                                </div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assigned_to_user_id" class="form-label">Assign to User (Optional)</label>
                            <select class="form-select" id="assigned_to_user_id" name="assigned_to_user_id">
                                <option value="">-- Keep unassigned to individual --</option>
                                <?php foreach ($department_users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Leave as "-- Keep unassigned to individual --" if you don't want to assign to a specific user.</small>
                        </div>
                        
                        <div class="alert alert-info">
                            <i class="bi bi-info-circle"></i> This will change the asset status to "Assigned" and record the movement.
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="assign_asset" class="btn btn-primary" <?php echo empty($available_assets) ? 'disabled' : ''; ?>>
                            <i class="bi bi-check-circle"></i> Assign Asset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Add New Asset Modal -->
    <div class="modal fade" id="addAssetModal" tabindex="-1" aria-labelledby="addAssetModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addAssetModalLabel">Add New Asset to Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="asset_name" class="form-label">Asset Name *</label>
                                <input type="text" class="form-control" id="asset_name" name="asset_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="asset_tag" class="form-label">Asset Tag</label>
                                <input type="text" class="form-control" id="asset_tag" name="asset_tag">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="asset_class" class="form-label">Asset Class *</label>
                                <select class="form-select" id="asset_class" name="asset_class" required>
                                    <option value="">-- Select Asset Class --</option>
                                    <?php foreach ($asset_classes as $class): ?>
                                        <option value="<?php echo htmlspecialchars($class['class_id']); ?>">
                                            <?php echo htmlspecialchars($class['class_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="model" class="form-label">Model</label>
                                <input type="text" class="form-control" id="model" name="model">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="manufacturer" class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" id="manufacturer" name="manufacturer">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="serial_number" class="form-label">Serial Number</label>
                                <input type="text" class="form-control" id="serial_number" name="serial_number">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="purchase_order_number" class="form-label">Purchase Order Number</label>
                                <input type="text" class="form-control" id="purchase_order_number" name="purchase_order_number">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="vendor" class="form-label">Vendor</label>
                                <input type="text" class="form-control" id="vendor" name="vendor">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="acquisition_date" class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" id="acquisition_date" name="acquisition_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="warranty_expiry" class="form-label">Warranty Expiry Date</label>
                                <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="cost" class="form-label">Cost ($)</label>
                                <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="location_id" class="form-label">Location</label>
                                <select class="form-select" id="location_id" name="location_id">
                                    <option value="">-- Select Location --</option>
                                    <?php foreach ($locations as $location): ?>
                                        <option value="<?php echo htmlspecialchars($location['location_id']); ?>">
                                            <?php echo htmlspecialchars($location['name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="depreciation_method" class="form-label">Depreciation Method</label>
                                <select class="form-select" id="depreciation_method" name="depreciation_method">
                                    <option value="">-- Select Method --</option>
                                    <option value="Straight Line">Straight Line</option>
                                    <option value="Declining Balance">Declining Balance</option>
                                    <option value="Sum of Years">Sum of Years</option>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="depreciation_rate" class="form-label">Depreciation Rate (%)</label>
                                <input type="number" class="form-control" id="depreciation_rate" name="depreciation_rate" step="0.01" min="0" max="100">
                            </div>
                        </div>
                        
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="depreciation_start_date" class="form-label">Depreciation Start Date</label>
                                <input type="date" class="form-control" id="depreciation_start_date" name="depreciation_start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="life_expectancy_years" class="form-label">Life Expectancy (Years)</label>
                                <input type="number" class="form-control" id="life_expectancy_years" name="life_expectancy_years" min="1" max="100">
                            </div>
                        </div>
                        
                        <div class="mb-3">
                            <label for="assigned_to_user_id_new" class="form-label">Assign to User (Optional)</label>
                            <select class="form-select" id="assigned_to_user_id_new" name="assigned_to_user_id">
                                <option value="">-- Keep unassigned to individual --</option>
                                <?php foreach ($department_users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Leave as "-- Keep unassigned to individual --" if you don't want to assign to a specific user.</small>
                        </div>
                        
                        <div class="mb-3">
                            <label for="remarks" class="form-label">Remarks</label>
                            <textarea class="form-control" id="remarks" name="remarks" rows="3"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_new_asset" class="btn btn-success">
                            <i class="bi bi-check-circle"></i> Add New Asset
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#assetsTable').DataTable({
                "pageLength": 25,
                "order": [[1, 'asc']]
            });
        });
        
        function viewAssetDetails(assetId) {
            window.open(`asset_details.php?id=${assetId}`, '_blank');
        }
        
        function reassignAsset(assetId) {
            if (confirm('Reassign this asset to another department/user?')) {
                window.location.href = `reassign_asset.php?asset_id=${assetId}&dept_id=<?php echo $department_id; ?>`;
            }
        }
        
        function exportAssets() {
            window.location.href = `export_assets.php?dept_id=<?php echo $department_id; ?>&type=department`;
        }
        
        // Auto-generate asset tag based on name
        $('#asset_name').on('blur', function() {
            var assetName = $(this).val();
            if (assetName && !$('#asset_tag').val()) {
                // Create a tag from asset name (first letters of words + timestamp)
                var tag = assetName
                    .toUpperCase()
                    .split(' ')
                    .map(word => word.charAt(0))
                    .join('');
                
                // If tag is too short or empty, use first 3 characters
                if (tag.length < 2) {
                    tag = assetName.toUpperCase().substring(0, 3);
                }
                
                // Add timestamp for uniqueness
                var timestamp = new Date().getTime().toString().substr(-4);
                $('#asset_tag').val(tag + '-' + timestamp);
            }
        });
        
        // Set depreciation start date to acquisition date if empty
        $('#acquisition_date').on('change', function() {
            if ($('#acquisition_date').val() && !$('#depreciation_start_date').val()) {
                $('#depreciation_start_date').val($('#acquisition_date').val());
            }
        });
        
        // Set warranty expiry to 1 year from acquisition date
        $('#acquisition_date').on('change', function() {
            if ($('#acquisition_date').val() && !$('#warranty_expiry').val()) {
                var acquisitionDate = new Date($('#acquisition_date').val());
                var warrantyDate = new Date(acquisitionDate);
                warrantyDate.setFullYear(warrantyDate.getFullYear() + 1);
                
                // Format date as YYYY-MM-DD
                var formattedDate = warrantyDate.toISOString().split('T')[0];
                $('#warranty_expiry').val(formattedDate);
            }
        });
        
        // Auto-calculate life expectancy based on depreciation method
        $('#depreciation_method').on('change', function() {
            var method = $(this).val();
            if (method && !$('#life_expectancy_years').val()) {
                switch(method) {
                    case 'Straight Line':
                        $('#life_expectancy_years').val(5);
                        break;
                    case 'Declining Balance':
                        $('#life_expectancy_years').val(7);
                        break;
                    case 'Sum of Years':
                        $('#life_expectancy_years').val(10);
                        break;
                }
            }
        });
    </script>
</body>
</html>
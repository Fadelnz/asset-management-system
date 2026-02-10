<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php'; // Add this line - adjust path as needed

check_auth(['logistic_coordinator','it_operation']);

$message = '';
$error = '';
$uploaded_image = null;

// Fetch asset classes for dropdown
$asset_classes = [];
$classes_query = "SELECT class_id, class_name FROM asset_classes ORDER BY class_name";
$classes_result = $db->conn->query($classes_query);
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $asset_classes[] = $row;
    }
}

// Fetch locations for dropdown
$locations = [];
$locations_query = "SELECT location_id, name FROM locations ORDER BY name";
$locations_result = $db->conn->query($locations_query);
if ($locations_result) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Helper function to get current user
function get_current_user_id() {
    return isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'SYSTEM';
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload
        if (isset($_FILES['asset_image']) && $_FILES['asset_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB in bytes
            $upload_dir = '../../uploads/assets/';
            
            // Create upload directory if it doesn't exist
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $_FILES['asset_image']['name'];
            $file_tmp = $_FILES['asset_image']['tmp_name'];
            $file_size = $_FILES['asset_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            // Validate file extension
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.');
            }
            
            // Validate file size
            if ($file_size > $max_file_size) {
                throw new Exception('File size must be less than 5MB.');
            }
            
            // Generate unique filename
            $unique_id = uniqid('asset_', true);
            $new_filename = $unique_id . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            // Move uploaded file
            if (move_uploaded_file($file_tmp, $destination)) {
                $uploaded_image = 'uploads/assets/' . $new_filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Generate asset ID
        $asset_id = 'ASSET' . date('Ymd') . str_pad(rand(1, 9999), 4, '0', STR_PAD_LEFT);
        
        // Prepare data using sanitization
        $asset_tag = $db->sanitize($_POST['asset_tag'] ?? '');
        $asset_name = $db->sanitize($_POST['asset_name'] ?? '');
        $asset_class = $db->sanitize($_POST['asset_class'] ?? '');
        $model = $db->sanitize($_POST['model'] ?? '');
        $manufacturer = $db->sanitize($_POST['manufacturer'] ?? '');
        $serial_number = $db->sanitize($_POST['serial_number'] ?? '');
        $purchase_order_number = $db->sanitize($_POST['purchase_order_number'] ?? '');
        $acquisition_date = $db->sanitize($_POST['acquisition_date'] ?? '');
        $warranty_expiry = $db->sanitize($_POST['warranty_expiry'] ?? '');
        $vendor = $db->sanitize($_POST['vendor'] ?? '');
        $cost = floatval($_POST['cost'] ?? 0);
        $depreciation_method = $db->sanitize($_POST['depreciation_method'] ?? '');
        $depreciation_rate = floatval($_POST['depreciation_rate'] ?? 0);
        $depreciation_start_date = $db->sanitize($_POST['depreciation_start_date'] ?? '');
        $life_expectancy_years = intval($_POST['life_expectancy_years'] ?? 0);
        $location_id = $db->sanitize($_POST['location_id'] ?? '');
        $remarks = $db->sanitize($_POST['remarks'] ?? '');
        
        // Get current user
        $current_user = get_current_user_id();
        $current_date = date('Y-m-d H:i:s');
        
        // Insert into assets table using PDO
        $sql = "INSERT INTO assets (
            asset_id, asset_tag, asset_name, asset_class, model, manufacturer, 
            serial_number, purchase_order_number, acquisition_date, warranty_expiry, 
            vendor, cost, depreciation_method, depreciation_rate, depreciation_start_date, 
            life_expectancy_years, location_id, remarks, image_path, created_by, updated_by
        ) VALUES (
            :asset_id, :asset_tag, :asset_name, :asset_class, :model, :manufacturer,
            :serial_number, :purchase_order_number, :acquisition_date, :warranty_expiry,
            :vendor, :cost, :depreciation_method, :depreciation_rate, :depreciation_start_date,
            :life_expectancy_years, :location_id, :remarks, :image_path, :created_by, :updated_by
        )";
        
        $stmt = $db->pdo->prepare($sql);
        $result = $stmt->execute([
            ':asset_id' => $asset_id,
            ':asset_tag' => $asset_tag,
            ':asset_name' => $asset_name,
            ':asset_class' => $asset_class,
            ':model' => $model,
            ':manufacturer' => $manufacturer,
            ':serial_number' => $serial_number,
            ':purchase_order_number' => $purchase_order_number,
            ':acquisition_date' => $acquisition_date,
            ':warranty_expiry' => $warranty_expiry,
            ':vendor' => $vendor,
            ':cost' => $cost,
            ':depreciation_method' => $depreciation_method,
            ':depreciation_rate' => $depreciation_rate,
            ':depreciation_start_date' => $depreciation_start_date,
            ':life_expectancy_years' => $life_expectancy_years,
            ':location_id' => $location_id,
            ':remarks' => $remarks,
            ':image_path' => $uploaded_image,
            ':created_by' => $current_user,
            ':updated_by' => $current_user
        ]);
        
        if ($result) {
            // Record in asset history
            $history_id = 'HIST' . date('YmdHis') . rand(100, 999);
            $change_summary = "New asset registered: $asset_name" . ($uploaded_image ? " with image" : "");
            
            $history_sql = "INSERT INTO asset_history (
                asset_history_id, asset_id, changed_by_user_id, change_type, change_summary
            ) VALUES (
                :history_id, :asset_id, :user_id, :change_type, :summary
            )";
            
            $history_stmt = $db->pdo->prepare($history_sql);
            $history_stmt->execute([
                ':history_id' => $history_id,
                ':asset_id' => $asset_id,
                ':user_id' => $current_user,
                ':change_type' => 'CREATE',
                ':summary' => $change_summary
            ]);
            
            $message = "Asset registered successfully! Asset ID: $asset_id";
        } else {
            throw new Exception("Error registering asset");
        }
        
    } catch (Exception $e) {
        $error = $e->getMessage();
        // Clean up uploaded file if there was an error
        if (isset($destination) && file_exists($destination)) {
            unlink($destination);
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register Assets - Warehouse Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        :root {
            --primary-color: #4361ee;
            --secondary-color: #3a0ca3;
            --accent-color: #4cc9f0;
            --light-bg: #f8f9ff;
            --border-color: #e3e8ff;
        }
        
        body {
            background-color: #f5f7ff;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
            transition: all 0.3s ease;
        }
        
        @media (max-width: 992px) {
            .main-content {
                margin-left: 0;
                padding: 15px;
            }
        }
        
        .page-header {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border-radius: 12px;
            padding: 25px 30px;
            margin-bottom: 30px;
            color: white;
            box-shadow: 0 4px 15px rgba(67, 97, 238, 0.15);
        }
        
        .page-header h1 {
            font-weight: 600;
            margin-bottom: 5px;
        }
        
        .page-header p {
            opacity: 0.9;
            margin-bottom: 0;
        }
        
        .form-card {
            background: white;
            border-radius: 12px;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.05);
            border: none;
            overflow: hidden;
            margin-bottom: 30px;
        }
        
        .form-card-header {
            background-color: #f9faff;
            padding: 20px 25px;
            border-bottom: 1px solid var(--border-color);
        }
        
        .form-card-header h5 {
            color: var(--primary-color);
            font-weight: 600;
            margin: 0;
            display: flex;
            align-items: center;
        }
        
        .form-card-header h5 i {
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .form-card-body {
            padding: 30px;
        }
        
        @media (max-width: 768px) {
            .form-card-body {
                padding: 20px;
            }
        }
        
        .form-section {
            margin-bottom: 35px;
            position: relative;
        }
        
        .section-title {
            display: flex;
            align-items: center;
            margin-bottom: 20px;
            padding-bottom: 12px;
            border-bottom: 2px solid #f0f3ff;
            color: var(--secondary-color);
            font-weight: 600;
            font-size: 1.1rem;
        }
        
        .section-title i {
            margin-right: 10px;
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            font-size: 1.2em;
        }
        
        .form-label {
            font-weight: 500;
            color: #444;
            margin-bottom: 8px;
            font-size: 0.95rem;
        }
        
        .required:after {
            content: " *";
            color: #ff4757;
        }
        
        .form-control, .form-select {
            border: 1px solid #e0e7ff;
            border-radius: 8px;
            padding: 10px 15px;
            font-size: 0.95rem;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            border-color: var(--accent-color);
            box-shadow: 0 0 0 0.2rem rgba(76, 201, 240, 0.2);
        }
        
        .image-upload-area {
            border: 2px dashed #c8d4ff;
            border-radius: 12px;
            padding: 25px;
            text-align: center;
            background-color: #f9fbff;
            cursor: pointer;
            transition: all 0.3s ease;
            min-height: 200px;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
        }
        
        .image-upload-area:hover {
            border-color: var(--primary-color);
            background-color: #f0f5ff;
        }
        
        .image-upload-icon {
            font-size: 3rem;
            color: var(--primary-color);
            margin-bottom: 15px;
            opacity: 0.7;
        }
        
        .image-preview-container {
            width: 100%;
            max-width: 300px;
            margin: 0 auto 20px;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 5px 15px rgba(0, 0, 0, 0.08);
        }
        
        .image-preview {
            width: 100%;
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background-color: #f8faff;
        }
        
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        
        .btn-primary-custom {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            border: none;
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 500;
            color: white;
            transition: all 0.3s ease;
        }
        
        .btn-primary-custom:hover {
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(67, 97, 238, 0.3);
            color: white;
        }
        
        .btn-outline-custom {
            border: 2px solid var(--primary-color);
            color: var(--primary-color);
            border-radius: 8px;
            padding: 10px 25px;
            font-weight: 500;
            transition: all 0.3s ease;
        }
        
        .btn-outline-custom:hover {
            background-color: var(--primary-color);
            color: white;
        }
        
        .alert-custom {
            border-radius: 10px;
            border: none;
            box-shadow: 0 4px 10px rgba(0, 0, 0, 0.05);
        }
        
        .info-text {
            font-size: 0.85rem;
            color: #6c757d;
            margin-top: 5px;
        }
        
        .form-row-spacing {
            margin-bottom: 20px;
        }
        
        .progress-indicator {
            display: flex;
            justify-content: space-between;
            margin-bottom: 40px;
            position: relative;
        }
        
        .progress-indicator:before {
            content: '';
            position: absolute;
            top: 20px;
            left: 0;
            right: 0;
            height: 2px;
            background-color: #e0e7ff;
            z-index: 1;
        }
        
        .progress-step {
            display: flex;
            flex-direction: column;
            align-items: center;
            position: relative;
            z-index: 2;
        }
        
        .step-icon {
            width: 40px;
            height: 40px;
            border-radius: 50%;
            background-color: white;
            border: 2px solid #e0e7ff;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-bottom: 10px;
            color: #adb5bd;
            font-weight: 600;
        }
        
        .step-label {
            font-size: 0.85rem;
            color: #6c757d;
            font-weight: 500;
        }
        
        .progress-step.active .step-icon {
            background-color: var(--primary-color);
            border-color: var(--primary-color);
            color: white;
        }
        
        .progress-step.active .step-label {
            color: var(--primary-color);
            font-weight: 600;
        }
        
        .section-divider {
            height: 1px;
            background: linear-gradient(to right, transparent, #e0e7ff, transparent);
            margin: 30px 0;
        }
        
        .floating-action-btn {
            position: fixed;
            bottom: 30px;
            right: 30px;
            z-index: 100;
            box-shadow: 0 5px 20px rgba(0, 0, 0, 0.15);
            border-radius: 50%;
            width: 60px;
            height: 60px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .floating-action-btn {
                bottom: 20px;
                right: 20px;
                width: 50px;
                height: 50px;
                font-size: 1.2rem;
            }
        }
        
        /* Custom animation for form sections */
        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(20px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .form-section {
            animation: fadeInUp 0.5s ease-out;
        }
        
        /* Hover effect for form controls */
        .form-control:hover, .form-select:hover {
            border-color: #a5b4fc;
        }
        
        /* Custom file input styling */
        .custom-file-input {
            position: absolute;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-info-box {
            background-color: #f0f5ff;
            border-radius: 8px;
            padding: 10px 15px;
            margin-top: 10px;
            font-size: 0.85rem;
            border-left: 3px solid var(--primary-color);
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'whsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 main-content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="d-flex justify-content-between align-items-center flex-wrap">
                        <div>
                            <h1><i class="bi bi-plus-circle me-2"></i>Register New Asset</h1>
                            <p>Add new assets to the inventory management system</p>
                        </div>
                        <div class="mt-2 mt-md-0">
                            <a href="list_assets.php" class="btn btn-light btn-sm">
                                <i class="bi bi-list me-1"></i> View All Assets
                            </a>
                            <a href="dashboard.php" class="btn btn-outline-light btn-sm ms-2">
                                <i class="bi bi-grid me-1"></i> Dashboard
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Progress Indicator -->
                <div class="progress-indicator">
                    <div class="progress-step active">
                        <div class="step-icon">1</div>
                        <div class="step-label">Basic Info</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-icon">2</div>
                        <div class="step-label">Acquisition</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-icon">3</div>
                        <div class="step-label">Financial</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-icon">4</div>
                        <div class="step-label">Location</div>
                    </div>
                    <div class="progress-step">
                        <div class="step-icon">5</div>
                        <div class="step-label">Review</div>
                    </div>
                </div>

                <!-- Success/Error Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-custom alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-check-circle-fill me-3 fs-4"></i>
                            <div>
                                <h6 class="mb-1">Success!</h6>
                                <?php echo htmlspecialchars($message); ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-custom alert-dismissible fade show" role="alert">
                        <div class="d-flex align-items-center">
                            <i class="bi bi-exclamation-triangle-fill me-3 fs-4"></i>
                            <div>
                                <h6 class="mb-1">Error!</h6>
                                <?php echo htmlspecialchars($error); ?>
                            </div>
                        </div>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Main Form Card -->
                <div class="form-card">
                    <div class="form-card-header">
                        <h5><i class="bi bi-box-seam"></i> Asset Registration Form</h5>
                    </div>
                    <div class="form-card-body">
                        <form method="POST" action="" id="assetForm" enctype="multipart/form-data">
                            <!-- Basic Information Section -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-info-circle"></i> Basic Information
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="asset_name" class="form-label required">Asset Name</label>
                                        <input type="text" class="form-control" id="asset_name" name="asset_name" required
                                               placeholder="Enter asset name">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="asset_tag" class="form-label">Asset Tag</label>
                                        <input type="text" class="form-control" id="asset_tag" name="asset_tag"
                                               placeholder="Optional asset tag">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="asset_class" class="form-label required">Asset Class</label>
                                        <select class="form-select" id="asset_class" name="asset_class" required>
                                            <option value="">Select Asset Class</option>
                                            <?php foreach ($asset_classes as $class): ?>
                                                <option value="<?php echo htmlspecialchars($class['class_id']); ?>">
                                                    <?php echo htmlspecialchars($class['class_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="model" class="form-label">Model</label>
                                        <input type="text" class="form-control" id="model" name="model"
                                               placeholder="Enter model number">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="manufacturer" class="form-label">Manufacturer</label>
                                        <input type="text" class="form-control" id="manufacturer" name="manufacturer"
                                               placeholder="Enter manufacturer name">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="serial_number" class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" id="serial_number" name="serial_number"
                                               placeholder="Enter serial number">
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="section-divider"></div>

                            <!-- Asset Image Section -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-image"></i> Asset Image
                                </div>
                                <div class="row">
                                    <div class="col-lg-4 mb-4">
                                        <div class="image-preview-container">
                                            <div class="image-preview" id="imagePreview">
                                                <div class="text-center p-4">
                                                    <i class="bi bi-image text-muted" style="font-size: 3rem;"></i>
                                                    <p class="mt-2 text-muted">No image selected</p>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="col-lg-8">
                                        <div class="image-upload-area" onclick="document.getElementById('asset_image').click()">
                                            <div class="image-upload-icon">
                                                <i class="bi bi-cloud-arrow-up"></i>
                                            </div>
                                            <h5>Upload Asset Image</h5>
                                            <p class="text-muted mb-3">Click here or drag and drop to upload</p>
                                            <input type="file" class="custom-file-input" id="asset_image" name="asset_image" 
                                                   accept="image/jpeg,image/png,image/gif,image/webp" 
                                                   onchange="previewImage(this)">
                                            <div class="file-info-box d-none" id="fileInfo">
                                                <div class="d-flex justify-content-between align-items-center">
                                                    <span id="fileName"></span>
                                                    <span id="fileSize" class="text-muted"></span>
                                                </div>
                                            </div>
                                        </div>
                                        <div class="info-text mt-3">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF, WEBP
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="section-divider"></div>

                            <!-- Acquisition Information Section -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-cart-check"></i> Acquisition Information
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="purchase_order_number" class="form-label">Purchase Order Number</label>
                                        <input type="text" class="form-control" id="purchase_order_number" name="purchase_order_number"
                                               placeholder="Enter PO number">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="acquisition_date" class="form-label">Acquisition Date</label>
                                        <input type="date" class="form-control" id="acquisition_date" name="acquisition_date">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="warranty_expiry" class="form-label">Warranty Expiry Date</label>
                                        <input type="date" class="form-control" id="warranty_expiry" name="warranty_expiry">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="vendor" class="form-label">Vendor</label>
                                        <input type="text" class="form-control" id="vendor" name="vendor"
                                               placeholder="Enter vendor name">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="cost" class="form-label">Cost (RM)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" class="form-control" id="cost" name="cost" step="0.01" min="0"
                                                   placeholder="0.00">
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="section-divider"></div>

                            <!-- Depreciation Information Section -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-graph-down"></i> Depreciation Information
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="depreciation_method" class="form-label">Depreciation Method</label>
                                        <select class="form-select" id="depreciation_method" name="depreciation_method">
                                            <option value="">Select Depreciation Method</option>
                                            <option value="Straight Line">Straight Line</option>
                                            <option value="Declining Balance">Declining Balance</option>
                                            <option value="None">None</option>
                                        </select>
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="depreciation_rate" class="form-label">Depreciation Rate (%)</label>
                                        <div class="input-group">
                                            <input type="number" class="form-control" id="depreciation_rate" name="depreciation_rate" 
                                                   step="0.01" min="0" max="100" placeholder="0.00">
                                            <span class="input-group-text">%</span>
                                        </div>
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="depreciation_start_date" class="form-label">Depreciation Start Date</label>
                                        <input type="date" class="form-control" id="depreciation_start_date" name="depreciation_start_date">
                                    </div>
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="life_expectancy_years" class="form-label">Life Expectancy (Years)</label>
                                        <input type="number" class="form-control" id="life_expectancy_years" name="life_expectancy_years" 
                                               min="0" placeholder="Enter years">
                                    </div>
                                </div>
                            </div>

                            <!-- Divider -->
                            <div class="section-divider"></div>

                            <!-- Location Information Section -->
                            <div class="form-section">
                                <div class="section-title">
                                    <i class="bi bi-geo-alt"></i> Location Information
                                </div>
                                <div class="row">
                                    <div class="col-md-6 form-row-spacing">
                                        <label for="location_id" class="form-label">Storage Location</label>
                                        <select class="form-select" id="location_id" name="location_id">
                                            <option value="">Select Location</option>
                                            <?php foreach ($locations as $location): ?>
                                                <option value="<?php echo htmlspecialchars($location['location_id']); ?>">
                                                    <?php echo htmlspecialchars($location['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-12 form-row-spacing">
                                        <label for="remarks" class="form-label">Remarks / Additional Notes</label>
                                        <textarea class="form-control" id="remarks" name="remarks" rows="4"
                                                  placeholder="Enter any additional notes or remarks about this asset"></textarea>
                                        <div class="info-text">
                                            <i class="bi bi-info-circle me-1"></i>
                                            Optional notes for internal reference
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <!-- Form Action Buttons -->
                            <div class="d-flex justify-content-between align-items-center mt-5 pt-4 border-top">
                                <button type="reset" class="btn btn-outline-custom" onclick="resetImagePreview()">
                                    <i class="bi bi-arrow-clockwise me-2"></i> Reset Form
                                </button>
                                <div>
                                    <a href="dashboard.php" class="btn btn-outline-secondary me-3">
                                        <i class="bi bi-x-circle me-2"></i> Cancel
                                    </a>
                                    <button type="submit" class="btn btn-primary-custom">
                                        <i class="bi bi-check-circle me-2"></i> Register Asset
                                    </button>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Quick Actions -->
    <a href="list_assets.php" class="btn btn-primary-custom floating-action-btn" title="View All Assets">
        <i class="bi bi-list"></i>
    </a>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    
    <!-- Keep the original JavaScript functionality unchanged -->
    <script>
        // Form validation and auto-fill date suggestions
        document.addEventListener('DOMContentLoaded', function() {
            // Set today's date for acquisition date
            const today = new Date().toISOString().split('T')[0];
            document.getElementById('acquisition_date').value = today;
            
            // Auto-suggest warranty expiry (1 year from today)
            const nextYear = new Date();
            nextYear.setFullYear(nextYear.getFullYear() + 1);
            document.getElementById('warranty_expiry').value = nextYear.toISOString().split('T')[0];
            
            // Auto-suggest depreciation start date (tomorrow)
            const tomorrow = new Date();
            tomorrow.setDate(tomorrow.getDate() + 1);
            document.getElementById('depreciation_start_date').value = tomorrow.toISOString().split('T')[0];
            
            // Form validation
            document.getElementById('assetForm').addEventListener('submit', function(e) {
                const assetName = document.getElementById('asset_name').value.trim();
                const assetClass = document.getElementById('asset_class').value;
                
                if (!assetName || !assetClass) {
                    e.preventDefault();
                    alert('Please fill in all required fields (marked with *)');
                    return false;
                }
                
                // File size validation
                const fileInput = document.getElementById('asset_image');
                if (fileInput.files.length > 0) {
                    const fileSize = fileInput.files[0].size;
                    const maxSize = 5 * 1024 * 1024; // 5MB
                    
                    if (fileSize > maxSize) {
                        e.preventDefault();
                        alert('File size must be less than 5MB.');
                        return false;
                    }
                }
                
                // Confirm submission
                if (!confirm('Are you sure you want to register this asset?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });

        // Image preview function - enhanced with file info display
        function previewImage(input) {
            const preview = document.getElementById('imagePreview');
            const fileInfo = document.getElementById('fileInfo');
            const fileName = document.getElementById('fileName');
            const fileSize = document.getElementById('fileSize');
            
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const file = input.files[0];
                
                // File size validation
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB.');
                    input.value = '';
                    preview.innerHTML = '<div class="text-center p-4"><i class="bi bi-image text-muted" style="font-size: 3rem;"></i><p class="mt-2 text-muted">No image selected</p></div>';
                    fileInfo.classList.add('d-none');
                    return;
                }
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                
                reader.readAsDataURL(file);
                
                // Show file info
                const sizeInMB = (file.size / (1024 * 1024)).toFixed(2);
                fileName.textContent = file.name.length > 25 ? file.name.substring(0, 25) + '...' : file.name;
                fileSize.textContent = sizeInMB + ' MB';
                fileInfo.classList.remove('d-none');
            } else {
                preview.innerHTML = '<div class="text-center p-4"><i class="bi bi-image text-muted" style="font-size: 3rem;"></i><p class="mt-2 text-muted">No image selected</p></div>';
                fileInfo.classList.add('d-none');
            }
        }

        // Reset image preview
        function resetImagePreview() {
            document.getElementById('imagePreview').innerHTML = '<div class="text-center p-4"><i class="bi bi-image text-muted" style="font-size: 3rem;"></i><p class="mt-2 text-muted">No image selected</p></div>';
            document.getElementById('fileInfo').classList.add('d-none');
        }
        
        // Add visual feedback for required fields
        document.querySelectorAll('.required').forEach(label => {
            const inputId = label.getAttribute('for');
            if (inputId) {
                const input = document.getElementById(inputId);
                if (input) {
                    input.addEventListener('blur', function() {
                        if (!this.value.trim()) {
                            this.classList.add('is-invalid');
                        } else {
                            this.classList.remove('is-invalid');
                        }
                    });
                }
            }
        });
    </script>
</body>
</html>
<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php';

check_auth(['logistic_coordinator','it_operation']);

$asset_id = isset($_GET['id']) ? $db->sanitize($_GET['id']) : '';

if (empty($asset_id)) {
    header('Location: list_assets.php');
    exit();
}

// Fetch existing asset data
$sql = "SELECT * FROM assets WHERE asset_id = :asset_id";
$stmt = $db->pdo->prepare($sql);
$stmt->execute([':asset_id' => $asset_id]);
$asset = $stmt->fetch(PDO::FETCH_ASSOC);

if (!$asset) {
    header('Location: list_assets.php?error=Asset not found');
    exit();
}

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

$message = '';
$error = '';
$uploaded_image = $asset['image_path'] ?? null;

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        // Handle image upload if new image provided
        if (isset($_FILES['asset_image']) && $_FILES['asset_image']['error'] === UPLOAD_ERR_OK) {
            $allowed_extensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
            $max_file_size = 5 * 1024 * 1024; // 5MB
            $upload_dir = '../../uploads/assets/';
            
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            $file_name = $_FILES['asset_image']['name'];
            $file_tmp = $_FILES['asset_image']['tmp_name'];
            $file_size = $_FILES['asset_image']['size'];
            $file_ext = strtolower(pathinfo($file_name, PATHINFO_EXTENSION));
            
            if (!in_array($file_ext, $allowed_extensions)) {
                throw new Exception('Invalid file type. Only JPG, JPEG, PNG, GIF, and WEBP are allowed.');
            }
            
            if ($file_size > $max_file_size) {
                throw new Exception('File size must be less than 5MB.');
            }
            
            // Generate unique filename
            $unique_id = uniqid('asset_', true);
            $new_filename = $unique_id . '.' . $file_ext;
            $destination = $upload_dir . $new_filename;
            
            if (move_uploaded_file($file_tmp, $destination)) {
                // Delete old image if exists
                if (!empty($asset['image_path'])) {
                    $old_image_path = '../../' . $asset['image_path'];
                    if (file_exists($old_image_path)) {
                        unlink($old_image_path);
                    }
                }
                $uploaded_image = 'uploads/assets/' . $new_filename;
            } else {
                throw new Exception('Failed to upload image.');
            }
        }
        
        // Prepare updated data
        $update_data = [
            ':asset_tag' => $db->sanitize($_POST['asset_tag'] ?? ''),
            ':asset_name' => $db->sanitize($_POST['asset_name'] ?? ''),
            ':asset_class' => $db->sanitize($_POST['asset_class'] ?? ''),
            ':model' => $db->sanitize($_POST['model'] ?? ''),
            ':manufacturer' => $db->sanitize($_POST['manufacturer'] ?? ''),
            ':serial_number' => $db->sanitize($_POST['serial_number'] ?? ''),
            ':purchase_order_number' => $db->sanitize($_POST['purchase_order_number'] ?? ''),
            ':acquisition_date' => $db->sanitize($_POST['acquisition_date'] ?? ''),
            ':warranty_expiry' => $db->sanitize($_POST['warranty_expiry'] ?? ''),
            ':vendor' => $db->sanitize($_POST['vendor'] ?? ''),
            ':cost' => floatval($_POST['cost'] ?? 0),
            ':depreciation_method' => $db->sanitize($_POST['depreciation_method'] ?? ''),
            ':depreciation_rate' => floatval($_POST['depreciation_rate'] ?? 0),
            ':depreciation_start_date' => $db->sanitize($_POST['depreciation_start_date'] ?? ''),
            ':life_expectancy_years' => intval($_POST['life_expectancy_years'] ?? 0),
            ':location_id' => $db->sanitize($_POST['location_id'] ?? ''),
            ':remarks' => $db->sanitize($_POST['remarks'] ?? ''),
            ':image_path' => $uploaded_image,
            ':updated_by' => isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'SYSTEM',
            ':asset_id' => $asset_id
        ];
        
        // Build dynamic SQL based on columns
        $sql = "UPDATE assets SET 
            asset_tag = :asset_tag,
            asset_name = :asset_name,
            asset_class = :asset_class,
            model = :model,
            manufacturer = :manufacturer,
            serial_number = :serial_number,
            purchase_order_number = :purchase_order_number,
            acquisition_date = :acquisition_date,
            warranty_expiry = :warranty_expiry,
            vendor = :vendor,
            cost = :cost,
            depreciation_method = :depreciation_method,
            depreciation_rate = :depreciation_rate,
            depreciation_start_date = :depreciation_start_date,
            life_expectancy_years = :life_expectancy_years,
            location_id = :location_id,
            remarks = :remarks,
            image_path = :image_path,
            updated_by = :updated_by,
            updated_at = NOW()
        WHERE asset_id = :asset_id";
        
        $stmt = $db->pdo->prepare($sql);
        $result = $stmt->execute($update_data);
        
        if ($result) {
            // Record in asset history
            $history_id = 'HIST' . date('YmdHis') . rand(100, 999);
            $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'SYSTEM';
            $change_summary = "Asset updated: " . $update_data[':asset_name'];
            
            try {
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
                    ':change_type' => 'UPDATE',
                    ':summary' => $change_summary
                ]);
            } catch (Exception $e) {
                // History table might not exist
                error_log("History record failed: " . $e->getMessage());
            }
            
            $message = "Asset updated successfully!";
            // Refresh asset data
            $stmt = $db->pdo->prepare("SELECT * FROM assets WHERE asset_id = :asset_id");
            $stmt->execute([':asset_id' => $asset_id]);
            $asset = $stmt->fetch(PDO::FETCH_ASSOC);
        } else {
            throw new Exception("Failed to update asset");
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
    <title>Edit Asset - <?php echo htmlspecialchars($asset['asset_name']); ?></title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background-color: #f8f9fa;
        }
        .main-content {
            margin-left: 280px;
            padding: 20px;
            min-height: 100vh;
        }
        @media (max-width: 768px) {
            .main-content {
                margin-left: 0;
            }
        }
        .card {
            border-radius: 10px;
            box-shadow: 0 4px 6px rgba(0,0,0,0.1);
            border: none;
        }
        .required:after {
            content: " *";
            color: red;
        }
        .form-section {
            background: #fff;
            border-radius: 8px;
            padding: 20px;
            margin-bottom: 20px;
            border-left: 4px solid #667eea;
        }
        .image-preview {
            width: 200px;
            height: 200px;
            border: 2px dashed #ccc;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
            background: #f8f9fa;
        }
        .image-preview img {
            max-width: 100%;
            max-height: 100%;
            object-fit: contain;
        }
        .current-image {
            max-width: 100%;
            border-radius: 5px;
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
                <!-- Header -->
                <div class="content-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h1 class="h2">
                                <i class="bi bi-pencil-square"></i> Edit Asset
                            </h1>
                            <p class="mb-0">
                                Asset ID: <strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong>
                            </p>
                        </div>
                        <div class="btn-group">
                            <a href="view_asset.php?id=<?php echo urlencode($asset_id); ?>" class="btn btn-outline-primary">
                                <i class="bi bi-eye"></i> View Details
                            </a>
                            <a href="list_assets.php" class="btn btn-outline-secondary">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Messages -->
                <?php if ($message): ?>
                    <div class="alert alert-success alert-dismissible fade show mt-3" role="alert">
                        <?php echo htmlspecialchars($message); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <?php if ($error): ?>
                    <div class="alert alert-danger alert-dismissible fade show mt-3" role="alert">
                        <?php echo htmlspecialchars($error); ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                    </div>
                <?php endif; ?>

                <!-- Edit Form -->
                <div class="card mt-4">
                    <div class="card-header bg-warning text-dark">
                        <h5 class="mb-0"><i class="bi bi-box-seam"></i> Edit Asset Information</h5>
                    </div>
                    <div class="card-body">
                        <form method="POST" action="" id="editAssetForm" enctype="multipart/form-data">
                            <div class="row">
                                <!-- Current Image Preview -->
                                <div class="form-section">
                                    <h6 class="mb-3 text-primary"><i class="bi bi-image"></i> Current Asset Image</h6>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <?php if (!empty($asset['image_path'])): ?>
                                                <div class="mb-3">
                                                    <p><strong>Current Image:</strong></p>
                                                    <img src="../../<?php echo htmlspecialchars($asset['image_path']); ?>" 
                                                         alt="Current Image" 
                                                         class="current-image mb-2">
                                                    <div class="form-check">
                                                        <input class="form-check-input" type="checkbox" 
                                                               name="remove_image" id="remove_image" value="1">
                                                        <label class="form-check-label" for="remove_image">
                                                            Remove current image
                                                        </label>
                                                    </div>
                                                </div>
                                            <?php else: ?>
                                                <div class="alert alert-info">
                                                    <i class="bi bi-info-circle"></i> No image currently set for this asset.
                                                </div>
                                            <?php endif; ?>
                                        </div>
                                        <div class="col-md-8">
                                            <label for="asset_image" class="form-label">Upload New Image</label>
                                            <div class="input-group mb-3">
                                                <input type="file" class="form-control" id="asset_image" 
                                                       name="asset_image" accept="image/jpeg,image/png,image/gif,image/webp"
                                                       onchange="previewImage(this)">
                                                <button class="btn btn-outline-secondary" type="button" 
                                                        onclick="document.getElementById('asset_image').value='';resetImagePreview()">
                                                    <i class="bi bi-x-circle"></i>
                                                </button>
                                            </div>
                                            <div id="newImagePreview" class="image-preview mb-3">
                                                <span class="text-muted">No new image selected</span>
                                            </div>
                                            <div class="form-text">
                                                <small>
                                                    <i class="bi bi-info-circle"></i> 
                                                    Maximum file size: 5MB. Allowed formats: JPG, PNG, GIF, WEBP
                                                </small>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <!-- Basic Information -->
                                <div class="form-section">
                                    <h6 class="mb-3 text-primary"><i class="bi bi-info-circle"></i> Basic Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="asset_name" class="form-label required">Asset Name</label>
                                            <input type="text" class="form-control" id="asset_name" 
                                                   name="asset_name" required
                                                   value="<?php echo htmlspecialchars($asset['asset_name']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="asset_tag" class="form-label">Asset Tag</label>
                                            <input type="text" class="form-control" id="asset_tag" 
                                                   name="asset_tag"
                                                   value="<?php echo htmlspecialchars($asset['asset_tag']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="asset_class" class="form-label required">Asset Class</label>
                                            <select class="form-select" id="asset_class" name="asset_class" required>
                                                <option value="">Select Class</option>
                                                <?php foreach ($asset_classes as $class): ?>
                                                    <option value="<?php echo htmlspecialchars($class['class_id']); ?>"
                                                        <?php echo ($asset['asset_class'] == $class['class_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="model" class="form-label">Model</label>
                                            <input type="text" class="form-control" id="model" 
                                                   name="model"
                                                   value="<?php echo htmlspecialchars($asset['model']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="manufacturer" class="form-label">Manufacturer</label>
                                            <input type="text" class="form-control" id="manufacturer" 
                                                   name="manufacturer"
                                                   value="<?php echo htmlspecialchars($asset['manufacturer']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="serial_number" class="form-label">Serial Number</label>
                                            <input type="text" class="form-control" id="serial_number" 
                                                   name="serial_number"
                                                   value="<?php echo htmlspecialchars($asset['serial_number']); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Acquisition Information -->
                                <div class="form-section">
                                    <h6 class="mb-3 text-primary"><i class="bi bi-cart-check"></i> Acquisition Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="purchase_order_number" class="form-label">PO Number</label>
                                            <input type="text" class="form-control" id="purchase_order_number" 
                                                   name="purchase_order_number"
                                                   value="<?php echo htmlspecialchars($asset['purchase_order_number']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="acquisition_date" class="form-label">Acquisition Date</label>
                                            <input type="date" class="form-control" id="acquisition_date" 
                                                   name="acquisition_date"
                                                   value="<?php echo htmlspecialchars($asset['acquisition_date']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="warranty_expiry" class="form-label">Warranty Expiry</label>
                                            <input type="date" class="form-control" id="warranty_expiry" 
                                                   name="warranty_expiry"
                                                   value="<?php echo htmlspecialchars($asset['warranty_expiry']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="vendor" class="form-label">Vendor</label>
                                            <input type="text" class="form-control" id="vendor" 
                                                   name="vendor"
                                                   value="<?php echo htmlspecialchars($asset['vendor']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="cost" class="form-label">Cost (RM)</label>
                                            <input type="number" class="form-control" id="cost" 
                                                   name="cost" step="0.01" min="0"
                                                   value="<?php echo htmlspecialchars($asset['cost']); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Depreciation Information -->
                                <div class="form-section">
                                    <h6 class="mb-3 text-primary"><i class="bi bi-graph-down"></i> Depreciation Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="depreciation_method" class="form-label">Depreciation Method</label>
                                            <select class="form-select" id="depreciation_method" name="depreciation_method">
                                                <option value="">Select Method</option>
                                                <option value="Straight Line" <?php echo ($asset['depreciation_method'] == 'Straight Line') ? 'selected' : ''; ?>>Straight Line</option>
                                                <option value="Declining Balance" <?php echo ($asset['depreciation_method'] == 'Declining Balance') ? 'selected' : ''; ?>>Declining Balance</option>
                                                <option value="None" <?php echo ($asset['depreciation_method'] == 'None') ? 'selected' : ''; ?>>None</option>
                                            </select>
                                        </div>
                                        <div class="col-md-6">
                                            <label for="depreciation_rate" class="form-label">Depreciation Rate (%)</label>
                                            <input type="number" class="form-control" id="depreciation_rate" 
                                                   name="depreciation_rate" step="0.01" min="0" max="100"
                                                   value="<?php echo htmlspecialchars($asset['depreciation_rate']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="depreciation_start_date" class="form-label">Depreciation Start Date</label>
                                            <input type="date" class="form-control" id="depreciation_start_date" 
                                                   name="depreciation_start_date"
                                                   value="<?php echo htmlspecialchars($asset['depreciation_start_date']); ?>">
                                        </div>
                                        <div class="col-md-6">
                                            <label for="life_expectancy_years" class="form-label">Life Expectancy (Years)</label>
                                            <input type="number" class="form-control" id="life_expectancy_years" 
                                                   name="life_expectancy_years" min="0"
                                                   value="<?php echo htmlspecialchars($asset['life_expectancy_years']); ?>">
                                        </div>
                                    </div>
                                </div>

                                <!-- Location Information -->
                                <div class="form-section">
                                    <h6 class="mb-3 text-primary"><i class="bi bi-geo-alt"></i> Location Information</h6>
                                    <div class="row g-3">
                                        <div class="col-md-6">
                                            <label for="location_id" class="form-label">Storage Location</label>
                                            <select class="form-select" id="location_id" name="location_id">
                                                <option value="">Select Location</option>
                                                <?php foreach ($locations as $location): ?>
                                                    <option value="<?php echo htmlspecialchars($location['location_id']); ?>"
                                                        <?php echo ($asset['location_id'] == $location['location_id']) ? 'selected' : ''; ?>>
                                                        <?php echo htmlspecialchars($location['name']); ?>
                                                    </option>
                                                <?php endforeach; ?>
                                            </select>
                                        </div>
                                        <div class="col-md-12">
                                            <label for="remarks" class="form-label">Remarks / Notes</label>
                                            <textarea class="form-control" id="remarks" name="remarks" rows="3"><?php echo htmlspecialchars($asset['remarks']); ?></textarea>
                                        </div>
                                    </div>
                                </div>

                                <!-- Form Buttons -->
                                <div class="col-12 mt-4">
                                    <div class="d-flex justify-content-between">
                                        <button type="reset" class="btn btn-secondary" onclick="resetForm()">
                                            <i class="bi bi-arrow-clockwise"></i> Reset Changes
                                        </button>
                                        <div>
                                            <a href="view_asset.php?id=<?php echo urlencode($asset_id); ?>" 
                                               class="btn btn-outline-danger me-2">
                                                <i class="bi bi-x-circle"></i> Cancel
                                            </a>
                                            <button type="submit" class="btn btn-success">
                                                <i class="bi bi-check-circle"></i> Update Asset
                                            </button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </form>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Image preview for new upload
        function previewImage(input) {
            const preview = document.getElementById('newImagePreview');
            if (input.files && input.files[0]) {
                const reader = new FileReader();
                const file = input.files[0];
                
                // File size validation
                const maxSize = 5 * 1024 * 1024; // 5MB
                if (file.size > maxSize) {
                    alert('File size must be less than 5MB.');
                    input.value = '';
                    preview.innerHTML = '<span class="text-muted">No new image selected</span>';
                    return;
                }
                
                reader.onload = function(e) {
                    preview.innerHTML = `<img src="${e.target.result}" alt="Preview">`;
                };
                
                reader.readAsDataURL(file);
            } else {
                preview.innerHTML = '<span class="text-muted">No new image selected</span>';
            }
        }
        
        // Reset image preview
        function resetImagePreview() {
            document.getElementById('newImagePreview').innerHTML = '<span class="text-muted">No new image selected</span>';
        }
        
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            document.getElementById('editAssetForm').addEventListener('submit', function(e) {
                const assetName = document.getElementById('asset_name').value.trim();
                const assetClass = document.getElementById('asset_class').value;
                
                if (!assetName || !assetClass) {
                    e.preventDefault();
                    alert('Please fill in all required fields (marked with *)');
                    return false;
                }
                
                // File size validation for new image
                const fileInput = document.getElementById('asset_image');
                if (fileInput.files.length > 0) {
                    const fileSize = fileInput.files[0].size;
                    const maxSize = 5 * 1024 * 1024;
                    
                    if (fileSize > maxSize) {
                        e.preventDefault();
                        alert('File size must be less than 5MB.');
                        return false;
                    }
                }
                
                // Confirm update
                if (!confirm('Are you sure you want to update this asset?')) {
                    e.preventDefault();
                    return false;
                }
            });
        });
        
        // Reset form to original values
        function resetForm() {
            if (confirm('Reset all changes to original values?')) {
                location.reload();
            }
        }
    </script>
</body>
</html>
<?php
require_once '../../includes/check_auth.php';
check_auth(['admin', 'it_operation']);

require_once '../../includes/db.php';
$conn = $db->conn;

$message      = '';
$message_type = '';

// ── Helper: save uploaded images ─────────────────────────────────────────
function saveAssetImages($conn, $asset_id) {
    if (!isset($_FILES['asset_images']) || empty($_FILES['asset_images']['name'][0])) return;
    $target_dir = "../../uploads/assets/";
    if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
    $files      = $_FILES['asset_images'];
    $is_primary = true;
    for ($i = 0; $i < count($files['name']); $i++) {
        if ($files['error'][$i] !== 0) continue;
        $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
        if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
        $image_name  = $asset_id . '_' . uniqid() . '.' . $ext;
        $target_file = $target_dir . $image_name;
        if (move_uploaded_file($files['tmp_name'][$i], $target_file)) {
            $primary_flag = $is_primary ? 1 : 0;
            $img_stmt = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, is_primary) VALUES (?, ?, ?)");
            $img_stmt->bind_param("ssi", $asset_id, $target_file, $primary_flag);
            $img_stmt->execute();
            $is_primary = false;
        }
    }
}

// ── Create / Update ───────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['action'])) {

    $asset_id            = $_POST['asset_id'] ?? '';
    // FIX 1: auto-generate tag if blank to avoid UNIQUE constraint duplicate error
    $asset_tag           = !empty($_POST['asset_tag'])
                           ? $_POST['asset_tag']
                           : 'TAG-' . date('Ymd') . '-' . rand(100, 999);
    $asset_name          = $_POST['asset_name'] ?? '';
    $asset_class         = $_POST['asset_class'] ?? '';
    $model               = $_POST['model'] ?? '';
    $manufacturer        = $_POST['manufacturer'] ?? '';
    $serial_number       = $_POST['serial_number'] ?? '';
    $purchase_order_number = $_POST['purchase_order_number'] ?? '';
    $acquisition_date    = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
    $warranty_expiry     = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $vendor              = $_POST['vendor'] ?? '';
    $cost                = !empty($_POST['cost'])             ? $_POST['cost']             : null;
    $asset_status        = $_POST['asset_status'] ?? 'In Stock';
    // FIX 2: NULL for FK fields when blank (prevents FK constraint crash)
    $location_id         = !empty($_POST['location_id'])         ? $_POST['location_id']         : null;
    $owner_department_id = !empty($_POST['owner_department_id']) ? $_POST['owner_department_id'] : null;
    $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? $_POST['assigned_to_user_id'] : null;
    $remarks             = $_POST['remarks'] ?? '';

    if ($_POST['action'] == 'create') {
        $asset_id = 'AST' . date('Ymd') . rand(100, 999);

        $sql  = "INSERT INTO assets (asset_id, asset_tag, asset_name, asset_class, model, manufacturer,
                 serial_number, purchase_order_number, acquisition_date, warranty_expiry, vendor, cost,
                 asset_status, location_id, owner_department_id, assigned_to_user_id, remarks, created_by)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("sssssssssssdssssss",
            $asset_id, $asset_tag, $asset_name, $asset_class, $model, $manufacturer,
            $serial_number, $purchase_order_number, $acquisition_date, $warranty_expiry,
            $vendor, $cost, $asset_status, $location_id, $owner_department_id,
            $assigned_to_user_id, $remarks, $_SESSION['user_id']
        );

        if ($stmt->execute()) {
            saveAssetImages($conn, $asset_id);
            $message      = "Asset created successfully!";
            $message_type = "success";
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, 'Created Asset', ?, ?)");
            $log_details = "Created asset: $asset_name";
            $log_stmt->bind_param("sss", $_SESSION['user_id'], $log_details, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        } else {
            $message      = "Error creating asset: " . $conn->error;
            $message_type = "danger";
        }

    } elseif ($_POST['action'] == 'update') {
        $sql  = "UPDATE assets SET asset_tag=?, asset_name=?, asset_class=?, model=?, manufacturer=?,
                 serial_number=?, purchase_order_number=?, acquisition_date=?, warranty_expiry=?,
                 vendor=?, cost=?, asset_status=?, location_id=?, owner_department_id=?,
                 assigned_to_user_id=?, remarks=?, updated_by=? WHERE asset_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssdsssssss",
            $asset_tag, $asset_name, $asset_class, $model, $manufacturer,
            $serial_number, $purchase_order_number, $acquisition_date, $warranty_expiry,
            $vendor, $cost, $asset_status, $location_id, $owner_department_id,
            $assigned_to_user_id, $remarks, $_SESSION['user_id'], $asset_id
        );

        if ($stmt->execute()) {
            saveAssetImages($conn, $asset_id);
            $message      = "Asset updated successfully!";
            $message_type = "success";
            $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, 'Updated Asset', ?, ?)");
            $log_details = "Updated asset: $asset_name";
            $log_stmt->bind_param("sss", $_SESSION['user_id'], $log_details, $_SERVER['REMOTE_ADDR']);
            $log_stmt->execute();
        } else {
            $message      = "Error updating asset: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// ── Delete ────────────────────────────────────────────────────────────────
if (isset($_GET['delete'])) {
    $asset_id = $_GET['delete'];

    // FIX 3: safe fetch — no crash if asset not found
    $name_stmt = $conn->prepare("SELECT asset_name FROM assets WHERE asset_id = ?");
    $name_stmt->bind_param("s", $asset_id);
    $name_stmt->execute();
    $name_row   = $name_stmt->get_result()->fetch_assoc();
    $asset_name = $name_row['asset_name'] ?? 'Unknown';

    // Delete physical image files
    $img_stmt = $conn->prepare("SELECT image_path FROM asset_images WHERE asset_id = ?");
    $img_stmt->bind_param("s", $asset_id);
    $img_stmt->execute();
    $img_res = $img_stmt->get_result();
    while ($img = $img_res->fetch_assoc()) {
        if (file_exists($img['image_path'])) unlink($img['image_path']);
    }

    // Delete image records from DB
    $di = $conn->prepare("DELETE FROM asset_images WHERE asset_id = ?");
    $di->bind_param("s", $asset_id);
    $di->execute();

    // Delete the asset itself
    $stmt = $conn->prepare("DELETE FROM assets WHERE asset_id = ?");
    $stmt->bind_param("s", $asset_id);

    if ($stmt->execute()) {
        $message      = "Asset deleted successfully!";
        $message_type = "success";
        $log_stmt = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, 'Deleted Asset', ?, ?)");
        $log_details = "Deleted asset: $asset_name";
        $log_stmt->bind_param("sss", $_SESSION['user_id'], $log_details, $_SERVER['REMOTE_ADDR']);
        $log_stmt->execute();
    } else {
        $message      = "Error deleting asset: " . $conn->error;
        $message_type = "danger";
    }
}

// ── Fetch assets (includes primary image) ────────────────────────────────
$assets = [];
$sql = "SELECT a.*, l.name as location_name, d.name as department_name,
        u.full_name as assigned_to, ac.class_name,
        (SELECT image_path FROM asset_images WHERE asset_id = a.asset_id AND is_primary = 1 LIMIT 1) as primary_image
        FROM assets a
        LEFT JOIN locations l      ON a.location_id         = l.location_id
        LEFT JOIN departments d    ON a.owner_department_id = d.department_id
        LEFT JOIN users u          ON a.assigned_to_user_id = u.user_id
        LEFT JOIN asset_classes ac ON a.asset_class         = ac.class_id
        ORDER BY a.created_at DESC";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $assets = $result->fetch_all(MYSQLI_ASSOC);
}

// ── Dropdowns ─────────────────────────────────────────────────────────────
$locations    = $conn->query("SELECT * FROM locations ORDER BY name");
$departments  = $conn->query("SELECT * FROM departments ORDER BY name");
$users        = $conn->query("SELECT user_id, full_name, username FROM users WHERE is_active = 1 ORDER BY full_name");
$asset_classes= $conn->query("SELECT * FROM asset_classes ORDER BY class_name");

// ── Stats ─────────────────────────────────────────────────────────────────
$total_assets      = count($assets);
$active_assets     = $in_stock_assets = $assigned_assets = $under_maintenance = 0;
foreach ($assets as $asset) {
    if      ($asset['asset_status'] == 'Active')            $active_assets++;
    elseif  ($asset['asset_status'] == 'In Stock')          $in_stock_assets++;
    elseif  ($asset['asset_status'] == 'Assigned')          $assigned_assets++;
    elseif  ($asset['asset_status'] == 'Under Maintenance') $under_maintenance++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Management</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .status-badge { padding:5px 10px; border-radius:20px; font-size:.85em; font-weight:500; }
        .status-active              { background:#d4edda; color:#155724; }
        .status-in-stock            { background:#cce5ff; color:#004085; }
        .status-assigned            { background:#fff3cd; color:#856404; }
        .status-under-maintenance   { background:#f8d7da; color:#721c24; }
        .status-disposed            { background:#e2e3e5; color:#383d41; }
        .filter-section { background:#f8f9fa; padding:15px; border-radius:10px; margin-bottom:20px; }
        .quick-stats    { background:linear-gradient(135deg,#667eea 0%,#764ba2 100%); color:white; padding:15px; border-radius:10px; margin-bottom:20px; }
        .action-btns .btn { padding:.25rem .5rem; font-size:.875rem; }

        /* Upload */
        .upload-area {
            border:2px dashed #dee2e6; border-radius:8px; padding:20px;
            text-align:center; background:#f8f9fa; cursor:pointer; transition:all .3s;
        }
        .upload-area:hover,.upload-area.dragover { border-color:#667eea; background:#fff; }
        .upload-area i { font-size:2rem; color:#6c757d; }
        .image-preview { display:flex; flex-wrap:wrap; gap:10px; margin-top:15px; }
        .preview-item  { position:relative; width:90px; height:90px; border-radius:6px; overflow:hidden; border:2px solid #dee2e6; }
        .preview-item img { width:100%; height:100%; object-fit:cover; }
        .remove-preview {
            position:absolute; top:2px; right:2px;
            background:rgba(220,53,69,.9); color:white; border:none;
            border-radius:50%; width:20px; height:20px; font-size:12px;
            display:flex; align-items:center; justify-content:center; cursor:pointer;
        }
        /* Table thumbnail */
        .asset-thumb { width:40px; height:40px; object-fit:cover; border-radius:6px; border:1px solid #dee2e6; }
        .asset-thumb-placeholder {
            width:40px; height:40px; border-radius:6px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            display:inline-flex; align-items:center; justify-content:center; color:white; font-size:1rem;
        }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'adsidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
            <div class="content-header">
                <div class="d-flex justify-content-between align-items-center">
                    <div>
                        <h1><i class="bi bi-box"></i> Asset Management</h1>
                        <p>Manage and track all company assets</p>
                    </div>
                    <button type="button" class="btn btn-primary"
                            data-bs-toggle="modal" data-bs-target="#assetModal"
                            onclick="resetForm()">
                        <i class="bi bi-plus-lg"></i> Add New Asset
                    </button>
                </div>
            </div>

            <!-- Quick Stats -->
            <div class="quick-stats">
                <div class="row">
                    <div class="col-md-2 col-6"><h6>Total Assets</h6><h3><?php echo $total_assets; ?></h3></div>
                    <div class="col-md-2 col-6"><h6>Active</h6><h3><?php echo $active_assets; ?></h3></div>
                    <div class="col-md-2 col-6"><h6>In Stock</h6><h3><?php echo $in_stock_assets; ?></h3></div>
                    <div class="col-md-2 col-6"><h6>Assigned</h6><h3><?php echo $assigned_assets; ?></h3></div>
                    <div class="col-md-2 col-6"><h6>Maintenance</h6><h3><?php echo $under_maintenance; ?></h3></div>
                </div>
            </div>

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Filters -->
            <div class="filter-section">
                <div class="row">
                    <div class="col-md-3">
                        <select class="form-select" id="statusFilter">
                            <option value="">All Status</option>
                            <option>Active</option><option>In Stock</option>
                            <option>Assigned</option><option>Under Maintenance</option><option>Disposed</option>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="classFilter">
                            <option value="">All Classes</option>
                            <?php $asset_classes->data_seek(0); while($c = $asset_classes->fetch_assoc()): ?>
                                <option><?php echo htmlspecialchars($c['class_name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <select class="form-select" id="locationFilter">
                            <option value="">All Locations</option>
                            <?php $locations->data_seek(0); while($l = $locations->fetch_assoc()): ?>
                                <option><?php echo htmlspecialchars($l['name']); ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>
                    <div class="col-md-3">
                        <input type="text" class="form-control" id="searchFilter" placeholder="Search...">
                    </div>
                </div>
            </div>

            <!-- Table -->
            <div class="card">
                <div class="card-body">
                    <div class="table-responsive">
                        <table class="table table-hover" id="assetsTable">
                            <thead>
                                <tr>
                                    <th>Photo</th>
                                    <th>Asset Tag</th>
                                    <th>Name</th>
                                    <th>Class</th>
                                    <th>Model</th>
                                    <th>Serial Number</th>
                                    <th>Status</th>
                                    <th>Location</th>
                                    <th>Assigned To</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($assets as $asset): ?>
                                <tr>
                                    <td>
                                        <?php if (!empty($asset['primary_image']) && file_exists($asset['primary_image'])): ?>
                                            <img src="<?php echo htmlspecialchars('../../' . ltrim($asset['primary_image'], './')); ?>"
                                                 class="asset-thumb" alt="Photo">
                                        <?php else: ?>
                                            <span class="asset-thumb-placeholder"><i class="bi bi-box"></i></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><strong><?php echo htmlspecialchars($asset['asset_tag'] ?? 'N/A'); ?></strong></td>
                                    <td><?php echo htmlspecialchars($asset['asset_name']); ?></td>
                                    <td><?php echo htmlspecialchars($asset['class_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['model'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['serial_number'] ?? 'N/A'); ?></td>
                                    <td>
                                        <?php $sc = match($asset['asset_status']) {
                                            'Active'            => 'status-active',
                                            'In Stock'          => 'status-in-stock',
                                            'Assigned'          => 'status-assigned',
                                            'Under Maintenance' => 'status-under-maintenance',
                                            default             => 'status-disposed',
                                        }; ?>
                                        <span class="status-badge <?php echo $sc; ?>"><?php echo $asset['asset_status']; ?></span>
                                    </td>
                                    <td><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></td>
                                    <td><?php echo htmlspecialchars($asset['assigned_to'] ?? 'Unassigned'); ?></td>
                                    <td class="action-btns">
                                        <button class="btn btn-sm btn-info view-asset"
                                                data-bs-toggle="modal" data-bs-target="#viewAssetModal"
                                                data-asset='<?php echo htmlspecialchars(json_encode($asset), ENT_QUOTES); ?>'>
                                            <i class="bi bi-eye"></i>
                                        </button>
                                        <button class="btn btn-sm btn-warning edit-asset"
                                                data-bs-toggle="modal" data-bs-target="#assetModal"
                                                data-asset='<?php echo htmlspecialchars(json_encode($asset), ENT_QUOTES); ?>'>
                                            <i class="bi bi-pencil"></i>
                                        </button>
                                        <a href="?delete=<?php echo $asset['asset_id']; ?>"
                                           class="btn btn-sm btn-danger"
                                           onclick="return confirm('Delete this asset?')">
                                            <i class="bi bi-trash"></i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- ══ Add / Edit Modal ══ -->
<div class="modal fade" id="assetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="assetModalTitle">Add New Asset</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <form method="POST" id="assetForm" enctype="multipart/form-data">
                <div class="modal-body">
                    <input type="hidden" name="action"   id="action"   value="create">
                    <input type="hidden" name="asset_id" id="asset_id">

                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Tag <small class="text-muted">(auto-generated if blank)</small></label>
                            <input type="text" class="form-control" name="asset_tag" id="asset_tag">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Name *</label>
                            <input type="text" class="form-control" name="asset_name" id="asset_name" required>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Asset Class</label>
                            <select class="form-select" name="asset_class" id="asset_class">
                                <option value="">Select Class</option>
                                <?php $asset_classes->data_seek(0); while($c = $asset_classes->fetch_assoc()): ?>
                                    <option value="<?php echo $c['class_id']; ?>"><?php echo $c['class_name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Model</label>
                            <input type="text" class="form-control" name="model" id="model">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Manufacturer</label>
                            <input type="text" class="form-control" name="manufacturer" id="manufacturer">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Serial Number</label>
                            <input type="text" class="form-control" name="serial_number" id="serial_number">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Purchase Order Number</label>
                            <input type="text" class="form-control" name="purchase_order_number" id="purchase_order_number">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Vendor</label>
                            <input type="text" class="form-control" name="vendor" id="vendor">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Acquisition Date</label>
                            <input type="date" class="form-control" name="acquisition_date" id="acquisition_date">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Warranty Expiry</label>
                            <input type="date" class="form-control" name="warranty_expiry" id="warranty_expiry">
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Cost</label>
                            <input type="number" step="0.01" class="form-control" name="cost" id="cost">
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Status</label>
                            <select class="form-select" name="asset_status" id="asset_status" required>
                                <option value="In Stock">In Stock</option>
                                <option value="Active">Active</option>
                                <option value="Assigned">Assigned</option>
                                <option value="Under Maintenance">Under Maintenance</option>
                                <option value="Disposed">Disposed</option>
                            </select>
                        </div>
                    </div>
                    <div class="row">
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Location</label>
                            <select class="form-select" name="location_id" id="location_id">
                                <option value="">Select Location</option>
                                <?php $locations->data_seek(0); while($l = $locations->fetch_assoc()): ?>
                                    <option value="<?php echo $l['location_id']; ?>"><?php echo $l['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                        <div class="col-md-6 mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="owner_department_id" id="owner_department_id">
                                <option value="">Select Department</option>
                                <?php $departments->data_seek(0); while($d = $departments->fetch_assoc()): ?>
                                    <option value="<?php echo $d['department_id']; ?>"><?php echo $d['name']; ?></option>
                                <?php endwhile; ?>
                            </select>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Assigned To</label>
                        <select class="form-select" name="assigned_to_user_id" id="assigned_to_user_id">
                            <option value="">Select User</option>
                            <?php $users->data_seek(0); while($u = $users->fetch_assoc()): ?>
                                <option value="<?php echo $u['user_id']; ?>"><?php echo $u['full_name']; ?></option>
                            <?php endwhile; ?>
                        </select>
                    </div>

                    <!-- Image Upload -->
                    <div class="mb-3">
                        <label class="form-label">Asset Photos</label>
                        <div class="upload-area" id="uploadArea"
                             onclick="document.getElementById('asset_images').click()">
                            <i class="bi bi-cloud-upload"></i>
                            <p class="mb-1 mt-2 fw-semibold">Click to upload or drag &amp; drop</p>
                            <small class="text-muted">JPG, PNG, GIF, WEBP — multiple files allowed</small>
                        </div>
                        <input type="file" id="asset_images" name="asset_images[]"
                               accept="image/*" multiple style="display:none"
                               onchange="previewImages(this)">
                        <div class="image-preview" id="imagePreview"></div>
                        <div id="existingImages"></div>
                    </div>

                    <div class="mb-3">
                        <label class="form-label">Remarks</label>
                        <textarea class="form-control" name="remarks" id="remarks" rows="3"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Asset</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- ══ View Modal ══ -->
<div class="modal fade" id="viewAssetModal" tabindex="-1">
    <div class="modal-dialog modal-lg">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title">Asset Details</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body" id="viewAssetContent"></div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
<script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>

<script>
$(document).ready(function() {
    var table = $('#assetsTable').DataTable({
        pageLength: 25,
        order: [[2, 'asc']],
        dom: 'Bfrtip',
        buttons: ['copy','csv','excel','pdf'],
        columnDefs: [{ orderable: false, targets: [0, 9] }]
    });

    $('#statusFilter, #classFilter, #locationFilter').on('change', function() { table.draw(); });
    $('#searchFilter').on('keyup', function() { table.search(this.value).draw(); });

    $.fn.dataTable.ext.search.push(function(settings, data) {
        var status   = $('#statusFilter').val();
        var cls      = $('#classFilter').val();
        var location = $('#locationFilter').val();
        if (status   && !data[6].includes(status))   return false;
        if (cls      && !data[3].includes(cls))       return false;
        if (location && !data[7].includes(location))  return false;
        return true;
    });

    // Edit
    $('.edit-asset').on('click', function() {
        var a = $(this).data('asset');
        $('#assetModalTitle').text('Edit Asset');
        $('#action').val('update');
        $('#asset_id').val(a.asset_id);
        $('#asset_tag').val(a.asset_tag);
        $('#asset_name').val(a.asset_name);
        $('#asset_class').val(a.asset_class);
        $('#model').val(a.model);
        $('#manufacturer').val(a.manufacturer);
        $('#serial_number').val(a.serial_number);
        $('#purchase_order_number').val(a.purchase_order_number);
        $('#acquisition_date').val(a.acquisition_date);
        $('#warranty_expiry').val(a.warranty_expiry);
        $('#vendor').val(a.vendor);
        $('#cost').val(a.cost);
        $('#asset_status').val(a.asset_status);
        $('#location_id').val(a.location_id);
        $('#owner_department_id').val(a.owner_department_id);
        $('#assigned_to_user_id').val(a.assigned_to_user_id);
        $('#remarks').val(a.remarks);
        $('#imagePreview').empty();
        $('#asset_images').val('');
        var ex = $('#existingImages').empty();
        if (a.primary_image) {
            var p = '../../' + a.primary_image.replace(/^\.\//, '');
            ex.html('<p class="mt-2 mb-1 text-muted small">Current photo:</p>'
                  + '<img src="' + p + '" style="height:80px;border-radius:6px;border:1px solid #dee2e6;" alt="Current">'
                  + '<p class="text-muted small mt-1">New uploads will be added alongside existing photos.</p>');
        }
    });

    // View
    $('.view-asset').on('click', function() {
        var a = $(this).data('asset');
        var imgHtml = '';
        if (a.primary_image) {
            var p = '../../' + a.primary_image.replace(/^\.\//, '');
            imgHtml = '<div class="text-center mb-3"><img src="' + p + '" style="max-height:200px;border-radius:8px;max-width:100%;"></div>';
        }
        $('#viewAssetContent').html(imgHtml + `
            <div class="row">
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><th>Asset ID</th><td>${a.asset_id}</td></tr>
                        <tr><th>Asset Tag</th><td>${a.asset_tag || 'N/A'}</td></tr>
                        <tr><th>Asset Name</th><td>${a.asset_name}</td></tr>
                        <tr><th>Class</th><td>${a.class_name || 'N/A'}</td></tr>
                        <tr><th>Model</th><td>${a.model || 'N/A'}</td></tr>
                        <tr><th>Manufacturer</th><td>${a.manufacturer || 'N/A'}</td></tr>
                        <tr><th>Serial Number</th><td>${a.serial_number || 'N/A'}</td></tr>
                    </table>
                </div>
                <div class="col-md-6">
                    <table class="table table-sm">
                        <tr><th>Status</th><td><span class="status-badge status-${a.asset_status.toLowerCase().replace(/ /g,'-')}">${a.asset_status}</span></td></tr>
                        <tr><th>Location</th><td>${a.location_name || 'N/A'}</td></tr>
                        <tr><th>Department</th><td>${a.department_name || 'N/A'}</td></tr>
                        <tr><th>Assigned To</th><td>${a.assigned_to || 'Unassigned'}</td></tr>
                        <tr><th>Cost</th><td>${a.cost ? 'RM ' + parseFloat(a.cost).toFixed(2) : 'N/A'}</td></tr>
                        <tr><th>Acquisition Date</th><td>${a.acquisition_date || 'N/A'}</td></tr>
                        <tr><th>Warranty Expiry</th><td>${a.warranty_expiry || 'N/A'}</td></tr>
                    </table>
                </div>
            </div>
            <h6>Remarks:</h6><p>${a.remarks || 'No remarks'}</p>
        `);
    });
});

function resetForm() {
    document.getElementById('assetForm').reset();
    document.getElementById('assetModalTitle').textContent = 'Add New Asset';
    document.getElementById('action').value   = 'create';
    document.getElementById('asset_id').value = '';
    document.getElementById('imagePreview').innerHTML   = '';
    document.getElementById('existingImages').innerHTML = '';
}

function previewImages(input) {
    var preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(function(file) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var item = document.createElement('div');
            item.className = 'preview-item';
            item.innerHTML = '<img src="' + e.target.result + '">'
                           + '<button type="button" class="remove-preview" onclick="this.parentElement.remove()">×</button>';
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}

var uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', function()  { this.classList.remove('dragover'); });
uploadArea.addEventListener('drop', function(e) {
    e.preventDefault(); this.classList.remove('dragover');
    var input = document.getElementById('asset_images');
    var dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(function(f) { dt.items.add(f); });
    input.files = dt.files;
    previewImages(input);
});
</script>
</body>
</html>
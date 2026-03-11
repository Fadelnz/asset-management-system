<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation', 'operation_manager', 'admin']);

require_once '../../includes/db.php';
$conn = $db->conn;
$user_id = $_SESSION['user_id'];

if (!isset($_GET['id'])) {
    header('Location: inventory_report.php');
    exit();
}

$asset_id = $_GET['id'];
$message      = '';
$message_type = '';

// ── Handle Update ─────────────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    $asset_tag           = !empty($_POST['asset_tag']) ? trim($_POST['asset_tag']) : 'TAG-' . date('Ymd') . '-' . rand(100,999);
    $asset_name          = trim($_POST['asset_name'] ?? '');
    $asset_class         = $_POST['asset_class'] ?? '';
    $model               = trim($_POST['model'] ?? '');
    $manufacturer        = trim($_POST['manufacturer'] ?? '');
    $serial_number       = trim($_POST['serial_number'] ?? '');
    $purchase_order_number = trim($_POST['purchase_order_number'] ?? '');
    $acquisition_date    = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
    $warranty_expiry     = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $vendor              = trim($_POST['vendor'] ?? '');
    $cost                = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $asset_status        = $_POST['asset_status'] ?? 'In Stock';
    $location_id         = !empty($_POST['location_id'])         ? $_POST['location_id']         : null;
    $owner_department_id = !empty($_POST['owner_department_id']) ? $_POST['owner_department_id'] : null;
    $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? $_POST['assigned_to_user_id'] : null;
    $remarks             = trim($_POST['remarks'] ?? '');

    if (empty($asset_name)) {
        $message      = "Asset Name is required.";
        $message_type = "danger";
    } else {
        $sql  = "UPDATE assets SET asset_tag=?, asset_name=?, asset_class=?, model=?, manufacturer=?,
                 serial_number=?, purchase_order_number=?, acquisition_date=?, warranty_expiry=?,
                 vendor=?, cost=?, asset_status=?, location_id=?, owner_department_id=?,
                 assigned_to_user_id=?, remarks=?, updated_by=? WHERE asset_id=?";
        $stmt = $conn->prepare($sql);
        $stmt->bind_param("ssssssssssdsssssss",
            $asset_tag, $asset_name, $asset_class, $model, $manufacturer,
            $serial_number, $purchase_order_number, $acquisition_date, $warranty_expiry,
            $vendor, $cost, $asset_status, $location_id, $owner_department_id,
            $assigned_to_user_id, $remarks, $user_id, $asset_id
        );

        if ($stmt->execute()) {
            // Save new images if uploaded
            if (isset($_FILES['asset_images']) && !empty($_FILES['asset_images']['name'][0])) {
                $target_dir = "../../uploads/assets/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $files = $_FILES['asset_images'];
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] !== 0) continue;
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
                    $img_file = $target_dir . $asset_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $img_file)) {
                        $ims = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, is_primary) VALUES (?, ?, 0)");
                        $ims->bind_param("ss", $asset_id, $img_file);
                        $ims->execute();
                    }
                }
            }

            // Delete images marked for removal
            if (!empty($_POST['delete_images'])) {
                foreach ($_POST['delete_images'] as $img_id) {
                    $img_id = (int)$img_id;
                    $path_stmt = $conn->prepare("SELECT image_path FROM asset_images WHERE id = ? AND asset_id = ?");
                    $path_stmt->bind_param("is", $img_id, $asset_id);
                    $path_stmt->execute();
                    $path_row = $path_stmt->get_result()->fetch_assoc();
                    if ($path_row && file_exists($path_row['image_path'])) unlink($path_row['image_path']);
                    $del = $conn->prepare("DELETE FROM asset_images WHERE id = ? AND asset_id = ?");
                    $del->bind_param("is", $img_id, $asset_id);
                    $del->execute();
                }
            }

            // Log activity
            $log = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, 'Updated Asset', ?, ?)");
            $det = "Updated asset: $asset_name (ID: $asset_id)";
            $log->bind_param("sss", $user_id, $det, $_SERVER['REMOTE_ADDR']);
            $log->execute();

            header("Location: view_asset.php?id=$asset_id&updated=1");
            exit();
        } else {
            $message      = "Error updating asset: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// ── Fetch current asset data ──────────────────────────────────────────────
$stmt = $conn->prepare("SELECT a.*, ac.class_name FROM assets a
                        LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
                        WHERE a.asset_id = ?");
$stmt->bind_param("s", $asset_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    header('Location: inventory_report.php');
    exit();
}

// Fetch existing images
$images = [];
$img_stmt = $conn->prepare("SELECT * FROM asset_images WHERE asset_id = ? ORDER BY is_primary DESC");
$img_stmt->bind_param("s", $asset_id);
$img_stmt->execute();
$img_res = $img_stmt->get_result();
while ($img = $img_res->fetch_assoc()) $images[] = $img;

// Dropdowns
$asset_classes = $conn->query("SELECT * FROM asset_classes ORDER BY class_name");
$locations     = $conn->query("SELECT * FROM locations ORDER BY name");
$departments   = $conn->query("SELECT * FROM departments ORDER BY name");
$users         = $conn->query("SELECT user_id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Asset - <?php echo htmlspecialchars($asset['asset_name']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6fb; }
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; min-height: 100vh; padding-top: 20px;
        }
        .sidebar .nav-link { color: white; padding: 10px 15px; margin: 5px 0; }
        .sidebar .nav-link:hover  { background: rgba(255,255,255,.1); }
        .sidebar .nav-link.active { background: rgba(255,255,255,.2); }
        .content-header { background:#fff; padding:20px 25px; border-bottom:1px solid #dee2e6; margin-bottom:25px; }
        .form-card { border:none; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.07); margin-bottom:24px; }
        .form-card .card-header {
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:white; border-radius:12px 12px 0 0 !important; padding:14px 20px; font-weight:600;
        }
        .required::after { content:" *"; color:#dc3545; }

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
        /* Existing image thumbnails */
        .existing-img-wrap { position:relative; width:90px; }
        .existing-img-wrap img { width:90px; height:90px; object-fit:cover; border-radius:6px; border:2px solid #dee2e6; }
        .existing-img-wrap.marked img { opacity:.35; }
        .existing-img-wrap .del-btn {
            position:absolute; top:2px; right:2px;
            background:rgba(220,53,69,.9); color:white; border:none;
            border-radius:50%; width:20px; height:20px; font-size:12px;
            display:flex; align-items:center; justify-content:center; cursor:pointer;
        }
        .primary-badge {
            position:absolute; bottom:0; left:0; right:0;
            background:rgba(102,126,234,.85); color:white;
            font-size:.62rem; text-align:center; padding:2px; border-radius:0 0 4px 4px;
        }
    </style>
</head>
<body>
<div class="container-fluid">
<div class="row">

    <!-- Sidebar -->
    <nav class="col-md-3 col-lg-2 d-md-block sidebar">
        <div class="position-sticky">
            <div class="text-center mb-4">
                <i class="bi bi-warehouse" style="font-size:48px;"></i>
                <h4 class="mt-2">Warehouse Dashboard</h4>
                <p class="mb-0">Welcome, <?php echo htmlspecialchars(get_user_name()); ?></p>
                <small class="text-light">Coordinator</small>
            </div>
            <ul class="nav flex-column">
                <li class="nav-item"><a class="nav-link" href="dashboard.php"><i class="bi bi-speedometer2"></i> Dashboard</a></li>
                <li class="nav-item"><a class="nav-link active" href="inventory_report.php"><i class="bi bi-clipboard-data"></i> Inventory / Asset Reports</a></li>
                <li class="nav-item"><a class="nav-link" href="approvals.php"><i class="bi bi-check-circle"></i> Approvals / Requests</a></li>
                <li class="nav-item"><a class="nav-link" href="vehicle_management.php"><i class="bi bi-truck"></i> Vehicle Management</a></li>
                <li class="nav-item"><a class="nav-link" href="maintenance.php"><i class="bi bi-tools"></i> Maintenance</a></li>
                <li class="nav-item mt-4">
                    <a class="nav-link text-warning" href="#"><i class="bi bi-bell"></i> Notifications <span class="badge bg-danger rounded-pill">3</span></a>
                </li>
                <li class="nav-item"><a class="nav-link text-danger" href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
            </ul>
        </div>
    </nav>

    <!-- Main Content -->
    <main class="col-md-9 ms-sm-auto col-lg-10 px-0">
        <div class="content-header d-flex align-items-center justify-content-between">
            <div>
                <nav aria-label="breadcrumb">
                    <ol class="breadcrumb mb-1">
                        <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                        <li class="breadcrumb-item"><a href="inventory_report.php" class="text-decoration-none">Inventory Reports</a></li>
                        <li class="breadcrumb-item"><a href="view_asset.php?id=<?php echo $asset_id; ?>" class="text-decoration-none">Asset Details</a></li>
                        <li class="breadcrumb-item active">Edit</li>
                    </ol>
                </nav>
                <h4 class="mb-0 fw-bold">
                    <i class="bi bi-pencil me-2 text-warning"></i>Edit Asset
                    <small class="text-muted fs-6 ms-2"><?php echo htmlspecialchars($asset['asset_name']); ?></small>
                </h4>
            </div>
            <a href="view_asset.php?id=<?php echo $asset_id; ?>" class="btn btn-outline-secondary">
                <i class="bi bi-arrow-left me-1"></i> Cancel
            </a>
        </div>

        <div class="px-4">

            <?php if ($message): ?>
            <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                <i class="bi bi-exclamation-triangle me-2"></i><?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <form method="POST" enctype="multipart/form-data" id="editForm">

                <!-- Basic Info -->
                <div class="form-card card">
                    <div class="card-header"><i class="bi bi-info-circle me-2"></i>Basic Information</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label required">Asset Name</label>
                                <input type="text" class="form-control" name="asset_name" required
                                       value="<?php echo htmlspecialchars($asset['asset_name']); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Asset Tag</label>
                                <input type="text" class="form-control" name="asset_tag"
                                       value="<?php echo htmlspecialchars($asset['asset_tag'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Asset Class</label>
                                <select class="form-select" name="asset_class">
                                    <option value="">— Select Class —</option>
                                    <?php while($c = $asset_classes->fetch_assoc()): ?>
                                        <option value="<?php echo $c['class_id']; ?>"
                                            <?php echo $asset['asset_class'] == $c['class_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($c['class_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Status</label>
                                <select class="form-select" name="asset_status">
                                    <?php foreach (['In Stock','Active','Assigned','Under Maintenance','Disposed'] as $s): ?>
                                        <option <?php echo $asset['asset_status'] == $s ? 'selected' : ''; ?>><?php echo $s; ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Model</label>
                                <input type="text" class="form-control" name="model"
                                       value="<?php echo htmlspecialchars($asset['model'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Manufacturer</label>
                                <input type="text" class="form-control" name="manufacturer"
                                       value="<?php echo htmlspecialchars($asset['manufacturer'] ?? ''); ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Purchase & Warranty -->
                <div class="form-card card">
                    <div class="card-header"><i class="bi bi-gear me-2"></i>Purchase & Warranty</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Serial Number</label>
                                <input type="text" class="form-control" name="serial_number"
                                       value="<?php echo htmlspecialchars($asset['serial_number'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">PO Number</label>
                                <input type="text" class="form-control" name="purchase_order_number"
                                       value="<?php echo htmlspecialchars($asset['purchase_order_number'] ?? ''); ?>">
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Vendor</label>
                                <input type="text" class="form-control" name="vendor"
                                       value="<?php echo htmlspecialchars($asset['vendor'] ?? ''); ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Cost (RM)</label>
                                <div class="input-group">
                                    <span class="input-group-text">RM</span>
                                    <input type="number" step="0.01" min="0" class="form-control" name="cost"
                                           value="<?php echo $asset['cost'] ?? ''; ?>">
                                </div>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Acquisition Date</label>
                                <input type="date" class="form-control" name="acquisition_date"
                                       value="<?php echo $asset['acquisition_date'] ?? ''; ?>">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Warranty Expiry</label>
                                <input type="date" class="form-control" name="warranty_expiry"
                                       value="<?php echo $asset['warranty_expiry'] ?? ''; ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Assignment -->
                <div class="form-card card">
                    <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Location & Assignment</div>
                    <div class="card-body">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Location</label>
                                <select class="form-select" name="location_id">
                                    <option value="">— Select Location —</option>
                                    <?php while($l = $locations->fetch_assoc()): ?>
                                        <option value="<?php echo $l['location_id']; ?>"
                                            <?php echo $asset['location_id'] == $l['location_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($l['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Department</label>
                                <select class="form-select" name="owner_department_id">
                                    <option value="">— Select Department —</option>
                                    <?php while($d = $departments->fetch_assoc()): ?>
                                        <option value="<?php echo $d['department_id']; ?>"
                                            <?php echo $asset['owner_department_id'] == $d['department_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($d['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label class="form-label">Assigned To</label>
                                <select class="form-select" name="assigned_to_user_id">
                                    <option value="">— Unassigned —</option>
                                    <?php while($u = $users->fetch_assoc()): ?>
                                        <option value="<?php echo $u['user_id']; ?>"
                                            <?php echo $asset['assigned_to_user_id'] == $u['user_id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($u['full_name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea class="form-control" name="remarks" rows="3"><?php echo htmlspecialchars($asset['remarks'] ?? ''); ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Photos -->
                <div class="form-card card">
                    <div class="card-header"><i class="bi bi-images me-2"></i>Photos</div>
                    <div class="card-body">

                        <!-- Existing images -->
                        <?php if (!empty($images)): ?>
                        <p class="text-muted small mb-2">Current photos — click <i class="bi bi-x-circle text-danger"></i> to remove:</p>
                        <div class="d-flex flex-wrap gap-2 mb-3" id="existingImagesContainer">
                            <?php foreach ($images as $img): ?>
                            <div class="existing-img-wrap" id="imgwrap-<?php echo $img['id']; ?>">
                                <img src="<?php echo htmlspecialchars('../../' . ltrim($img['image_path'], './')); ?>" alt="Photo">
                                <?php if ($img['is_primary']): ?><div class="primary-badge">Primary</div><?php endif; ?>
                                <button type="button" class="del-btn" onclick="markDelete(<?php echo $img['id']; ?>)" title="Remove">×</button>
                                <input type="checkbox" name="delete_images[]" value="<?php echo $img['id']; ?>"
                                       id="del-<?php echo $img['id']; ?>" style="display:none;">
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php endif; ?>

                        <!-- Upload new -->
                        <p class="text-muted small mb-2">Add new photos:</p>
                        <div class="upload-area" id="uploadArea"
                             onclick="document.getElementById('asset_images').click()">
                            <i class="bi bi-cloud-upload"></i>
                            <p class="mb-1 mt-2 fw-semibold">Click to upload or drag &amp; drop</p>
                            <small class="text-muted">JPG, PNG, GIF, WEBP</small>
                        </div>
                        <input type="file" id="asset_images" name="asset_images[]"
                               accept="image/*" multiple style="display:none"
                               onchange="previewImages(this)">
                        <div class="image-preview" id="imagePreview"></div>
                    </div>
                </div>

                <!-- Submit -->
                <div class="d-flex gap-2 mb-4">
                    <button type="submit" class="btn btn-warning btn-lg px-5" id="saveBtn">
                        <i class="bi bi-check-circle me-2"></i>Save Changes
                    </button>
                    <a href="view_asset.php?id=<?php echo $asset_id; ?>" class="btn btn-outline-secondary btn-lg">
                        Cancel
                    </a>
                </div>

            </form>
        </div>
    </main>
</div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
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

function markDelete(id) {
    var wrap = document.getElementById('imgwrap-' + id);
    var cb   = document.getElementById('del-' + id);
    if (cb.checked) {
        cb.checked = false;
        wrap.classList.remove('marked');
    } else {
        cb.checked = true;
        wrap.classList.add('marked');
    }
}

var ua = document.getElementById('uploadArea');
ua.addEventListener('dragover',  function(e) { e.preventDefault(); this.classList.add('dragover'); });
ua.addEventListener('dragleave', function()  { this.classList.remove('dragover'); });
ua.addEventListener('drop', function(e) {
    e.preventDefault(); this.classList.remove('dragover');
    var input = document.getElementById('asset_images');
    var dt = new DataTransfer();
    Array.from(e.dataTransfer.files).forEach(function(f) { dt.items.add(f); });
    input.files = dt.files;
    previewImages(input);
});

document.getElementById('editForm').addEventListener('submit', function() {
    var btn = document.getElementById('saveBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Saving...';
});
</script>
</body>
</html>
<?php $conn->close(); ?>
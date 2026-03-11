<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation', 'operation_manager', 'admin']);

require_once '../../includes/db.php';
$conn = $db->conn;
$user_id = $_SESSION['user_id'];

$message      = '';
$message_type = '';

// ── Handle Form Submission ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] == 'POST') {

    // Auto-generate tag if blank (prevents UNIQUE constraint crash)
    $asset_tag   = !empty($_POST['asset_tag'])
                   ? trim($_POST['asset_tag'])
                   : 'TAG-' . date('Ymd') . '-' . rand(100, 999);
    $asset_name  = trim($_POST['asset_name'] ?? '');
    $asset_class = $_POST['asset_class'] ?? '';
    $model       = trim($_POST['model'] ?? '');
    $manufacturer= trim($_POST['manufacturer'] ?? '');
    $serial_number        = trim($_POST['serial_number'] ?? '');
    $purchase_order_number= trim($_POST['purchase_order_number'] ?? '');
    $acquisition_date     = !empty($_POST['acquisition_date']) ? $_POST['acquisition_date'] : null;
    $warranty_expiry      = !empty($_POST['warranty_expiry'])  ? $_POST['warranty_expiry']  : null;
    $vendor      = trim($_POST['vendor'] ?? '');
    $cost        = !empty($_POST['cost']) ? $_POST['cost'] : null;
    $asset_status= $_POST['asset_status'] ?? 'In Stock';
    // NULL-safe FK fields
    $location_id         = !empty($_POST['location_id'])         ? $_POST['location_id']         : null;
    $owner_department_id = !empty($_POST['owner_department_id']) ? $_POST['owner_department_id'] : null;
    $assigned_to_user_id = !empty($_POST['assigned_to_user_id']) ? $_POST['assigned_to_user_id'] : null;
    $remarks     = trim($_POST['remarks'] ?? '');

    if (empty($asset_name)) {
        $message      = "Asset Name is required.";
        $message_type = "danger";
    } else {
        // Generate unique asset_id
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
            $assigned_to_user_id, $remarks, $user_id
        );

        if ($stmt->execute()) {
            // ── Save uploaded images ──────────────────────────────────
            if (isset($_FILES['asset_images']) && !empty($_FILES['asset_images']['name'][0])) {
                $target_dir = "../../uploads/assets/";
                if (!file_exists($target_dir)) mkdir($target_dir, 0777, true);
                $files      = $_FILES['asset_images'];
                $is_primary = true;
                for ($i = 0; $i < count($files['name']); $i++) {
                    if ($files['error'][$i] !== 0) continue;
                    $ext = strtolower(pathinfo($files['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ['jpg','jpeg','png','gif','webp'])) continue;
                    $img_file = $target_dir . $asset_id . '_' . uniqid() . '.' . $ext;
                    if (move_uploaded_file($files['tmp_name'][$i], $img_file)) {
                        $pf  = $is_primary ? 1 : 0;
                        $ims = $conn->prepare("INSERT INTO asset_images (asset_id, image_path, is_primary) VALUES (?, ?, ?)");
                        $ims->bind_param("ssi", $asset_id, $img_file, $pf);
                        $ims->execute();
                        $is_primary = false;
                    }
                }
            }

            // ── Log activity ──────────────────────────────────────────
            $log  = $conn->prepare("INSERT INTO activity_logs (user_id, activity, details, ip_address) VALUES (?, 'Registered Asset', ?, ?)");
            $det  = "Registered asset: $asset_name (ID: $asset_id)";
            $log->bind_param("sss", $user_id, $det, $_SERVER['REMOTE_ADDR']);
            $log->execute();

            // Redirect with success message
            header("Location: inventory_report.php?registered=1&asset=" . urlencode($asset_name));
            exit();

        } else {
            $message      = "Error registering asset: " . $conn->error;
            $message_type = "danger";
        }
    }
}

// ── Dropdown data ─────────────────────────────────────────────────────────
$asset_classes= $conn->query("SELECT * FROM asset_classes ORDER BY class_name");
$locations    = $conn->query("SELECT * FROM locations ORDER BY name");
$departments  = $conn->query("SELECT * FROM departments ORDER BY name");
$users        = $conn->query("SELECT user_id, full_name FROM users WHERE is_active = 1 ORDER BY full_name");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register New Asset - Warehouse Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        /* ── Sidebar ── */
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; min-height: 100vh; padding-top: 20px;
        }
        .sidebar .nav-link { color: white; padding: 10px 15px; margin: 5px 0; }
        .sidebar .nav-link:hover  { background: rgba(255,255,255,.1); }
        .sidebar .nav-link.active { background: rgba(255,255,255,.2); }

        /* ── Content ── */
        body { background: #f4f6fb; }
        .content-header {
            background: #fff; padding: 20px 25px;
            border-bottom: 1px solid #dee2e6; margin-bottom: 25px;
        }

        /* ── Form card ── */
        .form-card {
            background: #fff; border: none; border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,.07); margin-bottom: 24px;
        }
        .form-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white; border-radius: 12px 12px 0 0 !important;
            padding: 14px 20px; font-weight: 600;
        }
        .section-divider {
            border: none; border-top: 2px dashed #e0e0e0; margin: 20px 0;
        }

        /* ── Upload ── */
        .upload-area {
            border: 2px dashed #dee2e6; border-radius: 8px; padding: 30px;
            text-align: center; background: #f8f9fa; cursor: pointer; transition: all .3s;
        }
        .upload-area:hover, .upload-area.dragover { border-color: #667eea; background: #fff; }
        .upload-area i { font-size: 2.5rem; color: #6c757d; }
        .image-preview  { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 15px; }
        .preview-item   { position: relative; width: 100px; height: 100px; border-radius: 8px; overflow: hidden; border: 2px solid #dee2e6; }
        .preview-item img { width: 100%; height: 100%; object-fit: cover; }
        .remove-preview {
            position: absolute; top: 3px; right: 3px;
            background: rgba(220,53,69,.9); color: white; border: none;
            border-radius: 50%; width: 22px; height: 22px; font-size: 13px;
            display: flex; align-items: center; justify-content: center; cursor: pointer;
        }

        /* ── Required asterisk ── */
        .required::after { content: " *"; color: #dc3545; }

        /* ── Step indicator ── */
        .step-indicator {
            display: flex; gap: 0; margin-bottom: 28px; border-radius: 10px; overflow: hidden;
        }
        .step { flex: 1; text-align: center; padding: 12px 8px; font-size: .82rem; font-weight: 600;
                background: #e9ecef; color: #6c757d; cursor: pointer; transition: all .2s; }
        .step.active { background: linear-gradient(135deg,#667eea,#764ba2); color: white; }
        .step.done   { background: #28a745; color: white; }
        .step i { display: block; font-size: 1.2rem; margin-bottom: 3px; }

        /* ── Section panels ── */
        .form-section { display: none; }
        .form-section.active { display: block; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">

        <!-- ══ SIDEBAR ══ -->
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
                        <a class="nav-link text-warning" href="#">
                            <i class="bi bi-bell"></i> Notifications
                            <span class="badge bg-danger rounded-pill">3</span>
                        </a>
                    </li>
                    <li class="nav-item"><a class="nav-link text-danger" href="../../auth/logout.php"><i class="bi bi-box-arrow-right"></i> Logout</a></li>
                </ul>
            </div>
        </nav>

        <!-- ══ MAIN CONTENT ══ -->
        <main class="col-md-9 ms-sm-auto col-lg-10 px-0">

            <!-- Page header -->
            <div class="content-header d-flex align-items-center justify-content-between">
                <div>
                    <nav aria-label="breadcrumb">
                        <ol class="breadcrumb mb-1">
                            <li class="breadcrumb-item"><a href="dashboard.php" class="text-decoration-none">Dashboard</a></li>
                            <li class="breadcrumb-item"><a href="inventory_report.php" class="text-decoration-none">Inventory Reports</a></li>
                            <li class="breadcrumb-item active">Register New Asset</li>
                        </ol>
                    </nav>
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-plus-circle me-2 text-info"></i>Register New Asset
                    </h4>
                </div>
                <a href="inventory_report.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="px-4">

                <?php if ($message): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show">
                    <i class="bi bi-<?php echo $message_type == 'danger' ? 'exclamation-triangle' : 'check-circle'; ?> me-2"></i>
                    <?php echo $message; ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>

                <!-- Step indicator -->
                <div class="step-indicator">
                    <div class="step active" id="step1-btn" onclick="goStep(1)">
                        <i class="bi bi-info-circle"></i>Basic Info
                    </div>
                    <div class="step" id="step2-btn" onclick="goStep(2)">
                        <i class="bi bi-gear"></i>Details
                    </div>
                    <div class="step" id="step3-btn" onclick="goStep(3)">
                        <i class="bi bi-geo-alt"></i>Assignment
                    </div>
                    <div class="step" id="step4-btn" onclick="goStep(4)">
                        <i class="bi bi-images"></i>Photos
                    </div>
                </div>

                <form method="POST" id="registerForm" enctype="multipart/form-data" novalidate>

                    <!-- ══ STEP 1: Basic Info ══ -->
                    <div class="form-section active" id="section1">
                        <div class="form-card card">
                            <div class="card-header"><i class="bi bi-info-circle me-2"></i>Basic Information</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label required">Asset Name</label>
                                        <input type="text" class="form-control" name="asset_name" id="asset_name"
                                               placeholder="e.g. Dell Laptop Pro 15" required
                                               value="<?php echo htmlspecialchars($_POST['asset_name'] ?? ''); ?>">
                                        <div class="invalid-feedback">Asset name is required.</div>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Asset Tag <small class="text-muted">(auto-generated if blank)</small></label>
                                        <input type="text" class="form-control" name="asset_tag" id="asset_tag"
                                               placeholder="e.g. IT-2026-001"
                                               value="<?php echo htmlspecialchars($_POST['asset_tag'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Asset Class</label>
                                        <select class="form-select" name="asset_class" id="asset_class">
                                            <option value="">— Select Class —</option>
                                            <?php while($c = $asset_classes->fetch_assoc()): ?>
                                                <option value="<?php echo $c['class_id']; ?>"
                                                    <?php echo ($_POST['asset_class'] ?? '') == $c['class_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($c['class_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Status</label>
                                        <select class="form-select" name="asset_status" required>
                                            <?php
                                            $statuses = ['In Stock','Active','Assigned','Under Maintenance','Disposed'];
                                            foreach ($statuses as $s):
                                                $sel = ($_POST['asset_status'] ?? 'In Stock') == $s ? 'selected' : '';
                                            ?>
                                                <option value="<?php echo $s; ?>" <?php echo $sel; ?>><?php echo $s; ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Model</label>
                                        <input type="text" class="form-control" name="model"
                                               placeholder="e.g. XPS 15 9500"
                                               value="<?php echo htmlspecialchars($_POST['model'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Manufacturer</label>
                                        <input type="text" class="form-control" name="manufacturer"
                                               placeholder="e.g. Dell"
                                               value="<?php echo htmlspecialchars($_POST['manufacturer'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-end mt-2">
                                    <button type="button" class="btn btn-info" onclick="goStep(2)">
                                        Next <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STEP 2: Details ══ -->
                    <div class="form-section" id="section2">
                        <div class="form-card card">
                            <div class="card-header"><i class="bi bi-gear me-2"></i>Purchase & Warranty Details</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Serial Number</label>
                                        <input type="text" class="form-control" name="serial_number"
                                               placeholder="e.g. SN123456789"
                                               value="<?php echo htmlspecialchars($_POST['serial_number'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Purchase Order Number</label>
                                        <input type="text" class="form-control" name="purchase_order_number"
                                               placeholder="e.g. PO-2026-00123"
                                               value="<?php echo htmlspecialchars($_POST['purchase_order_number'] ?? ''); ?>">
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Vendor / Supplier</label>
                                        <input type="text" class="form-control" name="vendor"
                                               placeholder="e.g. Tech Solutions Sdn Bhd"
                                               value="<?php echo htmlspecialchars($_POST['vendor'] ?? ''); ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Cost (RM)</label>
                                        <div class="input-group">
                                            <span class="input-group-text">RM</span>
                                            <input type="number" step="0.01" min="0" class="form-control" name="cost"
                                                   placeholder="0.00"
                                                   value="<?php echo htmlspecialchars($_POST['cost'] ?? ''); ?>">
                                        </div>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Acquisition Date</label>
                                        <input type="date" class="form-control" name="acquisition_date"
                                               value="<?php echo $_POST['acquisition_date'] ?? ''; ?>">
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Warranty Expiry Date</label>
                                        <input type="date" class="form-control" name="warranty_expiry"
                                               value="<?php echo $_POST['warranty_expiry'] ?? ''; ?>">
                                    </div>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="goStep(1)">
                                        <i class="bi bi-arrow-left me-1"></i> Back
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="goStep(3)">
                                        Next <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STEP 3: Assignment ══ -->
                    <div class="form-section" id="section3">
                        <div class="form-card card">
                            <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Location & Assignment</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Location</label>
                                        <select class="form-select" name="location_id">
                                            <option value="">— Select Location —</option>
                                            <?php $locations->data_seek(0); while($l = $locations->fetch_assoc()): ?>
                                                <option value="<?php echo $l['location_id']; ?>"
                                                    <?php echo ($_POST['location_id'] ?? '') == $l['location_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($l['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Owner Department</label>
                                        <select class="form-select" name="owner_department_id">
                                            <option value="">— Select Department —</option>
                                            <?php while($d = $departments->fetch_assoc()): ?>
                                                <option value="<?php echo $d['department_id']; ?>"
                                                    <?php echo ($_POST['owner_department_id'] ?? '') == $d['department_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($d['name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="row">
                                    <div class="col-md-6 mb-3">
                                        <label class="form-label">Assigned To (User)</label>
                                        <select class="form-select" name="assigned_to_user_id">
                                            <option value="">— Unassigned —</option>
                                            <?php while($u = $users->fetch_assoc()): ?>
                                                <option value="<?php echo $u['user_id']; ?>"
                                                    <?php echo ($_POST['assigned_to_user_id'] ?? '') == $u['user_id'] ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($u['full_name']); ?>
                                                </option>
                                            <?php endwhile; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Remarks / Notes</label>
                                    <textarea class="form-control" name="remarks" rows="3"
                                              placeholder="Any additional notes about this asset..."><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                                </div>
                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="goStep(2)">
                                        <i class="bi bi-arrow-left me-1"></i> Back
                                    </button>
                                    <button type="button" class="btn btn-info" onclick="goStep(4)">
                                        Next <i class="bi bi-arrow-right ms-1"></i>
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ══ STEP 4: Photos ══ -->
                    <div class="form-section" id="section4">
                        <div class="form-card card">
                            <div class="card-header"><i class="bi bi-images me-2"></i>Asset Photos <small class="fw-normal opacity-75">(optional)</small></div>
                            <div class="card-body">
                                <div class="upload-area" id="uploadArea"
                                     onclick="document.getElementById('asset_images').click()">
                                    <i class="bi bi-cloud-upload"></i>
                                    <p class="fw-semibold mt-2 mb-1">Click to upload or drag &amp; drop</p>
                                    <small class="text-muted">JPG, PNG, GIF, WEBP — multiple files allowed. First image will be the primary photo.</small>
                                </div>
                                <input type="file" id="asset_images" name="asset_images[]"
                                       accept="image/*" multiple style="display:none"
                                       onchange="previewImages(this)">
                                <div class="image-preview" id="imagePreview"></div>

                                <hr class="section-divider">

                                <!-- Summary before submit -->
                                <div class="alert alert-info d-flex align-items-start gap-2 mb-3">
                                    <i class="bi bi-info-circle-fill fs-5 mt-1"></i>
                                    <div>
                                        <strong>Ready to register?</strong><br>
                                        Review the information across all steps before submitting.
                                        You can still go back to make changes.
                                    </div>
                                </div>

                                <div class="d-flex justify-content-between mt-2">
                                    <button type="button" class="btn btn-outline-secondary" onclick="goStep(3)">
                                        <i class="bi bi-arrow-left me-1"></i> Back
                                    </button>
                                    <button type="submit" class="btn btn-success btn-lg px-5" id="submitBtn">
                                        <i class="bi bi-check-circle me-2"></i>Register Asset
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>

                </form>
            </div><!-- /px-4 -->
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// ── Step navigation ───────────────────────────────────────────────────────
var currentStep = 1;

function goStep(n) {
    // Validate step 1 before advancing
    if (n > 1 && currentStep == 1) {
        var name = document.getElementById('asset_name').value.trim();
        if (!name) {
            document.getElementById('asset_name').classList.add('is-invalid');
            document.getElementById('asset_name').focus();
            return;
        }
        document.getElementById('asset_name').classList.remove('is-invalid');
    }

    // Hide all sections
    document.querySelectorAll('.form-section').forEach(s => s.classList.remove('active'));
    document.querySelectorAll('.step').forEach(s => s.classList.remove('active'));

    // Mark done steps
    for (var i = 1; i <= 4; i++) {
        var btn = document.getElementById('step' + i + '-btn');
        if (i < n) btn.classList.add('done');
        else        btn.classList.remove('done');
    }

    // Show target
    document.getElementById('section' + n).classList.add('active');
    document.getElementById('step'    + n + '-btn').classList.add('active');
    currentStep = n;
    window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ── Image preview ─────────────────────────────────────────────────────────
function previewImages(input) {
    var preview = document.getElementById('imagePreview');
    preview.innerHTML = '';
    Array.from(input.files).forEach(function(file, idx) {
        var reader = new FileReader();
        reader.onload = function(e) {
            var item = document.createElement('div');
            item.className = 'preview-item';
            item.innerHTML =
                (idx === 0 ? '<span style="position:absolute;bottom:0;left:0;right:0;background:rgba(102,126,234,.8);color:white;font-size:.65rem;text-align:center;padding:2px;">Primary</span>' : '') +
                '<img src="' + e.target.result + '" alt="Preview">' +
                '<button type="button" class="remove-preview" onclick="this.parentElement.remove()">×</button>';
            preview.appendChild(item);
        };
        reader.readAsDataURL(file);
    });
}

// ── Drag & Drop ───────────────────────────────────────────────────────────
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

// ── Prevent double-submit ─────────────────────────────────────────────────
document.getElementById('registerForm').addEventListener('submit', function() {
    var btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<span class="spinner-border spinner-border-sm me-2"></span>Registering...';
});
</script>
</body>
</html>
<?php $conn->close(); ?>
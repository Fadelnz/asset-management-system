<?php
require_once '../../includes/check_auth.php';
check_auth(['operation_team']);
require_once '../../includes/db.php';

$conn = $db->conn;
$user_id  = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'] ?? 'User';

$asset  = null;
$message = '';
$alert_type = '';
$serial_searched = '';

// ── STEP 1: Search asset by serial number ─────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'GET' && !empty($_GET['serial_number'])) {
    $serial_searched = trim($_GET['serial_number']);

    $sql = "SELECT a.*, ac.class_name, l.name AS location_name, u.full_name AS assigned_to_name
            FROM assets a
            LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
            LEFT JOIN locations l      ON a.location_id  = l.location_id
            LEFT JOIN users u          ON a.assigned_to_user_id = u.user_id
            WHERE a.serial_number = ? AND a.is_active = 1
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    $stmt->bind_param("s", $serial_searched);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 1) {
        $asset = $result->fetch_assoc();

        // If assigned to someone else (not me), only allow Return if it's assigned to me
        // Policy: you can Issue any In Stock asset, but only Return assets assigned to you
    } else {
        $message    = "No active asset found with serial number: " . htmlspecialchars($serial_searched);
        $alert_type = 'danger';
    }
    $stmt->close();
}

// ── STEP 2: Submit request ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['submit_request'])) {
    $asset_id   = trim($_POST['asset_id']);
    $action     = trim($_POST['action']);
    $condition  = trim($_POST['condition'] ?? '');
    $remarks    = trim($_POST['remarks'] ?? '');
    $issued_to  = trim($_POST['issued_to'] ?? $full_name); // default: requesting for themselves

    // Validation
    if (empty($action)) {
        $message = "Please select an action.";
        $alert_type = 'danger';
    } elseif ($action === 'Return' && empty($condition)) {
        $message = "Please select the condition of the asset being returned.";
        $alert_type = 'danger';
    } else {
        // Build combined remarks: who requested + original remarks
        $full_remarks = "Requested by: {$full_name}";
        if ($action === 'Issue') {
            $full_remarks .= " | For: {$issued_to}";
        } else {
            $full_remarks .= " | Condition: {$condition}";
        }
        if (!empty($remarks)) {
            $full_remarks .= " | Note: {$remarks}";
        }

        $movement_id = 'MOV' . date('YmdHis') . rand(100, 999);

        // Get current location of asset
        $loc_sql = "SELECT location_id FROM assets WHERE asset_id = ?";
        $loc_stmt = $conn->prepare($loc_sql);
        $loc_stmt->bind_param("s", $asset_id);
        $loc_stmt->execute();
        $loc_result = $loc_stmt->get_result()->fetch_assoc();
        $from_loc = $loc_result['location_id'] ?? null;
        $loc_stmt->close();

        $ins_sql = "INSERT INTO asset_movements 
                    (movement_id, asset_id, movement_type, performed_by_user_id, from_location_id, remarks, status)
                    VALUES (?, ?, ?, ?, ?, ?, 'Pending')";
        $ins_stmt = $conn->prepare($ins_sql);
        $ins_stmt->bind_param("ssssss", $movement_id, $asset_id, $action, $user_id, $from_loc, $full_remarks);

        if ($ins_stmt->execute()) {
            $message    = "✓ Your <strong>{$action}</strong> request has been submitted and is awaiting approval from the Logistics team.";
            $alert_type = 'success';
            $asset      = null;
            $serial_searched = '';
        } else {
            $message    = "Failed to submit request. Please try again.";
            $alert_type = 'danger';
        }
        $ins_stmt->close();
    }

    // Re-fetch asset on validation error
    if ($alert_type === 'danger' && !empty($_POST['asset_id'])) {
        $re_sql = "SELECT a.*, ac.class_name, l.name AS location_name, u.full_name AS assigned_to_name
                   FROM assets a
                   LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
                   LEFT JOIN locations l ON a.location_id = l.location_id
                   LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
                   WHERE a.asset_id = ? AND a.is_active = 1 LIMIT 1";
        $re_stmt = $conn->prepare($re_sql);
        $re_stmt->bind_param("s", $_POST['asset_id']);
        $re_stmt->execute();
        $asset = $re_stmt->get_result()->fetch_assoc();
        $re_stmt->close();
        $serial_searched = $asset['serial_number'] ?? '';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Issue / Return · Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .page-header {
            background: linear-gradient(135deg, #007bff 0%, #0056b3 100%);
            color: white;
            padding: 1.5rem 2rem;
            margin-bottom: 2rem;
        }
        .search-card, .asset-card-detail, .action-card {
            background: #fff;
            border-radius: 1.2rem;
            box-shadow: 0 4px 20px rgba(0,91,187,0.08);
            padding: 1.8rem 2rem;
            margin-bottom: 1.5rem;
            border: none;
        }
        .asset-card-detail { border-left: 5px solid #007bff; }
        .asset-image-box {
            background: #f0f5ff;
            border-radius: 1rem;
            padding: 1rem;
            text-align: center;
            border: 1px dashed #aac4f5;
            margin-top: 1rem;
        }
        .asset-image-box img { max-height: 200px; max-width: 100%; border-radius: .5rem; object-fit: contain; }
        .detail-label { font-size: 0.78rem; text-transform: uppercase; letter-spacing: 0.05em; color: #8892b0; margin-bottom: 0.1rem; }
        .detail-value { font-weight: 600; color: #1a2639; font-size: 1rem; }
        .form-control, .form-select {
            border: 1.5px solid #e2e8f0; border-radius: 12px;
            padding: 0.7rem 1.1rem; font-size: 0.95rem; background: #fcfdff;
            transition: border .15s, box-shadow .15s;
        }
        .form-control:focus, .form-select:focus {
            border-color: #007bff; box-shadow: 0 0 0 4px rgba(0,123,255,0.12); outline: none;
        }
        .form-label { font-weight: 500; font-size: 0.85rem; text-transform: uppercase; letter-spacing: 0.03em; color: #4b5a73; }
        .btn-search {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white; border: none; border-radius: 12px;
            padding: 0.7rem 1.5rem; font-weight: 600;
        }
        .btn-search:hover { opacity: .9; color: white; }
        .btn-submit {
            background: linear-gradient(135deg, #007bff, #0056b3);
            color: white; border: none; border-radius: 50px;
            padding: 0.85rem 2rem; font-weight: 600; width: 100%; font-size: 1rem;
        }
        .btn-submit:hover { opacity: .9; color: white; }
        .btn-submit:disabled { background: #b0b8cc; cursor: not-allowed; }
        .section-title { font-weight: 700; font-size: 1.05rem; color: #1a2639; margin-bottom: 1.2rem; display: flex; align-items: center; gap: 8px; }
        .section-title i { color: #007bff; }
        .alert-custom { border-radius: 50px; border: none; padding: 0.85rem 1.5rem; font-size: 0.9rem; display: flex; align-items: center; gap: 8px; }
        .alert-success-c { background: #e1f7ed; color: #0d683e; }
        .alert-danger-c  { background: #fef1f0; color: #ab2e2e; }
        .info-notice { background: #e8f1ff; border-radius: 14px; padding: 0.8rem 1.2rem; font-size: 0.85rem; color: #004a9f; margin-bottom: 1.5rem; display: flex; gap: 8px; }
        #returnFields, #issueFields { display: none; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'opsidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-3">

            <div class="page-header">
                <h2 class="mb-0"><i class="bi bi-arrow-left-right me-2"></i>Issue / Return Asset</h2>
                <p class="mb-0 mt-1 opacity-75">Search for an asset by serial number and submit your request.</p>
            </div>

            <!-- Notice -->
            <div class="info-notice">
                <i class="bi bi-info-circle-fill mt-1 flex-shrink-0"></i>
                <span>Your request will be sent to the <strong>Logistics Coordinator</strong> for approval. You will be able to track the status under <a href="history.php" class="fw-bold">My Requests</a>.</span>
            </div>

            <!-- Alert -->
            <?php if ($message): ?>
                <div class="alert-custom <?php echo $alert_type === 'success' ? 'alert-success-c' : 'alert-danger-c'; ?> mb-4">
                    <i class="bi <?php echo $alert_type === 'success' ? 'bi-check-circle-fill' : 'bi-exclamation-triangle-fill'; ?>"></i>
                    <span><?php echo $message; ?></span>
                </div>
            <?php endif; ?>

            <!-- Search -->
            <div class="search-card">
                <div class="section-title"><i class="bi bi-search"></i> Search Asset by Serial Number</div>
                <form method="GET" action="" class="d-flex gap-3 align-items-end flex-wrap">
                    <div class="flex-grow-1">
                        <label class="form-label">Serial Number</label>
                        <input type="text" name="serial_number" class="form-control"
                               placeholder="e.g. NCSBWT/03"
                               value="<?php echo htmlspecialchars($serial_searched); ?>"
                               required autofocus>
                    </div>
                    <button type="submit" class="btn-search">
                        <i class="bi bi-search me-1"></i> Confirm
                    </button>
                </form>
            </div>

            <?php if ($asset): ?>

            <!-- Asset Details -->
            <div class="asset-card-detail">
                <div class="section-title"><i class="bi bi-box-seam"></i> Asset Details</div>
                <div class="row g-3">
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Type</div>
                        <div class="detail-value"><?php echo htmlspecialchars($asset['class_name'] ?? $asset['asset_class'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Asset Name</div>
                        <div class="detail-value"><?php echo htmlspecialchars($asset['asset_name']); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Manufacturer / Model</div>
                        <div class="detail-value"><?php echo htmlspecialchars(trim(($asset['manufacturer'] ?? '') . ' ' . ($asset['model'] ?? '')) ?: 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Serial Number</div>
                        <div class="detail-value"><?php echo htmlspecialchars($asset['serial_number'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Current Location</div>
                        <div class="detail-value"><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Assigned To</div>
                        <div class="detail-value"><?php echo htmlspecialchars($asset['assigned_to_name'] ?? 'Not assigned'); ?></div>
                    </div>
                    <div class="col-sm-6 col-md-4">
                        <div class="detail-label">Available</div>
                        <div class="detail-value">
                            <?php if ($asset['asset_status'] === 'In Stock'): ?>
                                <span class="badge bg-success">Yes</span>
                            <?php else: ?>
                                <span class="badge bg-danger">No</span>
                            <?php endif; ?>
                            <small class="text-muted ms-1">(<?php echo htmlspecialchars($asset['asset_status']); ?>)</small>
                        </div>
                    </div>
                </div>
                <?php if (!empty($asset['image_path'])): ?>
                    <div class="asset-image-box">
                        <img src="../../<?php echo htmlspecialchars($asset['image_path']); ?>" alt="Asset">
                    </div>
                <?php endif; ?>
            </div>

            <!-- Action Form -->
            <div class="action-card">
                <div class="section-title"><i class="bi bi-send"></i> Submit Request</div>

                <form method="POST" action="">
                    <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($asset['asset_id']); ?>">

                    <div class="mb-3">
                        <label class="form-label">Action <span class="text-danger">*</span></label>
                        <select name="action" id="actionSelect" class="form-select" required>
                            <option value="">-- Select Action --</option>
                            <?php if ($asset['asset_status'] === 'In Stock'): ?>
                                <option value="Issue">Issue (Request to take this asset)</option>
                            <?php endif; ?>
                            <?php if ($asset['asset_status'] === 'Assigned'): ?>
                                <option value="Return">Return (Return this asset)</option>
                            <?php endif; ?>
                            <?php if (!in_array($asset['asset_status'], ['In Stock', 'Assigned'])): ?>
                                <option disabled>Asset not available for issue or return</option>
                            <?php endif; ?>
                        </select>
                        <small class="text-muted">
                            <?php if ($asset['asset_status'] === 'In Stock'): ?>
                                This asset is available to be issued.
                            <?php elseif ($asset['asset_status'] === 'Assigned'): ?>
                                This asset is currently assigned — you can submit a return request.
                            <?php else: ?>
                                This asset is <strong><?php echo htmlspecialchars($asset['asset_status']); ?></strong> and cannot be issued or returned at this time.
                            <?php endif; ?>
                        </small>
                    </div>

                    <!-- Issue Fields -->
                    <div id="issueFields">
                        <div class="mb-3">
                            <label class="form-label">Issuing To</label>
                            <input type="text" name="issued_to" class="form-control"
                                   placeholder="Your name or staff ID (leave blank for yourself)"
                                   value="<?php echo htmlspecialchars($_POST['issued_to'] ?? $full_name); ?>">
                            <small class="text-muted">Who will be using this asset?</small>
                        </div>
                    </div>

                    <!-- Return Fields -->
                    <div id="returnFields">
                        <div class="mb-3">
                            <label class="form-label">Condition on Return <span class="text-danger">*</span></label>
                            <select name="condition" class="form-select">
                                <option value="">-- Select Condition --</option>
                                <option value="Good" <?php echo (($_POST['condition'] ?? '') === 'Good') ? 'selected' : ''; ?>>Good</option>
                                <option value="Faulty" <?php echo (($_POST['condition'] ?? '') === 'Faulty') ? 'selected' : ''; ?>>Faulty</option>
                            </select>
                        </div>
                    </div>

                    <!-- Remarks -->
                    <div class="mb-4">
                        <label class="form-label">Remarks</label>
                        <textarea name="remarks" class="form-control" rows="3"
                                  placeholder="Any additional notes..."><?php echo htmlspecialchars($_POST['remarks'] ?? ''); ?></textarea>
                    </div>

                    <button type="submit" name="submit_request" class="btn-submit" id="submitBtn" disabled>
                        <i class="bi bi-send me-2"></i>Submit Request for Approval
                    </button>
                </form>
            </div>

            <?php endif; ?>
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
(function () {
    const actionSelect = document.getElementById('actionSelect');
    const submitBtn    = document.getElementById('submitBtn');
    const returnFields = document.getElementById('returnFields');
    const issueFields  = document.getElementById('issueFields');
    if (!actionSelect) return;

    function toggle() {
        const val = actionSelect.value;
        returnFields.style.display = val === 'Return' ? 'block' : 'none';
        issueFields.style.display  = val === 'Issue'  ? 'block' : 'none';
        submitBtn.disabled = !val;
    }

    actionSelect.addEventListener('change', toggle);
    toggle();
})();
</script>
</body>
</html>
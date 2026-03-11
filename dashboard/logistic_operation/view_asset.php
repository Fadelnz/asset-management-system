<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation', 'operation_manager', 'admin', 'accountant']);

require_once '../../includes/db.php';
$conn = $db->conn;

if (!isset($_GET['id'])) {
    header('Location: inventory_report.php');
    exit();
}

$asset_id = $_GET['id'];

// Fetch full asset details
$sql = "SELECT a.*, 
        ac.class_name,
        l.name as location_name,
        d.name as department_name,
        u.full_name as assigned_to_name,
        u2.full_name as created_by_name,
        u3.full_name as updated_by_name,
        CASE 
            WHEN a.warranty_expiry IS NULL THEN 'No Warranty'
            WHEN a.warranty_expiry < CURDATE() THEN 'Expired'
            WHEN a.warranty_expiry <= DATE_ADD(CURDATE(), INTERVAL 30 DAY) THEN 'Expiring Soon'
            ELSE 'Active'
        END as warranty_status,
        DATEDIFF(a.warranty_expiry, CURDATE()) as days_until_expiry
        FROM assets a
        LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN departments d ON a.owner_department_id = d.department_id
        LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
        LEFT JOIN users u2 ON a.created_by = u2.user_id
        LEFT JOIN users u3 ON a.updated_by = u3.user_id
        WHERE a.asset_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $asset_id);
$stmt->execute();
$asset = $stmt->get_result()->fetch_assoc();

if (!$asset) {
    header('Location: inventory_report.php');
    exit();
}

// Fetch asset images
$images = [];
$img_stmt = $conn->prepare("SELECT * FROM asset_images WHERE asset_id = ? ORDER BY is_primary DESC");
$img_stmt->bind_param("s", $asset_id);
$img_stmt->execute();
$img_res = $img_stmt->get_result();
while ($img = $img_res->fetch_assoc()) $images[] = $img;

// Fetch movement / activity history
$history = [];
$hist_sql = "SELECT am.*, u.full_name as performed_by,
             fl.name as from_location, tl.name as to_location
             FROM asset_movements am
             LEFT JOIN users u ON am.performed_by_user_id = u.user_id
             LEFT JOIN locations fl ON am.from_location_id = fl.location_id
             LEFT JOIN locations tl ON am.to_location_id = tl.location_id
             WHERE am.asset_id = ?
             ORDER BY am.movement_date DESC LIMIT 20";
$hist_stmt = $conn->prepare($hist_sql);
$hist_stmt->bind_param("s", $asset_id);
$hist_stmt->execute();
$hist_res = $hist_stmt->get_result();
while ($h = $hist_res->fetch_assoc()) $history[] = $h;

// Status color helper
function statusColor($s) {
    return match($s) {
        'Active','In Stock' => 'success',
        'Assigned'          => 'primary',
        'Under Maintenance','Maintenance' => 'warning',
        'Disposed','Retired'=> 'secondary',
        default             => 'info',
    };
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset Details - <?php echo htmlspecialchars($asset['asset_name']); ?></title>
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
        .detail-card { border:none; border-radius:12px; box-shadow:0 2px 12px rgba(0,0,0,.07); margin-bottom:24px; }
        .detail-card .card-header {
            background: linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            color:white; border-radius:12px 12px 0 0 !important; padding:14px 20px; font-weight:600;
        }
        .info-row { display:flex; justify-content:space-between; padding:10px 0; border-bottom:1px solid #f0f0f0; }
        .info-row:last-child { border-bottom:none; }
        .info-label { color:#6c757d; font-size:.875rem; font-weight:500; }
        .info-value { font-weight:600; color:#212529; text-align:right; max-width:60%; word-break:break-word; }
        .main-image { width:100%; height:280px; object-fit:cover; border-radius:10px; }
        .main-image-placeholder {
            width:100%; height:280px; border-radius:10px;
            background:linear-gradient(135deg,#667eea 0%,#764ba2 100%);
            display:flex; align-items:center; justify-content:center; color:white; font-size:5rem;
        }
        .thumb { width:65px; height:65px; object-fit:cover; border-radius:6px; cursor:pointer;
                 border:2px solid transparent; transition:border-color .2s; }
        .thumb:hover, .thumb.active { border-color:#764ba2; }
        .timeline { position:relative; padding-left:28px; }
        .timeline::before { content:''; position:absolute; left:9px; top:0; bottom:0; width:2px; background:#dee2e6; }
        .timeline-item { position:relative; margin-bottom:16px; }
        .timeline-dot { position:absolute; left:-25px; top:4px; width:12px; height:12px; border-radius:50%;
                        background:#764ba2; border:2px solid #fff; box-shadow:0 0 0 2px #764ba2; }
        .timeline-date { font-size:.75rem; color:#6c757d; }
        .timeline-title { font-weight:600; font-size:.88rem; }
        .timeline-body { font-size:.83rem; color:#555; }
        .stat-pill { display:inline-flex; align-items:center; gap:7px; background:#fff;
                     border:1px solid #e0e0e0; border-radius:50px; padding:7px 16px; font-size:.83rem; font-weight:600; }
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
                        <li class="breadcrumb-item active">Asset Details</li>
                    </ol>
                </nav>
                <h4 class="mb-0 fw-bold">
                    <i class="bi bi-box me-2 text-info"></i><?php echo htmlspecialchars($asset['asset_name']); ?>
                </h4>
            </div>
            <div class="d-flex gap-2">
                <a href="edit_asset.php?id=<?php echo $asset_id; ?>" class="btn btn-warning">
                    <i class="bi bi-pencil me-1"></i> Edit Asset
                </a>
                <a href="inventory_report.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>
        </div>

        <div class="px-4">

            <!-- Update success alert -->
            <?php if (isset($_GET['updated'])): ?>
            <div class="alert alert-success alert-dismissible fade show">
                <i class="bi bi-check-circle-fill me-2"></i>
                Asset updated successfully!
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
            <?php endif; ?>

            <!-- Warranty alert -->
            <?php if ($asset['warranty_status'] == 'Expired'): ?>
            <div class="alert alert-danger d-flex align-items-center mb-3">
                <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                <strong>Warranty Expired</strong>&nbsp;— expired on <?php echo date('d M Y', strtotime($asset['warranty_expiry'])); ?>.
            </div>
            <?php elseif ($asset['warranty_status'] == 'Expiring Soon'): ?>
            <div class="alert alert-warning d-flex align-items-center mb-3">
                <i class="bi bi-clock-fill me-2 fs-5"></i>
                <strong>Warranty Expiring Soon</strong>&nbsp;— <?php echo $asset['days_until_expiry']; ?> days left (<?php echo date('d M Y', strtotime($asset['warranty_expiry'])); ?>).
            </div>
            <?php endif; ?>

            <!-- Quick stat pills -->
            <div class="d-flex flex-wrap gap-2 mb-4">
                <span class="stat-pill">
                    <i class="bi bi-circle-fill text-<?php echo statusColor($asset['asset_status']); ?>"></i>
                    <?php echo htmlspecialchars($asset['asset_status']); ?>
                </span>
                <span class="stat-pill">
                    <i class="bi bi-tag text-info"></i>
                    <?php echo htmlspecialchars($asset['asset_tag'] ?? 'No Tag'); ?>
                </span>
                <span class="stat-pill">
                    <i class="bi bi-geo-alt text-danger"></i>
                    <?php echo htmlspecialchars($asset['location_name'] ?? 'No Location'); ?>
                </span>
                <?php if ($asset['cost']): ?>
                <span class="stat-pill">
                    <i class="bi bi-cash text-success"></i>
                    RM <?php echo number_format($asset['cost'], 2); ?>
                </span>
                <?php endif; ?>
            </div>

            <div class="row">
                <!-- LEFT -->
                <div class="col-lg-5">
                    <!-- Photos -->
                    <div class="detail-card card">
                        <div class="card-header"><i class="bi bi-images me-2"></i>Photos</div>
                        <div class="card-body">
                            <?php if (!empty($images)): ?>
                                <img id="mainPhoto"
                                     src="<?php echo htmlspecialchars('../../' . ltrim($images[0]['image_path'], './')); ?>"
                                     class="main-image mb-3" alt="Asset Photo">
                                <?php if (count($images) > 1): ?>
                                <div class="d-flex flex-wrap gap-2">
                                    <?php foreach ($images as $i => $img): ?>
                                    <img src="<?php echo htmlspecialchars('../../' . ltrim($img['image_path'], './')); ?>"
                                         class="thumb <?php echo $i === 0 ? 'active' : ''; ?>"
                                         onclick="switchPhoto(this)" alt="Photo <?php echo $i+1; ?>">
                                    <?php endforeach; ?>
                                </div>
                                <?php endif; ?>
                            <?php else: ?>
                                <div class="main-image-placeholder"><i class="bi bi-box"></i></div>
                                <p class="text-center text-muted mt-2 mb-0"><small>No photos uploaded</small></p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Identity -->
                    <div class="detail-card card">
                        <div class="card-header"><i class="bi bi-card-text me-2"></i>Identity</div>
                        <div class="card-body">
                            <div class="info-row"><span class="info-label">Asset ID</span><span class="info-value"><?php echo $asset['asset_id']; ?></span></div>
                            <div class="info-row"><span class="info-label">Asset Tag</span><span class="info-value"><?php echo htmlspecialchars($asset['asset_tag'] ?? 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Class</span><span class="info-value"><?php echo htmlspecialchars($asset['class_name'] ?? 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Serial Number</span><span class="info-value"><?php echo htmlspecialchars($asset['serial_number'] ?? 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Registered By</span><span class="info-value"><?php echo htmlspecialchars($asset['created_by_name'] ?? 'System'); ?></span></div>
                            <?php if ($asset['updated_by_name']): ?>
                            <div class="info-row"><span class="info-label">Last Updated By</span><span class="info-value"><?php echo htmlspecialchars($asset['updated_by_name']); ?></span></div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- RIGHT -->
                <div class="col-lg-7">
                    <!-- Specifications -->
                    <div class="detail-card card">
                        <div class="card-header"><i class="bi bi-gear me-2"></i>Specifications</div>
                        <div class="card-body">
                            <div class="row">
                                <div class="col-6">
                                    <div class="info-row"><span class="info-label">Model</span><span class="info-value"><?php echo htmlspecialchars($asset['model'] ?? 'N/A'); ?></span></div>
                                    <div class="info-row"><span class="info-label">Manufacturer</span><span class="info-value"><?php echo htmlspecialchars($asset['manufacturer'] ?? 'N/A'); ?></span></div>
                                    <div class="info-row"><span class="info-label">Vendor</span><span class="info-value"><?php echo htmlspecialchars($asset['vendor'] ?? 'N/A'); ?></span></div>
                                </div>
                                <div class="col-6">
                                    <div class="info-row"><span class="info-label">PO Number</span><span class="info-value"><?php echo htmlspecialchars($asset['purchase_order_number'] ?? 'N/A'); ?></span></div>
                                    <div class="info-row"><span class="info-label">Cost</span><span class="info-value"><?php echo $asset['cost'] ? 'RM ' . number_format($asset['cost'], 2) : 'N/A'; ?></span></div>
                                    <div class="info-row"><span class="info-label">Acquired</span><span class="info-value"><?php echo $asset['acquisition_date'] ? date('d M Y', strtotime($asset['acquisition_date'])) : 'N/A'; ?></span></div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Status & Assignment -->
                    <div class="detail-card card">
                        <div class="card-header"><i class="bi bi-person-check me-2"></i>Status & Assignment</div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Status</span>
                                <span class="info-value">
                                    <span class="badge bg-<?php echo statusColor($asset['asset_status']); ?> fs-6">
                                        <?php echo $asset['asset_status']; ?>
                                    </span>
                                </span>
                            </div>
                            <div class="info-row"><span class="info-label">Location</span><span class="info-value"><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Department</span><span class="info-value"><?php echo htmlspecialchars($asset['department_name'] ?? 'N/A'); ?></span></div>
                            <div class="info-row"><span class="info-label">Assigned To</span><span class="info-value"><?php echo htmlspecialchars($asset['assigned_to_name'] ?? 'Unassigned'); ?></span></div>
                        </div>
                    </div>

                    <!-- Warranty -->
                    <div class="detail-card card">
                        <div class="card-header"><i class="bi bi-shield-check me-2"></i>Warranty</div>
                        <div class="card-body">
                            <div class="info-row">
                                <span class="info-label">Warranty Status</span>
                                <span class="info-value">
                                    <?php $wc = match($asset['warranty_status']) {
                                        'Active' => 'success', 'Expiring Soon' => 'warning',
                                        'Expired' => 'danger', default => 'secondary'
                                    }; ?>
                                    <span class="badge bg-<?php echo $wc; ?>"><?php echo $asset['warranty_status']; ?></span>
                                </span>
                            </div>
                            <div class="info-row"><span class="info-label">Warranty Expiry</span>
                                <span class="info-value text-<?php echo $wc; ?>">
                                    <?php echo $asset['warranty_expiry'] ? date('d M Y', strtotime($asset['warranty_expiry'])) : 'N/A'; ?>
                                </span>
                            </div>
                            <?php if ($asset['warranty_expiry']): ?>
                            <div class="mt-3">
                                <?php $days = $asset['days_until_expiry']; ?>
                                <small class="text-muted">
                                    <?php echo $days < 0 ? abs($days) . ' days overdue' : $days . ' days remaining'; ?>
                                </small>
                                <div class="progress mt-1" style="height:7px;">
                                    <div class="progress-bar bg-<?php echo $wc; ?>"
                                         style="width:<?php echo max(0,min(100,100-($days/365*100))); ?>%"></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Remarks -->
            <?php if (!empty($asset['remarks'])): ?>
            <div class="detail-card card">
                <div class="card-header"><i class="bi bi-chat-left-text me-2"></i>Remarks</div>
                <div class="card-body">
                    <p class="mb-0"><?php echo nl2br(htmlspecialchars($asset['remarks'])); ?></p>
                </div>
            </div>
            <?php endif; ?>

            <!-- Movement History -->
            <div class="detail-card card">
                <div class="card-header d-flex justify-content-between align-items-center">
                    <span><i class="bi bi-clock-history me-2"></i>Movement History</span>
                    <span class="badge bg-light text-dark"><?php echo count($history); ?> records</span>
                </div>
                <div class="card-body">
                    <?php if (!empty($history)): ?>
                    <div class="timeline">
                        <?php foreach ($history as $h): ?>
                        <div class="timeline-item">
                            <div class="timeline-dot"></div>
                            <div class="timeline-date">
                                <?php echo $h['movement_date'] ? date('d M Y, H:i', strtotime($h['movement_date'])) : 'N/A'; ?>
                                · <?php echo htmlspecialchars($h['performed_by'] ?? 'System'); ?>
                            </div>
                            <div class="timeline-title">
                                <?php echo htmlspecialchars($h['movement_type'] ?? 'Movement'); ?>
                                <?php if ($h['from_location'] || $h['to_location']): ?>
                                <span class="fw-normal text-muted">
                                    — <?php echo htmlspecialchars($h['from_location'] ?? '?'); ?>
                                    <i class="bi bi-arrow-right mx-1"></i>
                                    <?php echo htmlspecialchars($h['to_location'] ?? '?'); ?>
                                </span>
                                <?php endif; ?>
                                <span class="badge bg-<?php echo $h['status'] == 'Completed' ? 'success' : 'warning'; ?> ms-1" style="font-size:.7rem;">
                                    <?php echo $h['status'] ?? ''; ?>
                                </span>
                            </div>
                            <?php if (!empty($h['remarks'])): ?>
                            <div class="timeline-body"><?php echo htmlspecialchars($h['remarks']); ?></div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                    <?php else: ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-inbox fs-2 d-block mb-2"></i>No movement history for this asset.
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Footer actions -->
            <div class="d-flex gap-2 mb-4">
                <a href="inventory_report.php" class="btn btn-outline-secondary"><i class="bi bi-arrow-left me-1"></i> Back</a>
                <a href="edit_asset.php?id=<?php echo $asset_id; ?>" class="btn btn-warning"><i class="bi bi-pencil me-1"></i> Edit Asset</a>
                <button onclick="window.print()" class="btn btn-outline-secondary"><i class="bi bi-printer me-1"></i> Print</button>
            </div>

        </div>
    </main>
</div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchPhoto(el) {
    document.getElementById('mainPhoto').src = el.src;
    document.querySelectorAll('.thumb').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}
</script>
</body>
</html>
<?php $conn->close(); ?>
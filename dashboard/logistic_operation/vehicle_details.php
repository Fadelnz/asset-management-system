<?php
require_once '../../includes/check_auth.php';
check_auth(['logistic_coordinator', 'it_operation']);

require_once '../../includes/db.php';
$conn = $db->conn;

// Get asset ID from URL
if (!isset($_GET['id'])) {
    header('Location: vehicle_management.php');
    exit();
}

$asset_id = $_GET['id'];

// Fetch vehicle details
$sql = "SELECT a.*, vd.license_plate, vd.vehicle_type, vd.fuel_type, vd.engine_capacity,
        vd.chassis_number, vd.color, vd.current_mileage, vd.last_service_date, vd.next_service_date,
        l.name as location_name,
        u.full_name as assigned_to_name
        FROM assets a
        LEFT JOIN vehicle_details vd ON a.asset_id = vd.asset_id
        LEFT JOIN locations l ON a.location_id = l.location_id
        LEFT JOIN users u ON a.assigned_to_user_id = u.user_id
        WHERE a.asset_id = ?";

$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $asset_id);
$stmt->execute();
$result = $stmt->get_result();
$vehicle = $result->fetch_assoc();

if (!$vehicle) {
    header('Location: vehicle_management.php');
    exit();
}

// Fetch vehicle images
$images = [];
$img_sql = "SELECT * FROM vehicle_images WHERE asset_id = ? ORDER BY is_primary DESC";
$img_stmt = $conn->prepare($img_sql);
$img_stmt->bind_param("s", $asset_id);
$img_stmt->execute();
$img_result = $img_stmt->get_result();
while ($img = $img_result->fetch_assoc()) {
    $images[] = $img;
}

// Fetch movement history
$movements = [];
$mov_sql = "SELECT am.*, u.full_name as performed_by,
            fl.name as from_location_name,
            tl.name as to_location_name
            FROM asset_movements am
            LEFT JOIN users u ON am.performed_by_user_id = u.user_id
            LEFT JOIN locations fl ON am.from_location_id = fl.location_id
            LEFT JOIN locations tl ON am.to_location_id = tl.location_id
            WHERE am.asset_id = ?
            ORDER BY am.movement_date DESC
            LIMIT 20";
$mov_stmt = $conn->prepare($mov_sql);
$mov_stmt->bind_param("s", $asset_id);
$mov_stmt->execute();
$mov_result = $mov_stmt->get_result();
while ($mov = $mov_result->fetch_assoc()) {
    $movements[] = $mov;
}

// Status color helper
function statusColor($status) {
    switch ($status) {
        case 'Available':
        case 'In Stock':   return 'success';
        case 'In Use':     return 'primary';
        case 'Maintenance': return 'warning';
        case 'Retired':    return 'secondary';
        default:           return 'info';
    }
}

// Service status
$next_service = $vehicle['next_service_date'] ?? null;
$service_warning = false;
$service_overdue = false;
if ($next_service) {
    $days_until = (strtotime($next_service) - time()) / 86400;
    if ($days_until < 0)        $service_overdue = true;
    elseif ($days_until <= 30)  $service_warning = true;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Vehicle Details - <?php echo htmlspecialchars($vehicle['license_plate'] ?? $vehicle['asset_id']); ?></title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body { background: #f4f6fb; }

        /* ── Sidebar ── */
        .sidebar {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            min-height: 100vh;
            padding-top: 20px;
        }
        .sidebar .nav-link { color: white; padding: 10px 15px; margin: 5px 0; }
        .sidebar .nav-link:hover  { background: rgba(255,255,255,0.1); }
        .sidebar .nav-link.active { background: rgba(255,255,255,0.2); }

        /* ── Page header ── */
        .content-header {
            background: #fff;
            padding: 20px 25px;
            border-bottom: 1px solid #dee2e6;
            margin-bottom: 25px;
        }

        /* ── Cards ── */
        .detail-card {
            border: none;
            border-radius: 12px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.07);
            margin-bottom: 24px;
        }
        .detail-card .card-header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 12px 12px 0 0 !important;
            padding: 14px 20px;
            font-weight: 600;
        }

        /* ── Info rows ── */
        .info-row { display: flex; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f0f0f0; }
        .info-row:last-child { border-bottom: none; }
        .info-label { color: #6c757d; font-size: 0.875rem; font-weight: 500; }
        .info-value { font-weight: 600; color: #212529; text-align: right; }

        /* ── Gallery ── */
        .main-image {
            width: 100%;
            height: 300px;
            object-fit: cover;
            border-radius: 10px;
            background: #eee;
        }
        .main-image-placeholder {
            width: 100%;
            height: 300px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-size: 5rem;
        }
        .thumb-img {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 6px;
            cursor: pointer;
            border: 2px solid transparent;
            transition: border-color 0.2s;
        }
        .thumb-img:hover, .thumb-img.active { border-color: #764ba2; }

        /* ── Timeline ── */
        .timeline { position: relative; padding-left: 30px; }
        .timeline::before {
            content: '';
            position: absolute;
            left: 10px;
            top: 0; bottom: 0;
            width: 2px;
            background: #dee2e6;
        }
        .timeline-item { position: relative; margin-bottom: 18px; }
        .timeline-dot {
            position: absolute;
            left: -26px;
            top: 4px;
            width: 14px;
            height: 14px;
            border-radius: 50%;
            background: #764ba2;
            border: 2px solid #fff;
            box-shadow: 0 0 0 2px #764ba2;
        }
        .timeline-date { font-size: 0.75rem; color: #6c757d; }
        .timeline-title { font-weight: 600; font-size: 0.9rem; }
        .timeline-body { font-size: 0.85rem; color: #555; }

        /* ── Stat pills ── */
        .stat-pill {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 50px;
            padding: 8px 18px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        .stat-pill i { font-size: 1.1rem; }

        /* ── Service alert ── */
        .service-overdue { border-left: 4px solid #dc3545; }
        .service-warning { border-left: 4px solid #ffc107; }
        .service-ok      { border-left: 4px solid #28a745; }
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
                    <li class="nav-item"><a class="nav-link" href="#"><i class="bi bi-plus-circle"></i> Register Assets</a></li>
                    <li class="nav-item"><a class="nav-link" href="inventory_report.php"><i class="bi bi-clipboard-data"></i> Inventory / Asset Reports</a></li>
                    <li class="nav-item"><a class="nav-link" href="approvals.php"><i class="bi bi-check-circle"></i> Approvals / Requests</a></li>
                    <li class="nav-item"><a class="nav-link active" href="vehicle_management.php"><i class="bi bi-truck"></i> Vehicle Management</a></li>
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
                            <li class="breadcrumb-item"><a href="vehicle_management.php" class="text-decoration-none">Vehicle Management</a></li>
                            <li class="breadcrumb-item active">Vehicle Details</li>
                        </ol>
                    </nav>
                    <h4 class="mb-0 fw-bold">
                        <i class="bi bi-truck me-2 text-danger"></i>
                        <?php echo htmlspecialchars($vehicle['asset_name'] ?? 'Vehicle Details'); ?>
                    </h4>
                </div>
                <a href="vehicle_management.php" class="btn btn-outline-secondary">
                    <i class="bi bi-arrow-left me-1"></i> Back
                </a>
            </div>

            <div class="px-4">

                <!-- ── Service alert banner ── -->
                <?php if ($service_overdue): ?>
                <div class="alert alert-danger service-overdue d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
                    <strong>Service Overdue!</strong>&nbsp;This vehicle's service was due on <?php echo date('d M Y', strtotime($next_service)); ?>. Please schedule maintenance immediately.
                </div>
                <?php elseif ($service_warning): ?>
                <div class="alert alert-warning service-warning d-flex align-items-center mb-3" role="alert">
                    <i class="bi bi-clock-fill me-2 fs-5"></i>
                    <strong>Service Due Soon.</strong>&nbsp;Next service is on <?php echo date('d M Y', strtotime($next_service)); ?>.
                </div>
                <?php endif; ?>

                <!-- ── Quick stat pills ── -->
                <div class="d-flex flex-wrap gap-2 mb-4">
                    <span class="stat-pill">
                        <i class="bi bi-circle-fill text-<?php echo statusColor($vehicle['asset_status']); ?>"></i>
                        <?php echo htmlspecialchars($vehicle['asset_status'] ?? 'Unknown'); ?>
                    </span>
                    <span class="stat-pill">
                        <i class="bi bi-speedometer2 text-primary"></i>
                        <?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km
                    </span>
                    <span class="stat-pill">
                        <i class="bi bi-geo-alt text-danger"></i>
                        <?php echo htmlspecialchars($vehicle['location_name'] ?? 'Unknown'); ?>
                    </span>
                    <span class="stat-pill">
                        <i class="bi bi-fuel-pump text-warning"></i>
                        <?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?>
                    </span>
                </div>

                <div class="row">

                    <!-- ── LEFT COLUMN ── -->
                    <div class="col-lg-5">

                        <!-- Vehicle Image -->
                        <div class="detail-card card">
                            <div class="card-header"><i class="bi bi-images me-2"></i>Vehicle Photos</div>
                            <div class="card-body">
                                <?php if (!empty($images)): ?>
                                    <img id="mainPhoto"
                                         src="<?php echo htmlspecialchars('../../' . ltrim($images[0]['image_path'], './')); ?>"
                                         class="main-image mb-3"
                                         alt="Vehicle Photo">
                                    <?php if (count($images) > 1): ?>
                                    <div class="d-flex flex-wrap gap-2">
                                        <?php foreach ($images as $i => $img): ?>
                                        <img src="<?php echo htmlspecialchars('../../' . ltrim($img['image_path'], './')); ?>"
                                             class="thumb-img <?php echo $i === 0 ? 'active' : ''; ?>"
                                             onclick="switchPhoto(this)"
                                             alt="Photo <?php echo $i+1; ?>">
                                        <?php endforeach; ?>
                                    </div>
                                    <?php endif; ?>
                                <?php else: ?>
                                    <div class="main-image-placeholder">
                                        <i class="bi bi-truck"></i>
                                    </div>
                                    <p class="text-center text-muted mt-2 mb-0"><small>No photos uploaded</small></p>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Registration & Identity -->
                        <div class="detail-card card">
                            <div class="card-header"><i class="bi bi-card-text me-2"></i>Registration & Identity</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">License Plate</span>
                                    <span class="info-value fs-5 text-danger"><?php echo htmlspecialchars($vehicle['license_plate'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Asset ID</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['asset_id']); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Asset Tag</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['asset_tag'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Serial Number</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['serial_number'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Chassis Number</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['chassis_number'] ?? 'N/A'); ?></span>
                                </div>
                            </div>
                        </div>

                    </div><!-- /LEFT -->

                    <!-- ── RIGHT COLUMN ── -->
                    <div class="col-lg-7">

                        <!-- Vehicle Specifications -->
                        <div class="detail-card card">
                            <div class="card-header"><i class="bi bi-gear me-2"></i>Vehicle Specifications</div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-6">
                                        <div class="info-row">
                                            <span class="info-label">Model</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['model'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Manufacturer</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['manufacturer'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Vehicle Type</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['vehicle_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Color</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['color'] ?? 'N/A'); ?></span>
                                        </div>
                                    </div>
                                    <div class="col-6">
                                        <div class="info-row">
                                            <span class="info-label">Fuel Type</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['fuel_type'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Engine Capacity</span>
                                            <span class="info-value"><?php echo htmlspecialchars($vehicle['engine_capacity'] ?? 'N/A'); ?></span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Current Mileage</span>
                                            <span class="info-value"><?php echo number_format($vehicle['current_mileage'] ?? 0); ?> km</span>
                                        </div>
                                        <div class="info-row">
                                            <span class="info-label">Acquisition Date</span>
                                            <span class="info-value">
                                                <?php echo $vehicle['acquisition_date'] ? date('d M Y', strtotime($vehicle['acquisition_date'])) : 'N/A'; ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Status & Location -->
                        <div class="detail-card card">
                            <div class="card-header"><i class="bi bi-geo-alt me-2"></i>Status & Location</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">Current Status</span>
                                    <span class="info-value">
                                        <span class="badge bg-<?php echo statusColor($vehicle['asset_status']); ?> fs-6">
                                            <?php echo htmlspecialchars($vehicle['asset_status'] ?? 'Unknown'); ?>
                                        </span>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Location</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['location_name'] ?? 'N/A'); ?></span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Assigned To</span>
                                    <span class="info-value"><?php echo htmlspecialchars($vehicle['assigned_to_name'] ?? 'Unassigned'); ?></span>
                                </div>
                            </div>
                        </div>

                        <!-- Service Information -->
                        <div class="detail-card card <?php echo $service_overdue ? 'border-danger' : ($service_warning ? 'border-warning' : ''); ?>">
                            <div class="card-header"><i class="bi bi-tools me-2"></i>Service Information</div>
                            <div class="card-body">
                                <div class="info-row">
                                    <span class="info-label">Last Service Date</span>
                                    <span class="info-value">
                                        <?php echo $vehicle['last_service_date'] ? date('d M Y', strtotime($vehicle['last_service_date'])) : 'N/A'; ?>
                                    </span>
                                </div>
                                <div class="info-row">
                                    <span class="info-label">Next Service Date</span>
                                    <span class="info-value <?php echo $service_overdue ? 'text-danger' : ($service_warning ? 'text-warning' : 'text-success'); ?>">
                                        <?php if ($next_service): ?>
                                            <?php echo date('d M Y', strtotime($next_service)); ?>
                                            <?php if ($service_overdue): ?>
                                                <i class="bi bi-exclamation-triangle-fill text-danger ms-1"></i>
                                            <?php elseif ($service_warning): ?>
                                                <i class="bi bi-clock-fill text-warning ms-1"></i>
                                            <?php else: ?>
                                                <i class="bi bi-check-circle-fill text-success ms-1"></i>
                                            <?php endif; ?>
                                        <?php else: ?>
                                            N/A
                                        <?php endif; ?>
                                    </span>
                                </div>
                                <?php if ($next_service): ?>
                                <div class="mt-3">
                                    <?php
                                        $days = ceil((strtotime($next_service) - time()) / 86400);
                                        $pct  = max(0, min(100, 100 - ($days / 180 * 100)));
                                        $bar  = $service_overdue ? 'bg-danger' : ($service_warning ? 'bg-warning' : 'bg-success');
                                    ?>
                                    <small class="text-muted">
                                        <?php echo $service_overdue
                                            ? abs($days) . ' days overdue'
                                            : $days . ' days until next service'; ?>
                                    </small>
                                    <div class="progress mt-1" style="height:8px;">
                                        <div class="progress-bar <?php echo $bar; ?>" style="width:<?php echo $pct; ?>%"></div>
                                    </div>
                                </div>
                                <?php endif; ?>
                            </div>
                        </div>

                    </div><!-- /RIGHT -->
                </div><!-- /row -->

                <!-- ── MOVEMENT HISTORY ── -->
                <div class="detail-card card">
                    <div class="card-header d-flex justify-content-between align-items-center">
                        <span><i class="bi bi-clock-history me-2"></i>Movement History</span>
                        <span class="badge bg-light text-dark"><?php echo count($movements); ?> records</span>
                    </div>
                    <div class="card-body">
                        <?php if (!empty($movements)): ?>
                        <div class="timeline">
                            <?php foreach ($movements as $mov): ?>
                            <div class="timeline-item">
                                <div class="timeline-dot"></div>
                                <div class="timeline-date">
                                    <?php echo $mov['movement_date'] ? date('d M Y, H:i', strtotime($mov['movement_date'])) : 'N/A'; ?>
                                    &nbsp;·&nbsp; by <?php echo htmlspecialchars($mov['performed_by'] ?? 'System'); ?>
                                </div>
                                <div class="timeline-title">
                                    <?php echo htmlspecialchars($mov['movement_type'] ?? 'Movement'); ?>
                                    <?php if ($mov['from_location_name'] || $mov['to_location_name']): ?>
                                        <span class="fw-normal text-muted">
                                            — <?php echo htmlspecialchars($mov['from_location_name'] ?? '?'); ?>
                                            <i class="bi bi-arrow-right mx-1"></i>
                                            <?php echo htmlspecialchars($mov['to_location_name'] ?? '?'); ?>
                                        </span>
                                    <?php endif; ?>
                                    <span class="badge bg-<?php echo $mov['status'] == 'Completed' ? 'success' : 'warning'; ?> ms-2" style="font-size:0.7rem;">
                                        <?php echo htmlspecialchars($mov['status'] ?? ''); ?>
                                    </span>
                                </div>
                                <?php if (!empty($mov['remarks'])): ?>
                                <div class="timeline-body"><?php echo htmlspecialchars($mov['remarks']); ?></div>
                                <?php endif; ?>
                            </div>
                            <?php endforeach; ?>
                        </div>
                        <?php else: ?>
                        <div class="text-center text-muted py-4">
                            <i class="bi bi-inbox fs-2 d-block mb-2"></i>
                            No movement history found for this vehicle.
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- ── FOOTER ACTIONS ── -->
                <div class="d-flex gap-2 mb-4">
                    <a href="vehicle_management.php" class="btn btn-outline-secondary">
                        <i class="bi bi-arrow-left me-1"></i> Back to Vehicles
                    </a>
                    <button class="btn btn-outline-primary" onclick="window.print()">
                        <i class="bi bi-printer me-1"></i> Print
                    </button>
                </div>

            </div><!-- /px-4 -->
        </main>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function switchPhoto(el) {
    document.getElementById('mainPhoto').src = el.src;
    document.querySelectorAll('.thumb-img').forEach(t => t.classList.remove('active'));
    el.classList.add('active');
}
</script>
</body>
</html>
<?php $conn->close(); ?>
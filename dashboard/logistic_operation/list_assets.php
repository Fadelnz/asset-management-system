<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php';

check_auth(['logistic_coordinator','it_operation']);

$message = '';
$error = '';
$search_query = '';
$filter_class = '';
$filter_location = '';
$filter_status = '';

// Handle search and filter parameters
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $search_query = isset($_GET['search']) ? trim($_GET['search']) : '';
    $filter_class = isset($_GET['class']) ? $_GET['class'] : '';
    $filter_location = isset($_GET['location']) ? $_GET['location'] : '';
    $filter_status = isset($_GET['status']) ? $_GET['status'] : '';
}

// Handle asset deletion
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_asset'])) {
    try {
        $asset_id = $db->sanitize($_POST['asset_id']);
        
        // Check if asset exists
        $check_sql = "SELECT asset_name FROM assets WHERE asset_id = :asset_id";
        $check_stmt = $db->pdo->prepare($check_sql);
        $check_stmt->execute([':asset_id' => $asset_id]);
        $asset = $check_stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($asset) {
            // Get asset image path
            $image_sql = "SELECT image_path FROM assets WHERE asset_id = :asset_id";
            $image_stmt = $db->pdo->prepare($image_sql);
            $image_stmt->execute([':asset_id' => $asset_id]);
            $image_data = $image_stmt->fetch(PDO::FETCH_ASSOC);
            
            // Delete asset
            $delete_sql = "DELETE FROM assets WHERE asset_id = :asset_id";
            $delete_stmt = $db->pdo->prepare($delete_sql);
            $result = $delete_stmt->execute([':asset_id' => $asset_id]);
            
            if ($result) {
                // Delete associated image file if exists
                if ($image_data && !empty($image_data['image_path'])) {
                    $image_path = '../../' . $image_data['image_path'];
                    if (file_exists($image_path)) {
                        unlink($image_path);
                    }
                }
                
                // Record in asset history
                $history_id = 'HIST' . date('YmdHis') . rand(100, 999);
                $current_user = isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'SYSTEM';
                $change_summary = "Asset deleted: " . $asset['asset_name'];
                
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
                        ':change_type' => 'DELETE',
                        ':summary' => $change_summary
                    ]);
                } catch (Exception $e) {
                    // History table might not exist, ignore error
                    error_log("History record failed: " . $e->getMessage());
                }
                
                $message = "Asset deleted successfully!";
            } else {
                throw new Exception("Failed to delete asset");
            }
        } else {
            throw new Exception("Asset not found");
        }
    } catch (Exception $e) {
        $error = $e->getMessage();
    }
}

// Fetch asset classes for filter dropdown
$asset_classes = [];
$classes_query = "SELECT class_id, class_name FROM asset_classes ORDER BY class_name";
$classes_result = $db->conn->query($classes_query);
if ($classes_result) {
    while ($row = $classes_result->fetch_assoc()) {
        $asset_classes[] = $row;
    }
}

// Fetch locations for filter dropdown
$locations = [];
$locations_query = "SELECT location_id, name FROM locations ORDER BY name";
$locations_result = $db->conn->query($locations_query);
if ($locations_result) {
    while ($row = $locations_result->fetch_assoc()) {
        $locations[] = $row;
    }
}

// Build the query with filters
$sql = "SELECT 
    a.*, 
    ac.class_name,
    l.name as location_name
FROM assets a
LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
LEFT JOIN locations l ON a.location_id = l.location_id
WHERE 1=1";

$params = [];

// Apply search filter
if (!empty($search_query)) {
    $sql .= " AND (
        a.asset_id LIKE :search OR 
        a.asset_tag LIKE :search OR 
        a.asset_name LIKE :search OR 
        a.model LIKE :search OR 
        a.serial_number LIKE :search OR 
        a.manufacturer LIKE :search
    )";
    $params[':search'] = "%$search_query%";
}

// Apply class filter
if (!empty($filter_class)) {
    $sql .= " AND a.asset_class = :class";
    $params[':class'] = $filter_class;
}

// Apply location filter
if (!empty($filter_location)) {
    $sql .= " AND a.location_id = :location";
    $params[':location'] = $filter_location;
}

// Apply status filter (example: based on warranty expiry)
if (!empty($filter_status)) {
    $today = date('Y-m-d');
    if ($filter_status === 'active') {
        $sql .= " AND (a.warranty_expiry IS NULL OR a.warranty_expiry >= :today)";
        $params[':today'] = $today;
    } elseif ($filter_status === 'expired') {
        $sql .= " AND a.warranty_expiry < :today";
        $params[':today'] = $today;
    }
}

// Order by
$sql .= " ORDER BY a.created_at DESC, a.asset_id DESC";

// Prepare and execute query
$stmt = $db->pdo->prepare($sql);
$stmt->execute($params);
$assets = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Get total count for statistics
$count_sql = "SELECT 
    COUNT(*) as total,
    SUM(CASE WHEN warranty_expiry < CURDATE() THEN 1 ELSE 0 END) as expired_warranty,
    SUM(CASE WHEN warranty_expiry >= CURDATE() OR warranty_expiry IS NULL THEN 1 ELSE 0 END) as active_warranty,
    ROUND(AVG(cost), 2) as avg_cost,
    SUM(cost) as total_value
FROM assets";
$count_stmt = $db->pdo->query($count_sql);
$stats = $count_stmt->fetch(PDO::FETCH_ASSOC);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Asset List - Warehouse Dashboard</title>
    <!-- Bootstrap 5 CSS -->
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <!-- Bootstrap Icons -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <!-- DataTables CSS -->
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
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
        .stat-card {
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            color: white;
        }
        .stat-card.total { background: linear-gradient(45deg, #667eea, #764ba2); }
        .stat-card.active { background: linear-gradient(45deg, #4facfe, #00f2fe); }
        .stat-card.expired { background: linear-gradient(45deg, #f093fb, #f5576c); }
        .stat-card.value { background: linear-gradient(45deg, #43e97b, #38f9d7); }
        .asset-image {
            width: 50px;
            height: 50px;
            object-fit: cover;
            border-radius: 5px;
        }
        .action-buttons .btn {
            padding: 0.25rem 0.5rem;
            margin: 0 2px;
        }
        .dataTables_wrapper {
            margin-top: 20px;
        }
        .badge-warranty {
            font-size: 0.75em;
        }
        .search-box {
            max-width: 300px;
        }
        .filter-container {
            background: white;
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
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
                            <h1 class="h2"><i class="bi bi-boxes"></i> Asset Inventory</h1>
                            <p class="mb-0">Manage and track all assets in the system</p>
                        </div>
                        <div>
                            <a href="reg_assets.php" class="btn btn-success">
                                <i class="bi bi-plus-circle"></i> Add New Asset
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

                <!-- Statistics Cards -->
                <div class="row mt-4">
                    <div class="col-md-3">
                        <div class="stat-card total">
                            <h6>Total Assets</h6>
                            <h3><?php echo $stats['total'] ?? 0; ?></h3>
                            <small><i class="bi bi-box-seam"></i> All registered assets</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card active">
                            <h6>Active Warranty</h6>
                            <h3><?php echo $stats['active_warranty'] ?? 0; ?></h3>
                            <small><i class="bi bi-shield-check"></i> Under warranty</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card expired">
                            <h6>Expired Warranty</h6>
                            <h3><?php echo $stats['expired_warranty'] ?? 0; ?></h3>
                            <small><i class="bi bi-shield-exclamation"></i> Warranty expired</small>
                        </div>
                    </div>
                    <div class="col-md-3">
                        <div class="stat-card value">
                            <h6>Total Value</h6>
                            <h3>RM <?php echo number_format($stats['total_value'] ?? 0, 2); ?></h3>
                            <small><i class="bi bi-cash-stack"></i> Total asset value</small>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="filter-container mt-4">
                    <form method="GET" action="" class="row g-3">
                        <div class="col-md-4">
                            <label for="search" class="form-label">Search Assets</label>
                            <div class="input-group">
                                <input type="text" class="form-control" id="search" name="search" 
                                       placeholder="Search by name, tag, serial..." value="<?php echo htmlspecialchars($search_query); ?>">
                                <button class="btn btn-primary" type="submit">
                                    <i class="bi bi-search"></i>
                                </button>
                            </div>
                        </div>
                        <div class="col-md-2">
                            <label for="class" class="form-label">Asset Class</label>
                            <select class="form-select" id="class" name="class">
                                <option value="">All Classes</option>
                                <?php foreach ($asset_classes as $class): ?>
                                    <option value="<?php echo htmlspecialchars($class['class_id']); ?>" 
                                        <?php echo ($filter_class == $class['class_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($class['class_name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="location" class="form-label">Location</label>
                            <select class="form-select" id="location" name="location">
                                <option value="">All Locations</option>
                                <?php foreach ($locations as $location): ?>
                                    <option value="<?php echo htmlspecialchars($location['location_id']); ?>"
                                        <?php echo ($filter_location == $location['location_id']) ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($location['name']); ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-2">
                            <label for="status" class="form-label">Warranty Status</label>
                            <select class="form-select" id="status" name="status">
                                <option value="">All Status</option>
                                <option value="active" <?php echo ($filter_status == 'active') ? 'selected' : ''; ?>>Active</option>
                                <option value="expired" <?php echo ($filter_status == 'expired') ? 'selected' : ''; ?>>Expired</option>
                            </select>
                        </div>
                        <div class="col-md-2 d-flex align-items-end">
                            <div class="btn-group w-100">
                                <button type="submit" class="btn btn-primary">
                                    <i class="bi bi-filter"></i> Apply Filters
                                </button>
                                <a href="list_assets.php" class="btn btn-outline-secondary">
                                    <i class="bi bi-arrow-clockwise"></i> Reset
                                </a>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Assets Table -->
                <div class="card mt-4">
                    <div class="card-header bg-primary text-white d-flex justify-content-between align-items-center">
                        <h5 class="mb-0"><i class="bi bi-table"></i> Asset List</h5>
                        <div>
                            <span class="badge bg-light text-dark">
                                Showing <?php echo count($assets); ?> assets
                            </span>
                        </div>
                    </div>
                    <div class="card-body">
                        <?php if (empty($assets)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-inbox" style="font-size: 3rem; color: #6c757d;"></i>
                                <h4 class="mt-3">No assets found</h4>
                                <p class="text-muted">No assets match your search criteria or no assets have been registered yet.</p>
                                <a href="register_asset.php" class="btn btn-primary mt-2">
                                    <i class="bi bi-plus-circle"></i> Add Your First Asset
                                </a>
                            </div>
                        <?php else: ?>
                            <div class="table-responsive">
                                <table id="assetsTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Image</th>
                                            <th>Asset ID</th>
                                            <th>Asset Name</th>
                                            <th>Tag</th>
                                            <th>Class</th>
                                            <th>Location</th>
                                            <th>Cost</th>
                                            <th>Warranty</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($assets as $asset): 
                                            $warranty_status = '';
                                            if (!empty($asset['warranty_expiry'])) {
                                                $expiry_date = new DateTime($asset['warranty_expiry']);
                                                $today = new DateTime();
                                                if ($expiry_date < $today) {
                                                    $warranty_status = '<span class="badge bg-danger badge-warranty">Expired</span>';
                                                } else {
                                                    $days_left = $today->diff($expiry_date)->days;
                                                    if ($days_left <= 30) {
                                                        $warranty_status = '<span class="badge bg-warning text-dark badge-warranty">Expiring Soon</span>';
                                                    } else {
                                                        $warranty_status = '<span class="badge bg-success badge-warranty">Active</span>';
                                                    }
                                                }
                                            }
                                        ?>
                                            <tr>
                                                <td>
                                                    <?php if (!empty($asset['image_path'])): ?>
                                                        <img src="../../<?php echo htmlspecialchars($asset['image_path']); ?>" 
                                                             alt="Asset Image" 
                                                             class="asset-image"
                                                             data-bs-toggle="tooltip" 
                                                             title="View Image">
                                                    <?php else: ?>
                                                        <div class="asset-image bg-light d-flex align-items-center justify-content-center">
                                                            <i class="bi bi-image text-muted"></i>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong>
                                                </td>
                                                <td>
                                                    <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong>
                                                    <?php if (!empty($asset['model'])): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($asset['model']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($asset['asset_tag'])): ?>
                                                        <span class="badge bg-info"><?php echo htmlspecialchars($asset['asset_tag']); ?></span>
                                                    <?php else: ?>
                                                        <span class="text-muted">-</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo htmlspecialchars($asset['class_name'] ?? 'N/A'); ?></td>
                                                <td><?php echo htmlspecialchars($asset['location_name'] ?? 'N/A'); ?></td>
                                                <td>
                                                    <strong>RM <?php echo number_format($asset['cost'], 2); ?></strong>
                                                    <?php if ($asset['depreciation_method'] && $asset['depreciation_method'] !== 'None'): ?>
                                                        <br><small class="text-muted"><?php echo htmlspecialchars($asset['depreciation_method']); ?></small>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php if (!empty($asset['warranty_expiry'])): ?>
                                                        <?php echo date('d/m/Y', strtotime($asset['warranty_expiry'])); ?>
                                                        <br><?php echo $warranty_status; ?>
                                                    <?php else: ?>
                                                        <span class="text-muted">No warranty</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td class="action-buttons">
                                                    <!-- View Details -->
                                                    <a href="view_asset.php?id=<?php echo urlencode($asset['asset_id']); ?>" 
                                                       class="btn btn-sm btn-info" 
                                                       data-bs-toggle="tooltip" 
                                                       title="View Details">
                                                        <i class="bi bi-eye"></i>
                                                    </a>
                                                    
                                                    <!-- Edit Asset -->
                                                    <a href="edit_asset.php?id=<?php echo urlencode($asset['asset_id']); ?>" 
                                                       class="btn btn-sm btn-warning" 
                                                       data-bs-toggle="tooltip" 
                                                       title="Edit Asset">
                                                        <i class="bi bi-pencil"></i>
                                                    </a>
                                                    
                                                    <!-- Delete Asset (with confirmation) -->
                                                    <button type="button" 
                                                            class="btn btn-sm btn-danger" 
                                                            data-bs-toggle="modal" 
                                                            data-bs-target="#deleteModal<?php echo $asset['asset_id']; ?>"
                                                            title="Delete Asset">
                                                        <i class="bi bi-trash"></i>
                                                    </button>
                                                    
                                                    <!-- Delete Confirmation Modal -->
                                                    <div class="modal fade" id="deleteModal<?php echo $asset['asset_id']; ?>" tabindex="-1">
                                                        <div class="modal-dialog">
                                                            <div class="modal-content">
                                                                <div class="modal-header bg-danger text-white">
                                                                    <h5 class="modal-title">
                                                                        <i class="bi bi-exclamation-triangle"></i> Confirm Delete
                                                                    </h5>
                                                                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                                                                </div>
                                                                <div class="modal-body">
                                                                    <p>Are you sure you want to delete the following asset?</p>
                                                                    <div class="alert alert-warning">
                                                                        <strong><?php echo htmlspecialchars($asset['asset_name']); ?></strong><br>
                                                                        ID: <?php echo htmlspecialchars($asset['asset_id']); ?><br>
                                                                        Class: <?php echo htmlspecialchars($asset['class_name'] ?? 'N/A'); ?>
                                                                    </div>
                                                                    <p class="text-danger">
                                                                        <i class="bi bi-exclamation-circle"></i> 
                                                                        This action cannot be undone. All associated data will be permanently deleted.
                                                                    </p>
                                                                </div>
                                                                <div class="modal-footer">
                                                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                                                                    <form method="POST" action="" style="display: inline;">
                                                                        <input type="hidden" name="asset_id" value="<?php echo htmlspecialchars($asset['asset_id']); ?>">
                                                                        <button type="submit" name="delete_asset" class="btn btn-danger">
                                                                            <i class="bi bi-trash"></i> Delete Asset
                                                                        </button>
                                                                    </form>
                                                                </div>
                                                            </div>
                                                        </div>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Export Options -->
                <?php if (!empty($assets)): ?>
                    <div class="card mt-4">
                        <div class="card-body">
                            <div class="d-flex justify-content-between align-items-center">
                                <div>
                                    <h6 class="mb-0"><i class="bi bi-download"></i> Export Assets</h6>
                                    <small class="text-muted">Export current filtered list</small>
                                </div>
                                <div class="btn-group">
                                    <a href="export_assets.php?format=csv&search=<?php echo urlencode($search_query); ?>&class=<?php echo urlencode($filter_class); ?>&location=<?php echo urlencode($filter_location); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                       class="btn btn-outline-primary">
                                        <i class="bi bi-file-earmark-excel"></i> CSV
                                    </a>
                                    <a href="export_assets.php?format=pdf&search=<?php echo urlencode($search_query); ?>&class=<?php echo urlencode($filter_class); ?>&location=<?php echo urlencode($filter_location); ?>&status=<?php echo urlencode($filter_status); ?>" 
                                       class="btn btn-outline-danger">
                                        <i class="bi bi-file-earmark-pdf"></i> PDF
                                    </a>
                                    <button onclick="window.print()" class="btn btn-outline-secondary">
                                        <i class="bi bi-printer"></i> Print
                                    </button>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <!-- jQuery -->
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <!-- DataTables -->
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#assetsTable').DataTable({
                "pageLength": 25,
                "order": [[1, 'desc']], // Sort by Asset ID by default
                "language": {
                    "search": "Search within table:",
                    "lengthMenu": "Show _MENU_ entries",
                    "info": "Showing _START_ to _END_ of _TOTAL_ assets",
                    "infoEmpty": "Showing 0 to 0 of 0 assets",
                    "infoFiltered": "(filtered from _MAX_ total assets)",
                    "zeroRecords": "No matching assets found"
                },
                "dom": '<"row"<"col-sm-12 col-md-6"l><"col-sm-12 col-md-6"f>>tip',
                "columnDefs": [
                    { "orderable": false, "targets": [0, 8] } // Disable sorting on image and actions columns
                ]
            });
            
            // Initialize tooltips
            var tooltipTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'))
            var tooltipList = tooltipTriggerList.map(function (tooltipTriggerEl) {
                return new bootstrap.Tooltip(tooltipTriggerEl)
            });
            
            // Auto-refresh page every 5 minutes to show updated data
            setTimeout(function() {
                location.reload();
            }, 300000); // 5 minutes
        });
        
        // Image preview modal
        document.addEventListener('click', function(e) {
            if (e.target.classList.contains('asset-image')) {
                e.preventDefault();
                var imgSrc = e.target.src;
                var modalHtml = `
                    <div class="modal fade" id="imageModal" tabindex="-1">
                        <div class="modal-dialog modal-dialog-centered modal-lg">
                            <div class="modal-content">
                                <div class="modal-body text-center p-0">
                                    <img src="${imgSrc}" class="img-fluid" style="max-height: 80vh;">
                                </div>
                                <div class="modal-footer">
                                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Close</button>
                                    <a href="${imgSrc}" download class="btn btn-primary">
                                        <i class="bi bi-download"></i> Download
                                    </a>
                                </div>
                            </div>
                        </div>
                    </div>
                `;
                document.body.insertAdjacentHTML('beforeend', modalHtml);
                var modal = new bootstrap.Modal(document.getElementById('imageModal'));
                modal.show();
                document.getElementById('imageModal').addEventListener('hidden.bs.modal', function () {
                    this.remove();
                });
            }
        });
    </script>
</body>
</html>
<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php';

check_auth(['logistic_coordinator','it_operation']);

// Get asset ID from URL
$asset_id = isset($_GET['id']) ? trim($_GET['id']) : '';

if (empty($asset_id)) {
    header('Location: list_assets.php');
    exit();
}

try {
    // Fetch asset details with related information
    $sql = "SELECT 
        a.*,
        ac.class_name,
        l.name as location_name,
        u.full_name as created_by_name
    FROM assets a
    LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
    LEFT JOIN locations l ON a.location_id = l.location_id
    LEFT JOIN users u ON a.created_by = u.user_id
    WHERE a.asset_id = :asset_id";
    
    $stmt = $db->pdo->prepare($sql);
    $stmt->execute([':asset_id' => $asset_id]);
    $asset = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$asset) {
        header('Location: list_assets.php');
        exit();
    }
    
    // Fetch asset history
    $history_sql = "SELECT 
        ah.*,
        u.full_name as changed_by_name
    FROM asset_history ah
    LEFT JOIN users u ON ah.changed_by_user_id = u.user_id
    WHERE ah.asset_id = :asset_id
    ORDER BY ah.changed_at DESC";
    
    $history_stmt = $db->pdo->prepare($history_sql);
    $history_stmt->execute([':asset_id' => $asset_id]);
    $history = $history_stmt->fetchAll(PDO::FETCH_ASSOC);
    
} catch (PDOException $e) {
    // Handle error gracefully
    die("Error loading asset: " . $e->getMessage());
}

// Calculate warranty status
$warranty_status = 'Unknown';
$warranty_class = 'secondary';
if (!empty($asset['warranty_expiry'])) {
    $expiry_date = new DateTime($asset['warranty_expiry']);
    $today = new DateTime();
    $interval = $today->diff($expiry_date);
    
    if ($expiry_date < $today) {
        $warranty_status = 'Expired';
        $warranty_class = 'danger';
    } else {
        $days_left = $interval->days;
        if ($days_left <= 30) {
            $warranty_status = "Expiring in {$days_left} days";
            $warranty_class = 'warning';
        } else {
            $warranty_status = "Valid ({$days_left} days left)";
            $warranty_class = 'success';
        }
    }
}

// Calculate depreciation if applicable
$current_value = $asset['cost'];
if (!empty($asset['depreciation_start_date']) && $asset['depreciation_rate'] > 0) {
    $start_date = new DateTime($asset['depreciation_start_date']);
    $today = new DateTime();
    $interval = $today->diff($start_date);
    $months_passed = ($interval->y * 12) + $interval->m;
    
    if ($asset['depreciation_method'] === 'Straight Line') {
        $monthly_rate = $asset['depreciation_rate'] / 12 / 100;
        $monthly_depreciation = $asset['cost'] * $monthly_rate;
        $total_depreciation = $monthly_depreciation * $months_passed;
        $current_value = max(0, $asset['cost'] - $total_depreciation);
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Asset - Warehouse Dashboard</title>
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
        .info-label {
            font-weight: 600;
            color: #495057;
            margin-bottom: 5px;
        }
        .info-value {
            color: #212529;
            margin-bottom: 15px;
        }
        .asset-image {
            max-width: 100%;
            max-height: 300px;
            object-fit: contain;
            border-radius: 8px;
        }
        .history-item {
            border-left: 3px solid #007bff;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .no-image {
            height: 200px;
            display: flex;
            align-items: center;
            justify-content: center;
            background: #f8f9fa;
            border-radius: 8px;
            color: #6c757d;
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
                <div class="content-header">
                    <div class="d-flex justify-content-between align-items-center mb-4">
                        <div>
                            <h1 class="h2"><i class="bi bi-eye"></i> Asset Details</h1>
                            <p class="mb-0">View complete information for asset: <strong><?php echo htmlspecialchars($asset['asset_id']); ?></strong></p>
                        </div>
                        <div>
                            <a href="list_assets.php" class="btn btn-outline-secondary me-2">
                                <i class="bi bi-arrow-left"></i> Back to List
                            </a>
                            <?php if (in_array($_SESSION['role'], ['warehouse_coordinator'])): ?>
                                <a href="edit_asset.php?id=<?php echo urlencode($asset['asset_id']); ?>" class="btn btn-primary">
                                    <i class="bi bi-pencil"></i> Edit Asset
                                </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="row">
                    <!-- Left Column: Asset Information -->
                    <div class="col-lg-8">
                        <!-- Basic Information Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-primary text-white">
                                <h5 class="mb-0"><i class="bi bi-info-circle"></i> Basic Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-label">Asset ID</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['asset_id']); ?></div>
                                        
                                        <div class="info-label">Asset Name</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['asset_name']); ?></div>
                                        
                                        <div class="info-label">Asset Tag</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['asset_tag'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Asset Class</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['class_name'] ?: 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Model</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['model'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Manufacturer</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['manufacturer'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Serial Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['serial_number'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Created By</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['created_by_name'] ?: 'System'); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Acquisition Information Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-success text-white">
                                <h5 class="mb-0"><i class="bi bi-cart-check"></i> Acquisition Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-label">Purchase Order Number</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['purchase_order_number'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Acquisition Date</div>
                                        <div class="info-value"><?php echo $asset['acquisition_date'] ? date('d/m/Y', strtotime($asset['acquisition_date'])) : 'N/A'; ?></div>
                                        
                                        <div class="info-label">Warranty Expiry</div>
                                        <div class="info-value">
                                            <?php if ($asset['warranty_expiry']): ?>
                                                <?php echo date('d/m/Y', strtotime($asset['warranty_expiry'])); ?>
                                                <span class="badge bg-<?php echo $warranty_class; ?> ms-2"><?php echo $warranty_status; ?></span>
                                            <?php else: ?>
                                                N/A
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Vendor</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['vendor'] ?: 'N/A'); ?></div>
                                        
                                        <div class="info-label">Cost</div>
                                        <div class="info-value">RM <?php echo number_format($asset['cost'], 2); ?></div>
                                        
                                        <div class="info-label">Current Value</div>
                                        <div class="info-value">RM <?php echo number_format($current_value, 2); ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Depreciation Information Card -->
                        <?php if ($asset['depreciation_method'] && $asset['depreciation_method'] !== 'None'): ?>
                        <div class="card mb-4">
                            <div class="card-header bg-warning text-dark">
                                <h5 class="mb-0"><i class="bi bi-graph-down"></i> Depreciation Information</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-label">Depreciation Method</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['depreciation_method']); ?></div>
                                        
                                        <div class="info-label">Depreciation Rate</div>
                                        <div class="info-value"><?php echo $asset['depreciation_rate']; ?>% per year</div>
                                    </div>
                                    <div class="col-md-6">
                                        <div class="info-label">Depreciation Start Date</div>
                                        <div class="info-value"><?php echo $asset['depreciation_start_date'] ? date('d/m/Y', strtotime($asset['depreciation_start_date'])) : 'N/A'; ?></div>
                                        
                                        <div class="info-label">Life Expectancy</div>
                                        <div class="info-value"><?php echo $asset['life_expectancy_years'] ? $asset['life_expectancy_years'] . ' years' : 'N/A'; ?></div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Location & Remarks Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-info text-white">
                                <h5 class="mb-0"><i class="bi bi-geo-alt"></i> Location & Remarks</h5>
                            </div>
                            <div class="card-body">
                                <div class="row">
                                    <div class="col-md-6">
                                        <div class="info-label">Storage Location</div>
                                        <div class="info-value"><?php echo htmlspecialchars($asset['location_name'] ?: 'N/A'); ?></div>
                                    </div>
                                    <div class="col-md-12 mt-3">
                                        <div class="info-label">Remarks / Notes</div>
                                        <div class="info-value">
                                            <?php if ($asset['remarks']): ?>
                                                <?php echo nl2br(htmlspecialchars($asset['remarks'])); ?>
                                            <?php else: ?>
                                                No remarks
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Right Column: Image and History -->
                    <div class="col-lg-4">
                        <!-- Asset Image Card -->
                        <div class="card mb-4">
                            <div class="card-header bg-secondary text-white">
                                <h5 class="mb-0"><i class="bi bi-image"></i> Asset Image</h5>
                            </div>
                            <div class="card-body text-center">
                                <?php if (!empty($asset['image_path']) && file_exists('../../' . $asset['image_path'])): ?>
                                    <img src="../../<?php echo htmlspecialchars($asset['image_path']); ?>" 
                                         alt="Asset Image" 
                                         class="asset-image img-fluid mb-3">
                                    <div class="mt-2">
                                        <a href="../../<?php echo htmlspecialchars($asset['image_path']); ?>" 
                                           target="_blank" 
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-zoom-in"></i> View Full Size
                                        </a>
                                    </div>
                                <?php else: ?>
                                    <div class="no-image">
                                        <div class="text-center">
                                            <i class="bi bi-image display-4 text-muted mb-3"></i>
                                            <p class="text-muted">No image available</p>
                                        </div>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>

                        <!-- Asset History Card -->
                        <div class="card">
                            <div class="card-header bg-dark text-white">
                                <h5 class="mb-0"><i class="bi bi-clock-history"></i> Asset History</h5>
                            </div>
                            <div class="card-body">
                                <?php if (!empty($history)): ?>
                                    <div class="history-list">
                                        <?php foreach ($history as $item): ?>
                                            <div class="history-item">
                                                <div class="d-flex justify-content-between">
                                                    <strong><?php echo htmlspecialchars($item['change_type']); ?></strong>
                                                    <small class="text-muted">
                                                        <?php echo $item['changed_at'] ? date('d/m/Y H:i', strtotime($item['changed_at'])) : ''; ?>
                                                    </small>
                                                </div>
                                                <p class="mb-1 small"><?php echo htmlspecialchars($item['change_summary']); ?></p>
                                                <p class="text-muted mb-0 small">
                                                    By: <?php echo htmlspecialchars($item['changed_by_name'] ?: 'System'); ?>
                                                </p>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                <?php else: ?>
                                    <p class="text-muted text-center">No history available</p>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Action Buttons -->
                <div class="mt-4">
                    <div class="d-flex justify-content-between">
                        <div>
                            <a href="export_assets.php?id=<?php echo urlencode($asset['asset_id']); ?>" class="btn btn-outline-info">
                                <i class="bi bi-download"></i> Export Details
                            </a>
                        </div>
                        <div>
                            <?php if (in_array($_SESSION['role'], ['warehouse_coordinator'])): ?>
                                <button type="button" class="btn btn-outline-danger" data-bs-toggle="modal" data-bs-target="#deleteModal">
                                    <i class="bi bi-trash"></i> Delete Asset
                                </button>
                            <?php endif; ?>
                            <a href="list_assets.php" class="btn btn-secondary ms-2">
                                <i class="bi bi-x-circle"></i> Close
                            </a>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteModal" tabindex="-1" aria-labelledby="deleteModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title" id="deleteModalLabel">
                        <i class="bi bi-exclamation-triangle"></i> Confirm Deletion
                    </h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p>Are you sure you want to delete this asset?</p>
                    <p><strong>Asset ID:</strong> <?php echo htmlspecialchars($asset['asset_id']); ?></p>
                    <p><strong>Asset Name:</strong> <?php echo htmlspecialchars($asset['asset_name']); ?></p>
                    <div class="alert alert-warning mt-3">
                        <i class="bi bi-exclamation-circle"></i>
                        <strong>Warning:</strong> This action cannot be undone. All asset history will also be deleted.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <a href="delete_asset.php?id=<?php echo urlencode($asset['asset_id']); ?>" class="btn btn-danger">Delete Asset</a>
                </div>
            </div>
        </div>
    </div>

    <!-- Bootstrap Bundle with Popper -->
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Any additional JavaScript can go here
        });
    </script>
</body>
</html>
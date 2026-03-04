<?php
require_once '../../includes/check_auth.php';
check_auth(['operation_team']);
require_once '../../includes/db.php';

$conn    = $db->conn;
$user_id = $_SESSION['user_id'];

$sql = "SELECT m.*, a.asset_name, a.serial_number, a.asset_class, ac.class_name
        FROM asset_movements m
        LEFT JOIN assets a ON m.asset_id = a.asset_id
        LEFT JOIN asset_classes ac ON a.asset_class = ac.class_id
        WHERE m.performed_by_user_id = ?
        ORDER BY m.movement_date DESC";
$stmt = $conn->prepare($sql);
$stmt->bind_param("s", $user_id);
$stmt->execute();
$requests = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Requests · Operations</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css" rel="stylesheet">
    <style>
        body { background: #f4f6fb; }
        .page-header { background: linear-gradient(135deg, #007bff, #0056b3); color: white; padding: 1.5rem 2rem; margin-bottom: 2rem; }
        .card { border: none; border-radius: 14px; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
        .badge-pending  { background: #fff3cd; color: #856404; }
        .badge-approved { background: #d1e7dd; color: #0a3622; }
        .badge-rejected { background: #f8d7da; color: #58151c; }
        .status-pill { font-size: 0.8rem; font-weight: 600; padding: 0.35rem 0.9rem; border-radius: 50px; }
    </style>
</head>
<body>
<div class="container-fluid">
    <div class="row">
        <?php include 'opsidebar.php'; ?>

        <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4 py-3">
            <div class="page-header">
                <h2 class="mb-0"><i class="bi bi-clock-history me-2"></i>My Requests</h2>
                <p class="mb-0 mt-1 opacity-75">Track the status of all your Issue / Return requests.</p>
            </div>

            <div class="card">
                <div class="card-body p-0">
                    <div class="table-responsive">
                        <table class="table table-hover mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Asset</th>
                                    <th>Serial No.</th>
                                    <th>Type</th>
                                    <th>Remarks</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if ($requests && $requests->num_rows > 0): ?>
                                    <?php while ($r = $requests->fetch_assoc()): ?>
                                    <tr>
                                        <td><small><?php echo date('d/m/Y H:i', strtotime($r['movement_date'])); ?></small></td>
                                        <td><strong><?php echo htmlspecialchars($r['asset_name'] ?? '—'); ?></strong></td>
                                        <td><code><?php echo htmlspecialchars($r['serial_number'] ?? '—'); ?></code></td>
                                        <td>
                                            <span class="badge <?php echo $r['movement_type'] === 'Issue' ? 'bg-primary' : 'bg-secondary'; ?>">
                                                <?php echo $r['movement_type']; ?>
                                            </span>
                                        </td>
                                        <td><small class="text-muted"><?php echo htmlspecialchars($r['remarks'] ?? '—'); ?></small></td>
                                        <td>
                                            <span class="status-pill badge-<?php echo strtolower($r['status']); ?>">
                                                <?php echo $r['status']; ?>
                                            </span>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <tr>
                                        <td colspan="6" class="text-center py-5 text-muted">
                                            <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                                            You have no requests yet. <a href="issue_return.php">Submit one now</a>.
                                        </td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </main>
    </div>
</div>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
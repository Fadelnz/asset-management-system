<?php
require_once '../../includes/check_auth.php';
check_auth(['admin']); // Only admin can access

// Database connection
require_once '../../includes/db.php';
$conn = $db->conn;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['add_department'])) {
        // Add new department
        $dept_id = 'DEPT_' . strtoupper(uniqid());
        $dept_name = $_POST['name'];
        $dept_head_id = $_POST['department_head_id'] ?? null;
        
        $stmt = $conn->prepare("INSERT INTO departments (department_id, name, department_head_id) VALUES (?, ?, ?)");
        $stmt->bind_param("sss", $dept_id, $dept_name, $dept_head_id);
        
        if ($stmt->execute()) {
            $success_msg = "Department '$dept_name' added successfully!";
            
            // Log activity
            logActivity($_SESSION['user_id'], "Added department: $dept_name", "Added new department");
        } else {
            $error_msg = "Failed to add department: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['update_department'])) {
        // Update department
        $dept_id = $_POST['department_id'];
        $dept_name = $_POST['name'];
        $dept_head_id = $_POST['department_head_id'] ?? null;
        
        $stmt = $conn->prepare("UPDATE departments SET name = ?, department_head_id = ? WHERE department_id = ?");
        $stmt->bind_param("sss", $dept_name, $dept_head_id, $dept_id);
        
        if ($stmt->execute()) {
            $success_msg = "Department '$dept_name' updated successfully!";
            
            // Log activity
            logActivity($_SESSION['user_id'], "Updated department: $dept_name", "Updated department details");
        } else {
            $error_msg = "Failed to update department: " . $conn->error;
        }
        $stmt->close();
    }
    
    if (isset($_POST['delete_department'])) {
        // Delete department (check if has assets first)
        $dept_id = $_POST['department_id'];
        
        // Check if department has assigned assets
        $check_stmt = $conn->prepare("SELECT COUNT(*) as asset_count FROM assets WHERE owner_department_id = ?");
        $check_stmt->bind_param("s", $dept_id);
        $check_stmt->execute();
        $check_result = $check_stmt->get_result();
        $check_data = $check_result->fetch_assoc();
        $check_stmt->close();
        
        if ($check_data['asset_count'] > 0) {
            $error_msg = "Cannot delete department. It has " . $check_data['asset_count'] . " assigned assets. Please reassign or remove assets first.";
        } else {
            // Check if department has users
            $check_stmt = $conn->prepare("SELECT COUNT(*) as user_count FROM users WHERE department_id = ?");
            $check_stmt->bind_param("s", $dept_id);
            $check_stmt->execute();
            $check_result = $check_stmt->get_result();
            $check_data = $check_result->fetch_assoc();
            $check_stmt->close();
            
            if ($check_data['user_count'] > 0) {
                $error_msg = "Cannot delete department. It has " . $check_data['user_count'] . " users assigned. Please reassign users first.";
            } else {
                $stmt = $conn->prepare("DELETE FROM departments WHERE department_id = ?");
                $stmt->bind_param("s", $dept_id);
                
                if ($stmt->execute()) {
                    $success_msg = "Department deleted successfully!";
                    
                    // Log activity
                    logActivity($_SESSION['user_id'], "Deleted department", "Deleted department ID: $dept_id");
                } else {
                    $error_msg = "Failed to delete department: " . $conn->error;
                }
                $stmt->close();
            }
        }
    }
}

// Function to log activity
function logActivity($user_id, $activity, $details) {
    global $conn;
    $log_id = 'LOG_' . uniqid();
    $ip_address = $_SERVER['REMOTE_ADDR'];
    
    $stmt = $conn->prepare("INSERT INTO activity_logs (log_id, user_id, activity, details, ip_address) VALUES (?, ?, ?, ?, ?)");
    $stmt->bind_param("sssss", $log_id, $user_id, $activity, $details, $ip_address);
    $stmt->execute();
    $stmt->close();
}

// Fetch all departments with asset counts and user counts
$departments = [];
$sql = "
    SELECT d.*, 
           u.full_name as head_name,
           u.email as head_email,
           COUNT(DISTINCT a.asset_id) as asset_count,
           SUM(a.cost) as total_asset_value,
           COUNT(DISTINCT us.user_id) as user_count
    FROM departments d
    LEFT JOIN users u ON d.department_head_id = u.user_id
    LEFT JOIN assets a ON d.department_id = a.owner_department_id AND a.is_active = 1
    LEFT JOIN users us ON d.department_id = us.department_id AND us.is_active = 1
    GROUP BY d.department_id
    ORDER BY d.name
";
$result = $conn->query($sql);
if ($result && $result->num_rows > 0) {
    $departments = $result->fetch_all(MYSQLI_ASSOC);
}

// Fetch all users for department head dropdown
$users = [];
$user_sql = "SELECT user_id, full_name, email, username FROM users WHERE is_active = 1 ORDER BY full_name";
$user_result = $conn->query($user_sql);
if ($user_result && $user_result->num_rows > 0) {
    $users = $user_result->fetch_all(MYSQLI_ASSOC);
}

// Fetch department statistics
$total_departments = count($departments);
$total_assets_assigned = 0;
$total_asset_value = 0;

foreach ($departments as $dept) {
    $total_assets_assigned += $dept['asset_count'];
    $total_asset_value += $dept['total_asset_value'];
}

$avg_assets_per_dept = $total_departments > 0 ? $total_assets_assigned / $total_departments : 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Departments - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">
    <style>
        .card-stat {
            border-radius: 10px;
            transition: transform 0.3s;
        }
        .card-stat:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 15px rgba(0,0,0,0.1);
        }
        .department-actions .btn {
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include Sidebar -->
            <?php include 'adsidebar.php'; ?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="d-flex justify-content-between flex-wrap flex-md-nowrap align-items-center pt-3 pb-2 mb-3 border-bottom">
                    <h1 class="h2">
                        <i class="bi bi-building me-2"></i>
                        Departments Management
                    </h1>
                    <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                        <i class="bi bi-plus-circle"></i> Add Department
                    </button>
                </div>
                
                <?php if (isset($success_msg)): ?>
                    <div class="alert alert-success alert-dismissible fade show" role="alert">
                        <i class="bi bi-check-circle me-2"></i> <?php echo $success_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <?php if (isset($error_msg)): ?>
                    <div class="alert alert-danger alert-dismissible fade show" role="alert">
                        <i class="bi bi-exclamation-triangle me-2"></i> <?php echo $error_msg; ?>
                        <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button>
                    </div>
                <?php endif; ?>
                
                <!-- Statistics Cards -->
                <div class="row mb-4">
                    <div class="col-md-3 mb-3">
                        <div class="card bg-primary text-white card-stat">
                            <div class="card-body">
                                <h5 class="card-title">Total Departments</h5>
                                <h2><?php echo $total_departments; ?></h2>
                                <i class="bi bi-buildings" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-success text-white card-stat">
                            <div class="card-body">
                                <h5 class="card-title">Total Users</h5>
                                <h2><?php echo count($users); ?></h2>
                                <i class="bi bi-people" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-info text-white card-stat">
                            <div class="card-body">
                                <h5 class="card-title">Total Assets Assigned</h5>
                                <h2><?php echo $total_assets_assigned; ?></h2>
                                <i class="bi bi-box" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-3 mb-3">
                        <div class="card bg-warning text-white card-stat">
                            <div class="card-body">
                                <h5 class="card-title">Total Asset Value</h5>
                                <h2>$<?php echo number_format($total_asset_value, 2); ?></h2>
                                <i class="bi bi-currency-dollar" style="font-size: 48px; opacity: 0.5; position: absolute; right: 20px; top: 20px;"></i>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Departments Table -->
                <div class="card">
                    <div class="card-header">
                        <h5 class="card-title mb-0">All Departments</h5>
                    </div>
                    <div class="card-body">
                        <div class="table-responsive">
                            <table id="departmentsTable" class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Department Name</th>
                                        <th>Head</th>
                                        <th>Assets</th>
                                        <th>Users</th>
                                        <th>Total Value</th>
                                        <th>Created Date</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($departments as $dept): ?>
                                        <tr>
                                            <td>
                                                <small class="text-muted"><?php echo htmlspecialchars($dept['department_id']); ?></small>
                                            </td>
                                            <td>
                                                <strong><?php echo htmlspecialchars($dept['name']); ?></strong>
                                            </td>
                                            <td>
                                                <?php if ($dept['head_name']): ?>
                                                    <div><?php echo htmlspecialchars($dept['head_name']); ?></div>
                                                    <small class="text-muted"><?php echo htmlspecialchars($dept['head_email']); ?></small>
                                                <?php else: ?>
                                                    <span class="text-muted">Not assigned</span>
                                                <?php endif; ?>
                                            </td>
                                            <td>
                                                <a href="departasset.php?dept_id=<?php echo urlencode($dept['department_id']); ?>" 
                                                   class="btn btn-sm btn-outline-info">
                                                    <i class="bi bi-box"></i> <?php echo $dept['asset_count']; ?> Assets
                                                </a>
                                            </td>
                                            <td>
                                                <span class="badge bg-secondary">
                                                    <i class="bi bi-people"></i> <?php echo $dept['user_count']; ?> Users
                                                </span>
                                            </td>
                                            <td>
                                                <strong>$<?php echo number_format($dept['total_asset_value'] ?? 0, 2); ?></strong>
                                            </td>
                                            <td>
                                                <?php echo date('Y-m-d', strtotime($dept['created_at'])); ?>
                                            </td>
                                            <td class="department-actions">
                                                <a href="departasset.php?dept_id=<?php echo urlencode($dept['department_id']); ?>" 
                                                   class="btn btn-sm btn-outline-primary" title="Manage Assets">
                                                    <i class="bi bi-box"></i>
                                                </a>
                                                <button class="btn btn-sm btn-outline-warning" 
                                                        onclick="editDepartment('<?php echo $dept['department_id']; ?>', '<?php echo addslashes($dept['name']); ?>', '<?php echo $dept['department_head_id']; ?>')"
                                                        title="Edit Department">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-outline-danger" 
                                                        onclick="confirmDelete('<?php echo $dept['department_id']; ?>', '<?php echo addslashes($dept['name']); ?>')"
                                                        title="Delete Department">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <?php if (empty($departments)): ?>
                            <div class="text-center py-5">
                                <i class="bi bi-building" style="font-size: 3rem; color: #6c757d;"></i>
                                <h5 class="mt-3">No Departments Found</h5>
                                <p class="text-muted">Get started by adding your first department.</p>
                                <button type="button" class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#addDepartmentModal">
                                    <i class="bi bi-plus-circle"></i> Add First Department
                                </button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Add Department Modal -->
    <div class="modal fade" id="addDepartmentModal" tabindex="-1" aria-labelledby="addDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title" id="addDepartmentModalLabel">Add New Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="department_head_id" class="form-label">Department Head</label>
                            <select class="form-select" id="department_head_id" name="department_head_id">
                                <option value="">-- Select Department Head --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <small class="text-muted">Optional. Select a user to be the department head.</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="add_department" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Add Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Edit Department Modal -->
    <div class="modal fade" id="editDepartmentModal" tabindex="-1" aria-labelledby="editDepartmentModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" id="edit_department_id" name="department_id">
                    <div class="modal-header">
                        <h5 class="modal-title" id="editDepartmentModalLabel">Edit Department</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label for="edit_name" class="form-label">Department Name *</label>
                            <input type="text" class="form-control" id="edit_name" name="name" required>
                        </div>
                        
                        <div class="mb-3">
                            <label for="edit_department_head_id" class="form-label">Department Head</label>
                            <select class="form-select" id="edit_department_head_id" name="department_head_id">
                                <option value="">-- Select Department Head --</option>
                                <?php foreach ($users as $user): ?>
                                    <option value="<?php echo htmlspecialchars($user['user_id']); ?>">
                                        <?php echo htmlspecialchars($user['full_name']); ?> (<?php echo htmlspecialchars($user['email']); ?>)
                                    </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="update_department" class="btn btn-primary">
                            <i class="bi bi-check-circle"></i> Update Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete Confirmation Modal -->
    <div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <input type="hidden" id="delete_department_id" name="department_id">
                    <div class="modal-header">
                        <h5 class="modal-title text-danger" id="deleteConfirmModalLabel">Confirm Deletion</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                    </div>
                    <div class="modal-body">
                        <div class="alert alert-warning">
                            <i class="bi bi-exclamation-triangle me-2"></i>
                            <strong>Warning!</strong> This action cannot be undone.
                        </div>
                        <p>Are you sure you want to delete department: <strong id="delete_department_name"></strong>?</p>
                        <p class="text-muted">Note: You can only delete departments with no assigned assets or users.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" name="delete_department" class="btn btn-danger">
                            <i class="bi bi-trash"></i> Delete Department
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/jquery.dataTables.min.js"></script>
    <script src="https://cdn.datatables.net/1.11.5/js/dataTables.bootstrap5.min.js"></script>
    
    <script>
        // Initialize DataTable
        $(document).ready(function() {
            $('#departmentsTable').DataTable({
                "pageLength": 25,
                "order": [[1, 'asc']],
                "columnDefs": [
                    { "orderable": false, "targets": 7 } // Disable sorting on actions column
                ]
            });
        });
        
        // Function to populate edit modal with department data
        function editDepartment(deptId, deptName, headId) {
            $('#edit_department_id').val(deptId);
            $('#edit_name').val(deptName);
            $('#edit_department_head_id').val(headId || '');
            
            var editModal = new bootstrap.Modal(document.getElementById('editDepartmentModal'));
            editModal.show();
        }
        
        // Function to confirm department deletion
        function confirmDelete(deptId, deptName) {
            $('#delete_department_id').val(deptId);
            $('#delete_department_name').text(deptName);
            
            var deleteModal = new bootstrap.Modal(document.getElementById('deleteConfirmModal'));
            deleteModal.show();
        }
        
        // Export departments to CSV
        function exportDepartments() {
            window.location.href = 'export_departments.php';
        }
    </script>
</body>
</html>
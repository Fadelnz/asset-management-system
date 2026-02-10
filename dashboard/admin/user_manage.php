<?php
require_once '../../includes/check_auth.php';
require_once '../../includes/db.php';
check_auth(['admin','it_operation']); // Only admin can access

// Get database connection from the Database class
$conn = $db->conn;

// Handle form submissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'create_user':
                // Create new user
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $role = $_POST['role'] ?? '';
                $department_id = $_POST['department_id'] ?? null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Generate user_id
                $user_id = uniqid('usr_');
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                $sql = "INSERT INTO users (user_id, username, password_hash, full_name, email, role, department_id, is_active) 
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("sssssssi", $user_id, $username, $password_hash, $full_name, $email, $role, $department_id, $is_active);
                    
                    if ($stmt->execute()) {
                        $message = "User created successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error creating user: " . $conn->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                } else {
                    $message = "Error preparing statement: " . $conn->error;
                    $message_type = "danger";
                }
                break;
                
            case 'update_user':
                // Update existing user
                $user_id = $_POST['user_id'] ?? '';
                $username = $_POST['username'] ?? '';
                $full_name = $_POST['full_name'] ?? '';
                $email = $_POST['email'] ?? '';
                $role = $_POST['role'] ?? '';
                $department_id = $_POST['department_id'] ?? null;
                $is_active = isset($_POST['is_active']) ? 1 : 0;
                
                // Check if password is being updated
                if (!empty($_POST['password'])) {
                    $password_hash = password_hash($_POST['password'], PASSWORD_DEFAULT);
                    $sql = "UPDATE users SET username=?, password_hash=?, full_name=?, email=?, role=?, department_id=?, is_active=? WHERE user_id=?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("ssssssis", $username, $password_hash, $full_name, $email, $role, $department_id, $is_active, $user_id);
                        
                        if ($stmt->execute()) {
                            $message = "User updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating user: " . $conn->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                } else {
                    $sql = "UPDATE users SET username=?, full_name=?, email=?, role=?, department_id=?, is_active=? WHERE user_id=?";
                    $stmt = $conn->prepare($sql);
                    if ($stmt) {
                        $stmt->bind_param("sssssis", $username, $full_name, $email, $role, $department_id, $is_active, $user_id);
                        
                        if ($stmt->execute()) {
                            $message = "User updated successfully!";
                            $message_type = "success";
                        } else {
                            $message = "Error updating user: " . $conn->error;
                            $message_type = "danger";
                        }
                        $stmt->close();
                    }
                }
                break;
                
            case 'delete_user':
                // Delete user
                $user_id = $_POST['user_id'] ?? '';
                
                // First, delete related sessions
                $sql1 = "DELETE FROM user_sessions WHERE user_id = ?";
                $stmt1 = $conn->prepare($sql1);
                if ($stmt1) {
                    $stmt1->bind_param("s", $user_id);
                    $stmt1->execute();
                    $stmt1->close();
                }
                
                // Then delete user
                $sql2 = "DELETE FROM users WHERE user_id = ?";
                $stmt2 = $conn->prepare($sql2);
                if ($stmt2) {
                    $stmt2->bind_param("s", $user_id);
                    
                    if ($stmt2->execute()) {
                        $message = "User deleted successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error deleting user: " . $conn->error;
                        $message_type = "danger";
                    }
                    $stmt2->close();
                }
                break;
                
            case 'invalidate_session':
                // Invalidate user session
                $session_id = $_POST['session_id'] ?? '';
                
                $sql = "UPDATE user_sessions SET is_valid = 0 WHERE session_id = ?";
                $stmt = $conn->prepare($sql);
                if ($stmt) {
                    $stmt->bind_param("s", $session_id);
                    
                    if ($stmt->execute()) {
                        $message = "Session invalidated successfully!";
                        $message_type = "success";
                    } else {
                        $message = "Error invalidating session: " . $conn->error;
                        $message_type = "danger";
                    }
                    $stmt->close();
                }
                break;
        }
    }
}

// Fetch data for display
$users = [];
$sessions = [];
$logs = [];

// Fetch all users - FIXED: Changed d.department_name to d.name
$sql_users = "SELECT u.*, d.name as department_name FROM users u 
              LEFT JOIN departments d ON u.department_id = d.department_id 
              ORDER BY u.created_at DESC";
$result_users = $conn->query($sql_users);
if ($result_users && $result_users->num_rows > 0) {
    $users = $result_users->fetch_all(MYSQLI_ASSOC);
}

// Fetch active sessions
$sql_sessions = "SELECT us.*, u.username, u.full_name 
                 FROM user_sessions us 
                 JOIN users u ON us.user_id = u.user_id 
                 WHERE us.is_valid = 1 
                 ORDER BY us.last_activity DESC";
$result_sessions = $conn->query($sql_sessions);
if ($result_sessions && $result_sessions->num_rows > 0) {
    $sessions = $result_sessions->fetch_all(MYSQLI_ASSOC);
}

// Fetch recent activity logs
$sql_logs = "SELECT al.*, u.username, u.full_name 
             FROM activity_logs al 
             LEFT JOIN users u ON al.user_id = u.user_id 
             ORDER BY al.created_at DESC 
             LIMIT 50";
$result_logs = $conn->query($sql_logs);
if ($result_logs && $result_logs->num_rows > 0) {
    $logs = $result_logs->fetch_all(MYSQLI_ASSOC);
}

// Fetch departments for dropdown - FIXED: Changed department_name to name
$departments = [];
$sql_dept = "SELECT department_id, name FROM departments ORDER BY name";
$result_dept = $conn->query($sql_dept);
if ($result_dept && $result_dept->num_rows > 0) {
    $departments = $result_dept->fetch_all(MYSQLI_ASSOC);
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Management - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <link rel="stylesheet" href="https://cdn.datatables.net/1.11.5/css/dataTables.bootstrap5.min.css">

</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Sidebar -->
           <?php include 'adsidebar.php';?>
            
            <!-- Main Content -->
            <main class="col-md-9 ms-sm-auto col-lg-10 px-md-4">
                <div class="content-header">
                    <h1><i class="bi bi-people"></i> User Management</h1>
                    <p>Manage system users, monitor sessions, and view activity logs</p>
                </div>
                
                <!-- Display Messages -->
                <?php if (isset($message)): ?>
                <div class="alert alert-<?php echo $message_type; ?> alert-dismissible fade show mt-3" role="alert">
                    <?php echo htmlspecialchars($message); ?>
                    <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
                </div>
                <?php endif; ?>
                
                <div class="container-fluid mt-4">
                    <!-- User Management Section -->
                    <div class="card mb-4">
                        <div class="card-header d-flex justify-content-between align-items-center">
                            <h5 class="card-title mb-0"><i class="bi bi-person-plus"></i> Create New User</h5>
                            <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#createUserModal">
                                <i class="bi bi-plus-circle"></i> Add User
                            </button>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table id="usersTable" class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>ID</th>
                                            <th>Username</th>
                                            <th>Full Name</th>
                                            <th>Email</th>
                                            <th>Role</th>
                                            <th>Department</th>
                                            <th>Status</th>
                                            <th>Created</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($users as $user): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars(substr($user['user_id'], 0, 10) . '...'); ?></td>
                                            <td><?php echo htmlspecialchars($user['username']); ?></td>
                                            <td><?php echo htmlspecialchars($user['full_name'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge bg-<?php 
                                                    if ($user['role'] == 'admin') echo 'primary';
                                                    elseif ($user['role'] == 'manager') echo 'warning';
                                                    else echo 'secondary';
                                                ?>">
                                                    <?php echo htmlspecialchars($user['role']); ?>
                                                </span>
                                            </td>
                                            <td><?php echo htmlspecialchars($user['department_name'] ?? 'N/A'); ?></td>
                                            <td>
                                                <span class="badge <?php echo $user['is_active'] ? 'badge-active' : 'badge-inactive'; ?>">
                                                    <?php echo $user['is_active'] ? 'Active' : 'Inactive'; ?>
                                                </span>
                                            </td>
                                            <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                                            <td>
                                                <button class="btn btn-sm btn-warning" data-bs-toggle="modal" data-bs-target="#editUserModal" 
                                                        data-userid="<?php echo htmlspecialchars($user['user_id']); ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                        data-fullname="<?php echo htmlspecialchars($user['full_name'] ?? ''); ?>"
                                                        data-email="<?php echo htmlspecialchars($user['email'] ?? ''); ?>"
                                                        data-role="<?php echo htmlspecialchars($user['role']); ?>"
                                                        data-department="<?php echo htmlspecialchars($user['department_id'] ?? ''); ?>"
                                                        data-active="<?php echo $user['is_active']; ?>">
                                                    <i class="bi bi-pencil"></i>
                                                </button>
                                                <button class="btn btn-sm btn-danger" data-bs-toggle="modal" data-bs-target="#deleteUserModal"
                                                        data-userid="<?php echo htmlspecialchars($user['user_id']); ?>"
                                                        data-username="<?php echo htmlspecialchars($user['username']); ?>">
                                                    <i class="bi bi-trash"></i>
                                                </button>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($users)): ?>
                                        <tr>
                                            <td colspan="9" class="text-center">No users found</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Active Sessions Section -->
                    <div class="card mb-4">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-clock-history"></i> Active Sessions</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>User</th>
                                            <th>Login Time</th>
                                            <th>Last Activity</th>
                                            <th>IP Address</th>
                                            <th>Session Status</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($sessions as $session): ?>
                                        <tr>
                                            <td>
                                                <strong><?php echo htmlspecialchars($session['username']); ?></strong><br>
                                                <small><?php echo htmlspecialchars($session['full_name']); ?></small>
                                            </td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($session['login_time'])); ?></td>
                                            <td><?php echo date('Y-m-d H:i', strtotime($session['last_activity'])); ?></td>
                                            <td><?php echo htmlspecialchars($session['ip_address']); ?></td>
                                            <td>
                                                <span class="badge badge-valid">Valid</span>
                                            </td>
                                            <td>
                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="invalidate_session">
                                                    <input type="hidden" name="session_id" value="<?php echo htmlspecialchars($session['session_id']); ?>">
                                                    <button type="submit" class="btn btn-sm btn-danger" onclick="return confirm('Invalidate this session?')">
                                                        <i class="bi bi-power"></i> Invalidate
                                                    </button>
                                                </form>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($sessions)): ?>
                                        <tr>
                                            <td colspan="6" class="text-center">No active sessions</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Activity Logs Section -->
                    <div class="card">
                        <div class="card-header">
                            <h5 class="card-title mb-0"><i class="bi bi-list-check"></i> Recent Activity Logs</h5>
                        </div>
                        <div class="card-body">
                            <div class="table-responsive">
                                <table class="table table-hover">
                                    <thead>
                                        <tr>
                                            <th>Time</th>
                                            <th>User</th>
                                            <th>Activity</th>
                                            <th>Details</th>
                                            <th>IP Address</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($logs as $log): ?>
                                        <tr>
                                            <td><?php echo date('Y-m-d H:i', strtotime($log['created_at'])); ?></td>
                                            <td>
                                                <?php if ($log['user_id']): ?>
                                                <strong><?php echo htmlspecialchars($log['username'] ?? 'Unknown'); ?></strong><br>
                                                <small><?php echo htmlspecialchars($log['full_name'] ?? ''); ?></small>
                                                <?php else: ?>
                                                <em>System</em>
                                                <?php endif; ?>
                                            </td>
                                            <td><?php echo htmlspecialchars($log['activity'] ?? 'N/A'); ?></td>
                                            <td><?php echo htmlspecialchars(substr($log['details'] ?? 'N/A', 0, 100)); ?>...</td>
                                            <td><?php echo htmlspecialchars($log['ip_address'] ?? 'N/A'); ?></td>
                                        </tr>
                                        <?php endforeach; ?>
                                        <?php if (empty($logs)): ?>
                                        <tr>
                                            <td colspan="5" class="text-center">No activity logs</td>
                                        </tr>
                                        <?php endif; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>
    
    <!-- Create User Modal -->
    <div class="modal fade" id="createUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Create New User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="create_user">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password *</label>
                            <input type="password" class="form-control" name="password" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" required>
                                <option value="">Select Role</option>
                                <option value="admin">Administrator</option>
                                <option value="user">Regular User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="createActive" checked>
                            <label class="form-check-label" for="createActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Create User</button>
                    </div>
                </form>
            </div>
        </div>
</div>
    
    <!-- Edit User Modal -->
    <div class="modal fade" id="editUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Edit User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="update_user">
                        <input type="hidden" name="user_id" id="editUserId">
                        <div class="mb-3">
                            <label class="form-label">Username *</label>
                            <input type="text" class="form-control" name="username" id="editUsername" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Password (leave blank to keep current)</label>
                            <input type="password" class="form-control" name="password">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Full Name</label>
                            <input type="text" class="form-control" name="full_name" id="editFullName">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Email</label>
                            <input type="email" class="form-control" name="email" id="editEmail">
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Role *</label>
                            <select class="form-select" name="role" id="editRole" required>
                                <option value="admin">Administrator</option>
                                <option value="user">Regular User</option>
                                <option value="manager">Manager</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Department</label>
                            <select class="form-select" name="department_id" id="editDepartment">
                                <option value="">Select Department</option>
                                <?php foreach ($departments as $dept): ?>
                                <option value="<?php echo htmlspecialchars($dept['department_id']); ?>">
                                    <?php echo htmlspecialchars($dept['name']); ?>
                                </option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="mb-3 form-check">
                            <input type="checkbox" class="form-check-input" name="is_active" id="editActive">
                            <label class="form-check-label" for="editActive">Active</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary">Update User</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <!-- Delete User Modal -->
    <div class="modal fade" id="deleteUserModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <form method="POST">
                    <div class="modal-header">
                        <h5 class="modal-title">Delete User</h5>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <input type="hidden" name="action" value="delete_user">
                        <input type="hidden" name="user_id" id="deleteUserId">
                        <p>Are you sure you want to delete user: <strong id="deleteUsername"></strong>?</p>
                        <p class="text-danger">Warning: This action cannot be undone. All user sessions and related data will be deleted.</p>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-danger">Delete User</button>
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
        $(document).ready(function() {
            // Initialize DataTable for users
            $('#usersTable').DataTable({
                pageLength: 10,
                order: [[7, 'desc']]
            });
            
            // Edit User Modal Handler
            $('#editUserModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var userId = button.data('userid');
                var username = button.data('username');
                var fullName = button.data('fullname');
                var email = button.data('email');
                var role = button.data('role');
                var department = button.data('department');
                var isActive = button.data('active');
                
                var modal = $(this);
                modal.find('#editUserId').val(userId);
                modal.find('#editUsername').val(username);
                modal.find('#editFullName').val(fullName);
                modal.find('#editEmail').val(email);
                modal.find('#editRole').val(role);
                modal.find('#editDepartment').val(department);
                modal.find('#editActive').prop('checked', isActive == 1);
            });
            
            // Delete User Modal Handler
            $('#deleteUserModal').on('show.bs.modal', function(event) {
                var button = $(event.relatedTarget);
                var userId = button.data('userid');
                var username = button.data('username');
                
                var modal = $(this);
                modal.find('#deleteUserId').val(userId);
                modal.find('#deleteUsername').text(username);
            });
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>
<?php
require_once '../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $db->sanitize($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = $db->sanitize($_POST['full_name']);
    $email = $db->sanitize($_POST['email']);
    $role = $db->sanitize($_POST['role']);
    $department_id = $db->sanitize($_POST['department_id'] ?? '');
    
    // Validation
    if (empty($username) || empty($password) || empty($full_name) || empty($email) || empty($role)) {
        $error = "All fields are required";
    } elseif ($password !== $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 8) {
        $error = "Password must be at least 8 characters long";
    } else {
        try {
            // Check if username already exists
            $check_sql = "SELECT user_id FROM users WHERE username = :username OR email = :email";
            $check_stmt = $db->pdo->prepare($check_sql);
            $check_stmt->bindParam(':username', $username, PDO::PARAM_STR);
            $check_stmt->bindParam(':email', $email, PDO::PARAM_STR);
            $check_stmt->execute();
            
            if ($check_stmt->rowCount() > 0) {
                $error = "Username or email already exists";
            } else {
                // Generate user_id
                $user_id = 'USR' . date('Ymd') . str_pad(rand(0, 9999), 4, '0', STR_PAD_LEFT);
                
                // Hash password
                $password_hash = password_hash($password, PASSWORD_DEFAULT);
                
                // Insert user
                $sql = "INSERT INTO users (user_id, username, password_hash, full_name, email, role, department_id, is_active, created_at) 
                        VALUES (:user_id, :username, :password_hash, :full_name, :email, :role, :department_id, 1, NOW())";
                
                $stmt = $db->pdo->prepare($sql);
                $stmt->bindParam(':user_id', $user_id, PDO::PARAM_STR);
                $stmt->bindParam(':username', $username, PDO::PARAM_STR);
                $stmt->bindParam(':password_hash', $password_hash, PDO::PARAM_STR);
                $stmt->bindParam(':full_name', $full_name, PDO::PARAM_STR);
                $stmt->bindParam(':email', $email, PDO::PARAM_STR);
                $stmt->bindParam(':role', $role, PDO::PARAM_STR);
                $stmt->bindParam(':department_id', $department_id, PDO::PARAM_STR);
                
                if ($stmt->execute()) {
                    $success = "Account created successfully! You can now login.";
                } else {
                    $error = "Error creating account";
                }
            }
        } catch (PDOException $e) {
            $error = "Database error: " . $e->getMessage();
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sign Up - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 20px 0;
        }
        .signup-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 500px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }
        .btn-signup {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="signup-container">
        <div class="logo">
            <i class="bi bi-box-seam" style="font-size: 48px;"></i>
            <h2 class="mt-3">Create Account</h2>
            <p class="text-muted">Join Asset Management System</p>
        </div>
        
        <?php if ($error): ?>
            <div class="alert alert-danger alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($error); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <?php if ($success): ?>
            <div class="alert alert-success alert-dismissible fade show" role="alert">
                <?php echo htmlspecialchars($success); ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="full_name" class="form-label">Full Name *</label>
                    <input type="text" class="form-control" id="full_name" name="full_name" 
                           required placeholder="John Doe">
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="username" class="form-label">Username *</label>
                    <input type="text" class="form-control" id="username" name="username" 
                           required placeholder="johndoe">
                </div>
            </div>
            
            <div class="mb-3">
                <label for="email" class="form-label">Email Address *</label>
                <input type="email" class="form-control" id="email" name="email" 
                       required placeholder="john@example.com">
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="password" class="form-label">Password *</label>
                    <input type="password" class="form-control" id="password" name="password" 
                           required placeholder="At least 8 characters">
                    <small class="text-muted">Must be at least 8 characters</small>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="confirm_password" class="form-label">Confirm Password *</label>
                    <input type="password" class="form-control" id="confirm_password" name="confirm_password" 
                           required placeholder="Confirm your password">
                </div>
            </div>
            
            <div class="row">
                <div class="col-md-6 mb-3">
                    <label for="role" class="form-label">Role *</label>
                    <select class="form-select" id="role" name="role" required>
                        <option value="">Select Role</option>
                        <option value="admin">Admin</option>
                        <option value="accountant">Accountant</option>
                        <option value="warehouse_coordinator">Warehouse Coordinator</option>
                        <option value="operation_manager">Operation Manager</option>
                        <option value="operation_team">Operation Team</option>
                    </select>
                </div>
                
                <div class="col-md-6 mb-3">
                    <label for="department_id" class="form-label">Department ID</label>
                    <input type="text" class="form-control" id="department_id" name="department_id" 
                           placeholder="Optional">
                </div>
            </div>
            
            <button type="submit" class="btn btn-signup mb-3">
                <i class="bi bi-person-plus"></i> Create Account
            </button>
            
            <div class="text-center">
                <p>Already have an account? <a href="login.php" class="text-decoration-none">Sign in</a></p>
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
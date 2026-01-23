<?php
require_once '../includes/db.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    // Use the sanitize method from the Database class
    $username = $db->sanitize($_POST['username']);
    $password = $_POST['password'];
    
    try {
        // Using PDO prepared statements
        $sql = "SELECT * FROM users WHERE username = :username AND is_active = 1";
        $stmt = $db->pdo->prepare($sql);
        $stmt->bindParam(':username', $username, PDO::PARAM_STR);
        $stmt->execute();
        
        if ($stmt->rowCount() === 1) {
            $user = $stmt->fetch(PDO::FETCH_ASSOC);
            
            if (password_verify($password, $user['password_hash'])) {
                // Update last login
                $update_sql = "UPDATE users SET last_login = NOW() WHERE user_id = :user_id";
                $update_stmt = $db->pdo->prepare($update_sql);
                $update_stmt->bindParam(':user_id', $user['user_id'], PDO::PARAM_STR);
                $update_stmt->execute();
                
                // Set session variables
                $_SESSION['user_id'] = $user['user_id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['full_name'] = $user['full_name'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['email'] = $user['email'];
                $_SESSION['department_id'] = $user['department_id'];
                
                // Redirect based on role
                switch ($user['role']) {
                    case 'admin':
                        header("Location: ../dashboard/admin/dashboard.php");
                        break;
                    case 'accountant':
                        header("Location: ../dashboard/accountant/dashboard.php");
                        break;
                    case 'warehouse_coordinator':
                        header("Location: ../dashboard/warehouse_coordinator/dashboard.php");
                        break;
                    case 'operation_manager':
                        header("Location: ../dashboard/operation_manager/dashboard.php");
                        break;
                    case 'operation_team':
                        header("Location: ../dashboard/operation/dashboard.php");
                        break;
                    default:
                        $error = "Invalid user role";
                        break;
                }
                exit();
            } else {
                $error = "Invalid username or password";
            }
        } else {
            $error = "Invalid username or password";
        }
    } catch (PDOException $e) {
        $error = "Database error: " . $e->getMessage();
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Asset Management System</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.8.1/font/bootstrap-icons.css">
    <style>
        body {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .login-container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            width: 100%;
            max-width: 400px;
        }
        .logo {
            text-align: center;
            margin-bottom: 30px;
            color: #667eea;
        }
        .btn-login {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border: none;
            color: white;
            padding: 10px 0;
            width: 100%;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="logo">
            <i class="bi bi-box-seam" style="font-size: 48px;"></i>
            <h2 class="mt-3">Asset Management System</h2>
            <p class="text-muted">Please sign in to continue</p>
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
            <div class="mb-3">
                <label for="username" class="form-label">Username</label>
                <input type="text" class="form-control" id="username" name="username" 
                       required autofocus placeholder="Enter your username">
            </div>
            
            <div class="mb-3">
                <label for="password" class="form-label">Password</label>
                <input type="password" class="form-control" id="password" name="password" 
                       required placeholder="Enter your password">
            </div>
            
            <div class="mb-3 form-check">
                <input type="checkbox" class="form-check-input" id="remember">
                <label class="form-check-label" for="remember">Remember me</label>
            </div>
            
            <button type="submit" class="btn btn-login mb-3">
                <i class="bi bi-box-arrow-in-right"></i> Sign In
            </button>
            
            <div class="text-center">
                <a href="forget-password.php" class="text-decoration-none">Forgot password?</a>
                <p class="mt-2">Don't have an account? <a href="signup.php" class="text-decoration-none">Sign up</a></p>
                <a href="../index.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Home
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
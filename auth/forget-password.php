<?php
require_once '../includes/db.php';

$message = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = $db->sanitize($_POST['email']);
    
    // Check if email exists
    $sql = "SELECT user_id, full_name FROM users WHERE email = ? AND is_active = 1";
    $stmt = $db->conn->prepare($sql);
    $stmt->bind_param("s", $email);
    $stmt->execute();
    $result = $stmt->get_result();
    
    if ($result->num_rows === 1) {
        $user = $result->fetch_assoc();
        $token = bin2hex(random_bytes(32));
        $expiry = date('Y-m-d H:i:s', strtotime('+1 hour'));
        
        // Store token in database
        $update_sql = "UPDATE users SET reset_token = ?, reset_token_expiry = ? WHERE user_id = ?";
        $update_stmt = $db->conn->prepare($update_sql);
        $update_stmt->bind_param("sss", $token, $expiry, $user['user_id']);
        
        if ($update_stmt->execute()) {
            // In a real application, you would send an email here
            $message = "Password reset instructions have been sent to your email.";
        } else {
            $message = "Error processing request. Please try again.";
        }
        $update_stmt->close();
    } else {
        $message = "If an account exists with this email, reset instructions will be sent.";
    }
    $stmt->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Asset Management System</title>
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
        .container {
            background: white;
            border-radius: 10px;
            padding: 40px;
            box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            max-width: 400px;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="text-center mb-4">
            <i class="bi bi-key" style="font-size: 48px; color: #667eea;"></i>
            <h2 class="mt-3">Forgot Password</h2>
            <p class="text-muted">Enter your email to reset your password</p>
        </div>
        
        <?php if ($message): ?>
            <div class="alert alert-info alert-dismissible fade show" role="alert">
                <?php echo $message; ?>
                <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
            </div>
        <?php endif; ?>
        
        <form method="POST" action="">
            <div class="mb-3">
                <label for="email" class="form-label">Email Address</label>
                <input type="email" class="form-control" id="email" name="email" 
                       required placeholder="Enter your email address">
            </div>
            
            <button type="submit" class="btn btn-primary w-100 mb-3">
                <i class="bi bi-envelope"></i> Send Reset Instructions
            </button>
            
            <div class="text-center">
                <a href="login.php" class="text-decoration-none">
                    <i class="bi bi-arrow-left"></i> Back to Login
                </a>
            </div>
        </form>
    </div>
    
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.1.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
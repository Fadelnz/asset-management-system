<?php
require_once 'db.php';

class Auth {
    
    public static function login($username, $password) {
        global $db;
        
        $stmt = $db->prepare("SELECT * FROM users WHERE username = ? AND is_active = TRUE");
        $stmt->execute([$username]);
        $user = $stmt->fetch();
        
        if ($user && password_verify($password, $user['password_hash'])) {
            // Update last login
            $update = $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = ?");
            $update->execute([$user['user_id']]);
            
            // Set session
            $_SESSION['user_id'] = $user['user_id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];
            $_SESSION['full_name'] = $user['full_name'];
            
            return true;
        }
        
        return false;
    }
    
    public static function logout() {
        session_destroy();
        header('Location: ../auth/login.php');
        exit();
    }
    
    public static function isLoggedIn() {
        return isset($_SESSION['user_id']);
    }
    
    public static function requireLogin() {
        if (!self::isLoggedIn()) {
            header('Location: ../auth/login.php');
            exit();
        }
    }
    
    public static function requireRole($requiredRole) {
        self::requireLogin();
        
        if ($_SESSION['role'] !== $requiredRole) {
            header('HTTP/1.0 403 Forbidden');
            echo "Access denied. Required role: $requiredRole";
            exit();
        }
    }
    
    public static function getUserRole() {
        return $_SESSION['role'] ?? null;
    }
    
    public static function hasPermission($requiredRole) {
        if (!self::isLoggedIn()) return false;
        
        $roleHierarchy = [
            'admin' => 4,
            'operations_manager' => 3,
            'accountant' => 2,
            'warehouse_coordinator' => 1
        ];
        
        $userLevel = $roleHierarchy[$_SESSION['role']] ?? 0;
        $requiredLevel = $roleHierarchy[$requiredRole] ?? 0;
        
        return $userLevel >= $requiredLevel;
    }
}
?>
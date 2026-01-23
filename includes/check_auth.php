<?php
session_start();

function check_auth($allowed_roles = []) {
    if (!isset($_SESSION['user_id'])) {
        header("Location: ../auth/login.php");
        exit();
    }
    
    if (!empty($allowed_roles) && !in_array($_SESSION['role'], $allowed_roles)) {
        header("Location: ../index.php?error=unauthorized");
        exit();
    }
}

function get_user_role() {
    return $_SESSION['role'] ?? null;
}

function get_user_id() {
    return $_SESSION['user_id'] ?? null;
}

function get_user_name() {
    return $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
}
?>
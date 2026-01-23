<?php
// Database Configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'asset_management');
define('DB_USER', 'root');
define('DB_PASS', '');

// Application Settings
define('APP_NAME', 'Asset Management System');
define('APP_URL', 'http://localhost/asset-management');
define('TIMEZONE', 'Asia/Kolkata');

// Session Configuration
session_start();
date_default_timezone_set(TIMEZONE);

// Error Reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
?>
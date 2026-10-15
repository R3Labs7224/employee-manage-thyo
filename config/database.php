<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'employee_management_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// define('DB_NAME', 'maagroup_ems');
// define('DB_USER', 'maagroup_ems');
// define('DB_PASS', 'PmJYET2bZrwfy3kZD2dG');

// Set timezone - Change this to match your location
// Common timezones: 'Asia/Kolkata', 'Asia/Dubai', 'America/New_York', 'Europe/London'
date_default_timezone_set('Asia/Kolkata');

try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    // Set MySQL timezone to match PHP timezone
    $pdo->exec("SET time_zone = '+05:30'"); // India Standard Time (IST)
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
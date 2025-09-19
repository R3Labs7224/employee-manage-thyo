<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_NAME', 'employee_management_system');
define('DB_USER', 'root');
define('DB_PASS', '');

// define('DB_NAME', 'maagroup_ems');
// define('DB_USER', 'maagroup_ems');
// define('DB_PASS', 'PmJYET2bZrwfy3kZD2dG');


try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
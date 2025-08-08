<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

try {
    $pdo = new PDO("mysql:host=localhost;dbname=employee_management_system", "root", "");
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    echo "Database connection successful!";
    
    // Test if tables exist
    $tables = ['users', 'employees', 'departments', 'shifts', 'sites'];
    foreach($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if($stmt->rowCount() > 0) {
            echo "<br>✓ Table '$table' exists";
        } else {
            echo "<br>✗ Table '$table' missing";
        }
    }
} catch(PDOException $e) {
    echo "Database connection failed: " . $e->getMessage();
}
?>
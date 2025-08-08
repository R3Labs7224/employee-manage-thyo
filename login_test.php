<?php
require_once 'config/database.php';
require_once 'includes/session.php';

// Check if basic includes work
echo "Includes loaded successfully!<br>";

// Check if we can query users table
try {
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM users");
    $count = $stmt->fetch()['count'];
    echo "Users table has $count records<br>";
} catch(Exception $e) {
    echo "Error: " . $e->getMessage();
}
?>
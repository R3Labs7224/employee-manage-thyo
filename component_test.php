<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Component Test</h2>";

// Test components existence
$components = [
    'components/sidebar.php',
    'components/header.php',
    'components/footer.php'
];

foreach ($components as $component) {
    if (file_exists($component)) {
        echo "✓ $component exists<br>";
    } else {
        echo "✗ $component missing<br>";
    }
}

// Test if we can include them
echo "<h3>Testing includes...</h3>";

try {
    require_once 'config/database.php';
    require_once 'includes/session.php';
    require_once 'includes/functions.php';
    
    echo "✓ All core includes loaded<br>";
    
    // Test getting user (this might fail if not logged in, which is OK)
    if (function_exists('getUser')) {
        echo "✓ getUser function exists<br>";
    }
    
    if (function_exists('getStats')) {
        echo "✓ getStats function exists<br>";
    }
    
} catch (Exception $e) {
    echo "✗ Include error: " . $e->getMessage() . "<br>";
}

echo "<h3>Test completed!</h3>";
?>
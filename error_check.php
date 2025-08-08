<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);

echo "<h2>PHP Error Check</h2>";

// Test 1: Basic PHP
echo "✓ PHP is working<br>";

// Test 2: Include files one by one
echo "<h3>Testing includes...</h3>";

try {
    require_once 'config/database.php';
    echo "✓ config/database.php loaded<br>";
} catch (Exception $e) {
    echo "✗ config/database.php failed: " . $e->getMessage() . "<br>";
}

try {
    require_once 'includes/session.php';
    echo "✓ includes/session.php loaded<br>";
} catch (Exception $e) {
    echo "✗ includes/session.php failed: " . $e->getMessage() . "<br>";
}

try {
    require_once 'includes/functions.php';
    echo "✓ includes/functions.php loaded<br>";
} catch (Exception $e) {
    echo "✗ includes/functions.php failed: " . $e->getMessage() . "<br>";
}

// Test 3: Session check
echo "<h3>Testing session functions...</h3>";
try {
    $isLoggedIn = isLoggedIn();
    echo "✓ isLoggedIn() function works: " . ($isLoggedIn ? 'true' : 'false') . "<br>";
} catch (Exception $e) {
    echo "✗ isLoggedIn() failed: " . $e->getMessage() . "<br>";
}

// Test 4: Database functions
echo "<h3>Testing database functions...</h3>";
try {
    $stats = getStats($pdo);
    echo "✓ getStats() function works<br>";
    print_r($stats);
} catch (Exception $e) {
    echo "✗ getStats() failed: " . $e->getMessage() . "<br>";
}

echo "<h3>All tests completed!</h3>";
?>
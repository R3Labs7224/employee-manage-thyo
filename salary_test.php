<?php
// Enable error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Salary Debug Test (Root Directory)</h2>";

// Test includes one by one - FOR ROOT DIRECTORY
try {
    echo "1. Testing database connection...<br>";
    require_once 'config/database.php';
    echo "✓ Database connected<br>";

    echo "2. Testing session...<br>";
    require_once 'includes/session.php';
    echo "✓ Session loaded<br>";
    
    echo "3. Testing if logged in...<br>";
    if (!isLoggedIn()) {
        echo "❌ NOT LOGGED IN - Please login first<br>";
        echo "<a href='login.php'>Login Here</a><br>";
        exit;
    } else {
        echo "✓ User is logged in<br>";
    }
    
    echo "4. Testing functions...<br>";
    require_once 'includes/functions.php';
    echo "✓ Functions loaded<br>";
    
    echo "5. Testing permissions...<br>";
    if (!hasPermission('superadmin')) {
        echo "❌ Permission denied - Need superadmin access<br>";
        exit;
    } else {
        echo "✓ Has superadmin permission<br>";
    }
    
    echo "6. Testing database query...<br>";
    $stmt = $pdo->query("SELECT COUNT(*) as count FROM salaries LIMIT 1");
    $count = $stmt->fetch()['count'];
    echo "✓ Found {$count} salary records<br>";
    
    echo "<h3>✅ All tests passed!</h3>";
    echo "<p>Now create the directory structure and place files correctly:</p>";
    echo "<ol>";
    echo "<li>Create directory: <code>pages/salary/</code></li>";
    echo "<li>Place the salary page in: <code>pages/salary/index.php</code></li>";
    echo "</ol>";
    echo "<a href='pages/salary/index.php'>Go to Salary Page (after creating)</a>";
    
} catch (Exception $e) {
    echo "<h3>❌ Error: " . $e->getMessage() . "</h3>";
    echo "<p>File: " . $e->getFile() . "</p>";
    echo "<p>Line: " . $e->getLine() . "</p>";
    
    echo "<h4>Directory Check:</h4>";
    echo "<p>Current directory: " . getcwd() . "</p>";
    echo "<p>Files in current directory:</p><ul>";
    foreach (scandir('.') as $file) {
        if ($file != '.' && $file != '..') {
            echo "<li>$file " . (is_dir($file) ? '(directory)' : '(file)') . "</li>";
        }
    }
    echo "</ul>";
}
?>
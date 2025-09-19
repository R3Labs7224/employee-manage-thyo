<?php
// Simple database connection test for live server
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Database Connection Test</h2>";

// Database configuration (using live server credentials)
define('DB_HOST', 'localhost');
define('DB_NAME', 'maagroup_ems');
define('DB_USER', 'maagroup_ems');
define('DB_PASS', 'PmJYET2bZrwfy3kZD2dG');

echo "<p><strong>Testing with:</strong></p>";
echo "<ul>";
echo "<li>Host: " . DB_HOST . "</li>";
echo "<li>Database: " . DB_NAME . "</li>";
echo "<li>User: " . DB_USER . "</li>";
echo "<li>Password: " . (DB_PASS ? '[SET]' : '[EMPTY]') . "</li>";
echo "</ul>";

try {
    // Test basic connection
    echo "<h3>Step 1: Testing Connection</h3>";
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME, DB_USER, DB_PASS);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    echo "<p style='color: green;'>✓ Database connection successful!</p>";

    // Test basic query
    echo "<h3>Step 2: Testing Basic Query</h3>";
    $test_query = $pdo->query("SELECT 1 as test");
    $result = $test_query->fetch();
    echo "<p style='color: green;'>✓ Basic query successful: " . $result['test'] . "</p>";

    // Show all tables
    echo "<h3>Step 3: Available Tables</h3>";
    $stmt = $pdo->query("SHOW TABLES");
    $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "<ul>";
    foreach ($tables as $table) {
        echo "<li>$table</li>";
    }
    echo "</ul>";

    // Test required tables for mobile app
    echo "<h3>Step 4: Checking Required Tables</h3>";
    $required_tables = ['employees', 'tasks', 'attendance', 'expenses', 'expense_categories', 'sites', 'users'];
    $missing_tables = [];

    foreach ($required_tables as $table) {
        if (in_array($table, $tables)) {
            echo "<p style='color: green;'>✓ Table '$table' exists</p>";
        } else {
            echo "<p style='color: red;'>✗ Table '$table' MISSING</p>";
            $missing_tables[] = $table;
        }
    }

    // Test employee table if exists
    if (in_array('employees', $tables)) {
        echo "<h3>Step 5: Employee Table Structure</h3>";
        $stmt = $pdo->query("DESCRIBE employees");
        $columns = $stmt->fetchAll();
        echo "<table border='1' style='border-collapse: collapse;'>";
        echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th></tr>";
        foreach ($columns as $column) {
            echo "<tr><td>{$column['Field']}</td><td>{$column['Type']}</td><td>{$column['Null']}</td><td>{$column['Key']}</td></tr>";
        }
        echo "</table>";

        // Test sample employee query
        echo "<h3>Step 6: Sample Employee Query</h3>";
        $stmt = $pdo->query("SELECT COUNT(*) as count FROM employees");
        $count = $stmt->fetch();
        echo "<p>Total employees: " . $count['count'] . "</p>";
    }

    echo "<h3>Summary</h3>";
    if (empty($missing_tables)) {
        echo "<p style='color: green; font-weight: bold;'>✓ All tests passed! Database is working correctly.</p>";
    } else {
        echo "<p style='color: red; font-weight: bold;'>✗ Missing tables: " . implode(', ', $missing_tables) . "</p>";
        echo "<p>You need to import your database schema to the live server.</p>";
    }

} catch (PDOException $e) {
    echo "<h3 style='color: red;'>Database Error</h3>";
    echo "<p><strong>Error Code:</strong> " . $e->getCode() . "</p>";
    echo "<p><strong>Error Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";

    // Common error explanations
    if ($e->getCode() == 1045) {
        echo "<p style='color: orange;'><strong>Tip:</strong> Access denied error - check username/password</p>";
    } elseif ($e->getCode() == 1049) {
        echo "<p style='color: orange;'><strong>Tip:</strong> Database doesn't exist - check database name</p>";
    } elseif ($e->getCode() == 2002) {
        echo "<p style='color: orange;'><strong>Tip:</strong> Can't connect - check if MySQL is running or try 127.0.0.1 instead of localhost</p>";
    }
} catch (Exception $e) {
    echo "<h3 style='color: red;'>General Error</h3>";
    echo "<p><strong>Error Message:</strong> " . $e->getMessage() . "</p>";
    echo "<p><strong>File:</strong> " . $e->getFile() . "</p>";
    echo "<p><strong>Line:</strong> " . $e->getLine() . "</p>";
}

echo "<p><em>Test completed at: " . date('Y-m-d H:i:s') . "</em></p>";
?>
<?php
// Quick database connection test
require_once '../../config/database.php';
require_once '../common/response.php';

try {
    // Test basic connection
    $test_query = $pdo->query("SELECT 1 as test");
    $result = $test_query->fetch();

    if ($result['test'] == 1) {
        echo "✓ Database connection successful\n";
    }

    // Test required tables exist
    $tables = ['employees', 'tasks', 'attendance', 'expenses', 'expense_categories', 'sites', 'users'];
    $missing_tables = [];

    foreach ($tables as $table) {
        $stmt = $pdo->query("SHOW TABLES LIKE '$table'");
        if ($stmt->rowCount() == 0) {
            $missing_tables[] = $table;
        }
    }

    if (empty($missing_tables)) {
        echo "✓ All required tables exist\n";
    } else {
        echo "✗ Missing tables: " . implode(', ', $missing_tables) . "\n";
    }

    // Test employee table structure
    $stmt = $pdo->query("DESCRIBE employees");
    $columns = $stmt->fetchAll(PDO::FETCH_COLUMN);
    echo "✓ Employee table columns: " . implode(', ', $columns) . "\n";

    sendSuccess('Database test completed', [
        'connection' => 'success',
        'missing_tables' => $missing_tables,
        'employee_columns' => $columns
    ]);

} catch (PDOException $e) {
    echo "✗ Database error: " . $e->getMessage() . "\n";
    sendError('Database connection failed: ' . $e->getMessage(), 500);
} catch (Exception $e) {
    echo "✗ General error: " . $e->getMessage() . "\n";
    sendError('Test failed: ' . $e->getMessage(), 500);
}
?>
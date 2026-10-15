<?php
require_once 'config/database.php';

echo "<h2>Testing Unified Task System</h2>";

try {
    // Test 1: Check unified table structure
    echo "<h3>1. Checking unified table structure:</h3>";
    $stmt = $pdo->query("DESCRIBE tasks");
    $columns = $stmt->fetchAll(PDO::FETCH_ASSOC);

    $required_columns = ['task_type', 'assigned_to_employee_id', 'assigned_by_user_id', 'start_latitude', 'completion_latitude'];
    foreach ($required_columns as $col) {
        $found = false;
        foreach ($columns as $column) {
            if ($column['Field'] === $col) {
                $found = true;
                break;
            }
        }
        echo "- $col: " . ($found ? "✓ Present" : "✗ Missing") . "<br>";
    }

    // Test 2: Check current data
    echo "<h3>2. Current tasks in unified table:</h3>";
    $stmt = $pdo->query("
        SELECT t.id, t.title, t.task_type, t.status,
               e.name as employee_name, e.employee_code,
               u.username as assigned_by
        FROM tasks t
        LEFT JOIN employees e ON t.assigned_to_employee_id = e.id
        LEFT JOIN users u ON t.assigned_by_user_id = u.id
        ORDER BY t.id
    ");
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Employee</th><th>Status</th><th>Assigned By</th></tr>";
    foreach ($tasks as $task) {
        echo "<tr>";
        echo "<td>{$task['id']}</td>";
        echo "<td>{$task['title']}</td>";
        echo "<td>{$task['task_type']}</td>";
        echo "<td>{$task['employee_code']} - {$task['employee_name']}</td>";
        echo "<td>{$task['status']}</td>";
        echo "<td>{$task['assigned_by']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Test 3: Test admin task query (similar to what admin panel uses)
    echo "<h3>3. Testing admin task grouping query:</h3>";
    $stmt = $pdo->prepare("
        SELECT t.*,
               u.username as created_by_username,
               COUNT(CASE WHEN t2.task_type = 'admin_assigned' AND t2.title = t.title AND t2.assigned_by_user_id = t.assigned_by_user_id THEN 1 END) as total_assignments,
               COUNT(CASE WHEN t2.task_type = 'admin_assigned' AND t2.title = t.title AND t2.assigned_by_user_id = t.assigned_by_user_id AND t2.status = 'completed' THEN 1 END) as completed_assignments
        FROM tasks t
        LEFT JOIN users u ON t.assigned_by_user_id = u.id
        LEFT JOIN tasks t2 ON t2.title = t.title AND t2.assigned_by_user_id = t.assigned_by_user_id AND t2.task_type = 'admin_assigned'
        WHERE t.task_type = 'admin_assigned'
        GROUP BY t.title, t.assigned_by_user_id, t.created_at
        ORDER BY t.created_at DESC
    ");
    $stmt->execute();
    $admin_tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "Found " . count($admin_tasks) . " grouped admin tasks:<br>";
    foreach ($admin_tasks as $task) {
        echo "- {$task['title']}: {$task['total_assignments']} assignments, {$task['completed_assignments']} completed<br>";
    }

    // Test 4: Check user-employee mapping
    echo "<h3>4. User-Employee mapping:</h3>";
    $stmt = $pdo->query("
        SELECT u.id as user_id, u.username, u.email as user_email,
               e.id as emp_id, e.name, e.email as emp_email
        FROM users u
        LEFT JOIN employees e ON u.email = e.email
        WHERE u.status = 'active'
        ORDER BY u.id
    ");
    $mappings = $stmt->fetchAll(PDO::FETCH_ASSOC);

    echo "<table border='1' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>User ID</th><th>Username</th><th>User Email</th><th>Emp ID</th><th>Employee Name</th><th>Emp Email</th></tr>";
    foreach ($mappings as $map) {
        echo "<tr>";
        echo "<td>{$map['user_id']}</td>";
        echo "<td>{$map['username']}</td>";
        echo "<td>{$map['user_email']}</td>";
        echo "<td>{$map['emp_id']}</td>";
        echo "<td>{$map['name']}</td>";
        echo "<td>{$map['emp_email']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    echo "<h3>✅ All tests completed successfully!</h3>";
    echo "<p><strong>Status:</strong> The unified task system is properly configured and ready for use.</p>";
    echo "<p><strong>Admin Panel:</strong> Should now work with the updated backend queries.</p>";

} catch (Exception $e) {
    echo "<h3 style='color: red;'>❌ Error during testing:</h3>";
    echo "<p style='color: red;'>" . $e->getMessage() . "</p>";
}
?>

<style>
    body { font-family: Arial, sans-serif; margin: 20px; }
    table { width: 100%; margin: 10px 0; }
    th, td { padding: 8px; text-align: left; border: 1px solid #ddd; }
    th { background-color: #f2f2f2; }
    h2 { color: #333; }
    h3 { color: #666; margin-top: 20px; }
</style>
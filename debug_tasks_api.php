<?php
// Debug file to test the tasks API
require_once 'config/database.php';
require_once 'api/common/response.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Tasks API Debug Tool</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background-color: #f5f5f5;
        }
        .container {
            max-width: 1200px;
            margin: 0 auto;
        }
        h1 {
            color: #333;
            text-align: center;
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .section {
            margin: 20px 0;
            padding: 15px;
            border: 1px solid #ddd;
            border-radius: 5px;
            background: white;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .success {
            background: #d4edda;
            border-color: #c3e6cb;
        }
        .error {
            background: #f8d7da;
            border-color: #f5c6cb;
        }
        .info {
            background: #d1ecf1;
            border-color: #bee5eb;
        }
        pre {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 3px;
            overflow-x: auto;
        }
        table {
            border-collapse: collapse;
            width: 100%;
            margin: 10px 0;
        }
        th, td {
            border: 1px solid #ddd;
            padding: 8px;
            text-align: left;
        }
        th {
            background-color: #f2f2f2;
            font-weight: bold;
        }
        .debug-info {
            background: #fff3cd;
            border: 1px solid #ffeaa7;
            padding: 10px;
            border-radius: 5px;
            margin: 10px 0;
        }
        hr {
            border: none;
            height: 2px;
            background: #eee;
            margin: 20px 0;
        }
        .status-success { color: #28a745; font-weight: bold; }
        .status-error { color: #dc3545; font-weight: bold; }
        .status-warning { color: #ffc107; font-weight: bold; }
    </style>
</head>
<body>
    <div class="container">
        <h1>Tasks API Debug Tool</h1>
<?php

// Step 1: Check database connection
echo "<div class='section info'>";
echo "<h2>1. Database Connection</h2>";
try {
    $pdo->query("SELECT 1");
    echo "<span class='status-success'>✅ Database connection successful</span><br>";
} catch (Exception $e) {
    echo "<span class='status-error'>❌ Database connection failed: " . $e->getMessage() . "</span><br>";
    exit;
}
echo "</div>";

// Step 2: Check users table
echo "<div class='section info'>";
echo "<h2>2. Available Users</h2>";
try {
    $users_stmt = $pdo->query("SELECT id, username, email, role, status FROM users WHERE status = 'active'");
    $users = $users_stmt->fetchAll();

    echo "<table>";
    echo "<tr><th>ID</th><th>Username</th><th>Email</th><th>Role</th></tr>";
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>{$user['id']}</td>";
        echo "<td>{$user['username']}</td>";
        echo "<td>{$user['email']}</td>";
        echo "<td>{$user['role']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error fetching users: " . $e->getMessage();
}
echo "</div>";

// Step 3: Check employees table
echo "<div class='section info'>";
echo "<h2>3. Available Employees</h2>";
try {
    $emp_stmt = $pdo->query("SELECT id, employee_code, name, email, status FROM employees WHERE status = 'active'");
    $employees = $emp_stmt->fetchAll();

    echo "<table>";
    echo "<tr><th>ID</th><th>Code</th><th>Name</th><th>Email</th></tr>";
    foreach ($employees as $emp) {
        echo "<tr>";
        echo "<td>{$emp['id']}</td>";
        echo "<td>{$emp['employee_code']}</td>";
        echo "<td>{$emp['name']}</td>";
        echo "<td>{$emp['email']}</td>";
        echo "</tr>";
    }
    echo "</table>";
} catch (Exception $e) {
    echo "❌ Error fetching employees: " . $e->getMessage();
}
echo "</div>";

// Step 4: Check tasks table
echo "<div class='section info'>";
echo "<h2>4. All Tasks</h2>";
try {
    $tasks_stmt = $pdo->query("
        SELECT t.id, t.title, t.employee_id, t.admin_created, t.assigned_by, t.status, t.created_at,
               e.name as employee_name
        FROM tasks t
        LEFT JOIN employees e ON t.employee_id = e.id
        ORDER BY t.created_at DESC
    ");
    $all_tasks = $tasks_stmt->fetchAll();

    if (empty($all_tasks)) {
        echo "<span class='status-warning'>⚠️ No tasks found in database</span>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Title</th><th>Employee</th><th>Admin Created</th><th>Assigned By</th><th>Status</th><th>Created</th></tr>";
        foreach ($all_tasks as $task) {
            echo "<tr>";
            echo "<td>{$task['id']}</td>";
            echo "<td>{$task['title']}</td>";
            echo "<td>{$task['employee_name']} (ID: {$task['employee_id']})</td>";
            echo "<td>" . ($task['admin_created'] ? 'Yes' : 'No') . "</td>";
            echo "<td>{$task['assigned_by']}</td>";
            echo "<td>{$task['status']}</td>";
            echo "<td>{$task['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching tasks: " . $e->getMessage();
}
echo "</div>";

// Step 5: Check task_assignments table
echo "<div class='section info'>";
echo "<h2>5. Task Assignments</h2>";
try {
    $assignments_stmt = $pdo->query("
        SELECT ta.id, ta.task_id, ta.assigned_to, ta.assigned_by, ta.status, ta.created_at,
               t.title as task_title,
               u1.username as assigned_to_username,
               u2.username as assigned_by_username
        FROM task_assignments ta
        LEFT JOIN tasks t ON ta.task_id = t.id
        LEFT JOIN users u1 ON ta.assigned_to = u1.id
        LEFT JOIN users u2 ON ta.assigned_by = u2.id
        ORDER BY ta.created_at DESC
    ");
    $assignments = $assignments_stmt->fetchAll();

    if (empty($assignments)) {
        echo "<span class='status-warning'>⚠️ No task assignments found in database</span>";
    } else {
        echo "<table>";
        echo "<tr><th>ID</th><th>Task</th><th>Assigned To</th><th>Assigned By</th><th>Status</th><th>Created</th></tr>";
        foreach ($assignments as $assignment) {
            echo "<tr>";
            echo "<td>{$assignment['id']}</td>";
            echo "<td>{$assignment['task_title']} (ID: {$assignment['task_id']})</td>";
            echo "<td>{$assignment['assigned_to_username']} (ID: {$assignment['assigned_to']})</td>";
            echo "<td>{$assignment['assigned_by_username']} (ID: {$assignment['assigned_by']})</td>";
            echo "<td>{$assignment['status']}</td>";
            echo "<td>{$assignment['created_at']}</td>";
            echo "</tr>";
        }
        echo "</table>";
    }
} catch (Exception $e) {
    echo "❌ Error fetching task assignments: " . $e->getMessage();
}
echo "</div>";

// Step 6: Test API for each employee user
echo "<div class='section info'>";
echo "<h2>6. Test API for Each Employee User</h2>";

$employee_users = array_filter($users, function($user) { return $user['role'] === 'employee'; });

if (empty($employee_users)) {
    echo "⚠️ No employee users found. Please create employee accounts first.";
} else {
    foreach ($employee_users as $user) {
        echo "<h3>Testing for user: {$user['username']} (ID: {$user['id']})</h3>";

        // Simulate the API call
        try {
            // Find corresponding employee
            $emp_stmt = $pdo->prepare("SELECT * FROM employees WHERE email = ?");
            $emp_stmt->execute([$user['email']]);
            $employee = $emp_stmt->fetch();

            if (!$employee) {
                echo "❌ No employee record found for user {$user['username']}<br>";
                continue;
            }

            echo "✅ Employee found: {$employee['name']} (Employee ID: {$employee['id']})<br>";

            // Test the actual API query
            $date = date('Y-m-d');

            $tasks_query = "
                SELECT
                    t.id,
                    t.employee_id,
                    t.attendance_id,
                    t.site_id,
                    t.title,
                    t.description,
                    t.status,
                    t.created_at,
                    t.admin_created,
                    t.assigned_by,
                    s.name as site_name,
                    u.username as assigned_by_name,
                    ta.id as assignment_id,
                    ta.status as assignment_status,
                    CASE
                        WHEN t.admin_created = 1 THEN 'assigned'
                        ELSE 'self_created'
                    END as task_type
                FROM tasks t
                JOIN sites s ON t.site_id = s.id
                LEFT JOIN users u ON t.assigned_by = u.id
                LEFT JOIN task_assignments ta ON t.id = ta.task_id AND ta.assigned_to = ?
                WHERE (
                    -- Self-created tasks
                    (t.employee_id = ? AND DATE(t.created_at) = ?) OR
                    -- Admin-assigned tasks (show all assigned tasks, not just today's)
                    (t.admin_created = 1 AND ta.assigned_to = ?)
                )
                ORDER BY t.created_at DESC
            ";

            $tasks_stmt = $pdo->prepare($tasks_query);
            $tasks_stmt->execute([
                $user['id'], // for task_assignments join
                $employee['id'], // for self-created tasks
                $date,          // for self-created tasks date
                $user['id']  // for admin-assigned tasks
            ]);
            $user_tasks = $tasks_stmt->fetchAll(PDO::FETCH_ASSOC);

            echo "📊 Query returned " . count($user_tasks) . " tasks<br>";

            if (empty($user_tasks)) {
                echo "⚠️ No tasks found for this user<br>";

                // Debug: Check what should be found
                echo "<strong>Debug info:</strong><br>";
                echo "- User ID: {$user['id']}<br>";
                echo "- Employee ID: {$employee['id']}<br>";
                echo "- Looking for date: {$date}<br>";

                // Check for self-created tasks
                $self_tasks_stmt = $pdo->prepare("SELECT COUNT(*) as count FROM tasks WHERE employee_id = ? AND DATE(created_at) = ?");
                $self_tasks_stmt->execute([$employee['id'], $date]);
                $self_count = $self_tasks_stmt->fetch()['count'];
                echo "- Self-created tasks today: {$self_count}<br>";

                // Check for assigned tasks
                $assigned_tasks_stmt = $pdo->prepare("
                    SELECT COUNT(*) as count
                    FROM tasks t
                    LEFT JOIN task_assignments ta ON t.id = ta.task_id
                    WHERE t.admin_created = 1 AND ta.assigned_to = ?
                ");
                $assigned_tasks_stmt->execute([$user['id']]);
                $assigned_count = $assigned_tasks_stmt->fetch()['count'];
                echo "- Assigned tasks: {$assigned_count}<br>";

            } else {
                echo "<table>";
                echo "<tr><th>ID</th><th>Title</th><th>Type</th><th>Status</th><th>Site</th></tr>";
                foreach ($user_tasks as $task) {
                    echo "<tr>";
                    echo "<td>{$task['id']}</td>";
                    echo "<td>{$task['title']}</td>";
                    echo "<td>{$task['task_type']}</td>";
                    echo "<td>{$task['status']}</td>";
                    echo "<td>{$task['site_name']}</td>";
                    echo "</tr>";
                }
                echo "</table>";
            }

        } catch (Exception $e) {
            echo "❌ Error testing API for {$user['username']}: " . $e->getMessage() . "<br>";
        }

        echo "<hr>";
    }
}

echo "</div>";

// Step 7: Quick fix suggestions
echo "<div class='section info'>";
echo "<h2>7. Quick Actions</h2>";
echo "<p>If you want to create a test task assignment:</p>";
echo "<ol>";
echo "<li>Go to the admin panel: <a href='pages/tasks/assign.php' target='_blank'>Task Assignment</a></li>";
echo "<li>Create a new task and assign it to one of the employee users</li>";
echo "<li>Refresh this debug page to see the results</li>";
echo "</ol>";
echo "</div>";

?>
    </div>
</body>
</html>
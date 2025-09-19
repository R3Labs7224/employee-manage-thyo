<?php
require_once 'config/database.php';

echo "<h1>Admin System Setup Test</h1>";

// Test 1: Check if admin_roles table exists
echo "<h2>1. Testing admin_roles table</h2>";
try {
    $stmt = $pdo->query("DESCRIBE admin_roles");
    $columns = $stmt->fetchAll();
    echo "<p>✅ admin_roles table exists with " . count($columns) . " columns:</p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "<p>❌ admin_roles table does not exist: " . $e->getMessage() . "</p>";
}

// Test 2: Check if admin roles data exists
echo "<h2>2. Testing admin roles data</h2>";
try {
    $stmt = $pdo->query("SELECT * FROM admin_roles");
    $roles = $stmt->fetchAll();
    echo "<p>✅ Found " . count($roles) . " admin roles:</p>";
    echo "<ul>";
    foreach ($roles as $role) {
        echo "<li>" . $role['display_name'] . " (" . $role['role_name'] . ")</li>";
    }
    echo "</ul>";
} catch (PDOException $e) {
    echo "<p>❌ Error fetching admin roles: " . $e->getMessage() . "</p>";
}

// Test 3: Check users table columns
echo "<h2>3. Testing users table columns</h2>";
try {
    $stmt = $pdo->query("DESCRIBE users");
    $columns = $stmt->fetchAll();
    echo "<p>✅ users table columns:</p>";
    echo "<ul>";
    foreach ($columns as $column) {
        echo "<li>" . $column['Field'] . " (" . $column['Type'] . ")</li>";
    }
    echo "</ul>";

    // Check for specific admin columns
    $adminColumns = ['admin_role_id', 'created_by', 'is_admin', 'last_login', 'account_locked', 'failed_login_attempts'];
    $existingColumns = array_column($columns, 'Field');

    echo "<p>Admin-specific columns:</p>";
    echo "<ul>";
    foreach ($adminColumns as $col) {
        if (in_array($col, $existingColumns)) {
            echo "<li>✅ $col exists</li>";
        } else {
            echo "<li>❌ $col missing</li>";
        }
    }
    echo "</ul>";

} catch (PDOException $e) {
    echo "<p>❌ Error checking users table: " . $e->getMessage() . "</p>";
}

// Test 4: Check current user's admin status
echo "<h2>4. Testing current user admin status</h2>";
session_start();
if (isset($_SESSION['user_id'])) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();

        if ($user) {
            echo "<p>Current user: " . $user['username'] . "</p>";
            echo "<p>Role: " . $user['role'] . "</p>";
            echo "<p>Is Admin: " . ($user['is_admin'] ? 'Yes' : 'No') . "</p>";
            echo "<p>Admin Role ID: " . ($user['admin_role_id'] ?? 'Not set') . "</p>";
        }
    } catch (PDOException $e) {
        echo "<p>❌ Error checking current user: " . $e->getMessage() . "</p>";
    }
} else {
    echo "<p>❌ No user logged in</p>";
}

// Test 5: Check audit log table
echo "<h2>5. Testing admin_audit_log table</h2>";
try {
    $stmt = $pdo->query("DESCRIBE admin_audit_log");
    $columns = $stmt->fetchAll();
    echo "<p>✅ admin_audit_log table exists with " . count($columns) . " columns</p>";
} catch (PDOException $e) {
    echo "<p>❌ admin_audit_log table does not exist: " . $e->getMessage() . "</p>";
}

echo "<hr>";
echo "<p><strong>Next Steps:</strong></p>";
echo "<ol>";
echo "<li>If any tables are missing, run the SQL setup script in phpMyAdmin</li>";
echo "<li>If admin columns are missing from users table, run the ALTER TABLE commands</li>";
echo "<li>If user is not marked as admin, update the user record</li>";
echo "<li>Try accessing the admin panel again</li>";
echo "</ol>";

echo "<p><a href='pages/admin/index.php'>→ Go to Admin Panel</a></p>";
?>

<style>
    body { font-family: Arial, sans-serif; margin: 40px; }
    h1 { color: #333; }
    h2 { color: #666; border-bottom: 1px solid #ddd; padding-bottom: 5px; }
    ul { background: #f5f5f5; padding: 15px; border-radius: 5px; }
    p { margin: 10px 0; }
    a { color: #e97316; text-decoration: none; font-weight: bold; }
    a:hover { text-decoration: underline; }
</style>
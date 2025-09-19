<?php
// Comprehensive Debug Form for Admin Creation
session_start();
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h1>🔧 Admin Creation Debug Tool</h1>";
echo "<style>
    body { font-family: Arial, sans-serif; margin: 20px; background: #f5f5f5; }
    .debug-section { background: white; padding: 20px; margin: 10px 0; border-radius: 8px; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
    .success { color: green; font-weight: bold; }
    .error { color: red; font-weight: bold; }
    .warning { color: orange; font-weight: bold; }
    .info { color: blue; }
    pre { background: #f8f9fa; padding: 10px; border-radius: 4px; overflow-x: auto; }
    form { background: #fff; padding: 20px; border: 2px solid #ddd; border-radius: 8px; }
    input, select, button { padding: 8px; margin: 5px 0; border: 1px solid #ddd; border-radius: 4px; }
    button { background: #007cba; color: white; cursor: pointer; }
    button:hover { background: #005a87; }
    .step { margin: 10px 0; padding: 10px; border-left: 4px solid #007cba; background: #f0f8ff; }
</style>";

// Step 1: Check Dependencies
echo "<div class='debug-section'>";
echo "<h2>📋 Step 1: Checking Dependencies</h2>";

$issues = [];

try {
    require_once 'config/database.php';
    echo "<div class='success'>✅ Database connection: SUCCESS</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Database connection: FAILED - " . $e->getMessage() . "</div>";
    $issues[] = "Database connection failed";
}

try {
    require_once 'includes/session.php';
    echo "<div class='success'>✅ Session functions: LOADED</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Session functions: FAILED - " . $e->getMessage() . "</div>";
    $issues[] = "Session functions failed";
}

try {
    require_once 'includes/functions.php';
    echo "<div class='success'>✅ Helper functions: LOADED</div>";
} catch (Exception $e) {
    echo "<div class='error'>❌ Helper functions: FAILED - " . $e->getMessage() . "</div>";
    $issues[] = "Helper functions failed";
}

// Check if functions exist
if (function_exists('sanitize')) {
    echo "<div class='success'>✅ sanitize() function: EXISTS</div>";
} else {
    echo "<div class='error'>❌ sanitize() function: MISSING</div>";
    $issues[] = "sanitize() function missing";
}

if (function_exists('logAdminAction')) {
    echo "<div class='success'>✅ logAdminAction() function: EXISTS</div>";
} else {
    echo "<div class='error'>❌ logAdminAction() function: MISSING</div>";
    $issues[] = "logAdminAction() function missing";
}

echo "</div>";

// Step 2: Check Database Tables
echo "<div class='debug-section'>";
echo "<h2>🗄️ Step 2: Database Table Analysis</h2>";

try {
    // Check users table structure
    $stmt = $pdo->query("DESCRIBE users");
    $users_structure = $stmt->fetchAll();

    echo "<h3>Users Table Structure:</h3>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse;'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    foreach ($users_structure as $column) {
        $highlight = ($column['Field'] === 'id' && $column['Extra'] === 'auto_increment') ? 'success' : '';
        echo "<tr class='$highlight'>";
        echo "<td>{$column['Field']}</td>";
        echo "<td>{$column['Type']}</td>";
        echo "<td>{$column['Null']}</td>";
        echo "<td>{$column['Key']}</td>";
        echo "<td>{$column['Default']}</td>";
        echo "<td>{$column['Extra']}</td>";
        echo "</tr>";
    }
    echo "</table>";

    // Check if ID has auto_increment
    $id_column = array_filter($users_structure, function($col) { return $col['Field'] === 'id'; });
    $id_column = reset($id_column);

    if ($id_column && $id_column['Extra'] === 'auto_increment') {
        echo "<div class='success'>✅ ID field has AUTO_INCREMENT</div>";
    } else {
        echo "<div class='error'>❌ ID field missing AUTO_INCREMENT</div>";
        $issues[] = "ID field missing AUTO_INCREMENT";
    }

    // Check role enum values
    $role_column = array_filter($users_structure, function($col) { return $col['Field'] === 'role'; });
    $role_column = reset($role_column);

    if ($role_column) {
        echo "<div class='info'>ℹ️ Role field type: {$role_column['Type']}</div>";
        if (strpos($role_column['Type'], 'admin') !== false) {
            echo "<div class='success'>✅ 'admin' role is allowed</div>";
        } else {
            echo "<div class='error'>❌ 'admin' role not in enum</div>";
            $issues[] = "'admin' role not allowed in enum";
        }
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Database table check failed: " . $e->getMessage() . "</div>";
    $issues[] = "Database table check failed";
}

echo "</div>";

// Step 3: Check Admin Roles
echo "<div class='debug-section'>";
echo "<h2>👥 Step 3: Admin Roles Check</h2>";

try {
    $stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY id");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll();

    echo "<div class='info'>Found " . count($adminRoles) . " active admin roles:</div>";
    echo "<table border='1' cellpadding='5' style='border-collapse: collapse; margin: 10px 0;'>";
    echo "<tr><th>ID</th><th>Role Name</th><th>Display Name</th><th>Usable for Creation</th></tr>";

    $usable_roles = 0;
    foreach ($adminRoles as $role) {
        $usable = ($role['role_name'] !== 'superadmin') ? 'YES' : 'NO';
        if ($usable === 'YES') $usable_roles++;

        echo "<tr>";
        echo "<td>{$role['id']}</td>";
        echo "<td>{$role['role_name']}</td>";
        echo "<td>{$role['display_name']}</td>";
        echo "<td class='" . ($usable === 'YES' ? 'success' : 'warning') . "'>$usable</td>";
        echo "</tr>";
    }
    echo "</table>";

    if ($usable_roles > 0) {
        echo "<div class='success'>✅ $usable_roles admin roles available for creation</div>";
    } else {
        echo "<div class='error'>❌ No admin roles available for creation</div>";
        $issues[] = "No admin roles available";
    }

} catch (Exception $e) {
    echo "<div class='error'>❌ Admin roles check failed: " . $e->getMessage() . "</div>";
    $issues[] = "Admin roles check failed";
}

echo "</div>";

// Step 4: Session Check
echo "<div class='debug-section'>";
echo "<h2>🔐 Step 4: Session & Authentication Check</h2>";

echo "<div class='info'>Session ID: " . session_id() . "</div>";
echo "<div class='info'>Session data:</div>";
echo "<pre>" . print_r($_SESSION, true) . "</pre>";

if (function_exists('isLoggedIn')) {
    if (isLoggedIn()) {
        echo "<div class='success'>✅ User is logged in</div>";

        if (function_exists('getUser')) {
            $current_user = getUser();
            if ($current_user) {
                echo "<div class='success'>✅ User data retrieved</div>";
                echo "<div class='info'>Current user: {$current_user['username']} (ID: {$current_user['id']})</div>";
            } else {
                echo "<div class='error'>❌ Failed to get user data</div>";
                $issues[] = "Failed to get user data";
            }
        }

        if (function_exists('isSuperAdmin')) {
            if (isSuperAdmin()) {
                echo "<div class='success'>✅ User has superadmin permissions</div>";
            } else {
                echo "<div class='error'>❌ User lacks superadmin permissions</div>";
                $issues[] = "User lacks superadmin permissions";
            }
        }
    } else {
        echo "<div class='warning'>⚠️ User not logged in (this might be expected for testing)</div>";
    }
} else {
    echo "<div class='error'>❌ isLoggedIn function not available</div>";
    $issues[] = "isLoggedIn function not available";
}

echo "</div>";

// Step 5: Form Processing Test
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    echo "<div class='debug-section'>";
    echo "<h2>🚀 Step 5: Form Processing Test</h2>";

    echo "<div class='step'>📥 POST Data Received:</div>";
    echo "<pre>" . print_r($_POST, true) . "</pre>";

    if (isset($_POST['action']) && $_POST['action'] === 'create_admin') {
        echo "<div class='step'>🔄 Processing Admin Creation...</div>";

        // Get form data
        $username = isset($_POST['username']) ? sanitize($_POST['username']) : '';
        $password = $_POST['password'] ?? '';
        $admin_role_id = (int)($_POST['admin_role_id'] ?? 0);
        $status = $_POST['status'] ?? 'active';

        echo "<div class='info'>Processed Data:</div>";
        echo "<ul>";
        echo "<li>Username: '$username'</li>";
        echo "<li>Password: " . (empty($password) ? 'EMPTY' : 'SET (' . strlen($password) . ' chars)') . "</li>";
        echo "<li>Role ID: $admin_role_id</li>";
        echo "<li>Status: '$status'</li>";
        echo "</ul>";

        // Validation
        echo "<div class='step'>✅ Validation Check:</div>";
        $validation_passed = true;

        if (empty($username)) {
            echo "<div class='error'>❌ Username is empty</div>";
            $validation_passed = false;
        } else {
            echo "<div class='success'>✅ Username provided</div>";
        }

        if (empty($password)) {
            echo "<div class='error'>❌ Password is empty</div>";
            $validation_passed = false;
        } else {
            echo "<div class='success'>✅ Password provided</div>";
        }

        if ($admin_role_id <= 0) {
            echo "<div class='error'>❌ Invalid admin role ID</div>";
            $validation_passed = false;
        } else {
            echo "<div class='success'>✅ Admin role ID provided</div>";
        }

        if ($validation_passed) {
            echo "<div class='step'>💾 Database Operation:</div>";

            try {
                // Check if username exists
                $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$username]);
                if ($stmt->fetch()) {
                    echo "<div class='error'>❌ Username '$username' already exists</div>";
                } else {
                    echo "<div class='success'>✅ Username '$username' is available</div>";

                    // Attempt to create user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    echo "<div class='info'>🔐 Password hashed successfully</div>";

                    $sql = "INSERT INTO users (username, password, role, admin_role_id, is_admin, status, created_by) VALUES (?, ?, 'admin', ?, 1, ?, ?)";
                    echo "<div class='info'>📝 SQL Query: $sql</div>";
                    echo "<div class='info'>📝 Parameters: [username, ***password***, $admin_role_id, '$status', 1]</div>";

                    $stmt = $pdo->prepare($sql);

                    if ($stmt->execute([$username, $hashedPassword, $admin_role_id, $status, 1])) {
                        $new_id = $pdo->lastInsertId();
                        echo "<div class='success'>🎉 SUCCESS! Admin user created with ID: $new_id</div>";

                        // Try to log the action
                        try {
                            logAdminAction('create_admin', 'user', $new_id, [
                                'username' => $username,
                                'admin_role_id' => $admin_role_id
                            ]);
                            echo "<div class='success'>✅ Admin action logged successfully</div>";
                        } catch (Exception $e) {
                            echo "<div class='warning'>⚠️ Failed to log admin action: " . $e->getMessage() . "</div>";
                        }

                        // Verify the user was created
                        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
                        $stmt->execute([$new_id]);
                        $created_user = $stmt->fetch();

                        if ($created_user) {
                            echo "<div class='success'>✅ User verification successful</div>";
                            echo "<div class='info'>Created user data:</div>";
                            echo "<pre>" . print_r($created_user, true) . "</pre>";
                        }

                    } else {
                        echo "<div class='error'>❌ Failed to execute INSERT statement</div>";
                        echo "<div class='error'>PDO Error Info:</div>";
                        echo "<pre>" . print_r($stmt->errorInfo(), true) . "</pre>";
                    }
                }

            } catch (Exception $e) {
                echo "<div class='error'>❌ Database error: " . $e->getMessage() . "</div>";
                echo "<div class='error'>Stack trace:</div>";
                echo "<pre>" . $e->getTraceAsString() . "</pre>";
            }
        } else {
            echo "<div class='error'>❌ Validation failed - cannot proceed with creation</div>";
        }
    }

    echo "</div>";
}

// Issues Summary
if (!empty($issues)) {
    echo "<div class='debug-section'>";
    echo "<h2>⚠️ Issues Found</h2>";
    echo "<ul>";
    foreach ($issues as $issue) {
        echo "<li class='error'>$issue</li>";
    }
    echo "</ul>";
    echo "</div>";
}

// Debug Form
echo "<div class='debug-section'>";
echo "<h2>🧪 Test Form</h2>";
echo "<form method='POST'>";
echo "<input type='hidden' name='action' value='create_admin'>";
echo "<p><label>Username: <input type='text' name='username' value='testadmin" . time() . "' required></label></p>";
echo "<p><label>Password: <input type='password' name='password' value='password123' required></label></p>";
echo "<p><label>Admin Role ID: <select name='admin_role_id' required>";
echo "<option value=''>Select Role</option>";

if (isset($adminRoles)) {
    foreach ($adminRoles as $role) {
        if ($role['role_name'] !== 'superadmin') {
            echo "<option value='{$role['id']}'>{$role['display_name']} (ID: {$role['id']})</option>";
        }
    }
}

echo "</select></label></p>";
echo "<p><label>Status: <select name='status'>";
echo "<option value='active'>Active</option>";
echo "<option value='inactive'>Inactive</option>";
echo "</select></label></p>";
echo "<p><button type='submit'>🚀 Test Create Admin</button></p>";
echo "</form>";
echo "</div>";

// JavaScript Test
echo "<script>";
echo "console.log('Debug page loaded successfully');";
echo "</script>";
?>
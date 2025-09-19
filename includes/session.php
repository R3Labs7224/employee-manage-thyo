<?php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: login.php');
        exit;
    }
}

function getUser() {
    if (!isLoggedIn()) return null;

    global $pdo;
    $stmt = $pdo->prepare("
        SELECT u.*, ar.role_name as admin_role_name, ar.display_name as admin_role_display, ar.permissions as admin_permissions
        FROM users u
        LEFT JOIN admin_roles ar ON u.admin_role_id = ar.id
        WHERE u.id = ?
    ");
    $stmt->execute([$_SESSION['user_id']]);
    return $stmt->fetch();
}

function hasPermission($role) {
    $user = getUser();
    if (!$user) return false;

    if ($role === 'supervisor') {
        return in_array($user['role'], ['supervisor', 'superadmin']);
    }

    if ($role === 'superadmin') {
        return $user['role'] === 'superadmin';
    }

    return false;
}

function isSuperAdmin() {
    $user = getUser();
    return $user && $user['role'] === 'superadmin';
}

function isAdmin() {
    $user = getUser();
    return $user && ($user['is_admin'] == 1 || $user['role'] === 'superadmin');
}

function hasAdminPermission($permission) {
    $user = getUser();
    if (!$user) return false;

    // Superadmin has all permissions
    if ($user['role'] === 'superadmin') return true;

    // Check if user is admin and has the specific permission
    if (!$user['is_admin']) return false;

    if (!$user['admin_permissions']) return false;

    $permissions = json_decode($user['admin_permissions'], true);
    if (!$permissions) return false;

    // Check for "all_permissions" (superadmin equivalent)
    if (in_array('all_permissions', $permissions)) return true;

    // Check for specific permission
    return in_array($permission, $permissions);
}

function getAdminRole() {
    $user = getUser();
    if (!$user || !$user['is_admin']) return null;

    return [
        'role_name' => $user['admin_role_name'],
        'display_name' => $user['admin_role_display'],
        'permissions' => json_decode($user['admin_permissions'], true)
    ];
}

function logAdminAction($action, $target_type = null, $target_id = null, $details = null) {
    if (!isAdmin()) return false;

    global $pdo;
    $user = getUser();

    try {
        $stmt = $pdo->prepare("
            INSERT INTO admin_audit_log (admin_user_id, action, target_type, target_id, details, ip_address, user_agent)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $user['id'],
            $action,
            $target_type,
            $target_id,
            $details ? json_encode($details) : null,
            $_SERVER['REMOTE_ADDR'] ?? null,
            $_SERVER['HTTP_USER_AGENT'] ?? null
        ]);

        return true;
    } catch (PDOException $e) {
        error_log("Failed to log admin action: " . $e->getMessage());
        return false;
    }
}

function logout() {
    session_destroy();
    header('Location: login.php');
    exit;
}
?>
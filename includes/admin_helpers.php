<?php
// Admin Helper Functions for Role-Based Access Control

function requireAdminPermission($permission) {
    if (!hasAdminPermission($permission)) {
        header('HTTP/1.0 403 Forbidden');
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

function requireSuperAdmin() {
    if (!isSuperAdmin()) {
        header('HTTP/1.0 403 Forbidden');
        header('Location: ../../index.php?error=access_denied');
        exit;
    }
}

function canAccessModule($module) {
    $modulePermissions = [
        'employees' => ['employees.view'],
        'attendance' => ['attendance.view'],
        'petty_cash' => ['petty_cash.view'],
        'salary' => ['salary.view'],
        'tasks' => ['tasks.view'],
        'leave' => ['leave.view'],
        'reports' => ['reports.view'],
        'settings' => ['settings.view']
    ];

    if (!isset($modulePermissions[$module])) {
        return false;
    }

    foreach ($modulePermissions[$module] as $permission) {
        if (hasAdminPermission($permission)) {
            return true;
        }
    }

    return false;
}

function canPerformAction($action, $module = null) {
    $actionMap = [
        'create' => [
            'employees' => 'employees.create',
            'default' => 'create'
        ],
        'edit' => [
            'employees' => 'employees.edit',
            'default' => 'edit'
        ],
        'delete' => [
            'employees' => 'employees.delete',
            'default' => 'delete'
        ],
        'approve' => [
            'petty_cash' => 'petty_cash.approve',
            'leave' => 'leave.approve',
            'default' => 'approve'
        ],
        'manage' => [
            'attendance' => 'attendance.manage',
            'salary' => 'salary.manage',
            'tasks' => 'tasks.manage',
            'default' => 'manage'
        ]
    ];

    if (!isset($actionMap[$action])) {
        return false;
    }

    $permission = $actionMap[$action][$module] ?? $actionMap[$action]['default'];

    return hasAdminPermission($permission);
}

function filterMenuItems($menuItems) {
    $filteredItems = [];

    foreach ($menuItems as $item) {
        $canAccess = true;

        if (isset($item['permission'])) {
            $canAccess = hasAdminPermission($item['permission']);
        } elseif (isset($item['module'])) {
            $canAccess = canAccessModule($item['module']);
        } elseif (isset($item['superadmin_only']) && $item['superadmin_only']) {
            $canAccess = isSuperAdmin();
        }

        if ($canAccess) {
            $filteredItems[] = $item;
        }
    }

    return $filteredItems;
}

function getAdminStats() {
    global $pdo;

    $stats = [];

    try {
        // Total admins
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 1");
        $stmt->execute();
        $stats['total_admins'] = $stmt->fetch()['count'];

        // Active admins
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM users WHERE is_admin = 1 AND status = 'active'");
        $stmt->execute();
        $stats['active_admins'] = $stmt->fetch()['count'];

        // Total roles
        $stmt = $pdo->prepare("SELECT COUNT(*) as count FROM admin_roles WHERE is_active = 1");
        $stmt->execute();
        $stats['total_roles'] = $stmt->fetch()['count'];

        // Recent admin actions (last 24 hours)
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as count
            FROM admin_audit_log
            WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
        $stats['recent_actions'] = $stmt->fetch()['count'];

    } catch (PDOException $e) {
        error_log("Failed to get admin stats: " . $e->getMessage());
        $stats = [
            'total_admins' => 0,
            'active_admins' => 0,
            'total_roles' => 0,
            'recent_actions' => 0
        ];
    }

    return $stats;
}

function getRecentAdminActions($limit = 10) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("
            SELECT aal.*, u.username
            FROM admin_audit_log aal
            JOIN users u ON aal.admin_user_id = u.id
            ORDER BY aal.created_at DESC
            LIMIT ?
        ");
        $stmt->execute([$limit]);
        return $stmt->fetchAll();
    } catch (PDOException $e) {
        error_log("Failed to get recent admin actions: " . $e->getMessage());
        return [];
    }
}

function validateAdminRole($role_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT id FROM admin_roles WHERE id = ? AND is_active = 1");
        $stmt->execute([$role_id]);
        return $stmt->fetch() !== false;
    } catch (PDOException $e) {
        return false;
    }
}

function getAdminRolePermissions($role_id) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT permissions FROM admin_roles WHERE id = ? AND is_active = 1");
        $stmt->execute([$role_id]);
        $result = $stmt->fetch();

        if ($result) {
            return json_decode($result['permissions'], true) ?: [];
        }
    } catch (PDOException $e) {
        error_log("Failed to get admin role permissions: " . $e->getMessage());
    }

    return [];
}

function renderPermissionBadges($permissions) {
    $availablePermissions = [
        'employees.view' => 'View Employees',
        'employees.create' => 'Create Employees',
        'employees.edit' => 'Edit Employees',
        'employees.delete' => 'Delete Employees',
        'attendance.view' => 'View Attendance',
        'attendance.manage' => 'Manage Attendance',
        'petty_cash.view' => 'View Petty Cash',
        'petty_cash.approve' => 'Approve Petty Cash',
        'salary.view' => 'View Salary',
        'salary.manage' => 'Manage Salary',
        'tasks.view' => 'View Tasks',
        'tasks.manage' => 'Manage Tasks',
        'leave.view' => 'View Leave Requests',
        'leave.approve' => 'Approve Leave Requests',
        'reports.view' => 'View Reports',
        'settings.view' => 'View Settings'
    ];

    $html = '';

    if (is_array($permissions)) {
        foreach ($permissions as $permission) {
            $label = $availablePermissions[$permission] ?? $permission;
            $html .= '<span class="permission-badge">';
            $html .= '<i class="fas fa-check"></i> ';
            $html .= htmlspecialchars($label);
            $html .= '</span>';
        }
    }

    return $html;
}
?>
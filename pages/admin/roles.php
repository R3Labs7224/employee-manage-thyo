<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user is superadmin
if (!isSuperAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Admin Roles Management';
$user = getUser();

// Define available permissions
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

$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_role') {
        $role_name = sanitize($_POST['role_name']);
        $display_name = sanitize($_POST['display_name']);
        $description = sanitize($_POST['description']);
        $permissions = $_POST['permissions'] ?? [];

        if (!empty($role_name) && !empty($display_name)) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO admin_roles (role_name, display_name, description, permissions)
                    VALUES (?, ?, ?, ?)
                ");

                if ($stmt->execute([$role_name, $display_name, $description, json_encode($permissions)])) {
                    logAdminAction('create_admin_role', 'admin_role', $pdo->lastInsertId(), [
                        'role_name' => $role_name,
                        'permissions' => $permissions
                    ]);
                    $message = 'Admin role created successfully';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill all required fields';
            $messageType = 'error';
        }
    }

    if ($action === 'update_role') {
        $role_id = (int)$_POST['role_id'];
        $display_name = sanitize($_POST['display_name']);
        $description = sanitize($_POST['description']);
        $permissions = $_POST['permissions'] ?? [];

        try {
            $stmt = $pdo->prepare("
                UPDATE admin_roles
                SET display_name = ?, description = ?, permissions = ?, updated_at = CURRENT_TIMESTAMP
                WHERE id = ? AND role_name != 'superadmin'
            ");

            if ($stmt->execute([$display_name, $description, json_encode($permissions), $role_id])) {
                logAdminAction('update_admin_role', 'admin_role', $role_id, [
                    'permissions' => $permissions
                ]);
                $message = 'Admin role updated successfully';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Failed to update admin role';
            $messageType = 'error';
        }
    }

    if ($action === 'toggle_role_status') {
        $role_id = (int)$_POST['role_id'];
        $new_status = $_POST['new_status'] === '1' ? 1 : 0;

        try {
            $stmt = $pdo->prepare("UPDATE admin_roles SET is_active = ? WHERE id = ? AND role_name != 'superadmin'");
            if ($stmt->execute([$new_status, $role_id])) {
                logAdminAction('toggle_admin_role_status', 'admin_role', $role_id, [
                    'new_status' => $new_status
                ]);
                $message = 'Role status updated successfully';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Failed to update role status';
            $messageType = 'error';
        }
    }
}

// Get all admin roles
$stmt = $pdo->prepare("SELECT * FROM admin_roles ORDER BY id");
$stmt->execute();
$adminRoles = $stmt->fetchAll();

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?> - Employee Management System</title>
    <link rel="icon" type="image/png" sizes="32x32" href="../../assets/images/logo.png">
    <link rel="stylesheet" href="../../assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>
    <div class="layout">
        <?php include '../../components/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../../components/header.php'; ?>

            <div class="content-wrapper">
                <div class="page-header">
                    <div class="page-title">
                        
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Admins
                        </a>
                        <button class="btn btn-primary" onclick="openCreateRoleModal()">
                            <i class="fas fa-plus"></i> Create New Role
                        </button>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Admin Roles Table -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-tag"></i> Administrator Roles</h3>
                        <div class="header-actions">
                            <span class="badge badge-info"><?php echo count($adminRoles); ?> Roles</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Role Name</th>
                                    <th>Description</th>
                                    <th>Permissions Count</th>
                                    <th>Status</th>
                                    <th>Created</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminRoles as $role): ?>
                                <tr>
                                    <td>
                                        <div class="role-info">
                                            <i class="fas fa-user-tag"></i>
                                            <div class="role-details">
                                                <strong><?php echo htmlspecialchars($role['display_name']); ?></strong>
                                                <small class="text-muted"><?php echo htmlspecialchars($role['role_name']); ?></small>
                                                <?php if ($role['role_name'] === 'superadmin'): ?>
                                                    <span class="badge badge-success">Protected</span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="description-text"><?php echo htmlspecialchars($role['description']); ?></span>
                                    </td>
                                    <td>
                                        <?php
                                        $permissions = json_decode($role['permissions'], true);
                                        $permCount = $permissions ? count($permissions) : 0;
                                        ?>
                                        <span class="badge badge-primary"><?php echo $permCount; ?> permissions</span>
                                        <button class="btn btn-sm btn-outline" onclick="viewPermissions(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                            <i class="fas fa-eye"></i> View
                                        </button>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $role['is_active'] ? 'success' : 'warning'; ?>">
                                            <?php echo $role['is_active'] ? 'Active' : 'Inactive'; ?>
                                        </span>
                                    </td>
                                    <td><?php echo date('M j, Y', strtotime($role['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($role['role_name'] !== 'superadmin'): ?>
                                                <button class="btn btn-sm btn-primary" onclick="editRole(<?php echo htmlspecialchars(json_encode($role)); ?>)">
                                                    <i class="fas fa-edit"></i>
                                                </button>

                                                <form method="POST" style="display: inline;">
                                                    <input type="hidden" name="action" value="toggle_role_status">
                                                    <input type="hidden" name="role_id" value="<?php echo $role['id']; ?>">
                                                    <input type="hidden" name="new_status" value="<?php echo $role['is_active'] ? '0' : '1'; ?>">
                                                    <button type="submit" class="btn btn-sm btn-warning"
                                                            onclick="return confirm('Are you sure you want to <?php echo $role['is_active'] ? 'deactivate' : 'activate'; ?> this role?')">
                                                        <i class="fas fa-<?php echo $role['is_active'] ? 'ban' : 'check'; ?>"></i>
                                                    </button>
                                                </form>
                                            <?php else: ?>
                                                <span class="text-muted">Protected</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create/Edit Role Modal -->
    <div id="roleModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3 id="modalTitle"><i class="fas fa-user-tag"></i> Create New Role</h3>
                <button class="modal-close" onclick="closeRoleModal()">&times;</button>
            </div>
            <form id="roleForm" method="POST">
                <input type="hidden" name="action" value="create_role">
                <input type="hidden" id="role_id" name="role_id" value="">

                <div class="modal-body">
                    <div class="form-row">
                        <div class="form-group">
                            <label for="role_name">Role Name (System ID)</label>
                            <input type="text" id="role_name" name="role_name" class="form-control" required>
                            <small class="text-muted">Lowercase, no spaces (e.g., admin_custom)</small>
                        </div>

                        <div class="form-group">
                            <label for="display_name">Display Name</label>
                            <input type="text" id="display_name" name="display_name" class="form-control" required>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="3"></textarea>
                    </div>

                    <div class="form-group">
                        <label>Permissions</label>
                        <div class="permissions-checkboxes">
                            <?php foreach ($availablePermissions as $perm => $label): ?>
                            <label class="checkbox-label">
                                <input type="checkbox" name="permissions[]" value="<?php echo $perm; ?>">
                                <span class="checkmark"></span>
                                <?php echo $label; ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeRoleModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary" id="submitBtn">Create Role</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Permissions Modal -->
    <div id="viewPermissionsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3 id="viewModalTitle"><i class="fas fa-shield-alt"></i> Role Permissions</h3>
                <button class="modal-close" onclick="closeViewPermissionsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="permissionsDisplay"></div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewPermissionsModal()">Close</button>
            </div>
        </div>
    </div>

    <style>
        .role-info {
            display: flex;
            align-items: center;
            gap: 0.75rem;
        }

        .role-info i {
            color: var(--orange-500);
            font-size: 1.2rem;
        }

        .role-details {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .role-details strong {
            font-weight: 600;
            color: var(--gray-900);
        }

        .role-details small {
            font-size: 0.8rem;
            color: var(--gray-500);
            font-family: monospace;
        }

        .description-text {
            max-width: 300px;
            display: block;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }

        .permissions-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.5rem;
            margin-top: 1rem;
        }

        .permission-badge {
            background: var(--success);
            color: white;
            padding: 0.5rem 0.75rem;
            border-radius: var(--radius);
            font-size: 0.85rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .permission-badge i {
            color: rgba(255,255,255,0.8);
        }

        .permissions-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 0.75rem;
            max-height: 350px;
            overflow-y: auto;
            padding: 1.5rem;
            border: 2px solid var(--border-color);
            border-radius: var(--radius-lg);
            background: var(--gray-50);
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.75rem;
            background: white;
            border-radius: var(--radius);
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .checkbox-label:hover {
            background: var(--orange-50);
            border-color: var(--orange-200);
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: var(--orange-500);
        }

        .modal-content.large {
            max-width: 900px;
            max-height: 90vh;
            overflow-y: auto;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .action-buttons {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .btn-outline {
            background: transparent;
            border: 1px solid var(--gray-300);
            color: var(--gray-600);
        }

        .btn-outline:hover {
            background: var(--gray-100);
            border-color: var(--gray-400);
        }

        .table td {
            vertical-align: middle;
            padding: 1rem 0.75rem;
        }

        .table th {
            font-weight: 600;
            color: var(--gray-700);
            background: var(--gray-50);
        }

        .badge {
            font-size: 0.8rem;
            padding: 0.35rem 0.7rem;
            font-weight: 500;
        }

        #permissionsDisplay {
            background: var(--gray-50);
            padding: 1.5rem;
            border-radius: var(--radius-lg);
            border: 1px solid var(--border-color);
        }

        #permissionsDisplay h4 {
            margin-bottom: 1rem;
            color: var(--gray-800);
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .permissions-checkboxes {
                grid-template-columns: 1fr;
            }

            .role-info {
                flex-direction: column;
                align-items: flex-start;
                gap: 0.5rem;
            }

            .description-text {
                max-width: none;
                white-space: normal;
            }
        }

        /* Modal Base Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(4px);
            /* Center modal content when shown */
            align-items: center;
            justify-content: center;
        }

        .modal.show {
            display: flex !important;
        }

        .modal-content {
            background-color: white;
            padding: 0;
            border-radius: 12px;
            width: 90%;
            max-width: 800px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalSlideIn 0.3s ease;
            max-height: 90vh;
            overflow-y: auto;
            margin: 0;
            position: relative;
        }

        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .modal-header {
            padding: 1.5rem;
            border-bottom: 1px solid #e2e8f0;
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: linear-gradient(135deg, var(--orange-50), var(--orange-100));
            border-radius: 12px 12px 0 0;
        }

        .modal-header h3 {
            margin: 0;
            color: var(--gray-900);
            font-size: 1.25rem;
            font-weight: 600;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .modal-header h3 i {
            color: var(--orange-600);
        }

        .modal-close {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: var(--gray-500);
            padding: 0.25rem;
            border-radius: 4px;
            transition: all 0.2s ease;
        }

        .modal-close:hover {
            background: rgba(0, 0, 0, 0.05);
            color: var(--gray-700);
        }

        .modal-body {
            padding: 1.5rem;
        }

        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
            display: flex;
            justify-content: flex-end;
            gap: 0.75rem;
            background: var(--gray-50);
            border-radius: 0 0 12px 12px;
        }

        .form-group {
            margin-bottom: 1.25rem;
        }

        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 2px solid #e2e8f0;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: var(--orange-400);
            box-shadow: 0 0 0 3px rgba(234, 88, 12, 0.1);
        }

        .btn {
            padding: 0.625rem 1.25rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.2s ease;
            border: none;
            display: inline-flex;
            align-items: center;
            gap: 0.5rem;
        }

        .btn-primary {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-600));
            color: white;
            box-shadow: 0 2px 4px rgba(234, 88, 12, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, var(--orange-600), var(--orange-700));
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(234, 88, 12, 0.3);
        }

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }
    </style>

    <script src="../../assets/js/script.js"></script>
    <script>
        const availablePermissions = <?php echo json_encode($availablePermissions); ?>;

        function openCreateRoleModal() {
            console.log('Opening create role modal...');
            const modal = document.getElementById('roleModal');
            if (modal) {
                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-user-tag"></i> Create New Role';
                document.getElementById('roleForm').action.value = 'create_role';
                document.getElementById('role_id').value = '';
                document.getElementById('role_name').disabled = false;
                document.getElementById('submitBtn').textContent = 'Create Role';
                clearForm();
                modal.classList.add('show');
                console.log('Create role modal opened');
            } else {
                console.error('Role modal not found!');
            }
        }

        function editRole(role) {
            console.log('Editing role:', role);
            try {
                const modal = document.getElementById('roleModal');
                if (!modal) {
                    console.error('Role modal not found!');
                    return;
                }

                document.getElementById('modalTitle').innerHTML = '<i class="fas fa-edit"></i> Edit Role';
                document.getElementById('roleForm').action.value = 'update_role';
                document.getElementById('role_id').value = role.id;
                document.getElementById('role_name').value = role.role_name;
                document.getElementById('role_name').disabled = true;
                document.getElementById('display_name').value = role.display_name;
                document.getElementById('description').value = role.description;
                document.getElementById('submitBtn').textContent = 'Update Role';

                // Check permissions
                const permissions = JSON.parse(role.permissions);
                console.log('Role permissions:', permissions);
                const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
                checkboxes.forEach(cb => {
                    cb.checked = permissions.includes(cb.value);
                });

                modal.classList.add('show');
                console.log('Edit role modal opened');
            } catch (error) {
                console.error('Error in editRole function:', error);
            }
        }

        function viewPermissions(role) {
            console.log('Viewing permissions for role:', role);
            try {
                const viewModal = document.getElementById('viewPermissionsModal');
                if (!viewModal) {
                    console.error('View permissions modal not found!');
                    return;
                }

                document.getElementById('viewModalTitle').innerHTML =
                    `<i class="fas fa-shield-alt"></i> ${role.display_name} - Permissions`;

                const permissions = JSON.parse(role.permissions);
                console.log('Parsed permissions:', permissions);
                let html = '';

                if (permissions && permissions.length > 0) {
                    html = '<h4><i class="fas fa-check-circle"></i> Assigned Permissions:</h4>';
                    html += '<div class="permissions-grid">';

                    permissions.forEach(permission => {
                        const label = availablePermissions[permission] || permission;
                        html += `
                            <div class="permission-badge">
                                <i class="fas fa-check"></i>
                                ${label}
                            </div>
                        `;
                    });

                    html += '</div>';

                    // Add role details
                    html += `
                        <div style="margin-top: 2rem; padding-top: 1.5rem; border-top: 1px solid var(--border-color);">
                            <h4><i class="fas fa-info-circle"></i> Role Details:</h4>
                            <p><strong>System Name:</strong> <code>${role.role_name}</code></p>
                            <p><strong>Description:</strong> ${role.description}</p>
                            <p><strong>Status:</strong>
                                <span class="badge badge-${role.is_active ? 'success' : 'warning'}">
                                    ${role.is_active ? 'Active' : 'Inactive'}
                                </span>
                            </p>
                            <p><strong>Total Permissions:</strong> ${permissions.length}</p>
                        </div>
                    `;
                } else {
                    html = `
                        <div style="text-align: center; padding: 2rem;">
                            <i class="fas fa-exclamation-triangle" style="font-size: 3rem; color: var(--warning); margin-bottom: 1rem;"></i>
                            <h4>No Permissions Assigned</h4>
                            <p class="text-muted">This role has no permissions assigned to it.</p>
                        </div>
                    `;
                }

                document.getElementById('permissionsDisplay').innerHTML = html;
                viewModal.classList.add('show');
                console.log('View permissions modal opened');
            } catch (error) {
                console.error('Error in viewPermissions function:', error);
            }
        }

        function closeRoleModal() {
            const modal = document.getElementById('roleModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }

        function closeViewPermissionsModal() {
            const modal = document.getElementById('viewPermissionsModal');
            if (modal) {
                modal.classList.remove('show');
            }
        }

        function clearForm() {
            document.getElementById('role_name').value = '';
            document.getElementById('display_name').value = '';
            document.getElementById('description').value = '';

            const checkboxes = document.querySelectorAll('input[name="permissions[]"]');
            checkboxes.forEach(cb => cb.checked = false);
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const roleModal = document.getElementById('roleModal');
            const viewModal = document.getElementById('viewPermissionsModal');

            if (event.target === roleModal) {
                closeRoleModal();
            }

            if (event.target === viewModal) {
                closeViewPermissionsModal();
            }
        }

        // Add keyboard support for modals
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeRoleModal();
                closeViewPermissionsModal();
            }
        });

        // Initialize page when DOM is loaded
        document.addEventListener('DOMContentLoaded', function() {
            console.log('DOM loaded, initializing roles page...');
            console.log('Available permissions:', availablePermissions);

            // Check if modals exist
            const roleModal = document.getElementById('roleModal');
            const viewModal = document.getElementById('viewPermissionsModal');

            console.log('Role modal found:', !!roleModal);
            console.log('View permissions modal found:', !!viewModal);

            // Add click event listeners to all buttons for debugging
            const viewButtons = document.querySelectorAll('button[onclick^="viewPermissions"]');
            const editButtons = document.querySelectorAll('button[onclick^="editRole"]');

            console.log('View buttons found:', viewButtons.length);
            console.log('Edit buttons found:', editButtons.length);

            // Test if buttons are clickable
            viewButtons.forEach((btn, index) => {
                btn.addEventListener('click', function(e) {
                    console.log(`View button ${index} clicked`);
                });
            });

            editButtons.forEach((btn, index) => {
                btn.addEventListener('click', function(e) {
                    console.log(`Edit button ${index} clicked`);
                });
            });
        });

        // Debug function to test modals manually
        function testViewModal() {
            const testRole = {
                id: 1,
                role_name: 'test',
                display_name: 'Test Role',
                description: 'Test description',
                permissions: '["employees.view", "employees.create"]',
                is_active: true
            };
            viewPermissions(testRole);
        }

        function testEditModal() {
            const testRole = {
                id: 1,
                role_name: 'test',
                display_name: 'Test Role',
                description: 'Test description',
                permissions: '["employees.view", "employees.create"]',
                is_active: true
            };
            editRole(testRole);
        }
    </script>
</body>
</html>
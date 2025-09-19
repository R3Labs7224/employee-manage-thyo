<?php
// Enable error reporting for debugging
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user is superadmin
if (!isSuperAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Admin Management';
$user = getUser();

// Handle admin actions
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Note: Admin creation is now handled on the separate create.php page

    if ($action === 'toggle_status') {
        $admin_id = (int)$_POST['admin_id'];
        $new_status = $_POST['new_status'];

        try {
            $stmt = $pdo->prepare("UPDATE users SET status = ? WHERE id = ? AND is_admin = 1");
            if ($stmt->execute([$new_status, $admin_id])) {
                logAdminAction('toggle_admin_status', 'user', $admin_id, [
                    'new_status' => $new_status
                ]);
                $message = 'Admin status updated successfully';
                $messageType = 'success';
            }
        } catch (PDOException $e) {
            $message = 'Failed to update admin status';
            $messageType = 'error';
        }
    }

    if ($action === 'delete_admin') {
        $admin_id = (int)$_POST['admin_id'];

        // Prevent deleting self
        if ($admin_id === $user['id']) {
            $message = 'Cannot delete your own account';
            $messageType = 'error';
        } else {
            try {
                $stmt = $pdo->prepare("DELETE FROM users WHERE id = ? AND is_admin = 1 AND role != 'superadmin'");
                if ($stmt->execute([$admin_id])) {
                    logAdminAction('delete_admin', 'user', $admin_id);
                    $message = 'Admin user deleted successfully';
                    $messageType = 'success';
                }
            } catch (PDOException $e) {
                $message = 'Failed to delete admin user';
                $messageType = 'error';
            }
        }
    }
}

// Get all admin roles
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY id");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll();

    // Debug output
    error_log("Admin roles found: " . count($adminRoles));

} catch (PDOException $e) {
    error_log("Error fetching admin roles: " . $e->getMessage());
    $adminRoles = [];
}

// Get all admin users
try {
    $stmt = $pdo->prepare("
        SELECT u.*, ar.display_name as role_display, cr.username as created_by_username
        FROM users u
        LEFT JOIN admin_roles ar ON u.admin_role_id = ar.id
        LEFT JOIN users cr ON u.created_by = cr.id
        WHERE u.is_admin = 1
        ORDER BY u.created_at DESC
    ");
    $stmt->execute();
    $adminUsers = $stmt->fetchAll();

    // Debug output
    error_log("Admin users found: " . count($adminUsers));

} catch (PDOException $e) {
    error_log("Error fetching admin users: " . $e->getMessage());
    $adminUsers = [];
}

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
                <!-- Enhanced Page Header -->
                <div class="enhanced-page-header">
                    <div class="header-main">
                        <div class="header-icon">
                            <i class="fas fa-user-shield"></i>
                        </div>
                        <div class="header-content">
                            <h1>Admin Management</h1>
                           
                            
                        </div>
                    </div>

                    <div class="header-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-users-cog"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count($adminUsers); ?></span>
                                <span class="stat-label">Total Admins</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-check"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count(array_filter($adminUsers, function($admin) { return $admin['status'] === 'active'; })); ?></span>
                                <span class="stat-label">Active Admins</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-user-tag"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count($adminRoles); ?></span>
                                <span class="stat-label">Admin Roles</span>
                            </div>
                        </div>
                    </div>

                    <div class="header-actions">
                        <a href="roles.php" class="btn btn-secondary">
                            <i class="fas fa-user-tag"></i>
                            <span>Manage Roles</span>
                        </a>
                        <a href="create.php" class="btn btn-primary">
                            <i class="fas fa-plus"></i>
                            <span>Create New Admin</span>
                        </a>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Admin Users Table -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-users-cog"></i> Administrator Accounts</h3>
                        <div class="header-actions">
                            <span class="badge badge-info"><?php echo count($adminUsers); ?> Admins</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Username</th>
                                    <th>Role</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created At</th>
                                    <th>Last Login</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($adminUsers as $admin): ?>
                                <tr>
                                    <td>
                                        <div class="user-info">
                                            <i class="fas fa-user-shield"></i>
                                            <span><?php echo htmlspecialchars($admin['username']); ?></span>
                                            <?php if ($admin['role'] === 'superadmin'): ?>
                                                <span class="badge badge-success">Super Admin</span>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary">
                                            <?php echo htmlspecialchars($admin['role_display'] ?? 'Admin'); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php echo $admin['status'] === 'active' ? 'success' : 'warning'; ?>">
                                            <?php echo ucfirst($admin['status']); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($admin['created_by_username'] ?? 'System'); ?></td>
                                    <td><?php echo date('M j, Y', strtotime($admin['created_at'])); ?></td>
                                    <td>
                                        <?php if ($admin['last_login']): ?>
                                            <?php echo date('M j, Y H:i', strtotime($admin['last_login'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">Never</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-buttons">
                                            <?php if ($admin['id'] !== $user['id']): ?>
                                                <?php if ($admin['role'] !== 'superadmin'): ?>
                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="toggle_status">
                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <input type="hidden" name="new_status" value="<?php echo $admin['status'] === 'active' ? 'inactive' : 'active'; ?>">
                                                        <button type="submit" class="btn btn-sm btn-warning"
                                                                onclick="return confirm('Are you sure you want to <?php echo $admin['status'] === 'active' ? 'deactivate' : 'activate'; ?> this admin?')">
                                                            <i class="fas fa-<?php echo $admin['status'] === 'active' ? 'ban' : 'check'; ?>"></i>
                                                        </button>
                                                    </form>

                                                    <form method="POST" style="display: inline;">
                                                        <input type="hidden" name="action" value="delete_admin">
                                                        <input type="hidden" name="admin_id" value="<?php echo $admin['id']; ?>">
                                                        <button type="submit" class="btn btn-sm btn-danger"
                                                                onclick="return confirm('Are you sure you want to delete this admin? This action cannot be undone.')">
                                                            <i class="fas fa-trash"></i>
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <span class="text-muted">Protected</span>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                <span class="badge badge-info">You</span>
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


    <style>
        .enhanced-page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.25rem 1.5rem;
            margin-bottom: 1.5rem;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05);
            display: grid;
            grid-template-columns: 1fr auto auto;
            gap: 1.5rem;
            align-items: center;
        }

        .header-main {
            display: flex;
            align-items: center;
            gap: 1rem;
        }

        .header-icon {
            width: 48px;
            height: 48px;
            background: linear-gradient(135deg, var(--orange-500), var(--orange-600));
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(234, 88, 12, 0.2);
        }

        .header-icon i {
            font-size: 20px;
            color: white;
        }

        .header-content h1 {
            margin: 0 0 0.25rem 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: var(--gray-900);
        }

        .header-description {
            margin: 0 0 0.25rem 0;
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.4;
        }

        .header-breadcrumb {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .header-breadcrumb .active {
            color: var(--orange-600);
            font-weight: 600;
        }

        .header-breadcrumb i {
            font-size: 0.75rem;
        }

        .header-stats {
            display: flex;
            gap: 0.75rem;
        }

        .stat-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 0.875rem;
            display: flex;
            align-items: center;
            gap: 0.75rem;
            min-width: 110px;
            box-shadow: 0 1px 2px rgba(0, 0, 0, 0.05);
            transition: all 0.2s ease;
        }

        .stat-card:hover {
            transform: translateY(-1px);
            box-shadow: 0 2px 8px rgba(0, 0, 0, 0.1);
        }

        .stat-icon {
            width: 36px;
            height: 36px;
            border-radius: 8px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 16px;
        }

        .stat-card:nth-child(1) .stat-icon {
            background: linear-gradient(135deg, #3b82f6, #1d4ed8);
            color: white;
        }

        .stat-card:nth-child(2) .stat-icon {
            background: linear-gradient(135deg, #10b981, #059669);
            color: white;
        }

        .stat-card:nth-child(3) .stat-icon {
            background: linear-gradient(135deg, #8b5cf6, #7c3aed);
            color: white;
        }

        .stat-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .stat-number {
            font-size: 1.375rem;
            font-weight: 700;
            color: var(--gray-900);
            line-height: 1;
        }

        .stat-label {
            font-size: 0.7rem;
            color: var(--gray-500);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }

        .header-actions {
            display: flex;
            gap: 0.5rem;
            align-items: center;
        }

        .header-actions .btn {
            display: flex;
            align-items: center;
            gap: 0.375rem;
            padding: 0.625rem 1rem;
            border-radius: 8px;
            font-weight: 600;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            text-decoration: none;
            border: none;
            cursor: pointer;
        }

        .header-actions .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .header-actions .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header-actions .btn-primary {
            background: linear-gradient(135deg, var(--orange-500), var(--orange-600));
            color: white;
            box-shadow: 0 2px 6px rgba(234, 88, 12, 0.2);
        }

        .header-actions .btn-primary:hover {
            background: linear-gradient(135deg, var(--orange-600), var(--orange-700));
            transform: translateY(-1px);
            box-shadow: 0 3px 8px rgba(234, 88, 12, 0.3);
        }

        .header-actions .btn i {
            font-size: 0.875rem;
        }

        /* Responsive Design */
        @media (max-width: 1024px) {
            .enhanced-page-header {
                grid-template-columns: 1fr;
                gap: 1.5rem;
                text-align: center;
            }

            .header-main {
                justify-content: center;
            }

            .header-stats {
                justify-content: center;
                flex-wrap: wrap;
            }

            .header-actions {
                justify-content: center;
            }
        }

        @media (max-width: 640px) {
            .enhanced-page-header {
                padding: 1rem;
            }

            .header-main {
                flex-direction: column;
                gap: 0.75rem;
            }

            .header-content h1 {
                font-size: 1.25rem;
            }

            .stat-card {
                min-width: 100px;
                padding: 0.75rem;
            }

            .stat-number {
                font-size: 1.25rem;
            }

            .header-actions {
                flex-direction: column;
                width: 100%;
            }

            .header-actions .btn {
                width: 100%;
                justify-content: center;
            }
        }

        /* Modal Styles */
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
            /* Center modal content */
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
            max-width: 500px;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            animation: modalSlideIn 0.3s ease;
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
        // Page animations
        document.addEventListener('DOMContentLoaded', function() {
            // Add animation on page load
            const header = document.querySelector('.enhanced-page-header');
            if (header) {
                header.style.opacity = '0';
                header.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    header.style.transition = 'all 0.6s ease';
                    header.style.opacity = '1';
                    header.style.transform = 'translateY(0)';
                }, 100);
            }

            // Animate stat cards
            const statCards = document.querySelectorAll('.stat-card');
            statCards.forEach((card, index) => {
                card.style.opacity = '0';
                card.style.transform = 'translateY(20px)';

                setTimeout(() => {
                    card.style.transition = 'all 0.4s ease';
                    card.style.opacity = '1';
                    card.style.transform = 'translateY(0)';
                }, 200 + (index * 100));
            });
        });
    </script>
</body>
</html>
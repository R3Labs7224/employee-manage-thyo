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

$pageTitle = 'Edit Admin';
$user = getUser();

// Get admin ID from URL
$admin_id = isset($_GET['id']) ? (int)$_GET['id'] : 0;

if ($admin_id === 0) {
    header('Location: index.php');
    exit;
}

// Prevent editing self
if ($admin_id === $user['id']) {
    header('Location: index.php?error=cannot_edit_self');
    exit;
}

$message = '';
$messageType = '';

// Get admin details
try {
    $stmt = $pdo->prepare("
        SELECT u.*, ar.display_name as role_display
        FROM users u
        LEFT JOIN admin_roles ar ON u.admin_role_id = ar.id
        WHERE u.id = ? AND u.is_admin = 1
    ");
    $stmt->execute([$admin_id]);
    $admin = $stmt->fetch();

    if (!$admin) {
        header('Location: index.php?error=admin_not_found');
        exit;
    }

    // Prevent editing superadmin
    if ($admin['role'] === 'superadmin') {
        header('Location: index.php?error=cannot_edit_superadmin');
        exit;
    }

} catch (PDOException $e) {
    header('Location: index.php?error=database_error');
    exit;
}

// Get all admin roles for dropdown
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY id");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll();
} catch (PDOException $e) {
    $adminRoles = [];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $new_password = trim($_POST['password']);
    $admin_role_id = !empty($_POST['admin_role_id']) ? (int)$_POST['admin_role_id'] : null;

    // Validation
    $errors = [];

    if (empty($username)) {
        $errors[] = 'Username is required';
    } elseif (strlen($username) < 3) {
        $errors[] = 'Username must be at least 3 characters long';
    }

    // Check if username already exists (excluding current admin)
    try {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? AND id != ?");
        $stmt->execute([$username, $admin_id]);
        if ($stmt->fetch()) {
            $errors[] = 'Username already exists';
        }
    } catch (PDOException $e) {
        $errors[] = 'Database error occurred';
    }

    // Password validation (only if provided)
    if (!empty($new_password) && strlen($new_password) < 6) {
        $errors[] = 'Password must be at least 6 characters long';
    }

    if (empty($errors)) {
        try {
            // Prepare update query
            $updateFields = ['username = ?', 'admin_role_id = ?'];
            $updateValues = [$username, $admin_role_id];

            // Add password to update if provided
            if (!empty($new_password)) {
                $updateFields[] = 'password = ?';
                $updateValues[] = password_hash($new_password, PASSWORD_DEFAULT);
            }

            $updateValues[] = $admin_id; // Add admin_id for WHERE clause

            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $pdo->prepare($sql);

            if ($stmt->execute($updateValues)) {
                logAdminAction('edit_admin', 'user', $admin_id, [
                    'username' => $username,
                    'admin_role_id' => $admin_role_id,
                    'password_changed' => !empty($new_password)
                ]);

                header('Location: index.php?success=admin_updated');
                exit;
            } else {
                $message = 'Failed to update admin';
                $messageType = 'error';
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = implode('<br>', $errors);
        $messageType = 'error';
    }
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
                            <i class="fas fa-user-edit"></i>
                        </div>
                        <div class="header-content">
                            <h1>Edit Admin</h1>
                            <div class="header-description">
                                Update admin account details and permissions
                            </div>
                            <div class="header-breadcrumb">
                                <a href="../../index.php">Dashboard</a>
                                <i class="fas fa-chevron-right"></i>
                                <a href="index.php">Admin Management</a>
                                <i class="fas fa-chevron-right"></i>
                                <span class="active">Edit Admin</span>
                            </div>
                        </div>
                    </div>

                    <div class="header-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i>
                            <span>Back to List</span>
                        </a>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <?php echo $message; ?>
                </div>
                <?php endif; ?>

                <!-- Edit Admin Form -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-edit"></i> Edit Admin Account</h3>
                        <div class="header-actions">
                            <span class="badge badge-info">Admin ID: <?php echo $admin['id']; ?></span>
                        </div>
                    </div>

                    <form method="POST" class="admin-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">
                                    <i class="fas fa-user"></i>
                                    Username *
                                </label>
                                <input type="text"
                                       id="username"
                                       name="username"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($admin['username']); ?>"
                                       required>
                                <small class="form-help">Username must be at least 3 characters long</small>
                            </div>

                            <div class="form-group">
                                <label for="password">
                                    <i class="fas fa-lock"></i>
                                    New Password
                                </label>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       placeholder="Leave blank to keep current password">
                                <small class="form-help">Password must be at least 6 characters long (optional)</small>
                            </div>

                            <div class="form-group">
                                <label for="admin_role_id">
                                    <i class="fas fa-user-tag"></i>
                                    Admin Role
                                </label>
                                <select id="admin_role_id" name="admin_role_id" class="form-control">
                                    <option value="">Select Role (Optional)</option>
                                    <?php foreach ($adminRoles as $role): ?>
                                        <option value="<?php echo $role['id']; ?>"
                                                <?php echo $admin['admin_role_id'] == $role['id'] ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['display_name']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-help">Optional: Assign a specific admin role with defined permissions</small>
                            </div>
                        </div>

                        <!-- Current Info Display -->
                        <div class="current-info">
                            <h4><i class="fas fa-info-circle"></i> Current Information</h4>
                            <div class="info-grid">
                                <div class="info-item">
                                    <label>Current Username:</label>
                                    <span><?php echo htmlspecialchars($admin['username']); ?></span>
                                </div>
                                <div class="info-item">
                                    <label>Current Role:</label>
                                    <span class="badge badge-primary">
                                        <?php echo htmlspecialchars($admin['role_display'] ?? 'Admin'); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <label>Status:</label>
                                    <span class="badge badge-<?php echo $admin['status'] === 'active' ? 'success' : 'warning'; ?>">
                                        <?php echo ucfirst($admin['status']); ?>
                                    </span>
                                </div>
                                <div class="info-item">
                                    <label>Last Login:</label>
                                    <span>
                                        <?php if ($admin['last_login']): ?>
                                            <?php echo date('M j, Y H:i:s', strtotime($admin['last_login'])); ?>
                                        <?php else: ?>
                                            Never
                                        <?php endif; ?>
                                    </span>
                                </div>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-save"></i>
                                Update Admin
                            </button>
                            <a href="index.php" class="btn btn-secondary">
                                <i class="fas fa-times"></i>
                                Cancel
                            </a>
                        </div>
                    </form>
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
            display: flex;
            justify-content: space-between;
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
            margin: 0 0 0.5rem 0;
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

        .header-breadcrumb a {
            color: var(--gray-500);
            text-decoration: none;
        }

        .header-breadcrumb a:hover {
            color: var(--orange-600);
        }

        .header-breadcrumb .active {
            color: var(--orange-600);
            font-weight: 600;
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

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .admin-form {
            padding: 1.5rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr;
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: var(--gray-700);
            font-size: 0.875rem;
        }

        .form-group label i {
            color: var(--orange-600);
            width: 16px;
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

        .form-help {
            margin-top: 0.25rem;
            font-size: 0.75rem;
            color: var(--gray-500);
        }

        .current-info {
            background: var(--gray-50);
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.25rem;
            margin-bottom: 2rem;
        }

        .current-info h4 {
            margin: 0 0 1rem 0;
            color: var(--gray-700);
            font-size: 1rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .current-info h4 i {
            color: var(--orange-600);
        }

        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
        }

        .info-item {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .info-item label {
            font-size: 0.75rem;
            color: var(--gray-500);
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }

        .info-item span {
            font-size: 0.875rem;
            color: var(--gray-700);
        }

        .form-actions {
            display: flex;
            gap: 0.75rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
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
            text-decoration: none;
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

        /* Responsive Design */
        @media (max-width: 768px) {
            .enhanced-page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .header-main {
                justify-content: center;
            }

            .form-actions {
                flex-direction: column;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

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

            // Form validation
            const form = document.querySelector('.admin-form');
            const passwordField = document.getElementById('password');

            form.addEventListener('submit', function(e) {
                const username = document.getElementById('username').value.trim();
                const password = passwordField.value;

                if (username.length < 3) {
                    e.preventDefault();
                    alert('Username must be at least 3 characters long');
                    return;
                }

                if (password && password.length < 6) {
                    e.preventDefault();
                    alert('Password must be at least 6 characters long');
                    return;
                }
            });
        });
    </script>
</body>
</html>
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

$pageTitle = 'Create New Admin';
$user = getUser();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Debug logging
    error_log("🔍 CREATE PAGE: POST request received");
    error_log("🔍 CREATE PAGE: POST data: " . print_r($_POST, true));

    $username = sanitize($_POST['username']);
    $email = sanitize($_POST['email'] ?? '');
    $password = $_POST['password'];
    $admin_role_id = (int)$_POST['admin_role_id'];
    $status = $_POST['status'] ?? 'active';

    error_log("🔍 CREATE PAGE: Processed data - Username: $username, Email: $email, Role ID: $admin_role_id, Status: $status");
    error_log("🔍 CREATE PAGE: Current user data: " . print_r($user, true));

    if (!empty($username) && !empty($password) && $admin_role_id > 0) {
        error_log("🔍 CREATE PAGE: Validation passed - proceeding with creation");
        try {
            // Check if username already exists
            error_log("🔍 CREATE PAGE: Checking if username exists");
            $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$username]);
            if ($stmt->fetch()) {
                error_log("🔍 CREATE PAGE: Username already exists");
                $message = 'Username already exists';
                $messageType = 'error';
            } else {
                error_log("🔍 CREATE PAGE: Username is available");
                // Check if email exists (if provided)
                if (!empty($email)) {
                    $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
                    $stmt->execute([$email]);
                    if ($stmt->fetch()) {
                        $message = 'Email already exists';
                        $messageType = 'error';
                    }
                }

                if (empty($message)) {
                    error_log("🔍 CREATE PAGE: Proceeding with user creation");
                    // Create new admin user
                    $hashedPassword = password_hash($password, PASSWORD_DEFAULT);
                    error_log("🔍 CREATE PAGE: Password hashed successfully");

                    // Handle email - set to NULL if empty
                    $email_value = !empty($email) ? $email : NULL;

                    $sql = "INSERT INTO users (username, email, password, role, admin_role_id, is_admin, status, created_by) VALUES (?, ?, ?, 'admin', ?, 1, ?, ?)";
                    error_log("🔍 CREATE PAGE: SQL Query: $sql");
                    error_log("🔍 CREATE PAGE: Parameters: [username, email_value, ***password***, $admin_role_id, '$status', created_by_id]");

                    $stmt = $pdo->prepare($sql);

                    // Use current user ID if available, otherwise use 1 (like debug form)
                    $created_by_id = ($user && isset($user['id'])) ? $user['id'] : 1;
                    error_log("🔍 CREATE PAGE: Using created_by ID: $created_by_id");

                    if ($stmt->execute([$username, $email_value, $hashedPassword, $admin_role_id, $status, $created_by_id])) {
                        $new_id = $pdo->lastInsertId();
                        error_log("🔍 CREATE PAGE: SUCCESS! User created with ID: $new_id");

                        try {
                            logAdminAction('create_admin', 'user', $new_id, [
                                'username' => $username,
                                'admin_role_id' => $admin_role_id
                            ]);
                            error_log("🔍 CREATE PAGE: Admin action logged successfully");
                        } catch (Exception $e) {
                            error_log("🔍 CREATE PAGE: Failed to log admin action: " . $e->getMessage());
                        }

                        $message = 'Admin user created successfully';
                        $messageType = 'success';

                        // Clear form data on success
                        $_POST = [];
                    } else {
                        error_log("🔍 CREATE PAGE: Failed to execute INSERT statement");
                        error_log("🔍 CREATE PAGE: PDO Error Info: " . print_r($stmt->errorInfo(), true));
                        $message = 'Failed to create admin user';
                        $messageType = 'error';
                    }
                }
            }
        } catch (PDOException $e) {
            error_log("🔍 CREATE PAGE: Database error: " . $e->getMessage());
            error_log("🔍 CREATE PAGE: Stack trace: " . $e->getTraceAsString());
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        error_log("🔍 CREATE PAGE: Validation failed - missing required fields");
        error_log("🔍 CREATE PAGE: Username: " . ($username ? 'SET' : 'EMPTY'));
        error_log("🔍 CREATE PAGE: Password: " . ($password ? 'SET' : 'EMPTY'));
        error_log("🔍 CREATE PAGE: Role ID: $admin_role_id");
        $message = 'Please fill all required fields';
        $messageType = 'error';
    }
}

// Get all admin roles
try {
    $stmt = $pdo->prepare("SELECT * FROM admin_roles WHERE is_active = 1 ORDER BY id");
    $stmt->execute();
    $adminRoles = $stmt->fetchAll();
} catch (PDOException $e) {
    $adminRoles = [];
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
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <div class="header-icon">
                            <i class="fas fa-user-plus"></i>
                        </div>
                        <div class="header-content">
                            <h1>Create New Administrator</h1>
                            <p>Add a new admin user to the system</p>
                        </div>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Admin List
                        </a>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Create Admin Form -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-user-plus"></i> Administrator Details</h3>
                    </div>

                    <form method="POST" class="admin-form">
                        <div class="form-grid">
                            <div class="form-group">
                                <label for="username">Username <span class="required">*</span></label>
                                <input type="text"
                                       id="username"
                                       name="username"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>"
                                       required>
                                <small class="form-help">Choose a unique username for login</small>
                            </div>

                            <div class="form-group">
                                <label for="email">Email Address</label>
                                <input type="email"
                                       id="email"
                                       name="email"
                                       class="form-control"
                                       value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
                                <small class="form-help">Optional email address for notifications</small>
                            </div>

                            <div class="form-group">
                                <label for="password">Password <span class="required">*</span></label>
                                <input type="password"
                                       id="password"
                                       name="password"
                                       class="form-control"
                                       required
                                       minlength="6">
                                <small class="form-help">Minimum 6 characters required</small>
                            </div>

                            <div class="form-group">
                                <label for="admin_role_id">Admin Role <span class="required">*</span></label>
                                <select id="admin_role_id" name="admin_role_id" class="form-control" required>
                                    <option value="">Select Admin Role</option>
                                    <?php foreach ($adminRoles as $role): ?>
                                        <?php if ($role['role_name'] !== 'superadmin'): ?>
                                        <option value="<?php echo $role['id']; ?>"
                                                <?php echo (isset($_POST['admin_role_id']) && $_POST['admin_role_id'] == $role['id']) ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($role['display_name']); ?>
                                        </option>
                                        <?php endif; ?>
                                    <?php endforeach; ?>
                                </select>
                                <small class="form-help">Determines admin permissions and access level</small>
                            </div>

                            <div class="form-group">
                                <label for="status">Status <span class="required">*</span></label>
                                <select id="status" name="status" class="form-control">
                                    <option value="active" <?php echo (($_POST['status'] ?? 'active') === 'active') ? 'selected' : ''; ?>>Active</option>
                                    <option value="inactive" <?php echo (($_POST['status'] ?? '') === 'inactive') ? 'selected' : ''; ?>>Inactive</option>
                                </select>
                                <small class="form-help">Account status - active users can login</small>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="button" class="btn btn-secondary" onclick="window.location.href='index.php'">
                                <i class="fas fa-times"></i> Cancel
                            </button>
                            <button type="submit" class="btn btn-primary">
                                <i class="fas fa-user-plus"></i> Create Admin
                            </button>
                        </div>
                    </form>
                </div>

                <!-- Available Roles Information -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-info-circle"></i> Available Admin Roles</h3>
                    </div>

                    <div class="roles-grid">
                        <?php foreach ($adminRoles as $role): ?>
                            <?php if ($role['role_name'] !== 'superadmin'): ?>
                            <div class="role-card">
                                <div class="role-header">
                                    <i class="fas fa-user-tag"></i>
                                    <h4><?php echo htmlspecialchars($role['display_name']); ?></h4>
                                </div>
                                <p class="role-description"><?php echo htmlspecialchars($role['description']); ?></p>
                                <div class="role-permissions">
                                    <?php
                                    $permissions = json_decode($role['permissions'], true);
                                    $permCount = $permissions ? count($permissions) : 0;
                                    ?>
                                    <span class="permission-count"><?php echo $permCount; ?> permissions</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <style>
        .page-header {
            background: linear-gradient(135deg, #ffffff 0%, #f8fafc 100%);
            border: 1px solid #e2e8f0;
            border-radius: 12px;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }

        .page-title {
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

        .header-content p {
            margin: 0;
            color: var(--gray-600);
            font-size: 0.875rem;
        }

        .admin-form {
            padding: 2rem;
        }

        .form-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: var(--gray-700);
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .required {
            color: var(--error);
        }

        .form-control {
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
            font-size: 0.75rem;
            color: var(--gray-500);
            margin-top: 0.25rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e2e8f0;
        }

        .btn {
            padding: 0.75rem 1.5rem;
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

        .btn-secondary {
            background: #f1f5f9;
            color: #475569;
            border: 1px solid #e2e8f0;
        }

        .btn-secondary:hover {
            background: #e2e8f0;
            transform: translateY(-1px);
        }

        .roles-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            padding: 1.5rem;
        }

        .role-card {
            background: white;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 1.5rem;
            transition: all 0.2s ease;
        }

        .role-card:hover {
            border-color: var(--orange-200);
            transform: translateY(-2px);
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1);
        }

        .role-header {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            margin-bottom: 1rem;
        }

        .role-header i {
            color: var(--orange-500);
            font-size: 1.2rem;
        }

        .role-header h4 {
            margin: 0;
            color: var(--gray-900);
            font-size: 1rem;
            font-weight: 600;
        }

        .role-description {
            color: var(--gray-600);
            font-size: 0.875rem;
            line-height: 1.5;
            margin-bottom: 1rem;
        }

        .permission-count {
            background: var(--orange-100);
            color: var(--orange-700);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .alert {
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .alert-success {
            background: #f0f9ff;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }

        .alert-error {
            background: #fef2f2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }

        @media (max-width: 768px) {
            .page-header {
                flex-direction: column;
                gap: 1rem;
                text-align: center;
            }

            .form-grid {
                grid-template-columns: 1fr;
            }

            .form-actions {
                flex-direction: column-reverse;
            }

            .btn {
                width: 100%;
                justify-content: center;
            }
        }
    </style>

    <script src="../../assets/js/script.js"></script>
    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            console.log('🔍 CREATE PAGE: DOM loaded');
            const form = document.querySelector('.admin-form');
            console.log('🔍 CREATE PAGE: Form found:', !!form);

            if (form) {
                console.log('🔍 CREATE PAGE: Adding form submit listener');
                form.addEventListener('submit', function(e) {
                    console.log('🔍 CREATE PAGE: Form submission started');

                    const username = document.getElementById('username').value.trim();
                    const email = document.getElementById('email').value.trim();
                    const password = document.getElementById('password').value;
                    const role = document.getElementById('admin_role_id').value;

                    console.log('🔍 CREATE PAGE: Form data:', {
                        username: username,
                        email: email,
                        password: password ? '***SET***' : 'EMPTY',
                        role: role
                    });

                    if (!username || !password || !role) {
                        e.preventDefault();
                        console.log('🔍 CREATE PAGE: Validation failed - missing fields');
                        alert('Please fill in all required fields');
                        return false;
                    }

                    if (password.length < 6) {
                        e.preventDefault();
                        console.log('🔍 CREATE PAGE: Validation failed - password too short');
                        alert('Password must be at least 6 characters long');
                        return false;
                    }

                    console.log('🔍 CREATE PAGE: Validation passed, submitting form');

                    // Show loading state
                    const submitBtn = form.querySelector('button[type="submit"]');
                    if (submitBtn) {
                        submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Creating...';
                        submitBtn.disabled = true;
                        console.log('🔍 CREATE PAGE: Button set to loading state');
                    }
                });
            } else {
                console.error('🔍 CREATE PAGE: Form not found!');
            }
        });
    </script>
</body>
</html>
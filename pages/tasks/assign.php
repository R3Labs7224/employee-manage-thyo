<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission to manage tasks
if (!hasAdminPermission('tasks.manage')) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Assign Task';
$user = getUser();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $priority = $_POST['priority'] ?? 'medium';
    $due_date = $_POST['due_date'] ?? null;
    $assigned_users = $_POST['assigned_users'] ?? [];
    $site_id = $_POST['site_id'] ?? null;

    if (!empty($title) && !empty($assigned_users)) {
        try {
            $pdo->beginTransaction();

            // Insert into tasks table with admin-created flag
            $stmt = $pdo->prepare("
                INSERT INTO tasks (title, description, priority, due_date, site_id, assigned_by, admin_created, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 1, 'active', NOW())
            ");

            if ($stmt->execute([$title, $description, $priority, $due_date, $site_id, $user['id']])) {
                $task_id = $pdo->lastInsertId();

                // Insert task assignments for selected users
                $stmt_assign = $pdo->prepare("
                    INSERT INTO task_assignments (task_id, assigned_to, assigned_by, status)
                    VALUES (?, ?, ?, 'pending')
                ");

                foreach ($assigned_users as $user_id) {
                    $stmt_assign->execute([$task_id, $user_id, $user['id']]);
                }

                $pdo->commit();

                logAdminAction('assign_task', 'task', $task_id, [
                    'title' => $title,
                    'assigned_users' => $assigned_users
                ]);

                $message = 'Task assigned successfully to ' . count($assigned_users) . ' user(s)';
                $messageType = 'success';

                // Clear form data
                $_POST = [];
            } else {
                throw new Exception('Failed to create task');
            }
        } catch (Exception $e) {
            $pdo->rollback();
            $message = 'Error assigning task: ' . $e->getMessage();
            $messageType = 'error';
        }
    } else {
        $message = 'Please fill all required fields and assign to at least one user';
        $messageType = 'error';
    }
}

// Get all active users for assignment
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role,
           COALESCE(e.name, u.username) as display_name,
           COALESCE(e.employee_code, 'N/A') as employee_code
    FROM users u
    LEFT JOIN employees e ON u.username = e.email OR u.id = e.id
    WHERE u.status = 'active'
    ORDER BY u.role, display_name
");
$stmt->execute();
$users = $stmt->fetchAll();

// Get all active sites
$stmt = $pdo->prepare("SELECT id, name FROM sites WHERE status = 'active' ORDER BY name");
$stmt->execute();
$sites = $stmt->fetchAll();

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
    <div class="wrapper">
        <?php include '../../components/sidebar.php'; ?>

        <div class="main-content">
            <?php include '../../components/header.php'; ?>

            <div class="content">
                <!-- Page Header -->
                <div class="page-header">
                    <div class="page-title">
                        <div class="header-icon">
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="header-content">
                            <h1>Assign Task</h1>
                            <p>Create and assign tasks to users</p>
                        </div>
                    </div>
                    <div class="page-actions">
                        <a href="index.php" class="btn btn-secondary">
                            <i class="fas fa-arrow-left"></i> Back to Tasks
                        </a>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <div class="card">
                    <div class="card-header">
                        <h3><i class="fas fa-plus"></i> Task Details</h3>
                    </div>
                    <div class="card-body">
                        <form method="POST" class="task-form">
                            <!-- Task Information Section -->
                            <div class="form-section">
                                <h4><i class="fas fa-info-circle"></i> Task Information</h4>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="title">Task Title <span class="required">*</span></label>
                                        <input type="text" id="title" name="title" class="form-control" required
                                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>"
                                               placeholder="Enter task title">
                                    </div>

                                    <div class="form-group">
                                        <label for="site_id">Site</label>
                                        <select id="site_id" name="site_id" class="form-control">
                                            <option value="">Select Site (Optional)</option>
                                            <?php foreach ($sites as $site): ?>
                                                <option value="<?php echo $site['id']; ?>"
                                                        <?php echo (isset($_POST['site_id']) && $_POST['site_id'] == $site['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($site['name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>

                                <div class="form-group">
                                    <label for="description">Description</label>
                                    <textarea id="description" name="description" class="form-control" rows="4"
                                              placeholder="Enter task description"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="priority">Priority</label>
                                        <select id="priority" name="priority" class="form-control">
                                            <option value="low" <?php echo ($_POST['priority'] ?? '') === 'low' ? 'selected' : ''; ?>>Low</option>
                                            <option value="medium" <?php echo ($_POST['priority'] ?? 'medium') === 'medium' ? 'selected' : ''; ?>>Medium</option>
                                            <option value="high" <?php echo ($_POST['priority'] ?? '') === 'high' ? 'selected' : ''; ?>>High</option>
                                        </select>
                                    </div>

                                    <div class="form-group">
                                        <label for="due_date">Due Date</label>
                                        <input type="date" id="due_date" name="due_date" class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>"
                                               min="<?php echo date('Y-m-d'); ?>">
                                    </div>
                                </div>
                            </div>

                            <!-- User Assignment Section -->
                            <div class="form-section">
                                <h4><i class="fas fa-users"></i> Assign to Users <span class="required">*</span></h4>

                                <div class="users-assignment">
                                    <?php
                                    $current_role = '';
                                    $posted_users = $_POST['assigned_users'] ?? [];
                                    ?>
                                    <?php foreach ($users as $assignment_user): ?>
                                        <?php if ($current_role !== $assignment_user['role']): ?>
                                            <?php if ($current_role !== ''): ?></div></div><?php endif; ?>
                                            <?php $current_role = $assignment_user['role']; ?>
                                            <div class="user-role-group">
                                                <h5 class="role-header"><?php echo ucfirst($current_role); ?>s</h5>
                                                <div class="user-checkboxes">
                                        <?php endif; ?>

                                        <label class="user-checkbox">
                                            <input type="checkbox" name="assigned_users[]" value="<?php echo $assignment_user['id']; ?>"
                                                   <?php echo in_array($assignment_user['id'], $posted_users) ? 'checked' : ''; ?>>
                                            <span class="checkmark"></span>
                                            <div class="user-info">
                                                <span class="user-name"><?php echo htmlspecialchars($assignment_user['display_name']); ?></span>
                                                <small class="user-details">
                                                    <?php if ($assignment_user['employee_code'] !== 'N/A'): ?>
                                                        <?php echo htmlspecialchars($assignment_user['employee_code']); ?> -
                                                    <?php endif; ?>
                                                    <?php echo htmlspecialchars($assignment_user['username']); ?>
                                                </small>
                                            </div>
                                        </label>
                                    <?php endforeach; ?>
                                    <?php if ($current_role !== ''): ?></div></div><?php endif; ?>
                                </div>
                            </div>

                            <div class="form-actions">
                                <button type="button" onclick="window.location.href='index.php'" class="btn btn-secondary">
                                    <i class="fas fa-times"></i> Cancel
                                </button>
                                <button type="submit" class="btn btn-primary">
                                    <i class="fas fa-paper-plane"></i> Assign Task
                                </button>
                            </div>
                        </form>
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
            background: linear-gradient(135deg, #f97316, #ea580c);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.2);
        }

        .header-icon i {
            font-size: 20px;
            color: white;
        }

        .header-content h1 {
            margin: 0 0 0.25rem 0;
            font-size: 1.5rem;
            font-weight: 700;
            color: #111827;
        }

        .header-content p {
            margin: 0;
            color: #6b7280;
            font-size: 0.875rem;
        }

        .task-form {
            max-width: none;
        }

        .form-section {
            margin-bottom: 2rem;
            padding: 1.5rem;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            background: #f9fafb;
        }

        .form-section h4 {
            margin: 0 0 1.5rem 0;
            font-size: 1.125rem;
            font-weight: 600;
            color: #111827;
            display: flex;
            align-items: center;
            gap: 0.5rem;
            padding-bottom: 0.75rem;
            border-bottom: 1px solid #e5e7eb;
        }

        .form-section h4 i {
            color: #f97316;
        }

        .form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
        }

        .form-group label {
            font-weight: 600;
            color: #374151;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }

        .required {
            color: #dc2626;
        }

        .form-control {
            padding: 0.75rem;
            border: 2px solid #e5e7eb;
            border-radius: 8px;
            font-size: 0.875rem;
            transition: all 0.2s ease;
            background: white;
        }

        .form-control:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }

        .users-assignment {
            max-height: 400px;
            overflow-y: auto;
            border: 1px solid #e5e7eb;
            border-radius: 8px;
            padding: 1rem;
            background: white;
        }

        .user-role-group {
            margin-bottom: 1.5rem;
        }

        .user-role-group:last-child {
            margin-bottom: 0;
        }

        .role-header {
            margin: 0 0 0.75rem 0;
            font-size: 0.875rem;
            font-weight: 600;
            color: #f97316;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid #fed7aa;
        }

        .user-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
            gap: 0.75rem;
        }

        .user-checkbox {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 6px;
            border: 1px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .user-checkbox:hover {
            background: #fef3e2;
            border-color: #fed7aa;
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .user-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: #f97316;
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .user-name {
            font-weight: 500;
            color: #111827;
            font-size: 0.875rem;
        }

        .user-details {
            color: #6b7280;
            font-size: 0.75rem;
        }

        .form-actions {
            display: flex;
            gap: 1rem;
            justify-content: flex-end;
            padding-top: 1.5rem;
            border-top: 1px solid #e5e7eb;
            margin-top: 2rem;
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
            background: linear-gradient(135deg, #f97316, #ea580c);
            color: white;
            box-shadow: 0 2px 4px rgba(249, 115, 22, 0.2);
        }

        .btn-primary:hover {
            background: linear-gradient(135deg, #ea580c, #dc2626);
            transform: translateY(-1px);
            box-shadow: 0 4px 8px rgba(249, 115, 22, 0.3);
        }

        .btn-secondary {
            background: #f3f4f6;
            color: #374151;
            border: 1px solid #d1d5db;
        }

        .btn-secondary:hover {
            background: #e5e7eb;
            transform: translateY(-1px);
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

            .user-checkboxes {
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

    <script>
        // Form validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('.task-form');
            const checkboxes = document.querySelectorAll('input[name="assigned_users[]"]');

            form.addEventListener('submit', function(e) {
                const title = document.getElementById('title').value.trim();
                const checkedUsers = document.querySelectorAll('input[name="assigned_users[]"]:checked');

                if (!title) {
                    e.preventDefault();
                    alert('Please enter a task title');
                    return false;
                }

                if (checkedUsers.length === 0) {
                    e.preventDefault();
                    alert('Please assign the task to at least one user');
                    return false;
                }

                // Show loading state
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn) {
                    submitBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Assigning...';
                    submitBtn.disabled = true;
                }
            });

            // Add visual feedback for checkbox selection
            checkboxes.forEach(checkbox => {
                checkbox.addEventListener('change', function() {
                    const label = this.closest('.user-checkbox');
                    if (this.checked) {
                        label.style.background = '#fef3e2';
                        label.style.borderColor = '#f97316';
                    } else {
                        label.style.background = '#f9fafb';
                        label.style.borderColor = '#e5e7eb';
                    }
                });
            });
        });
    </script>
</body>
</html>
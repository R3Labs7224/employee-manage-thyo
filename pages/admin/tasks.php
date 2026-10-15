<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/task_identifier.php';

requireLogin();

// Check if user is admin
if (!isAdmin()) {
    header('Location: ../../index.php');
    exit;
}

$pageTitle = 'Task Management';
$user = getUser();

// Handle form submission
$message = '';
$messageType = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create_task') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = $_POST['due_date'] ?? null;
        $assigned_users = $_POST['assigned_users'] ?? [];
        $status = 'pending';

        if (!empty($title) && !empty($assigned_users)) {
            try {
                $pdo->beginTransaction();

                // Generate task identifier ONCE for this task (before creating individual assignments)
                $site_id = 1; // Default site ID
                $task_identifier = generateTaskIdentifier($pdo, $title, getSiteNameById($pdo, $site_id));

                // Create separate task for each assigned user in unified table
                $created_count = 0;
                foreach ($assigned_users as $user_id) {
                    // Map user ID to employee ID
                    $emp_stmt = $pdo->prepare("
                        SELECT e.id FROM employees e
                        JOIN users u ON e.email = u.email
                        WHERE u.id = ? AND e.status = 'active'
                    ");
                    $emp_stmt->execute([$user_id]);
                    $employee = $emp_stmt->fetch();

                    if ($employee) {
                        $stmt = $pdo->prepare("
                            INSERT INTO tasks (
                                title, description, task_type, assigned_to_employee_id, assigned_by_user_id,
                                site_id, priority, due_date, status, task_identifier, created_at
                            ) VALUES (?, ?, 'admin_assigned', ?, ?, ?, ?, ?, ?, ?, NOW())
                        ");

                        if ($stmt->execute([$title, $description, $employee['id'], $user['id'], $site_id, $priority, $due_date, $status, $task_identifier])) {
                            $created_count++;
                        }
                    }
                }

                if ($created_count > 0) {
                    $pdo->commit();

                    logAdminAction('create_task', 'unified_task', null, [
                        'title' => $title,
                        'assigned_users' => $assigned_users,
                        'created_count' => $created_count
                    ]);

                    $message = "Task created and assigned to {$created_count} user(s) successfully";
                    $messageType = 'success';

                    // Clear form data
                    $_POST = [];
                } else {
                    throw new Exception('No valid employees found for assigned users');
                }
            } catch (Exception $e) {
                $pdo->rollback();
                $message = 'Error creating task: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Please fill all required fields and assign to at least one user';
            $messageType = 'error';
        }
    }

    if ($action === 'update_task_status') {
        $task_id = (int)$_POST['task_id'];
        $new_status = $_POST['new_status'];

        try {
            // First check if task exists
            $check_stmt = $pdo->prepare("SELECT id, title, status FROM tasks WHERE id = ? AND task_type = 'admin_assigned'");
            $check_stmt->execute([$task_id]);
            $existing_task = $check_stmt->fetch();

            if (!$existing_task) {
                $message = "Task not found (ID: $task_id)";
                $messageType = 'error';
            } else {
                $stmt = $pdo->prepare("UPDATE tasks SET status = ?, updated_at = NOW() WHERE id = ? AND task_type = 'admin_assigned'");
                if ($stmt->execute([$new_status, $task_id])) {
                    $affected_rows = $stmt->rowCount();
                    if ($affected_rows > 0) {
                        try {
                            logAdminAction('update_task_status', 'unified_task', $task_id, [
                                'old_status' => $existing_task['status'],
                                'new_status' => $new_status,
                                'task_title' => $existing_task['title']
                            ]);
                        } catch (Exception $log_error) {
                            // Log error but don't fail the update
                            error_log("Log admin action failed: " . $log_error->getMessage());
                        }
                        $message = 'Task status updated successfully';
                        $messageType = 'success';
                    } else {
                        $message = 'No rows were updated - task may not exist or already has this status';
                        $messageType = 'error';
                    }
                } else {
                    $message = 'Failed to execute update query';
                    $messageType = 'error';
                }
            }
        } catch (PDOException $e) {
            $message = 'Database error: ' . $e->getMessage();
            $messageType = 'error';
            error_log("Task status update error: " . $e->getMessage());
        }
    }
}

// Get all users (both admin and employees for assignment)
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

// Get all admin tasks from unified table (individual assignments)
$stmt = $pdo->prepare("
    SELECT t.*,
           u.username as created_by_username,
           e.name as assigned_employee_name,
           e.employee_code as assigned_employee_code,
           1 as total_assignments,
           CASE WHEN t.status = 'completed' THEN 1 ELSE 0 END as completed_assignments
    FROM tasks t
    LEFT JOIN users u ON t.assigned_by_user_id = u.id
    LEFT JOIN employees e ON t.assigned_to_employee_id = e.id
    WHERE t.task_type = 'admin_assigned'
    ORDER BY t.created_at DESC
");
$stmt->execute();
$tasks = $stmt->fetchAll();

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
                            <i class="fas fa-tasks"></i>
                        </div>
                        <div class="header-content">
                            <h1>Task Management</h1>
                            <p>Create and assign tasks to users</p>
                        </div>
                    </div>

                    <div class="header-stats">
                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clipboard-list"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count($tasks); ?></span>
                                <span class="stat-label">Total Tasks</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-clock"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count(array_filter($tasks, function($task) { return $task['status'] === 'pending'; })); ?></span>
                                <span class="stat-label">Pending</span>
                            </div>
                        </div>

                        <div class="stat-card">
                            <div class="stat-icon">
                                <i class="fas fa-check-circle"></i>
                            </div>
                            <div class="stat-info">
                                <span class="stat-number"><?php echo count(array_filter($tasks, function($task) { return $task['status'] === 'completed'; })); ?></span>
                                <span class="stat-label">Completed</span>
                            </div>
                        </div>
                    </div>

                    <div class="header-actions">
                        <button class="btn btn-primary" onclick="openCreateTaskModal()">
                            <i class="fas fa-plus"></i>
                            <span>Create New Task</span>
                        </button>
                    </div>
                </div>

                <?php if (!empty($message)): ?>
                <div class="alert alert-<?php echo $messageType; ?>">
                    <i class="fas fa-<?php echo $messageType === 'success' ? 'check-circle' : 'exclamation-triangle'; ?>"></i>
                    <?php echo htmlspecialchars($message); ?>
                </div>
                <?php endif; ?>

                <!-- Tasks Table -->
                <div class="glass-card">
                    <div class="card-header">
                        <h3><i class="fas fa-clipboard-list"></i> All Tasks</h3>
                        <div class="header-actions">
                            <span class="badge badge-info"><?php echo count($tasks); ?> Tasks</span>
                        </div>
                    </div>

                    <div class="table-responsive">
                        <table class="table">
                            <thead>
                                <tr>
                                    <th>Task ID</th>
                                    <th>Task Details</th>
                                    <th>Priority</th>
                                    <th>Due Date</th>
                                    <th>Assignments</th>
                                    <th>Status</th>
                                    <th>Created By</th>
                                    <th>Created Date</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (empty($tasks)): ?>
                                <tr>
                                    <td colspan="9" class="text-center">
                                        <div class="empty-state">
                                            <i class="fas fa-clipboard-list"></i>
                                            <h4>No Tasks Found</h4>
                                            <p>Create your first task to get started</p>
                                        </div>
                                    </td>
                                </tr>
                                <?php else: ?>
                                <?php foreach ($tasks as $task): ?>
                                <tr>
                                    <td>
                                        <div class="task-id-info">
                                            <?php if (!empty($task['task_identifier'])): ?>
                                                <span class="task-identifier">MGR-<?php echo htmlspecialchars($task['task_identifier']); ?></span>
                                            <?php else: ?>
                                                <span class="task-identifier-placeholder">No ID</span>
                                            <?php endif; ?>
                                            <?php if (!empty($task['site_name'])): ?>
                                                <small class="site-info">
                                                    <i class="fas fa-map-marker-alt"></i>
                                                    <?php echo htmlspecialchars($task['site_name']); ?>
                                                </small>
                                            <?php endif; ?>
                                        </div>
                                    </td>
                                    <td>
                                        <div class="task-info">
                                            <h4 class="task-title"><?php echo htmlspecialchars($task['title']); ?></h4>
                                            <p class="task-description"><?php echo htmlspecialchars($task['description']); ?></p>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php
                                            echo $task['priority'] === 'high' ? 'danger' :
                                                ($task['priority'] === 'medium' ? 'warning' : 'info');
                                        ?>">
                                            <?php echo ucfirst($task['priority']); ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if ($task['due_date']): ?>
                                            <?php echo date('M j, Y', strtotime($task['due_date'])); ?>
                                        <?php else: ?>
                                            <span class="text-muted">No due date</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="assignment-info">
                                            <span class="assignment-count">
                                                <strong><?php echo htmlspecialchars($task['assigned_employee_code'] ?? 'N/A'); ?></strong>
                                            </span>
                                            <small class="assignment-progress">
                                                <?php echo htmlspecialchars($task['assigned_employee_name'] ?? 'Unknown Employee'); ?>
                                            </small>
                                            <br>
                                            <small style="color: #666;">
                                                Status: <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                            </small>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-<?php
                                            echo $task['status'] === 'completed' ? 'success' :
                                                ($task['status'] === 'in_progress' ? 'warning' : 'secondary');
                                        ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $task['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo htmlspecialchars($task['created_by_username']); ?></td>
                                    <td><?php echo date('M j, Y H:i', strtotime($task['created_at'])); ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <form method="POST" style="display: inline;">
                                                <input type="hidden" name="action" value="update_task_status">
                                                <input type="hidden" name="task_id" value="<?php echo $task['id']; ?>">
                                                <select name="new_status" onchange="this.form.submit()" class="form-control form-control-sm">
                                                    <option value="pending" <?php echo $task['status'] === 'pending' ? 'selected' : ''; ?>>Pending</option>
                                                    <option value="in_progress" <?php echo $task['status'] === 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                                    <option value="completed" <?php echo $task['status'] === 'completed' ? 'selected' : ''; ?>>Completed</option>
                                                    <option value="cancelled" <?php echo $task['status'] === 'cancelled' ? 'selected' : ''; ?>>Cancelled</option>
                                                </select>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                                <?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Create Task Modal -->
    <div id="createTaskModal" class="modal">
        <div class="modal-content large">
            <div class="modal-header">
                <h3><i class="fas fa-plus"></i> Create New Task</h3>
                <button class="modal-close" onclick="closeCreateTaskModal()">&times;</button>
            </div>
            <form method="POST">
                <input type="hidden" name="action" value="create_task">

                <div class="modal-body">
                    <div class="form-group">
                        <label for="title">Task Title <span class="required">*</span></label>
                        <input type="text" id="title" name="title" class="form-control" required
                               value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
                    </div>

                    <div class="form-group">
                        <label for="description">Description</label>
                        <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
                    </div>

                    <div class="form-row">
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
                                   value="<?php echo htmlspecialchars($_POST['due_date'] ?? ''); ?>">
                        </div>
                    </div>

                    <div class="form-group">
                        <label>Assign To <span class="required">*</span></label>
                        <div class="users-assignment">
                            <?php
                            $current_role = '';
                            $posted_users = $_POST['assigned_users'] ?? [];
                            ?>
                            <?php foreach ($users as $assignment_user): ?>
                                <?php if ($current_role !== $assignment_user['role']): ?>
                                    <?php if ($current_role !== ''): ?></div><?php endif; ?>
                                    <?php $current_role = $assignment_user['role']; ?>
                                    <div class="user-role-group">
                                        <h4 class="role-header"><?php echo ucfirst($current_role); ?>s</h4>
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
                </div>

                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" onclick="closeCreateTaskModal()">Cancel</button>
                    <button type="submit" class="btn btn-primary">Create Task</button>
                </div>
            </form>
        </div>
    </div>

    <!-- View Assignments Modal -->
    <div id="viewAssignmentsModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3><i class="fas fa-users"></i> Task Assignments</h3>
                <button class="modal-close" onclick="closeViewAssignmentsModal()">&times;</button>
            </div>
            <div class="modal-body">
                <div id="assignmentsDisplay">Loading...</div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeViewAssignmentsModal()">Close</button>
            </div>
        </div>
    </div>

    <?php include '../../components/admin_page_styles.php'; ?>

    <style>
        .task-info {
            max-width: 300px;
        }

        .task-title {
            margin: 0 0 0.5rem 0;
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--gray-900);
        }

        .task-description {
            margin: 0;
            font-size: 0.8rem;
            color: var(--gray-600);
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
        }

        .task-id-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
            min-width: 120px;
        }

        .task-identifier {
            font-family: 'Courier New', monospace;
            font-weight: 600;
            font-size: 0.875rem;
            color: var(--orange-600);
            background: var(--orange-50);
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            border: 1px solid var(--orange-200);
            display: inline-block;
        }

        .task-identifier-placeholder {
            font-size: 0.75rem;
            color: var(--gray-400);
            font-style: italic;
        }

        .site-info {
            font-size: 0.75rem;
            color: var(--gray-500);
            display: flex;
            align-items: center;
            gap: 0.25rem;
        }

        .site-info i {
            font-size: 0.65rem;
        }

        .filter-form {
            margin: 0;
        }

        .filter-row {
            display: grid;
            grid-template-columns: 1fr 1fr 1fr 180px;
            gap: 1rem;
            align-items: end;
        }

        .filter-buttons {
            display: flex;
            gap: 0.5rem;
        }

        .filter-buttons .btn {
            padding: 0.5rem 1rem;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .filter-row {
                grid-template-columns: 1fr;
                gap: 0.75rem;
            }

            .filter-buttons {
                justify-content: center;
            }
        }

        .assignment-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .assignment-count {
            font-weight: 600;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        .assignment-progress {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .users-assignment {
            max-height: 300px;
            overflow-y: auto;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            padding: 1rem;
            background: var(--gray-50);
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
            color: var(--orange-600);
            text-transform: uppercase;
            letter-spacing: 0.05em;
            padding-bottom: 0.5rem;
            border-bottom: 1px solid var(--orange-200);
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
            background: white;
            border-radius: 6px;
            border: 1px solid var(--border-color);
            transition: all 0.2s ease;
        }

        .user-checkbox:hover {
            background: var(--orange-50);
            border-color: var(--orange-200);
            transform: translateY(-1px);
            box-shadow: 0 2px 4px rgba(0,0,0,0.1);
        }

        .user-checkbox input[type="checkbox"] {
            width: 16px;
            height: 16px;
            accent-color: var(--orange-500);
        }

        .user-info {
            display: flex;
            flex-direction: column;
            gap: 0.25rem;
        }

        .user-name {
            font-weight: 500;
            color: var(--gray-900);
            font-size: 0.875rem;
        }

        .user-details {
            color: var(--gray-500);
            font-size: 0.75rem;
        }

        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 1.5rem;
        }

        .form-control-sm {
            padding: 0.375rem 0.5rem;
            font-size: 0.75rem;
            border-radius: 4px;
        }

        .empty-state {
            text-align: center;
            padding: 3rem 1rem;
            color: var(--gray-500);
        }

        .empty-state i {
            font-size: 3rem;
            margin-bottom: 1rem;
            color: var(--gray-400);
        }

        .empty-state h4 {
            margin: 0 0 0.5rem 0;
            color: var(--gray-700);
            font-weight: 600;
        }

        .empty-state p {
            margin: 0;
            font-size: 0.875rem;
        }

        @media (max-width: 768px) {
            .form-row {
                grid-template-columns: 1fr;
            }

            .user-checkboxes {
                grid-template-columns: 1fr;
            }

            .task-info {
                max-width: none;
            }
        }
    </style>

    <script>
        function openCreateTaskModal() {
            document.getElementById('createTaskModal').classList.add('show');
        }

        function closeCreateTaskModal() {
            document.getElementById('createTaskModal').classList.remove('show');
        }

        function viewAssignments(taskId) {
            const modal = document.getElementById('viewAssignmentsModal');
            const display = document.getElementById('assignmentsDisplay');

            display.innerHTML = 'Loading...';
            modal.classList.add('show');

            // Fetch task assignments via AJAX
            fetch('get_task_assignments.php?task_id=' + taskId)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        let html = '<h4>Task: ' + data.task.title + '</h4>';
                        html += '<div class="assignments-list">';

                        if (data.assignments.length > 0) {
                            data.assignments.forEach(assignment => {
                                html += `
                                    <div class="assignment-item">
                                        <div class="assignment-user">
                                            <i class="fas fa-user"></i>
                                            <span>${assignment.display_name}</span>
                                            <small>(${assignment.username})</small>
                                        </div>
                                        <div class="assignment-status">
                                            <span class="badge badge-${assignment.status === 'completed' ? 'success' : 'secondary'}">
                                                ${assignment.status}
                                            </span>
                                        </div>
                                    </div>
                                `;
                            });
                        } else {
                            html += '<p class="text-muted">No assignments found</p>';
                        }

                        html += '</div>';
                        display.innerHTML = html;
                    } else {
                        display.innerHTML = '<p class="text-error">Error loading assignments</p>';
                    }
                })
                .catch(error => {
                    display.innerHTML = '<p class="text-error">Error loading assignments</p>';
                });
        }

        function closeViewAssignmentsModal() {
            document.getElementById('viewAssignmentsModal').classList.remove('show');
        }

        // Close modals when clicking outside
        window.onclick = function(event) {
            const createModal = document.getElementById('createTaskModal');
            const viewModal = document.getElementById('viewAssignmentsModal');

            if (event.target === createModal) {
                closeCreateTaskModal();
            }
            if (event.target === viewModal) {
                closeViewAssignmentsModal();
            }
        }

        // Keyboard support
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeCreateTaskModal();
                closeViewAssignmentsModal();
            }
        });
    </script>
</body>
</html>
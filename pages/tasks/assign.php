<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';
require_once '../../includes/task_identifier.php';

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
    $start_date = $_POST['start_date'] ?? null;
    $end_date = $_POST['end_date'] ?? null;
    $assigned_users = $_POST['assigned_users'] ?? [];
    $site_id = $_POST['site_id'] ?? null;
    $expense_required = isset($_POST['expense_required']) ? 1 : 0;
    $budget_amount = $expense_required && !empty($_POST['budget_amount']) ? (float)$_POST['budget_amount'] : null;

    if (!empty($title) && !empty($assigned_users)) {
        try {
            $pdo->beginTransaction();

            // Generate task identifier ONCE for this task (before creating individual assignments)
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
                            site_id, priority, start_date, due_date, status, expense_required, budget_amount, task_identifier, created_at
                        ) VALUES (?, ?, 'admin_assigned', ?, ?, ?, ?, ?, ?, 'pending', ?, ?, ?, NOW())
                    ");

                    if ($stmt->execute([$title, $description, $employee['id'], $user['id'], $site_id, $priority, $start_date, $end_date, $expense_required, $budget_amount, $task_identifier])) {
                        $created_count++;
                    }
                }
            }

            if ($created_count > 0) {
                $pdo->commit();

                logAdminAction('assign_task', 'unified_task', null, [
                    'title' => $title,
                    'start_date' => $start_date,
                    'end_date' => $end_date,
                    'expense_required' => $expense_required,
                    'budget_amount' => $budget_amount,
                    'assigned_users' => $assigned_users,
                    'created_count' => $created_count
                ]);

                $message = "Task assigned successfully to {$created_count} user(s)";
                $messageType = 'success';

                // Clear form data
                $_POST = [];
            } else {
                throw new Exception('No valid employees found for assigned users');
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

// First, create user accounts for employees who don't have them
$create_users_stmt = $pdo->prepare("
    INSERT IGNORE INTO users (username, email, password, role, status, created_at)
    SELECT
        CONCAT(SUBSTRING_INDEX(e.email, '@', 1), '_', e.employee_code) as username,
        e.email,
        ? as password,
        'employee' as role,
        'active' as status,
        NOW() as created_at
    FROM employees e
    WHERE e.status = 'active'
    AND e.email IS NOT NULL
    AND e.email NOT IN (SELECT COALESCE(u.email, '') FROM users u WHERE u.email IS NOT NULL)
");
$default_password = password_hash('employee123', PASSWORD_DEFAULT);
$create_users_stmt->execute([$default_password]);

// Now get all active users for assignment (including newly created employee accounts)
$stmt = $pdo->prepare("
    SELECT u.id, u.username, u.role,
           COALESCE(e.name, u.username) as display_name,
           COALESCE(e.employee_code, 'N/A') as employee_code
    FROM users u
    LEFT JOIN employees e ON u.email = e.email
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
                                </div>

                                <div class="form-grid">
                                    <div class="form-group">
                                        <label for="start_date">Start Date</label>
                                        <input type="date" id="start_date" name="start_date" class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['start_date'] ?? ''); ?>"
                                               min="<?php echo date('Y-m-d'); ?>"
                                               onchange="calculateDuration()">
                                    </div>

                                    <div class="form-group">
                                        <label for="end_date">End Date</label>
                                        <input type="date" id="end_date" name="end_date" class="form-control"
                                               value="<?php echo htmlspecialchars($_POST['end_date'] ?? ''); ?>"
                                               min="<?php echo date('Y-m-d'); ?>"
                                               onchange="calculateDuration()">
                                    </div>
                                </div>

                                <div class="form-group" id="duration-display" style="display: none;">
                                    <label>Duration</label>
                                    <div class="duration-info">
                                        <span id="duration-text"></span>
                                    </div>
                                </div>
                            </div>

                            <!-- Budget Section -->
                            <div class="form-section">
                                <h4><i class="fas fa-dollar-sign"></i> Budget Information</h4>

                                <div class="form-group">
                                    <label class="checkbox-label">
                                        <input type="checkbox" id="expense_required" name="expense_required"
                                               onchange="toggleBudgetField()"
                                               <?php echo isset($_POST['expense_required']) ? 'checked' : ''; ?>>
                                        <span class="checkmark-budget"></span>
                                        <span class="checkbox-text">Expense Required</span>
                                    </label>
                                    <small class="form-text text-muted">Check this if the task requires budget allocation</small>
                                </div>

                                <div class="form-group" id="budget-amount-group" style="display: none;">
                                    <label for="budget_amount">Budget Amount <span class="required">*</span></label>
                                    <div class="input-group">
                                        <span class="input-prefix">₹</span>
                                        <input type="number" id="budget_amount" name="budget_amount" class="form-control"
                                               step="0.01" min="0" placeholder="Enter budget amount"
                                               value="<?php echo htmlspecialchars($_POST['budget_amount'] ?? ''); ?>">
                                    </div>
                                    <small class="form-text text-muted">Enter the allocated budget amount for this task</small>
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

        .duration-info {
            padding: 0.75rem;
            background: #f0f9ff;
            border: 1px solid #bae6fd;
            border-radius: 6px;
            font-weight: 500;
            color: #0c4a6e;
        }

        .checkbox-label {
            display: flex;
            align-items: center;
            gap: 0.75rem;
            cursor: pointer;
            padding: 0.75rem;
            background: #f9fafb;
            border-radius: 8px;
            border: 2px solid #e5e7eb;
            transition: all 0.2s ease;
        }

        .checkbox-label:hover {
            background: #f0f9ff;
            border-color: #3b82f6;
        }

        .checkbox-label input[type="checkbox"] {
            width: 18px;
            height: 18px;
            accent-color: #f97316;
            cursor: pointer;
        }

        .checkbox-text {
            font-weight: 500;
            color: #374151;
            font-size: 0.875rem;
        }

        .input-group {
            display: flex;
            align-items: center;
        }

        .input-prefix {
            background: #f3f4f6;
            border: 2px solid #e5e7eb;
            border-right: none;
            padding: 0.75rem 1rem;
            border-radius: 8px 0 0 8px;
            font-weight: 600;
            color: #374151;
            font-size: 1rem;
        }

        .input-group .form-control {
            border-radius: 0 8px 8px 0;
            border-left: none;
        }

        .input-group .form-control:focus {
            border-left: 2px solid #f97316;
        }

        .form-text {
            margin-top: 0.5rem;
            font-size: 0.75rem;
            color: #6b7280;
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

                // Validate budget if expense is required
                const expenseRequired = document.getElementById('expense_required').checked;
                const budgetAmount = document.getElementById('budget_amount').value;

                if (expenseRequired && (!budgetAmount || budgetAmount <= 0)) {
                    e.preventDefault();
                    alert('Please enter a valid budget amount when expense is required');
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

        // Calculate duration between start and end dates
        function calculateDuration() {
            const startDate = document.getElementById('start_date').value;
            const endDate = document.getElementById('end_date').value;
            const durationDisplay = document.getElementById('duration-display');
            const durationText = document.getElementById('duration-text');

            if (startDate && endDate) {
                const start = new Date(startDate);
                const end = new Date(endDate);

                if (end >= start) {
                    const timeDiff = end.getTime() - start.getTime();
                    const dayDiff = Math.ceil(timeDiff / (1000 * 3600 * 24));

                    let durationString = '';
                    if (dayDiff === 0) {
                        durationString = 'Same day';
                    } else if (dayDiff === 1) {
                        durationString = '1 day';
                    } else {
                        durationString = dayDiff + ' days';
                    }

                    durationText.textContent = durationString;
                    durationDisplay.style.display = 'block';
                } else {
                    durationText.textContent = 'End date must be after start date';
                    durationText.style.color = '#dc2626';
                    durationDisplay.style.display = 'block';
                }
            } else {
                durationDisplay.style.display = 'none';
            }
        }

        // Set minimum end date based on start date
        document.getElementById('start_date').addEventListener('change', function() {
            const startDate = this.value;
            const endDateInput = document.getElementById('end_date');

            if (startDate) {
                endDateInput.min = startDate;
                if (endDateInput.value && endDateInput.value < startDate) {
                    endDateInput.value = startDate;
                }
            }
            calculateDuration();
        });

        // Toggle budget amount field based on expense required checkbox
        function toggleBudgetField() {
            const expenseRequired = document.getElementById('expense_required');
            const budgetGroup = document.getElementById('budget-amount-group');
            const budgetAmount = document.getElementById('budget_amount');

            if (expenseRequired.checked) {
                budgetGroup.style.display = 'block';
                budgetAmount.required = true;
            } else {
                budgetGroup.style.display = 'none';
                budgetAmount.required = false;
                budgetAmount.value = '';
            }
        }

        // Initialize budget field on page load
        document.addEventListener('DOMContentLoaded', function() {
            toggleBudgetField();

            // Initialize form values on page load
            const expenseCheckbox = document.getElementById('expense_required');
            if (expenseCheckbox && expenseCheckbox.checked) {
                toggleBudgetField();
            }
        });
    </script>
</body>
</html>
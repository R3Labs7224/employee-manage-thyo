<?php
require_once '../../config/database.php';
require_once '../../includes/session.php';
require_once '../../includes/functions.php';

requireLogin();

// Check if user has permission to edit tasks
if (!hasAdminPermission('tasks.edit')) {
    echo "<script>alert('Access denied. You do not have permission to edit tasks.'); window.close();</script>";
    exit;
}

$task_id = (int)($_GET['task_id'] ?? 0);
$message = '';
$messageType = '';

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'update_task') {
        $title = sanitize($_POST['title']);
        $description = sanitize($_POST['description']);
        $priority = $_POST['priority'] ?? 'medium';
        $due_date = $_POST['due_date'] ?? null;
        $expense_required = isset($_POST['expense_required']) ? 1 : 0;
        $budget_amount = $expense_required && !empty($_POST['budget_amount']) ? (float)$_POST['budget_amount'] : null;

        if (!empty($title)) {
            try {
                $stmt = $pdo->prepare("
                    UPDATE tasks SET
                        title = ?,
                        description = ?,
                        priority = ?,
                        due_date = ?,
                        expense_required = ?,
                        budget_amount = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");

                if ($stmt->execute([$title, $description, $priority, $due_date, $expense_required, $budget_amount, $task_id])) {
                    $message = 'Task updated successfully!';
                    $messageType = 'success';
                    echo "<script>
                        alert('Task updated successfully!');
                        setTimeout(() => window.close(), 500);
                    </script>";
                } else {
                    $message = 'Failed to update task';
                    $messageType = 'error';
                }
            } catch (PDOException $e) {
                $message = 'Database error: ' . $e->getMessage();
                $messageType = 'error';
            }
        } else {
            $message = 'Task title is required';
            $messageType = 'error';
        }
    }
}

// Get task data
try {
    $stmt = $pdo->prepare("SELECT * FROM tasks WHERE id = ?");
    $stmt->execute([$task_id]);
    $task = $stmt->fetch();

    if (!$task) {
        echo "<script>alert('Task not found'); window.close();</script>";
        exit;
    }
} catch (PDOException $e) {
    echo "<script>alert('Error loading task data'); window.close();</script>";
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Task - <?php echo htmlspecialchars($task['title']); ?></title>
    <link rel="stylesheet" href="../../assets/css/style.css">
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 0;
            padding: 20px;
            background: #f8f9fa;
        }
        .popup-container {
            background: white;
            border-radius: 8px;
            padding: 20px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .popup-header {
            border-bottom: 1px solid #dee2e6;
            padding-bottom: 15px;
            margin-bottom: 20px;
        }
        .popup-header h2 {
            margin: 0;
            color: #343a40;
            font-size: 1.25rem;
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: 500;
            color: #495057;
        }
        .form-control {
            width: 100%;
            padding: 8px 12px;
            border: 1px solid #ced4da;
            border-radius: 4px;
            font-size: 14px;
            box-sizing: border-box;
        }
        .form-control:focus {
            border-color: #007bff;
            outline: none;
            box-shadow: 0 0 0 2px rgba(0,123,255,0.25);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .checkbox-group {
            display: flex;
            align-items: center;
            margin-bottom: 10px;
        }
        .checkbox-group input[type="checkbox"] {
            margin-right: 8px;
        }
        .btn {
            padding: 8px 16px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
            text-decoration: none;
            display: inline-block;
            text-align: center;
        }
        .btn-primary {
            background: #007bff;
            color: white;
        }
        .btn-secondary {
            background: #6c757d;
            color: white;
        }
        .btn:hover {
            opacity: 0.9;
        }
        .button-group {
            display: flex;
            gap: 10px;
            justify-content: flex-end;
            margin-top: 20px;
            padding-top: 15px;
            border-top: 1px solid #dee2e6;
        }
        .alert {
            padding: 10px 15px;
            margin-bottom: 15px;
            border-radius: 4px;
        }
        .alert-success {
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
        }
        .alert-error {
            background: #f8d7da;
            color: #721c24;
            border: 1px solid #f5c6cb;
        }
        .required {
            color: #dc3545;
        }
        .task-id-display {
            background: #f8f9fa;
            padding: 8px 12px;
            border-radius: 4px;
            font-family: monospace;
            font-size: 14px;
            color: #6c757d;
            margin-bottom: 15px;
        }
    </style>
</head>
<body>
    <div class="popup-container">
        <div class="popup-header">
            <h2>Edit Task</h2>
            <?php if (!empty($task['task_identifier'])): ?>
                <div class="task-id-display">
                    Task ID: <?php echo htmlspecialchars($task['task_identifier']); ?>
                </div>
            <?php endif; ?>
        </div>

        <?php if ($message): ?>
            <div class="alert alert-<?php echo $messageType; ?>">
                <?php echo htmlspecialchars($message); ?>
            </div>
        <?php endif; ?>

        <form method="POST">
            <input type="hidden" name="action" value="update_task">

            <div class="form-group">
                <label for="title">Task Title <span class="required">*</span></label>
                <input type="text" id="title" name="title" class="form-control" required
                       value="<?php echo htmlspecialchars($task['title']); ?>">
            </div>

            <div class="form-group">
                <label for="description">Description</label>
                <textarea id="description" name="description" class="form-control" rows="4"><?php echo htmlspecialchars($task['description']); ?></textarea>
            </div>

            <div class="form-row">
                <div class="form-group">
                    <label for="priority">Priority</label>
                    <select id="priority" name="priority" class="form-control">
                        <option value="low" <?php echo $task['priority'] === 'low' ? 'selected' : ''; ?>>Low</option>
                        <option value="medium" <?php echo $task['priority'] === 'medium' ? 'selected' : ''; ?>>Medium</option>
                        <option value="high" <?php echo $task['priority'] === 'high' ? 'selected' : ''; ?>>High</option>
                    </select>
                </div>

                <div class="form-group">
                    <label for="due_date">Due Date</label>
                    <input type="date" id="due_date" name="due_date" class="form-control"
                           value="<?php echo $task['due_date']; ?>">
                </div>
            </div>

            <div class="form-group">
                <div class="checkbox-group">
                    <input type="checkbox" id="expense_required" name="expense_required"
                           <?php echo $task['expense_required'] ? 'checked' : ''; ?>
                           onchange="toggleBudgetField()">
                    <label for="expense_required">Expense Required</label>
                </div>

                <input type="number" id="budget_amount" name="budget_amount" class="form-control"
                       placeholder="Budget Amount (₹)" step="0.01"
                       value="<?php echo $task['budget_amount']; ?>"
                       <?php echo !$task['expense_required'] ? 'disabled' : ''; ?>>
            </div>

            <div class="button-group">
                <button type="button" class="btn btn-secondary" onclick="window.close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Update Task</button>
            </div>
        </form>
    </div>

    <script>
        function toggleBudgetField() {
            const checkbox = document.getElementById('expense_required');
            const budgetField = document.getElementById('budget_amount');
            budgetField.disabled = !checkbox.checked;
            if (!checkbox.checked) {
                budgetField.value = '';
            }
        }

        // Auto-close popup after successful update
        <?php if ($messageType === 'success'): ?>
            setTimeout(() => {
                window.close();
            }, 1500);
        <?php endif; ?>
    </script>
</body>
</html>
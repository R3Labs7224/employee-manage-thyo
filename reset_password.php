<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';
$success = false;
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_email = '';

// Validate token
if ($token) {
    try {
        $stmt = $pdo->prepare("SELECT email, expires_at, used FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();

        if (!$reset_data) {
            $message = '<div class="alert alert-error">Invalid or expired reset link. Please request a new password reset.</div>';
        } elseif ($reset_data['expires_at'] <= date('Y-m-d H:i:s')) {
            $message = '<div class="alert alert-error">This reset link has expired. Please request a new password reset.</div>';
        } elseif ($reset_data['used'] == 1) {
            $message = '<div class="alert alert-error">This reset link has already been used. Please request a new password reset.</div>';
        } else {
            $valid_token = true;
            $user_email = $reset_data['email'];
        }
    } catch (PDOException $e) {
        $message = '<div class="alert alert-error">Database error occurred.</div>';
    }
} else {
    $message = '<div class="alert alert-error">Invalid reset link.</div>';
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    if (empty($new_password) || empty($confirm_password)) {
        $message = '<div class="alert alert-error">Please fill in both password fields.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div class="alert alert-error">Password must be at least 6 characters long.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div class="alert alert-error">Passwords do not match.</div>';
    } else {
        try {
            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $stmt->execute([$hashed_password, $user_email]);

            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);

            $success = true;
            $message = '<div class="alert alert-success">Password has been reset successfully! You can now login with your new password.</div>';
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Error updating password. Please try again.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password - Employee Management System</title>
    <link rel="icon" type="image/png" href="assets/images/logo.png">
    <link rel="stylesheet" href="assets/css/style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        body {
            background: linear-gradient(135deg, #fb7185 0%, #f97316 100%);
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
        }
        .reset-container {
            background: white;
            padding: 2rem;
            border-radius: 12px;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.2);
            width: 100%;
            max-width: 400px;
        }
        .reset-header {
            text-align: center;
            margin-bottom: 2rem;
        }
        .reset-header h2 {
            color: #333;
            margin-bottom: 0.5rem;
        }
        .reset-header p {
            color: #666;
            font-size: 0.9rem;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            color: #333;
            font-weight: 500;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 6px;
            font-size: 1rem;
            transition: border-color 0.3s ease;
        }
        .form-control:focus {
            outline: none;
            border-color: #f97316;
            box-shadow: 0 0 0 3px rgba(249, 115, 22, 0.1);
        }
        .btn {
            width: 100%;
            padding: 0.75rem;
            background: #f97316;
            color: white;
            border: none;
            border-radius: 6px;
            font-size: 1rem;
            cursor: pointer;
            transition: background-color 0.3s ease;
        }
        .btn:hover {
            background: #ea580c;
        }
        .btn:disabled {
            background: #9ca3af;
            cursor: not-allowed;
        }
        .back-link {
            text-align: center;
            margin-top: 1rem;
        }
        .back-link a {
            color: #f97316;
            text-decoration: none;
            font-size: 0.9rem;
        }
        .back-link a:hover {
            text-decoration: underline;
        }
        .alert {
            padding: 0.75rem;
            border-radius: 6px;
            margin-bottom: 1rem;
        }
        .alert-success {
            background-color: #d1fae5;
            color: #065f46;
            border: 1px solid #a7f3d0;
        }
        .alert-error {
            background-color: #fee2e2;
            color: #991b1b;
            border: 1px solid #fca5a5;
        }
        .password-requirements {
            font-size: 0.8rem;
            color: #666;
            margin-top: 0.25rem;
        }
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h2><i class="fas fa-lock"></i> Reset Password</h2>
            <?php if ($valid_token && !$success): ?>
                <p>Enter your new password below</p>
            <?php endif; ?>
        </div>

        <?php echo $message; ?>

        <?php if ($valid_token && !$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                       placeholder="Enter new password" minlength="6">
                <div class="password-requirements">Minimum 6 characters required</div>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                       placeholder="Confirm new password" minlength="6">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-save"></i> Update Password
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>

    <script>
        // Password confirmation validation
        document.getElementById('confirm_password').addEventListener('input', function() {
            const password = document.getElementById('password').value;
            const confirmPassword = this.value;

            if (password !== confirmPassword) {
                this.setCustomValidity('Passwords do not match');
            } else {
                this.setCustomValidity('');
            }
        });

        document.getElementById('password').addEventListener('input', function() {
            const confirmPassword = document.getElementById('confirm_password');
            if (confirmPassword.value && this.value !== confirmPassword.value) {
                confirmPassword.setCustomValidity('Passwords do not match');
            } else {
                confirmPassword.setCustomValidity('');
            }
        });
    </script>
</body>
</html>
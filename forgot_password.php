<?php
require_once 'config/database.php';
require_once 'includes/functions.php';

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

    if (!$email) {
        $message = '<div class="alert alert-error">Please enter a valid email address.</div>';
    } else {
        try {
            // Check if user exists and is superadmin
            $stmt = $pdo->prepare("SELECT id, username as name, role, email FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            if (!$user) {
                $message = '<div class="alert alert-error">No account found with this email address.</div>';
            } elseif ($user['role'] !== 'superadmin') {
                $message = '<div class="alert alert-error">Password reset is only available for superadmin accounts.</div>';
            } else {
                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                // Store token in database
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
                $stmt->execute([$email, $token, $expires_at]);

                // Create reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $token;

                // Send email
                require_once 'includes/email_config.php';

                $to = $email;
                $subject = "Password Reset - Employee Management System";
                $message_body = "
Dear {$user['name']},

You have requested a password reset for your Employee Management System account.

Click the link below to reset your password:
$reset_link

This link will expire in 1 hour.

If you did not request this password reset, please ignore this email.

Best regards,
Employee Management System
                ";

                if (sendResetEmail($to, $subject, $message_body)) {
                    $success = true;
                    $message = '<div class="alert alert-success">Password reset link has been sent to your email address.</div>';
                } else {
                    $message = '<div class="alert alert-error">Failed to send email. Please contact system administrator.</div>';
                }
            }
        } catch (PDOException $e) {
            $message = '<div class="alert alert-error">Database error occurred. Please try again.</div>';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password - Employee Management System</title>
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
    </style>
</head>
<body>
    <div class="reset-container">
        <div class="reset-header">
            <h2><i class="fas fa-key"></i> Forgot Password</h2>
            <p>Enter your email address to receive a password reset link</p>
        </div>

        <?php echo $message; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       placeholder="Enter your email address" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>">
            </div>

            <button type="submit" class="btn">
                <i class="fas fa-paper-plane"></i> Send Reset Link
            </button>
        </form>
        <?php endif; ?>

        <div class="back-link">
            <a href="login.php">
                <i class="fas fa-arrow-left"></i> Back to Login
            </a>
        </div>
    </div>
</body>
</html>
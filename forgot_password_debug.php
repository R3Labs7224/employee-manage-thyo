<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';
require_once 'includes/email_config.php';

echo "<h2>Forgot Password Debug</h2>";

$message = '';
$success = false;

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = filter_var(trim($_POST['email']), FILTER_VALIDATE_EMAIL);

    echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Step 1: Email Validation</strong><br>";
    echo "Input Email: " . htmlspecialchars($_POST['email'] ?? 'not set') . "<br>";
    echo "Validated Email: " . htmlspecialchars($email ?: 'invalid') . "<br>";
    echo "</div>";

    if (!$email) {
        $message = '<div style="color: red;">Please enter a valid email address.</div>';
    } else {
        try {
            echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Step 2: Database Query</strong><br>";

            // Check if user exists and is superadmin
            $stmt = $pdo->prepare("SELECT id, username as name, role, email FROM users WHERE email = ? AND status = 'active'");
            $stmt->execute([$email]);
            $user = $stmt->fetch();

            echo "Query: SELECT id, username as name, role, email FROM users WHERE email = '$email' AND status = 'active'<br>";
            echo "User Found: " . ($user ? 'YES' : 'NO') . "<br>";
            if ($user) {
                echo "User ID: " . $user['id'] . "<br>";
                echo "User Name: " . $user['name'] . "<br>";
                echo "User Role: " . $user['role'] . "<br>";
                echo "User Email: " . $user['email'] . "<br>";
            }
            echo "</div>";

            if (!$user) {
                $message = '<div style="color: red;">No account found with this email address.</div>';
            } elseif ($user['role'] !== 'superadmin') {
                $message = '<div style="color: red;">Password reset is only available for superadmin accounts. Current role: ' . $user['role'] . '</div>';
            } else {
                echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Step 3: Generate Token</strong><br>";

                // Generate reset token
                $token = bin2hex(random_bytes(32));
                $expires_at = date('Y-m-d H:i:s', strtotime('+1 hour'));

                echo "Token: $token<br>";
                echo "Expires At: $expires_at<br>";
                echo "</div>";

                echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Step 4: Store Token</strong><br>";

                // Store token in database
                $stmt = $pdo->prepare("INSERT INTO password_reset_tokens (email, token, expires_at) VALUES (?, ?, ?)");
                $result = $stmt->execute([$email, $token, $expires_at]);

                echo "Insert Result: " . ($result ? 'SUCCESS' : 'FAILED') . "<br>";
                echo "</div>";

                echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Step 5: Create Reset Link</strong><br>";

                // Create reset link
                $reset_link = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['REQUEST_URI']) . "/reset_password.php?token=" . $token;

                echo "Reset Link: <a href='$reset_link'>$reset_link</a><br>";
                echo "</div>";

                echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Step 6: Prepare Email</strong><br>";

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

                echo "To: $to<br>";
                echo "Subject: $subject<br>";
                echo "Message Body:<br>";
                echo "<pre>" . htmlspecialchars($message_body) . "</pre>";
                echo "</div>";

                echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
                echo "<strong>Step 7: Send Email</strong><br>";
                echo "Calling sendResetEmail()...<br>";
                flush();

                $email_result = sendResetEmail($to, $subject, $message_body);

                echo "Email Send Result: " . ($email_result ? 'SUCCESS ✓' : 'FAILED ✗') . "<br>";
                echo "</div>";

                if ($email_result) {
                    $success = true;
                    $message = '<div style="color: green; background: #d1fae5; padding: 10px;">Password reset link has been sent to your email address.</div>';
                } else {
                    $message = '<div style="color: red; background: #fee2e2; padding: 10px;">Failed to send email. Check the debug output above.</div>';

                    // Check PHP error log
                    echo "<div style='background: #fef3c7; padding: 10px; margin: 10px 0;'>";
                    echo "<strong>Error Log Location:</strong><br>";
                    echo "Error Log: " . ini_get('error_log') . "<br>";
                    echo "</div>";
                }
            }
        } catch (PDOException $e) {
            $message = '<div style="color: red;">Database error: ' . $e->getMessage() . '</div>';
            echo "<div style='background: #fee2e2; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Database Error:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        } catch (Exception $e) {
            $message = '<div style="color: red;">Error: ' . $e->getMessage() . '</div>';
            echo "<div style='background: #fee2e2; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Error:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Forgot Password Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 800px;
            margin: 0 auto;
            background: white;
            padding: 20px;
            border-radius: 8px;
        }
        .form-group {
            margin-bottom: 1rem;
        }
        .form-group label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: bold;
        }
        .form-control {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        .btn {
            padding: 0.75rem 1.5rem;
            background: #f97316;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
        }
        .btn:hover {
            background: #ea580c;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Forgot Password - Debug Mode</h1>

        <?php echo $message; ?>

        <?php if (!$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="email">Email Address</label>
                <input type="email" id="email" name="email" class="form-control" required
                       placeholder="Enter your email address" value="<?php echo htmlspecialchars($_POST['email'] ?? 'rabiranjan7224@gmail.com'); ?>">
            </div>

            <button type="submit" class="btn">Send Reset Link</button>
        </form>
        <?php endif; ?>

        <p><a href="forgot_password.php">← Back to Forgot Password</a></p>
    </div>
</body>
</html>

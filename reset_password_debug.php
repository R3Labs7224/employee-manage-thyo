<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

require_once 'config/database.php';
require_once 'includes/functions.php';

echo "<h2>Reset Password Debug</h2>";

$message = '';
$success = false;
$token = $_GET['token'] ?? '';
$valid_token = false;
$user_email = '';

echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
echo "<strong>Step 1: Token from URL</strong><br>";
echo "Token: " . htmlspecialchars($token ?: 'NOT PROVIDED') . "<br>";
echo "Token Length: " . strlen($token) . " characters<br>";
echo "</div>";

// Validate token
if ($token) {
    try {
        echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Step 2: Database Query</strong><br>";

        $stmt = $pdo->prepare("SELECT email, expires_at, used, created_at FROM password_reset_tokens WHERE token = ?");
        $stmt->execute([$token]);
        $reset_data = $stmt->fetch();

        echo "Query: SELECT email, expires_at, used, created_at FROM password_reset_tokens WHERE token = '$token'<br>";
        echo "Result Found: " . ($reset_data ? 'YES' : 'NO') . "<br>";

        if ($reset_data) {
            echo "<strong>Token Details:</strong><br>";
            echo "Email: " . htmlspecialchars($reset_data['email']) . "<br>";
            echo "Expires At: " . htmlspecialchars($reset_data['expires_at']) . "<br>";
            echo "Used: " . ($reset_data['used'] ? 'YES' : 'NO') . "<br>";
            echo "Created At: " . htmlspecialchars($reset_data['created_at']) . "<br>";
            echo "<br>";

            $now = date('Y-m-d H:i:s');
            echo "Current Time: $now<br>";
            echo "Is Expired: " . ($reset_data['expires_at'] < $now ? 'YES (EXPIRED)' : 'NO (VALID)') . "<br>";
            echo "Is Used: " . ($reset_data['used'] ? 'YES (ALREADY USED)' : 'NO (NOT USED)') . "<br>";
        }
        echo "</div>";

        // Check all conditions
        if (!$reset_data) {
            $message = '<div style="color: red; background: #fee2e2; padding: 10px;">Token not found in database. It may have been deleted.</div>';
        } elseif ($reset_data['expires_at'] <= date('Y-m-d H:i:s')) {
            $message = '<div style="color: red; background: #fee2e2; padding: 10px;">Token has expired. It expired at ' . htmlspecialchars($reset_data['expires_at']) . '</div>';
        } elseif ($reset_data['used'] == 1) {
            $message = '<div style="color: red; background: #fee2e2; padding: 10px;">Token has already been used.</div>';
        } else {
            $valid_token = true;
            $user_email = $reset_data['email'];
            $message = '<div style="color: green; background: #d1fae5; padding: 10px;">✓ Token is valid! You can reset your password.</div>';
        }
    } catch (PDOException $e) {
        $message = '<div style="color: red; background: #fee2e2; padding: 10px;">Database error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        echo "<div style='background: #fee2e2; padding: 10px; margin: 10px 0;'>";
        echo "<strong>Database Error:</strong><br>";
        echo htmlspecialchars($e->getMessage());
        echo "</div>";
    }
} else {
    $message = '<div style="color: red; background: #fee2e2; padding: 10px;">No token provided in URL.</div>';
}

// Process password reset
if ($_SERVER['REQUEST_METHOD'] == 'POST' && $valid_token) {
    $new_password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
    echo "<strong>Step 3: Password Reset Form Submitted</strong><br>";
    echo "New Password Length: " . strlen($new_password) . " characters<br>";
    echo "Passwords Match: " . ($new_password === $confirm_password ? 'YES' : 'NO') . "<br>";
    echo "</div>";

    if (empty($new_password) || empty($confirm_password)) {
        $message = '<div style="color: red;">Please fill in both password fields.</div>';
    } elseif (strlen($new_password) < 6) {
        $message = '<div style="color: red;">Password must be at least 6 characters long.</div>';
    } elseif ($new_password !== $confirm_password) {
        $message = '<div style="color: red;">Passwords do not match.</div>';
    } else {
        try {
            echo "<div style='background: #e3f2fd; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Step 4: Updating Password</strong><br>";

            // Update password
            $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
            echo "Password hashed successfully<br>";

            $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
            $result = $stmt->execute([$hashed_password, $user_email]);

            echo "Update Query: UPDATE users SET password = '[HASHED]' WHERE email = '$user_email'<br>";
            echo "Rows Affected: " . $stmt->rowCount() . "<br>";

            if ($stmt->rowCount() == 0) {
                echo "<div style='color: orange;'>⚠ Warning: No rows updated. User may not exist with email: $user_email</div>";
            }

            // Mark token as used
            $stmt = $pdo->prepare("UPDATE password_reset_tokens SET used = 1 WHERE token = ?");
            $stmt->execute([$token]);
            echo "Token marked as used<br>";

            echo "</div>";

            $success = true;
            $message = '<div style="color: green; background: #d1fae5; padding: 10px; margin: 20px 0; font-size: 1.1em;">✓ Password has been reset successfully! You can now <a href="login.php">login</a> with your new password.</div>';
        } catch (PDOException $e) {
            $message = '<div style="color: red;">Error updating password: ' . htmlspecialchars($e->getMessage()) . '</div>';
            echo "<div style='background: #fee2e2; padding: 10px; margin: 10px 0;'>";
            echo "<strong>Database Error:</strong><br>";
            echo htmlspecialchars($e->getMessage());
            echo "</div>";
        }
    }
}

// Show all tokens for debugging
echo "<div style='background: #fef3c7; padding: 10px; margin: 20px 0;'>";
echo "<strong>Debug: All Password Reset Tokens</strong><br>";
try {
    $stmt = $pdo->query("SELECT id, email, LEFT(token, 20) as token_preview, expires_at, used, created_at FROM password_reset_tokens ORDER BY created_at DESC LIMIT 10");
    $all_tokens = $stmt->fetchAll();

    if (count($all_tokens) > 0) {
        echo "<table border='1' cellpadding='5' style='margin-top: 10px; width: 100%; border-collapse: collapse;'>";
        echo "<tr><th>ID</th><th>Email</th><th>Token Preview</th><th>Expires At</th><th>Used</th><th>Created At</th></tr>";
        foreach ($all_tokens as $t) {
            $is_expired = $t['expires_at'] < date('Y-m-d H:i:s');
            $row_color = $is_expired ? '#fee2e2' : ($t['used'] ? '#fef3c7' : '#d1fae5');
            echo "<tr style='background: $row_color;'>";
            echo "<td>" . $t['id'] . "</td>";
            echo "<td>" . htmlspecialchars($t['email']) . "</td>";
            echo "<td>" . htmlspecialchars($t['token_preview']) . "...</td>";
            echo "<td>" . htmlspecialchars($t['expires_at']) . ($is_expired ? ' <strong>(EXPIRED)</strong>' : '') . "</td>";
            echo "<td>" . ($t['used'] ? 'YES' : 'NO') . "</td>";
            echo "<td>" . htmlspecialchars($t['created_at']) . "</td>";
            echo "</tr>";
        }
        echo "</table>";
    } else {
        echo "No tokens found in database.";
    }
} catch (PDOException $e) {
    echo "Error: " . htmlspecialchars($e->getMessage());
}
echo "</div>";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reset Password Debug</title>
    <style>
        body {
            font-family: Arial, sans-serif;
            margin: 20px;
            background: #f5f5f5;
        }
        .container {
            max-width: 900px;
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
        table {
            font-size: 0.9em;
        }
    </style>
</head>
<body>
    <div class="container">
        <h1>Reset Password - Debug Mode</h1>

        <?php echo $message; ?>

        <?php if ($valid_token && !$success): ?>
        <form method="POST" action="">
            <div class="form-group">
                <label for="password">New Password</label>
                <input type="password" id="password" name="password" class="form-control" required
                       placeholder="Enter new password" minlength="6">
                <small>Minimum 6 characters required</small>
            </div>

            <div class="form-group">
                <label for="confirm_password">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="form-control" required
                       placeholder="Confirm new password" minlength="6">
            </div>

            <button type="submit" class="btn">Update Password</button>
        </form>
        <?php endif; ?>

        <p style="margin-top: 20px;">
            <a href="forgot_password.php">← Request New Reset Link</a> |
            <a href="login.php">Login</a>
        </p>
    </div>
</body>
</html>

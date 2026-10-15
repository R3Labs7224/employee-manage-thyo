<?php
echo "<h2>Server Timezone Configuration Check</h2>";
echo "<hr>";

echo "<h3>PHP Timezone Settings:</h3>";
echo "Server Timezone: " . date_default_timezone_get() . "<br>";
echo "Server Date: " . date('Y-m-d') . "<br>";
echo "Server Time: " . date('Y-m-d H:i:s') . "<br>";
echo "Unix Timestamp: " . time() . "<br>";
echo "MySQL Timezone (from php.ini): " . ini_get('date.timezone') . "<br>";

echo "<hr>";
echo "<h3>Database Timezone Settings:</h3>";

// Check database timezone
require_once 'config/database.php';

try {
    // Check MySQL timezone
    $stmt = $pdo->query("SELECT @@global.time_zone as global_tz, @@session.time_zone as session_tz");
    $tz = $stmt->fetch();
    echo "MySQL Global Timezone: " . $tz['global_tz'] . "<br>";
    echo "MySQL Session Timezone: " . $tz['session_tz'] . "<br>";

    echo "<hr>";
    echo "<h3>Current Time Comparison:</h3>";

    // Get current time from MySQL
    $stmt = $pdo->query("SELECT NOW() as mysql_now, CURRENT_TIMESTAMP as mysql_timestamp, UTC_TIMESTAMP() as mysql_utc");
    $times = $stmt->fetch();
    echo "MySQL NOW(): " . $times['mysql_now'] . "<br>";
    echo "MySQL CURRENT_TIMESTAMP: " . $times['mysql_timestamp'] . "<br>";
    echo "MySQL UTC_TIMESTAMP: " . $times['mysql_utc'] . "<br>";
    echo "PHP date('Y-m-d H:i:s'): " . date('Y-m-d H:i:s') . "<br>";

    echo "<hr>";
    echo "<h3>Recent Attendance Records (Last 5):</h3>";

    // Check recent attendance records
    $stmt = $pdo->query("
        SELECT a.id, a.employee_id, e.name, a.date, a.created_at, a.updated_at
        FROM attendance a
        LEFT JOIN employees e ON a.employee_id = e.id
        ORDER BY a.id DESC
        LIMIT 5
    ");
    $records = $stmt->fetchAll();

    if (!empty($records)) {
        echo "<table border='1' cellpadding='5' cellspacing='0'>";
        echo "<tr>
                <th>ID</th>
                <th>Employee</th>
                <th>Date (stored)</th>
                <th>Created At (timestamp)</th>
                <th>Date Match?</th>
              </tr>";

        foreach ($records as $record) {
            $stored_date = $record['date'];
            $created_date = date('Y-m-d', strtotime($record['created_at']));
            $match = ($stored_date === $created_date) ? '✓ Match' : '✗ Mismatch';
            $color = ($stored_date === $created_date) ? 'green' : 'red';

            echo "<tr>
                    <td>{$record['id']}</td>
                    <td>{$record['name']}</td>
                    <td>{$stored_date}</td>
                    <td>{$record['created_at']}</td>
                    <td style='color: {$color}; font-weight: bold;'>{$match}</td>
                  </tr>";
        }
        echo "</table>";
    } else {
        echo "No attendance records found.";
    }

    echo "<hr>";
    echo "<h3>Timezone Recommendations:</h3>";
    echo "<p><strong>If you see timezone mismatches:</strong></p>";
    echo "<ol>";
    echo "<li>Set PHP timezone in config/database.php by adding: <code>date_default_timezone_set('Asia/Kolkata');</code></li>";
    echo "<li>Set MySQL timezone by adding after PDO connection: <code>\$pdo->exec(\"SET time_zone = '+05:30'\");</code></li>";
    echo "<li>Common timezones: Asia/Kolkata (India), Asia/Dubai (UAE), America/New_York (EST)</li>";
    echo "</ol>";

} catch (PDOException $e) {
    echo "Database Error: " . $e->getMessage();
}

echo "<hr>";
echo "<p><em>Generated at: " . date('Y-m-d H:i:s') . "</em></p>";
?>

<?php
error_reporting(E_ALL);
ini_set('display_errors', 1);

echo "<h2>Debug Salary Query Step by Step</h2>";

try {
    require_once 'config/database.php';
    
    $employee_id = 1; // Rabi Ranjan's ID
    $year = 2025;
    $limit = 12;
    
    echo "<h3>Step 1: Test Basic Query</h3>";
    
    // Start with simplest query
    $sql = "SELECT COUNT(*) as count FROM salaries WHERE employee_id = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $result = $stmt->fetch();
    echo "✅ Basic count query: Found {$result['count']} salary records<br><br>";
    
    echo "<h3>Step 2: Test JOIN Query</h3>";
    
    $sql = "
        SELECT s.id, s.month, s.year, s.net_salary, e.name 
        FROM salaries s 
        JOIN employees e ON s.employee_id = e.id 
        WHERE s.employee_id = ?
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$employee_id]);
    $results = $stmt->fetchAll();
    echo "✅ JOIN query: Found " . count($results) . " records<br>";
    
    if (count($results) > 0) {
        foreach ($results as $row) {
            echo "- {$row['name']}: {$row['month']}/{$row['year']} = {$row['net_salary']}<br>";
        }
    }
    echo "<br>";
    
    echo "<h3>Step 3: Test Full Query with Parameters</h3>";
    
    // Build query exactly like in API
    $sql = "
        SELECT s.*, 
               e.name as employee_name,
               e.employee_code,
               e.basic_salary as current_basic_salary,
               COALESCE(d.name, 'No Department') as department_name
        FROM salaries s
        JOIN employees e ON s.employee_id = e.id
        LEFT JOIN departments d ON e.department_id = d.id
        WHERE s.employee_id = ?
    ";
    
    $params = [$employee_id];
    
    if ($year > 0) {
        $sql .= " AND s.year = ?";
        $params[] = $year;
    }
    
    $sql .= " ORDER BY s.year DESC, s.month DESC LIMIT " . (int)$limit;
    
    echo "<strong>Final SQL:</strong><br>";
    echo "<code>" . str_replace('?', '<span style=\"color: red;\">?</span>', htmlspecialchars($sql)) . "</code><br><br>";
    
    echo "<strong>Parameters:</strong><br>";
    foreach ($params as $i => $param) {
        echo "Param " . ($i + 1) . ": $param<br>";
    }
    echo "<br>";
    
    // Count question marks in SQL
    $question_marks = substr_count($sql, '?');
    $param_count = count($params);
    
    echo "<strong>Parameter Check:</strong><br>";
    echo "Question marks in SQL: $question_marks<br>";
    echo "Parameters provided: $param_count<br>";
    
    if ($question_marks === $param_count) {
        echo "✅ Parameter count matches!<br><br>";
        
        echo "<h3>Step 4: Execute Full Query</h3>";
        $stmt = $pdo->prepare($sql);
        $stmt->execute($params);
        $salary_slips = $stmt->fetchAll();
        
        echo "✅ Query executed successfully!<br>";
        echo "Records found: " . count($salary_slips) . "<br><br>";
        
        if (count($salary_slips) > 0) {
            echo "<table border='1' style='border-collapse: collapse;'>";
            echo "<tr><th>ID</th><th>Employee</th><th>Month/Year</th><th>Net Salary</th><th>Status</th></tr>";
            foreach ($salary_slips as $slip) {
                echo "<tr>";
                echo "<td>{$slip['id']}</td>";
                echo "<td>{$slip['employee_name']}</td>";
                echo "<td>{$slip['month']}/{$slip['year']}</td>";
                echo "<td>" . number_format($slip['net_salary'], 2) . "</td>";
                echo "<td>{$slip['status']}</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
        
    } else {
        echo "❌ Parameter count mismatch!<br>";
        echo "This is the cause of the SQLSTATE[HY093] error.<br>";
    }
    
} catch (PDOException $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ <strong>PDO Error:</strong> " . $e->getMessage() . "<br>";
    echo "<strong>Error Code:</strong> " . $e->getCode() . "<br>";
    echo "</div>";
} catch (Exception $e) {
    echo "<div style='background: #f8d7da; padding: 10px; border-radius: 5px;'>";
    echo "❌ <strong>General Error:</strong> " . $e->getMessage();
    echo "</div>";
}
?>
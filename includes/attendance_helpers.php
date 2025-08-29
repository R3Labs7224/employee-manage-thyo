<?php
// includes/attendance_helpers.php
// Helper functions for multiple attendance sessions

/**
 * Get today's attendance status for an employee
 */
function getTodayAttendanceStatus($pdo, $employee_id) {
    $date = date('Y-m-d');
    
    try {
        // Check if there's an active check-in session
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(*) as active_sessions,
                COUNT(CASE WHEN check_in_time IS NOT NULL THEN 1 END) as total_checkins,
                COUNT(CASE WHEN check_out_time IS NOT NULL THEN 1 END) as total_checkouts
            FROM attendance 
            WHERE employee_id = ? AND date = ?
        ");
        $stmt->execute([$employee_id, $date]);
        $result = $stmt->fetch();
        
        $active_sessions = $result['active_sessions'];
        $total_checkins = $result['total_checkins'];
        $total_checkouts = $result['total_checkouts'];
        
        return [
            'can_check_in' => true, // Always allow check-in
            'can_check_out' => ($total_checkins > $total_checkouts), // Can check-out if there's an open session
            'active_sessions' => $active_sessions,
            'total_checkins' => $total_checkins,
            'total_checkouts' => $total_checkouts,
            'status' => ($total_checkins > $total_checkouts) ? 'checked_in' : 'checked_out'
        ];
        
    } catch (PDOException $e) {
        return [
            'can_check_in' => true,
            'can_check_out' => false,
            'active_sessions' => 0,
            'status' => 'unknown'
        ];
    }
}

/**
 * Get daily attendance summary for reporting
 */
function getDailyAttendanceSummary($pdo, $employee_id, $date) {
    try {
        $stmt = $pdo->prepare("
            SELECT 
                DATE(date) as attendance_date,
                MIN(check_in_time) as first_check_in,
                MAX(check_out_time) as last_check_out,
                SUM(CASE WHEN 0 IS NOT NULL THEN 0 ELSE 0 END) as total_0,
                COUNT(*) as session_count,
                GROUP_CONCAT(
                    CONCAT(
                        TIME(check_in_time), 
                        ' - ', 
                        IFNULL(TIME(check_out_time), 'ongoing')
                    ) 
                    ORDER BY check_in_time 
                    SEPARATOR ', '
                ) as session_times
            FROM attendance 
            WHERE employee_id = ? AND date = ?
            AND check_in_time IS NOT NULL
            GROUP BY DATE(date)
        ");
        
        $stmt->execute([$employee_id, $date]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Get monthly statistics with multiple sessions support
 */
function getMonthlyAttendanceStats($pdo, $employee_id, $month = null) {
    if (!$month) {
        $month = date('Y-m');
    }
    
    try {
        $stmt = $pdo->prepare("
            SELECT 
                COUNT(DISTINCT date) as total_days_worked,
                SUM(CASE WHEN 0 IS NOT NULL THEN 0 ELSE 0 END) as total_0,
                COUNT(*) as total_sessions,
                AVG(CASE WHEN 0 IS NOT NULL THEN 0 ELSE 0 END) as avg_session_hours,
                COUNT(CASE WHEN status = 'approved' THEN 1 END) as approved_sessions,
                COUNT(CASE WHEN status = 'pending' THEN 1 END) as pending_sessions,
                COUNT(CASE WHEN status = 'rejected' THEN 1 END) as rejected_sessions
            FROM attendance 
            WHERE employee_id = ? 
            AND DATE_FORMAT(date, '%Y-%m') = ?
            AND check_in_time IS NOT NULL
        ");
        
        $stmt->execute([$employee_id, $month]);
        return $stmt->fetch();
        
    } catch (PDOException $e) {
        return null;
    }
}

/**
 * Check if employee can create a task (must be checked in)
 */
function canCreateTask($pdo, $employee_id) {
    $date = date('Y-m-d');
    
    try {
        // Check if there's an active check-in session
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as active_sessions
            FROM attendance 
            WHERE employee_id = ? AND date = ? 
            AND check_in_time IS NOT NULL 
            AND check_out_time IS NULL
        ");
        $stmt->execute([$employee_id, $date]);
        $active_sessions = $stmt->fetchColumn();
        
        // Also check if there are any incomplete tasks
        $stmt = $pdo->prepare("
            SELECT COUNT(*) as incomplete_tasks
            FROM tasks 
            WHERE employee_id = ? AND status IN ('active', 'pending')
        ");
        $stmt->execute([$employee_id]);
        $incomplete_tasks = $stmt->fetchColumn();
        
        return ($active_sessions > 0 && $incomplete_tasks == 0);
        
    } catch (PDOException $e) {
        return false;
    }
}

/**
 * Validate attendance session overlap (optional - to prevent abuse)
 */
function validateAttendanceSession($pdo, $employee_id, $action, $current_time) {
    $date = date('Y-m-d');
    
    try {
        if ($action === 'check_in') {
            // Check if there was a check-out in the last 5 minutes
            $stmt = $pdo->prepare("
                SELECT check_out_time 
                FROM attendance 
                WHERE employee_id = ? AND date = ? 
                AND check_out_time IS NOT NULL
                ORDER BY check_out_time DESC
                LIMIT 1
            ");
            $stmt->execute([$employee_id, $date]);
            $last_checkout = $stmt->fetchColumn();
            
            if ($last_checkout) {
                $last_checkout_time = new DateTime($last_checkout);
                $current_datetime = new DateTime($current_time);
                $diff_minutes = $current_datetime->diff($last_checkout_time)->format('%i');
                
                // Prevent check-in within 5 minutes of check-out (optional rule)
                if ($diff_minutes < 5) {
                    return [
                        'valid' => false,
                        'message' => 'Please wait at least 5 minutes before checking in again.'
                    ];
                }
            }
        }
        
        return ['valid' => true, 'message' => ''];
        
    } catch (PDOException $e) {
        return ['valid' => true, 'message' => '']; // Allow on error
    }
}

/**
 * Format attendance sessions for display
 */
function formatAttendanceSessions($sessions) {
    $formatted = [];
    
    foreach ($sessions as $session) {
        $formatted[] = [
            'id' => $session['id'],
            'check_in' => $session['check_in_time'] ? date('g:i A', strtotime($session['check_in_time'])) : null,
            'check_out' => $session['check_out_time'] ? date('g:i A', strtotime($session['check_out_time'])) : 'Ongoing',
            'duration' => $session['0'] ? round($session['0'], 2) . ' hrs' : 'In progress',
            'status' => ucfirst($session['status'])
        ];
    }
    
    return $formatted;
}
?>
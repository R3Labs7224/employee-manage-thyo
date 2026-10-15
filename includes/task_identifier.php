<?php
/**
 * Task Identifier Generator
 * Format: First 3 chars from task - First 3 chars from site - 4-digit number (0001, 0002, etc.)
 * Example: "Visit to KK" at "Ranipatna" -> VIS-RAN-0001
 */

/**
 * Get first 3 characters from first word only
 */
function getInitials($text, $count = 3) {
    // Clean text and convert to uppercase
    $text = strtoupper(trim($text));

    // Split by spaces and get the first word only
    $words = preg_split('/\s+/', $text);
    $firstWord = $words[0] ?? '';

    // Take only first 3 characters of the first word (or available characters if less than 3)
    return substr($firstWord, 0, $count);
}

/**
 * Get next sequence number for a specific task+site combination
 */
function getNextSequenceNumber($pdo, $taskInitials, $siteInitials) {
    try {
        // Find the highest sequence number for this site (regardless of task type)
        // Pattern: ANY-SITE-#### where SITE matches our site initials
        $sitePattern = '%-' . $siteInitials . '-%';

        $stmt = $pdo->prepare("
            SELECT task_identifier
            FROM tasks
            WHERE task_identifier LIKE ?
            ORDER BY CAST(SUBSTRING_INDEX(task_identifier, '-', -1) AS UNSIGNED) DESC
            LIMIT 1
        ");
        $stmt->execute([$sitePattern]);
        $result = $stmt->fetch();

        if (!$result) {
            return 1; // First task at this site
        }

        // Extract the number from the identifier (last 4 digits after the last dash)
        $identifier = $result['task_identifier'];
        $parts = explode('-', $identifier);
        $numberPart = end($parts); // Get the last part (the number)
        $currentNumber = intval($numberPart);

        return $currentNumber + 1;

    } catch (Exception $e) {
        error_log("Error getting sequence number: " . $e->getMessage());
        return 1;
    }
}

/**
 * Generate task identifier
 */
function generateTaskIdentifier($pdo, $title, $siteName) {
    try {
        // Get first 3 initials from task title
        $taskInitials = getInitials($title, 3);

        // Get first 3 initials from site name
        $siteInitials = getInitials($siteName, 3);

        // Get next sequence number
        $sequenceNumber = getNextSequenceNumber($pdo, $taskInitials, $siteInitials);

        // Format the identifier: TASK(3) - SITE(3) - NUMBER(4)
        $identifier = $taskInitials . '-' . $siteInitials . '-' . sprintf('%04d', $sequenceNumber);

        // Double-check uniqueness
        $attempts = 0;
        while ($attempts < 10) {
            $stmt = $pdo->prepare("SELECT id FROM tasks WHERE task_identifier = ?");
            $stmt->execute([$identifier]);

            if (!$stmt->fetch()) {
                break; // Identifier is unique
            }

            // If exists, increment number
            $sequenceNumber++;
            $identifier = $taskInitials . '-' . $siteInitials . '-' . sprintf('%04d', $sequenceNumber);
            $attempts++;
        }

        return $identifier;

    } catch (Exception $e) {
        error_log("Error generating task identifier: " . $e->getMessage());
        return null;
    }
}

/**
 * Get site name by ID
 */
function getSiteNameById($pdo, $siteId) {
    try {
        $stmt = $pdo->prepare("SELECT name FROM sites WHERE id = ?");
        $stmt->execute([$siteId]);
        $result = $stmt->fetch();

        return $result ? $result['name'] : 'Unknown';
    } catch (Exception $e) {
        error_log("Error getting site name: " . $e->getMessage());
        return 'Unknown';
    }
}

/**
 * Assign task identifier to a task
 */
function assignTaskIdentifier($pdo, $taskId, $title, $siteId) {
    try {
        // Get site name
        $siteName = getSiteNameById($pdo, $siteId);

        // Generate identifier
        $identifier = generateTaskIdentifier($pdo, $title, $siteName);

        if ($identifier) {
            // Update the task with the identifier
            $stmt = $pdo->prepare("UPDATE tasks SET task_identifier = ? WHERE id = ?");
            $stmt->execute([$identifier, $taskId]);
        }

        return $identifier;
    } catch (Exception $e) {
        error_log("Error assigning task identifier: " . $e->getMessage());
        return null;
    }
}

/**
 * Preview identifier (for testing)
 */
function previewTaskIdentifier($title, $siteName) {
    $taskInitials = getInitials($title, 3);
    $siteInitials = getInitials($siteName, 3);

    return [
        'task_initials' => $taskInitials,
        'site_initials' => $siteInitials,
        'preview' => $taskInitials . '-' . $siteInitials . '-0001'
    ];
}
?>
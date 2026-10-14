<?php
require_once __DIR__ . '/../config/database.php';

/**
 * Generates standardized Ticket Number: TT-YYYY-XXXX (e.g., TT-2026-0015)
 * Fixes the legacy TK/TT formatting mismatch.
 */
function generate_ticket_number(): string {
    $db = Database::getConnection();
    $year = date('Y');
    
    $stmt = $db->prepare("SELECT COUNT(*) as count FROM tickets WHERE ticket_number LIKE :prefix");
    $stmt->execute([':prefix' => "TT-{$year}-%"]);
    $row = $stmt->fetch();
    
    $nextSeq = str_pad(($row['count'] ?? 0) + 1, 4, '0', STR_PAD_LEFT);
    return "TT-{$year}-{$nextSeq}";
}

/**
 * Calculates shift based on trip time
 */
function calculate_shift(string $timeString): string {
    $time = strtotime($timeString);
    $hour = (int)date('H', $time);

    if ($hour >= 6 && $hour < 14) {
        return '7AM Incoming';
    } elseif ($hour >= 14 && $hour < 22) {
        return '3PM Outgoing';
    } else {
        return '7PM Outgoing';
    }
}

/**
 * Logs immutable actions to activity_logs table
 */
function log_activity(int $userId, string $actionType, ?string $ticketNumber = null, ?string $details = null): void {
    try {
        $db = Database::getConnection();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action_type, ticket_number, details) VALUES (:user_id, :action, :ticket, :details)");
        $stmt->execute([
            ':user_id' => $userId,
            ':action'  => $actionType,
            ':ticket'  => $ticketNumber,
            ':details' => $details
        ]);
    } catch (Exception $e) {
        // Silent catch or fallback to log file
        error_log("Activity log failure: " . $e->getMessage());
    }
}

/**
 * Sanitizes input string for safer rendering
 */
function sanitize(string $input): string {
    return htmlspecialchars(trim($input), ENT_QUOTES, 'UTF-8');
}
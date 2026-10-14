<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['admin']);

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT a.created_at, COALESCE(u.username, a.username_snapshot, 'Deleted account') AS username,
                               a.action_type, a.ticket_number, a.details
                        FROM activity_logs a
                        LEFT JOIN users u ON a.user_id = u.id
                        ORDER BY a.created_at DESC");

    $filename = 'sdpbus_activity_log_' . date('Ymd_His') . '.csv';
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Timestamp (UTC)', 'User', 'Action', 'Ticket Number', 'Details']);

    $safeCsvValue = static function ($value): string {
        $value = (string)($value ?? '');
        return preg_match('/^[\s]*[=+@-]/', $value) === 1 ? "'" . $value : $value;
    };

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $safeCsvValue($row['created_at']),
            $safeCsvValue($row['username'] ?? 'System'),
            $safeCsvValue($row['action_type']),
            $safeCsvValue($row['ticket_number']),
            $safeCsvValue($row['details'])
        ]);
    }

    fclose($output);
} catch (Throwable $e) {
    error_log('Activity Log Export Error: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Unable to export the activity log.';
}
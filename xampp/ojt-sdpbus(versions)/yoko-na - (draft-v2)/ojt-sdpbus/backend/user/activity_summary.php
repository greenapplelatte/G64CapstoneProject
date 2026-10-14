<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['admin']);
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();
    $stmt = $db->query("SELECT COALESCE(u.username, 'System') AS username,
                               u.profile_image,
                               COUNT(a.id) AS activity_count
                        FROM activity_logs a
                        LEFT JOIN users u ON a.user_id = u.id
                        GROUP BY a.user_id, u.username, u.profile_image
                        ORDER BY activity_count DESC, username COLLATE NOCASE ASC
                        LIMIT 4");

    $users = array_map(static function (array $user): array {
        return [
            'username' => (string)$user['username'],
            'profile_image' => $user['profile_image'],
            'activity_count' => (int)$user['activity_count']
        ];
    }, $stmt->fetchAll(PDO::FETCH_ASSOC));

    $latestActivityId = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();

    echo json_encode([
        'users' => $users,
        'latest_activity_id' => $latestActivityId
    ]);
} catch (Throwable $e) {
    error_log('Activity Summary Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to load activity summary.']);
}

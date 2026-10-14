<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['admin']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'error' => 'POST is required.']);
    exit;
}

$submittedToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['activity_clear_token'] ?? '');
if ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'error' => 'The confirmation expired. Refresh the page and try again.']);
    exit;
}

$db = Database::getConnection();
try {
    $db->beginTransaction();
    $db->exec('DELETE FROM activity_logs');
    $stmt = $db->prepare("DELETE FROM sqlite_sequence WHERE name = 'activity_logs'");
    $stmt->execute();
    $db->commit();

    unset($_SESSION['activity_clear_token']);
    echo json_encode(['success' => true]);
} catch (Throwable $e) {
    if ($db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Clear Activity Log Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['success' => false, 'error' => 'Unable to clear the activity log. The CSV download has started.']);
}
<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['staff', 'admin']);
header('Content-Type: application/json; charset=utf-8');

try {
    $db = Database::getConnection();
    $stmt = $db->prepare('SELECT last_login, force_password_change FROM users WHERE id = :id');
    $stmt->execute([':id' => (int)$_SESSION['user_id']]);
    $userData = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$userData) {
        http_response_code(404);
        echo json_encode(['error' => 'Account not found.']);
        exit;
    }

    echo json_encode([
        'account_status' => 'Active',
        'last_login' => $userData['last_login'],
        'force_password_change' => (bool)$userData['force_password_change']
    ]);
} catch (Throwable $e) {
    error_log('Profile Status Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to refresh profile status.']);
}
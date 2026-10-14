<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['admin']);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate');

try {
    $db = Database::getConnection();
    $users = $db->query('SELECT id, last_login FROM users ORDER BY id')->fetchAll(PDO::FETCH_ASSOC);
    echo json_encode(['users' => $users], JSON_THROW_ON_ERROR);
} catch (Throwable $e) {
    error_log('Last Login Refresh Error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode(['error' => 'Unable to refresh last login times.']);
}
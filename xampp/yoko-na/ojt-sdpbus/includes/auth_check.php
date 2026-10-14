<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/**
 * Validates session user authentication and role authorization
 * @param array $allowedRoles Array of allowed roles e.g. ['admin'] or ['staff', 'admin']
 */
function require_auth(array $allowedRoles = []): void {
    if (!isset($_SESSION['user_id'])) {
        header("Location: /xampp/yoko-na/ojt-sdpbus/login.php");
        exit;
    }

    require_once __DIR__ . '/../config/database.php';
    $db = Database::getConnection();
    $userCheck = $db->prepare('SELECT 1 FROM users WHERE id = :id');
    $userCheck->execute([':id' => (int)$_SESSION['user_id']]);
    if (!$userCheck->fetchColumn()) {
        $_SESSION = [];
        session_destroy();
        header("Location: /xampp/yoko-na/ojt-sdpbus/login.php");
        exit;
    }

    // Role-based route protection
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/shared/403.php");
        exit;
    }
}
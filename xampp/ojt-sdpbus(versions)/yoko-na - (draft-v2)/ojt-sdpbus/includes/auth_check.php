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
        header("Location: /ojt-sdpbus/login.php");
        exit;
    }

    // Role-based route protection
    if (!empty($allowedRoles) && !in_array($_SESSION['role'], $allowedRoles, true)) {
        header("Location: /ojt-sdpbus/frontend/shared/403.php");
        exit;
    }
}
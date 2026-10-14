<?php
// backend/auth/logout.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

// Log logout action before destroying session
if (isset($_SESSION['user_id'])) {
    log_activity($_SESSION['user_id'], 'User Logout', null, 'User signed out of session');
}

// Unset all session variables
$_SESSION = array();

// If session cookie exists, destroy it
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(session_name(), '', time() - 42000,
        $params["path"], $params["domain"],
        $params["secure"], $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to login portal
header("Location: /xampp/yoko-na/ojt-sdpbus/login.php?logged_out=1");
exit;
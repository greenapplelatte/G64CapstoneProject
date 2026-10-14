<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Unset all session variables
$_SESSION = array();

// Destroy session cookies if active
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

setcookie('mv_keep', '', time() - 3600, '/');   // forget "keep me signed in"

// Destroy session data completely
session_destroy();

// Redirect using absolute root path
header("Location: " . BASE_URL . "/login.php");
exit();
?>
<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Handle guest session initialization
if (isset($_GET['guest']) && $_GET['guest'] === 'true') {
    $_SESSION['user_id'] = 0;
    $_SESSION['username'] = 'guest';
    $_SESSION['fullname'] = 'Guest User';
    $_SESSION['role'] = 'viewer';
}

// Redirect to login page if no valid session exists
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
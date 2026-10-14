<?php
// includes/auth_check.php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect to login page if no valid session exists
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}
?>
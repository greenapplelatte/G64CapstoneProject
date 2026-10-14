<?php
// index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect based on active authentication state and user role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /ojt-sdpbus/frontend/admin/dashboard.php");
        exit;
    } else {
        header("Location: /ojt-sdpbus/frontend/staff/dashboard.php");
        exit;
    }
} else {
    header("Location: /ojt-sdpbus/login.php");
    exit;
}
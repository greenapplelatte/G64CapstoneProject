<?php
// index.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect based on active authentication state and user role
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    if ($_SESSION['role'] === 'admin') {
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/dashboard.php");
        exit;
    } else {
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/staff/dashboard.php");
        exit;
    }
} else {
    header("Location: /xampp/yoko-na/ojt-sdpbus/login.php");
    exit;
}
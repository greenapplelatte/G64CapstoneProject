<?php
// Starts the session (config/app.php sets the cookie options) and loads the auth helpers
require_once __DIR__ . '/includes/auth.php';

// Check if user is already logged in
if (isset($_SESSION['user_id']) && isset($_SESSION['role'])) {
    $role = $_SESSION['role'];

    // Route according to user role
    switch ($role) {
        case 'manager':
            header("Location: views/manager/dashboard.php");
            exit();
        case 'supervisor':
            header("Location: views/supervisor/dashboard.php");
            exit();
        case 'staff':
            header("Location: views/staff/dashboard.php");
            exit();
        default:
            // Fallback if role is unassigned or invalid
            session_destroy();
            header("Location: login.php?error=invalid_role");
            exit();
    }
} else {
    // Redirect unauthenticated users to the login page
    header("Location: login.php");
    exit();
}
?>
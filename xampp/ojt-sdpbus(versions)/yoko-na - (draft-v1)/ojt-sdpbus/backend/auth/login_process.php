<?php
// backend/auth/login_process.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $_SESSION['login_error'] = "Please fill in all required fields.";
        header("Location: /ojt-sdpbus/login.php");
        exit;
    }

    try {
        $db = Database::getConnection();

        // Query user record
        $stmt = $db->prepare("SELECT id, username, password_hash, role, force_password_change FROM users WHERE username = :username");
        $stmt->execute([':username' => $username]);
        $user = $stmt->fetch();

        // Password Verification & Generic Error Response
        if ($user && password_verify($password, $user['password_hash'])) {
            // Regenerate session ID to prevent session fixation attacks
            session_regenerate_id(true);

            $_SESSION['user_id'] = (int)$user['id'];
            $_SESSION['username'] = $user['username'];
            $_SESSION['role'] = $user['role'];

            // Update last login timestamp
            $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
            $updateStmt->execute([':id' => $user['id']]);

            // Log activity
            log_activity($user['id'], 'User Login', null, "User logged in with role: {$user['role']}");

            // Handle forced password change redirect
            if ((int)$user['force_password_change'] === 1) {
                header("Location: /ojt-sdpbus/frontend/shared/profile.php?force_change=1");
                exit;
            }

            // Role-based Routing
            if ($user['role'] === 'admin') {
                header("Location: /ojt-sdpbus/frontend/admin/dashboard.php");
            } else {
                header("Location: /ojt-sdpbus/frontend/staff/dashboard.php");
            }
            exit;
        } else {
            // Generic security error message
            $_SESSION['login_error'] = "Invalid username or password.";
            header("Location: /ojt-sdpbus/login.php");
            exit;
        }

    } catch (Exception $e) {
        error_log("Login Exception: " . $e->getMessage());
        $_SESSION['login_error'] = "A system error occurred. Please try again.";
        header("Location: /ojt-sdpbus/login.php");
        exit;
    }
} else {
    // Direct GET access protection
    header("Location: /ojt-sdpbus/login.php");
    exit;
}
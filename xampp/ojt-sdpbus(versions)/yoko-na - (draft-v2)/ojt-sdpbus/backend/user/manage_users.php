<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$redirect = '/ojt-sdpbus/frontend/admin/user_mgmt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$redirect}");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? '';

try {
    $db = Database::getConnection();

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'staff');

        if ($username === '' || strlen($username) > 64 || $password === '' || !in_array($role, ['admin', 'staff'], true)) {
            throw new InvalidArgumentException('Enter a valid username, password, and role.');
        }

        $stmt = $db->prepare('INSERT INTO users (username, password_hash, role, force_password_change) VALUES (:username, :password_hash, :role, 1)');
        $stmt->execute([
            ':username' => $username,
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':role' => $role
        ]);

        log_activity($userId, 'Create User', null, "Created {$role} account: {$username}");
        $_SESSION['success'] = "User '{$username}' created successfully.";
    } elseif ($action === 'reset_password') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === $userId) {
            throw new InvalidArgumentException('Select another user account to reset.');
        }

        $stmt = $db->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 1 WHERE id = :id');
        $stmt->execute([
            ':password_hash' => password_hash('Password123!', PASSWORD_DEFAULT),
            ':id' => $targetUserId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('The selected user account was not found.');
        }

        log_activity($userId, 'Reset User Password', null, "Reset password for user ID: {$targetUserId}");
        $_SESSION['success'] = 'Password reset successfully. The user must change it after signing in.';
    } else {
        throw new InvalidArgumentException('Unsupported user operation.');
    }
} catch (InvalidArgumentException $e) {
    $_SESSION['error'] = $e->getMessage();
} catch (PDOException $e) {
    error_log('Manage Users Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to save the user. Check that the username is unique.';
} catch (Exception $e) {
    error_log('Manage Users Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to process the user operation.';
}

header("Location: {$redirect}");
exit;
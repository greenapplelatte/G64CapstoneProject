<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$redirect = '/xampp/yoko-na/ojt-sdpbus/frontend/admin/user_mgmt.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$redirect}");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = $_POST['action'] ?? '';
$db = null;

try {
    $db = Database::getConnection();

    if ($action === 'create') {
        $username = trim((string)($_POST['username'] ?? ''));
        $password = (string)($_POST['password'] ?? '');
        $role = (string)($_POST['role'] ?? 'staff');

        if ($username === '' || strlen($username) > 64 || strlen($password) < 8 || !in_array($role, ['admin', 'staff'], true)) {
            throw new InvalidArgumentException('Enter a valid username, a password of at least 8 characters, and a role.');
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
        $password = (string)($_POST['password'] ?? '');
        $passwordMode = (string)($_POST['password_mode'] ?? 'custom');
        if ($targetUserId <= 0 || $targetUserId === $userId) {
            throw new InvalidArgumentException('Select another user account to reset.');
        }
        if (strlen($password) < 8 || !in_array($passwordMode, ['custom', 'generate'], true)) {
            throw new InvalidArgumentException('Enter or generate a password with at least 8 characters.');
        }

        $stmt = $db->prepare('UPDATE users SET password_hash = :password_hash, force_password_change = 1 WHERE id = :id');
        $stmt->execute([
            ':password_hash' => password_hash($password, PASSWORD_DEFAULT),
            ':id' => $targetUserId
        ]);

        if ($stmt->rowCount() === 0) {
            throw new InvalidArgumentException('The selected user account was not found.');
        }

        log_activity($userId, 'Reset User Password', null, "Reset password for user ID: {$targetUserId}");
        $_SESSION['success'] = $passwordMode === 'generate'
            ? "Password reset. Generated password: {$password}. The user must change it after signing in."
            : 'Password reset successfully. The user must change it after signing in.';
    } elseif ($action === 'delete') {
        $targetUserId = (int)($_POST['user_id'] ?? 0);
        if ($targetUserId <= 0 || $targetUserId === $userId) {
            throw new InvalidArgumentException('You cannot delete your own active account.');
        }

        $db->beginTransaction();
        $userStmt = $db->prepare('SELECT username FROM users WHERE id = :id');
        $userStmt->execute([':id' => $targetUserId]);
        $targetUsername = $userStmt->fetchColumn();
        if ($targetUsername === false) {
            throw new InvalidArgumentException('The selected user account was not found.');
        }

        $snapshotTickets = $db->prepare('UPDATE tickets SET username_snapshot = COALESCE(username_snapshot, :username) WHERE user_id = :id');
        $snapshotTickets->execute([':username' => $targetUsername, ':id' => $targetUserId]);
        $snapshotActivity = $db->prepare('UPDATE activity_logs SET username_snapshot = COALESCE(username_snapshot, :username) WHERE user_id = :id');
        $snapshotActivity->execute([':username' => $targetUsername, ':id' => $targetUserId]);

        $deleteStmt = $db->prepare('DELETE FROM users WHERE id = :id');
        $deleteStmt->execute([':id' => $targetUserId]);
        if ($deleteStmt->rowCount() === 0) {
            throw new InvalidArgumentException('The selected user account was not found.');
        }

        log_activity($userId, 'Delete User', null, "Deleted account {$targetUsername} (user ID: {$targetUserId})");
        $db->commit();
        $_SESSION['success'] = "User account '{$targetUsername}' deleted successfully. Existing trip and audit history has been retained.";
    } else {
        throw new InvalidArgumentException('Unsupported user operation.');
    }
} catch (InvalidArgumentException $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    $_SESSION['error'] = $e->getMessage();
} catch (PDOException $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Manage Users Error: ' . $e->getMessage());
    $_SESSION['error'] = $action === 'create'
        ? 'Failed to save the user. Check that the username is unique.'
        : 'Failed to process the user operation. Related records may prevent deletion.';
} catch (Exception $e) {
    if ($db instanceof PDO && $db->inTransaction()) {
        $db->rollBack();
    }
    error_log('Manage Users Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Failed to process the user operation.';
}

header("Location: {$redirect}");
exit;
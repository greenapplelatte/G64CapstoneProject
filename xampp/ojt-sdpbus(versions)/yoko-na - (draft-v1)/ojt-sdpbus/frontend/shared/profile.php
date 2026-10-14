<?php
// frontend/shared/profile.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$userId = (int)$_SESSION['user_id'];
$db = Database::getConnection();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
        $_SESSION['error'] = "All password fields are required.";
    } elseif ($newPass !== $confirmPass) {
        $_SESSION['error'] = "New password and confirmation do not match.";
    } else {
        $stmt = $db->prepare("SELECT password_hash FROM users WHERE id = :id");
        $stmt->execute([':id' => $userId]);
        $user = $stmt->fetch();

        if ($user && password_verify($currentPass, $user['password_hash'])) {
            $newHash = password_hash($newPass, PASSWORD_BCRYPT);
            $updateStmt = $db->prepare("UPDATE users SET password_hash = :hash, force_password_change = 0 WHERE id = :id");
            $updateStmt->execute([':hash' => $newHash, ':id' => $userId]);

            log_activity($userId, "Password Change", null, "User changed password successfully");
            $_SESSION['success'] = "Password updated successfully.";
            header("Location: " . ($_SESSION['role'] === 'admin' ? '/ojt-sdpbus/frontend/admin/dashboard.php' : '/ojt-sdpbus/frontend/staff/dashboard.php'));
            exit;
        } else {
            $_SESSION['error'] = "Current password is incorrect.";
        }
    }
}

$pageTitle = "User Profile - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Account Profile & Security</h1>
    </div>

    <?php if (isset($_GET['force_change'])): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Security Notice:</strong> You are required to change your default password before proceeding.
        </div>
    <?php endif; ?>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card form-container" style="max-width: 500px;">
        <h3>Change Password</h3>
        <form action="" method="POST">
            <div class="form-group margin-top">
                <label for="current_password">Current Password *</label>
                <input type="password" id="current_password" name="current_password" required>
            </div>

            <div class="form-group margin-top">
                <label for="new_password">New Password *</label>
                <input type="password" id="new_password" name="new_password" required minlength="8">
            </div>

            <div class="form-group margin-top">
                <label for="confirm_password">Confirm New Password *</label>
                <input type="password" id="confirm_password" name="confirm_password" required minlength="8">
            </div>

            <div class="form-actions margin-top">
                <button type="submit" class="btn btn-primary">Update Password</button>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
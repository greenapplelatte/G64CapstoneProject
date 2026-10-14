<?php
// frontend/shared/profile.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$userId = (int)$_SESSION['user_id'];
$db = Database::getConnection();

// --- 1. Fetch User Data ---
$stmt = $db->prepare("SELECT username, role, force_password_change, profile_image, last_login FROM users WHERE id = :id");
$stmt->execute([':id' => $userId]);
$userData = $stmt->fetch(PDO::FETCH_ASSOC);

$username = $userData['username'] ?? ($_SESSION['username'] ?? 'User');
$roleName = ucfirst($userData['role'] ?? ($_SESSION['role'] ?? 'Staff'));
$isDefaultPassword = (bool)($userData['force_password_change'] ?? 0);
$profileImage = $userData['profile_image'] ?? null;
$_SESSION['profile_image'] = $profileImage;

$lastSignin = (string)($userData['last_login'] ?? '');

$initials = strtoupper(substr($username, 0, 2));

// --- 2. Handle Form Processing ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['delete_avatar'])) {
        $storedImage = (string)($userData['profile_image'] ?? '');

        try {
            $deleteStmt = $db->prepare('UPDATE users SET profile_image = NULL WHERE id = :id');
            $deleteStmt->execute([':id' => $userId]);
            unset($_SESSION['profile_image']);
            $profileImage = null;

            $avatarUrlPrefix = '/ojt-sdpbus/uploads/avatars/';
            $avatarFilename = basename((string)parse_url($storedImage, PHP_URL_PATH));
            $ownedAvatarPattern = '/^avatar_(?:user_)?' . preg_quote((string)$userId, '/') . '_[A-Za-z0-9._-]+$/';

            if (
                substr($storedImage, 0, strlen($avatarUrlPrefix)) === $avatarUrlPrefix &&
                preg_match($ownedAvatarPattern, $avatarFilename) === 1
            ) {
                $avatarPath = __DIR__ . '/../../uploads/avatars/' . $avatarFilename;
                if (is_file($avatarPath) && !unlink($avatarPath)) {
                    error_log('Unable to remove profile avatar file: ' . $avatarPath);
                }
            }

            log_activity($userId, 'Delete Profile Photo', null, 'User removed their profile photo');
            $_SESSION['success'] = 'Profile photo removed successfully.';
        } catch (PDOException $e) {
            error_log('Profile Photo Delete Error: ' . $e->getMessage());
            $_SESSION['error'] = 'Unable to remove the profile photo. Please try again.';
        }
    }

    if (isset($_POST['update_username'])) {
        $newUsername = trim((string)($_POST['username'] ?? ''));

        if ($newUsername === '' || strlen($newUsername) > 64) {
            $_SESSION['error'] = 'Username must be between 1 and 64 characters.';
        } else {
            $checkStmt = $db->prepare('SELECT id FROM users WHERE username = :username AND id <> :id');
            $checkStmt->execute([':username' => $newUsername, ':id' => $userId]);

            if ($checkStmt->fetchColumn()) {
                $_SESSION['error'] = 'That username is already in use.';
            } else {
                try {
                    $updateStmt = $db->prepare('UPDATE users SET username = :username WHERE id = :id');
                    $updateStmt->execute([':username' => $newUsername, ':id' => $userId]);
                    $_SESSION['username'] = $newUsername;
                    $username = $newUsername;
                    $initials = strtoupper(substr($username, 0, 2));
                    log_activity($userId, 'Update Username', null, 'Username updated');
                    $_SESSION['success'] = 'Username updated successfully.';
                } catch (PDOException $e) {
                    error_log('Profile Username Update Error: ' . $e->getMessage());
                    $_SESSION['error'] = 'Unable to update the username. Please try again.';
                }
            }
        }
    }

    // Check if Avatar is uploaded directly via Form Submit
    if (isset($_FILES['avatar']) && $_FILES['avatar']['error'] === UPLOAD_ERR_OK) {
        $file = $_FILES['avatar'];
        $allowedTypes = ['image/jpeg', 'image/png', 'image/webp'];

        if (in_array($file['type'], $allowedTypes) && $file['size'] <= 2 * 1024 * 1024) {
            $uploadDir = __DIR__ . '/../../uploads/avatars/';
            if (!is_dir($uploadDir)) {
                mkdir($uploadDir, 0755, true);
            }

            $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'avatar_' . $userId . '_' . time() . '.' . $ext;
            $targetPath = $uploadDir . $filename;
            $dbRelativePath = '/ojt-sdpbus/uploads/avatars/' . $filename;

            if (move_uploaded_file($file['tmp_name'], $targetPath)) {
                $imgStmt = $db->prepare("UPDATE users SET profile_image = :img WHERE id = :id");
                $imgStmt->execute([':img' => $dbRelativePath, ':id' => $userId]);
                $_SESSION['profile_image'] = $dbRelativePath;
                $profileImage = $dbRelativePath;
                $_SESSION['success'] = "Profile picture updated successfully.";
            }
        } else {
            $_SESSION['error'] = "Invalid image format or file size exceeds 2MB limit.";
        }
    }

    // Check Password Update
    $currentPass = $_POST['current_password'] ?? '';
    $newPass = $_POST['new_password'] ?? '';
    $confirmPass = $_POST['confirm_password'] ?? '';

    if (!empty($currentPass) || !empty($newPass) || !empty($confirmPass)) {
        if (empty($currentPass) || empty($newPass) || empty($confirmPass)) {
            $_SESSION['error'] = "All password fields are required to update your password.";
        } elseif ($newPass !== $confirmPass) {
            $_SESSION['error'] = "New password and confirmation do not match.";
        } elseif (
            preg_match('/^.{8,}$/u', $newPass) !== 1 ||
            preg_match('/[a-z]/', $newPass) !== 1 ||
            preg_match('/[A-Z]/', $newPass) !== 1 ||
            preg_match('/\d/', $newPass) !== 1 ||
            preg_match('/[^A-Za-z0-9\s]/', $newPass) !== 1
        ) {
            $_SESSION['error'] = "Password must be at least 8 characters and include uppercase and lowercase letters, a number, and a symbol.";
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
}

$pageTitle = "Account Profile & Security - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="profile-page-container">
        <!-- Title & Subtitle -->
        <div class="profile-header">
            <h2>Account Profile & Security</h2>
            <p>Manage how you sign in to SDPBUS.</p>
        </div>

        <!-- System Alerts -->
        <?php if (isset($_GET['force_change']) || $isDefaultPassword): ?>
            <div class="security-banner">
                <i class="fa-solid fa-triangle-exclamation"></i>
                <span><strong>Security notice:</strong> You are still using the default password. Change it now to unlock the rest of the system.</span>
            </div>
        <?php endif; ?>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
        <?php endif; ?>

        <?php if (isset($_SESSION['success'])): ?>
            <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
        <?php endif; ?>

        <div class="profile-grid">
            <!-- Left Card: User Profile & Avatar -->
            <div class="profile-card identity-card">
                <form id="avatarForm" class="avatar-form" enctype="multipart/form-data" method="POST">
                    <div class="avatar-wrapper">
                        <input type="file" id="avatarInput" name="avatar" accept="image/png, image/jpeg, image/webp" style="display: none;" onchange="this.form.submit()">
                        <label for="avatarInput" class="avatar-label" title="Click to change profile picture">
                            <?php if (!empty($profileImage)): ?>
                                <img src="<?= htmlspecialchars($profileImage) ?>" id="avatarImage" alt="Profile Picture">
                            <?php else: ?>
                                <div class="avatar-placeholder"><?= htmlspecialchars($initials) ?></div>
                            <?php endif; ?>
                            <div class="avatar-overlay">
                                <i class="fa-solid fa-camera"></i>
                            </div>
                        </label>
                    </div>
                    <?php if (!empty($profileImage)): ?>
                        <button type="submit" id="deleteAvatarButton" name="delete_avatar" value="1" class="avatar-delete-button">
                            <i class="fa-solid fa-trash-can" aria-hidden="true"></i> Remove photo
                        </button>
                    <?php endif; ?>
                </form>

                <form method="POST" class="profile-name-form">
                    <label for="profileUsername">Username</label>
                    <div class="profile-name-controls">
                        <input type="text" id="profileUsername" name="username" value="<?= htmlspecialchars($username) ?>" maxlength="64" required>
                        <button type="submit" name="update_username" value="1">Save</button>
                    </div>
                </form>
                <span class="role-badge"><?= htmlspecialchars($roleName) ?></span>

                <div class="profile-info-list">
                    <div class="info-row">
                        <span class="info-label">Account status</span>
                        <span class="info-value status-active" id="accountStatus">Active</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Last sign-in</span>
                        <span class="info-value" id="lastSignin" data-last-login="<?= htmlspecialchars($lastSignin, ENT_QUOTES, 'UTF-8') ?>">—</span>
                    </div>
                    <div class="info-row">
                        <span class="info-label">Password</span>
                        <span class="info-value <?= $isDefaultPassword ? 'text-warning' : '' ?>" id="passwordStatus">
                            <?= $isDefaultPassword ? 'Default, needs change' : 'Updated' ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Right Card: Change Password Form -->
            <div class="profile-card security-card">
                <h3>Change password</h3>
                <p class="section-desc">Choose a password you have not used for other accounts.</p>

                <form method="POST" action="">
                    <div class="form-group">
                        <label for="current_password">Current password</label>
                        <div class="password-input-wrapper">
                            <input type="password" id="current_password" name="current_password" required placeholder="••••••••••••">
                            <i class="fa-regular fa-eye toggle-password" data-target="current_password"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group">
                            <label for="new_password">New password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="new_password" name="new_password" required minlength="8" placeholder="••••••••••••">
                                <i class="fa-regular fa-eye toggle-password" data-target="new_password"></i>
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="confirm_password">Confirm new password</label>
                            <div class="password-input-wrapper">
                                <input type="password" id="confirm_password" name="confirm_password" required minlength="8" placeholder="••••••••••••">
                                <i class="fa-solid fa-check match-icon" id="matchIcon"></i>
                                <i class="fa-regular fa-eye toggle-password" data-target="confirm_password"></i>
                            </div>
                        </div>
                    </div>

                    <!-- Password Strength Meter -->
                    <div class="strength-gauge-container">
                        <div class="strength-text">Strength: <strong id="strengthLabel" class="weak">Weak</strong>. <span id="strengthAdvice">Use at least 8 characters with mixed case, a number, and a symbol.</span></div>
                        <div class="strength-meter">
                            <div class="meter-line" id="meterLine"></div>
                            <span class="step-dot" id="dot0"></span>
                            <span class="step-dot" id="dot1"></span>
                            <span class="step-dot" id="dot2"></span>
                            <span class="step-dot" id="dot3"></span>
                        </div>
                    </div>

                    <!-- Password Requirements Checklist -->
                    <div class="password-rules">
                        <div class="rule-item" id="ruleMinLength"><i class="fa-regular fa-circle"></i> At least 8 characters</div>
                        <div class="rule-item" id="ruleCasing"><i class="fa-regular fa-circle"></i> Upper and lower case letters</div>
                        <div class="rule-item" id="ruleNumber"><i class="fa-regular fa-circle"></i> At least one number</div>
                        <div class="rule-item" id="ruleSymbol"><i class="fa-regular fa-circle"></i> At least one symbol</div>
                        <div class="rule-item" id="ruleMatch"><i class="fa-regular fa-circle"></i> New and confirm passwords match</div>
                    </div>

                    <button type="submit" class="btn-primary-block">Update password</button>
                </form>
            </div>
        </div>
    </div>
</main>

<script src="/ojt-sdpbus/assets/js/profile.js"></script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>
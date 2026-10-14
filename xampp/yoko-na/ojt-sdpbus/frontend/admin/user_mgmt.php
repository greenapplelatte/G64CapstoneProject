<?php
// frontend/admin/user_mgmt.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$users = $db->query("SELECT id, username, role, profile_image, force_password_change, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();
$totalUsers = count($users);
$adminCount = count(array_filter($users, static fn($user) => $user['role'] === 'admin'));
$staffCount = count(array_filter($users, static fn($user) => $user['role'] === 'staff'));
$passwordChangeCount = count(array_filter($users, static fn($user) => (int)$user['force_password_change'] === 1));
$currentUserId = (int)($_SESSION['user_id'] ?? 0);

$pageTitle = "User Management - SDPBUS";
$extraCss = 'user-mgmt.css';
$extraScript = 'user-mgmt.js';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content user-mgmt-page">
    <div class="user-mgmt-content">
    <header class="user-mgmt-header">
        <h1>User Management</h1>
        <p>Create accounts and control who can sign in. New and reset accounts must change their password at first sign-in.</p>
    </header>
    <div class="user-stats" aria-label="User account summary">
        <article class="user-stat">
            <span class="user-stat-icon icon-blue"><i class="fas fa-users" aria-hidden="true"></i></span>
            <span class="user-stat-copy"><strong><?= $totalUsers ?></strong><small>Total accounts</small></span>
        </article>
        <article class="user-stat">
            <span class="user-stat-icon icon-sky"><i class="fas fa-shield-alt" aria-hidden="true"></i></span>
            <span class="user-stat-copy"><strong><?= $adminCount ?></strong><small>Administrators</small></span>
        </article>
        <article class="user-stat">
            <span class="user-stat-icon icon-green"><i class="fas fa-user" aria-hidden="true"></i></span>
            <span class="user-stat-copy"><strong><?= $staffCount ?></strong><small>Staff</small></span>
        </article>
        <article class="user-stat">
            <span class="user-stat-icon icon-amber"><i class="fas fa-key" aria-hidden="true"></i></span>
            <span class="user-stat-copy"><strong><?= $passwordChangeCount ?></strong><small>Must change password</small></span>
        </article>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible user-mgmt-alert"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success user-mgmt-alert"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="user-mgmt-layout">
        <section class="user-panel create-user-panel" aria-labelledby="create-user-title">
            <header class="panel-heading">
                <h2 id="create-user-title">Create new user</h2>
                <p>Share the initial password once. The user must change it at first sign-in.</p>
            </header>
            <form action="/xampp/yoko-na/ojt-sdpbus/backend/user/manage_users.php" method="POST" class="create-user-form">
            <input type="hidden" name="action" value="create">
            <div class="user-form-field">
                <label for="username">Username *</label>
                <div class="input-with-icon"><i class="far fa-user" aria-hidden="true"></i><input type="text" id="username" name="username" required maxlength="64" autocomplete="off" placeholder="e.g. jsmith"></div>
            </div>
            <fieldset class="role-fieldset">
                <legend>Assigned role</legend>
                <div class="role-options">
                    <label class="role-option">
                        <input type="radio" name="role" value="staff" checked>
                        <span class="role-option-content"><strong><i class="fas fa-bus" aria-hidden="true"></i> Staff</strong><small>Records trips and submits tickets</small></span>
                    </label>
                    <label class="role-option">
                        <input type="radio" name="role" value="admin">
                        <span class="role-option-content"><strong><i class="fas fa-shield-alt" aria-hidden="true"></i> Admin</strong><small>Reviews tickets, manages data and users</small></span>
                    </label>
                </div>
            </fieldset>
            <div class="user-form-field password-user-field">
                <label for="password">Initial password *</label>
                <div class="password-control">
                    <i class="fas fa-lock" aria-hidden="true"></i>
                    <input type="password" id="password" name="password" required minlength="8" autocomplete="new-password" placeholder="Create a strong password">
                    <button type="button" class="toggle-password-visibility" id="toggle-password-visibility" aria-label="Show password" aria-pressed="false" aria-controls="password" title="Show password"><i class="fas fa-eye-slash" aria-hidden="true"></i></button>
                    <button type="button" class="generate-password" id="generate-password"><i class="fas fa-sync-alt" aria-hidden="true"></i> Generate</button>
                </div>
                <small class="password-note"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i> Create a strong staff/admin password.</small>
            </div>
            <button type="submit" class="btn btn-primary create-user-submit"><i class="fas fa-user-plus" aria-hidden="true"></i> Create user account</button>
            </form>
        </section>

        <section class="user-panel accounts-panel" aria-labelledby="accounts-title">
            <div class="accounts-heading">
                <div class="panel-heading"><h2 id="accounts-title">System user accounts</h2></div>
                <label class="user-search"><i class="fas fa-search" aria-hidden="true"></i><span class="visually-hidden">Search users</span><input type="search" id="user-search" placeholder="Search users" autocomplete="off"></label>
            </div>
            <div class="user-filters" role="group" aria-label="Filter user accounts">
                <button type="button" class="user-filter is-active" data-filter="all" aria-pressed="true">All <span><?= $totalUsers ?></span></button>
                <button type="button" class="user-filter" data-filter="admin" aria-pressed="false">Admins <span><?= $adminCount ?></span></button>
                <button type="button" class="user-filter" data-filter="staff" aria-pressed="false">Staff <span><?= $staffCount ?></span></button>
                <button type="button" class="user-filter" data-filter="password" aria-pressed="false">Must change password <span><?= $passwordChangeCount ?></span></button>
            </div>
            <div class="accounts-table-wrap">
                <table class="accounts-table">
                    <thead><tr><th scope="col">User</th><th scope="col">Role</th><th scope="col">Password</th><th scope="col">Last login</th><th scope="col">Actions</th></tr></thead>
                    <tbody id="user-rows">
                    <?php foreach ($users as $u): ?>
                        <?php
                        $isCurrentUser = (int)$u['id'] === $currentUserId;
                        $needsPasswordChange = (int)$u['force_password_change'] === 1;
                        $initials = strtoupper(substr($u['username'], 0, 2));
                        $avatarUrl = (string)($u['profile_image'] ?? '');
                        $legacyAvatarPrefix = '/ojt-sdpbus/uploads/avatars/';
                        if (str_starts_with($avatarUrl, $legacyAvatarPrefix)) {
                            $avatarUrl = '/xampp/yoko-na' . $avatarUrl;
                        }
                        $loginDate = $u['last_login'] ? strtotime($u['last_login']) : false;
                        ?>
                        <tr class="account-row" data-user-id="<?= (int)$u['id'] ?>" data-role="<?= htmlspecialchars($u['role']) ?>" data-password="<?= $needsPasswordChange ? 'change' : 'set' ?>" data-search="<?= htmlspecialchars(strtolower($u['username'])) ?>">
                            <td data-label="User"><div class="account-identity"><?php if ($avatarUrl !== ''): ?><img class="account-avatar" src="<?= htmlspecialchars($avatarUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy"><?php else: ?><span class="account-avatar avatar-<?= (int)$u['id'] % 5 ?>"><?= htmlspecialchars($initials) ?></span><?php endif; ?><strong><?= htmlspecialchars($u['username']) ?></strong><?php if ($isCurrentUser): ?><span class="current-user-tag">You</span><?php endif; ?></div></td>
                            <td data-label="Role"><span class="role-badge role-<?= htmlspecialchars($u['role']) ?>"><?= htmlspecialchars(ucfirst($u['role'])) ?></span></td>
                            <td data-label="Password"><span class="password-badge <?= $needsPasswordChange ? 'password-change' : 'password-set' ?>"><i class="fas <?= $needsPasswordChange ? 'fa-exclamation-triangle' : 'fa-check' ?>" aria-hidden="true"></i> <?= $needsPasswordChange ? 'Must change' : 'Set' ?></span></td>
                            <td data-label="Last login" class="last-login" data-last-login="<?= htmlspecialchars((string)($u['last_login'] ?? ''), ENT_QUOTES, 'UTF-8') ?>"><?php if ($loginDate): ?><strong><?= date('H:i', $loginDate) ?></strong><small><?= date('M j', $loginDate) === date('M j') ? 'Today' : date('M j', $loginDate) ?></small><?php else: ?><span class="never-login">Never</span><?php endif; ?></td>
                            <td data-label="Actions">
                                <?php if ($isCurrentUser): ?>
                                    <span class="current-account">Current account</span>
                                <?php else: ?>
                                    <div class="account-actions">
                                        <form action="/xampp/yoko-na/ojt-sdpbus/backend/user/manage_users.php" method="POST" class="reset-password-form" data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="reset_password">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <input type="hidden" name="password" class="reset-password-value">
                                            <input type="hidden" name="password_mode" class="reset-password-mode" value="generate">
                                            <button type="submit" class="reset-password-button"><i class="fas fa-key" aria-hidden="true"></i> Reset password</button>
                                        </form>
                                        <form action="/xampp/yoko-na/ojt-sdpbus/backend/user/manage_users.php" method="POST" class="delete-user-form" data-username="<?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?>">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                                            <button type="submit" class="delete-user-button" aria-label="Delete <?= htmlspecialchars($u['username'], ENT_QUOTES, 'UTF-8') ?> account"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>Delete</span></button>
                                        </form>
                                    </div>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    <tr class="empty-users-row" id="empty-users" hidden><td colspan="5">No matching accounts.</td></tr>
                    </tbody>
                </table>
            </div>
            <footer class="accounts-footer" id="accounts-footer">Showing <?= $totalUsers ?> of <?= $totalUsers ?> accounts</footer>
        </section>
    </div>
    </div>
</main>

<dialog class="delete-user-dialog" id="delete-user-dialog" aria-labelledby="delete-user-dialog-title" aria-describedby="delete-user-dialog-description">
    <div class="delete-dialog-icon"><i class="fas fa-trash-alt" aria-hidden="true"></i></div>
    <p class="delete-dialog-eyebrow">Delete user account</p>
    <h2 id="delete-user-dialog-title">Delete <span id="delete-user-name"></span>?</h2>
    <p id="delete-user-dialog-description">This permanently removes the account and cannot be undone. Existing trip and audit history will be retained under this username.</p>
    <div class="delete-dialog-actions">
        <button type="button" class="delete-dialog-cancel" id="cancel-delete-user" autofocus>Keep account</button>
        <button type="button" class="delete-dialog-confirm" id="confirm-delete-user"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete account</button>
    </div>
</dialog>

<dialog class="reset-password-dialog" id="reset-password-dialog" aria-labelledby="reset-password-dialog-title" aria-describedby="reset-password-dialog-description">
    <div class="reset-dialog-icon"><i class="fas fa-key" aria-hidden="true"></i></div>
    <p class="reset-dialog-eyebrow">Account credentials</p>
    <h2 id="reset-password-dialog-title">Reset <span id="reset-password-username"></span></h2>
    <p id="reset-password-dialog-description">Choose a password for this account. The user will be asked to change it after signing in.</p>
    <fieldset class="reset-password-mode-options">
        <legend>Choose a password</legend>
        <label><input type="radio" name="reset-password-mode-choice" value="generate" checked><span>Generate password</span></label>
        <label><input type="radio" name="reset-password-mode-choice" value="custom"><span>Set user password </span></label>
    </fieldset>
    <div class="reset-generated-panel" id="reset-generated-panel">
        <label for="generated-reset-password">Generated password</label>
        <div class="reset-password-value-control">
            <input type="text" id="generated-reset-password" readonly autocomplete="off" aria-label="Generated password">
            <button type="button" id="regenerate-reset-password" class="reset-dialog-icon-button" title="Generate another password" aria-label="Generate another password"><i class="fas fa-sync-alt" aria-hidden="true"></i></button>
            <button type="button" id="copy-reset-password" class="reset-dialog-icon-button" title="Copy password" aria-label="Copy password"><i class="far fa-copy" aria-hidden="true"></i></button>
        </div>
    </div>
    <div class="reset-custom-panel" id="reset-custom-panel" hidden>
        <label for="custom-reset-password">New password</label>
        <div class="reset-password-value-control">
            <input type="password" id="custom-reset-password" minlength="8" autocomplete="new-password" placeholder="Enter at least 8 characters">
            <button type="button" id="toggle-custom-reset-password" class="reset-dialog-icon-button" title="Show password" aria-label="Show password" aria-pressed="false"><i class="fas fa-eye-slash" aria-hidden="true"></i></button>
        </div>
        <small>Use at least 8 characters. The user must change it after signing in.</small>
    </div>
    <p class="reset-dialog-feedback" id="reset-password-feedback" role="status" aria-live="polite"></p>
    <div class="reset-dialog-actions">
        <button type="button" class="reset-dialog-cancel" id="cancel-reset-password" autofocus>Cancel</button>
        <button type="button" class="reset-dialog-confirm" id="confirm-reset-password"><i class="fas fa-key" aria-hidden="true"></i> Reset password</button>
    </div>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
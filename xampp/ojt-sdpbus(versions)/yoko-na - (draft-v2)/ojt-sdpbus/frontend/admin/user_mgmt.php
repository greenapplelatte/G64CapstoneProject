<?php
// frontend/admin/user_mgmt.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$users = $db->query("SELECT id, username, role, force_password_change, last_login, created_at FROM users ORDER BY created_at DESC")->fetchAll();

$pageTitle = "User Management - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>System User Management</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <!-- User Creation Card -->
    <div class="card margin-top">
        <h3>Create New System User</h3>
            <form action="/ojt-sdpbus/backend/user/manage_users.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
            <input type="hidden" name="action" value="create">
            <div>
                <label for="username">Username *</label>
                <input type="text" id="username" name="username" required placeholder="e.g. jsmith">
            </div>
            <div>
                <label for="password">Initial Password *</label>
                <input type="password" id="password" name="password" value="Password123!" required>
            </div>
            <div>
                <label for="role">Assigned Role</label>
                <select name="role" id="role">
                    <option value="staff">Staff</option>
                    <option value="admin">Administrator</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Create User Account</button>
        </form>
    </div>

    <!-- User List -->
    <div class="card margin-top">
        <h3>System User Accounts</h3>
        <table class="table">
            <thead>
                <tr>
                    <th>Username</th>
                    <th>Role</th>
                    <th>Force Pwd Change</th>
                    <th>Last Login</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><strong>👤 <?= htmlspecialchars($u['username']) ?></strong></td>
                        <td><span class="badge badge-info"><?= strtoupper($u['role']) ?></span></td>
                        <td><?= (int)$u['force_password_change'] === 1 ? 'Yes' : 'No' ?></td>
                        <td><?= $u['last_login'] ? htmlspecialchars($u['last_login']) : 'Never' ?></td>
                        <td>
                            <form action="/ojt-sdpbus/backend/user/manage_users.php" method="POST" style="display: inline;">
                                <input type="hidden" name="action" value="reset_password">
                                <input type="hidden" name="user_id" value="<?= $u['id'] ?>">
                                <button type="submit" class="btn btn-sm btn-warning" onclick="return confirm('Reset password for <?= htmlspecialchars($u['username']) ?> to Password123!?')">Reset Password</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
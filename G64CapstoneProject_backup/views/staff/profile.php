<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('staff');
$staffContext = db_one($conn, "SELECT assigned_station FROM users WHERE id = ?", "i", [(int)$_SESSION['user_id']]);
$staffStation = trim($staffContext['assigned_station'] ?? '');

$full_name = $_SESSION['full_name'] ?? 'Staff1';
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Profile - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Profile Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/profile.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <?php if (!empty($_SESSION['must_change_password'])): ?>
        <div role="alert" style="background:#fff7e0;border:1px solid #f0c36d;color:#7a5200;padding:12px 16px;border-radius:8px;margin-bottom:16px;">
            <strong>Set a new password to continue.</strong> You signed in with a temporary password. Choose your own below; the rest of the system stays locked until you do.
        </div>
        <?php endif; ?>
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Profile</h1>
                <p class="subtitle">Your account details, assigned station and sign-in settings.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
                <div style="color:var(--text-secondary)">Station <strong><?= htmlspecialchars($staffStation !== '' ? $staffStation : 'Not assigned', ENT_QUOTES); ?></strong></div>
            </div>
        </header>

        <section class="grid-profile">
            <!-- Left Profile Card -->
            <div class="card">
                <div class="profile-avatar-container">
                    <div class="avatar-large"><?= htmlspecialchars($initials); ?></div>
                    <div class="profile-name" id="display-fullname"><?= htmlspecialchars($full_name); ?></div>
                    <div class="profile-role">Production Staff</div>
                    <span class="shift-badge" id="shift-badge" style="display:none"></span>
                </div>
                <div class="profile-meta-list">
                    <div class="meta-row">
                        <span class="meta-label">Assigned station</span>
                        <span class="meta-val" id="meta-station">Not assigned</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Shift</span>
                        <span class="meta-val" id="meta-shift">Not set</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Last sign-in</span>
                        <span class="meta-val" id="meta-signin">Not available</span>
                    </div>
                </div>
            </div>

            <!-- Right Profile Forms -->
            <div>
                <!-- Account Details -->
                <div class="card">
                    <div class="card-header-bar header-blue">ACCOUNT DETAILS</div>
                    <div class="card-body">
                        <form id="accountForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Full name</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="input-fullname" class="form-control" readonly>
                                        <span class="lock-icon">🔒</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Employee ID</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="input-empid" class="form-control" readonly>
                                        <span class="lock-icon">🔒</span>
                                    </div>
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Department</label>
                                    <div class="input-wrapper">
                                        <input type="text" id="input-dept" class="form-control" readonly>
                                        <span class="lock-icon">🔒</span>
                                    </div>
                                </div>
                                <div class="form-group">
                                    <label>Contact number</label>
                                    <input type="tel" name="contact_number" id="input-contact" class="form-control" placeholder="09XX XXX XXXX" pattern="(?:\+63|0)9[0-9]{9}" title="Enter a valid Philippine mobile number, e.g. 09171234567 or +639171234567">
                                </div>
                            </div>

                            <div class="form-action-row">
                                <span class="helper-text">🔒 Locked fields can only be changed by your supervisor.</span>
                                <button type="submit" class="btn-save-yellow">SAVE CHANGES</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Change Password -->
                <div class="card">
                    <div class="card-header-bar header-green">CHANGE PASSWORD</div>
                    <div class="card-body">
                        <form id="passwordForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Current password</label>
                                    <input type="password" name="current_password" class="form-control" required>
                                </div>
                                <div class="form-group">
                                    <label>New password</label>
                                    <input type="password" name="new_password" class="form-control" required minlength="8">
                                </div>
                            </div>

                            <div class="form-group" style="width: 49%; margin-bottom: 15px;">
                                <label>Confirm new password</label>
                                <input type="password" name="confirm_password" class="form-control" required minlength="8">
                            </div>

                            <div class="form-action-row">
                                <span class="helper-text">Use at least 8 characters, including at least one number.</span>
                                <button type="submit" class="btn-save-yellow">UPDATE PASSWORD</button>
                            </div>
                        </form>
                    </div>
                </div>


            </div>
        </section>
    </main>

    <!-- Inline Script Handlers -->
    <script>
        const BASE_URL = <?= json_encode(BASE_URL); ?>;
        document.addEventListener("DOMContentLoaded", () => {
            loadProfileData();

            // Account Form Submit
            document.getElementById("accountForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_profile.php?action=update_account", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => { alert(data.message); if (data.status === 'success') loadProfileData(); })
                .catch(() => alert('Network error. Contact details were not saved. Please try again.'));
            });

            // Password Form Submit
            document.getElementById("passwordForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_profile.php?action=update_password", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        this.reset();
                        <?php if (!empty($_SESSION['must_change_password'])): ?>
                        window.location.href = BASE_URL + "/views/staff/dashboard.php";
                        <?php endif; ?>
                    }
                })
                .catch(() => alert('Network error. Password was not updated. Please try again.'));
            });
        });

        function loadProfileData() {
            fetch(BASE_URL + "/backend/process_profile.php?action=get_profile")
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        const d = res.data;
                        document.getElementById("input-fullname").value = d.full_name || '';
                        document.getElementById("input-empid").value = d.employee_id || 'Not set';
                        document.getElementById("input-dept").value = d.department || 'Not set';
                        document.getElementById("input-contact").value = d.contact_number || '';
                        document.getElementById("meta-station").innerText = d.assigned_station || 'Not assigned';
                        document.getElementById("meta-shift").innerText = d.shift_schedule || 'Not set';
                        document.getElementById("meta-signin").innerText = d.last_signin || 'Not available';

                    }
                })
                .catch(() => alert('Unable to load profile information. Refresh the page or contact your supervisor.'));
        }

    </script>
</body>
</html>
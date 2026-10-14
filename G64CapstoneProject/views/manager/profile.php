<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('manager');

$full_name = $_SESSION['full_name'] ?? 'Manager1';
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
                <div style="color:var(--text-secondary)">Station <strong>Not assigned</strong></div>
            </div>
        </header>

        <section class="grid-profile">
            <!-- Left Profile Card -->
            <div class="card">
                <div class="profile-avatar-container">
                    <div class="avatar-large"><?= htmlspecialchars($initials); ?></div>
                    <div class="profile-name" id="display-fullname"><?= htmlspecialchars($full_name); ?></div>
                    <div class="profile-role">Plant Manager · Operations</div>
                    <span class="shift-badge">● ACTIVE SESSION</span>
                    <button class="btn-change-photo">Change photo</button>
                </div>
                <div class="profile-meta-list">
                    <div class="meta-row">
                        <span class="meta-label">Assigned station</span>
                        <span class="meta-val" id="meta-station">Not assigned</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Shift</span>
                        <span class="meta-val" id="meta-shift">Flexible / On-Call</span>
                    </div>
                    <div class="meta-row">
                        <span class="meta-label">Last sign-in</span>
                        <span class="meta-val" id="meta-signin">Today, 6:55 AM</span>
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
                                    <input type="text" name="contact_number" id="input-contact" class="form-control" placeholder="09XX XXX XXXX">
                                </div>
                            </div>

                            <div class="form-action-row">
                                <span class="helper-text">🔒 Locked fields can only be changed by system administration.</span>
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
                                <span class="helper-text">Use at least 8 characters, with a number.</span>
                                <button type="submit" class="btn-save-yellow">UPDATE PASSWORD</button>
                            </div>
                        </form>
                    </div>
                </div>

                <!-- Preferences -->
                <div class="card">
                    <div class="card-header-bar header-dark">PREFERENCES</div>
                    <div class="card-body">
                        <form id="prefForm">
                            <div class="pref-item">
                                <div class="pref-text">
                                    <strong>Alert me when critical defect reports are received</strong>
                                    <small>Shows an immediate message on your executive dashboard.</small>
                                </div>
                                <input type="checkbox" name="pref_defect_alert" id="pref_alert" class="custom-checkbox" onchange="savePreferences()">
                            </div>

                            <div class="pref-item">
                                <div class="pref-text">
                                    <strong>Remind me before shift closing deadlines</strong>
                                    <small>Helps keep track of pending supervisor close-outs.</small>
                                </div>
                                <input type="checkbox" name="pref_shift_reminder" id="pref_reminder" class="custom-checkbox" onchange="savePreferences()">
                            </div>

                            <div class="pref-item">
                                <div class="pref-text">
                                    <strong>Show time in 24-hour format</strong>
                                </div>
                                <input type="checkbox" name="pref_24h_format" id="pref_format" class="custom-checkbox" onchange="savePreferences()">
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Script Handlers -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadProfileData();

            // Account Form Submit
            document.getElementById("accountForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_manager_profile.php?action=update_account", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => alert(data.message))
                .catch(err => console.error("Error updating account:", err));
            });

            // Password Form Submit
            document.getElementById("passwordForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_manager_profile.php?action=update_password", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        this.reset();
                        <?php if (!empty($_SESSION['must_change_password'])): ?>
                        window.location.href = BASE_URL + "/views/manager/dashboard.php";
                        <?php endif; ?>
                    }
                })
                .catch(err => console.error("Error updating password:", err));
            });
        });

        function loadProfileData() {
            fetch(BASE_URL + "/backend/process_manager_profile.php?action=get_profile")
                .then(res => res.json())
                .then(res => {
                    if (res.status === 'success' && res.data) {
                        const d = res.data;
                        document.getElementById("input-fullname").value = d.full_name || '';
                        document.getElementById("input-empid").value = d.employee_id || 'MVE-M001';
                        document.getElementById("input-dept").value = d.department || 'Plant Management';
                        document.getElementById("input-contact").value = d.contact_number || '';
                        document.getElementById("meta-station").innerText = d.assigned_station || 'Not assigned';
                        document.getElementById("meta-shift").innerText = d.shift_schedule || 'Flexible / On-Call';
                        document.getElementById("meta-signin").innerText = d.last_signin || 'Today, 6:55 AM';

                        document.getElementById("pref_alert").checked = d.pref_defect_alert == 1;
                        document.getElementById("pref_reminder").checked = d.pref_shift_reminder == 1;
                        document.getElementById("pref_format").checked = d.pref_24h_format == 1;
                    }
                })
                .catch(err => console.error("Error loading profile data:", err));
        }

        function savePreferences() {
            let formData = new FormData(document.getElementById("prefForm"));

            fetch(BASE_URL + "/backend/process_manager_profile.php?action=save_preferences", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => console.log("Preferences saved"))
            .catch(err => console.error("Error saving preferences:", err));
        }
    </script>
</body>
</html>
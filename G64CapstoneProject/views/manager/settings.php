<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('manager');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>System Settings - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & System Settings Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/settings.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>System Settings</h1>
                <p class="subtitle">Plant-wide options for time, alerts, sign-in security, data retention and station connectivity. Changes apply to every user.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <form id="settingsForm">
            <section class="grid-settings-main">
                <!-- Left Main Column -->
                <div>
                    <!-- General Settings -->
                    <div class="card">
                        <div class="card-header-bar header-blue">GENERAL</div>
                        <div class="card-body">
                            <div class="form-group" style="margin-bottom: 12px;">
                                <label>Plant name</label>
                                <input type="text" name="plant_name" id="plant_name" class="form-control" value="Masters Vineyard Enterprise">
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Time zone</label>
                                    <select name="time_zone" id="time_zone" class="form-control">
                                        <option value="Asia/Manila (UTC+08:00)">Asia/Manila (UTC+08:00)</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Time format</label>
                                    <select name="time_format" id="time_format" class="form-control">
                                        <option value="12-hour (12:00 PM)">12-hour (12:00 PM)</option>
                                        <option value="24-hour (24:00)">24-hour (24:00)</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Alerts Settings -->
                    <div class="card">
                        <div class="card-header-bar header-blue">
                            <span>ALERTS</span>
                            <small style="text-transform: none; font-weight: normal; opacity: 0.9;">Sent to managers and supervisors</small>
                        </div>
                        <div class="card-body">
                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>Defect reports waiting over 30 minutes</strong>
                                    <small>Notify the supervisor on shift, then the manager after 60 minutes.</small>
                                </div>
                                <input type="checkbox" name="alert_defect_30m" id="alert_defect_30m" class="custom-checkbox" checked>
                            </div>

                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>Line stopped for over 15 minutes</strong>
                                    <small>Includes pauses that have no reason recorded.</small>
                                </div>
                                <input type="checkbox" name="alert_line_stop_15m" id="alert_line_stop_15m" class="custom-checkbox" checked>
                            </div>

                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>OEE under target for a full shift</strong>
                                    <small>Checked when a shift is closed out.</small>
                                </div>
                                <input type="checkbox" name="alert_oee_under_target" id="alert_oee_under_target" class="custom-checkbox" checked>
                            </div>

                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>Station goes offline</strong>
                                    <small>Uses the offline threshold set under station connectivity.</small>
                                </div>
                                <input type="checkbox" name="alert_station_offline" id="alert_station_offline" class="custom-checkbox" checked>
                            </div>

                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>Weekly summary to managers</strong>
                                    <small>Monday at 06:00 AM, PDF attached.</small>
                                </div>
                                <input type="checkbox" name="alert_weekly_summary" id="alert_weekly_summary" class="custom-checkbox">
                            </div>
                        </div>
                    </div>

                    <!-- Sign-In Security -->
                    <div class="card">
                        <div class="card-header-bar header-blue">SIGN-IN SECURITY</div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Sign out after inactivity</label>
                                    <select name="inactivity_timeout" id="inactivity_timeout" class="form-control">
                                        <option value="30 minutes">30 minutes</option>
                                        <option value="15 minutes">15 minutes</option>
                                        <option value="60 minutes">60 minutes</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Minimum password length</label>
                                    <input type="number" name="min_password_len" id="min_password_len" class="form-control" value="10">
                                </div>
                            </div>

                            <div class="form-row" style="margin-bottom: 15px;">
                                <div class="form-group">
                                    <label>Change password every</label>
                                    <select name="password_expiry" id="password_expiry" class="form-control">
                                        <option value="90 days">90 days</option>
                                        <option value="180 days">180 days</option>
                                    </select>
                                </div>
                            </div>

                            <div class="alert-item">
                                <div class="alert-text">
                                    <strong>Require two-step sign-in for managers</strong>
                                    <small>Managers confirm a code from their phone after entering a password.</small>
                                </div>
                                <input type="checkbox" name="require_2fa_managers" id="require_2fa_managers" class="custom-checkbox" checked>
                            </div>
                        </div>
                    </div>

                    <!-- Data & Retention -->
                    <div class="card">
                        <div class="card-header-bar header-blue">DATA & RETENTION</div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Keep production history for</label>
                                    <select name="history_retention" id="history_retention" class="form-control">
                                        <option value="24 months">24 months</option>
                                        <option value="12 months">12 months</option>
                                    </select>
                                </div>
                                <div class="form-group">
                                    <label>Weekly export</label>
                                    <select name="weekly_export_schedule" id="weekly_export_schedule" class="form-control">
                                        <option value="Monday, 06:00 AM">Monday, 06:00 AM</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>

                    <button type="submit" class="btn-save-settings">SAVE SETTINGS</button>
                </div>

                <!-- Right Side Widgets -->
                <div>
                    <!-- System Status Widget -->
                    <div class="card">
                        <div class="card-header-bar header-green">
                            <span>SYSTEM STATUS</span>
                            <small style="color:var(--text-primary); text-transform:none;">All services running</small>
                        </div>
                        <div class="card-body">
                            <div class="status-row">
                                <div><span class="dot-indicator dot-green"></span> <strong>Server</strong></div>
                                <span style="color:var(--accent-green);">Running</span>
                            </div>

                            <div class="status-row" style="flex-direction: column; align-items: flex-start;">
                                <div style="display:flex; justify-content:space-between; width:100%;">
                                    <div><span class="dot-indicator dot-green"></span> <strong>Database</strong></div>
                                    <span style="color:var(--text-secondary);">62% of 50 GB</span>
                                </div>
                                <div class="storage-bar-bg"><div class="storage-bar-fill" style="width: 62%;"></div></div>
                            </div>

                            <div class="status-row">
                                <div><span class="dot-indicator dot-yellow"></span> <strong>Stations</strong></div>
                                <span style="color:var(--accent-yellow); font-weight:bold;">11 of 12 online</span>
                            </div>

                            <div class="status-row">
                                <div><span class="dot-indicator dot-green"></span> <strong>Last backup</strong></div>
                                <span style="color:var(--text-secondary);">Oct 09 · 03:00 AM</span>
                            </div>

                            <div class="status-row">
                                <span style="color:var(--text-secondary);">Version</span>
                                <strong>2.4.1</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Station Connectivity Widget -->
                    <div class="card">
                        <div class="card-header-bar header-blue">STATION CONNECTIVITY</div>
                        <div class="card-body">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>Check-in every (s)</label>
                                    <input type="number" name="checkin_interval_sec" id="checkin_interval_sec" class="form-control" value="10">
                                </div>
                                <div class="form-group">
                                    <label>Offline after (s)</label>
                                    <input type="number" name="offline_threshold_sec" id="offline_threshold_sec" class="form-control" value="60">
                                </div>
                            </div>

                            <div class="warning-box">
                                <strong>Live station alerts will appear when monitoring data is available.</strong>
                            </div>
                        </div>
                    </div>

                    <!-- Settings History Widget -->
                    <div class="card">
                        <div class="card-header-bar header-blue">SETTINGS HISTORY</div>
                        <div class="card-body" id="settings-history-list">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>
            </section>
        </form>
    </main>

    <!-- Inline Script Handling -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadSettings();

            document.getElementById("settingsForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_system_settings.php?action=save_settings", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message);
                    if (data.status === 'success') {
                        loadSettings();
                    }
                })
                .catch(err => console.error("Error saving settings:", err));
            });
        });

        function loadSettings() {
            fetch(BASE_URL + "/backend/process_system_settings.php?action=get_settings")
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success' && data.settings) {
                        const s = data.settings;
                        document.getElementById("plant_name").value = s.plant_name || 'Masters Vineyard Enterprise';
                        document.getElementById("time_zone").value = s.time_zone;
                        document.getElementById("time_format").value = s.time_format;

                        document.getElementById("alert_defect_30m").checked = s.alert_defect_30m == 1;
                        document.getElementById("alert_line_stop_15m").checked = s.alert_line_stop_15m == 1;
                        document.getElementById("alert_oee_under_target").checked = s.alert_oee_under_target == 1;
                        document.getElementById("alert_station_offline").checked = s.alert_station_offline == 1;
                        document.getElementById("alert_weekly_summary").checked = s.alert_weekly_summary == 1;

                        document.getElementById("inactivity_timeout").value = s.inactivity_timeout;
                        document.getElementById("min_password_len").value = s.min_password_len;
                        document.getElementById("password_expiry").value = s.password_expiry;
                        document.getElementById("require_2fa_managers").checked = s.require_2fa_managers == 1;

                        document.getElementById("history_retention").value = s.history_retention;
                        document.getElementById("weekly_export_schedule").value = s.weekly_export_schedule;

                        document.getElementById("checkin_interval_sec").value = s.checkin_interval_sec;
                        document.getElementById("offline_threshold_sec").value = s.offline_threshold_sec;

                        renderHistory(data.history);
                    }
                })
                .catch(err => console.error("Error loading settings:", err));
        }

        function renderHistory(history) {
            const container = document.getElementById("settings-history-list");
            container.innerHTML = "";

            if (!history || history.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.8rem;">No settings changes recorded.</p>';
                return;
            }

            history.forEach(h => {
                container.innerHTML += `
                    <div class="history-item">
                        <div class="history-time">${esc(h.log_timestamp)}</div>
                        <div><strong>${esc(h.actor)}</strong> ${esc(h.action_description)}</div>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
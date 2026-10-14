<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('supervisor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Line & Session Control - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Line Control Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/line_control.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Line & Session Control</h1>
                <p class="subtitle">Set production targets, pause or resume stations, and close out the session when the shift is done.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- Active Session Card -->
        <div class="card">
            <div class="card-header-bar header-blue">ACTIVE SESSION</div>
            <div class="card-body">
                <div class="session-meta-grid">
                    <div>
                        <div class="session-code-title">
                            <h2 id="session-code-text">Loading session…</h2>
                            <span class="badge-status badge-RUNNING" id="session-status-badge">RUNNING</span>
                        </div>
                        <div class="session-info-details">
                            <div>
                                <label>Product</label>
                                <strong id="lbl-product">Not available</strong>
                            </div>
                            <div>
                                <label>Started</label>
                                <strong id="lbl-started">—</strong>
                            </div>
                            <div>
                                <label>Elapsed</label>
                                <strong id="lbl-elapsed">—</strong>
                            </div>
                            <div>
                                <label>Opened by</label>
                                <strong id="lbl-opened">—</strong>
                            </div>
                        </div>
                    </div>

                    <div class="action-controls-box">
                        <label style="font-size:0.75rem; color:var(--text-secondary);">Reason for pausing</label>
                        <select class="form-control-lsc" id="pause_reason_select">
                            <option value="Machine jam">Machine jam</option>
                            <option value="Material shortage">Material shortage</option>
                            <option value="Tooling changeover">Tooling changeover</option>
                            <option value="Scheduled break">Scheduled break</option>
                        </select>

                        <div class="btn-group-session">
                            <button class="btn-gold" id="btn-pause" onclick="toggleSession('PAUSED')">PAUSE LINE</button>
                            <button class="btn-gray-outline" id="btn-resume" onclick="toggleSession('RUNNING')">RESUME LINE</button>
                            <button class="btn-danger-red" id="btn-close" onclick="closeSession()">CLOSE SESSION</button>
                            <button class="btn-gold" id="btn-open-session" style="display:none;" onclick="openSession()">START NEW SESSION</button>
                        </div>

                        <div class="alert-warning-text">
                            ⚠️ <span id="warning-defects-text">2 defect reports still need review before the session can be closed.</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Bottom Grid Layout -->
        <section class="grid-lsc-bottom">
            <!-- Station Controls -->
            <div class="card">
                <div class="card-header-bar header-green">STATION CONTROLS</div>
                <div class="card-body" id="station-controls-list">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>

            <!-- Right Side Column Widgets -->
            <div>
                <!-- Production Targets Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">PRODUCTION TARGETS</div>
                    <div class="card-body">
                        <form id="targetsForm">
                            <div style="margin-bottom: 12px;">
                                <label style="font-size:0.75rem; color:var(--text-secondary); display:block; margin-bottom:5px;">Product</label>
                                <select name="product_name" id="product_select" class="form-control-lsc">
                                    <option value="Vent Caps (A2)">Vent Caps (A2)</option>
                                    <option value="Filter Housings (B1)">Filter Housings (B1)</option>
                                </select>
                            </div>

                            <div style="display:grid; grid-template-columns: 1fr 1fr; gap:12px; margin-bottom: 15px;">
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-secondary); display:block; margin-bottom:5px;">Line target</label>
                                    <input type="number" name="line_target" id="input_line_target" class="form-control-lsc" value="" placeholder="Set target">
                                </div>
                                <div>
                                    <label style="font-size:0.75rem; color:var(--text-secondary); display:block; margin-bottom:5px;">Per station</label>
                                    <input type="number" name="station_target" id="input_station_target" class="form-control-lsc" value="" placeholder="Set target">
                                </div>
                            </div>

                            <button type="submit" class="btn-gold" style="width:100%;">SAVE TARGETS</button>
                        </form>
                    </div>
                </div>

                <!-- Shift Readiness Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">SHIFT READINESS</div>
                    <div class="card-body">
                        <div class="readiness-item">
                            <div class="icon-check-circle">✓</div>
                            <span>All 4 hardware stations online</span>
                        </div>
                        <div class="readiness-item">
                            <div class="icon-check-circle">✓</div>
                            <span>Production target verified</span>
                        </div>
                        <div class="readiness-item">
                            <div class="icon-check-circle">✓</div>
                            <span>Operators checked in (4 of 4)</span>
                        </div>
                        <div class="readiness-item">
                            <div class="icon-warn-circle">!</div>
                            <span id="readiness-defect-msg">Defect queue cleared (2 pending)</span>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Logic -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadLineControlData();

            document.getElementById("targetsForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_line_control.php?action=save_targets", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    alert(data.message || (data.status === 'success' ? 'Changes saved.' : 'Unable to save changes.'));
                    if (data.status === 'success') loadLineControlData();
                });
            });
        });

        function loadLineControlData() {
            fetch(BASE_URL + "/backend/process_line_control.php?action=get_session_data")
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderSession(data.session);
                        renderStations(data.stations);
                        document.getElementById("warning-defects-text").innerText = `${data.pending_defects} defect reports still need review before the session can be closed.`;
                        document.getElementById("readiness-defect-msg").innerText = `Defect queue cleared (${data.pending_defects} pending)`;
                    }
                })
                .catch(err => { alert('Unable to load line/session data. Please refresh and check the server connection.'); console.error("Error loading session data:", err); });
        }

        function renderSession(s) {
            if (!s) return;
            document.getElementById("session-code-text").innerText = s.session_code;
            const badge = document.getElementById("session-status-badge");
            badge.innerText = s.status;
            badge.className = `badge-status badge-${s.status}`;

            const closed = s.status === 'CLOSED';
            document.getElementById("btn-pause").style.display = closed ? "none" : "";
            document.getElementById("btn-resume").style.display = closed ? "none" : "";
            document.getElementById("btn-close").style.display = closed ? "none" : "";
            document.getElementById("btn-open-session").style.display = closed ? "" : "none";

            document.getElementById("lbl-product").innerText = s.product_name;
            document.getElementById("lbl-started").innerText = s.start_time;
            document.getElementById("lbl-elapsed").innerText = `${s.elapsed_hours}h ${String(s.elapsed_minutes || 0).padStart(2, '0')}m`;
            document.getElementById("lbl-opened").innerText = s.opened_by;

            document.getElementById("input_line_target").value = s.line_target;
            document.getElementById("input_station_target").value = s.station_target;
            document.getElementById("product_select").value = s.product_name;
        }

        function renderStations(stations) {
            const container = document.getElementById("station-controls-list");
            container.innerHTML = "";

            stations.forEach(st => {
                let isPaused = st.status === 'PAUSED';
                let btnHtml = isPaused 
                    ? `<button class="btn-stn-resume" onclick="toggleStation('${st.station_code}', 'RUNNING')">Resume</button>`
                    : `<button class="btn-stn-pause" onclick="toggleStation('${st.station_code}', 'PAUSED')">Pause</button>`;

                container.innerHTML += `
                    <div class="station-control-row">
                        <div class="stn-info">
                            <strong>${esc(st.station_code)}</strong>
                            <small>${esc(st.assigned_staff)} · ${esc(st.workstation_name)}</small>
                        </div>
                        <span class="badge-status badge-${esc(st.status)}">${esc(st.status)}</span>
                        <div class="target-input-box">
                            <label>Target</label>
                            <input type="text" class="input-target-sm" value="${esc(st.target_units)}" readonly>
                        </div>
                        ${btnHtml}
                    </div>
                `;
            });
        }

        function toggleSession(status) {
            let reason = document.getElementById("pause_reason_select").value;
            let formData = new FormData();
            formData.append("status", status);
            formData.append("pause_reason", reason);

            fetch(BASE_URL + "/backend/process_line_control.php?action=toggle_session", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => { alert(data.message || 'Request processed.'); if (data.status === 'success') loadLineControlData(); })
            .catch(() => alert('Request failed. Check your connection and try again.'));
        }

        function toggleStation(stnCode, status) {
            let formData = new FormData();
            formData.append("station_code", stnCode);
            formData.append("status", status);

            fetch(BASE_URL + "/backend/process_line_control.php?action=toggle_station", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => { if (data.status === 'success') loadLineControlData(); else alert(data.message || 'Unable to update station.'); })
            .catch(() => alert('Station update failed. Check your connection.'));
        }

        function openSession() {
            if (!confirm("Start a new production session and open a new shift?")) return;
            let formData = new FormData();
            formData.append("product_name", document.getElementById("product_select").value);
            formData.append("line_target", document.getElementById("input_line_target").value);
            formData.append("station_target", document.getElementById("input_station_target").value);

            fetch(BASE_URL + "/backend/process_line_control.php?action=open_session", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => { alert(data.message || 'Request processed.'); if (data.status === 'success') loadLineControlData(); })
            .catch(() => alert('Request failed. Check your connection and try again.'));
        }

        function closeSession() {
            if (confirm("Are you sure you want to close this session?")) {
                toggleSession('CLOSED');
            }
        }
    </script>
</body>
</html>
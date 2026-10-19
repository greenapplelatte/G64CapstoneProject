<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

check_role('supervisor');
$projectOptions = [];
$masterDataError = '';
try {
    $projectOptions = db_all($conn, "SELECT project_type FROM master_project_types WHERE is_active = 1 ORDER BY project_type");
} catch (mysqli_sql_exception $e) {
    error_log('Line-control project lookup failed: ' . $e->getMessage());
    $masterDataError = 'Master Data needs a database update before products can be selected.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Line & Session Control | MVE</title>
    
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
                <div id="station-session-detail" role="region" aria-live="polite" style="display:none;margin:0 16px 16px;padding:14px;border:1px solid #426180;border-radius:8px;background:#17212b;"></div>
            </div>

            <!-- Right Side Column Widgets -->
            <div>
                <!-- Production Targets Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">PRODUCTION TARGETS</div>
                    <div class="card-body">
                        <?php if ($masterDataError !== ''): ?>
                            <p role="alert" style="color:#f85149;"><?= htmlspecialchars($masterDataError, ENT_QUOTES); ?></p>
                        <?php elseif (!$projectOptions): ?>
                            <p role="status" style="color:var(--text-secondary);">No active project types. Ask a manager to add one in Master Data.</p>
                        <?php endif; ?>
                        <form id="targetsForm">
                            <div style="margin-bottom: 12px;">
                                <label style="font-size:0.75rem; color:var(--text-secondary); display:block; margin-bottom:5px;">Product</label>
                                <select name="product_name" id="product_select" class="form-control-lsc" required <?= $projectOptions ? '' : 'disabled' ?>>
                                    <option value="">Choose a project type</option>
                                    <?php foreach ($projectOptions as $project): ?>
                                        <option value="<?= htmlspecialchars($project['project_type'], ENT_QUOTES); ?>"><?= htmlspecialchars($project['project_type'], ENT_QUOTES); ?></option>
                                    <?php endforeach; ?>
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
                            <span id="readiness-stations">Checking configured stations…</span>
                        </div>
                        <div class="readiness-item">
                            <div class="icon-check-circle">✓</div>
                            <span id="readiness-targets">Checking targets…</span>
                        </div>
                        <div class="readiness-item">
                            <div class="icon-check-circle">✓</div>
                            <span id="readiness-operators">Checking active staff assignments…</span>
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
            document.getElementById('targetsForm').addEventListener('input', () => { targetsDirty = true; });
            document.getElementById('targetsForm').addEventListener('change', () => { targetsDirty = true; });
            // Poll server-side assignments and session/station status every 5 seconds.
            setInterval(() => { if (!document.hidden) loadLineControlData(true); }, 5000);

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
                    if (data.status === 'success') { targetsDirty = false; loadLineControlData(); }
                });
            });
        });

        let selectedStationCode = null;
        let currentSession = null;
        let currentStations = [];
        let lineControlLoading = false;
        let targetsDirty = false;

        function loadLineControlData(silent = false) {
            if (lineControlLoading) return;
            lineControlLoading = true;
            fetch(BASE_URL + "/backend/process_line_control.php?action=get_session_data")
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        currentSession = data.session;
                        currentStations = data.stations || [];
                        renderSession(data.session);
                        renderStations(currentStations);
                        renderStationDetail();
                        document.getElementById('readiness-operators').textContent =
                            `${data.assigned_count || 0} active staff assigned across ${data.station_count || 0} stations (not attendance)`;
                        document.getElementById('readiness-stations').textContent =
                            `${currentStations.filter(st => st.master_status === 'Online').length} of ${currentStations.length} stations configured online (not hardware heartbeat)`;
                        document.getElementById('readiness-targets').textContent =
                            data.session && Number(data.session.line_target) > 0 && Number(data.session.station_target) > 0
                              ? 'Production targets configured' : 'Production targets need configuration';
                        document.getElementById("warning-defects-text").innerText = `${data.pending_defects} defect reports still need review before the session can be closed.`;
                        document.getElementById("readiness-defect-msg").innerText = data.pending_defects
                            ? `${data.pending_defects} defect report(s) still need review`
                            : 'Defect queue cleared (0 pending)';
                    }
                })
                .catch(err => { if (!silent) alert('Unable to load line/session data. Please refresh and check the server connection.'); console.error("Error loading session data:", err); })
                .finally(() => { lineControlLoading = false; });
        }

        function renderSession(s) {
            if (!s) {
                document.getElementById('session-code-text').textContent = 'No production session';
                document.getElementById('btn-pause').style.display = 'none';
                document.getElementById('btn-resume').style.display = 'none';
                document.getElementById('btn-close').style.display = 'none';
                document.getElementById('btn-open-session').style.display = '';
                return;
            }
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

            // Do not overwrite a supervisor's in-progress edits during polling.
            if (!targetsDirty) {
                document.getElementById("input_line_target").value = s.line_target;
                document.getElementById("input_station_target").value = s.station_target;
                document.getElementById("product_select").value = s.product_name;
            }
        }

        function renderStations(stations) {
            const container = document.getElementById('station-controls-list');
            container.replaceChildren();
            if (!stations.length) {
                container.textContent = 'No stations are configured. Add stations in Manager → Master Data.';
                return;
            }
            for (const st of stations) {
                const maintenance = st.master_status === 'Maintenance';
                const paused = st.status === 'PAUSED';
                const closed = !currentSession || currentSession.status === 'CLOSED';
                const row = document.createElement('div');
                row.className = 'station-control-row';
                row.style.cursor = 'pointer';
                row.setAttribute('role', 'button');
                row.tabIndex = 0;
                row.setAttribute('aria-label', `View ${st.station_code} in active session`);
                if (selectedStationCode === st.station_code) row.style.outline = '2px solid #4c99e6';
                const info = document.createElement('div');
                info.className = 'stn-info';
                const title = document.createElement('strong');
                title.textContent = st.station_code;
                const sub = document.createElement('small');
                sub.textContent = `${st.line_name || 'Unassigned line'} · ${st.assigned_staff || 'No active staff assigned'} · ${st.workstation_name}`;
                info.append(title, sub);
                const badge = document.createElement('span');
                badge.className = `badge-status badge-${maintenance || closed ? 'PAUSED' : paused ? 'PAUSED' : 'RUNNING'}`;
                badge.textContent = maintenance ? 'MAINTENANCE' : closed ? 'NO SESSION' : currentSession.status === 'PAUSED' ? 'LINE PAUSED' : st.status;
                const target = document.createElement('div');
                target.className = 'target-input-box';
                const targetLabel = document.createElement('label');
                targetLabel.textContent = 'Target';
                const input = document.createElement('input');
                input.type = 'text';
                input.className = 'input-target-sm';
                input.value = st.target_units;
                input.readOnly = true;
                target.append(targetLabel, input);
                const action = document.createElement('button');
                action.type = 'button';
                action.className = paused ? 'btn-stn-resume' : 'btn-stn-pause';
                action.textContent = maintenance ? 'Maintenance' : closed ? 'No session' : paused ? 'Resume' : 'Pause';
                action.disabled = maintenance || closed || (currentSession.status === 'PAUSED' && paused);
                action.addEventListener('click', event => {
                    event.stopPropagation();
                    toggleStation(st.station_code, paused ? 'RUNNING' : 'PAUSED');
                });
                row.append(info, badge, target, action);
                const select = () => { selectedStationCode = st.station_code; renderStations(currentStations); renderStationDetail(); };
                row.addEventListener('click', select);
                row.addEventListener('keydown', event => {
                    if (event.target === row && (event.key === 'Enter' || event.key === ' ')) {
                        event.preventDefault(); select();
                    }
                });
                container.append(row);
            }
        }

        function renderStationDetail() {
            const box = document.getElementById('station-session-detail');
            const st = currentStations.find(item => item.station_code === selectedStationCode);
            if (!st) { box.style.display = 'none'; return; }
            box.replaceChildren();
            box.style.display = 'block';
            const fields = [
                ['Station', st.station_code],
                ['Line', st.line_name || 'Unassigned'],
                ['Workstation', st.workstation_name],
                ['Active staff accounts', st.assigned_staff || 'No active staff assigned'],
                ['Session', currentSession ? currentSession.session_code : 'No session'],
                ['Session state', currentSession ? currentSession.status : 'NOT STARTED'],
                ['Station state', st.master_status === 'Maintenance' ? 'MAINTENANCE' : st.status],
                ['Product', currentSession ? currentSession.product_name : '—'],
                ['Good / NG units', `${st.good_units} / ${st.ng_units}`],
                ['Station target', String(st.target_units)]
            ];
            const heading = document.createElement('strong');
            heading.textContent = 'SELECTED STATION · ACTIVE SESSION';
            heading.style.display = 'block';
            heading.style.marginBottom = '10px';
            box.append(heading);
            for (const [label, value] of fields) {
                const line = document.createElement('div');
                line.style.margin = '5px 0';
                const bold = document.createElement('strong');
                bold.textContent = `${label}: `;
                line.append(bold, document.createTextNode(value));
                box.append(line);
            }
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

        async function openSession() {
            if (!await systemConfirm("Start a new production session and open a new shift?", "Open production session")) return;
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

        async function closeSession() {
            if (await systemConfirm("Are you sure you want to close this session?", "Close production session")) {
                toggleSession('CLOSED');
            }
        }
    </script>
</body>
</html>
<?php
require_once __DIR__ . '/../../config/app.php';   // starts the session with the shared cookie options
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Allow access to Manager and Supervisor roles
if (!in_array($_SESSION['role'] ?? '', ['manager', 'supervisor'], true)) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Master Data - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Page Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/master_data.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Master Data</h1>
                <p class="subtitle">Set up machine configurations and the target defaults that new shifts start with. Supervisors can still adjust a session target on the floor.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- Top Counter Pills Toolbar -->
        <div class="pill-toolbar">
            <button class="btn-counter-pill active">Machines · 12</button>
            <button class="btn-counter-pill">Lines · 3</button>
            <button class="btn-counter-pill">Defect types · 6</button>
            <button class="btn-counter-pill">Shifts · 3</button>
        </div>

        <section class="grid-master-main">
            <!-- Left Column -->
            <div>
                <!-- Machines & Stations Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>MACHINES & STATIONS</span>
                        <small>11 of 12 online</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>STATION</th>
                                    <th>IDEAL CYCLE</th>
                                    <th>RATE / HR</th>
                                    <th>STATUS</th>
                                    <th>CALIBRATED</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="stations-table-body">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                        <div class="table-footer-row">
                            <span>Showing 8 of 12 stations</span>
                            <div style="display: flex; gap: 8px;">
                                <button class="btn-show-all">Show all</button>
                                <button class="btn-add-green">+ Add station</button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shift Schedule Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>SHIFT SCHEDULE</span>
                        <small>Times use Asia/Manila</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>SHIFT</th>
                                    <th>HOURS</th>
                                    <th>TARGET</th>
                                    <th>STATUS</th>
                                </tr>
                            </thead>
                            <tbody id="shifts-table-body">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Target Defaults Widget -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>TARGET DEFAULTS</span>
                        <small>New sessions</small>
                    </div>
                    <div class="card-body">
                        <form id="defaultsForm">
                            <div class="form-row">
                                <div class="form-group">
                                    <label>OEE target (%)</label>
                                    <input type="number" step="0.1" name="oee_target_pct" id="def_oee" class="form-control" value="85.0">
                                </div>
                                <div class="form-group">
                                    <label>Yield target (%)</label>
                                    <input type="number" step="0.1" name="yield_target_pct" id="def_yield" class="form-control" value="98.0">
                                </div>
                            </div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Shift target (units)</label>
                                    <input type="number" name="shift_target_units" id="def_units" class="form-control" value="320">
                                </div>
                                <div class="form-group">
                                    <label>Shift length (hours)</label>
                                    <input type="number" name="shift_length_hours" id="def_hours" class="form-control" value="8">
                                </div>
                            </div>

                            <div class="section-sub-label">OEE factor targets</div>

                            <div class="form-row">
                                <div class="form-group">
                                    <label>Availability</label>
                                    <input type="number" step="0.1" name="availability_target_pct" id="def_avail" class="form-control" value="92.0">
                                </div>
                                <div class="form-group">
                                    <label>Performance</label>
                                    <input type="number" step="0.1" name="performance_target_pct" id="def_perf" class="form-control" value="96.0">
                                </div>
                            </div>

                            <div class="form-group" style="width: 48%; margin-bottom: 15px;">
                                <label>Quality</label>
                                <input type="number" step="0.1" name="quality_target_pct" id="def_qual" class="form-control" value="97.0">
                            </div>

                            <button type="submit" class="btn-save-defaults">SAVE DEFAULTS</button>
                            <p style="font-size: 0.72rem; color: var(--text-secondary); margin-top: 8px;">
                                Changes apply to sessions that start after you save. Sessions already running keep their current target.
                            </p>
                        </form>
                    </div>
                </div>

                <!-- Defect Types Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>DEFECT TYPES</span>
                        <small>Shown to operators</small>
                    </div>
                    <div class="card-body">
                        <div id="defect-types-list"></div>
                        <button type="button" class="btn-add-defect-dash" onclick="addDefectType()">Add defect type</button>
                    </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Page Logic -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadMasterData();

            document.getElementById("defaultsForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_master_data.php?action=update_defaults", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => alert(data.message))
                .catch(err => console.error("Error saving defaults:", err));
            });
        });

        function loadMasterData() {
            fetch(BASE_URL + "/backend/process_master_data.php?action=get_master_data")
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderStationsTable(data.stations);
                        renderTargetDefaults(data.defaults);
                        renderShiftsTable(data.shifts);
                        renderDefectTypes(data.defects || []);
                    }
                })
                .catch(err => console.error("Error loading master data:", err));
        }

        function renderStationsTable(stations) {
            const tbody = document.getElementById("stations-table-body");
            tbody.innerHTML = "";

            stations.forEach(s => {
                let statusClass = `status-text-${s.status}`;
                tbody.innerHTML += `
                    <tr>
                        <td>
                            <span class="station-code">${esc(s.station_code)}</span>
                            <span class="station-sub">${esc(s.line_name)} · ${esc(s.workstation_name)}</span>
                        </td>
                        <td>${esc(s.ideal_cycle_sec)} s</td>
                        <td>${esc(s.rate_per_hr)}</td>
                        <td><span class="${statusClass}">${esc(s.status)}</span></td>
                        <td style="color:var(--text-secondary);">${esc(s.last_calibrated)}</td>
                        <td><button type="button" class="btn-edit-sm" onclick="editStation(${Number(s.id)})">Edit</button>
                            <button type="button" class="btn-edit-sm" onclick="deleteStation(${Number(s.id)})">Delete</button></td>
                    </tr>
                `;
            });
        }


        function postMasterAction(action, values) {
            const formData = new FormData();
            Object.entries(values).forEach(([key, value]) => formData.append(key, value));
            return fetch(BASE_URL + "/backend/process_master_data.php?action=" + action, {method:"POST", body:formData})
                .then(async response => {
                    const data = await response.json();
                    if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Request failed.');
                    return data;
                });
        }

        function editStation(id) {
            fetch(BASE_URL + "/backend/process_master_data.php?action=get_master_data")
                .then(r => r.json()).then(data => {
                    const station = (data.stations || []).find(item => Number(item.id) === Number(id));
                    if (!station) throw new Error('Station not found.');
                    const station_code = prompt('Station code:', station.station_code); if (station_code === null) return;
                    const line_name = prompt('Line name:', station.line_name); if (line_name === null) return;
                    const workstation_name = prompt('Workstation name:', station.workstation_name); if (workstation_name === null) return;
                    const ideal_cycle_sec = prompt('Ideal cycle time (seconds):', station.ideal_cycle_sec); if (ideal_cycle_sec === null) return;
                    const rate_per_hr = prompt('Target rate per hour:', station.rate_per_hr); if (rate_per_hr === null) return;
                    const status = prompt('Status: Online or Maintenance', station.status); if (status === null) return;
                    return postMasterAction('update_station', {id, station_code:station_code.trim(), line_name:line_name.trim(),
                        workstation_name:workstation_name.trim(), ideal_cycle_sec:ideal_cycle_sec.trim(), rate_per_hr:rate_per_hr.trim(), status:status.trim()})
                        .then(result => { alert(result.message); loadMasterData(); });
                }).catch(error => alert(error.message));
        }

        function deleteStation(id) {
            if (!confirm('Delete this station? This cannot be undone.')) return;
            postMasterAction('delete_station', {id})
                .then(result => { alert(result.message); loadMasterData(); })
                .catch(error => alert(error.message));
        }

        function renderDefectTypes(defects) {
            const container = document.getElementById('defect-types-list');
            if (!container) return;
            container.innerHTML = '';
            defects.forEach(defect => {
                const item = document.createElement('div');
                item.className = 'defect-list-item';
                const title = document.createElement('strong');
                title.textContent = defect.category_name;
                const count = document.createElement('span');
                count.className = 'check-active';
                count.textContent = `${defect.count} reports`;
                const edit = document.createElement('button');
                edit.type = 'button'; edit.className = 'btn-edit-sm'; edit.textContent = 'Edit';
                edit.onclick = () => editDefectType(defect);
                const remove = document.createElement('button');
                remove.type = 'button'; remove.className = 'btn-edit-sm'; remove.textContent = 'Delete';
                remove.onclick = () => deleteDefectType(defect.id);
                item.append(title, count, edit, remove);
                container.appendChild(item);
            });
        }

        function addDefectType() {
            const category_name = prompt('New defect type name:');
            if (!category_name || !category_name.trim()) return;
            postMasterAction('add_defect_type', {category_name:category_name.trim()})
                .then(result => { alert(result.message); loadMasterData(); })
                .catch(error => alert(error.message));
        }
        function editDefectType(defect) {
            const category_name = prompt('Defect type name:', defect.category_name);
            if (category_name === null || !category_name.trim()) return;
            postMasterAction('update_defect_type', {id:defect.id, category_name:category_name.trim()})
                .then(result => { alert(result.message); loadMasterData(); })
                .catch(error => alert(error.message));
        }
        function deleteDefectType(id) {
            if (!confirm('Delete this defect type? Existing defect history is not changed.')) return;
            postMasterAction('delete_defect_type', {id})
                .then(result => { alert(result.message); loadMasterData(); })
                .catch(error => alert(error.message));
        }
        function renderTargetDefaults(d) {
            if (!d) return;
            document.getElementById("def_oee").value = d.oee_target_pct;
            document.getElementById("def_yield").value = d.yield_target_pct;
            document.getElementById("def_units").value = d.shift_target_units;
            document.getElementById("def_hours").value = d.shift_length_hours;
            document.getElementById("def_avail").value = d.availability_target_pct;
            document.getElementById("def_perf").value = d.performance_target_pct;
            document.getElementById("def_qual").value = d.quality_target_pct;
        }

        function renderShiftsTable(shifts) {
            const tbody = document.getElementById("shifts-table-body");
            tbody.innerHTML = "";

            shifts.forEach(sh => {
                let statusColor = sh.status === 'Scheduled' ? 'color:var(--accent-green); font-weight:bold;' : 'color:var(--text-secondary);';
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${esc(sh.shift_name)}</strong></td>
                        <td style="color:var(--text-secondary);">${esc(sh.time_range)}</td>
                        <td>${sh.target_units > 0 ? sh.target_units + ' units' : '—'}</td>
                        <td style="${statusColor}">${esc(sh.status)}</td>
                    </tr>
                `;
            });
        }
    </script>
</body>
</html>
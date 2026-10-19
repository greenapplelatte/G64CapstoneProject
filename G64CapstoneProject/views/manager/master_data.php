<?php
require_once __DIR__ . '/../../config/app.php';   // starts the session with the shared cookie options
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

// Allow access to Manager and Supervisor roles (check_role also re-checks the account is still Active)
check_role(['manager', 'supervisor']);
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
    <style>
        .btn-stations-toggle{display:inline-flex;align-items:center;gap:8px;background:transparent;border:1px solid transparent;color:inherit;font:inherit;font-weight:700;padding:4px 10px;margin-left:-10px;border-radius:6px;cursor:pointer}
        .btn-stations-toggle:hover,.btn-stations-toggle[aria-expanded="true"]{background:#30363d;border-color:#484f58}
        .btn-stations-toggle:focus-visible{outline:2px solid #58a6ff;outline-offset:2px}
        .btn-stations-toggle .toggle-hint{font-size:.72rem;font-weight:600;color:var(--text-secondary,#8b949e)}
        .line-stations-row td{padding:0 16px 16px!important;background:#161b22}
        .line-stations-panel{border:1px solid var(--card-border,#30363d);border-radius:8px;padding:12px;display:flex;flex-direction:column;gap:10px}
        .line-stations-list{list-style:none;margin:0;padding:0;display:flex;flex-direction:column}
        .line-stations-list li{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:8px 0;border-bottom:1px solid var(--card-border,#30363d)}
        .line-stations-list li:last-child{border-bottom:0}
        .line-stations-info{display:flex;flex-direction:column;min-width:0}
        .line-stations-actions{display:flex;align-items:center;gap:8px;flex-wrap:wrap;justify-content:flex-end}
        .line-stations-empty{margin:0;font-size:.82rem;color:var(--text-secondary,#8b949e)}
        .line-stations-add{align-self:flex-start}
        .visually-hidden{position:absolute;width:1px;height:1px;margin:-1px;padding:0;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
    </style>
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
            <button type="button" class="btn-counter-pill active" data-master-tab="machines">Machines · <span id="machines-count">0</span></button>
            <button type="button" class="btn-counter-pill" data-master-tab="defects">Defect types · <span id="defects-count">0</span></button>
            <button type="button" class="btn-counter-pill" data-master-tab="projects">Project types · <span id="projects-count">0</span></button>
            <button type="button" class="btn-counter-pill" data-master-tab="shifts">Shifts · <span id="shifts-count">0</span></button>
        </div>
        <div id="master-data-error" class="master-data-error" role="alert" hidden></div>

        <section class="grid-master-main">
            <!-- Left Column -->
            <div>
                <div class="card" data-master-panel="machines">
                    <div class="card-header-bar header-blue">
                        <span>PRODUCTION LINES</span>
                        <small>Available for station and staff assignments</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead><tr><th>LINE</th><th>STATIONS</th><th></th></tr></thead>
                            <tbody id="lines-table-body"></tbody>
                        </table>
                        <div class="table-footer-row">
                            <span>Renaming a line updates linked stations and user assignments.<?php if (($_SESSION['role'] ?? '') === 'manager'): ?> Select a station count to add, edit or remove that line's stations.<?php endif; ?></span>
                            <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                                <button type="button" class="btn-add-green" onclick="addLine()">+ Add line</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <div class="card" data-master-panel="projects" hidden>
                    <div class="card-header-bar header-blue">
                        <span>PROJECT TYPES</span>
                        <small>Available when staff report damaged products</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead><tr><th>PROJECT TYPE</th><th>STATUS</th><th></th></tr></thead>
                            <tbody id="project-types-table-body"></tbody>
                        </table>
                        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                            <div class="table-footer-row">
                                <span>Removing a project type hides it from new reports; report history is preserved.</span>
                                <button type="button" class="btn-add-green" onclick="addProjectType()">+ Add project type</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <div class="card" data-master-panel="machines">
                    <div class="card-header-bar header-blue">
                        <span>WORKSTATIONS</span>
                        <small>Available when staff report damaged products</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead><tr><th>WORKSTATION</th><th>STATUS</th><th></th></tr></thead>
                            <tbody id="workstations-table-body"></tbody>
                        </table>
                        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                            <div class="table-footer-row">
                                <span>Removing a workstation hides it from new reports; report history is preserved.</span>
                                <button type="button" class="btn-add-green" onclick="addWorkstation()">+ Add workstation</button>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Machines & Stations Card -->
                <div class="card" data-master-panel="machines">
                    <div class="card-header-bar header-blue">
                        <span>MACHINES, LINES & OPERATOR ASSIGNMENTS</span>
                        <small id="machines-status">Loading station status...</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="data-table">
                            <thead>
                                <tr>
                                    <th>MACHINE / WORKSTATION</th>
                                    <th>PRODUCTION LINE</th>
                                    <th>ASSIGNED OPERATOR</th>
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
                            <span id="stations-count-label">Loading stations...</span>
                            <div style="display: flex; gap: 8px;">
                                <button type="button" class="btn-show-all" onclick="toggleAllStations()">Show all</button>
                                <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                                    <button type="button" class="btn-add-green" onclick="addStation()">+ Add station</button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Shift Schedule Card -->
                <div class="card" data-master-panel="shifts" hidden>
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
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="shifts-table-body">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                                <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                                    <div class="table-footer-row">
                                        <span>Only scheduled shifts can open new production shift records.</span>
                                        <button type="button" class="btn-add-green" onclick="addShift()">+ Add shift</button>
                                    </div>
                                <?php endif; ?>
                    </div>
                </div>

                <!-- Defect Types Widget -->
                <div class="card" data-master-panel="defects" hidden>
                    <div class="card-header-bar header-blue">
                        <span>DEFECT TYPES</span>
                        <small>Shown to operators</small>
                    </div>
                    <div class="card-body">
                        <div id="defect-types-list"></div>
                        <?php if (($_SESSION['role'] ?? '') === 'manager'): ?>
                            <button type="button" class="btn-add-defect-dash" onclick="addDefectType()">Add defect type</button>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column -->
            <div>
                <!-- Target Defaults Widget -->
                <div class="card" data-master-panel="machines">
                    <div class="card-header-bar header-green">
                        <span>TARGET DEFAULTS</span>
                        <small>New sessions</small>
                    </div>
                    <div class="card-body">
                        <fieldset <?= (($_SESSION['role'] ?? '') === 'manager') ? '' : 'disabled' ?> style="border:0;padding:0;margin:0;min-width:0;">
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
                        </fieldset>
                    </div>
                </div>

            </div>
        </section>
    </main>

    <!-- Page Logic -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            document.querySelectorAll('[data-master-tab]').forEach(button => {
                button.addEventListener('click', () => selectMasterTab(button.dataset.masterTab));
            });
            loadMasterData();

            document.getElementById("defaultsForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_master_data.php?action=update_defaults", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message || 'Could not save target defaults.');
                    systemNotify(data.message, 'success');
                })
                .catch(err => {
                    console.error("Error saving defaults:", err);
                    systemNotify(err.message, 'error');
                });
            });
        });

        function loadMasterData() {
            fetch(BASE_URL + "/backend/process_master_data.php?action=get_master_data")
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message || 'Unable to load master data.');
                    masterLines = data.lines || [];
                    masterShifts = data.shifts || [];
                    const stations = data.stations || [];
                    masterStations = stations;
                    masterStaff = data.staff || [];
                    const defects = data.defects || [];
                    masterProjectTypes = data.project_types || [];
                    masterWorkstations = data.workstations || [];
                    renderStationsTable(stations);
                    renderLines(masterLines);
                    renderProjectTypes(masterProjectTypes);
                    renderWorkstations(masterWorkstations);
                    renderTargetDefaults(data.defaults);
                    renderShiftsTable(masterShifts);
                    renderDefectTypes(defects);
                    document.getElementById('machines-count').textContent = stations.length;
                    document.getElementById('defects-count').textContent = defects.length;
                    document.getElementById('projects-count').textContent = masterProjectTypes.filter(item => Number(item.is_active) === 1).length;
                    document.getElementById('shifts-count').textContent = masterShifts.length;
                    document.getElementById('master-data-error').hidden = true;
                })
                .catch(err => {
                    console.error("Error loading master data:", err);
                    const errorBox = document.getElementById('master-data-error');
                    errorBox.textContent = err.message;
                    errorBox.hidden = false;
                });
        }

        function selectMasterTab(tab) {
            document.querySelector('.grid-master-main').classList.toggle('single-panel', tab !== 'machines');
            document.querySelectorAll('[data-master-tab]').forEach(button => {
                button.classList.toggle('active', button.dataset.masterTab === tab);
            });
            document.querySelectorAll('[data-master-panel]').forEach(panel => {
                panel.hidden = panel.dataset.masterPanel !== tab;
            });
        }

        const canManageMasterData = <?= json_encode(($_SESSION['role'] ?? '') === 'manager') ?>;
        let masterLines = [];
        let masterShifts = [];
        let masterProjectTypes = [];
        let masterWorkstations = [];
        let masterStations = [];
        let masterStaff = [];
        let showAllStations = false;

        function esc(value) {
            const element = document.createElement('span');
            element.textContent = value == null ? '' : String(value);
            return element.innerHTML;
        }

        const expandedLines = new Set();

        function toggleLineStations(id) {
            const key = Number(id);
            if (expandedLines.has(key)) expandedLines.delete(key); else expandedLines.add(key);
            renderLines(masterLines);
        }

        function addStationToLine(lineId) {
            const line = masterLines.find(item => Number(item.id) === Number(lineId));
            if (line) addStation(line.line_name);
        }

        function renderLines(lines) {
            const tbody = document.getElementById('lines-table-body');
            tbody.innerHTML = '';
            lines.forEach(line => {
                const id = Number(line.id);
                const count = Number(line.station_count);
                const expanded = canManageMasterData && expandedLines.has(id);
                const row = document.createElement('tr');
                const actions = canManageMasterData
                    ? `<button type="button" class="btn-edit-sm" onclick="editLine(${id})">Edit</button>
                       <button type="button" class="btn-edit-sm" onclick="deleteLine(${id})">Delete</button>`
                    : '';
                const countCell = canManageMasterData
                    ? `<button type="button" class="btn-stations-toggle" aria-expanded="${expanded}" aria-controls="line-stations-${id}" onclick="toggleLineStations(${id})">
                           <span>${count}</span><span class="toggle-hint" aria-hidden="true">${expanded ? 'Hide ▴' : 'Edit ▾'}</span>
                           <span class="visually-hidden">stations on ${esc(line.line_name)}, ${expanded ? 'hide the list' : 'show and edit the list'}</span>
                       </button>`
                    : String(count);
                row.innerHTML = `<td><strong>${esc(line.line_name)}</strong></td><td>${countCell}</td><td>${actions}</td>`;
                tbody.appendChild(row);

                if (!expanded) return;
                const lineStations = masterStations.filter(station => station.line_name === line.line_name);
                const items = lineStations.map(station => `
                    <li>
                        <div class="line-stations-info">
                            <span class="station-code">${esc(station.station_code)}</span>
                            <span class="station-sub">${esc(station.workstation_name)}</span>
                        </div>
                        <div class="line-stations-actions">
                            <span class="status-text-${esc(station.status)}">${esc(station.status)}</span>
                            <button type="button" class="btn-edit-sm" onclick="editStation(${Number(station.id)})">Edit</button>
                            <button type="button" class="btn-edit-sm" onclick="deleteStation(${Number(station.id)})">Delete</button>
                        </div>
                    </li>`).join('');
                const detail = document.createElement('tr');
                detail.className = 'line-stations-row';
                detail.id = `line-stations-${id}`;
                detail.innerHTML = `<td colspan="3"><div class="line-stations-panel">
                    ${lineStations.length ? `<ul class="line-stations-list">${items}</ul>` : '<p class="line-stations-empty">No stations on this line yet.</p>'}
                    <button type="button" class="btn-add-green line-stations-add" onclick="addStationToLine(${id})">+ Add station to ${esc(line.line_name)}</button>
                </div></td>`;
                tbody.appendChild(detail);
            });
        }

        function renderManagedChoices(items, tbodyId, nameKey, actions) {
            const tbody = document.getElementById(tbodyId);
            tbody.innerHTML = '';
            items.forEach(item => {
                const active = Number(item.is_active) === 1;
                const row = document.createElement('tr');
                const actionButtons = canManageMasterData
                    ? `<button type="button" class="btn-edit-sm" onclick="${actions.edit}(${Number(item.id)})">Edit</button>
                       <button type="button" class="btn-edit-sm" onclick="${actions.toggle}(${Number(item.id)})">${active ? 'Delete' : 'Restore'}</button>`
                    : '';
                row.innerHTML = `<td><strong>${esc(item[nameKey])}</strong></td>
                    <td style="${active ? 'color:var(--accent-green);font-weight:bold;' : 'color:var(--text-secondary);'}">${active ? 'Active' : 'Removed'}</td>
                    <td>${actionButtons}</td>`;
                tbody.appendChild(row);
            });
            if (!items.length) {
                tbody.innerHTML = '<tr><td colspan="3">No entries yet. Add one to make it available in defect reports.</td></tr>';
            }
        }

        function renderProjectTypes(items) {
            renderManagedChoices(items, 'project-types-table-body', 'project_type', {edit:'editProjectType', toggle:'toggleProjectType'});
        }

        function renderWorkstations(items) {
            renderManagedChoices(items, 'workstations-table-body', 'workstation_name', {edit:'editWorkstation', toggle:'toggleWorkstation'});
        }

        function saveManagedChoice(action, payload) {
            return postMasterAction(action, payload).then(result => {
                systemNotify(result.message, 'success');
                if (result.status === 'success') loadMasterData();
            }).catch(error => systemNotify(error.message, 'error'));
        }

        async function addProjectType() {
            const project_type = await systemPrompt('Project type name:', '', 'Add project type');
            if (project_type === null || !project_type.trim()) return;
            saveManagedChoice('add_project_type', {project_type:project_type.trim()});
        }

        async function editProjectType(id) {
            const item = masterProjectTypes.find(entry => Number(entry.id) === Number(id));
            if (!item) return;
            const project_type = await systemPrompt('Project type name:', item.project_type, 'Edit project type');
            if (project_type === null || !project_type.trim()) return;
            saveManagedChoice('update_project_type', {id, project_type:project_type.trim()});
        }

        async function toggleProjectType(id) {
            const item = masterProjectTypes.find(entry => Number(entry.id) === Number(id));
            if (!item) return;
            const action = Number(item.is_active) === 1 ? 'delete_project_type' : 'restore_project_type';
            if (action === 'delete_project_type' && !await systemConfirm('Remove this project type from new defect reports? Existing reports are preserved.')) return;
            saveManagedChoice(action, {id});
        }

        async function addWorkstation() {
            const workstation_name = await systemPrompt('Workstation name:', '', 'Add workstation');
            if (workstation_name === null || !workstation_name.trim()) return;
            saveManagedChoice('add_workstation', {workstation_name:workstation_name.trim()});
        }

        async function editWorkstation(id) {
            const item = masterWorkstations.find(entry => Number(entry.id) === Number(id));
            if (!item) return;
            const workstation_name = await systemPrompt('Workstation name:', item.workstation_name, 'Edit workstation');
            if (workstation_name === null || !workstation_name.trim()) return;
            saveManagedChoice('update_workstation', {id, workstation_name:workstation_name.trim()});
        }

        async function toggleWorkstation(id) {
            const item = masterWorkstations.find(entry => Number(entry.id) === Number(id));
            if (!item) return;
            const action = Number(item.is_active) === 1 ? 'delete_workstation' : 'restore_workstation';
            if (action === 'delete_workstation' && !await systemConfirm('Remove this workstation from new defect reports? Existing reports are preserved.')) return;
            saveManagedChoice(action, {id});
        }

        async function addLine() {
            const line_name = await systemPrompt('Line name (for example, Line 4):', '', 'Add production line');
            if (line_name === null || !line_name.trim()) return;
            postMasterAction('add_line', {line_name: line_name.trim()})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        async function editLine(id) {
            const line = masterLines.find(item => Number(item.id) === Number(id));
            if (!line) return;
            const line_name = await systemPrompt('Line name:', line.line_name, 'Edit production line');
            if (line_name === null || !line_name.trim()) return;
            postMasterAction('update_line', {id, line_name: line_name.trim()})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        async function deleteLine(id) {
            if (!await systemConfirm('Delete this line? It must not have assigned stations or user accounts.')) return;
            postMasterAction('delete_line', {id})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        function renderStationsTable(stations) {
            const tbody = document.getElementById("stations-table-body");
            tbody.innerHTML = "";

            const visibleStations = showAllStations ? stations : stations.slice(0, 8);
            document.getElementById('stations-count-label').textContent = `Showing ${visibleStations.length} of ${stations.length} stations`;
            document.getElementById('machines-status').textContent = `${stations.filter(station => station.status === 'Online').length} of ${stations.length} online`;
            visibleStations.forEach(s => {
                let statusClass = `status-text-${s.status}`;
                const staffOptions = masterStaff.map(member =>
                    `<option value="${Number(member.id)}" ${Number(member.id) === Number(s.assigned_staff_id) ? 'selected' : ''}>${esc(member.full_name)}</option>`
                ).join('');
                tbody.innerHTML += `
                    <tr>
                        <td>
                            <span class="station-code">${esc(s.station_code)}</span>
                            <span class="station-sub">${esc(s.workstation_name)}</span>
                        </td>
                        <td>${esc(s.line_name)}</td>
                        <td>${canManageMasterData
                            ? `<select class="station-staff-select" aria-label="Assign operator to ${esc(s.station_code)}" onchange="assignStationStaff(${Number(s.id)}, this.value)">
                                <option value="">Unassigned</option>${staffOptions}
                               </select>`
                            : esc(s.assigned_staff_name || 'Unassigned')}</td>
                        <td>${esc(s.ideal_cycle_sec)} s</td>
                        <td>${esc(s.rate_per_hr)}</td>
                        <td><span class="${statusClass}">${esc(s.status)}</span></td>
                        <td style="color:var(--text-secondary);">${esc(s.last_calibrated)}</td>
                        <td>${canManageMasterData
                            ? `<button type="button" class="btn-edit-sm" onclick="editStation(${Number(s.id)})">Edit</button>
                               <button type="button" class="btn-edit-sm" onclick="deleteStation(${Number(s.id)})">Delete</button>`
                            : ''}</td>
                    </tr>
                `;
            });
        }

        function toggleAllStations() {
            showAllStations = !showAllStations;
            document.querySelector('.btn-show-all').textContent = showAllStations ? 'Show fewer' : 'Show all';
            fetch(BASE_URL + "/backend/process_master_data.php?action=get_master_data")
                .then(response => response.json())
                .then(data => {
                    if (data.status !== 'success') throw new Error(data.message || 'Unable to load stations.');
                    masterStaff = data.staff || [];
                    masterStations = data.stations || [];
                    renderStationsTable(masterStations);
                })
                .catch(error => systemNotify(error.message, 'error'));
        }

        function nextStationCode() {
            const usedCodes = new Set(masterStations.map(station => String(station.station_code).toUpperCase()));
            let number = 1;
            while (usedCodes.has(`ESP32-STN${String(number).padStart(2, '0')}`)) number += 1;
            return `ESP32-STN${String(number).padStart(2, '0')}`;
        }

        async function addStation(presetLine) {
            if (!masterLines.length) return systemNotify('Add a production line before adding a station.', 'info');
            const station_code = await systemPrompt('Machine / workstation code:', nextStationCode(), 'Add machine station');
            if (station_code === null || !station_code.trim()) return;
            let line_name = typeof presetLine === 'string' ? presetLine : '';
            if (!line_name) {
                line_name = await systemPrompt(`Production line (${masterLines.map(line => line.line_name).join(', ')}):`, masterLines[0].line_name, 'Choose production line');
                if (line_name === null || !line_name.trim()) return;
            }
            const activeWorkstations = masterWorkstations.filter(item => Number(item.is_active) === 1);
            const workstation_name = await systemPrompt('Work process / workstation name:', activeWorkstations[0]?.workstation_name || '', 'Choose workstation');
            if (workstation_name === null || !workstation_name.trim()) return;
            const ideal_cycle_sec = await systemPrompt('Ideal cycle time (seconds):', '30', 'Station target');
            if (ideal_cycle_sec === null) return;
            const rate_per_hr = await systemPrompt('Target rate per hour:', '120', 'Station target');
            if (rate_per_hr === null) return;
            postMasterAction('add_station', {
                station_code: station_code.trim().toUpperCase(), line_name: line_name.trim(),
                workstation_name: workstation_name.trim(), ideal_cycle_sec, rate_per_hr
            }).then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
              .catch(error => systemNotify(error.message, 'error'));
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

        async function editStation(id) {
            fetch(BASE_URL + "/backend/process_master_data.php?action=get_master_data")
                .then(r => r.json()).then(async data => {
                    const station = (data.stations || []).find(item => Number(item.id) === Number(id));
                    if (!station) throw new Error('Station not found.');
                    const availableLines = (data.lines || []).map(item => item.line_name).join(', ');
                    const station_code = await systemPrompt('Machine / workstation code:', station.station_code, 'Edit machine station'); if (station_code === null) return;
                    const line_name = await systemPrompt(`Production line (${availableLines}):`, station.line_name, 'Edit machine station'); if (line_name === null) return;
                    const workstation_name = await systemPrompt('Work process / workstation name:', station.workstation_name, 'Edit machine station'); if (workstation_name === null) return;
                    const ideal_cycle_sec = await systemPrompt('Ideal cycle time (seconds):', station.ideal_cycle_sec, 'Edit station target'); if (ideal_cycle_sec === null) return;
                    const rate_per_hr = await systemPrompt('Target rate per hour:', station.rate_per_hr, 'Edit station target'); if (rate_per_hr === null) return;
                    const status = await systemPrompt('Status: Online or Maintenance', station.status, 'Edit station status'); if (status === null) return;
                    return postMasterAction('update_station', {id, station_code:station_code.trim().toUpperCase(), line_name:line_name.trim(),
                        workstation_name:workstation_name.trim(), ideal_cycle_sec:ideal_cycle_sec.trim(), rate_per_hr:rate_per_hr.trim(), status:status.trim()})
                        .then(result => { systemNotify(result.message, 'success'); loadMasterData(); });
                }).catch(error => systemNotify(error.message, 'error'));
        }

        async function deleteStation(id) {
            if (!await systemConfirm('Delete this station? This cannot be undone.')) return;
            postMasterAction('delete_station', {id})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        function assignStationStaff(stationId, staffId) {
            const targetStation = masterStations.find(station => Number(station.id) === Number(stationId));
            const selectedStaff = masterStaff.find(member => Number(member.id) === Number(staffId));
            const displacedStaff = targetStation && masterStaff.find(member =>
                Number(member.id) === Number(targetStation.assigned_staff_id) && Number(member.id) !== Number(staffId)
            );
            const previousStation = selectedStaff && masterStations.find(station =>
                Number(station.assigned_staff_id) === Number(staffId) && Number(station.id) !== Number(stationId)
            );
            const changes = [];
            if (displacedStaff) changes.push(`${displacedStaff.full_name} will be unassigned from ${targetStation.station_code}`);
            if (previousStation) changes.push(`${selectedStaff.full_name} will be moved from ${previousStation.station_code}`);
            if (changes.length) {
                systemConfirm(`${changes.join(' and ')}. Continue?`, 'Confirm operator reassignment').then(confirmed => {
                    if (confirmed) saveStationStaffAssignment(stationId, staffId);
                    else loadMasterData();
                });
                return;
            }
            saveStationStaffAssignment(stationId, staffId);
        }

        function saveStationStaffAssignment(stationId, staffId) {
            postMasterAction('assign_station_staff', {station_id:stationId, staff_id:staffId})
                .then(result => {
                    systemNotify(result.message, 'success');
                    loadMasterData();
                })
                .catch(error => {
                    systemNotify(error.message, 'error');
                    loadMasterData();
                });
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
                item.append(title, count);
                if (canManageMasterData) {
                    const edit = document.createElement('button');
                    edit.type = 'button'; edit.className = 'btn-edit-sm'; edit.textContent = 'Edit';
                    edit.onclick = () => editDefectType(defect);
                    const remove = document.createElement('button');
                    remove.type = 'button'; remove.className = 'btn-edit-sm'; remove.textContent = 'Delete';
                    remove.onclick = () => deleteDefectType(defect.id);
                    item.append(edit, remove);
                }
                container.appendChild(item);
            });
        }

        async function addDefectType() {
            const category_name = await systemPrompt('Defect type name:', '', 'Add defect type');
            if (!category_name || !category_name.trim()) return;
            postMasterAction('add_defect_type', {category_name:category_name.trim()})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }
        async function editDefectType(defect) {
            const category_name = await systemPrompt('Defect type name:', defect.category_name, 'Edit defect type');
            if (category_name === null || !category_name.trim()) return;
            postMasterAction('update_defect_type', {id:defect.id, category_name:category_name.trim()})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }
        async function deleteDefectType(id) {
            if (!await systemConfirm('Delete this defect type? Existing defect history is not changed.')) return;
            postMasterAction('delete_defect_type', {id})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
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
                const actions = canManageMasterData
                    ? `<button type="button" class="btn-edit-sm" onclick="editShift(${Number(sh.id)})">Edit</button>
                       <button type="button" class="btn-edit-sm" onclick="toggleShift(${Number(sh.id)})">${sh.status === 'Off' ? 'Enable' : 'Disable'}</button>`
                    : '';
                tbody.innerHTML += `<tr>
                    <td><strong>${esc(sh.shift_name)}</strong></td>
                    <td style="color:var(--text-secondary);">${esc(sh.time_range)}</td>
                    <td>${sh.target_units > 0 ? sh.target_units + ' units' : '—'}</td>
                    <td style="${statusColor}">${esc(sh.status)}</td><td>${actions}</td>
                </tr>`;
            });
        }

        async function getShiftValues(existing) {
            const shift_name = await systemPrompt('Shift name:', existing ? existing.shift_name : '', existing ? 'Edit shift' : 'Add shift');
            if (shift_name === null || !shift_name.trim()) return null;
            const start_time = await systemPrompt('Start time (24-hour HH:MM):', existing ? String(existing.start_time).slice(0, 5) : '06:00', 'Shift hours');
            if (start_time === null) return null;
            const end_time = await systemPrompt('End time (24-hour HH:MM):', existing ? String(existing.end_time).slice(0, 5) : '14:00', 'Shift hours');
            if (end_time === null) return null;
            const target_units = await systemPrompt('Target units:', existing ? existing.target_units : '320', 'Shift target');
            if (target_units === null) return null;
            return {shift_name: shift_name.trim(), start_time, end_time, target_units};
        }

        async function addShift() {
            const values = await getShiftValues(null);
            if (!values) return;
            postMasterAction('add_shift', values)
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        async function editShift(id) {
            const shift = masterShifts.find(item => Number(item.id) === Number(id));
            if (!shift) return;
            const values = await getShiftValues(shift);
            if (!values) return;
            postMasterAction('update_shift', {id, ...values})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }

        async function toggleShift(id) {
            const shift = masterShifts.find(item => Number(item.id) === Number(id));
            if (!shift) return;
            const action = shift.status === 'Off' ? 'enable_shift' : 'disable_shift';
            if (action === 'disable_shift' && !await systemConfirm('Disable this shift? Existing shift records will be preserved.')) return;
            postMasterAction(action, {id})
                .then(result => { systemNotify(result.message, 'success'); loadMasterData(); })
                .catch(error => systemNotify(error.message, 'error'));
        }
    </script>
</body>
</html>
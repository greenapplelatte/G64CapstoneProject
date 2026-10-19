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
    <title>User Management - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & User Management Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/user_management.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>User Management</h1>
                <p class="subtitle">Create staff accounts, set roles, and approve access requests. Role permissions are fixed, so changing a role changes what that person can do.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- Metric Summary Bar -->
        <section class="grid-top-stats">
            <div class="stat-card">
                <label>TOTAL ACCOUNTS</label>
                <div class="stat-val" id="stat-total">9</div>
            </div>
            <div class="stat-card">
                <label>ACTIVE</label>
                <div class="stat-val" style="color: var(--accent-green);" id="stat-active">8</div>
            </div>
            <div class="stat-card">
                <label>PENDING REQUESTS</label>
                <div class="stat-val" style="color: var(--accent-yellow);" id="stat-pending">0</div>
            </div>
            <div class="stat-card">
                <label>DISABLED</label>
                <div class="stat-val" style="color: var(--text-secondary);" id="stat-disabled">1</div>
            </div>
        </section>

        <!-- Main Layout Grid -->
        <section class="grid-um-main">
            <!-- Left Primary Column -->
            <div>
                <!-- Staff Accounts Table Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>STAFF ACCOUNTS</span>
                        <small id="accounts-count-badge">9 accounts</small>
                    </div>
                    <div class="card-body">
                        <!-- Filters Toolbar -->
                        <div class="filter-pills-row">
                            <button class="btn-filter-pill active" onclick="filterRole('All')">All · 9</button>
                            <button class="btn-filter-pill" onclick="filterRole('Operator')">Operators · 6</button>
                            <button class="btn-filter-pill" onclick="filterRole('Supervisor')">Supervisors · 2</button>
                            <button class="btn-filter-pill" onclick="filterRole('Manager')">Managers · 1</button>
                        </div>

                        <input type="text" class="search-bar" id="search-input" placeholder="Search by name..." onkeyup="searchUsers()">

                        <table class="um-table">
                            <thead>
                                <tr>
                                    <th>USER</th>
                                    <th>ROLE</th>
                                    <th>ASSIGNMENT</th>
                                    <th>LAST ACTIVE</th>
                                    <th>STATUS</th>
                                    <th></th>
                                </tr>
                            </thead>
                            <tbody id="users-table-body">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>

                <!-- Recent Access Changes Log Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>RECENT ACCESS CHANGES</span>
                        <small>Last 7 days</small>
                    </div>
                    <div class="card-body" id="access-changes-list">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>

            <!-- Right Side Column Widgets -->
            <div>
                <!-- Access Requests Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>ACCESS REQUESTS</span>
                        <span style="background:var(--brand-orange); color:#000; font-weight:bold; padding:2px 8px; border-radius:10px; font-size:0.75rem; display:none;" id="req-badge">0</span>
                    </div>
                    <div class="card-body" id="requests-list">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

                <!-- Add User Form Widget -->
                <div class="card">
                    <div class="card-header-bar header-green">ADD USER</div>
                    <div class="card-body">
                        <form id="addUserForm">
                            <div class="form-group-um">
                                <label>Username</label>
                                <input type="text" name="username" class="form-control-um" placeholder="For example Staff9" required>
                            </div>

                            <div class="form-group-um">
                                <label>Role</label>
                                <select name="role" class="form-control-um">
                                    <option value="Operator">Operator</option>
                                    <option value="Supervisor">Supervisor</option>
                                    <option value="Manager">Manager</option>
                                </select>
                            </div>

                            <div class="form-group-um">
                                <label>Assignment</label>
                                <select name="assignment" id="assignment-select" class="form-control-um"></select>
                            </div>

                            <div class="form-group-um" id="station-group">
                                <label>Station (operators only)</label>
                                <select name="assigned_station" id="station-select" class="form-control-um"></select>
                            </div>

                            <button type="submit" class="btn-create-account">CREATE ACCOUNT</button>
                            <p style="font-size:0.72rem; color:var(--text-secondary); margin-top:8px;">
                                A temporary password is shown once after the account is created. The person must change it at first login.
                            </p>
                        </form>
                    </div>
                </div>

                <!-- Role Permissions Reference Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">ROLE PERMISSIONS</div>
                    <div class="card-body" style="padding: 0;">
                        <table class="perm-table">
                            <thead>
                                <tr>
                                    <th>CAN DO</th>
                                    <th>OPERATOR</th>
                                    <th>SUPERVISOR</th>
                                    <th>MANAGER</th>
                                </tr>
                            </thead>
                            <tbody>
                                <tr>
                                    <td>Report defects</td>
                                    <td class="perm-yes">Yes</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-no">—</td>
                                </tr>
                                <tr>
                                    <td>Review defect queue</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                    <td class="perm-no">—</td>
                                </tr>
                                <tr>
                                    <td>Control line & session</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                    <td class="perm-no">—</td>
                                </tr>
                                <tr>
                                    <td>Close out shifts</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                    <td class="perm-no">—</td>
                                </tr>
                                <tr>
                                    <td>View OEE analytics</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                </tr>
                                <tr>
                                    <td>Edit master data</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                </tr>
                                <tr>
                                    <td>Manage users</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                </tr>
                                <tr>
                                    <td>System settings</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-no">—</td>
                                    <td class="perm-yes">Yes</td>
                                </tr>
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Secure account deactivation confirmation -->
    <div id="deactivate-modal" role="dialog" aria-modal="true" aria-labelledby="deactivate-title" hidden style="position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.7);display:none;align-items:center;justify-content:center;padding:20px;">
      <div style="width:100%;max-width:430px;border:1px solid #3d4856;background:#20262d;color:#f3f4f6;border-radius:14px;padding:24px;box-shadow:0 18px 50px rgba(0,0,0,.4)">
        <h2 id="deactivate-title" style="margin:0 0 10px;font-size:20px">Deactivate account</h2>
        <p id="deactivate-summary" style="color:#cbd5e1;line-height:1.5"></p>
        <p style="font-size:13px;color:#f2c663">Account access will be updated without deleting production history. Your manager password is required.</p>
        <form id="deactivate-form">
          <label for="deactivate-password" style="display:block;margin-bottom:7px">Your manager password</label>
          <input type="password" id="deactivate-password" autocomplete="current-password" required class="form-control-um" style="width:100%;box-sizing:border-box" />
          <p id="deactivate-error" role="alert" style="color:#ff8e88;font-size:13px" hidden></p>
          <div style="display:flex;justify-content:flex-end;gap:10px;margin-top:22px">
            <button type="button" id="deactivate-cancel" class="btn-edit-user">Cancel</button>
            <button type="submit" id="deactivate-submit" class="btn-edit-user" style="background:#b42318;color:white">Deactivate account</button>
          </div>
        </form>
      </div>
    </div>

    <!-- Inline Logic -->
    <script>
        let allUsers = [];
        let allStations = [];
        let allLines = [];
        const CURRENT_USER_ID = <?= (int)($_SESSION['user_id'] ?? 0) ?>;
        let selectedDeactivateId = null;
        let pendingAccountAction = null;

        function stationsForLine(line) {
            return allStations.filter(st => st.line_name === line);
        }
        function stationOptionsHtml(line, selected) {
            const list = stationsForLine(line);
            if (!list.length) return '<option value="">No stations on this line</option>';
            return list.map(st => `<option value="${esc(st.station_code)}"${st.station_code === selected ? ' selected' : ''}>${esc(st.station_code)} · ${esc(st.workstation_name)}</option>`).join('');
        }
        function refreshStationPicker() {
            const form = document.getElementById("addUserForm");
            const isOperator = form.elements['role'].value === 'Operator';
            document.getElementById("station-group").style.display = isOperator ? '' : 'none';
            document.getElementById("station-select").innerHTML = stationOptionsHtml(form.elements['assignment'].value, '');
        }
        function renderAssignmentOptions(selected = '') {
            const select = document.getElementById('assignment-select');
            if (!select) return;
            select.innerHTML = allLines.map(line =>
                `<option value="${esc(line.line_name)}"${line.line_name === selected ? ' selected' : ''}>${esc(line.line_name)}</option>`
            ).join('') + `<option value="All Lines"${selected === 'All Lines' ? ' selected' : ''}>All Lines</option>`;
            if (!selected && allLines.length) select.value = allLines[0].line_name;
        }

        document.addEventListener("DOMContentLoaded", () => {
            loadUsersData();
            const addForm = document.getElementById("addUserForm");
            addForm.elements['role'].addEventListener('change', refreshStationPicker);
            addForm.elements['assignment'].addEventListener('change', refreshStationPicker);

            // Bind the secure deactivation dialog controls. Without these handlers,
            // submitting the password form only reloads the page.
            document.getElementById('deactivate-form').addEventListener('submit', submitDeactivate);
            document.getElementById('deactivate-cancel').addEventListener('click', closeDeactivateModal);
            document.getElementById('deactivate-modal').addEventListener('click', function(event) {
                if (event.target === this) closeDeactivateModal();
            });
            document.addEventListener('keydown', function(event) {
                if (event.key === 'Escape' && selectedDeactivateId !== null) closeDeactivateModal();
            });

            document.getElementById("addUserForm").addEventListener("submit", function(e) {
                e.preventDefault();
                let formData = new FormData(this);

                fetch(BASE_URL + "/backend/process_user_management.php?action=create_user", {
                    method: "POST",
                    body: formData
                })
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        const extra = data.temporary_password ? "\\nTemporary password: " + data.temporary_password + "\\nCopy it now; it will not be shown again." : "";
                        alert(data.message + extra);
                        this.reset();
                        loadUsersData();   // also rebuilds the station picker
                    } else alert(data.message || 'Could not create account.');
                })
                .catch(err => console.error("Error creating user:", err));
            });
        });

        function loadUsersData() {
            fetch(BASE_URL + "/backend/process_user_management.php?action=get_users_data")
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        allUsers = data.users;
                        allStations = data.stations || [];
                        allLines = data.lines || [];
                        renderAssignmentOptions(document.getElementById('assignment-select').value);
                        refreshStationPicker();
                        updateUserMetrics(allUsers, data.requests || []);
                        renderUserTable(allUsers);
                        renderRequests(data.requests);
                        renderLogs(data.logs);
                    }
                })
                .catch(err => console.error("Error loading user management data:", err));
        }

        function updateUserMetrics(users, requests) {
            const total = users.length;
            const active = users.filter(u => (u.status || '').toLowerCase() === 'active').length;
            const disabled = users.filter(u => (u.status || '').toLowerCase() !== 'active').length;
            document.getElementById('stat-total').textContent = total;
            document.getElementById('stat-active').textContent = active;
            document.getElementById('stat-disabled').textContent = disabled;
            document.getElementById('stat-pending').textContent = requests.length;
            document.getElementById('accounts-count-badge').textContent = `${total} accounts`;
            const counts = {
                All: total,
                Operator: users.filter(u => u.role === 'staff').length,
                Supervisor: users.filter(u => u.role === 'supervisor').length,
                Manager: users.filter(u => u.role === 'manager').length
            };
            document.querySelectorAll('.btn-filter-pill').forEach(btn => {
                const label = btn.textContent.trim();
                if (label.startsWith('All')) btn.textContent = `All · ${counts.All}`;
                else if (label.startsWith('Operators')) btn.textContent = `Operators · ${counts.Operator}`;
                else if (label.startsWith('Supervisors')) btn.textContent = `Supervisors · ${counts.Supervisor}`;
                else if (label.startsWith('Managers')) btn.textContent = `Managers · ${counts.Manager}`;
            });
        }

        function renderUserTable(users) {
            const tbody = document.getElementById("users-table-body");
            tbody.innerHTML = "";

            users.forEach(u => {
                let roleClass = u.role === 'staff' ? 'Operator' : (u.role === 'supervisor' ? 'Supervisor' : 'Manager');
                let displayRole = roleClass;
                let initials = u.full_name ? u.full_name.substring(0, 2).toUpperCase() : 'U';
                const isSelf = Number(u.id) === CURRENT_USER_ID;
                const isDisabled = String(u.status).toLowerCase() !== 'active';
                const deactivateBtn = isDisabled
                    ? `<button type="button" class="btn-edit-user" onclick="reactivateUser(${Number(u.id)})" title="Reactivate after manager password verification" style="color:#39b67a">Reactivate</button>`
                    : `<button type="button" class="btn-edit-user" ${isSelf || isDisabled ? 'disabled aria-disabled="true"' : `onclick="deleteUser(${Number(u.id)})"`} title="${isSelf ? 'You cannot deactivate your own account' : isDisabled ? 'Account is already disabled' : 'Deactivate account after password verification'}" style="color:${isSelf || isDisabled ? '#89919b' : '#ff7770'};opacity:${isSelf || isDisabled ? '.5' : '1'};cursor:${isSelf || isDisabled ? 'not-allowed' : 'pointer'}">Deactivate</button>`;

                tbody.innerHTML += `
                    <tr>
                        <td>
                            <div class="user-cell">
                                <div class="avatar-sm">${esc(initials)}</div>
                                <strong>${esc(u.full_name)}</strong>
                            </div>
                        </td>
                        <td><span class="role-badge role-${roleClass}">${displayRole}</span></td>
                        <td style="color:var(--text-secondary);">${esc(u.assignment || 'Not assigned')}${u.role === 'staff' ? '<br><small>' + esc(u.assigned_station || 'No station assigned') + '</small>' : ''}</td>
                        <td style="color:var(--text-secondary);">${esc(u.last_active || 'Now')}</td>
                        <td><span class="status-${esc(u.status)}">${esc(u.status)}</span></td>
                        <td><button type="button" class="btn-edit-user" onclick="editUser(${Number(u.id)})">Edit</button>
                            <button type="button" class="btn-edit-user" onclick="resetPassword(${Number(u.id)})">Reset password</button>
                            ${deactivateBtn}</td>
                    </tr>
                `;
            });
        }

        function renderRequests(requests) {
            const container = document.getElementById("requests-list");
            container.innerHTML = "";
            const requestBadge = document.getElementById("req-badge");
            requestBadge.innerText = requests.length;
            requestBadge.style.display = requests.length ? '' : 'none';

            if (!requests || requests.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.8rem;">No pending access requests.</p>';
                return;
            }

            requests.forEach(r => {
                container.innerHTML += `
                    <div class="request-item">
                        <div class="request-meta">
                            <strong>${esc(r.username)}</strong>
                            <span class="pill-op">${esc(r.requested_role)}</span>
                        </div>
                        <div class="request-sub">${esc(r.assignment_line)} · requested by ${esc(r.requested_by)} on ${esc(r.request_date)}</div>
                        ${String(r.requested_role).toLowerCase() === 'operator' ? `<select id="req-station-${Number(r.id)}" class="form-control-um" style="margin:6px 0;">${stationOptionsHtml(r.assignment_line, '')}</select>` : ''}
                        <div class="request-btn-row">
                            <button class="btn-approve" onclick="handleDecision(${esc(r.id)}, 'Approved')">Approve</button>
                            <button class="btn-decline" onclick="handleDecision(${esc(r.id)}, 'Declined')">Decline</button>
                        </div>
                    </div>
                `;
            });
        }

        function renderLogs(logs) {
            const container = document.getElementById("access-changes-list");
            container.innerHTML = "";

            logs.forEach(l => {
                container.innerHTML += `
                    <div class="change-item">
                        <div class="change-time">${esc(l.log_timestamp)}</div>
                        <div><strong>${esc(l.actor)}</strong> ${esc(l.action_description)}</div>
                    </div>
                `;
            });
        }

        function handleDecision(reqId, decision) {
            let formData = new FormData();
            formData.append("request_id", reqId);
            formData.append("decision", decision);
            const pick = document.getElementById("req-station-" + reqId);
            if (pick) formData.append("assigned_station", pick.value);

            fetch(BASE_URL + "/backend/process_user_management.php?action=handle_request", {
                method: "POST",
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                if (data.status !== 'success') alert(data.message);
                else if (data.temporary_password) {
                    alert(data.message + "\nUsername: " + data.username + "\nTemporary password: " + data.temporary_password + "\nCopy it now and give it to the user; it will not be shown again.");
                }
                loadUsersData();
            }).catch(error => alert(error.message));
        }


        function postUserAction(action, values) {
            const formData = new FormData();
            Object.entries(values).forEach(([key, value]) => formData.append(key, value));
            return fetch(BASE_URL + "/backend/process_user_management.php?action=" + action, {
                method: "POST", body: formData
            }).then(async response => {
                const data = await response.json();
                if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Request failed.');
                return data;
            });
        }

        async function editUser(id) {
            const user = allUsers.find(item => Number(item.id) === Number(id));
            if (!user) return alert('User not found.');
            const username = await systemPrompt('Username:', user.username || '', 'Edit user');
            if (username === null) return;
            const full_name = await systemPrompt('Full name:', user.full_name || '', 'Edit user');
            if (full_name === null) return;
            const currentRole = user.role === 'staff' ? 'staff' : user.role;
            const roleInput = await systemPrompt('Role: staff, supervisor, or manager', currentRole, 'Edit user role');
            if (roleInput === null) return;
            const assignment = await systemPrompt('Assignment: ' + allLines.map(line => line.line_name).concat('All Lines').join(', '), user.assignment || '', 'Edit user assignment');
            if (assignment === null) return;
            let station = '';
            if (roleInput.trim().toLowerCase() === 'staff') {
                const codes = stationsForLine(assignment.trim()).map(st => st.station_code).join(', ');
                station = await systemPrompt('Station for this operator (' + codes + '):', user.assigned_station || '', 'Assign operator station');
                if (station === null) return;
            }
            const status = user.status; // Account activation state is managed by the password-confirmed action only.
            postUserAction('update_user', {id, username: username.trim(), full_name: full_name.trim(),
                role: roleInput.trim().toLowerCase(), assignment: assignment.trim(),
                assigned_station: station.trim(), status: status.trim()})
                .then(data => { alert(data.message); loadUsersData(); })
                .catch(error => alert(error.message));
        }

        async function resetPassword(id) {
            const user = allUsers.find(item => Number(item.id) === Number(id));
            if (!user || !await systemConfirm(`Reset the password for "${user.username}"? Their current password will stop working.`, 'Reset password')) return;
            postUserAction('reset_password', {id})
                .then(data => {
                    alert(data.message + "\nUsername: " + data.username + "\nTemporary password: " + data.temporary_password +
                          "\nIt will not be shown again. The user must choose a new password at next sign-in.");
                    loadUsersData();
                })
                .catch(error => alert(error.message));
        }

        function closeDeactivateModal() {
            const modal = document.getElementById('deactivate-modal');
            modal.style.display = 'none';
            modal.hidden = true;
            selectedDeactivateId = null;
            pendingAccountAction = null;
            document.getElementById('deactivate-form').reset();
            document.getElementById('deactivate-error').hidden = true;
        }

        function deleteUser(id) {
            const user = allUsers.find(item => Number(item.id) === Number(id));
            if (!user || Number(id) === CURRENT_USER_ID || String(user.status).toLowerCase() !== 'active') return;
            selectedDeactivateId = Number(id);
            pendingAccountAction = 'delete_user';
            document.getElementById('deactivate-title').textContent = 'Deactivate account';
            document.getElementById('deactivate-submit').textContent = 'Deactivate account';
            document.getElementById('deactivate-submit').style.background = '#b42318';
            document.getElementById('deactivate-summary').textContent = `Deactivate "${user.full_name || user.username}" (${user.username})? This will block the account from signing in.`;
            const modal = document.getElementById('deactivate-modal');
            modal.hidden = false;
            modal.style.display = 'flex';
            document.getElementById('deactivate-password').focus();
        }

        function reactivateUser(id) {
            const user = allUsers.find(item => Number(item.id) === Number(id));
            if (!user || String(user.status).toLowerCase() !== 'disabled') return;
            selectedDeactivateId = Number(id);
            pendingAccountAction = 'reactivate_user';
            document.getElementById('deactivate-title').textContent = 'Reactivate account';
            document.getElementById('deactivate-submit').textContent = 'Reactivate account';
            document.getElementById('deactivate-submit').style.background = '#168653';
            document.getElementById('deactivate-summary').textContent = `Reactivate "${user.full_name || user.username}" (${user.username})? This will restore sign-in access to the existing account.`;
            const modal = document.getElementById('deactivate-modal');
            modal.hidden = false;
            modal.style.display = 'flex';
            document.getElementById('deactivate-error').hidden = true;
            document.getElementById('deactivate-password').focus();
        }

        async function submitDeactivate(event) {
            event.preventDefault();
            if (!selectedDeactivateId) return;
            const button = document.getElementById('deactivate-submit');
            const error = document.getElementById('deactivate-error');
            const password = document.getElementById('deactivate-password').value;
            if (!password) return;
            button.disabled = true;
            button.textContent = 'Verifying...';
            error.hidden = true;
            try {
                const data = await postUserAction(pendingAccountAction, {id: selectedDeactivateId, manager_password: password});
                closeDeactivateModal();
                await loadUsersData();
                alert(data.message);
            } catch (e) {
                error.textContent = e.message;
                error.hidden = false;
            } finally {
                button.disabled = false;
                button.textContent = pendingAccountAction === 'reactivate_user' ? 'Reactivate account' : 'Deactivate account';
            }
        }

        function filterRole(roleName) {
            document.querySelectorAll(".btn-filter-pill").forEach(btn => {
                btn.classList.remove("active");
                if (btn.innerText.includes(roleName)) btn.classList.add("active");
            });

            if (roleName === 'All') {
                renderUserTable(allUsers);
            } else {
                let filtered = allUsers.filter(u => {
                    let r = u.role === 'staff' ? 'Operator' : (u.role === 'supervisor' ? 'Supervisor' : 'Manager');
                    return r === roleName;
                });
                renderUserTable(filtered);
            }
        }

        function searchUsers() {
            let term = document.getElementById("search-input").value.toLowerCase();
            let filtered = allUsers.filter(u => u.full_name.toLowerCase().includes(term));
            renderUserTable(filtered);
        }
    </script>
</body>
</html>
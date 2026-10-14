<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('staff');
$staffContext = db_one($conn, "SELECT assigned_station FROM users WHERE id = ?", "i", [(int)$_SESSION['user_id']]);
$staffStation = trim($staffContext['assigned_station'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Activity Logs - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/activity_logs.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>My Activity Logs</h1>
                <p class="subtitle">Your personal record of shifts, counted units and defect reports. Entries are saved automatically and can't be edited.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
                <div style="color:var(--text-secondary)">Station <strong><?= htmlspecialchars($staffStation !== '' ? $staffStation : 'Not assigned', ENT_QUOTES); ?></strong></div>
            </div>
        </header>

        <!-- Metric Summary Cards -->
        <section class="grid-top">
            <div class="metric-card">
                <div class="card-title">Shifts this week</div>
                <div class="card-val" style="color: var(--accent-green);" id="stat-shifts">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Good units counted</div>
                <div class="card-val" style="color: var(--accent-yellow);" id="stat-good">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Defects reported</div>
                <div class="card-val" style="color: var(--brand-orange);" id="stat-defects">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Average yield</div>
                <div class="card-val" style="color: var(--accent-green);" id="stat-yield">0%</div>
            </div>
        </section>

        <!-- Main Layout Grid -->
        <section class="grid-main">
            <!-- Left Side: Activity Table -->
            <div class="card">
                <div class="card-header-bar header-blue">ACTIVITY LOG</div>
                <div class="card-body">
                    <!-- Filters Toolbar -->
                    <div class="filter-toolbar">
                        <div class="filter-group">
                            <label>Date</label>
                            <select class="form-control" id="filter-date">
                                <option value="today">Today</option>
                                <option value="week">This Week</option>
                                <option value="month">This Month</option>
                                <option value="all">All dates</option>
                            </select>
                        </div>
                        <div class="filter-group">
                            <label>From</label>
                            <input type="date" class="form-control" id="filter-from">
                        </div>
                        <div class="filter-group">
                            <label>To</label>
                            <input type="date" class="form-control" id="filter-to">
                        </div>
                        <div class="filter-group">
                            <label>Type</label>
                            <select class="form-control" id="filter-type">
                                <option value="all">All activity</option>
                                <option value="Count">Count</option>
                                <option value="Defect">Defect</option>
                                <option value="Shift">Shift</option>
                                <option value="System">System</option>
                            </select>
                        </div>
                    </div>

                    <button class="btn-export" onclick="exportLogsCSV()">📥 Export</button>

                    <!-- Table -->
                    <table class="log-table">
                        <thead>
                            <tr>
                                <th>WHEN</th>
                                <th>TYPE</th>
                                <th>DETAILS</th>
                                <th>STATION</th>
                            </tr>
                        </thead>
                        <tbody id="log-table-body">
                            <!-- Populated dynamically via JS -->
                        </tbody>
                    </table>

                    <!-- Pagination -->
                    <div class="pagination-row">
                        <span id="pagination-text">Loading…</span>
                        <div class="pagination-btns">
                            <button class="btn-page" id="btn-prev" type="button">Previous</button>
                            <button class="btn-page active" id="btn-next" type="button">Next</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Side: Shift History -->
            <div class="card">
                <div class="card-header-bar header-green">SHIFT HISTORY</div>
                <div class="card-body" id="shift-history-list">
                    <!-- Populated dynamically via JS -->
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Script Handling -->
    <script>
        let logPage = 1, logPages = 1;

        function logQuery() {
            const q = new URLSearchParams({
                range: document.getElementById('filter-date').value,
                type: document.getElementById('filter-type').value,
                page: logPage
            });
            const from = document.getElementById('filter-from').value;
            const to = document.getElementById('filter-to').value;
            if (from) q.set('from', from);
            if (to) q.set('to', to);
            return q;
        }

        function loadLogs() {
            const q = logQuery(); q.set('action', 'logs');
            fetch(BASE_URL + '/backend/get_activity_data.php?' + q.toString())
                .then(res => res.json())
                .then(data => { if (data.status === 'success') renderLogsTable(data); })
                .catch(err => console.error("Error loading logs:", err));
        }

        document.addEventListener("DOMContentLoaded", () => {
            loadActivityData();
            ['filter-date', 'filter-type', 'filter-from', 'filter-to'].forEach(id =>
                document.getElementById(id).addEventListener('change', () => {
                    // picking a custom date overrides the quick range
                    if (id === 'filter-date') {
                        document.getElementById('filter-from').value = '';
                        document.getElementById('filter-to').value = '';
                    }
                    logPage = 1; loadLogs();
                }));
            document.getElementById('btn-prev').addEventListener('click', () => { if (logPage > 1) { logPage--; loadLogs(); } });
            document.getElementById('btn-next').addEventListener('click', () => { if (logPage < logPages) { logPage++; loadLogs(); } });
        });

        function loadActivityData() {
            fetch(BASE_URL + '/backend/get_activity_data.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderStats(data.stats);
                        renderLogsTable({logs: data.logs, total: data.log_total, page: data.log_page, pages: data.log_pages, per_page: 10});
                        renderShiftHistory(data.shifts);
                    }
                })
                .catch(err => console.error("Error loading activity logs:", err));
        }

        function renderStats(stats) {
            document.getElementById('stat-shifts').innerText = stats.shifts_this_week;
            document.getElementById('stat-good').innerText = stats.good_units;
            document.getElementById('stat-defects').innerText = stats.defects_reported;
            document.getElementById('stat-yield').innerText = stats.average_yield;
        }

        function renderLogsTable(data) {
            const logs = data.logs || [];
            logPage = data.page; logPages = data.pages;
            const tbody = document.getElementById('log-table-body');
            tbody.innerHTML = '';
            document.getElementById('btn-prev').disabled = logPage <= 1;
            document.getElementById('btn-next').disabled = logPage >= logPages;

            if (logs.length === 0) {
                tbody.innerHTML = '<tr><td colspan="4" style="text-align:center; color:var(--text-secondary);">No log activity for this filter.</td></tr>';
                document.getElementById('pagination-text').innerText = 'Showing 0 of 0 entries';
                return;
            }

            logs.forEach(row => {
                tbody.innerHTML += `
                    <tr>
                        <td><strong>${esc(row.formatted_time)}</strong><br><small style="color:var(--text-secondary);">${esc(row.formatted_date)}</small></td>
                        <td><span class="pill-type pill-${esc(row.log_type)}">${esc(row.log_type)}</span></td>
                        <td>${esc(row.details)}</td>
                        <td style="color:var(--text-secondary);">${esc(row.station_id)}</td>
                    </tr>
                `;
            });

            const first = (data.page - 1) * data.per_page + 1;
            document.getElementById('pagination-text').innerText =
                `Showing ${first}–${first + logs.length - 1} of ${data.total} entries`;
        }

        function renderShiftHistory(shifts) {
            const container = document.getElementById('shift-history-list');
            container.innerHTML = '';

            shifts.forEach(shift => {
                container.innerHTML += `
                    <div class="shift-item">
                        <div class="shift-header-line">
                            <strong>${esc(shift.formatted_date)}</strong>
                            <span class="shift-status-pill status-${esc(shift.status)}">${esc(shift.status)}</span>
                        </div>
                        <div class="shift-time">${esc(shift.formatted_time)}</div>
                        <div class="shift-metrics">
                            <span class="shift-good">${esc(shift.good_units)} good</span>
                            <span class="shift-ng">${esc(shift.ng_units)} NG</span>
                            <span class="shift-yield">${esc(shift.yield_rate)}% yield</span>
                        </div>
                    </div>
                `;
            });
        }

        function exportLogsCSV() {
            const q = logQuery(); q.delete('page'); q.set('action', 'export_csv');
            window.location.href = BASE_URL + '/backend/get_activity_data.php?' + q.toString();
        }
    </script>
</body>
</html>
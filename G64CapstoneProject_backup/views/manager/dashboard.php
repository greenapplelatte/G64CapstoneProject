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
    <title>Executive OEE Dashboard - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Manager Dashboard Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/manager_dashboard.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Executive OEE Dashboard</h1>
                <p class="subtitle">Plant efficiency across all lines. See where output is lost, which defects keep coming back, and what needs a decision.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- Period Selection Pills -->
        <div class="period-toolbar">
            <span class="period-label">PERIOD</span>
            <button class="btn-period">7 days</button>
            <button class="btn-period active">30 days</button>
            <button class="btn-period">Quarter</button>
            <button class="btn-period">Year</button>
            <span class="date-range-badge">Current reporting period</span>
        </div>

        <section class="grid-manager-main">
            <!-- Left Primary Column -->
            <div>
                <!-- Plant OEE Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>PLANT OEE</span>
                        <small>ALL LINES · 30 DAYS</small>
                    </div>
                    <div class="card-body">
                        <div class="oee-overview-container">
                            <div class="gauge-box">
                                <svg class="gauge-svg" viewBox="0 0 140 140">
                                    <circle class="gauge-bg" cx="70" cy="70" r="60"></circle>
                                    <circle class="gauge-fill" cx="70" cy="70" r="60" id="gauge-circle"></circle>
                                </svg>
                                <div class="gauge-center">
                                    <div class="gauge-val" id="oee-val">—</div>
                                    <div class="gauge-lbl">OEE</div>
                                </div>
                            </div>
                            <div class="oee-summary-text">
                                <p id="oee-comparison">Comparison unavailable until verified historical OEE data exists.</p>
                                <p style="color: var(--text-secondary); margin-top: 6px;">
                                    OEE factors will appear when reliable runtime and cycle-time measurements are recorded.
                                </p>
                            </div>
                        </div>

                        <!-- APQ Breakdown Cards -->
                        <div class="apq-grid">
                            <div class="apq-card">
                                <div class="apq-title">AVAILABILITY</div>
                                <div class="apq-val" id="avail-val">—</div>
                                <div class="apq-target">Target unavailable</div>
                            </div>
                            <div class="apq-card">
                                <div class="apq-title">PERFORMANCE</div>
                                <div class="apq-val" id="perf-val">—</div>
                                <div class="apq-target">Target unavailable</div>
                            </div>
                            <div class="apq-card">
                                <div class="apq-title">QUALITY</div>
                                <div class="apq-val" id="qual-val">—</div>
                                <div class="apq-target">Based on recorded counts</div>
                            </div>
                        </div>

                        <!-- Production Totals -->
                        <div class="volume-row">
                            <div class="volume-item"><label>TOTAL OUTPUT</label><strong id="vol-total">—</strong></div>
                            <div class="volume-item"><label>GOOD UNITS</label><strong style="color: var(--accent-green);" id="vol-good">—</strong></div>
                            <div class="volume-item"><label>REJECTED (NG)</label><strong style="color: var(--brand-orange);" id="vol-ng">—</strong></div>
                        </div>
                    </div>
                </div>

                <!-- OEE BY WEEK GRAPH CARD -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>OEE BY WEEK</span>
                        <small>LAST 8 WEEKS</small>
                    </div>
                    <div class="card-body">
                        <div class="chart-container">
                            <svg class="trend-svg" viewBox="0 0 600 200" preserveAspectRatio="none">
                                <defs>
                                    <linearGradient id="blueGradient" x1="0" y1="0" x2="0" y2="1">
                                        <stop offset="0%" stop-color="#58a6ff" stop-opacity="0.5"/>
                                        <stop offset="100%" stop-color="#58a6ff" stop-opacity="0.0"/>
                                    </linearGradient>
                                </defs>

                                <!-- Y Axis Grid Lines -->
                                <line x1="50" y1="30" x2="570" y2="30" class="grid-line" />
                                <text x="40" y="34" class="chart-axis-text" text-anchor="end">90%</text>

                                <line x1="50" y1="80" x2="570" y2="80" class="grid-line" />
                                <text x="40" y="84" class="chart-axis-text" text-anchor="end">80%</text>

                                <line x1="50" y1="130" x2="570" y2="130" class="grid-line" />
                                <text x="40" y="134" class="chart-axis-text" text-anchor="end">70%</text>

                                <line x1="50" y1="180" x2="570" y2="180" class="grid-line" />
                                <text x="40" y="184" class="chart-axis-text" text-anchor="end">60%</text>

                                <!-- Target 85% Line -->
                                <line x1="50" y1="55" x2="570" y2="55" class="target-line" />
                                <text x="570" y="48" class="target-label">Target 85%</text>

                                <!-- Dynamic Polygon Area Fill -->
                                <polygon id="chart-area-fill" class="area-fill" points="" />

                                <!-- Dynamic Line Path -->
                                <path id="chart-line-path" class="trend-line" d="" />

                                <!-- Data Points & Labels Container -->
                                <g id="chart-points-group"></g>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Where OEE is Lost -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>WHERE OEE IS LOST</span>
                        <small>Share of planned capacity</small>
                    </div>
                    <div class="card-body">
                        <div class="loss-row">
                            <div class="loss-header"><span>Planned capacity</span><strong>100.0%</strong></div>
                            <div class="loss-bar-bg"><div class="loss-bar-fill" style="width: 100%; background: #30363d;"></div></div>
                        </div>
                        <div class="loss-row">
                            <div class="loss-header"><span>Downtime (Breakdowns and changeovers)</span><strong style="color:var(--brand-orange);">-10.8</strong></div>
                            <div class="loss-bar-bg"><div class="loss-bar-fill" style="width: 10.8%;"></div></div>
                        </div>
                        <div class="loss-row">
                            <div class="loss-header"><span>Slow cycles (Minor stops and reduced speed)</span><strong style="color:var(--brand-orange);">-5.8</strong></div>
                            <div class="loss-bar-bg"><div class="loss-bar-fill" style="width: 5.8%;"></div></div>
                        </div>
                        <div class="loss-row">
                            <div class="loss-header"><span>Rejected units (Confirmed NG)</span><strong style="color:var(--brand-orange);">-5.0</strong></div>
                            <div class="loss-bar-bg"><div class="loss-bar-fill" style="width: 5.0%;"></div></div>
                        </div>
                        <div class="loss-row">
                            <div class="loss-header"><span>Effective OEE</span><strong style="color:var(--accent-green);">—</strong></div>
                            <div class="loss-bar-bg"><div class="loss-bar-fill" style="width: 78.4%; background: var(--accent-green);"></div></div>
                        </div>
                    </div>
                </div>

                <!-- Line Performance -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>LINE PERFORMANCE</span>
                        <small>OEE target 85%</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="line-table">
                            <thead>
                                <tr>
                                    <th>LINE</th>
                                    <th>OUTPUT</th>
                                    <th>AVAIL.</th>
                                    <th>PERF.</th>
                                    <th>QUALITY</th>
                                    <th>OEE</th>
                                </tr>
                            </thead>
                            <tbody id="line-table-body">
                                <!-- Populated via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column Widgets -->
            <div>
                <!-- Needs Your Attention -->
                <div class="card">
                    <div class="card-header-bar header-blue">NEEDS YOUR ATTENTION</div>
                    <div class="card-body" id="manager-attention-list"><p class="empty-state">No live alerts available.</p></div>
                </div>

                <!-- Recurring Defects -->
                <div class="card">
                    <div class="card-header-bar header-blue">RECURRING DEFECTS</div>
                    <div class="card-body" id="manager-recurring-defects"><p class="empty-state">No verified defect data available.</p></div>
                </div>

                <!-- Executive Report -->
                <div class="card">
                    <div class="card-header-bar header-green">EXECUTIVE REPORT · CURRENT PERIOD</div>
                    <div class="card-body">
                        <label style="font-size: 0.75rem; color: var(--text-secondary); display: block; margin-bottom: 5px;">Report period</label>
                        <select class="report-select">
                            <option>Last 30 days</option>
                        </select>

                        <div class="check-group">
                            <label class="check-item"><input type="checkbox" checked> OEE summary and weekly trend</label>
                            <label class="check-item"><input type="checkbox" checked> Loss breakdown by line</label>
                            <label class="check-item"><input type="checkbox" checked> Recurring defects and root causes</label>
                        </div>

                        <button class="btn-generate">GENERATE REPORT</button>
                        <div class="btn-row">
                            <button class="btn-export-sub">Export PDF</button>
                            <button class="btn-export-sub">Export CSV</button>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Dashboard Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadManagerDashboard();
        });

        function loadManagerDashboard() {
            fetch(BASE_URL + '/backend/get_manager_data.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderMetrics(data.metrics);
                        renderLineTable(data.lines);
                        renderWeeklyChart(data.weekly || []);
                    } else {
                        renderMetrics(null); renderLineTable([]); renderWeeklyChart([]);
                    }
                    }
                })
                .catch(err => {
                    console.error("Error loading manager data:", err);
                    renderMetrics(null); renderLineTable([]); renderWeeklyChart([]);
                    const alert = document.getElementById('manager-attention-list');
                    if (alert) alert.textContent = 'Unable to load live alerts.';
                });
        }

        function renderMetrics(m) {
            if (!m) {
                ['oee-val','avail-val','perf-val','qual-val','vol-total','vol-good','vol-ng'].forEach(id => {
                    const el = document.getElementById(id); if (el) el.textContent = '—';
                });
                return;
            }
            document.getElementById('oee-val').innerText = m.overall_oee == null ? '—' : m.overall_oee + '%';
            document.getElementById('avail-val').innerText = m.availability == null ? '—' : m.availability + '%';
            document.getElementById('perf-val').innerText = m.performance == null ? '—' : m.performance + '%';
            document.getElementById('qual-val').innerText = m.quality == null ? '—' : m.quality + '%';
            document.getElementById('vol-total').innerText = parseInt(m.total_output).toLocaleString() + ' units';
            document.getElementById('vol-good').innerText = parseInt(m.good_units).toLocaleString();
            document.getElementById('vol-ng').innerText = parseInt(m.ng_units).toLocaleString() + (parseInt(m.total_output) > 0 ? ' - ' + ((parseInt(m.ng_units) / parseInt(m.total_output)) * 100).toFixed(1) + '%' : '');
        }

        function renderLineTable(lines) {
            const tbody = document.getElementById('line-table-body');
            tbody.innerHTML = '';
            if (!lines || !lines.length) {
                tbody.innerHTML = '<tr><td colspan="6">No verified line performance data available.</td></tr>';
                return;
            }
            lines.forEach(line => {
                tbody.innerHTML += `
                    <tr>
                        <td>
                            <strong>${esc(line.line_name)}</strong>
                            ${line.is_lowest == 1 ? '<span class="badge-lowest">Lowest</span>' : ''}<br>
                            <small style="color:var(--text-secondary);">${esc(line.share_pct)}</small>
                        </td>
                        <td>${parseInt(line.total_output).toLocaleString()}</td>
                        <td>${esc(line.availability)}%</td>
                        <td>${esc(line.performance)}%</td>
                        <td>${esc(line.quality)}%</td>
                        <td style="color:var(--accent-yellow); font-weight:bold;">${esc(line.oee)}%</td>
                    </tr>
                `;
            });
        }

        function renderWeeklyChart(weeklyData) {
            if (!weeklyData || weeklyData.length === 0) {
                document.getElementById('chart-line-path').setAttribute('d', '');
                document.getElementById('chart-area-fill').setAttribute('points', '');
                document.getElementById('chart-points-group').innerHTML = '<text x="300" y="110" text-anchor="middle" class="chart-axis-text">No verified trend data available</text>';
                return;
            }

            const startX = 65;
            const endX = 550;
            const minY = 180; // 60% level
            const maxY = 30;  // 90% level

            const stepX = (endX - startX) / (weeklyData.length - 1);

            let pathD = "";
            let areaPoints = `${startX},${minY} `;
            let pointsHTML = "";

            weeklyData.forEach((item, index) => {
                const val = parseFloat(item.oee_value);
                const cx = startX + (index * stepX);
                // Map 60%..90% to Y coordinates 180..30
                const cy = minY - ((val - 60) / (90 - 60)) * (minY - maxY);

                if (index === 0) {
                    pathD += `M ${cx} ${cy}`;
                } else {
                    pathD += ` L ${cx} ${cy}`;
                }

                areaPoints += `${cx},${cy} `;

                let weekSubText = index === weeklyData.length - 1 ? '<tspan x="' + cx + '" dy="12" style="font-size:9px; fill:#8b949e;">to date</tspan>' : '';

                pointsHTML += `
                    <circle cx="${cx}" cy="${cy}" class="data-point"></circle>
                    <text x="${cx}" y="${cy - 12}" class="val-label">${val}</text>
                    <text x="${cx}" y="195" class="chart-axis-text" text-anchor="middle">${esc(item.week_label)}${weekSubText}</text>
                `;
            });

            areaPoints += `${startX + ((weeklyData.length - 1) * stepX)},${minY}`;

            document.getElementById('chart-line-path').setAttribute('d', pathD);
            document.getElementById('chart-area-fill').setAttribute('points', areaPoints);
            document.getElementById('chart-points-group').innerHTML = pointsHTML;
        }
    </script>
</body>
</html>
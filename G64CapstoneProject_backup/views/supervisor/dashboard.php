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
    <title>Floor Overview - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Supervisor Dashboard Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/supervisor_dashboard.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Floor Overview</h1>
                <p class="subtitle">Real-time status of every station on the line, overall yield, and defect reports waiting for your review.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
                <span class="session-badge">● <span id="session-badge-text">Loading session status…</span></span>
            </div>
        </header>

        <!-- Top Metric Summary Cards -->
        <section class="grid-top-metrics">
            <div class="metric-card">
                <div class="card-title">Line Output vs Target</div>
                <div class="card-val" id="stat-output">— <span style="font-size:1.2rem; color:var(--text-secondary); font-weight:normal;">/ —</span></div>
                <div class="target-progress-bg"><div class="target-progress-fill" id="stat-progress-bar" style="width: 0%;"></div></div>
                <div class="card-sub" style="margin-top:6px;" id="stat-project-sub">Loading production summary…</div>
            </div>

            <div class="metric-card">
                <div class="card-title">Overall Yield</div>
                <div class="card-val" style="color: var(--accent-yellow);" id="stat-yield">—</div>
                <div class="card-sub" id="stat-units-sub">Waiting for production data…</div>
            </div>

            <div class="metric-card">
                <div class="card-title">Reports to Review</div>
                <div class="card-val" style="color: var(--brand-orange);" id="stat-reports">—</div>
                <a href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php" class="card-link">Open queue →</a>
            </div>

            <div class="metric-card">
                <div class="card-title">Stations Online</div>
                <div class="card-val" id="stat-stations">— <span style="font-size:1.2rem; color:var(--text-secondary); font-weight:normal;">/ —</span></div>
                <div class="card-sub" id="stat-stations-sub">Loading station status…</div>
            </div>
        </section>

        <section class="grid-sp-main">
            <!-- Left Main Grid Area -->
            <div>
                <!-- Station Status Grid -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>STATION STATUS</span>
                        <a href="<?= BASE_URL ?>/views/supervisor/line_control.php" style="color:#fff; text-decoration:none; font-size:0.75rem;">Manage in Line Control →</a>
                    </div>
                    <div class="card-body">
                        <div class="station-cards-grid" id="stations-box-container">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Floor Alerts -->
                <div class="card">
                    <div class="card-header-bar header-red">FLOOR ALERTS</div>
                    <div class="card-body" id="alerts-feed-container">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>

            <!-- Right Column Widgets -->
            <div>
                <!-- Line Output Per Hour -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>LINE OUTPUT PER HOUR</span>
                        <div class="legend-bar-row">
                            <span><span class="legend-dot" style="background:var(--accent-yellow);"></span> Good</span>
                            <span><span class="legend-dot" style="background:var(--brand-orange);"></span> NG</span>
                        </div>
                    </div>
                    <div class="card-body">
                        <div class="chart-container-hourly">
                            <svg class="hourly-svg" viewBox="0 0 300 150" preserveAspectRatio="none">
                                <g id="svg-bars-group"></g>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Waiting For Review -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>WAITING FOR REVIEW</span>
                        <a href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php" style="color:#fff; text-decoration:none; font-size:0.75rem;">Open full queue →</a>
                    </div>
                    <div class="card-body" id="reviews-list-container">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Dashboard Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadSupervisorDashboard();
            window.supervisorRefresh = setInterval(() => { if (!document.hidden) loadSupervisorDashboard(); }, 15000);
        });

        function showSupervisorError(message) { let el = document.getElementById('supervisor-load-error'); if (!el) { el = document.createElement('div'); el.id = 'supervisor-load-error'; el.setAttribute('role','alert'); el.style.cssText = 'padding:10px 14px;margin:10px 0;border:1px solid #b42318;border-radius:8px;color:#b42318;background:#fff5f5'; document.querySelector('.main-content').prepend(el); } el.textContent = message; }
        function loadSupervisorDashboard() {
            fetch(BASE_URL + '/backend/get_supervisor_dashboard.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderSummary(data.summary);
                        renderStations(data.stations);
                        renderHourlyChart(data.hourly);
                        renderAlerts(data.alerts);
                        renderReviews(data.pending_reviews);
                    }
                })
                .catch(err => { showSupervisorError('Unable to load live floor data. Check the server/database connection and refresh.'); console.error("Error loading supervisor data:", err); });
        }

        function renderSummary(s) {
            const err = document.getElementById('supervisor-load-error'); if (err) err.remove();
            const sessionBadge = document.getElementById('session-badge-text'); if (sessionBadge) sessionBadge.textContent = s.session_code ? `SESSION ${s.session_status || 'ACTIVE'} · ${s.session_code}` : 'No active session';
            document.getElementById('stat-output').innerHTML = `${esc(s.output)} <span style="font-size:1.2rem; color:var(--text-secondary); font-weight:normal;">/ ${esc(s.target)}</span>`;
            document.getElementById('stat-progress-bar').style.width = `${s.pct_done}%`;
            document.getElementById('stat-project-sub').innerText = `Vent Caps (A2) · ${s.pct_done}% done`;
            
            document.getElementById('stat-yield').innerText = s.yield;
            document.getElementById('stat-units-sub').innerText = `${s.good_units} good · ${s.ng_units} rejected`;
            
            document.getElementById('stat-reports').innerText = s.reports_to_review;
            document.getElementById('stat-stations').innerHTML = `${esc(s.stations_online)} <span style="font-size:1.2rem; color:var(--text-secondary); font-weight:normal;">/ ${esc(s.total_stations)}</span>`;
            document.getElementById('stat-stations-sub').innerText = `${s.running_count} running · ${s.paused_count} paused`;
        }

        function renderStations(stations) {
            const container = document.getElementById('stations-box-container');
            container.innerHTML = '';

            stations.forEach(st => {
                let borderClass = st.status === 'RUNNING' ? 'border-running' : 'border-paused';
                let totalStationUnits = parseInt(st.good_units) + parseInt(st.ng_units);
                let yieldPct = totalStationUnits > 0 ? ((st.good_units / totalStationUnits) * 100).toFixed(1) : "100.0";
                let progressWidth = Math.min(100, Math.round((st.good_units / st.target_units) * 100));

                container.innerHTML += `
                    <div class="station-box ${borderClass}">
                        <div class="station-box-header">
                            <div>
                                <span class="station-code">${esc(st.station_code)}</span>
                                <span class="station-staff">${esc(st.assigned_staff)} · ${esc(st.workstation_name)}</span>
                            </div>
                            <span class="badge-status badge-${esc(st.status)}">${esc(st.status)}</span>
                        </div>
                        <div class="station-val-line">
                            <span class="good-val">${esc(st.good_units)} good</span>
                            <span class="ng-val">${esc(st.ng_units)} NG</span>
                        </div>
                        <div class="target-progress-bg">
                            <div class="target-progress-fill" style="width: ${progressWidth}%;"></div>
                        </div>
                        <div class="station-meta-row">
                            <span>Yield ${yieldPct}%</span>
                            <span>${esc(st.good_units)} / ${esc(st.target_units)}</span>
                        </div>
                    </div>
                `;
            });
        }

        function renderHourlyChart(hourlyData) {
            const group = document.getElementById('svg-bars-group');
            group.innerHTML = '';

            if (!hourlyData || hourlyData.length === 0) return;

            const maxVal = 70;
            const startX = 20;
            const stepX = 55;

            hourlyData.forEach((h, index) => {
                const cx = startX + (index * stepX);
                const goodH = (h.good_units / maxVal) * 100;
                const ngH = (h.ng_units / maxVal) * 100;

                const goodY = 120 - goodH;
                const ngY = goodY - ngH;

                group.innerHTML += `
                    <!-- Good Bar -->
                    <rect x="${cx}" y="${goodY}" width="16" height="${goodH}" fill="var(--accent-yellow)" rx="2"/>
                    <!-- NG Bar -->
                    <rect x="${cx}" y="${ngY}" width="16" height="${ngH}" fill="var(--brand-orange)" rx="2"/>
                    <!-- Labels -->
                    <text x="${cx + 8}" y="135" fill="var(--text-primary)" font-size="9" font-weight="bold" text-anchor="middle">${esc(h.good_units)}</text>
                    <text x="${cx + 8}" y="147" fill="var(--text-secondary)" font-size="8" text-anchor="middle">${esc(h.hour_label)}</text>
                `;
            });
        }

        function renderAlerts(alerts) {
            const container = document.getElementById('alerts-feed-container');
            container.innerHTML = '';

            alerts.forEach(a => {
                container.innerHTML += `
                    <div class="alert-feed-item">
                        <div class="alert-title-group">
                            <span class="dot-alert dot-${esc(a.alert_type)}"></span>
                            <div class="alert-feed-text">
                                <strong>${esc(a.title)}</strong>
                                <small>${esc(a.subtitle)}</small>
                            </div>
                        </div>
                        <div class="alert-feed-time">${esc(a.alert_time)}</div>
                    </div>
                `;
            });
        }

        function renderReviews(reviews) {
            const container = document.getElementById('reviews-list-container');
            container.innerHTML = '';

            if (!reviews || reviews.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.8rem;">No pending defect reports waiting for review.</p>';
                return;
            }

            reviews.forEach(r => {
                let timeFormatted = esc(r.report_time ? r.report_time.substring(0, 5) : '09:18 AM');
                container.innerHTML += `
                    <div class="review-item">
                        <div class="review-text">
                            <strong>${esc(r.defect_category)} · ${esc(r.workstation)}</strong>
                            <small>Reported by ${esc(r.reported_by || 'Unknown reporter')} · ${timeFormatted} · Qty ${esc(r.quantity_rejected || 1)}</small>
                        </div>
                        <div style="display:flex; align-items:center;">
                            <span class="pill-sev-gold">${esc(r.severity || 'Major')}</span>
                            <a href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php" class="btn-review-outline">Review</a>
                        </div>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
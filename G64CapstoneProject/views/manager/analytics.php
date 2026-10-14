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
    <title>Production Analytics - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Analytics Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/analytics.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Production Analytics</h1>
                <p class="subtitle">Historical quality and defect trends. Compare periods, find which factors line up with recurring defects, and pull past reports.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- Filter Controls Toolbar -->
        <div class="filter-controls-bar">
            <div class="filter-row">
                <span class="filter-label">PERIOD</span>
                <button class="btn-filter">7 days</button>
                <button class="btn-filter active">30 days</button>
                <button class="btn-filter">Quarter</button>
                <button class="btn-filter">Year</button>
            </div>
            <div class="filter-row">
                <span class="filter-label">LINE</span>
                <button class="btn-filter active">All lines</button>
                <button class="btn-filter">Line 1</button>
                <button class="btn-filter">Line 2</button>
                <button class="btn-filter">Line 3</button>
            </div>
        </div>

        <section class="grid-analytics-main">
            <!-- Left Main Analytics Column -->
            <div>
                <!-- Yield By Day Chart Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>YIELD BY DAY</span>
                        <small id="yield-date-range">Last 30 days · awaiting data</small>
                    </div>
                    <div class="card-body">
                           <div class="yield-summary-title">
                              <span class="yield-meta">Daily production yield</span>
                           </div>
                        <!-- SVG Line Chart -->
                        <div class="chart-container">
                            <svg class="yield-svg" viewBox="0 0 600 180" preserveAspectRatio="none">
                                <!-- Horizontal Grid Lines -->
                                <line x1="50" y1="20" x2="570" y2="20" class="grid-line"/>
                                <text x="40" y="24" class="chart-axis-text" text-anchor="end">100%</text>

                                <line x1="50" y1="60" x2="570" y2="60" class="grid-line"/>
                                <text x="40" y="64" class="chart-axis-text" text-anchor="end">98%</text>

                                <line x1="50" y1="100" x2="570" y2="100" class="grid-line"/>
                                <text x="40" y="104" class="chart-axis-text" text-anchor="end">96%</text>

                                <line x1="50" y1="140" x2="570" y2="140" class="grid-line"/>
                                <text x="40" y="144" class="chart-axis-text" text-anchor="end">94%</text>

                                <line x1="50" y1="170" x2="570" y2="170" class="grid-line"/>
                                <text x="40" y="174" class="chart-axis-text" text-anchor="end">90%</text>

                                <!-- Target 97% Line -->
                                <line x1="50" y1="80" x2="570" y2="80" class="target-line"/>
                                <text x="570" y="72" class="target-label">Target 97%</text>

                                <!-- Dynamic Line Path -->
                                <path id="yield-line-path" class="trend-line" d="" />
                                <g id="yield-points-group"></g>
                            </svg>
                        </div>
                    </div>
                </div>

                <!-- Root-Cause View Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>ROOT-CAUSE VIEW</span>
                        <small id="defect-period-summary">No verified data loaded</small>
                    </div>
                    <div class="card-body">
                        <!-- Defect Category Pills -->
                        <div class="defect-pills">
                            <button class="btn-defect-pill active" onclick="loadRootCause('Short shot')">Short shot</button>
                            <button class="btn-defect-pill" onclick="loadRootCause('Scratch')">Scratch</button>
                            <button class="btn-defect-pill" onclick="loadRootCause('Crack')">Crack</button>
                            <button class="btn-defect-pill" onclick="loadRootCause('Flash')">Flash</button>
                            <button class="btn-defect-pill" onclick="loadRootCause('Warpage')">Warpage</button>
                        </div>

                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 12px;">
                            Verified defect-factor correlations will appear when source data is available.
                        </p>

                        <div class="legend-row">
                            <div class="legend-item"><span class="legend-color-orange"></span> Share of short shots</div>
                            <div class="legend-item"><span class="legend-color-gray"></span> Share of output</div>
                        </div>

                        <!-- Factors Correlation Meters Container -->
                        <div id="factors-container">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <div class="btn-action-row">
                            <button class="btn-outline-gold">Assign Investigation</button>
                            <button class="btn-secondary-gray">Export analysis</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Right Column Widgets -->
            <div>
                <!-- Period Comparison -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>COMPARE PERIODS</span>
                        <small>vs previous 30 days</small>
                    </div>
                    <div class="card-body" style="padding: 0;">
                        <table class="compare-table">
                            <thead>
                                <tr>
                                    <th>METRIC</th>
                                    <th>NOW</th>
                                    <th>BEFORE</th>
                                    <th>CHANGE</th>
                                </tr>
                            </thead>
                            <tbody id="comparison-table-body"><tr><td colspan="4">No verified comparison data available.</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <!-- Report History Widget -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>REPORT HISTORY</span>
                        <small id="report-count-badge">4 saved</small>
                    </div>
                    <div class="card-body" id="report-history-list">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>

                <!-- Build A Report Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">BUILD A REPORT</div>
                    <div class="card-body">
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 15px;">
                            Uses the period and line selected above. Reports count confirmed rejects only.
                        </p>
                        <button class="btn-generate-light">GENERATE REPORT</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Analytics Script -->
    <script>
        document.addEventListener("DOMContentLoaded", () => {
            loadAnalyticsData();
        });

        function loadAnalyticsData() {
            fetch(BASE_URL + '/backend/get_analytics_data.php')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderYieldChart(data.daily_yield || []);
                        renderFactors(data.factors);
                        renderReports(data.reports);
                    }
                })
                .catch(err => console.error("Error loading analytics data:", err));
        }

        function renderYieldChart(yieldData) {
            if (!yieldData || yieldData.length === 0) {
                document.getElementById('yield-line-path').setAttribute('d', '');
                document.getElementById('yield-points-group').innerHTML = '<text x="300" y="100" text-anchor="middle" class="chart-axis-text">No verified yield data available</text>';
                document.getElementById('yield-average').textContent = '—';
                document.getElementById('yield-date-range').textContent = 'Last 30 days · no production records';
                return;
            }
            const avg = yieldData.reduce((sum, item) => sum + Number(item.yield_value || 0), 0) / yieldData.length;
            document.getElementById('yield-average').textContent = avg.toFixed(1) + '%';
            document.getElementById('yield-date-range').textContent =
                yieldData[0].day_label + ' – ' + yieldData[yieldData.length - 1].day_label;

            const startX = 65;
            const endX = 550;
            const minY = 170; // 90%
            const maxY = 20;  // 100%

            const stepX = (endX - startX) / (yieldData.length - 1);
            let pathD = "";
            let pointsHTML = "";

            yieldData.forEach((item, index) => {
                const val = parseFloat(item.yield_value);
                const cx = startX + (index * stepX);
                const cy = minY - ((val - 90) / (100 - 90)) * (minY - maxY);

                if (index === 0) {
                    pathD += `M ${cx} ${cy}`;
                } else {
                    pathD += ` L ${cx} ${cy}`;
                }

                let extraText = '';
                if (index === yieldData.length - 1) {
                    extraText = `<text x="${cx}" y="${cy - 12}" class="val-label">${val}</text>`;
                }

                pointsHTML += `
                    <circle cx="${cx}" cy="${cy}" class="data-point"></circle>
                    ${extraText}
                    <text x="${cx}" y="185" class="chart-axis-text" text-anchor="middle">${esc(item.day_label)}</text>
                `;
            });

            document.getElementById('yield-line-path').setAttribute('d', pathD);
            document.getElementById('yield-points-group').innerHTML = pointsHTML;
        }

        function loadRootCause(defectType) {
            document.querySelectorAll('.btn-defect-pill').forEach(btn => {
                btn.classList.remove('active');
                if (btn.innerText === defectType) btn.classList.add('active');
            });

            fetch(`${BASE_URL}/backend/get_analytics_data.php?defect_type=${encodeURIComponent(defectType)}`)
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        renderFactors(data.factors);
                    }
                });
        }

        function renderFactors(factors) {
            const container = document.getElementById('factors-container');
            container.innerHTML = '';

            if (!factors || factors.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.85rem;">No factor correlation data for this defect.</p>';
                return;
            }

            factors.forEach(f => {
                container.innerHTML += `
                    <div class="factor-row">
                        <div class="factor-header">
                            <div class="factor-info">
                                <strong>${esc(f.factor_name)}</strong>
                                <small>${esc(f.factor_category)}</small>
                            </div>
                            <span class="multiplier-badge">${esc(f.multiplier)}</span>
                        </div>
                        <div class="bar-stack">
                            <div class="track-bg"><div class="bar-fill-orange" style="width: ${esc(f.defect_share_pct)}%;"></div></div>
                            <div class="track-bg"><div class="bar-fill-gray" style="width: ${esc(f.output_share_pct)}%;"></div></div>
                        </div>
                        <div class="factor-vals">${esc(f.defect_share_pct)}% / ${esc(f.output_share_pct)}%</div>
                    </div>
                `;
            });
        }

        function renderReports(reports) {
            const container = document.getElementById('report-history-list');
            container.innerHTML = '';
            reports = reports || [];
            document.getElementById('report-count-badge').innerText = reports.length + ' saved';
            if (!reports.length) { container.innerHTML = '<p>No reports have been generated yet.</p>'; return; }

            reports.forEach(r => {
                container.innerHTML += `
                    <div class="report-item">
                        <div class="report-title">
                            <strong>${esc(r.title)}</strong>
                            <small>${esc(r.file_format)} · ${esc(r.generated_time)}</small>
                        </div>
                        <a href="${esc(r.download_url)}" class="btn-download">Download</a>
                    </div>
                `;
            });
        }
    </script>
</body>
</html>
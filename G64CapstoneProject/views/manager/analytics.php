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
                <div id="analytics-period-filter">
                    <button class="btn-filter" data-days="7">7 days</button>
                    <button class="btn-filter active" data-days="30">30 days</button>
                    <button class="btn-filter" data-days="90">Quarter</button>
                    <button class="btn-filter" data-days="365">Year</button>
                </div>
            </div>
            <div class="filter-row">
                <span class="filter-label">LINE</span>
                <div id="analytics-line-filter"><button class="btn-filter active" data-line="all">All lines</button></div>
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
                            <span class="yield-meta">Daily production yield · average <strong id="yield-average">—</strong></span>
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
                                <line id="yield-target-line" x1="50" y1="80" x2="570" y2="80" class="target-line"/>
                                <text id="yield-target-label" x="570" y="72" class="target-label">Target 97%</text>

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
                        <div class="defect-pills" id="defect-type-pills"></div>

                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 12px;">
                            Confirmed defect reports are compared with production share by workstation for the selected dates.
                        </p>

                        <div class="legend-row">
                            <div class="legend-item"><span class="legend-color-orange"></span> Share of selected defect rejects</div>
                            <div class="legend-item"><span class="legend-color-gray"></span> Share of produced units</div>
                        </div>

                        <!-- Factors Correlation Meters Container -->
                        <div id="factors-container">
                            <!-- Populated dynamically via JS -->
                        </div>

                        <div class="btn-action-row">
                            <button class="btn-secondary-gray" id="export-root-cause">Export analysis CSV</button>
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
                        <small id="comparison-period-label">Previous matching period</small>
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
                            <tbody id="comparison-table-body"><tr><td colspan="4">No production data available for comparison.</td></tr></tbody>
                        </table>
                    </div>
                </div>

                <!-- Report Export Widget -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>REPORT EXPORT</span>
                        <small id="report-count-badge">Current selection</small>
                    </div>
                    <div class="card-body">
                        <p id="report-history-list">Export the filtered yield, defect, and line data as a CSV file.</p>
                    </div>
                </div>

                <!-- Build A Report Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">BUILD A REPORT</div>
                    <div class="card-body">
                        <p style="font-size:0.8rem; color:var(--text-secondary); margin-bottom: 15px;">
                            Uses the period and line selected above. Reports count confirmed rejects only.
                        </p>
                        <button class="btn-generate-light" id="generate-analytics-report" type="button">DOWNLOAD CURRENT REPORT</button>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Analytics Script -->
    <script>
        const analyticsState = {days:30, line:'all', defectType:'', data:null};
        document.addEventListener("DOMContentLoaded", () => {
            document.getElementById('analytics-period-filter').addEventListener('click', event => {
                const button = event.target.closest('[data-days]');
                if (!button) return;
                analyticsState.days = Number(button.dataset.days);
                loadAnalyticsData();
            });
            document.getElementById('analytics-line-filter').addEventListener('click', event => {
                const button = event.target.closest('[data-line]');
                if (!button) return;
                analyticsState.line = button.dataset.line;
                loadAnalyticsData();
            });
            document.getElementById('defect-type-pills').addEventListener('click', event => {
                const button = event.target.closest('[data-defect-type]');
                if (!button) return;
                analyticsState.defectType = button.dataset.defectType;
                loadAnalyticsData();
            });
            document.getElementById('export-root-cause').addEventListener('click', exportAnalyticsCsv);
            document.getElementById('generate-analytics-report').addEventListener('click', exportAnalyticsCsv);
            loadAnalyticsData();
        });

        async function loadAnalyticsData() {
            const params = new URLSearchParams({days:String(analyticsState.days), line:analyticsState.line});
            if (analyticsState.defectType) params.set('defect_type', analyticsState.defectType);
            try {
                const response = await fetch(`${BASE_URL}/backend/get_analytics_data.php?${params}`);
                const data = await response.json();
                if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to load analytics.');
                analyticsState.data = data;
                analyticsState.defectType = data.selected_defect_type || '';
                document.getElementById('yield-target-label').textContent = `Target ${data.yield_target_pct}%`;
                const targetY = 170 - ((Math.max(90, Math.min(100, Number(data.yield_target_pct))) - 90) / 10) * 150;
                document.getElementById('yield-target-line').setAttribute('y1', targetY);
                document.getElementById('yield-target-line').setAttribute('y2', targetY);
                document.getElementById('yield-target-label').setAttribute('y', targetY - 8);
                renderFilters(data);
                renderYieldChart(data.daily_yield || []);
                renderFactors(data.factors || []);
                renderComparison(data.comparison);
                document.getElementById('defect-period-summary').textContent = `${data.days} days · ${data.line === 'all' ? 'All lines' : data.line}`;
                document.getElementById('report-history-list').textContent = `${data.days}-day report · ${data.line === 'all' ? 'All lines' : data.line} · confirmed defects only`;
            } catch (err) {
                console.error('Error loading analytics data:', err);
                document.getElementById('factors-container').textContent = err.message;
            }
        }

        function renderFilters(data) {
            document.querySelectorAll('#analytics-period-filter [data-days]').forEach(button => {
                button.classList.toggle('active', Number(button.dataset.days) === Number(data.days));
            });
            const lineBox = document.getElementById('analytics-line-filter');
            lineBox.innerHTML = `<button class="btn-filter ${data.line === 'all' ? 'active' : ''}" data-line="all">All lines</button>` +
                (data.lines || []).map(line => `<button class="btn-filter ${data.line === line.line_name ? 'active' : ''}" data-line="${esc(line.line_name)}">${esc(line.line_name)}</button>`).join('');
            const typeBox = document.getElementById('defect-type-pills');
            typeBox.innerHTML = (data.defect_types || []).map(type => `<button class="btn-defect-pill ${data.selected_defect_type === type.name ? 'active' : ''}" data-defect-type="${esc(type.name)}">${esc(type.name)}</button>`).join('');
        }

        function renderYieldChart(yieldData) {
            if (!yieldData || yieldData.length === 0) {
                document.getElementById('yield-line-path').setAttribute('d', '');
                document.getElementById('yield-points-group').innerHTML = '<text x="300" y="100" text-anchor="middle" class="chart-axis-text">No verified yield data available</text>';
                document.getElementById('yield-average').textContent = '—';
                document.getElementById('yield-date-range').textContent = `${analyticsState.days} days · no closed-shift production records`;
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

            const stepX = yieldData.length > 1 ? (endX - startX) / (yieldData.length - 1) : 0;
            let pathD = "";
            let pointsHTML = "";

            yieldData.forEach((item, index) => {
                const val = parseFloat(item.yield_value);
                const cx = yieldData.length > 1 ? startX + (index * stepX) : (startX + endX) / 2;
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

        function renderComparison(comparison) {
            const tbody = document.getElementById('comparison-table-body');
            const label = document.getElementById('comparison-period-label');
            label.textContent = `Previous ${analyticsState.days} days`;
            if (!comparison || comparison.current_yield == null) {
                tbody.innerHTML = '<tr><td colspan="4">No closed-shift yield data available for this comparison.</td></tr>';
                return;
            }
            const before = comparison.previous_yield;
            const change = before == null ? null : Number(comparison.current_yield) - Number(before);
            tbody.innerHTML = `<tr><td>Weighted yield</td><td>${Number(comparison.current_yield).toFixed(1)}%</td><td>${before == null ? '—' : Number(before).toFixed(1) + '%'}</td><td>${change == null ? '—' : (change > 0 ? '+' : '') + change.toFixed(1) + ' pp'}</td></tr>`;
        }

        function csvCell(value) {
            let text = String(value ?? '');
            if (/^[=+\-@]/.test(text)) text = "'" + text;
            return '"' + text.replace(/"/g, '""') + '"';
        }

        function exportAnalyticsCsv() {
            const data = analyticsState.data;
            if (!data) { alert('Analytics data is not loaded yet.'); return; }
            const rows = [['section','date_or_factor','metric','value']];
            (data.daily_yield || []).forEach(day => rows.push(['Daily yield',day.day_label,'yield_pct',day.yield_value]));
            (data.factors || []).forEach(factor => rows.push(['Defect comparison',factor.factor_name,'defect_share_pct',factor.defect_share_pct],['Defect comparison',factor.factor_name,'output_share_pct',factor.output_share_pct],['Defect comparison',factor.factor_name,'multiplier',factor.multiplier]));
            rows.push(['Filters','period_days',data.days],['Filters','line',data.line],['Filters','defect_type',data.selected_defect_type || '']);
            const csv = rows.map(row => row.map(csvCell).join(',')).join('\r\n');
            const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
            const link = document.createElement('a');
            link.href = url;
            link.download = `production-analytics-${data.days}d-${new Date().toISOString().slice(0,10)}.csv`;
            link.click();
            URL.revokeObjectURL(url);
        }
    </script>
</body>
</html>
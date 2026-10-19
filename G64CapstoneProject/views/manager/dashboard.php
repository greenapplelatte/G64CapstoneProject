
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

    <style>
        @media print {
            .sidebar,
            .period-toolbar,
            .time-area,
            .btn-generate,
            .btn-row,
            .check-group {
                display: none !important;
            }

            .main-content {
                margin: 0;
                padding: 0;
            }

            body,
            .card {
                background: #fff !important;
                color: #111 !important;
            }

            .card {
                border: 1px solid #bbb;
                break-inside: avoid;
            }
        }
    </style>
</head>

<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- HEADER -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Executive OEE Dashboard</h1>
                <p class="subtitle">
                    Plant efficiency across all lines. See where output is lost,
                    which defects keep coming back, and what needs a decision.
                </p>
            </div>

            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <!-- DATA SCOPE -->
        <div class="period-toolbar">
            <span class="period-label">DATA SCOPE</span>

            <span class="date-range-badge" id="manager-data-scope">
                Loading current production sessions…
            </span>
        </div>

        <!-- MAIN DASHBOARD -->
        <section class="grid-manager-main">

            <!-- LEFT PRIMARY COLUMN -->
            <div>

                <!-- PLANT OEE -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        <span>PLANT OEE</span>
                        <small>ALL LINES · LATEST SESSIONS</small>
                    </div>

                    <div class="card-body">

                        <div class="oee-overview-container">

                            <!-- OEE GAUGE -->
                            <div class="gauge-box">
                                <svg class="gauge-svg" viewBox="0 0 140 140">
                                    <circle
                                        class="gauge-bg"
                                        cx="70"
                                        cy="70"
                                        r="60">
                                    </circle>

                                    <circle
                                        class="gauge-fill"
                                        cx="70"
                                        cy="70"
                                        r="60"
                                        id="gauge-circle">
                                    </circle>
                                </svg>

                                <div class="gauge-center">
                                    <div class="gauge-val" id="oee-val">—</div>
                                    <div class="gauge-lbl">OEE</div>
                                </div>
                            </div>

                            <!-- OEE SUMMARY -->
                            <div class="oee-summary-text">

                                <p id="oee-comparison">
                                    Comparison unavailable until verified historical OEE data exists.
                                </p>

                                <p style="color:var(--text-secondary);margin-top:6px;">
                                    OEE factors will appear when reliable runtime
                                    and cycle-time measurements are recorded.
                                </p>

                            </div>
                        </div>

                        <!-- APQ BREAKDOWN -->
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

                        <!-- PRODUCTION TOTALS -->
                        <div class="volume-row">

                            <div class="volume-item">
                                <label>TOTAL OUTPUT</label>
                                <strong id="vol-total">—</strong>
                            </div>

                            <div class="volume-item">
                                <label>GOOD UNITS</label>
                                <strong
                                    id="vol-good"
                                    style="color:var(--accent-green);">
                                    —
                                </strong>
                            </div>

                            <div class="volume-item">
                                <label>REJECTED (NG)</label>
                                <strong
                                    id="vol-ng"
                                    style="color:var(--brand-orange);">
                                    —
                                </strong>
                            </div>

                        </div>

                    </div>
                </div>

                <!-- OEE BY WEEK -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        <span>OEE BY WEEK</span>
                        <small>LAST 8 WEEKS</small>
                    </div>

                    <div class="card-body">

                        <div class="chart-container">

                            <svg
                                class="trend-svg"
                                viewBox="0 0 600 200"
                                preserveAspectRatio="none">

                                <defs>
                                    <linearGradient
                                        id="blueGradient"
                                        x1="0"
                                        y1="0"
                                        x2="0"
                                        y2="1">

                                        <stop
                                            offset="0%"
                                            stop-color="#58a6ff"
                                            stop-opacity="0.5"/>

                                        <stop
                                            offset="100%"
                                            stop-color="#58a6ff"
                                            stop-opacity="0.0"/>

                                    </linearGradient>
                                </defs>

                                <!-- Y AXIS -->
                                <line
                                    x1="50"
                                    y1="30"
                                    x2="570"
                                    y2="30"
                                    class="grid-line"/>

                                <text
                                    x="40"
                                    y="34"
                                    class="chart-axis-text"
                                    text-anchor="end">
                                    90%
                                </text>

                                <line
                                    x1="50"
                                    y1="80"
                                    x2="570"
                                    y2="80"
                                    class="grid-line"/>

                                <text
                                    x="40"
                                    y="84"
                                    class="chart-axis-text"
                                    text-anchor="end">
                                    80%
                                </text>

                                <line
                                    x1="50"
                                    y1="130"
                                    x2="570"
                                    y2="130"
                                    class="grid-line"/>

                                <text
                                    x="40"
                                    y="134"
                                    class="chart-axis-text"
                                    text-anchor="end">
                                    70%
                                </text>

                                <line
                                    x1="50"
                                    y1="180"
                                    x2="570"
                                    y2="180"
                                    class="grid-line"/>

                                <text
                                    x="40"
                                    y="184"
                                    class="chart-axis-text"
                                    text-anchor="end">
                                    60%
                                </text>

                                <!-- OEE TARGET -->
                                <line
                                    id="chart-target-line"
                                    x1="50"
                                    y1="55"
                                    x2="570"
                                    y2="55"
                                    class="target-line"/>

                                <text
                                    id="chart-target-label"
                                    x="570"
                                    y="48"
                                    class="target-label">
                                    Target 85%
                                </text>

                                <!-- DYNAMIC GRAPH -->
                                <polygon
                                    id="chart-area-fill"
                                    class="area-fill"
                                    points=""/>

                                <path
                                    id="chart-line-path"
                                    class="trend-line"
                                    d=""/>

                                <g id="chart-points-group"></g>

                            </svg>

                        </div>

                    </div>
                </div>

                <!-- WHERE OEE IS LOST -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        <span>WHERE OEE IS LOST</span>
                        <small>Verified shift losses</small>
                    </div>

                    <div class="card-body">
                        <p class="empty-state">
                            Loss breakdown is not available until shifts have
                            verified runtime, output, and reject totals.
                        </p>
                    </div>

                </div>

                <!-- LINE PERFORMANCE -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        <span>LINE PERFORMANCE</span>
                        <small id="oee-target-label">
                            OEE target loading…
                        </small>
                    </div>

                    <div class="card-body" style="padding:0;">

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
                                <!-- Filled by dashboard-manager.js -->
                            </tbody>

                        </table>

                    </div>
                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div>

                <!-- NEEDS YOUR ATTENTION -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        NEEDS YOUR ATTENTION
                    </div>

                    <div
                        class="card-body"
                        id="manager-attention-list">

                        <p class="empty-state">
                            No live alerts available.
                        </p>

                    </div>

                </div>

                <!-- RECURRING DEFECTS -->
                <div class="card">

                    <div class="card-header-bar header-blue">
                        RECURRING DEFECTS
                    </div>

                    <div
                        class="card-body"
                        id="manager-recurring-defects">

                        <p class="empty-state">
                            No verified defect data available.
                        </p>

                    </div>

                </div>

                <!-- EXECUTIVE REPORT -->
                <div class="card">

                    <div class="card-header-bar header-green">
                        EXECUTIVE REPORT · CURRENT PERIOD
                    </div>

                    <div class="card-body">

                        <label
                            style="font-size:0.75rem;color:var(--text-secondary);display:block;margin-bottom:5px;">
                            Report period
                        </label>

                        <select
                            class="report-select"
                            id="manager-report-period">

                            <option value="latest">
                                Latest session per station
                            </option>

                        </select>

                        <!-- REPORT SECTIONS -->
                        <div class="check-group">

                            <label class="check-item">
                                <input
                                    type="checkbox"
                                    data-report-section="oee"
                                    checked>
                                Current production summary
                            </label>

                            <label class="check-item">
                                <input
                                    type="checkbox"
                                    data-report-section="lines"
                                    checked>
                                Output and yield by line
                            </label>

                            <label class="check-item">
                                <input
                                    type="checkbox"
                                    data-report-section="defects"
                                    checked>
                                Defect analysis availability
                            </label>

                        </div>

                        <!-- GENERATE REPORT -->
                        <button
                            class="btn-generate"
                            id="manager-generate-report"
                            type="button">
                            GENERATE REPORT
                        </button>

                        <!-- EXPORT BUTTONS -->
                        <div class="btn-row">

                            <button
                                class="btn-export-sub"
                                id="manager-export-pdf"
                                type="button">
                                Print / Save PDF
                            </button>

                            <button
                                class="btn-export-sub"
                                id="manager-export-csv"
                                type="button">
                                Export CSV
                            </button>

                        </div>

                    </div>
                </div>

            </div>

        </section>

    </main>

    <!-- =========================================
         MANAGER REALTIME DASHBOARD CONNECTION
         ========================================= -->

    <script>
        document.documentElement.dataset.baseUrl =
            <?= json_encode(BASE_URL) ?>;
    </script>

    <!-- Realtime monitoring: refresh every 5 seconds -->
    <script
        src="<?= BASE_URL ?>/assets/js/dashboard-manager.js?v=2"
        defer>
    </script>

</body>
</html>

<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/functions.php';

// Authorize access strictly for Staff role
check_role('staff');
$staffContext = db_one($conn, "SELECT u.assigned_station, s.line_name FROM users u
                               LEFT JOIN master_stations s ON s.station_code = u.assigned_station
                               WHERE u.id = ?", "i", [(int)$_SESSION['user_id']]);
$staffStation = trim($staffContext['assigned_station'] ?? '');
$staffLine = trim($staffContext['line_name'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Terminal - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">

    <style>
        :root {
            --bg-dark: #12161a;
            --card-bg: #22272d;
            --card-border: #30363d;
            --text-primary: #e6edf3;
            --text-secondary: #8b949e;
            --accent-green: #7ee787;
            --accent-yellow: #e3b341;
            --accent-red: #f85149;
            --accent-blue: #1f6beb;
            --brand-orange: #f0883e;
        }

        * { box-sizing: border-box; }

        body {
            margin: 0;
            background-color: var(--bg-dark);
            color: var(--text-primary);
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
        }

        /* Layout Container */
        .main-content {
            margin-left: 250px;
            padding: 25px;
            min-height: 100vh;
        }

        /* Header Bar */
        .dashboard-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 20px;
        }

        .title-area h1 {
            font-size: 1.8rem;
            display: inline-block;
            margin: 0;
        }

        .view-badge {
            background: #21262d;
            font-size: 0.75rem;
            padding: 3px 8px;
            border-radius: 12px;
            color: var(--text-secondary);
            margin-left: 10px;
            vertical-align: middle;
        }

        .subtitle {
            color: var(--text-secondary);
            font-size: 0.85rem;
            margin-top: 4px;
        }

        .time-area {
            text-align: right;
            color: var(--text-secondary);
            font-size: 0.85rem;
        }

        /* Metric Grid Cards */
        .grid-top {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 15px;
            margin-bottom: 20px;
        }

        .card {
            background: var(--card-bg);
            border: 1px solid var(--card-border);
            border-radius: 8px;
            padding: 18px;
        }

        .card-title {
            font-size: 0.75rem;
            color: var(--text-secondary);
            text-transform: uppercase;
            margin-bottom: 10px;
        }

        .metric-large {
            font-size: 2.2rem;
            font-weight: bold;
            color: var(--accent-green);
        }

        .metric-large .target {
            color: var(--text-secondary);
            font-size: 1.5rem;
        }

        .metric-sub {
            font-size: 0.8rem;
            color: var(--text-secondary);
            margin-top: 8px;
        }

        .status-card {
            background: #142a1b;
            border-color: #238636;
        }

        .status-card .status-indicator {
            color: var(--accent-green);
            font-weight: bold;
            display: flex;
            align-items: center;
            gap: 8px;
        }

        .dot {
            width: 10px;
            height: 10px;
            background: var(--accent-green);
            border-radius: 50%;
            display: inline-block;
        }

        /* Middle & Bottom Grids */
        .grid-main {
            display: grid;
            grid-template-columns: 2fr 1fr;
            gap: 15px;
            margin-bottom: 20px;
        }

        .chart-header {
            background: #0d419d;
            margin: -18px -18px 15px -18px;
            padding: 10px 18px;
            border-top-left-radius: 8px;
            border-top-right-radius: 8px;
            display: flex;
            justify-content: space-between;
            font-size: 0.8rem;
            font-weight: bold;
        }

        /* Hardware Simulator Button Strip */
        .simulator-bar {
            background: #161b22;
            border: 1px dashed var(--card-border);
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .simulator-bar h4 {
            margin: 0;
            font-size: 0.9rem;
            color: var(--text-secondary);
        }

        .simulator-bar form { display: flex; align-items: center; gap: 10px; }
        .simulator-bar label { color: var(--text-secondary); font-size: 0.8rem; }
        .simulator-bar input[type="number"] {
            width: 86px;
            padding: 8px;
            border: 1px solid var(--card-border);
            border-radius: 6px;
            background: #161b22;
            color: var(--text-primary);
        }
        .simulator-bar button:disabled { opacity: 0.55; cursor: not-allowed; }

        .btn-group {
            display: flex;
            gap: 10px;
        }

        .btn {
            padding: 8px 16px;
            font-size: 0.85rem;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            color: white;
            font-weight: bold;
            transition: opacity 0.2s;
        }

        .btn:hover { opacity: 0.85; }
        .btn-good { background: #238636; }
        .btn-ng { background: #da3633; }

        /* Hourly Bar Chart Simulation */
        .bars-container {
            display: flex;
            justify-content: space-around;
            align-items: flex-end;
            height: 160px;
            padding-top: 20px;
        }

        #hourly-chart { overflow-x:auto; justify-content:flex-start; gap:16px; }
        .bar-group { flex:0 0 58px; }
        .bar-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            width: 40px;
        }

        .bar-track {
            height: 100px;
            display: flex;
            align-items: flex-end;
            gap: 3px;
        }

        .bar-good { width: 14px; background: var(--accent-yellow); border-radius: 2px 2px 0 0; }
        .bar-ng { width: 8px; background: var(--accent-red); border-radius: 2px 2px 0 0; }
        .bar-labels { font-size: 0.72rem; color: var(--text-secondary); text-align: center; margin-top: 8px; }

        /* Category Progress Bars */
        .category-row { margin-bottom: 12px; }
        .category-header { display: flex; justify-content: space-between; font-size: 0.85rem; margin-bottom: 5px; }
        .progress-bg { background: #161b22; height: 8px; border-radius: 4px; overflow: hidden; }
        .progress-fill { background: var(--brand-orange); height: 100%; }

        /* Activity Feeds */
        .activity-item, .report-item {
            display: flex;
            align-items: center;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #21262d;
            font-size: 0.83rem;
        }

        .activity-time { color: var(--accent-green); font-family: monospace; font-weight: bold; }
        .activity-time.defect { color: var(--accent-red); }

        .severity-pill {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: bold;
        }
        .severity-Major { background: var(--accent-yellow); color: #000; }
        .severity-Minor { background: #238636; color: #fff; }

        .link-btn {
            display: inline-block;
            color: var(--accent-yellow);
            text-decoration: none;
            font-size: 0.85rem;
            margin-top: 10px;
        }

        @media (max-width: 1024px) {
            .grid-top { grid-template-columns: repeat(2, 1fr); }
            .grid-main { grid-template-columns: 1fr; }
            .simulator-bar { align-items: flex-start; flex-direction: column; gap: 12px; }
            .simulator-bar form { flex-wrap: wrap; }
        }
    </style>
    <!-- Staff-only theme; all existing JS/PHP processes remain unchanged -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/staff-theme.css?v=1">
</head>
<body class="staff-theme">

    <!-- Shared Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">

        <!-- Top Header -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Dashboard</h1> <span class="view-badge">VIEW ONLY</span>
                <p class="subtitle">Live machine status, unit counters and real-time production for your station.</p>
            </div>
            <div class="time-area">
                <div id="live-updated" style="color:#7ee787;font-size:.73rem;margin-bottom:4px" role="status">Connecting to live data…</div>
                <div id="current-datetime"><?= date('M d, Y · h:i A'); ?></div>
                <div style="color:var(--text-secondary)" id="station-id">Station <?= htmlspecialchars($staffStation !== '' ? $staffStation . ($staffLine !== '' ? ' · ' . $staffLine : '') : 'Not assigned', ENT_QUOTES); ?></div>
            </div>
        </header>

        <section class="simulator-bar" aria-labelledby="counter-heading">
            <div>
                <h4 id="counter-heading">SOFTWARE PRODUCTION COUNTER</h4>
                <p id="counting-status" class="metric-sub">Good-unit counts are recorded against your assigned station.</p>
            </div>
            <form id="manual-count-form">
                <label for="count-quantity">Good units</label>
                <input type="number" id="count-quantity" name="qty" min="1" max="100" value="1" required>
                <input type="hidden" name="type" value="good">
                <input type="hidden" name="station_code" value="<?= htmlspecialchars($staffStation, ENT_QUOTES); ?>">
                <button class="btn btn-good" type="submit" id="count-submit" <?= $staffStation === '' ? 'disabled' : '' ?>>ADD GOOD UNITS</button>
            </form>
        </section>

        <!-- Top Cards Metrics -->
        <section class="grid-top">
            <div class="card">
                <div class="card-title">Daily Target</div>
                <div class="metric-large"><span id="target_goal">0</span></div>
                <div class="metric-sub">Target units · <span id="done-pct">0%</span> completed</div>
            </div>

            <div class="card">
                <div class="card-title">Good Units Produced</div>
                <div class="metric-large" style="color:var(--accent-yellow)" id="good_units_val">0</div>
                <div class="metric-sub">Yield rate · <span id="yield_rate">0%</span></div>
            </div>

            <div class="card">
                <div class="card-title">Confirmed NG Units</div>
                <div class="metric-large" style="color:var(--brand-orange)" id="ng_count">0</div>
                <div class="metric-sub">Confirmed rejects in current production session</div>
            </div>

            <div class="card status-card">
                <div class="status-indicator">
                    <span class="dot"></span> <span id="hw-status">COUNTER STATUS</span>
                </div>
                <div id="hardware-detail" style="margin-top:15px; font-size:0.8rem; color:var(--text-secondary)">Checking production session…</div>
            </div>
        </section>

        <!-- Hourly Chart & Defect Categories -->
        <section class="grid-main">
            <!-- Hourly Production Chart -->
            <div class="card">
                <div class="chart-header">
                    <span>OUTPUT PER HOUR</span>
                    <div>
                        <span style="color:var(--accent-yellow)">■ Good</span> &nbsp; 
                        <span style="color:var(--brand-orange)">■ NG</span>
                    </div>
                </div>
                <div class="bars-container" id="hourly-chart"><p style="color:var(--text-secondary);font-size:.85rem">Loading hourly station production…</p>
                </div>
            </div>

            <!-- Defects by Category -->
            <div class="card">
                <div class="card-title">Defects By Category</div>
                <div id="category-list"><p style="color:var(--text-secondary);font-size:.85rem">Loading confirmed defects…</p></div>
                <div style="margin-top:20px; font-size:0.85rem; display:flex; justify-content:space-between;">
                    <span>Confirmed rejected units today</span>
                    <strong id="total-rejected">0</strong>
                </div>
            </div>
        </section>

        <!-- Shift Logs & Latest Defects -->
        <section class="grid-main">
            <!-- Activity Stream -->
            <div class="card">
                <div class="chart-header" style="background:#0d419d">RECENT SHIFT ACTIVITY</div>
                <div id="activity-log-list"><p style="color:var(--text-secondary);font-size:.85rem">Loading activity…</p>
                </div>
            </div>

            <!-- Latest Defect Reports -->
            <div class="card">
                <div class="chart-header" style="background:#1b4b27">LATEST DEFECT REPORTS</div>
                <div id="latest-defect-list"><p style="color:var(--text-secondary);font-size:.85rem">Loading reports…</p></div>
                <a href="<?= BASE_URL ?>/views/staff/defect_reporting.php" class="link-btn">Open Defect Reporting →</a>
            </div>
        </section>

    </main>

    <!-- AJAX Logic and Live API Integrations -->
<script>const BASE_URL = <?= json_encode(BASE_URL); ?>;</script>
<script src="<?= BASE_URL ?>/assets/js/dashboard-common.js"></script>
<script src="<?= BASE_URL ?>/assets/js/dashboard-staff.js?v=2"></script>
</body>
</html>
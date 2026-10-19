
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
    <meta name="viewport"
          content="width=device-width, initial-scale=1.0">

    <title>Floor Overview - Masters Vineyard Enterprise</title>

    <!-- SHARED STYLES -->
    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/sidebar.css">

    <link rel="stylesheet"
          href="<?= BASE_URL ?>/assets/css/supervisor_dashboard.css">

    <style>
        /* =========================================
           SUPERVISOR LIVE STATUS
        ========================================= */

        .supervisor-live-status {
            display: inline-flex;
            align-items: center;
            gap: 8px;
            margin-top: 10px;
            font-size: 12px;
            font-weight: 500;
            color: #22c55e;
        }

        .supervisor-live-status .live-dot {
            width: 8px;
            height: 8px;
            flex-shrink: 0;
            border-radius: 50%;
            background: #22c55e;
            box-shadow: 0 0 0 3px rgba(34,197,94,.13);
        }

        .supervisor-live-status.error {
            color: #ef4444;
        }

        .supervisor-live-status.error .live-dot {
            background: #ef4444;
            box-shadow: 0 0 0 3px rgba(239,68,68,.12);
        }

        .supervisor-live-status.paused {
            color: #eab308;
        }

        .supervisor-live-status.paused .live-dot {
            background: #eab308;
        }

        /* Keep monitoring status from blocking navigation */
        .supervisor-live-status {
            pointer-events: none;
        }
    </style>
</head>

<body>

    <!-- =========================================
         SHARED SIDEBAR
    ========================================= -->

    <?php
    include_once __DIR__ . '/../../includes/sidebar.php';
    ?>

    <main class="main-content">

        <!-- =========================================
             DASHBOARD HEADER
        ========================================= -->

        <header class="dashboard-header">

            <div class="title-area">

                <h1>Floor Overview</h1>

                <p class="subtitle">
                    Real-time status of every station on the line,
                    overall yield, and defect reports waiting
                    for your review.
                </p>

                <!-- Realtime monitoring indicator -->
                <div
                    class="supervisor-live-status"
                    id="supervisor-live-indicator"
                    role="status"
                    aria-live="polite">

                    <span class="live-dot"></span>

                    <span id="supervisor-live-text">
                        Connecting to production monitoring...
                    </span>

                </div>

            </div>

            <div class="time-area">

                <div>
                    <?= date('M d, Y · h:i A'); ?>
                </div>

                <span class="session-badge">

                    ●

                    <span id="session-badge-text">
                        Loading session status…
                    </span>

                </span>

            </div>

        </header>

        <!-- =========================================
             TOP METRIC SUMMARY CARDS
        ========================================= -->

        <section class="grid-top-metrics">

            <!-- LINE OUTPUT VS TARGET -->
            <div class="metric-card">

                <div class="card-title">
                    Line Output vs Target
                </div>

                <div
                    class="card-val"
                    id="stat-output">

                    —

                    <span style="
                        font-size:1.2rem;
                        color:var(--text-secondary);
                        font-weight:normal;
                    ">
                        / —
                    </span>

                </div>

                <div class="target-progress-bg">

                    <div
                        class="target-progress-fill"
                        id="stat-progress-bar"
                        style="width:0%;">
                    </div>

                </div>

                <div
                    class="card-sub"
                    style="margin-top:6px;"
                    id="stat-project-sub">

                    Loading production summary…

                </div>

            </div>

            <!-- OVERALL YIELD -->
            <div class="metric-card">

                <div class="card-title">
                    Overall Yield
                </div>

                <div
                    class="card-val"
                    id="stat-yield"
                    style="color:var(--accent-yellow);">

                    —

                </div>

                <div
                    class="card-sub"
                    id="stat-units-sub">

                    Waiting for production data…

                </div>

            </div>

            <!-- REPORTS TO REVIEW -->
            <div class="metric-card">

                <div class="card-title">
                    Reports to Review
                </div>

                <div
                    class="card-val"
                    id="stat-reports"
                    style="color:var(--brand-orange);">

                    —

                </div>

                <a
                    href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php"
                    class="card-link">

                    Open queue →

                </a>

            </div>

            <!-- STATIONS ONLINE -->
            <div class="metric-card">

                <div class="card-title">
                    Stations Online
                </div>

                <div
                    class="card-val"
                    id="stat-stations">

                    —

                    <span style="
                        font-size:1.2rem;
                        color:var(--text-secondary);
                        font-weight:normal;
                    ">
                        / —
                    </span>

                </div>

                <div
                    class="card-sub"
                    id="stat-stations-sub">

                    Loading station status…

                </div>

            </div>

        </section>

        <!-- =========================================
             MAIN DASHBOARD CONTENT
        ========================================= -->

        <section class="grid-sp-main">

            <!-- LEFT COLUMN -->
            <div>

                <!-- =================================
                     STATION STATUS
                ================================= -->

                <div class="card">

                    <div class="card-header-bar header-blue">

                        <span>
                            STATION STATUS
                        </span>

                        <a
                            href="<?= BASE_URL ?>/views/supervisor/line_control.php"
                            style="
                                color:#fff;
                                text-decoration:none;
                                font-size:0.75rem;
                            ">

                            Manage in Line Control →

                        </a>

                    </div>

                    <div class="card-body">

                        <div
                            class="station-cards-grid"
                            id="stations-box-container">

                            <div style="
                                padding:18px;
                                color:var(--text-secondary);
                            ">

                                Loading current station information…

                            </div>

                        </div>

                    </div>

                </div>

                <!-- =================================
                     FLOOR ALERTS
                ================================= -->

                <div class="card">

                    <div class="card-header-bar header-red">
                        FLOOR ALERTS
                    </div>

                    <div
                        class="card-body"
                        id="alerts-feed-container">

                        <p style="color:var(--text-secondary);">
                            Loading floor alerts…
                        </p>

                    </div>

                </div>

            </div>

            <!-- RIGHT COLUMN -->
            <div>

                <!-- =================================
                     LINE OUTPUT PER HOUR
                ================================= -->

                <div class="card">

                    <div class="card-header-bar header-green">

                        <span>
                            LINE OUTPUT PER HOUR
                        </span>

                        <div class="legend-bar-row">

                            <span>

                                <span
                                    class="legend-dot"
                                    style="background:var(--accent-yellow);">
                                </span>

                                Good

                            </span>

                            <span>

                                <span
                                    class="legend-dot"
                                    style="background:var(--brand-orange);">
                                </span>

                                NG

                            </span>

                        </div>

                    </div>

                    <div class="card-body">

                        <div class="chart-container-hourly">

                            <svg
                                class="hourly-svg"
                                viewBox="0 0 300 150"
                                preserveAspectRatio="none">

                                <g id="svg-bars-group"></g>

                            </svg>

                        </div>

                    </div>

                </div>

                <!-- =================================
                     WAITING FOR REVIEW
                ================================= -->

                <div class="card">

                    <div class="card-header-bar header-green">

                        <span>
                            WAITING FOR REVIEW
                        </span>

                        <a
                            href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php"
                            style="
                                color:#fff;
                                text-decoration:none;
                                font-size:0.75rem;
                            ">

                            Open full queue →

                        </a>

                    </div>

                    <div
                        class="card-body"
                        id="reviews-list-container">

                        <p style="color:var(--text-secondary);">
                            Loading pending defect reports…
                        </p>

                    </div>

                </div>

            </div>

        </section>

    </main>

    <!-- =========================================
         REALTIME MONITORING CONFIGURATION
    ========================================= -->

    <script>
        document.documentElement.dataset.baseUrl =
            <?= json_encode(BASE_URL) ?>;
    </script>

    <!-- =========================================
         SUPERVISOR DASHBOARD JAVASCRIPT
         No MutationObserver.
         No duplicate polling.
    ========================================= -->

    <script
        src="<?= BASE_URL ?>/assets/js/dashboard-supervisor.js?v=4"
        defer>
    </script>

</body>
</html>

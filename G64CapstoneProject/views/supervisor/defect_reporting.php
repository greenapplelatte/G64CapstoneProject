<?php
require_once __DIR__ . '/../../config/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';
require_once __DIR__ . '/../../includes/auth.php';

// Supervisor review queue. Staff use views/staff/defect_reporting.php instead.
check_role('supervisor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defect Queue &amp; Review - Masters Vineyard Enterprise</title>

    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/defect_reporting.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/defect_queue.css">
</head>
<body>

    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Defect Queue &amp; Review</h1>
                <p class="subtitle">Confirm a report to count its units as rejected, reject it to release them, or request a recheck to keep them pending.</p>
            </div>
            <div class="time-area">
                <div id="current-datetime"><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <section class="grid-top">
            <div class="metric-card">
                <div class="card-title">Awaiting review</div>
                <div class="card-val" style="color: var(--accent-yellow);" id="stat-pending">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Recheck requested</div>
                <div class="card-val" style="color: #58a6ff;" id="stat-recheck">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Confirmed</div>
                <div class="card-val" style="color: var(--accent-green);" id="stat-confirmed">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Rejected</div>
                <div class="card-val" style="color: var(--accent-red);" id="stat-rejected">0</div>
            </div>
        </section>

        <div class="card">
            <div class="card-header-bar header-blue queue-head">
                <span>REPORT QUEUE</span>
                <small id="queue-live" aria-live="polite">Updates every 10 seconds</small>
            </div>
            <div class="card-body">
                <div class="filter-pills" role="group" aria-label="Filter reports by status">
                    <button type="button" class="filter-pill active" data-filter="review">To review · <span id="count-review">0</span></button>
                    <button type="button" class="filter-pill" data-filter="confirmed">Confirmed · <span id="count-confirmed">0</span></button>
                    <button type="button" class="filter-pill" data-filter="rejected">Rejected · <span id="count-rejected">0</span></button>
                    <button type="button" class="filter-pill" data-filter="all">All · <span id="count-all">0</span></button>
                </div>

                <div id="queue-list" class="queue-list" aria-live="polite">
                    <p class="queue-empty">Loading reports…</p>
                </div>

                <div class="queue-pagination" id="queue-pagination" style="display:none;">
                    <button type="button" id="queue-prev" class="queue-page-btn">← Previous</button>
                    <span id="queue-page-status" class="queue-page-count">Page 1 of 1</span>
                    <button type="button" id="queue-next" class="queue-page-btn">Next →</button>
                </div>
            </div>
        </div>
    </main>

    <script src="<?= BASE_URL ?>/assets/js/defect_queue.js"></script>
</body>
</html>
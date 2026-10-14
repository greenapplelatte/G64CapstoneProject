<?php
// Enable error output for debugging
ini_set('display_errors', 1);
error_reporting(E_ALL);

require_once __DIR__ . '/../../config/app.php';   // starts the session with the shared cookie options
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check database file existence safely
$db_path = __DIR__ . '/../../config/database.php';
$auth_path = __DIR__ . '/../../includes/auth.php';

if (!file_exists($db_path)) {
    die("Error: Could not find database configuration at: " . realpath(__DIR__ . '/../../') . "/config/database.php");
}

if (!file_exists($auth_path)) {
    die("Error: Could not find auth file at: " . realpath(__DIR__ . '/../../') . "/includes/auth.php");
}

require_once $db_path;
require_once __DIR__ . '/../../includes/functions.php'; // Provides db_one(), db_all(), and db_run()
require_once $auth_path;

// Fallback role check if function exists
if (function_exists('check_role')) {
    check_role('staff');
    $staffContext = db_one($conn, "SELECT u.assigned_station, s.workstation_name FROM users u LEFT JOIN master_stations s ON s.station_code = u.assigned_station WHERE u.id = ?", "i", [(int)$_SESSION['user_id']]);
} else {
    if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'staff') {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}

$stationOptions = db_all($conn, "SELECT station_code, workstation_name FROM master_stations WHERE status <> 'Maintenance' ORDER BY station_code");
$categoryOptions = db_all($conn, "SELECT category_name FROM defect_categories ORDER BY category_name");
$projectOptions = db_all($conn, "SELECT project_type FROM master_project_types WHERE is_active = 1 ORDER BY project_type");
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Defect Reporting - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Page CSS -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/defect_reporting.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Defect Reporting</h1>
                <p class="subtitle">Submit a defect for supervisor review. Rejected-unit totals change only after the report is confirmed.</p>
            </div>
            <div class="time-area">
                <div id="current-datetime"><?= date('M d, Y · h:i A'); ?></div>
                <div style="color:var(--text-secondary)">Station <strong><?= htmlspecialchars(($staffContext['assigned_station'] ?? '') ?: 'Not assigned', ENT_QUOTES); ?></strong></div>
            </div>
        </header>

        <!-- Metric Cards -->
        <section class="grid-top">
            <div class="metric-card">
                <div class="card-title">Reports today</div>
                <div class="card-val" style="color: var(--brand-orange);" id="stat-total">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Minor</div>
                <div class="card-val" style="color: var(--accent-green);" id="stat-minor">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Major</div>
                <div class="card-val" style="color: var(--accent-yellow);" id="stat-major">0</div>
            </div>
            <div class="metric-card">
                <div class="card-title">Critical</div>
                <div class="card-val" style="color: var(--accent-red);" id="stat-critical">0</div>
            </div>
        </section>

        <!-- Main Layout -->
        <section class="grid-main">
            <!-- Left Form Container -->
            <div class="card">
                <div class="card-header-bar header-green">NEW DEFECT REPORT</div>
                <div class="card-body">
                    <form id="defectForm" enctype="multipart/form-data">
                        <div class="form-row">
                            <div class="form-group">
                                <label>Project Type</label>
                                <select name="project_type" class="form-control" required>
                                    <option value="" disabled selected>Choose</option>
                                    <?php foreach ($projectOptions as $project): ?><option value="<?= htmlspecialchars($project['project_type'], ENT_QUOTES); ?>"><?= htmlspecialchars($project['project_type']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Workstation</label>
                                <select name="workstation" class="form-control" required>
                                    <option value="" disabled selected>Choose</option>
                                    <?php foreach ($stationOptions as $stationOption): if (($stationOption['station_code'] ?? '') !== ($staffContext['assigned_station'] ?? '')) continue; ?><option value="<?= htmlspecialchars($stationOption['workstation_name'], ENT_QUOTES); ?>"><?= htmlspecialchars($stationOption['workstation_name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                        </div>

                        <div class="form-row">
                            <div class="form-group">
                                <label>Defect Category</label>
                                <select name="defect_category" class="form-control" required>
                                    <option value="" disabled selected>Choose</option>
                                    <?php foreach ($categoryOptions as $categoryOption): ?><option value="<?= htmlspecialchars($categoryOption['category_name'], ENT_QUOTES); ?>"><?= htmlspecialchars($categoryOption['category_name']); ?></option><?php endforeach; ?>
                                </select>
                            </div>
                            <div class="form-group">
                                <label>Quantity rejected</label>
                                <input type="number" name="quantity_rejected" class="form-control" value="1" min="1" required>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Severity</label>
                            <input type="hidden" name="severity" id="severity_val" value="Major">
                            <div class="severity-group">
                                <button type="button" class="btn-severity" data-val="Minor" onclick="selectSeverity('Minor')">Minor</button>
                                <button type="button" class="btn-severity active" data-val="Major" onclick="selectSeverity('Major')">Major</button>
                                <button type="button" class="btn-severity" data-val="Critical" onclick="selectSeverity('Critical')">Critical</button>
                            </div>
                        </div>

                        <div class="form-group" style="margin-bottom: 15px;">
                            <label>Notes (optional)</label>
                            <textarea name="notes" class="form-control" placeholder="What did you see? Where on the unit?"></textarea>
                        </div>

                        <div class="photo-upload-box">
                            <span id="photo-filename">📷 Add a photo of the defective unit</span>
                            <button type="button" class="btn-attach" onclick="document.getElementById('photoInput').click()">Attach photo</button>
                            <input type="file" name="photo" id="photoInput" accept="image/jpeg,image/png,image/webp" style="display: none;" onchange="handleFileSelect(this)">
                            <img id="photo-preview" alt="Selected defect photo preview" style="display:none;max-width:100%;max-height:180px;margin-top:10px;border-radius:8px;">
                        </div>

                        <div class="form-footer">
                            <div class="footer-info">
                                Reported by <strong><?= htmlspecialchars($_SESSION['full_name'] ?? 'Staff1'); ?></strong> · Time is added automatically
                            </div>
                            <div class="action-buttons">
                                <button type="reset" class="btn-clear" onclick="resetForm()">Clear</button>
                                <button type="submit" class="btn-submit-report">SUBMIT REPORT</button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Right Side Recent Reports & Guide -->
            <div>
                <!-- Recent Reports -->
                <div class="card">
                    <div class="card-header-bar header-blue">MY RECENT REPORTS</div>
                    <div class="card-body" id="recent-reports-list">
                        <!-- Populated via JS -->
                    </div>
                </div>

                <!-- Severity Legend -->
                <div class="card">
                    <div class="card-header-bar header-dark">SEVERITY GUIDE</div>
                    <div class="card-body">
                        <div class="guide-item">
                            <strong class="dot-minor">● Minor</strong>
                            <p>Cosmetic only. The unit still works.</p>
                        </div>
                        <div class="guide-item">
                            <strong class="dot-major">● Major</strong>
                            <p>Affects fit or function. Needs rework.</p>
                        </div>
                        <div class="guide-item">
                            <strong class="dot-critical">● Critical</strong>
                            <p>Unsafe or unusable. Tell your supervisor right away.</p>
                        </div>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- JS Form & Fetch Handling -->
    <script>const BASE_URL = <?= json_encode(BASE_URL); ?>;</script>
    <script src="<?= BASE_URL ?>/assets/js/defect_reporting.js"></script>
</body>
</html>
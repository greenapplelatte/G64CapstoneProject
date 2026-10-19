<?php
// backend/process_defect.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('staff');

$action  = $_GET['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

if ($action === 'submit_report') {
    require_post();
    $project_type    = trim($_POST['project_type'] ?? '');
    $workstation     = trim($_POST['workstation'] ?? '');
    $defect_category = trim($_POST['defect_category'] ?? '');
    $quantity        = intval($_POST['quantity_rejected'] ?? 1);
    $severity        = $_POST['severity'] ?? 'Minor';
    $notes           = trim($_POST['notes'] ?? '');
    $reported_by     = $_SESSION['full_name'] ?? 'Staff';
    $report_time     = date('H:i:s');

    if ($project_type === '' || $workstation === '' || $defect_category === '') {
        json_out(['status' => 'error', 'message' => 'Choose a project, workstation, and defect category.'], 400);
    }
    if ($defect_category === '') {
        json_out(['status' => 'error', 'message' => 'Please choose a defect category.'], 400);
    }
    try {
        $validCategory = db_one($conn, "SELECT id FROM defect_categories WHERE category_name = ? LIMIT 1", "s", [$defect_category]);
        $validProject = db_one($conn, "SELECT id FROM master_project_types WHERE project_type = ? AND is_active = 1 LIMIT 1", "s", [$project_type]);
        $validWorkstation = db_one($conn, "SELECT id FROM master_workstations WHERE workstation_name = ? AND is_active = 1 LIMIT 1", "s", [$workstation]);
    } catch (mysqli_sql_exception $e) {
        error_log('Defect report master data schema error: ' . $e->getMessage());
        json_out(['status' => 'error', 'message' => 'Master Data needs a database update. Ask a manager to import config/database.sql, then try again.'], 503);
    }
    if (!$validCategory) json_out(['status' => 'error', 'message' => 'Choose a valid defect category from the list.'], 400);
    if (!$validProject) json_out(['status' => 'error', 'message' => 'Choose a valid project type from the list.'], 400);
    if (!$validWorkstation) json_out(['status' => 'error', 'message' => 'Choose an active workstation from the list.'], 400);
    if ($quantity < 1 || $quantity > 1000) {
        json_out(['status' => 'error', 'message' => 'Quantity must be between 1 and 1000.'], 400);
    }
    if (!in_array($severity, ['Minor', 'Major', 'Critical'], true)) {
        json_out(['status' => 'error', 'message' => 'Invalid severity.'], 400);
    }

    // The report belongs to the operator's own station (must exist in master_stations)
    $me = db_one($conn, "SELECT assigned_station FROM users WHERE id = ?", "i", [$user_id]);
    $station = null;
    if ($me && !empty($me['assigned_station'])) {
        $ok = db_one($conn, "SELECT station_code FROM master_stations WHERE station_code = ?", "s", [$me['assigned_station']]);
        $station = $ok['station_code'] ?? null;
    }
    if ($station === null) {
        json_out(['status' => 'error', 'message' => 'No valid station is assigned to your account. Contact your supervisor before submitting a report.'], 409);
    }

    try {
        $conn->begin_transaction();

        // Saved as Pending. NG counts are NOT changed here; they change only when a supervisor confirms.
        db_run($conn,
            "INSERT INTO defect_reports
                (project_type, workstation, station, defect_category, quantity_rejected, severity, notes, reported_by, reported_by_id, report_time)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)",
            "ssssisssis",
            [$project_type, $workstation, $station, $defect_category, $quantity, $severity, $notes, $reported_by, $user_id, $report_time]);

        // The units are shown as "pending review" on the open shift until the supervisor decides
        $shift = get_open_shift($conn);
        if ($shift && $station !== null) {
            ws_adjust($conn, (int)$shift['id'], $station, 0, 0, $quantity);
        }

        floor_alert($conn, 'defect', "New defect report: $defect_category",
            ($station ?? 'Unknown station') . ($workstation !== '' ? " · $workstation" : '') . " · $quantity unit(s) · $severity");

        log_activity($conn, (string)$station, 'Defect', "Defect report submitted: $defect_category ($quantity unit(s))", $user_id);

        $conn->commit();
        json_out(['status' => 'success', 'message' => 'Defect report submitted and sent to the supervisor review queue.']);
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to save defect report.'], 500);
    }
}

if ($action === 'cancel_report') {
    require_post();
    $reportId = (int)($_POST['report_id'] ?? 0);
    if ($reportId < 1) json_out(['status'=>'error','message'=>'Invalid report.'], 400);
    $report = db_one($conn, "SELECT id, station, quantity_rejected FROM defect_reports WHERE id = ? AND reported_by_id = ? AND status = 'Pending' LIMIT 1", 'ii', [$reportId, $user_id]);
    if (!$report) json_out(['status'=>'error','message'=>'Only your own pending reports can be cancelled.'], 409);
    try {
        $conn->begin_transaction();
        db_run($conn, "DELETE FROM defect_reports WHERE id = ? AND reported_by_id = ? AND status = 'Pending'", 'ii', [$reportId, $user_id]);
        $shift = get_open_shift($conn);
        if ($shift) ws_adjust($conn, (int)$shift['id'], (string)$report['station'], 0, 0, -(int)$report['quantity_rejected']);
        log_activity($conn, (string)$report['station'], 'Defect', 'Pending defect report cancelled', $user_id);
        $conn->commit();
        json_out(['status'=>'success','message'=>'Pending defect report cancelled.']);
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status'=>'error','message'=>'Could not cancel this report. Please try again.'], 500);
    }
}

if ($action === 'get_reports') {
    // Total stats
    $stats = db_one($conn, "SELECT
        COUNT(*) as total_today,
        COALESCE(SUM(CASE WHEN severity = 'Minor' THEN 1 ELSE 0 END), 0) as minor_count,
        COALESCE(SUM(CASE WHEN severity = 'Major' THEN 1 ELSE 0 END), 0) as major_count,
        COALESCE(SUM(CASE WHEN severity = 'Critical' THEN 1 ELSE 0 END), 0) as critical_count
    FROM defect_reports WHERE reported_by_id = ? AND DATE(created_at) = CURDATE()", "i", [$user_id]);

    // Recent reports
    $reports = db_all($conn, "SELECT id, project_type, workstation, defect_category, quantity_rejected, severity, notes, status, supervisor_comment, report_time, created_at FROM defect_reports WHERE reported_by_id = ? ORDER BY id DESC LIMIT 10", "i", [$user_id]);

    json_out([
        'status'  => 'success',
        'stats'   => $stats,
        'reports' => $reports
    ]);
}

json_out(['status' => 'error', 'message' => 'Unknown action'], 400);
?>
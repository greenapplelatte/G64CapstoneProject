<?php
// backend/process_defect_review.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');

$action = $_GET['action'] ?? '';

// Fetch Queue Data & Stats
if ($action === 'get_queue') {
    $reports = db_all($conn, "SELECT * FROM defect_reports ORDER BY id DESC");
    $pending_cnt = 0;
    $confirmed_cnt = 0;
    $rejected_cnt = 0;

    foreach ($reports as $row) {
        if ($row['status'] === 'Pending') $pending_cnt++;
        if ($row['status'] === 'Confirmed') $confirmed_cnt++;
        if ($row['status'] === 'Rejected') $rejected_cnt++;
    }

    json_out([
        'status' => 'success',
        'stats' => [
            'pending' => $pending_cnt,
            'confirmed' => $confirmed_cnt,
            'rejected' => $rejected_cnt,
            'all' => count($reports)
        ],
        'reports' => $reports
    ]);
}

// Review a report: Confirmed / Rejected / Recheck Requested
if ($action === 'review_report') {
    require_post();
    $report_id = intval($_POST['report_id'] ?? 0);
    $decision  = $_POST['decision'] ?? '';
    $comment   = trim($_POST['supervisor_comment'] ?? '');

    if ($report_id <= 0) {
        json_out(['status' => 'error', 'message' => 'Invalid report ID.'], 400);
    }
    if (!in_array($decision, ['Confirmed', 'Rejected', 'Recheck Requested'], true)) {
        json_out(['status' => 'error', 'message' => 'Invalid decision.'], 400);
    }

    try {
        $conn->begin_transaction();

        // Lock the row so two quick clicks cannot both be counted
        $rep = db_one($conn, "SELECT * FROM defect_reports WHERE id = ? FOR UPDATE", "i", [$report_id]);
        if (!$rep) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'Report not found.'], 404);
        }
        if (!in_array($rep['status'], ['Pending', 'Recheck Requested'], true)) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => "This report was already reviewed (status: {$rep['status']})."], 409);
        }
        if ($rep['status'] === 'Recheck Requested' && $decision === 'Recheck Requested') {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'A recheck was already requested for this report.'], 409);
        }

        db_run($conn,
            "UPDATE defect_reports SET status = ?, supervisor_comment = ? WHERE id = ?",
            "ssi", [$decision, $comment, $report_id]);

        $qty     = (int)$rep['quantity_rejected'];
        $station = $rep['station'];
        $shift   = get_open_shift($conn);

        if ($station) {
            if ($decision === 'Confirmed') {
                // Confirmed defects become NG everywhere (station, session, shift yield, hourly) and leave "pending"
                apply_counts($conn, $station, 0, $qty, -$qty);
            } elseif ($decision === 'Rejected' && $shift) {
                // Not a defect: just release the pending units
                ws_adjust($conn, (int)$shift['id'], $station, 0, 0, -$qty);
            }
            // Recheck Requested: units stay pending

            log_activity($conn, $station, 'Defect', "Defect report #$report_id " . strtolower($decision));
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to update report.'], 500);
    }

    json_out(['status' => 'success', 'message' => "Report status updated to $decision."]);
}

json_out(['status' => 'error', 'message' => 'Unknown action'], 400);
?>

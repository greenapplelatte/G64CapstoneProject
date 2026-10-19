<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_role('supervisor');
try {
  $active = db_one($conn, "SELECT sr.*, sl.started_at AS session_started_at, sl.closed_at AS session_closed_at FROM shift_records sr LEFT JOIN supervisor_line_sessions sl ON sl.id = sr.session_id WHERE sr.status='OPEN' ORDER BY sr.shift_date DESC, sr.id DESC LIMIT 1");
  if ($active) { $active['line_target'] = (int)($active['line_target'] ?? 0); $active['yield_pct'] = (float)($active['yield_pct'] ?? 0); }
  $lineSession = db_one($conn, "SELECT status FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1");
  $pending = (int)(db_one($conn, "SELECT COUNT(*) AS total FROM defect_reports WHERE status IN ('Pending', 'Recheck Requested')")['total'] ?? 0);
  $canClose = $active !== null && ($lineSession['status'] ?? 'CLOSED') !== 'CLOSED' && $pending === 0;
  $workstations = $active ? db_all($conn, "SELECT station_code, workstation_name, output_units, ng_units, pending_units, yield_pct, is_lowest FROM shift_workstation_yield WHERE shift_record_id = ? ORDER BY id ASC", 'i', [(int)$active['id']]) : [];
  $hourly = $active && !empty($active['session_id'])
    ? db_all($conn, "SELECT hour_label, good_units, ng_units FROM supervisor_hourly_output WHERE session_id = ? ORDER BY id ASC", 'i', [(int)$active['session_id']])
    : [];
  $records = db_all($conn, "SELECT sr.id, sr.session_id, sl.session_code, sl.product_name,
      sl.opened_by, sl.started_at, sl.closed_at, sl.paused_seconds,
      sr.shift_title, sr.shift_date, sr.time_range, sr.produced_units, sr.good_units,
      sr.ng_units, sr.rejected_units, sr.pending_units, sr.line_target,
      sr.closeout_comment, sr.planned_minutes, sr.run_minutes, sr.yield_pct, sr.status
      FROM shift_records sr
      LEFT JOIN supervisor_line_sessions sl ON sl.id = sr.session_id
      ORDER BY sr.shift_date DESC, sr.id DESC");
  // Count only supervisor-confirmed rejected units belonging to the selected open shift.
  // Match reports to the actual session start (not every report on the calendar date).
  $defects = [];
  if ($active) {
    $started = !empty($active['session_started_at'])
      ? (string)$active['session_started_at']
      : (string)$active['shift_date'] . ' ' . (string)$active['time_start'];
    $ended = !empty($active['session_closed_at'])
      ? (string)$active['session_closed_at']
      : date('Y-m-d H:i:s');
    $defects = db_all($conn,
      "SELECT defect_category, 'Confirmed' AS status,
              COALESCE(SUM(quantity_rejected), 0) AS units, COUNT(*) AS cnt
         FROM defect_reports
        WHERE status = 'Confirmed'
          AND created_at >= ? AND created_at <= ?
        GROUP BY defect_category
        ORDER BY units DESC, defect_category ASC",
      'ss', [$started, $ended]);
  }
  echo json_encode(['status'=>'success','summary'=>$active,'workstations'=>$workstations,'records'=>$records,'defects'=>$defects,'hourly'=>$hourly,'closeout'=>['can_close'=>$canClose,'pending_reports'=>$pending,'session_status'=>$lineSession['status'] ?? 'CLOSED']]);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Unable to retrieve shift yield data.']); }

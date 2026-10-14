<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_role('supervisor');
try {
  $active = db_one($conn, "SELECT * FROM shift_records WHERE status='OPEN' ORDER BY shift_date DESC, id DESC LIMIT 1");
  if ($active) { $active['line_target'] = (int)($active['line_target'] ?? 0); $active['yield_pct'] = (float)($active['yield_pct'] ?? 0); }
  $workstations = $active ? db_all($conn, "SELECT station_code, workstation_name, output_units, ng_units, pending_units, yield_pct, is_lowest FROM shift_workstation_yield WHERE shift_record_id = ? ORDER BY id ASC", 'i', [(int)$active['id']]) : [];
  $records = db_all($conn, "SELECT id, shift_title, shift_date, time_range, produced_units, good_units, ng_units, rejected_units, pending_units, yield_pct, status FROM shift_records ORDER BY shift_date DESC, id DESC");
  $defects = $active ? db_all($conn, "SELECT defect_category, status, SUM(quantity_rejected) AS units, COUNT(*) AS cnt FROM defect_reports WHERE DATE(created_at) = ? GROUP BY defect_category, status ORDER BY defect_category", 's', [(string)$active['shift_date']]) : [];
  echo json_encode(['status'=>'success','summary'=>$active,'workstations'=>$workstations,'records'=>$records,'defects'=>$defects,'hourly'=>[]]);
} catch (Throwable $e) { http_response_code(500); echo json_encode(['status'=>'error','message'=>'Unable to retrieve shift yield data.']); }

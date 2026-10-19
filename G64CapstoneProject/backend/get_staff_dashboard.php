<?php
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_role('staff');
$userId = (int) $_SESSION['user_id'];
$me = db_one($conn, "SELECT u.assigned_station, s.line_name FROM users u LEFT JOIN master_stations s ON s.station_code = u.assigned_station WHERE u.id = ? AND u.role = 'staff'", 'i', [$userId]);
$station = trim($me['assigned_station'] ?? '');
if ($station === '') json_out(['status'=>'error','message'=>'No station is assigned to your account. Contact your supervisor.'], 409);
$session = db_one($conn, "SELECT id, created_at FROM production_sessions WHERE machine_id = ? ORDER BY id DESC LIMIT 1", 's', [$station]);
// All widgets refer to the same station and latest production session.
$since = $session['created_at'] ?? date('Y-m-d 00:00:00');
$categories = db_all($conn, "SELECT defect_category AS name, COALESCE(SUM(quantity_rejected),0) AS total FROM defect_reports WHERE station = ? AND created_at >= ? AND status = 'Confirmed' GROUP BY defect_category ORDER BY total DESC", 'ss', [$station, $since]);
$logs = db_all($conn, "SELECT log_time, log_type, details, log_date FROM staff_activity_logs WHERE station_id = ? AND log_date = CURDATE() ORDER BY log_time DESC, id DESC LIMIT 8", 's', [$station]);
$reports = db_all($conn, "SELECT defect_category, workstation, severity, status, report_time, created_at, quantity_rejected FROM defect_reports WHERE station = ? ORDER BY id DESC LIMIT 5", 's', [$station]);
// Chart GOOD comes from the stored +quantity activity events (never from simulated or hard-coded values).
// Chart NG uses the report submission hour, since the current schema does not record a confirmed_at time.
$goodHours = db_all($conn, "SELECT HOUR(TIMESTAMP(log_date, log_time)) AS hour_no, SUM(CAST(SUBSTRING_INDEX(SUBSTRING_INDEX(details, '+', -1), ' ', 1) AS UNSIGNED)) AS units FROM staff_activity_logs WHERE station_id = ? AND log_type = 'Count' AND log_date = CURDATE() AND details LIKE 'Count logged: +% good unit(s)' AND TIMESTAMP(log_date,log_time) >= ? GROUP BY HOUR(TIMESTAMP(log_date,log_time))", 'ss', [$station, $since]);
$ngHours = db_all($conn, "SELECT HOUR(created_at) AS hour_no, SUM(quantity_rejected) AS units FROM defect_reports WHERE station = ? AND status = 'Confirmed' AND DATE(created_at) = CURDATE() AND created_at >= ? GROUP BY HOUR(created_at)", 'ss', [$station, $since]);
$byHour = [];
foreach ($goodHours as $r) { $h = (int)$r['hour_no']; $byHour[$h]['good'] = (int)$r['units']; }
foreach ($ngHours as $r) { $h = (int)$r['hour_no']; $byHour[$h]['ng'] = (int)$r['units']; }
ksort($byHour);
$hourly = [];
foreach ($byHour as $hour => $counts) $hourly[] = ['hour' => date('g A', mktime((int)$hour,0,0)), 'good' => $counts['good'] ?? 0, 'ng' => $counts['ng'] ?? 0];
json_out(['status'=>'success','station'=>$station,'line'=>$me['line_name'] ?? '', 'session_id'=>$session['id'] ?? null,'categories'=>$categories,'activity'=>$logs,'reports'=>$reports,'hourly'=>$hourly,'server_time'=>date('c')]);

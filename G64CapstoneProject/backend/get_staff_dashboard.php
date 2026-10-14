<?php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';
check_role('staff');
$userId = (int)$_SESSION['user_id'];
$me = db_one($conn, "SELECT assigned_station FROM users WHERE id = ? AND role = 'staff'", 'i', [$userId]);
$station = trim($me['assigned_station'] ?? '');
if ($station === '') json_out(['status'=>'error','message'=>'No station is assigned to your account. Contact your supervisor.'], 409);
$categories = db_all($conn, "SELECT defect_category AS name, COALESCE(SUM(quantity_rejected),0) AS total FROM defect_reports WHERE station = ? AND DATE(created_at) = CURDATE() AND status = 'Confirmed' GROUP BY defect_category ORDER BY total DESC", 's', [$station]);
$logs = db_all($conn, "SELECT log_time, log_type, details FROM staff_activity_logs WHERE staff_id = ? AND log_date = CURDATE() ORDER BY log_time DESC, id DESC LIMIT 6", 'i', [$userId]);
$reports = db_all($conn, "SELECT defect_category, workstation, severity, status, report_time, created_at FROM defect_reports WHERE reported_by_id = ? ORDER BY id DESC LIMIT 4", 'i', [$userId]);
json_out(['status'=>'success','station'=>$station,'categories'=>$categories,'activity'=>$logs,'reports'=>$reports]);

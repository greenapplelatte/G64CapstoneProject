<?php
// backend/get_supervisor_dashboard.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');

$line_session = db_one($conn, "SELECT id, session_code, status, product_name, line_target FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1");

// Source of truth: active staff accounts, not the seeded assigned_staff column.
// A station appears only when a real active staff member is assigned to it.
// Master station records supply line/workstation details; status records supply counts.
$stations = db_all($conn, "SELECT
    ms.station_code, ms.line_name, ms.workstation_name,
    ms.status AS master_status,
    COALESCE(st.status, 'PAUSED') AS status,
    COALESCE(st.good_units, 0) AS good_units,
    COALESCE(st.ng_units, 0) AS ng_units,
    COALESCE(st.target_units, 120) AS target_units,
    GROUP_CONCAT(DISTINCT u.full_name ORDER BY u.full_name SEPARATOR ', ') AS assigned_staff,
    COUNT(DISTINCT u.id) AS assigned_staff_count
FROM master_stations ms
INNER JOIN users u
    ON u.assigned_station = ms.station_code
   AND u.role = 'staff'
   AND u.status = 'Active'
   AND (u.assignment = ms.line_name OR u.assignment = 'All Lines')
LEFT JOIN supervisor_station_status st ON st.station_code = ms.station_code
GROUP BY ms.id, ms.station_code, ms.line_name, ms.workstation_name,
         ms.status, st.status, st.good_units, st.ng_units, st.target_units
ORDER BY ms.line_name, ms.station_code");
$total_good = 0;
$total_ng = 0;
$running_count = 0;
$paused_count = 0;

foreach ($stations as $row) {
    $total_good += intval($row['good_units']);
    $total_ng += intval($row['ng_units']);
    if (($row['master_status'] ?? '') !== 'Maintenance' && $row['status'] === 'RUNNING') $running_count++;
    if (($row['master_status'] ?? '') === 'Maintenance' || $row['status'] === 'PAUSED') $paused_count++;
}

// Compute Yield
$total_units = $total_good + $total_ng;
$overall_yield = $total_units > 0 ? number_format(($total_good / $total_units) * 100, 1) : "—";

// Fetch Hourly Output Bar Data
$hourly = $line_session
    ? db_all($conn, "SELECT hour_label, good_units, ng_units FROM supervisor_hourly_output WHERE session_id = ? ORDER BY id ASC", "i", [(int)$line_session['id']])
    : [];

// Fetch Floor Alerts Feed
$alerts = db_all($conn, "SELECT * FROM supervisor_floor_alerts ORDER BY id DESC LIMIT 5");

// Fetch Pending Review Defects
$pending_reviews = db_all($conn, "SELECT * FROM defect_reports WHERE status IN ('Pending', 'Recheck Requested') ORDER BY id DESC LIMIT 5");
$pending_count = (int)(db_one($conn, "SELECT COUNT(*) AS total FROM defect_reports WHERE status IN ('Pending', 'Recheck Requested')")['total'] ?? 0);
$stations_online = count(array_filter($stations, fn($r) => ($r['master_status'] ?? '') !== 'Maintenance' && in_array($r['status'], ['RUNNING','PAUSED'], true)));

echo json_encode([
    'status' => 'success',
    'summary' => [
        'output' => $total_good,
        'target' => (int)($line_session['line_target'] ?? 0),
        'pct_done' => (int)(($line_session['line_target'] ?? 0) > 0 ? round(($total_good / (int)$line_session['line_target']) * 100) : 0),
        'session_code' => $line_session['session_code'] ?? null,
        'session_status' => $line_session['status'] ?? null,
        'product_name' => $line_session['product_name'] ?? null,
        'yield' => $overall_yield . '%',
        'good_units' => $total_good,
        'ng_units' => $total_ng,
        'reports_to_review' => $pending_count,
        'stations_online' => $stations_online,
        'total_stations' => count($stations),
        'running_count' => $running_count,
        'paused_count' => $paused_count
    ],
    'stations' => $stations,
    'hourly' => $hourly,
    'alerts' => $alerts,
    'pending_reviews' => $pending_reviews
]);
exit;
?>
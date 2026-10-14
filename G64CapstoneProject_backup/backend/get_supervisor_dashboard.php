<?php
// backend/get_supervisor_dashboard.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');

$line_session = db_one($conn, "SELECT session_code, status FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1");

// Fetch Live Stations Status
$stations = db_all($conn, "SELECT * FROM supervisor_station_status ORDER BY id ASC");
$total_good = 0;
$total_ng = 0;
$running_count = 0;
$paused_count = 0;

foreach ($stations as $row) {
    $total_good += intval($row['good_units']);
    $total_ng += intval($row['ng_units']);
    if ($row['status'] === 'RUNNING') $running_count++;
    if ($row['status'] === 'PAUSED') $paused_count++;
}

// Compute Yield
$total_units = $total_good + $total_ng;
$overall_yield = $total_units > 0 ? number_format(($total_good / $total_units) * 100, 1) : "—";

// Fetch Hourly Output Bar Data
$hourly = db_all($conn, "SELECT * FROM supervisor_hourly_output ORDER BY id ASC");

// Fetch Floor Alerts Feed
$alerts = db_all($conn, "SELECT * FROM supervisor_floor_alerts ORDER BY id DESC LIMIT 5");

// Fetch Pending Review Defects
$pending_reviews = db_all($conn, "SELECT * FROM defect_reports WHERE status = 'Pending' ORDER BY id DESC LIMIT 5");

echo json_encode([
    'status' => 'success',
    'summary' => [
        'output' => $total_good,
        'target' => (int)(db_one($conn, "SELECT line_target FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1")['line_target'] ?? 0),
        'pct_done' => (int)(function () use ($conn, $total_good) { $t = (int)(db_one($conn, "SELECT line_target FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1")['line_target'] ?? 0); return $t > 0 ? round(($total_good / $t) * 100) : 0; })(),
        'session_code' => $line_session['session_code'] ?? null,
        'session_status' => $line_session['status'] ?? null,
        'yield' => $overall_yield . '%',
        'good_units' => $total_good,
        'ng_units' => $total_ng,
        'reports_to_review' => count($pending_reviews),
        'stations_online' => count(array_filter($stations, fn($r) => in_array($r['status'], ['RUNNING','PAUSED'], true))),
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
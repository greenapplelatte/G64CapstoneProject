<?php
// check_setup.php - read-only setup checker. Open http://localhost/G64CapstoneProject/check_setup.php
// DELETE THIS FILE once everything works. It only answers requests from this computer.
if (!in_array($_SERVER['REMOTE_ADDR'] ?? '', ['127.0.0.1', '::1'], true)) { http_response_code(403); exit('Local access only.'); }
mysqli_report(MYSQLI_REPORT_OFF);
header('Content-Type: text/html; charset=utf-8');
function row($ok, $label, $detail = '') {
    echo '<tr><td style="color:' . ($ok ? '#15803d' : '#b91c1c') . ';font-weight:bold">' . ($ok ? 'OK' : 'PROBLEM') . '</td><td>'
       . htmlspecialchars($label) . '</td><td>' . htmlspecialchars($detail) . '</td></tr>';
}
echo '<!DOCTYPE html><meta charset="utf-8"><title>Setup check</title>
<style>body{font:14px Arial;margin:24px}table{border-collapse:collapse}td,th{border:1px solid #ccc;padding:6px 10px;text-align:left}h2{margin-top:28px}</style>
<h1>Setup check</h1>';

require_once __DIR__ . '/config/app.php';
$folder = '/' . basename(__DIR__);
echo '<h2>1. Folder / URL</h2><table>';
row(BASE_URL === $folder, 'BASE_URL matches the folder name', 'BASE_URL=' . BASE_URL . '  actual folder=' . $folder);
row(is_file(__DIR__ . '/login.php'), 'login.php is directly inside this folder (not in a nested copy)');
row(is_dir(__DIR__ . '/uploads/defects') && is_writable(__DIR__ . '/uploads/defects'), 'uploads/defects exists and is writable');
echo '</table>';

echo '<h2>2. Database</h2><table>';
$conn = @new mysqli('localhost', 'root', '', 'g64capstoneproject');
if ($conn->connect_errno) { row(false, 'Connect to g64capstoneproject', $conn->connect_error); echo '</table>'; exit; }
row(true, 'Connected to g64capstoneproject', 'server ' . $conn->server_info);
$need = ['users','login_attempts','user_access_requests','user_access_changes','system_settings','system_settings_history',
 'master_target_defaults','master_stations','master_lines','master_workstations','master_shift_schedule','master_project_types',
 'oee_plant_metrics','oee_line_performance','oee_weekly_trend','analytics_daily_yield','analytics_report_history','analytics_root_cause',
 'production_sessions','shift_records','shift_workstation_yield','supervisor_line_sessions','supervisor_station_status',
 'supervisor_hourly_output','supervisor_floor_alerts','staff_activity_logs','defect_reports','defect_logs','defect_categories'];
$have = [];
$r = $conn->query('SHOW TABLES');
while ($r && ($t = $r->fetch_row())) { $have[strtolower($t[0])] = true; }
$missing = array_values(array_filter($need, fn($t) => empty($have[$t])));
row(!$missing, 'All required tables exist', $missing ? 'MISSING: ' . implode(', ', $missing) . '  -> re-import config/database.sql' : count($need) . ' tables found');
echo '</table>';

echo '<h2>3. Accounts</h2><table><tr><th></th><th>username / role</th><th>detail</th></tr>';
$r = $conn->query('SELECT username, role, status, must_change_password, password FROM users ORDER BY id');
if (!$r) { row(false, 'Read users table', $conn->error); }
else while ($u = $r->fetch_assoc()) {
    $okStatus = strcasecmp((string)$u['status'], 'Active') === 0;
    $okRole = in_array($u['role'], ['manager','supervisor','staff'], true);
    $okHash = strlen($u['password']) === 60 && preg_match('/^\$2[abxy]\$/', $u['password']);
    $isDemo = password_verify('password123', $u['password']);
    $detail = 'status=' . $u['status'] . ', hash ' . ($okHash ? 'valid format' : 'BAD FORMAT (len ' . strlen($u['password']) . ')')
            . ', password123 ' . ($isDemo ? 'WORKS' : 'does not match') . ($u['must_change_password'] ? ', must change password on first login' : '');
    row($okStatus && $okRole && $okHash, $u['username'] . ' / ' . $u['role'], $detail);
}
echo '</table>';

echo '<h2>4. Lockout</h2><table>';
$r = $conn->query("SELECT username, COUNT(*) n FROM login_attempts WHERE attempted_at > NOW() - INTERVAL 5 MINUTE GROUP BY username");
if (!$r) row(false, 'login_attempts table', $conn->error);
else { $locked = []; while ($x = $r->fetch_assoc()) { if ($x['n'] >= 5) $locked[] = $x['username']; }
       row(!$locked, 'No username is currently locked out', $locked ? 'LOCKED: ' . implode(', ', $locked) . ' (wait 5 min or run repair_access.sql)' : ''); }
echo '</table><p style="margin-top:24px;color:#b91c1c"><b>Delete check_setup.php when done.</b></p>';

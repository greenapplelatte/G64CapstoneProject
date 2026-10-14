<?php
// backend/get_activity_data.php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('staff');

$staff_id = (int)($_SESSION['user_id'] ?? 0);

/**
 * WHERE clause for this operator's log, from ?range=today|week|month|all, ?from=, ?to= (YYYY-MM-DD) and ?type=.
 * A from/to date, when given, replaces the range.
 */
function activity_filter(int $staff_id): array {
    $where = 'staff_id = ?'; $types = 'i'; $params = [$staff_id];
    $valid = fn($d) => is_string($d) && preg_match('/^\d{4}-\d{2}-\d{2}$/', $d) && strtotime($d) !== false;
    $from = $_GET['from'] ?? ''; $to = $_GET['to'] ?? '';
    if ($valid($from) || $valid($to)) {
        if ($valid($from)) { $where .= ' AND log_date >= ?'; $types .= 's'; $params[] = $from; }
        if ($valid($to))   { $where .= ' AND log_date <= ?'; $types .= 's'; $params[] = $to; }
    } else {
        $range = $_GET['range'] ?? 'today';
        $start = null;
        if ($range === 'today')     $start = date('Y-m-d');
        elseif ($range === 'week')  $start = date('Y-m-d', strtotime('monday this week'));
        elseif ($range === 'month') $start = date('Y-m-01');
        if ($start) { $where .= ' AND log_date >= ?'; $types .= 's'; $params[] = $start; }
    }
    $type = $_GET['type'] ?? 'all';
    if (in_array($type, ['Count', 'Defect', 'Shift', 'System'], true)) {
        $where .= ' AND log_type = ?'; $types .= 's'; $params[] = $type;
    }
    return [$where, $types, $params];
}

/* ---------- CSV export of this operator's activity log ---------- */
if (($_GET['action'] ?? '') === 'export_csv') {
    [$where, $ftypes, $fparams] = activity_filter($staff_id);
    $stmt = $conn->prepare(
        "SELECT log_date, log_time, log_type, details, station_id FROM staff_activity_logs WHERE $where ORDER BY log_date DESC, log_time DESC, id DESC");
    $stmt->bind_param($ftypes, ...$fparams);
    $stmt->execute();
    $res = $stmt->get_result();

    // Stop spreadsheet apps from running a cell that starts with = + - @ as a formula
    $safe = function ($v) {
        $v = (string)$v;
        return ($v !== '' && strpos("=+-@\t\r", $v[0]) !== false) ? "'" . $v : $v;
    };

    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="activity_log_' . date('Y-m-d') . '.csv"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // UTF-8 BOM so Excel opens it correctly
    fputcsv($out, ['Date', 'Time', 'Type', 'Details', 'Station']);
    while ($row = $res->fetch_assoc()) {
        fputcsv($out, [
            $row['log_date'] ? date('Y-m-d', strtotime($row['log_date'])) : '',
            date('h:i A', strtotime($row['log_time'])),
            $safe($row['log_type']),
            $safe($row['details']),
            $safe($row['station_id']),
        ]);
    }
    fclose($out);
    $stmt->close();
    exit;
}

header('Content-Type: application/json');

/* ---------- filtered, paged log entries (used by the filter bar and the Previous/Next buttons) ---------- */
function activity_page(mysqli $conn, int $staff_id): array {
    [$where, $types, $params] = activity_filter($staff_id);
    $perPage = 10;
    $total = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM staff_activity_logs WHERE $where", $types, $params)['n'] ?? 0);
    $pages = max(1, (int)ceil($total / $perPage));
    $page  = min($pages, max(1, (int)($_GET['page'] ?? 1)));
    $offset = ($page - 1) * $perPage;
    $rows = db_all($conn,
        "SELECT * FROM staff_activity_logs WHERE $where ORDER BY log_date DESC, log_time DESC, id DESC LIMIT $perPage OFFSET $offset",
        $types, $params);
    foreach ($rows as &$r) {
        $r['formatted_time'] = date('h:i A', strtotime($r['log_time']));
        $r['formatted_date'] = !empty($r['log_date']) ? date('M d', strtotime($r['log_date'])) : '';
    }
    unset($r);
    return ['logs' => $rows, 'total' => $total, 'page' => $page, 'pages' => $pages, 'per_page' => $perPage];
}
if (($_GET['action'] ?? '') === 'logs') {
    echo json_encode(['status' => 'success'] + activity_page($conn, $staff_id));
    exit;
}

// Top summary metrics calculation (this operator's shifts, plus shifts not tied to anyone)
$stats = db_one($conn,
    "SELECT COUNT(*) AS total_shifts, COALESCE(SUM(good_units),0) AS total_good, COALESCE(SUM(ng_units),0) AS total_ng, AVG(yield_rate) AS avg_yield
     FROM shift_records WHERE staff_id = ? AND shift_date >= ?",
    "is", [$staff_id, date('Y-m-d', strtotime('monday this week'))]);
$reportStats = db_one($conn, "SELECT COUNT(*) AS report_count FROM defect_reports WHERE reported_by_id = ? AND created_at >= ?", "is", [$staff_id, date('Y-m-d', strtotime('monday this week'))]);

// Shift History List
$shifts = db_all($conn,
    "SELECT * FROM shift_records WHERE staff_id = ? ORDER BY shift_date DESC LIMIT 5",
    "i", [$staff_id]);
foreach ($shifts as &$row) {
    $row['formatted_date'] = date('M d, Y', strtotime($row['shift_date']));
    $row['formatted_time'] = date('g:i A', strtotime($row['time_start'])) . ' – ' . date('g:i A', strtotime($row['time_end']));
}
unset($row);

// Activity Log Entries (first page with the default filter)
$logPage = activity_page($conn, $staff_id);

echo json_encode([
    'status' => 'success',
    'stats' => [
        'shifts_this_week' => $stats['total_shifts'] ?? 0,
        'good_units' => $stats['total_good'] ?? 0,
        'defects_reported' => (int)($reportStats['report_count'] ?? 0),
        'average_yield' => number_format($stats['avg_yield'] ?? 0, 1) . '%'
    ],
    'shifts' => $shifts,
    'logs' => $logPage['logs'],
    'log_total' => $logPage['total'], 'log_page' => $logPage['page'], 'log_pages' => $logPage['pages']
]);
exit;
?>
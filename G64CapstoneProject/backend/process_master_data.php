<?php
// backend/process_master_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role(['manager', 'supervisor']);

$action = $_GET['action'] ?? '';
function shift_time_ranges_overlap(string $startA, string $endA, string $startB, string $endB): bool {
    $toMinutes = static function (string $time): int {
        return ((int)substr($time, 0, 2) * 60) + (int)substr($time, 3, 2);
    };
    $segments = static function (string $start, string $end) use ($toMinutes): array {
        $from = $toMinutes($start);
        $to = $toMinutes($end);
        return $from < $to ? [[$from, $to]] : [[$from, 1440], [0, $to]];
    };
    foreach ($segments($startA, $endA) as [$fromA, $toA]) {
        foreach ($segments($startB, $endB) as [$fromB, $toB]) {
            if (max($fromA, $fromB) < min($toA, $toB)) return true;
        }
    }
    return false;
}

function shift_schedule_conflict(mysqli $conn, string $start, string $end, ?int $excludeId = null): ?string {
    $sql = "SELECT id, shift_name, start_time, end_time FROM master_shift_schedule
            WHERE status = 'Scheduled' AND start_time IS NOT NULL AND end_time IS NOT NULL";
    $shifts = db_all($conn, $sql);
    foreach ($shifts as $shift) {
        if ($excludeId !== null && (int)$shift['id'] === $excludeId) continue;
        if (shift_time_ranges_overlap($start, $end, (string)$shift['start_time'], (string)$shift['end_time'])) {
            return (string)$shift['shift_name'];
        }
    }
    return null;
}

function valid_station_code(string $code): bool {
    return preg_match('/^ESP32-STN\d{2,}$/', $code) === 1;
}

if ($action !== 'get_master_data' && ($_SESSION['role'] ?? '') !== 'manager') {
    http_response_code(403);
    echo json_encode(['status' => 'error', 'message' => 'Only managers can change master data.']);
    exit;
}

if ($action === 'get_master_data') {
    try {
        $stations = db_all($conn, "SELECT * FROM master_stations ORDER BY id ASC");
        $staff = db_all($conn, "SELECT id, full_name, assignment, assigned_station FROM users WHERE role = 'staff' AND status = 'Active' ORDER BY full_name");
        foreach ($stations as &$station) {
            $station['assigned_staff_id'] = null;
            $station['assigned_staff_name'] = null;
            foreach ($staff as $member) {
                if ($member['assigned_station'] === $station['station_code']) {
                    $station['assigned_staff_id'] = (int)$member['id'];
                    $station['assigned_staff_name'] = $member['full_name'];
                    break;
                }
            }
        }
        unset($station);
        $defaults = db_one($conn, "SELECT * FROM master_target_defaults WHERE id = 1");
        $defects  = db_all($conn, "SELECT * FROM defect_categories ORDER BY id ASC");
        $projectTypes = db_all($conn, "SELECT * FROM master_project_types ORDER BY project_type");
        $workstations = db_all($conn, "SELECT * FROM master_workstations ORDER BY workstation_name");
        $shifts   = db_all($conn, "SELECT * FROM master_shift_schedule ORDER BY start_time, id");
        $lines    = db_all($conn, "SELECT l.id, l.line_name, COUNT(s.id) AS station_count
                                   FROM master_lines l LEFT JOIN master_stations s ON s.line_name = l.line_name
                                   GROUP BY l.id, l.line_name ORDER BY l.id");
    } catch (mysqli_sql_exception $e) {
        error_log('Master data schema/query error: ' . $e->getMessage());
        http_response_code(503);
        echo json_encode(['status'=>'error','message'=>'Master Data database updates are required. Import config/database.sql into g64capstoneproject in phpMyAdmin, then reload this page.']);
        exit;
    }

    echo json_encode([
        'status' => 'success',
        'stations' => $stations,
        'staff' => $staff,
        'defaults' => $defaults,
        'defects' => $defects,
        'project_types' => $projectTypes,
        'workstations' => $workstations,
        'shifts' => $shifts,
        'lines' => $lines
    ]);
    exit;
}

if ($action === 'add_line') {
    require_post();
    $name = trim($_POST['line_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 30) {
        echo json_encode(['status'=>'error','message'=>'Line name is required (maximum 30 characters).']); exit;
    }
    try {
        $stmt = $conn->prepare('INSERT INTO master_lines (line_name) VALUES (?)');
        $stmt->bind_param('s', $name);
        $stmt->execute();
        echo json_encode(['status'=>'success','message'=>'Line added. It is now available for station and staff assignments.']);
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['status'=>'error','message'=>(int)$e->getCode() === 1062 ? 'That line name already exists.' : 'Could not add line.']);
    }
    exit;
}

if ($action === 'update_line') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['line_name'] ?? '');
    if (!$id || $name === '' || mb_strlen($name) > 30) {
        echo json_encode(['status'=>'error','message'=>'Valid line ID and name (maximum 30 characters) are required.']); exit;
    }
    $existing = db_one($conn, 'SELECT line_name FROM master_lines WHERE id = ?', 'i', [$id]);
    if (!$existing) { echo json_encode(['status'=>'error','message'=>'Line not found.']); exit; }
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('UPDATE master_lines SET line_name = ? WHERE id = ?');
        $stmt->bind_param('si', $name, $id);
        $stmt->execute();
        $oldName = $existing['line_name'];
        $stmt = $conn->prepare('UPDATE master_stations SET line_name = ? WHERE line_name = ?');
        $stmt->bind_param('ss', $name, $oldName);
        $stmt->execute();
        $stmt = $conn->prepare('UPDATE users SET assignment = ? WHERE assignment = ?');
        $stmt->bind_param('ss', $name, $oldName);
        $stmt->execute();
        $oldDepartment = 'Production - ' . $oldName;
        $newDepartment = 'Production - ' . $name;
        $stmt = $conn->prepare('UPDATE users SET department = ? WHERE department = ?');
        $stmt->bind_param('ss', $newDepartment, $oldDepartment);
        $stmt->execute();
        $stmt = $conn->prepare('UPDATE user_access_requests SET assignment_line = ? WHERE assignment_line = ?');
        $stmt->bind_param('ss', $name, $oldName);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Line renamed. Station and user assignments were updated.']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>(int)$e->getCode() === 1062 ? 'That line name already exists.' : 'Could not update line.']);
    }
    exit;
}

if ($action === 'delete_line') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid line ID.']); exit; }
    $line = db_one($conn, 'SELECT line_name FROM master_lines WHERE id = ?', 'i', [$id]);
    if (!$line) { echo json_encode(['status'=>'error','message'=>'Line not found.']); exit; }
    $assigned = db_one($conn, "SELECT
        (SELECT COUNT(*) FROM master_stations WHERE line_name = ?) +
        (SELECT COUNT(*) FROM users WHERE assignment = ?) +
        (SELECT COUNT(*) FROM user_access_requests WHERE assignment_line = ?) AS total", 'sss',
        [$line['line_name'], $line['line_name'], $line['line_name']]);
    if ((int)$assigned['total'] > 0) {
        echo json_encode(['status'=>'error','message'=>'This line still has stations, user assignments, or access requests. Reassign them before deleting it.']); exit;
    }
    $stmt = $conn->prepare('DELETE FROM master_lines WHERE id = ?');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    echo json_encode(['status'=>'success','message'=>'Line deleted.']);
    exit;
}

if ($action === 'update_station') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $code = strtoupper(trim($_POST['station_code'] ?? ''));
    $line = trim($_POST['line_name'] ?? '');
    $workstation = trim($_POST['workstation_name'] ?? '');
    $cycle = filter_input(INPUT_POST, 'ideal_cycle_sec', FILTER_VALIDATE_INT);
    $rate = filter_input(INPUT_POST, 'rate_per_hr', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? '');
    if (!$id || !valid_station_code($code) || mb_strlen($code) > 30 || $line === '' || $workstation === '' || mb_strlen($workstation) > 80
        || !$cycle || $cycle > 100000 || !$rate || $rate > 1000000 || !in_array($status, ['Online','Maintenance'], true)) {
        echo json_encode(['status'=>'error','message'=>'Use a station code in ESP32-STN01 format and provide valid station details.']); exit;
    }
    if (!db_one($conn, 'SELECT id FROM master_lines WHERE line_name = ?', 's', [$line])) {
        echo json_encode(['status'=>'error','message'=>'Choose a line that exists in Master Data.']); exit;
    }
    $existing = db_one($conn, 'SELECT station_code FROM master_stations WHERE id = ?', 'i', [$id]);
    if (!$existing) { echo json_encode(['status'=>'error','message'=>'Station not found.']); exit; }
    try {
        $conn->begin_transaction();
        $oldCode = $existing['station_code'];
        $stmt = $conn->prepare('UPDATE master_stations SET station_code=?, line_name=?, workstation_name=?, ideal_cycle_sec=?, rate_per_hr=?, status=? WHERE id=?');
        $stmt->bind_param('sssiisi', $code, $line, $workstation, $cycle, $rate, $status, $id);
        $stmt->execute();
        $department = 'Production - ' . $line;
        $stmt = $conn->prepare('UPDATE users SET assignment = ?, department = ? WHERE assigned_station = ?');
        $stmt->bind_param('sss', $line, $department, $oldCode);
        $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO master_workstations (workstation_name) VALUES (?) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->bind_param('s', $workstation);
        $stmt->execute();
        $stmt = $conn->prepare('UPDATE users SET assigned_station = ? WHERE assigned_station = ?');
        $stmt->bind_param('ss', $code, $oldCode);
        $stmt->execute();
        $stmt = $conn->prepare("UPDATE supervisor_station_status SET station_code = ?, workstation_name = ?, status = IF(? = 'Maintenance', 'PAUSED', status) WHERE station_code = ?");
        $stmt->bind_param('ssss', $code, $workstation, $status, $oldCode);
        $stmt->execute();
        if ($status === 'Maintenance') {
            $stmt = $conn->prepare("UPDATE production_sessions SET status = 'paused' WHERE machine_id = ? AND status = 'active'");
            $stmt->bind_param('s', $oldCode);
            $stmt->execute();
        }
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Station updated successfully.']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>((int)$e->getCode()===1062 ? 'That station code is already in use.' : 'Could not update station.')]);
    }
    exit;
}

if ($action === 'assign_station_staff') {
    require_post();
    $stationId = filter_input(INPUT_POST, 'station_id', FILTER_VALIDATE_INT);
    $staffId = trim($_POST['staff_id'] ?? '');
    if (!$stationId || ($staffId !== '' && !ctype_digit($staffId))) {
        echo json_encode(['status'=>'error','message'=>'Choose a valid station and operator.']); exit;
    }
    $station = db_one($conn, 'SELECT station_code, line_name FROM master_stations WHERE id = ?', 'i', [$stationId]);
    if (!$station) { echo json_encode(['status'=>'error','message'=>'Station not found.']); exit; }
    $member = $staffId === '' ? null : db_one($conn, "SELECT id, full_name FROM users WHERE id = ? AND role = 'staff' AND status = 'Active'", 'i', [(int)$staffId]);
    if ($staffId !== '' && !$member) { echo json_encode(['status'=>'error','message'=>'Choose an active operator account.']); exit; }
    try {
        $conn->begin_transaction();
        $stationCode = (string)$station['station_code'];
        // Remember where the operator was before, so their pending defect reports can follow them
        $prevStation = null;
        $moved = 0;
        if ($member) {
            $prevRow = db_one($conn, 'SELECT assigned_station FROM users WHERE id = ?', 'i', [(int)$member['id']]);
            $prevStation = $prevRow['assigned_station'] ?? null;
        }
        $clear = $conn->prepare('UPDATE users SET assigned_station = NULL WHERE assigned_station = ?');
        $clear->bind_param('s', $stationCode);
        $clear->execute();
        if ($member) {
            $lineName = (string)$station['line_name'];
            $department = 'Production - ' . $lineName;
            $memberId = (int)$member['id'];
            $assign = $conn->prepare('UPDATE users SET assignment = ?, assigned_station = ?, department = ? WHERE id = ?');
            $assign->bind_param('sssi', $lineName, $stationCode, $department, $memberId);
            $assign->execute();
            if ($prevStation !== $stationCode) {
                $moved = move_pending_defect_reports($conn, $memberId, $stationCode);
            }
        }
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>$member
            ? $member['full_name'] . ' assigned to ' . $stationCode . ' on ' . $station['line_name'] . '.'
                . ($moved > 0 ? " $moved pending defect report(s) moved to $stationCode." : '')
            : 'Operator unassigned from ' . $stationCode . '.']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        error_log('Station operator assignment failed: ' . $e->getMessage());
        echo json_encode(['status'=>'error','message'=>'Could not update the operator assignment.']);
    }
    exit;
}

if ($action === 'add_station') {
    require_post();
    $code = strtoupper(trim($_POST['station_code'] ?? ''));
    $line = trim($_POST['line_name'] ?? '');
    $workstation = trim($_POST['workstation_name'] ?? '');
    $cycle = filter_var($_POST['ideal_cycle_sec'] ?? null, FILTER_VALIDATE_INT);
    $rate = filter_var($_POST['rate_per_hr'] ?? null, FILTER_VALIDATE_INT);
    if (!valid_station_code($code) || mb_strlen($code) > 30 || $line === '' || $workstation === '' || mb_strlen($workstation) > 80
        || $cycle === false || $cycle === null || $cycle < 1 || $cycle > 100000
        || $rate === false || $rate === null || $rate < 1 || $rate > 1000000) {
        echo json_encode(['status'=>'error','message'=>'Use a station code in ESP32-STN01 format, choose a line and workstation, and enter valid positive cycle/rate values.']); exit;
    }
    if (!db_one($conn, 'SELECT id FROM master_lines WHERE line_name = ?', 's', [$line])) {
        echo json_encode(['status'=>'error','message'=>'Choose a line that exists in Master Data.']); exit;
    }
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare("INSERT INTO master_stations (station_code, line_name, workstation_name, ideal_cycle_sec, rate_per_hr, status, last_calibrated) VALUES (?, ?, ?, ?, ?, 'Online', NULL)");
        $stmt->bind_param('sssii', $code, $line, $workstation, $cycle, $rate);
        $stmt->execute();
        $stmt = $conn->prepare('INSERT INTO master_workstations (workstation_name) VALUES (?) ON DUPLICATE KEY UPDATE is_active = 1');
        $stmt->bind_param('s', $workstation);
        $stmt->execute();
        $stmt = $conn->prepare("INSERT INTO supervisor_station_status (station_code, workstation_name, assigned_staff, status, good_units, ng_units, target_units) VALUES (?, ?, NULL, 'RUNNING', 0, 0, ?)");
        $stmt->bind_param('ssi', $code, $workstation, $rate);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Station added and made available on supervisor and staff pages.']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>(int)$e->getCode() === 1062 ? 'That station code already exists.' : 'Could not add station.']);
    }
    exit;
}

if ($action === 'delete_station') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid station ID.']); exit; }
    $station = db_one($conn, 'SELECT station_code FROM master_stations WHERE id = ?', 'i', [$id]);
    if (!$station) { echo json_encode(['status'=>'error','message'=>'Station not found.']); exit; }
    if (db_one($conn, 'SELECT id FROM users WHERE assigned_station = ? LIMIT 1', 's', [$station['station_code']])) {
        echo json_encode(['status'=>'error','message'=>'This station is assigned to a user. Reassign the user before deleting it.']); exit;
    }
    if (db_one($conn, "SELECT id FROM production_sessions WHERE machine_id = ? AND status IN ('active', 'paused') LIMIT 1", 's', [$station['station_code']])) {
        echo json_encode(['status'=>'error','message'=>'This station has an active production session and cannot be deleted yet.']); exit;
    }
    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('DELETE FROM supervisor_station_status WHERE station_code = ?');
        $stmt->bind_param('s', $station['station_code']);
        $stmt->execute();
        $stmt = $conn->prepare('DELETE FROM master_stations WHERE id = ?');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $conn->commit();
        echo json_encode(['status'=>'success','message'=>'Station removed from staff and supervisor pages. Existing production and defect history was preserved.']);
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Could not delete station.']);
    }
    exit;
}

if (in_array($action, ['add_project_type', 'update_project_type', 'delete_project_type', 'restore_project_type'], true)) {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['project_type'] ?? '');
    if (in_array($action, ['add_project_type', 'update_project_type'], true)
        && ($name === '' || mb_strlen($name) > 100)) {
        echo json_encode(['status'=>'error','message'=>'Project type is required (maximum 100 characters).']); exit;
    }
    if ($action !== 'add_project_type' && !$id) {
        echo json_encode(['status'=>'error','message'=>'A valid project type ID is required.']); exit;
    }
    try {
        if ($action === 'add_project_type') {
            $stmt = $conn->prepare('INSERT INTO master_project_types (project_type) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>'Project type added and available in defect reports.']);
        } elseif ($action === 'update_project_type') {
            $stmt = $conn->prepare('UPDATE master_project_types SET project_type = ? WHERE id = ?');
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>'Project type updated. Existing reports are unchanged.']);
        } else {
            $active = $action === 'restore_project_type' ? 1 : 0;
            $stmt = $conn->prepare('UPDATE master_project_types SET is_active = ? WHERE id = ?');
            $stmt->bind_param('ii', $active, $id);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>$active ? 'Project type restored.' : 'Project type removed from new reports. Existing reports are unchanged.']);
        }
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['status'=>'error','message'=>(int)$e->getCode() === 1062 ? 'That project type already exists.' : 'Could not save project type.']);
    }
    exit;
}

if (in_array($action, ['add_workstation', 'update_workstation', 'delete_workstation', 'restore_workstation'], true)) {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['workstation_name'] ?? '');
    if (in_array($action, ['add_workstation', 'update_workstation'], true)
        && ($name === '' || mb_strlen($name) > 80)) {
        echo json_encode(['status'=>'error','message'=>'Workstation name is required (maximum 80 characters).']); exit;
    }
    if ($action !== 'add_workstation' && !$id) {
        echo json_encode(['status'=>'error','message'=>'A valid workstation ID is required.']); exit;
    }
    try {
        if ($action === 'add_workstation') {
            $stmt = $conn->prepare('INSERT INTO master_workstations (workstation_name) VALUES (?)');
            $stmt->bind_param('s', $name);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>'Workstation added and available in defect reports.']);
        } elseif ($action === 'update_workstation') {
            $existing = db_one($conn, 'SELECT workstation_name FROM master_workstations WHERE id = ?', 'i', [$id]);
            if (!$existing) { echo json_encode(['status'=>'error','message'=>'Workstation not found.']); exit; }
            $conn->begin_transaction();
            $oldName = $existing['workstation_name'];
            $stmt = $conn->prepare('UPDATE master_workstations SET workstation_name = ? WHERE id = ?');
            $stmt->bind_param('si', $name, $id);
            $stmt->execute();
            $stmt = $conn->prepare('UPDATE master_stations SET workstation_name = ? WHERE workstation_name = ?');
            $stmt->bind_param('ss', $name, $oldName);
            $stmt->execute();
            $stmt = $conn->prepare('UPDATE supervisor_station_status SET workstation_name = ? WHERE workstation_name = ?');
            $stmt->bind_param('ss', $name, $oldName);
            $stmt->execute();
            $conn->commit();
            echo json_encode(['status'=>'success','message'=>'Workstation updated. Existing defect reports are unchanged.']);
        } else {
            $active = $action === 'restore_workstation' ? 1 : 0;
            $stmt = $conn->prepare('UPDATE master_workstations SET is_active = ? WHERE id = ?');
            $stmt->bind_param('ii', $active, $id);
            $stmt->execute();
            echo json_encode(['status'=>'success','message'=>$active ? 'Workstation restored.' : 'Workstation removed from new reports. Existing reports are unchanged.']);
        }
    } catch (mysqli_sql_exception $e) {
        if ($action === 'update_workstation') $conn->rollback();
        echo json_encode(['status'=>'error','message'=>(int)$e->getCode() === 1062 ? 'That workstation already exists.' : 'Could not save workstation.']);
    }
    exit;
}

if ($action === 'add_defect_type') {
    require_post();
    $name = trim($_POST['category_name'] ?? '');
    if ($name === '' || mb_strlen($name) > 100) { echo json_encode(['status'=>'error','message'=>'Defect name is required (maximum 100 characters).']); exit; }
    $stmt = $conn->prepare('INSERT INTO defect_categories (category_name, count) VALUES (?, 0)');
    $stmt->bind_param('s', $name);
    try { $stmt->execute(); echo json_encode(['status'=>'success','message'=>'Defect type added.']); }
    catch (mysqli_sql_exception $e) { echo json_encode(['status'=>'error','message'=>'Could not add defect type.']); }
    exit;
}
if ($action === 'update_defect_type') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $name = trim($_POST['category_name'] ?? '');
    if (!$id || $name === '' || mb_strlen($name) > 100) { echo json_encode(['status'=>'error','message'=>'Valid defect ID and name are required.']); exit; }
    $stmt = $conn->prepare('UPDATE defect_categories SET category_name=? WHERE id=?');
    $stmt->bind_param('si', $name, $id); $stmt->execute();
    echo json_encode(['status'=>$stmt->affected_rows >= 0 ? 'success' : 'error','message'=>'Defect type updated.']);
    exit;
}
if ($action === 'delete_defect_type') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid defect ID.']); exit; }
    $stmt = $conn->prepare('DELETE FROM defect_categories WHERE id=?');
    $stmt->bind_param('i', $id); $stmt->execute();
    echo json_encode(['status'=>$stmt->affected_rows ? 'success' : 'error','message'=>$stmt->affected_rows ? 'Defect type deleted.' : 'Defect type not found.']);
    exit;
}

if ($action === 'add_shift' || $action === 'update_shift') {
    require_post();
    $id = $action === 'update_shift' ? filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT) : null;
    $name = trim($_POST['shift_name'] ?? '');
    $start = trim($_POST['start_time'] ?? '');
    $end = trim($_POST['end_time'] ?? '');
    $target = filter_var($_POST['target_units'] ?? null, FILTER_VALIDATE_INT);
    if (($action === 'update_shift' && !$id) || $name === '' || mb_strlen($name) > 40
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $start)
        || !preg_match('/^(?:[01]\d|2[0-3]):[0-5]\d$/', $end)
        || $start === $end || $target === false || $target === null || $target < 0 || $target > 1000000) {
        echo json_encode(['status'=>'error','message'=>'Enter a shift name, valid 24-hour start/end times, and a target from 0 to 1,000,000.']); exit;
    }
    $startSql = $start . ':00';
    $endSql = $end . ':00';
    $conflict = shift_schedule_conflict($conn, $startSql, $endSql, $id);
    if (($action === 'add_shift' || ($id && ($oldShift = db_one($conn, 'SELECT status FROM master_shift_schedule WHERE id = ?', 'i', [$id]))
        && $oldShift['status'] === 'Scheduled')) && $conflict !== null) {
        echo json_encode(['status'=>'error','message'=>"This time overlaps with scheduled shift \"$conflict\"."]); exit;
    }
    $startLabel = date('h:i A', strtotime($startSql));
    $endLabel = date('h:i A', strtotime($endSql));
    $timeRange = $startLabel . ' – ' . $endLabel;
    try {
        if ($action === 'add_shift') {
            if (db_one($conn, 'SELECT id FROM master_shift_schedule WHERE shift_name = ?', 's', [$name])) {
                echo json_encode(['status'=>'error','message'=>'That shift name already exists.']); exit;
            }
            $stmt = $conn->prepare("INSERT INTO master_shift_schedule (shift_name, time_range, start_time, end_time, target_units, status) VALUES (?, ?, ?, ?, ?, 'Scheduled')");
            $stmt->bind_param('ssssi', $name, $timeRange, $startSql, $endSql, $target);
        } else {
            $oldShift = db_one($conn, 'SELECT shift_name FROM master_shift_schedule WHERE id = ?', 'i', [$id]);
            if (!$oldShift) { echo json_encode(['status'=>'error','message'=>'Shift not found.']); exit; }
            if (db_one($conn, 'SELECT id FROM master_shift_schedule WHERE shift_name = ? AND id <> ?', 'si', [$name, $id])) {
                echo json_encode(['status'=>'error','message'=>'That shift name already exists.']); exit;
            }
            $conn->begin_transaction();
            $stmt = $conn->prepare('UPDATE master_shift_schedule SET shift_name = ?, time_range = ?, start_time = ?, end_time = ?, target_units = ? WHERE id = ?');
            $stmt->bind_param('ssssii', $name, $timeRange, $startSql, $endSql, $target, $id);
        }
        $stmt->execute();
        if ($action === 'update_shift') {
            $oldLabel = preg_replace('/\s+\(.*\)$/', '', $oldShift['shift_name']);
            $newLabel = $name . ' · ' . $timeRange;
            $stmt = $conn->prepare("UPDATE users SET shift_schedule = ? WHERE shift_schedule LIKE CONCAT(?, ' ·%')");
            $stmt->bind_param('ss', $newLabel, $oldLabel);
            $stmt->execute();
            $conn->commit();
        }
        echo json_encode(['status'=>'success','message'=>$action === 'add_shift' ? 'Shift added.' : 'Shift updated.']);
    } catch (mysqli_sql_exception $e) {
        if ($action === 'update_shift') $conn->rollback();
        echo json_encode(['status'=>'error','message'=>'Could not save shift. Check that the shift name is unique.']);
    }
    exit;
}

if ($action === 'disable_shift' || $action === 'enable_shift') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid shift ID.']); exit; }
    $status = $action === 'enable_shift' ? 'Scheduled' : 'Off';
    if ($status === 'Scheduled') {
        $shift = db_one($conn, 'SELECT start_time, end_time FROM master_shift_schedule WHERE id = ?', 'i', [$id]);
        if (!$shift) { echo json_encode(['status'=>'error','message'=>'Shift not found.']); exit; }
        $conflict = shift_schedule_conflict($conn, (string)$shift['start_time'], (string)$shift['end_time'], $id);
        if ($conflict !== null) { echo json_encode(['status'=>'error','message'=>"This time overlaps with scheduled shift \"$conflict\"."]); exit; }
    }
    $stmt = $conn->prepare('UPDATE master_shift_schedule SET status = ? WHERE id = ?');
    $stmt->bind_param('si', $status, $id);
    $stmt->execute();
    echo json_encode(['status'=>$stmt->affected_rows >= 0 ? 'success' : 'error',
        'message'=>$status === 'Off' ? 'Shift disabled. Past shift records are unchanged.' : 'Shift enabled.']);
    exit;
}

if ($action === 'update_defaults') {
    require_post();
    $oee      = floatval($_POST['oee_target_pct'] ?? 85.0);
    $yield    = floatval($_POST['yield_target_pct'] ?? 98.0);
    $units    = intval($_POST['shift_target_units'] ?? 320);
    $hours    = intval($_POST['shift_length_hours'] ?? 8);
    $avail    = floatval($_POST['availability_target_pct'] ?? 92.0);
    $perf     = floatval($_POST['performance_target_pct'] ?? 96.0);
    $qual     = floatval($_POST['quality_target_pct'] ?? 97.0);

    $stmt = $conn->prepare("UPDATE master_target_defaults SET oee_target_pct=?, yield_target_pct=?, shift_target_units=?, shift_length_hours=?, availability_target_pct=?, performance_target_pct=?, quality_target_pct=? WHERE id=1");
    $stmt->bind_param("ddiiddd", $oee, $yield, $units, $hours, $avail, $perf, $qual);
    
    if ($stmt->execute()) {
        echo json_encode(['status' => 'success', 'message' => 'Target defaults saved successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save defaults.']);
    }
    $stmt->close();
    exit;
}

http_response_code(400);
echo json_encode(['status' => 'error', 'message' => 'Unknown master data action.']);
?>
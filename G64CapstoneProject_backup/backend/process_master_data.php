<?php
// backend/process_master_data.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

$action = $_GET['action'] ?? '';

if ($action === 'get_master_data') {
    $stations = db_all($conn, "SELECT * FROM master_stations ORDER BY id ASC");
    $defaults = db_one($conn, "SELECT * FROM master_target_defaults WHERE id = 1");
    $defects  = db_all($conn, "SELECT * FROM defect_categories ORDER BY id ASC");
    $shifts   = db_all($conn, "SELECT * FROM master_shift_schedule ORDER BY id ASC");

    echo json_encode([
        'status' => 'success',
        'stations' => $stations,
        'defaults' => $defaults,
        'defects' => $defects,
        'shifts' => $shifts
    ]);
    exit;
}

if ($action === 'update_station') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $code = trim($_POST['station_code'] ?? '');
    $line = trim($_POST['line_name'] ?? '');
    $workstation = trim($_POST['workstation_name'] ?? '');
    $cycle = filter_input(INPUT_POST, 'ideal_cycle_sec', FILTER_VALIDATE_INT);
    $rate = filter_input(INPUT_POST, 'rate_per_hr', FILTER_VALIDATE_INT);
    $status = trim($_POST['status'] ?? '');
    if (!$id || $code === '' || $line === '' || $workstation === '' || !$cycle || !$rate || !in_array($status, ['Online','Maintenance'], true)) {
        echo json_encode(['status'=>'error','message'=>'Please provide valid station details.']); exit;
    }
    $stmt = $conn->prepare('UPDATE master_stations SET station_code=?, line_name=?, workstation_name=?, ideal_cycle_sec=?, rate_per_hr=?, status=? WHERE id=?');
    $stmt->bind_param('sssiisi', $code, $line, $workstation, $cycle, $rate, $status, $id);
    try {
        $stmt->execute();
        echo json_encode(['status'=>'success','message'=>'Station updated successfully.']);
    } catch (mysqli_sql_exception $e) {
        echo json_encode(['status'=>'error','message'=>((int)$e->getCode()===1062 ? 'That station code is already in use.' : 'Could not update station.')]);
    }
    exit;
}

if ($action === 'delete_station') {
    require_post();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) { echo json_encode(['status'=>'error','message'=>'Invalid station ID.']); exit; }
    $stmt = $conn->prepare('DELETE FROM master_stations WHERE id=?');
    $stmt->bind_param('i', $id); $stmt->execute();
    echo json_encode(['status'=>$stmt->affected_rows ? 'success' : 'error','message'=>$stmt->affected_rows ? 'Station deleted.' : 'Station not found.']);
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
?>
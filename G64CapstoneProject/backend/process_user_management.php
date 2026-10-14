<?php
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

function respond($status, $message, $extra = [], $httpCode = 200) {
    http_response_code($httpCode);
    echo json_encode(array_merge(['status' => $status, 'message' => $message], $extra));
    exit;
}
function audit_change(mysqli $conn, string $description): void {
    $actor = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'Manager';
    $time = date('M d · h:i A');
    $stmt = $conn->prepare('INSERT INTO user_access_changes (log_timestamp, actor, action_description) VALUES (?, ?, ?)');
    $stmt->bind_param('sss', $time, $actor, $description);
    $stmt->execute();
}
/**
 * Operators (role 'staff') must have a station. Returns [station_code|null, error|null].
 * The station must exist in master_stations and belong to the chosen line.
 */
function resolve_station(mysqli $conn, string $role, string $assignment, string $raw): array {
    if ($role !== 'staff') return [null, null];
    $code = trim($raw);
    if ($code === '') return [null, 'Please choose a station for this operator.'];
    $st = db_one($conn, "SELECT station_code, line_name FROM master_stations WHERE station_code = ?", "s", [$code]);
    if (!$st) return [null, 'That station does not exist.'];
    if ($assignment !== 'All Lines' && $st['line_name'] !== $assignment) {
        return [null, "Station $code belongs to {$st['line_name']}, not $assignment."];
    }
    return [$st['station_code'], null];
}
$action = $_GET['action'] ?? '';

if ($action === 'get_users_data') {
    $users = db_all($conn, "SELECT id, username, full_name, role, assignment, assigned_station, last_active, status FROM users ORDER BY id ASC");
    $stations = db_all($conn, "SELECT station_code, line_name, workstation_name FROM master_stations ORDER BY station_code");
    $requests = db_all($conn, "SELECT * FROM user_access_requests WHERE status = 'Pending' ORDER BY id ASC");
    $logs = db_all($conn, "SELECT * FROM user_access_changes ORDER BY id DESC LIMIT 5");
    respond('success', 'Loaded.', ['users'=>$users, 'requests'=>$requests, 'logs'=>$logs, 'stations'=>$stations]);
}
require_post();

if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? 'staff'));
    if ($role === 'operator') $role = 'staff';   // the form says Operator, the database says staff
    $assignment = trim($_POST['assignment'] ?? 'Line 1');
    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) respond('error', 'Username must be 3–50 characters and use only letters, numbers, dots, dashes, or underscores.');
    if (!in_array($role, ['staff','supervisor','manager'], true)) respond('error', 'Invalid role.');
    if (!in_array($assignment, ['Line 1','Line 2','Line 3','All Lines'], true)) respond('error', 'Invalid assignment.');
    [$station, $stErr] = resolve_station($conn, $role, $assignment, $_POST['assigned_station'] ?? '');
    if ($stErr) respond('error', $stErr);
    $temporaryPassword = bin2hex(random_bytes(5));
    $password = password_hash($temporaryPassword, PASSWORD_DEFAULT);
    try {
        $stmt = $conn->prepare("INSERT INTO users (username, full_name, password, role, assignment, assigned_station, status, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 'Active', 1)");
        $stmt->bind_param('ssssss', $username, $username, $password, $role, $assignment, $station);
        $stmt->execute();
        audit_change($conn, "created account for $username ($role)");
        respond('success', "Account created. Copy this temporary password now.", ['id'=>$conn->insert_id, 'temporary_password'=>$temporaryPassword]);
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) respond('error', 'That username already exists.');
        respond('error', 'Could not create account.');
    }
}
if ($action === 'update_user') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $username = trim($_POST['username'] ?? '');
    $fullName = trim($_POST['full_name'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? ''));
    $assignment = trim($_POST['assignment'] ?? '');
    $status = trim($_POST['status'] ?? '');
    if (!$id || $username === '' || $fullName === '') respond('error', 'Please provide a valid user, username, and full name.');
    if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) respond('error', 'Invalid username format.');
    if (!in_array($role, ['staff','supervisor','manager'], true) || !in_array($assignment, ['Line 1','Line 2','Line 3','All Lines'], true) || !in_array($status, ['Active','Disabled'], true)) respond('error', 'Invalid role, assignment, or status.');
    $existing = $conn->prepare('SELECT id, role, status FROM users WHERE id=?');
    $existing->bind_param('i', $id); $existing->execute();
    $old = $existing->get_result()->fetch_assoc();
    if (!$old) respond('error', 'User not found.');
    if ($id === (int)($_SESSION['user_id'] ?? 0) && ($role !== 'manager' || $status !== 'Active')) respond('error', 'You cannot disable or remove your own manager access.');
    if ($old['role'] === 'manager' && $old['status'] === 'Active' && ($role !== 'manager' || $status !== 'Active')) {
        $count = db_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role='manager' AND status='Active'")['n'];
        if ((int)$count <= 1) respond('error', 'At least one active manager must remain.');
    }
    [$station, $stErr] = resolve_station($conn, $role, $assignment, $_POST['assigned_station'] ?? '');
    if ($stErr) respond('error', $stErr);
    try {
        // Non-operators keep whatever station text they already had (e.g. 'All lines'); only operators are managed here.
        $stmt = $conn->prepare('UPDATE users SET username=?, full_name=?, role=?, assignment=?, assigned_station=IF(?="staff", ?, assigned_station), status=? WHERE id=?');
        $stmt->bind_param('sssssssi', $username, $fullName, $role, $assignment, $role, $station, $status, $id);
        $stmt->execute();
        audit_change($conn, "updated account for $username");
        respond('success', 'User updated successfully.');
    } catch (mysqli_sql_exception $e) {
        if ((int)$e->getCode() === 1062) respond('error', 'That username already exists.');
        respond('error', 'Could not update user.');
    }
}
if ($action === 'delete_user') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) respond('error', 'Invalid user ID.');
    if ($id === (int)($_SESSION['user_id'] ?? 0)) respond('error', 'You cannot delete your own account.');
    $stmt = $conn->prepare('SELECT username, role, status FROM users WHERE id=?');
    $stmt->bind_param('i', $id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc();
    if (!$user) respond('error', 'User not found.');
    if ($user['role'] === 'manager' && $user['status'] === 'Active') {
        $count = db_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role='manager' AND status='Active'")['n'];
        if ((int)$count <= 1) respond('error', 'The last manager account cannot be deleted.');
    }
    $del = $conn->prepare('DELETE FROM users WHERE id=?'); $del->bind_param('i', $id); $del->execute();
    audit_change($conn, "deleted account for {$user['username']}");
    respond('success', 'User deleted successfully.');
}
if ($action === 'reset_password') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    if (!$id) respond('error', 'Invalid user ID.');
    if ($id === (int)($_SESSION['user_id'] ?? 0)) respond('error', 'Use your profile page to change your own password.');
    $stmt = $conn->prepare('SELECT username, status FROM users WHERE id=?');
    $stmt->bind_param('i', $id); $stmt->execute(); $user = $stmt->get_result()->fetch_assoc();
    if (!$user) respond('error', 'User not found.');
    $temporaryPassword = bin2hex(random_bytes(5));
    $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);
    $upd = $conn->prepare('UPDATE users SET password=?, must_change_password=1 WHERE id=?');
    $upd->bind_param('si', $hash, $id); $upd->execute();
    audit_change($conn, "reset password for {$user['username']}");
    respond('success', "Password reset for {$user['username']}. Copy the temporary password now.", ['username'=>$user['username'], 'temporary_password'=>$temporaryPassword]);
}
if ($action === 'handle_request') {
    $id = filter_input(INPUT_POST, 'request_id', FILTER_VALIDATE_INT);
    $decision = $_POST['decision'] ?? '';
    if (!$id || !in_array($decision, ['Approved','Declined'], true)) respond('error', 'Invalid access request decision.');
    try {
        $conn->begin_transaction();

        // Lock the request so two clicks cannot create the account twice
        $lock = $conn->prepare("SELECT * FROM user_access_requests WHERE id=? AND status='Pending' FOR UPDATE");
        $lock->bind_param('i', $id); $lock->execute();
        $req = $lock->get_result()->fetch_assoc();
        if (!$req) { $conn->rollback(); respond('error', 'Request not found or already handled.'); }

        $extra = [];
        $message = "Request $decision.";

        if ($decision === 'Approved') {
            // Operator -> staff, Supervisor -> supervisor. Managers are never created from a request.
            $role = (strtolower($req['requested_role']) === 'supervisor') ? 'supervisor' : 'staff';
            $assignment = in_array($req['assignment_line'], ['Line 1','Line 2','Line 3','All Lines'], true) ? $req['assignment_line'] : 'Line 1';
            [$station, $stErr] = resolve_station($conn, $role, $assignment, $_POST['assigned_station'] ?? '');
            if ($stErr) { $conn->rollback(); respond('error', $stErr); }
            $temporaryPassword = bin2hex(random_bytes(5));
            $hash = password_hash($temporaryPassword, PASSWORD_DEFAULT);

            $ins = $conn->prepare("INSERT INTO users (username, full_name, password, role, assignment, assigned_station, status, must_change_password) VALUES (?, ?, ?, ?, ?, ?, 'Active', 1)");
            $ins->bind_param('ssssss', $req['username'], $req['requested_by'], $hash, $role, $assignment, $station);
            $ins->execute();

            $extra = ['temporary_password' => $temporaryPassword, 'username' => $req['username']];
            $message = "Request approved. Account created for {$req['username']}.";
        }

        $stmt = $conn->prepare("UPDATE user_access_requests SET status=? WHERE id=?");
        $stmt->bind_param('si', $decision, $id); $stmt->execute();
        audit_change($conn, strtolower($decision) . " access request #$id ({$req['username']})");

        $conn->commit();
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ((int)$e->getCode() === 1062) respond('error', 'That username already has an account. Decline this request instead.');
        respond('error', 'Could not process the request.');
    }
    respond('success', $message, $extra);
}
respond('error', 'Unknown action.');
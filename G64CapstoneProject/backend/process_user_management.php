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
function valid_assignment(mysqli $conn, string $assignment): bool {
    if ($assignment === 'All Lines') return true;
    return (bool)db_one($conn, 'SELECT id FROM master_lines WHERE line_name = ?', 's', [$assignment]);
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
    $lines = db_all($conn, "SELECT line_name FROM master_lines ORDER BY id");
    $requests = db_all($conn, "SELECT * FROM user_access_requests WHERE status = 'Pending' ORDER BY id ASC");
    $logs = db_all($conn, "SELECT * FROM user_access_changes ORDER BY id DESC LIMIT 5");
    respond('success', 'Loaded.', ['users'=>$users, 'requests'=>$requests, 'logs'=>$logs, 'stations'=>$stations, 'lines'=>$lines, 'current_user_id'=>(int)($_SESSION['user_id'] ?? 0)]);
}
require_post();

if ($action === 'create_user') {
    $username = trim($_POST['username'] ?? '');
    $role = strtolower(trim($_POST['role'] ?? 'staff'));
    if ($role === 'operator') $role = 'staff';   // the form says Operator, the database says staff
    $assignment = trim($_POST['assignment'] ?? '');
    if ($username === '' || !preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) respond('error', 'Username must be 3–50 characters and use only letters, numbers, dots, dashes, or underscores.');
    if (!in_array($role, ['staff','supervisor','manager'], true)) respond('error', 'Invalid role.');
    if (!valid_assignment($conn, $assignment)) respond('error', 'Choose an active production line or All Lines.');
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
    if (!in_array($role, ['staff','supervisor','manager'], true) || !valid_assignment($conn, $assignment) || !in_array($status, ['Active','Disabled'], true)) respond('error', 'Invalid role, assignment, or status.');
    $existing = $conn->prepare('SELECT id, role, status, assigned_station FROM users WHERE id=?');
    $existing->bind_param('i', $id); $existing->execute();
    $old = $existing->get_result()->fetch_assoc();
    if (!$old) respond('error', 'User not found.');
    if ($status !== $old['status']) respond('error', 'Use Deactivate or Reactivate with manager password verification to change account status.', [], 403);
    if ($id === (int)($_SESSION['user_id'] ?? 0) && ($role !== 'manager' || $status !== 'Active')) respond('error', 'You cannot disable or remove your own manager access.');
    if ($old['role'] === 'manager' && $old['status'] === 'Active' && ($role !== 'manager' || $status !== 'Active')) {
        $count = db_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role='manager' AND status='Active'")['n'];
        if ((int)$count <= 1) respond('error', 'At least one active manager must remain.');
    }
    [$station, $stErr] = resolve_station($conn, $role, $assignment, $_POST['assigned_station'] ?? '');
    if ($stErr) respond('error', $stErr);
    try {
        $conn->begin_transaction();
        // Non-operators keep whatever station text they already had (e.g. 'All lines'); only operators are managed here.
        $stmt = $conn->prepare('UPDATE users SET username=?, full_name=?, role=?, assignment=?, assigned_station=IF(?="staff", ?, assigned_station), status=? WHERE id=?');
        $stmt->bind_param('sssssssi', $username, $fullName, $role, $assignment, $role, $station, $status, $id);
        $stmt->execute();
        // Moving an operator to another station: their reports still waiting for review move with them
        $moved = 0;
        if ($role === 'staff' && $station !== null && $station !== ($old['assigned_station'] ?? null)) {
            $moved = move_pending_defect_reports($conn, $id, $station);
        }
        $conn->commit();
        audit_change($conn, "updated account for $username");
        respond('success', 'User updated successfully.' . ($moved > 0 ? " $moved pending defect report(s) moved to $station." : ''));
    } catch (mysqli_sql_exception $e) {
        $conn->rollback();
        if ((int)$e->getCode() === 1062) respond('error', 'That username already exists.');
        respond('error', 'Could not update user.');
    }
}
if ($action === 'delete_user') {
    // This existing action now performs a reversible deactivation (soft delete).
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $managerPassword = (string)($_POST['manager_password'] ?? '');
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    if (!$id || $id === $actorId) respond('error', 'You cannot deactivate your own account.', [], 400);
    if ($managerPassword === '') respond('error', 'Enter your manager password.', [], 400);

    // Password verification happens in PHP; a frontend confirmation is never sufficient.
    $actorStmt = $conn->prepare("SELECT password FROM users WHERE id=? AND role='manager' AND status='Active'");
    $actorStmt->bind_param('i', $actorId);
    $actorStmt->execute();
    $actor = $actorStmt->get_result()->fetch_assoc();
    if (!$actor || !password_verify($managerPassword, $actor['password'])) {
        respond('error', 'Incorrect manager password.', [], 403);
    }

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT username, role, status FROM users WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) { $conn->rollback(); respond('error', 'User not found.', [], 404); }
        if ($user['status'] !== 'Active') { $conn->rollback(); respond('error', 'Account is already disabled.', [], 400); }
        if ($user['role'] === 'manager') {
            $count = db_one($conn, "SELECT COUNT(*) AS n FROM users WHERE role='manager' AND status='Active'")['n'];
            if ((int)$count <= 1) { $conn->rollback(); respond('error', 'The last active manager cannot be deactivated.', [], 400); }
        }
        $upd = $conn->prepare("UPDATE users SET status='Disabled' WHERE id=? AND status='Active'");
        $upd->bind_param('i', $id);
        $upd->execute();
        if ($upd->affected_rows !== 1) throw new RuntimeException('Account state changed; please retry.');
        audit_change($conn, "deactivated account for {$user['username']}");
        $conn->commit();
        respond('success', 'Account deactivated. Historical records were preserved.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond('error', 'Could not deactivate the account. Please retry.', [], 500);
    }
}
if ($action === 'reactivate_user') {
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $managerPassword = (string)($_POST['manager_password'] ?? '');
    $actorId = (int)($_SESSION['user_id'] ?? 0);
    if (!$id) respond('error', 'Invalid user ID.', [], 400);
    if ($managerPassword === '') respond('error', 'Enter your manager password.', [], 400);

    $actorStmt = $conn->prepare("SELECT password FROM users WHERE id=? AND role='manager' AND status='Active'");
    $actorStmt->bind_param('i', $actorId);
    $actorStmt->execute();
    $actor = $actorStmt->get_result()->fetch_assoc();
    if (!$actor || !password_verify($managerPassword, $actor['password'])) {
        respond('error', 'Incorrect manager password.', [], 403);
    }

    try {
        $conn->begin_transaction();
        $stmt = $conn->prepare('SELECT username, status FROM users WHERE id=? FOR UPDATE');
        $stmt->bind_param('i', $id);
        $stmt->execute();
        $user = $stmt->get_result()->fetch_assoc();
        if (!$user) { $conn->rollback(); respond('error', 'User not found.', [], 404); }
        if ($user['status'] !== 'Disabled') { $conn->rollback(); respond('error', 'Only disabled accounts can be reactivated.', [], 400); }

        $upd = $conn->prepare("UPDATE users SET status='Active' WHERE id=? AND status='Disabled'");
        $upd->bind_param('i', $id);
        $upd->execute();
        if ($upd->affected_rows !== 1) throw new RuntimeException('Account state changed; please retry.');
        audit_change($conn, "reactivated account for {$user['username']}");
        $conn->commit();
        respond('success', 'Account reactivated. The user can sign in again with their existing credentials.');
    } catch (Throwable $e) {
        $conn->rollback();
        respond('error', 'Could not reactivate the account. Please retry.', [], 500);
    }
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
            $assignment = (string)$req['assignment_line'];
            if (!valid_assignment($conn, $assignment)) {
                $conn->rollback();
                respond('error', 'The requested production line is no longer active. Ask the applicant to submit a new request.');
            }
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
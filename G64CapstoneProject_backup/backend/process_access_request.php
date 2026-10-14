<?php
// backend/process_access_request.php
// PUBLIC endpoint (no login): the "Request access" form on login.php.
// It only adds a Pending row to user_access_requests. A manager approves or
// declines it in Users & Access; the account is created on approval.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';   // also starts the session
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_post();

csrf_verify();

// Bots fill hidden fields. Pretend it worked.
if (trim($_POST['website'] ?? '') !== '') {
    json_out(['status' => 'success', 'message' => 'Request sent. A manager will review it.']);
}

/* ---------- light throttling: 5 requests per hour per browser session ---------- */
$now = time();
$_SESSION['access_req_times'] = array_values(array_filter(
    $_SESSION['access_req_times'] ?? [],
    fn($t) => $t > $now - 3600
));
if (count($_SESSION['access_req_times']) >= 5) {
    json_out(['status' => 'error', 'message' => 'Too many requests. Please try again later.'], 429);
}

/* ---------- validation ---------- */
$full_name = trim(preg_replace('/\s+/', ' ', $_POST['full_name'] ?? ''));
$username  = trim($_POST['username'] ?? '');
$role      = $_POST['role'] ?? '';
$line      = $_POST['line'] ?? '';

// Letters, spaces, dots, apostrophes and dashes only (the name is shown on other pages)
if (mb_strlen($full_name) < 2 || mb_strlen($full_name) > 100
    || !preg_match("/^[\p{L}\p{M}][\p{L}\p{M} .'-]*$/u", $full_name)) {
    json_out(['status' => 'error', 'message' => 'Please enter your full name using letters only.'], 400);
}
if (!preg_match('/^[a-zA-Z0-9_.-]{3,50}$/', $username)) {
    json_out(['status' => 'error', 'message' => 'Username must be 3–50 characters: letters, numbers, dots, dashes or underscores.'], 400);
}
if (!in_array($role, ['Operator', 'Supervisor'], true)) {   // managers are never self-requested
    json_out(['status' => 'error', 'message' => 'Please choose a role.'], 400);
}
if (!in_array($line, ['Line 1', 'Line 2', 'Line 3'], true)) {
    json_out(['status' => 'error', 'message' => 'Please choose a line.'], 400);
}

// Cap the pending queue so the form can't flood the manager's page
$q = db_one($conn, "SELECT COUNT(*) AS n FROM user_access_requests WHERE status = 'Pending'");
if ((int)$q['n'] >= 100) {
    json_out(['status' => 'error', 'message' => 'Requests are temporarily unavailable. Please ask your supervisor.'], 503);
}

if (db_one($conn, "SELECT id FROM users WHERE username = ?", "s", [$username])
    || db_one($conn, "SELECT id FROM user_access_requests WHERE username = ? AND status = 'Pending'", "s", [$username])) {
    json_out(['status' => 'error', 'message' => 'That username is not available. Please choose another one.'], 409);
}

db_run($conn,
    "INSERT INTO user_access_requests (username, requested_role, assignment_line, requested_by, request_date, status)
     VALUES (?, ?, ?, ?, ?, 'Pending')",
    "sssss", [$username, $role, $line, $full_name, date('M d')]);

$_SESSION['access_req_times'][] = $now;

json_out(['status' => 'success', 'message' => 'Request sent. A manager will review it and give you your login details.']);
?>
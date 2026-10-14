<?php
// backend/get_access_requests.php
// Read-only list of access requests for supervisors. Only a manager can approve or decline
// (backend/process_user_management.php); supervisors can see where a request stands.
header('Content-Type: application/json; charset=utf-8');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');

$status = $_GET['status'] ?? 'all';
$sql = "SELECT id, username, requested_role, assignment_line, requested_by, request_date, status FROM user_access_requests";
$types = ''; $params = [];
if (in_array($status, ['Pending', 'Approved', 'Declined'], true)) {
    $sql .= " WHERE status = ?"; $types = 's'; $params[] = $status;
}
$sql .= " ORDER BY (status = 'Pending') DESC, id DESC LIMIT 100";   // pending first, newest first, capped

$rows = db_all($conn, $sql, $types, $params);
$pending = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM user_access_requests WHERE status = 'Pending'")['n'] ?? 0);

echo json_encode(['status' => 'success', 'requests' => $rows, 'pending' => $pending]);
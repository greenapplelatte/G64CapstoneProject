<?php
// register.php
session_start();

// Connect to the database config inside sdpbus/database/
require_once __DIR__ . '/sdpbus/database/database.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['status' => 'error', 'message' => 'Invalid request method.']);
    exit;
}

$fullname        = trim($_POST['fullname'] ?? '');
$username        = trim($_POST['username'] ?? '');
$password        = $_POST['password'] ?? '';
$confirmPassword = $_POST['confirm_password'] ?? '';

// --- Validation ---
if ($fullname === '' || $username === '' || $password === '' || $confirmPassword === '') {
    echo json_encode(['status' => 'error', 'message' => 'Please fill in all fields.']);
    exit;
}

if (strlen($password) < 6) {
    echo json_encode(['status' => 'error', 'message' => 'Password must be at least 6 characters.']);
    exit;
}

if ($password !== $confirmPassword) {
    echo json_encode(['status' => 'error', 'message' => 'Passwords do not match.']);
    exit;
}

// Keep usernames predictable: letters, numbers, dots, underscores only
if (!preg_match('/^[A-Za-z0-9._]{3,32}$/', $username)) {
    echo json_encode([
        'status' => 'error',
        'message' => 'Username must be 3-32 characters (letters, numbers, dots, underscores only).'
    ]);
    exit;
}

try {
    // Check for an existing username before inserting, so we can give
    // a clear message instead of a raw constraint-violation error.
    $check = $db->prepare('SELECT COUNT(*) FROM users WHERE username = :username');
    $check->execute([':username' => $username]);

    if ((int) $check->fetchColumn() > 0) {
        echo json_encode(['status' => 'error', 'message' => 'That username is already taken.']);
        exit;
    }

    $hash = password_hash($password, PASSWORD_BCRYPT);

    $stmt = $db->prepare(
        'INSERT INTO users (username, password, fullname, role) VALUES (:u, :p, :f, :r)'
    );
    $stmt->execute([
        ':u' => $username,
        ':p' => $hash,
        ':f' => $fullname,
        ':r' => 'dispatcher', // self-registered accounts are never admin by default
    ]);

    $newUserId = (int) $db->lastInsertId();

    // Auto-login: start the session right away so the person lands
    // straight on the dashboard instead of having to log in again.
    $_SESSION['user_id']  = $newUserId;
    $_SESSION['username'] = $username;
    $_SESSION['fullname'] = $fullname;
    $_SESSION['role']     = 'dispatcher';

    echo json_encode(['status' => 'success', 'redirect' => 'dashboard.php']);

} catch (PDOException $e) {
    // A UNIQUE constraint race (two people registering the same
    // username at the same instant) lands here even after the check
    // above — handle it with the same friendly message.
    if (str_contains($e->getMessage(), 'UNIQUE constraint failed')) {
        echo json_encode(['status' => 'error', 'message' => 'That username is already taken.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    }
}

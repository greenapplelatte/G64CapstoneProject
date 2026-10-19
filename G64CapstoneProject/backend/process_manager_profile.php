<?php
// backend/process_manager_profile.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';

check_role('manager');

$user_id = (int) ($_SESSION['user_id'] ?? 0);
if ($user_id <= 0) {
    http_response_code(401);
    echo json_encode(['status' => 'error', 'message' => 'Session expired. Please log in again.']);
    exit;
}
$action  = $_GET['action'] ?? '';

// Fetch Manager Profile Info
if ($action === 'get_profile') {
    $stmt = $conn->prepare("SELECT full_name, employee_id, department, contact_number, assigned_station, shift_schedule, last_signin, pref_defect_alert, pref_shift_reminder, pref_24h_format FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $data = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

// Update editable account details for the logged-in manager.
if ($action === 'update_account') {
    require_post();

    $name = trim((string) ($_POST['full_name'] ?? ''));
    $department = trim((string) ($_POST['department'] ?? ''));
    $contact = trim((string) ($_POST['contact_number'] ?? ''));

    if (mb_strlen($name) < 2 || mb_strlen($name) > 100 ||
        mb_strlen($department) < 2 || mb_strlen($department) > 100 ||
        mb_strlen($contact) > 30) {
        http_response_code(422);
        echo json_encode(['status' => 'error', 'message' => 'Please enter a valid name, department, and contact number.']);
        exit;
    }

    $stmt = $conn->prepare('UPDATE users SET full_name = ?, department = ?, contact_number = ? WHERE id = ?');
    if (!$stmt) {
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Cannot prepare profile update.']);
        exit;
    }
    $stmt->bind_param('sssi', $name, $department, $contact, $user_id);
    if (!$stmt->execute()) {
        $stmt->close();
        http_response_code(500);
        echo json_encode(['status' => 'error', 'message' => 'Unable to save account details.']);
        exit;
    }
    $stmt->close();
    $_SESSION['full_name'] = $name;

    echo json_encode([
        'status' => 'success',
        'message' => 'Account details saved successfully.',
        'data' => ['full_name' => $name, 'department' => $department, 'contact_number' => $contact]
    ]);
    exit;
}

// Update Password
if ($action === 'update_password') {
    require_post();
    $current_pass = $_POST['current_password'] ?? '';
    $new_pass     = $_POST['new_password'] ?? '';
    $confirm_pass = $_POST['confirm_password'] ?? '';

    if ($new_pass !== $confirm_pass) {
        echo json_encode(['status' => 'error', 'message' => 'New passwords do not match.']);
        exit;
    }

    $stmt = $conn->prepare("SELECT password FROM users WHERE id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $res = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    if ($res && password_verify($current_pass, $res['password'])) {
        if (strlen($new_pass) < 8) {
            echo json_encode(['status' => 'error', 'message' => 'New password must be at least 8 characters.']);
            exit;
        }
        if (hash_equals($current_pass, $new_pass)) {
            echo json_encode(['status' => 'error', 'message' => 'Choose a password different from the current one.']);
            exit;
        }
        $new_hash = password_hash($new_pass, PASSWORD_BCRYPT);
        $update_stmt = $conn->prepare("UPDATE users SET password = ?, must_change_password = 0 WHERE id = ?");
        $update_stmt->bind_param("si", $new_hash, $user_id);
        $update_stmt->execute();
        $update_stmt->close();
        unset($_SESSION['must_change_password']);

        echo json_encode(['status' => 'success', 'message' => 'Password updated successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Current password is incorrect.']);
    }
    exit;
}

// Save Preferences
if ($action === 'save_preferences') {
    require_post();
    $alert    = isset($_POST['pref_defect_alert']) ? 1 : 0;
    $reminder = isset($_POST['pref_shift_reminder']) ? 1 : 0;
    $format24 = isset($_POST['pref_24h_format']) ? 1 : 0;

    $stmt = $conn->prepare("UPDATE users SET pref_defect_alert = ?, pref_shift_reminder = ?, pref_24h_format = ? WHERE id = ?");
    $stmt->bind_param("iiii", $alert, $reminder, $format24, $user_id);
    $stmt->execute();
    $stmt->close();

    echo json_encode(['status' => 'success', 'message' => 'Preferences saved.']);
    exit;
}
?>
<?php
// backend/master_data/manage_buses.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $action = sanitize($_POST['action'] ?? '');
    $busId = (int)($_POST['bus_id'] ?? 0);

    try {
        $db = Database::getConnection();

        if ($action === 'create' || $action === 'update') {
            $busCode = sanitize($_POST['bus_code'] ?? '');
            $capacity = (int)($_POST['capacity'] ?? 40);
            $status = sanitize($_POST['status'] ?? 'ACTIVE');

            if (empty($busCode)) {
                $_SESSION['error'] = "Bus code is required.";
                header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=buses");
                exit;
            }

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO buses (bus_code, capacity, status) VALUES (:code, :capacity, :status)");
                $stmt->execute([
                    ':code'     => $busCode,
                    ':capacity' => $capacity,
                    ':status'   => $status
                ]);
                log_activity($userId, "Create Bus", null, "Created bus: {$busCode}");
                $_SESSION['success'] = "Bus '{$busCode}' added successfully.";
            } else {
                $stmt = $db->prepare("UPDATE buses SET bus_code = :code, capacity = :capacity, status = :status WHERE id = :id");
                $stmt->execute([
                    ':code'     => $busCode,
                    ':capacity' => $capacity,
                    ':status'   => $status,
                    ':id'       => $busId
                ]);
                log_activity($userId, "Update Bus", null, "Updated bus ID: {$busId}");
                $_SESSION['success'] = "Bus details updated successfully.";
            }

        } elseif ($action === 'toggle_status') {
            $status = sanitize($_POST['status'] ?? 'INACTIVE');
            $stmt = $db->prepare("UPDATE buses SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $busId]);

            log_activity($userId, "Toggle Bus Status", null, "Bus ID {$busId} set to {$status}");
            $_SESSION['success'] = "Bus status updated to {$status}.";
        }

        header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=buses");
        exit;

    } catch (Exception $e) {
        error_log("Manage Buses Error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process bus operation. Ensure bus code is unique.";
        header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=buses");
        exit;
    }
}
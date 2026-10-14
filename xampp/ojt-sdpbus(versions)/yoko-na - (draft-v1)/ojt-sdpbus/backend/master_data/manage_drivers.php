<?php
// backend/master_data/manage_drivers.php
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
    $driverId = (int)($_POST['driver_id'] ?? 0);

    try {
        $db = Database::getConnection();

        if ($action === 'create' || $action === 'update') {
            $driverName = sanitize($_POST['driver_name'] ?? '');
            $status = sanitize($_POST['status'] ?? 'ACTIVE');

            if (empty($driverName)) {
                $_SESSION['error'] = "Driver name is required.";
                header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=drivers");
                exit;
            }

            if ($action === 'create') {
                $stmt = $db->prepare("INSERT INTO drivers (driver_name, status) VALUES (:name, :status)");
                $stmt->execute([
                    ':name'   => $driverName,
                    ':status' => $status
                ]);
                log_activity($userId, "Create Driver", null, "Created driver: {$driverName}");
                $_SESSION['success'] = "Driver '{$driverName}' added successfully.";
            } else {
                $stmt = $db->prepare("UPDATE drivers SET driver_name = :name, status = :status WHERE id = :id");
                $stmt->execute([
                    ':name'   => $driverName,
                    ':status' => $status,
                    ':id'     => $driverId
                ]);
                log_activity($userId, "Update Driver", null, "Updated driver ID: {$driverId}");
                $_SESSION['success'] = "Driver details updated successfully.";
            }

        } elseif ($action === 'toggle_status') {
            $status = sanitize($_POST['status'] ?? 'INACTIVE');
            $stmt = $db->prepare("UPDATE drivers SET status = :status WHERE id = :id");
            $stmt->execute([':status' => $status, ':id' => $driverId]);

            log_activity($userId, "Toggle Driver Status", null, "Driver ID {$driverId} set to {$status}");
            $_SESSION['success'] = "Driver status updated to {$status}.";
        }

        header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=drivers");
        exit;

    } catch (Exception $e) {
        error_log("Manage Drivers Error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to process driver operation.";
        header("Location: /ojt-sdpbus/frontend/admin/master_data.php?tab=drivers");
        exit;
    }
}
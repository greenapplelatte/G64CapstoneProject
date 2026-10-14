<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$redirect = '/xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php?tab=assignments';
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header("Location: {$redirect}");
    exit;
}

$userId = (int)($_SESSION['user_id'] ?? 0);
$action = sanitize($_POST['action'] ?? '');

try {
    $db = Database::getConnection();

    if ($action === 'create') {
        $driverId = (int)($_POST['driver_id'] ?? 0);
        $busId = (int)($_POST['bus_id'] ?? 0);
        $routeId = (int)($_POST['route_id'] ?? 0);

        if ($driverId <= 0 || $busId <= 0 || $routeId <= 0) {
            throw new InvalidArgumentException('Select an active driver, bus, and route.');
        }

        $activeEntities = $db->prepare("SELECT
            (SELECT COUNT(*) FROM drivers WHERE id = :driver_id AND status = 'ACTIVE') AS driver_count,
            (SELECT COUNT(*) FROM buses WHERE id = :bus_id AND status = 'ACTIVE') AS bus_count,
            (SELECT COUNT(*) FROM routes WHERE id = :route_id AND status = 'ACTIVE') AS route_count");
        $activeEntities->execute([
            ':driver_id' => $driverId,
            ':bus_id' => $busId,
            ':route_id' => $routeId
        ]);
        $counts = $activeEntities->fetch();
        if ((int)$counts['driver_count'] !== 1 || (int)$counts['bus_count'] !== 1 || (int)$counts['route_count'] !== 1) {
            throw new InvalidArgumentException('Assignments require an active driver, bus, and route.');
        }

        $existing = $db->prepare('SELECT id, status FROM driver_assignments WHERE driver_id = :driver_id AND bus_id = :bus_id AND route_id = :route_id');
        $existing->execute([
            ':driver_id' => $driverId,
            ':bus_id' => $busId,
            ':route_id' => $routeId
        ]);
        $assignment = $existing->fetch();

        if ($assignment) {
            if ($assignment['status'] === 'ACTIVE') {
                throw new InvalidArgumentException('That driver, bus, and route combination is already assigned.');
            }
            $save = $db->prepare("UPDATE driver_assignments SET status = 'ACTIVE' WHERE id = :id");
            $save->execute([':id' => $assignment['id']]);
            $message = 'Existing assignment reactivated successfully.';
        } else {
            $save = $db->prepare("INSERT INTO driver_assignments (driver_id, bus_id, route_id, status) VALUES (:driver_id, :bus_id, :route_id, 'ACTIVE')");
            $save->execute([
                ':driver_id' => $driverId,
                ':bus_id' => $busId,
                ':route_id' => $routeId
            ]);
            $message = 'Driver, bus, and route assignment created successfully.';
        }

        log_activity($userId, 'Create Driver Assignment', null, "Assigned driver ID {$driverId}, bus ID {$busId}, route ID {$routeId}");
        $_SESSION['success'] = $message;
    } elseif ($action === 'toggle_status') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        $status = strtoupper(sanitize($_POST['status'] ?? ''));
        if ($assignmentId <= 0 || !in_array($status, ['ACTIVE', 'INACTIVE'], true)) {
            throw new InvalidArgumentException('Select a valid assignment and status.');
        }

        $update = $db->prepare('UPDATE driver_assignments SET status = :status WHERE id = :id');
        $update->execute([':status' => $status, ':id' => $assignmentId]);
        if ($update->rowCount() === 0) {
            $check = $db->prepare('SELECT 1 FROM driver_assignments WHERE id = :id');
            $check->execute([':id' => $assignmentId]);
            if (!$check->fetchColumn()) {
                throw new InvalidArgumentException('The selected assignment was not found.');
            }
        }

        log_activity($userId, 'Toggle Driver Assignment', null, "Assignment ID {$assignmentId} set to {$status}");
        $_SESSION['success'] = 'Assignment status updated.';
    } elseif ($action === 'delete') {
        $assignmentId = (int)($_POST['assignment_id'] ?? 0);
        if ($assignmentId <= 0) {
            throw new InvalidArgumentException('Select a valid assignment to delete.');
        }

        $detailsStmt = $db->prepare('SELECT d.driver_name, b.bus_code, r.route_name
                                     FROM driver_assignments da
                                     JOIN drivers d ON d.id = da.driver_id
                                     JOIN buses b ON b.id = da.bus_id
                                     JOIN routes r ON r.id = da.route_id
                                     WHERE da.id = :id');
        $detailsStmt->execute([':id' => $assignmentId]);
        $assignment = $detailsStmt->fetch();
        if (!$assignment) {
            throw new InvalidArgumentException('The selected assignment was not found.');
        }

        $delete = $db->prepare('DELETE FROM driver_assignments WHERE id = :id');
        $delete->execute([':id' => $assignmentId]);
        if ($delete->rowCount() !== 1) {
            throw new RuntimeException('The assignment could not be deleted.');
        }

        log_activity(
            $userId,
            'Delete Driver Assignment',
            null,
            "Removed assignment: {$assignment['driver_name']} / {$assignment['bus_code']} / {$assignment['route_name']}"
        );
        $_SESSION['success'] = 'Assignment deleted. The driver, bus, route, and trip records are unchanged.';
    } else {
        throw new InvalidArgumentException('Unsupported assignment operation.');
    }
} catch (InvalidArgumentException $e) {
    $_SESSION['error'] = $e->getMessage();
} catch (Throwable $e) {
    error_log('Manage Driver Assignments Error: ' . $e->getMessage());
    $_SESSION['error'] = 'Unable to save this driver, bus, and route assignment.';
}

header("Location: {$redirect}");
exit;
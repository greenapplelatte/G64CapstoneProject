<?php
// backend/schedule/save_schedule.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $scheduleDate = sanitize($_POST['schedule_date'] ?? date('Y-m-d'));
    $schedules = $_POST['schedules'] ?? []; // Array of [shift, route_id, bus_id, driver_id, forecast_passengers]

    if (empty($schedules) || !is_array($schedules)) {
        $_SESSION['error'] = "No schedule entries were submitted.";
        header("Location: /ojt-sdpbus/frontend/admin/trip_schedule.php?date=" . urlencode($scheduleDate));
        exit;
    }

    $db = null;

    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        // Server-side conflict detection maps
        $assignedBuses = [];
        $assignedDrivers = [];

        foreach ($schedules as $index => $entry) {
            $shift = sanitize($entry['shift'] ?? '');
            $routeId = (int)($entry['route_id'] ?? 0);
            $busId = !empty($entry['bus_id']) ? (int)$entry['bus_id'] : null;
            $driverId = !empty($entry['driver_id']) ? (int)$entry['driver_id'] : null;
            $forecast = (int)($entry['forecast_passengers'] ?? 0);

            if (empty($shift) || $routeId <= 0) {
                continue; // Skip incomplete entries
            }

            // Conflict Check: Bus availability per shift
            if ($busId) {
                $busKey = "{$shift}_{$busId}";
                if (isset($assignedBuses[$busKey])) {
                    $db->rollBack();
                    $_SESSION['error'] = "Conflict detected: Bus ID {$busId} is double-booked for shift '{$shift}'.";
                    header("Location: /ojt-sdpbus/frontend/admin/trip_schedule.php?date=" . urlencode($scheduleDate));
                    exit;
                }
                $assignedBuses[$busKey] = true;
            }

            // Conflict Check: Driver availability per shift
            if ($driverId) {
                $driverKey = "{$shift}_{$driverId}";
                if (isset($assignedDrivers[$driverKey])) {
                    $db->rollBack();
                    $_SESSION['error'] = "Conflict detected: Driver ID {$driverId} is double-booked for shift '{$shift}'.";
                    header("Location: /ojt-sdpbus/frontend/admin/trip_schedule.php?date=" . urlencode($scheduleDate));
                    exit;
                }
                $assignedDrivers[$driverKey] = true;
            }

            // Upsert schedule row (Update if exists for date/shift/route, else insert)
            $checkStmt = $db->prepare("SELECT id FROM trip_schedules WHERE schedule_date = :date AND shift = :shift AND route_id = :route_id");
            $checkStmt->execute([
                ':date'     => $scheduleDate,
                ':shift'    => $shift,
                ':route_id' => $routeId
            ]);
            $existing = $checkStmt->fetch();

            if ($existing) {
                $updateStmt = $db->prepare("UPDATE trip_schedules SET 
                                            bus_id = :bus_id, 
                                            driver_id = :driver_id, 
                                            forecast_passengers = :forecast 
                                            WHERE id = :id");
                $updateStmt->execute([
                    ':bus_id'   => $busId,
                    ':driver_id'=> $driverId,
                    ':forecast' => $forecast,
                    ':id'        => $existing['id']
                ]);
            } else {
                $insertStmt = $db->prepare("INSERT INTO trip_schedules 
                                            (schedule_date, shift, route_id, bus_id, driver_id, forecast_passengers) 
                                            VALUES (:date, :shift, :route_id, :bus_id, :driver_id, :forecast)");
                $insertStmt->execute([
                    ':date'     => $scheduleDate,
                    ':shift'    => $shift,
                    ':route_id' => $routeId,
                    ':bus_id'   => $busId,
                    ':driver_id'=> $driverId,
                    ':forecast' => $forecast
                ]);
            }
        }

        $db->commit();

        log_activity($userId, "Updated Trip Schedule", null, "Updated schedule for date: {$scheduleDate}");

        $_SESSION['success'] = "Trip schedule for {$scheduleDate} updated successfully.";
        header("Location: /ojt-sdpbus/frontend/admin/trip_schedule.php?date=" . urlencode($scheduleDate));
        exit;

    } catch (Exception $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Save Schedule Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while saving the schedule.";
        header("Location: /ojt-sdpbus/frontend/admin/trip_schedule.php?date=" . urlencode($scheduleDate));
        exit;
    }
}
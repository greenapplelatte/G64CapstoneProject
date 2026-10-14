<?php
// backend/tickets/create_ticket.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $scheduleId = !empty($_POST['schedule_id']) ? (int)$_POST['schedule_id'] : null;
    $routeId = (int)($_POST['route_id'] ?? 0);
    $busId = (int)($_POST['bus_id'] ?? 0);
    $driverId = (int)($_POST['driver_id'] ?? 0);
    $tripDate = sanitize($_POST['trip_date'] ?? date('Y-m-d'));
    $tripTime = sanitize($_POST['trip_time'] ?? date('H:i'));
    $forecastPassengers = (int)($_POST['forecast_passengers'] ?? 0);
    $actualPassengers = (int)($_POST['actual_passengers'] ?? 0);
    $tripStatus = sanitize($_POST['trip_status'] ?? 'On time');
    $remarks = sanitize($_POST['remarks'] ?? '');
    $action = sanitize($_POST['action'] ?? 'submit'); // 'draft' or 'submit'

    // Form Validation
    if ($routeId <= 0 || $busId <= 0 || $driverId <= 0 || empty($tripTime)) {
        $_SESSION['error'] = "Please fill in all required dropdowns and trip details.";
        header("Location: /ojt-sdpbus/frontend/staff/new_ticket.php");
        exit;
    }

    if ($actualPassengers < 0 || $forecastPassengers < 0) {
        $_SESSION['error'] = "Passenger counts cannot be negative.";
        header("Location: /ojt-sdpbus/frontend/staff/new_ticket.php");
        exit;
    }

    try {
        $db = Database::getConnection();

        // Check Bus Capacity validation
        $busStmt = $db->prepare("SELECT capacity FROM buses WHERE id = :bus_id");
        $busStmt->execute([':bus_id' => $busId]);
        $bus = $busStmt->fetch();

        if ($bus && $actualPassengers > (int)$bus['capacity']) {
            $_SESSION['error'] = "Actual passengers ({$actualPassengers}) exceed bus capacity ({$bus['capacity']}).";
            header("Location: /ojt-sdpbus/frontend/staff/new_ticket.php");
            exit;
        }

        // Fetch Route Rate and Toll automatically from Master Data
        $routeStmt = $db->prepare("SELECT rate, toll FROM routes WHERE id = :route_id");
        $routeStmt->execute([':route_id' => $routeId]);
        $route = $routeStmt->fetch();

        if (!$route) {
            $_SESSION['error'] = "Selected route does not exist.";
            header("Location: /ojt-sdpbus/frontend/staff/new_ticket.php");
            exit;
        }

        $rate = (float)$route['rate'];
        $toll = (float)$route['toll'];
        $ticketTotal = $rate + $toll;

        // Auto Calculations
        $ticketNumber = generate_ticket_number();
        $shift = calculate_shift($tripTime);
        $reviewStatus = ($action === 'draft') ? 'Draft' : 'Submitted';

        // Insert Ticket
        $stmt = $db->prepare("INSERT INTO tickets (
            ticket_number, schedule_id, user_id, route_id, bus_id, driver_id,
            shift, trip_date, trip_time, forecast_passengers, actual_passengers,
            rate, toll, ticket_total, trip_status, review_status, remarks
        ) VALUES (
            :ticket_number, :schedule_id, :user_id, :route_id, :bus_id, :driver_id,
            :shift, :trip_date, :trip_time, :forecast, :actual,
            :rate, :toll, :total, :trip_status, :review_status, :remarks
        )");

        $stmt->execute([
            ':ticket_number' => $ticketNumber,
            ':schedule_id'   => $scheduleId,
            ':user_id'       => $userId,
            ':route_id'      => $routeId,
            ':bus_id'        => $busId,
            ':driver_id'     => $driverId,
            ':shift'         => $shift,
            ':trip_date'     => $tripDate,
            ':trip_time'     => $tripTime,
            ':forecast'      => $forecastPassengers,
            ':actual'        => $actualPassengers,
            ':rate'          => $rate,
            ':toll'          => $toll,
            ':total'         => $ticketTotal,
            ':trip_status'   => $tripStatus,
            ':review_status' => $reviewStatus,
            ':remarks'       => $remarks
        ]);

        log_activity($userId, "Create Ticket ({$reviewStatus})", $ticketNumber, "Ticket created for shift {$shift}");

        $_SESSION['success'] = "Ticket {$ticketNumber} saved successfully as {$reviewStatus}.";

        if ($_SESSION['role'] === 'admin') {
            header("Location: /ojt-sdpbus/frontend/admin/dispatch_log.php");
        } else {
            header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
        }
        exit;

    } catch (Exception $e) {
        error_log("Create Ticket Error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to create trip ticket. Please try again.";
        header("Location: /ojt-sdpbus/frontend/staff/new_ticket.php");
        exit;
    }
}
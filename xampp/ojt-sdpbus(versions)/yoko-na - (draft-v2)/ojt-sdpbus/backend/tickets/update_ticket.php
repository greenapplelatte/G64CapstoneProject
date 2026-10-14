<?php
// backend/tickets/update_ticket.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? 'submit'); // 'draft' or 'resubmit'
    $tripTime = sanitize($_POST['trip_time'] ?? '');
    $actualPassengers = (int)($_POST['actual_passengers'] ?? 0);
    $tripStatus = sanitize($_POST['trip_status'] ?? 'On time');
    $remarks = sanitize($_POST['remarks'] ?? '');

    try {
        $db = Database::getConnection();

        // Verify ticket ownership & status lock check
        $checkStmt = $db->prepare("SELECT ticket_number, review_status, billing_closed, user_id FROM tickets WHERE id = :id");
        $checkStmt->execute([':id' => $ticketId]);
        $ticket = $checkStmt->fetch();

        if (!$ticket) {
            $_SESSION['error'] = "Ticket not found.";
            header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
            exit;
        }

        // Staff can only edit Draft or Returned states; Approved tickets are locked
        if ($_SESSION['role'] === 'staff' && !in_array($ticket['review_status'], ['Draft', 'Returned'], true)) {
            $_SESSION['error'] = "Approved or locked tickets cannot be edited.";
            header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
            exit;
        }

        if ((int)$ticket['billing_closed'] === 1) {
            $_SESSION['error'] = "This ticket belongs to a closed billing period and is permanently locked.";
            header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
            exit;
        }

        $newReviewStatus = ($action === 'draft') ? 'Draft' : 'Submitted';
        $shift = !empty($tripTime) ? calculate_shift($tripTime) : null;

        $updateSql = "UPDATE tickets SET 
                        actual_passengers = :actual,
                        trip_status = :trip_status,
                        review_status = :review_status,
                        remarks = :remarks";

        if ($shift) {
            $updateSql .= ", trip_time = :trip_time, shift = :shift";
        }

        $updateSql .= " WHERE id = :id";

        $stmt = $db->prepare($updateSql);

        $params = [
            ':actual'        => $actualPassengers,
            ':trip_status'   => $tripStatus,
            ':review_status' => $newReviewStatus,
            ':remarks'       => $remarks,
            ':id'             => $ticketId
        ];

        if ($shift) {
            $params[':trip_time'] = $tripTime;
            $params[':shift']     = $shift;
        }

        $stmt->execute($params);

        log_activity($userId, "Updated Ticket ({$newReviewStatus})", $ticket['ticket_number'], "Ticket updated and status changed to {$newReviewStatus}");

        $_SESSION['success'] = "Ticket {$ticket['ticket_number']} updated successfully.";

        if ($_SESSION['role'] === 'admin') {
            header("Location: /ojt-sdpbus/frontend/admin/dispatch_log.php");
        } else {
            header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
        }
        exit;

    } catch (Exception $e) {
        error_log("Update Ticket Error: " . $e->getMessage());
        $_SESSION['error'] = "Failed to update ticket.";
        header("Location: /ojt-sdpbus/frontend/staff/my_tickets.php");
        exit;
    }
}
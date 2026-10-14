<?php
// backend/tickets/review_action.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $ticketId = (int)($_POST['ticket_id'] ?? 0);
    $action = sanitize($_POST['action'] ?? ''); // 'approve' or 'return'
    $adminComment = sanitize($_POST['admin_comment'] ?? '');

    if ($ticketId <= 0 || !in_array($action, ['approve', 'return'], true)) {
        $_SESSION['error'] = "Invalid review action requested.";
        header("Location: /ojt-sdpbus/frontend/admin/review_queue.php");
        exit;
    }

    // Require comment when returning ticket
    if ($action === 'return' && empty($adminComment)) {
        $_SESSION['error'] = "A reason comment is required when returning a ticket to staff.";
        header("Location: /ojt-sdpbus/frontend/admin/review_queue.php");
        exit;
    }

    try {
        $db = Database::getConnection();

        $stmt = $db->prepare("SELECT ticket_number, review_status FROM tickets WHERE id = :id");
        $stmt->execute([':id' => $ticketId]);
        $ticket = $stmt->fetch();

        if (!$ticket) {
            $_SESSION['error'] = "Ticket not found.";
            header("Location: /ojt-sdpbus/frontend/admin/review_queue.php");
            exit;
        }

        $newStatus = ($action === 'approve') ? 'Approved' : 'Returned';

        $updateStmt = $db->prepare("UPDATE tickets SET review_status = :status, admin_comment = :comment WHERE id = :id");
        $updateStmt->execute([
            ':status'  => $newStatus,
            ':comment' => $adminComment,
            ':id'      => $ticketId
        ]);

        log_activity($userId, "Ticket Reviewed ({$newStatus})", $ticket['ticket_number'], "Admin changed review status to {$newStatus}. Comment: {$adminComment}");

        $_SESSION['success'] = "Ticket {$ticket['ticket_number']} has been {$newStatus}.";
        header("Location: /ojt-sdpbus/frontend/admin/review_queue.php");
        exit;

    } catch (Exception $e) {
        error_log("Review Action Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while processing the ticket review.";
        header("Location: /ojt-sdpbus/frontend/admin/review_queue.php");
        exit;
    }
}
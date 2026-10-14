<?php
// backend/reports/close_period.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $startDate = sanitize($_POST['start_date'] ?? '');
    $endDate = sanitize($_POST['end_date'] ?? '');

    if (empty($startDate) || empty($endDate)) {
        $_SESSION['error'] = "Please select both start and end dates to close the billing period.";
        header("Location: /ojt-sdpbus/frontend/admin/billing.php");
        exit;
    }

    try {
        $db = Database::getConnection();

        // Lock all approved tickets in the range
        $stmt = $db->prepare("UPDATE tickets 
                              SET billing_closed = 1 
                              WHERE trip_date BETWEEN :start_date AND :end_date 
                              AND review_status = 'Approved' 
                              AND billing_closed = 0");
        
        $stmt->execute([
            ':start_date' => $startDate,
            ':end_date'   => $endDate
        ]);

        $affectedRows = $stmt->rowCount();

        log_activity($userId, "Close Billing Period", null, "Closed period from {$startDate} to {$endDate}. Locked {$affectedRows} tickets.");

        $_SESSION['success'] = "Billing period closed successfully. Locked {$affectedRows} approved ticket(s).";
        header("Location: /ojt-sdpbus/frontend/admin/billing.php");
        exit;

    } catch (Exception $e) {
        error_log("Close Period Error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred while closing the billing period.";
        header("Location: /ojt-sdpbus/frontend/admin/billing.php");
        exit;
    }
}
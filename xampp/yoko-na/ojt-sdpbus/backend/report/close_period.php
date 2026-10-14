<?php
// backend/reports/close_period.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$submittedToken = (string)($_POST['csrf_token'] ?? '');
$sessionToken = (string)($_SESSION['billing_close_token'] ?? '');
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($sessionToken === '' || !hash_equals($sessionToken, $submittedToken))) {
    $_SESSION['error'] = 'This billing form has expired. Reload the page and review the period again.';
    header('Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $userId = (int)$_SESSION['user_id'];
    $startDate = trim((string)($_POST['start_date'] ?? ''));
    $endDate = trim((string)($_POST['end_date'] ?? ''));
    $expectedTicketCount = (int)($_POST['expected_ticket_count'] ?? -1);
    $parseDate = static function (string $value): ?DateTimeImmutable {
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
        return $date && $date->format('Y-m-d') === $value ? $date : null;
    };
    $parsedStart = $parseDate($startDate);
    $parsedEnd = $parseDate($endDate);

    if (!$parsedStart || !$parsedEnd || $parsedStart > $parsedEnd) {
        $_SESSION['error'] = 'Choose valid start and end dates, with the start date on or before the end date.';
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php?start_date=" . urlencode($startDate) . '&end_date=' . urlencode($endDate));
        exit;
    }

    $db = null;
    try {
        $db = Database::getConnection();
        $db->beginTransaction();

        $overlapStmt = $db->prepare('SELECT id FROM billing_periods
                                     WHERE start_date <= :end_date AND end_date >= :start_date
                                     LIMIT 1');
        $overlapStmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        if ($overlapStmt->fetchColumn()) {
            throw new InvalidArgumentException('This date range overlaps a billing period that has already been closed.');
        }

        $previewStmt = $db->prepare("SELECT COUNT(*) AS ticket_count, COALESCE(SUM(ticket_total), 0) AS total_amount
                                     FROM tickets
                                     WHERE trip_date BETWEEN :start_date AND :end_date
                                       AND review_status = 'Approved' AND billing_closed = 0");
        $previewStmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);
        $preview = $previewStmt->fetch(PDO::FETCH_ASSOC);
        $ticketCount = (int)$preview['ticket_count'];
        if ($ticketCount === 0) {
            throw new InvalidArgumentException('There are no approved, unbilled tickets in this date range to close.');
        }
        if ($expectedTicketCount !== $ticketCount) {
            throw new InvalidArgumentException('The tickets in this range changed since you reviewed them. Review the range again before closing it.');
        }

        $periodStmt = $db->prepare('INSERT INTO billing_periods
            (start_date, end_date, ticket_count, total_amount, closed_by)
            VALUES (:start_date, :end_date, :ticket_count, :total_amount, :closed_by)');
        $periodStmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate,
            ':ticket_count' => $ticketCount,
            ':total_amount' => (float)$preview['total_amount'],
            ':closed_by' => $userId
        ]);

        $lockStmt = $db->prepare("UPDATE tickets SET billing_closed = 1
                                  WHERE trip_date BETWEEN :start_date AND :end_date
                                    AND review_status = 'Approved' AND billing_closed = 0");
        $lockStmt->execute([
            ':start_date' => $startDate,
            ':end_date' => $endDate
        ]);
        if ($lockStmt->rowCount() !== $ticketCount) {
            throw new RuntimeException('The tickets changed while the period was being closed.');
        }

        log_activity($userId, 'Close Billing Period', null, "Closed period from {$startDate} to {$endDate}. Locked {$ticketCount} tickets.");
        $db->commit();

        $_SESSION['success'] = "Billing period closed successfully. Locked {$ticketCount} approved ticket(s).";
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php?start_date=" . urlencode($startDate) . '&end_date=' . urlencode($endDate));
        exit;

    } catch (InvalidArgumentException $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        $_SESSION['error'] = $e->getMessage();
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php?start_date=" . urlencode($startDate) . '&end_date=' . urlencode($endDate));
        exit;
    } catch (Exception $e) {
        if ($db instanceof PDO && $db->inTransaction()) {
            $db->rollBack();
        }
        error_log("Close Period Error: " . $e->getMessage());
        $_SESSION['error'] = 'An error occurred while closing the billing period. No tickets were locked.';
        header("Location: /xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php");
        exit;
    }
}
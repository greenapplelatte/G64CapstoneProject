<?php
// backend/reports/export_csv.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

try {
    $db = Database::getConnection();

    $query = "SELECT t.ticket_number, t.trip_date, t.trip_time, t.shift, 
                     r.route_name, b.bus_code, d.driver_name, u.username as encoded_by,
                     t.forecast_passengers, t.actual_passengers, 
                     t.rate, t.toll, t.ticket_total, t.trip_status, t.review_status,
                     CASE WHEN t.billing_closed = 1 THEN 'Closed' ELSE 'Open' END as billing_status
              FROM tickets t
              LEFT JOIN routes r ON t.route_id = r.id
              LEFT JOIN buses b ON t.bus_id = b.id
              LEFT JOIN drivers d ON t.driver_id = d.id
              LEFT JOIN users u ON t.user_id = u.id
              WHERE 1=1";

    $params = [];

    if (!empty($startDate) && !empty($endDate)) {
        $query .= " AND t.trip_date BETWEEN :start_date AND :end_date";
        $params[':start_date'] = $startDate;
        $params[':end_date'] = $endDate;
    }

    if (!empty($statusFilter)) {
        $query .= " AND t.review_status = :status";
        $params[':status'] = $statusFilter;
    }

    $query .= " ORDER BY t.trip_date DESC, t.id DESC";

    $stmt = $db->prepare($query);
    $stmt->execute($params);
    $rows = $stmt->fetchAll();

    // Set CSV HTTP Headers
    $filename = "sdpbus_report_" . date('Ymd_His') . ".csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');

    $output = fopen('php://output', 'w');

    // CSV Header Row
    fputcsv($output, [
        'Ticket #', 'Date', 'Time', 'Shift', 'Route', 'Bus Code', 
        'Driver', 'Encoded By', 'Forecast Passengers', 'Actual Passengers', 
        'Rate', 'Toll', 'Total', 'Trip Status', 'Review Status', 'Billing Status'
    ]);

    // Populate Rows
    foreach ($rows as $row) {
        fputcsv($output, [
            $row['ticket_number'],
            $row['trip_date'],
            $row['trip_time'],
            $row['shift'],
            $row['route_name'],
            $row['bus_code'],
            $row['driver_name'],
            $row['encoded_by'],
            $row['forecast_passengers'],
            $row['actual_passengers'],
            number_format((float)$row['rate'], 2),
            number_format((float)$row['toll'], 2),
            number_format((float)$row['ticket_total'], 2),
            $row['trip_status'],
            $row['review_status'],
            $row['billing_status']
        ]);
    }

    fclose($output);
    log_activity($_SESSION['user_id'], "Export CSV Report", null, "Exported {$filename}");
    exit;

} catch (Exception $e) {
    error_log("Export CSV Error: " . $e->getMessage());
    $_SESSION['error'] = "Failed to export report.";
    header("Location: /ojt-sdpbus/frontend/admin/billing.php");
    exit;
}
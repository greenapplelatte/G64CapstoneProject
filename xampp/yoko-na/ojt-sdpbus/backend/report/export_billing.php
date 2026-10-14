<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth_check.php';

require_auth(['admin']);

$startDate = (string)($_GET['start_date'] ?? '');
$endDate = (string)($_GET['end_date'] ?? '');
$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
};
$parsedStart = $parseDate($startDate);
$parsedEnd = $parseDate($endDate);

if (!$parsedStart || !$parsedEnd || $parsedStart > $parsedEnd) {
    http_response_code(400);
    header('Content-Type: text/plain; charset=utf-8');
    echo 'A valid billing date range is required.';
    exit;
}

try {
    $db = Database::getConnection();
    $stmt = $db->prepare("SELECT t.ticket_number, t.trip_date, t.trip_time, t.shift,
                                 r.route_name, t.forecast_passengers, t.actual_passengers,
                                 t.ticket_total,
                                 CASE WHEN t.billing_closed = 1 THEN 'Closed' ELSE 'Open' END AS billing_status
                          FROM tickets t
                          JOIN routes r ON r.id = t.route_id
                          WHERE t.review_status = 'Approved'
                            AND t.trip_date BETWEEN :start_date AND :end_date
                          ORDER BY t.trip_date, t.trip_time, t.ticket_number");
    $stmt->execute([':start_date' => $startDate, ':end_date' => $endDate]);

    $filename = "sdpbus_billing_{$startDate}_{$endDate}.csv";
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    header('Cache-Control: no-store, no-cache, must-revalidate');

    $output = fopen('php://output', 'w');
    fputcsv($output, ['Ticket Number', 'Trip Date', 'Trip Time', 'Shift', 'Route', 'Forecast Passengers', 'Actual Passengers', 'Total Fare', 'Billing Status']);

    $safeCsvValue = static function ($value): string {
        $value = (string)($value ?? '');
        return preg_match('/^[\s]*[=+@-]/', $value) === 1 ? "'" . $value : $value;
    };

    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        fputcsv($output, [
            $safeCsvValue($row['ticket_number']),
            $safeCsvValue($row['trip_date']),
            $safeCsvValue($row['trip_time']),
            $safeCsvValue($row['shift']),
            $safeCsvValue($row['route_name']),
            (int)$row['forecast_passengers'],
            (int)$row['actual_passengers'],
            number_format((float)$row['ticket_total'], 2, '.', ''),
            $safeCsvValue($row['billing_status'])
        ]);
    }

    fclose($output);
} catch (Throwable $e) {
    error_log('Billing CSV Export Error: ' . $e->getMessage());
    if (!headers_sent()) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
    }
    echo 'Unable to export billing records.';
}
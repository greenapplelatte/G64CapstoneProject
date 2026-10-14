
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

try {
    $daily = db_all(
        $conn,
        "SELECT
            DATE(created_at) AS day_date,
            SUM(good_count) AS good_units,
            SUM(ng_count) AS ng_units
         FROM production_sessions
         WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 30 DAY)
         GROUP BY DATE(created_at)
         ORDER BY day_date ASC"
    );

    $yield = [];

    foreach ($daily as $row) {
        $good = (int) $row['good_units'];
        $ng = (int) $row['ng_units'];
        $total = $good + $ng;

        if ($total > 0) {
            $yield[] = [
                'day_label' => date(
                    'M d',
                    strtotime($row['day_date'])
                ),
                'yield_value' => round(
                    ($good / $total) * 100,
                    1
                )
            ];
        }
    }

    echo json_encode([
        'status' => 'success',
        'daily_yield' => $yield,
        'factors' => [],
        'reports' => [],
        'source' => 'production_sessions'
    ]);

} catch (Throwable $e) {
    error_log('Manager analytics: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Analytics data is temporarily unavailable.'
    ]);
}

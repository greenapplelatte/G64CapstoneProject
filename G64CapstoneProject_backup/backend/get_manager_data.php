
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

try {
    $totals = db_one(
        $conn,
        "SELECT
            COALESCE(SUM(good_count), 0) AS good_units,
            COALESCE(SUM(ng_count), 0) AS ng_units
         FROM production_sessions"
    );

    $good = (int) ($totals['good_units'] ?? 0);
    $ng = (int) ($totals['ng_units'] ?? 0);
    $total = $good + $ng;

    $metrics = [
        'overall_oee' => null,
        'availability' => null,
        'performance' => null,
        'quality' => $total > 0
            ? round(($good / $total) * 100, 1)
            : null,
        'total_output' => $total,
        'good_units' => $good,
        'ng_units' => $ng
    ];

    $lines = db_all(
        $conn,
        "SELECT
            COALESCE(ms.line_name, 'Unassigned line')
                AS line_name,
            SUM(ps.good_count + ps.ng_count)
                AS total_output,
            CASE
                WHEN SUM(ps.good_count + ps.ng_count) > 0
                THEN ROUND(
                    SUM(ps.good_count) /
                    SUM(ps.good_count + ps.ng_count) * 100,
                    1
                )
                ELSE NULL
            END AS quality
         FROM production_sessions ps
         LEFT JOIN master_stations ms
            ON ms.station_code = ps.machine_id
         GROUP BY COALESCE(ms.line_name, 'Unassigned line')
         ORDER BY line_name"
    );

    echo json_encode([
        'status' => 'success',
        'metrics' => $metrics,
        'lines' => $lines,
        'defects' => [],
        'weekly' => [],
        'period' => 'All recorded production sessions'
    ]);

} catch (Throwable $e) {
    error_log('Manager dashboard: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Live manager metrics are unavailable.'
    ]);
}

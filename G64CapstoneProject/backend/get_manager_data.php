
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
            COALESCE(SUM(ps.good_count), 0) AS good_units,
            COALESCE(SUM(ps.ng_count), 0) AS ng_units
         FROM production_sessions ps
         JOIN (
            SELECT machine_id, MAX(id) AS latest_id
            FROM production_sessions
            GROUP BY machine_id
         ) latest ON latest.latest_id = ps.id"
    );

    $good = (int) ($totals['good_units'] ?? 0);
    $ng = (int) ($totals['ng_units'] ?? 0);
    $total = $good + $ng;
    $target = db_one($conn, "SELECT oee_target_pct FROM master_target_defaults ORDER BY id ASC LIMIT 1");

    $metrics = [
        'overall_oee' => null,
        'availability' => null,
        'performance' => null,
        'oee_target_pct' => (float)($target['oee_target_pct'] ?? 85.0),
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
            SUM(ps.good_count) AS good_units,
            SUM(ps.ng_count) AS ng_units,
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
         JOIN (
            SELECT machine_id, MAX(id) AS latest_id
            FROM production_sessions
            GROUP BY machine_id
         ) latest ON latest.latest_id = ps.id
         LEFT JOIN master_stations ms ON ms.station_code = ps.machine_id
         GROUP BY COALESCE(ms.line_name, 'Unassigned line')
         ORDER BY line_name"
    );
    $lineOutput = array_sum(array_map(static fn($line) => (int)$line['total_output'], $lines));
    foreach ($lines as &$line) {
        $line['share_pct'] = ($lineOutput > 0 ? number_format((int)$line['total_output'] / $lineOutput * 100, 1) : '0.0') . '% of latest-session output';
        $line['availability'] = null;
        $line['performance'] = null;
        $line['oee'] = null;
        $line['is_lowest'] = 0;
    }
    unset($line);

    echo json_encode([
        'status' => 'success',
        'metrics' => $metrics,
        'lines' => $lines,
        'defects' => [],
        'weekly' => [],
        'period' => 'Latest production session per station'
    ]);

} catch (Throwable $e) {
    error_log('Manager dashboard: ' . $e->getMessage());

    http_response_code(500);

    echo json_encode([
        'status' => 'error',
        'message' => 'Live manager metrics are unavailable.'
    ]);
}

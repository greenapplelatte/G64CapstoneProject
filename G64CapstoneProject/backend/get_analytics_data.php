
<?php
header('Content-Type: application/json; charset=utf-8');

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

try {
    $days = filter_var($_GET['days'] ?? '30', FILTER_VALIDATE_INT);
    if (!in_array($days, [7, 30, 90, 365], true)) {
        http_response_code(400);
        throw new InvalidArgumentException('Choose a supported reporting period.');
    }
    $line = trim($_GET['line'] ?? 'all');
    if ($line !== 'all' && !db_one($conn, 'SELECT id FROM master_lines WHERE line_name = ?', 's', [$line])) {
        http_response_code(400);
        throw new InvalidArgumentException('Choose a line that exists in Master Data.');
    }
    $lineFilter = $line === 'all' ? '' : ' AND ms.line_name = ?';
    $lineTypes = $line === 'all' ? '' : 's';
    $from = (new DateTimeImmutable('today'))->modify('-' . ($days - 1) . ' days')->format('Y-m-d');
    $previousFrom = (new DateTimeImmutable($from))->modify('-' . $days . ' days')->format('Y-m-d');
    $previousTo = (new DateTimeImmutable($from))->modify('-1 day')->format('Y-m-d');
    $fetchDaily = static function (string $start, string $end) use ($conn, $line, $lineFilter, $lineTypes): array {
        if ($line === 'all') {
            return db_all($conn,
                "SELECT shift_date AS day_date, SUM(good_units) AS good_units, SUM(ng_units) AS ng_units
                 FROM shift_records
                 WHERE status = 'CLOSED' AND shift_date BETWEEN ? AND ?
                 GROUP BY shift_date ORDER BY shift_date",
                'ss', [$start, $end]);
        }
        $types = 'sss';
        $params = [$start, $end, $line];
        return db_all($conn,
            "SELECT sr.shift_date AS day_date,
                    SUM(w.output_units) AS good_units,
                    SUM(w.ng_units) AS ng_units
             FROM shift_records sr
             JOIN shift_workstation_yield w ON w.shift_record_id = sr.id
             LEFT JOIN master_stations ms ON ms.station_code = w.station_code
             WHERE sr.status = 'CLOSED' AND sr.shift_date BETWEEN ? AND ? $lineFilter
             GROUP BY sr.shift_date ORDER BY sr.shift_date",
            $types, $params);
    };
    $daily = $fetchDaily($from, date('Y-m-d'));
    $previousDaily = $fetchDaily($previousFrom, $previousTo);
    $toYield = static function (array $rows): array {
        $result = [];
        foreach ($rows as $row) {
            $good = (int)$row['good_units'];
            $ng = (int)$row['ng_units'];
            $total = $good + $ng;
            if ($total > 0) {
                $result[] = [
                    'day_label' => date('M d', strtotime($row['day_date'])),
                    'yield_value' => round($good / $total * 100, 1),
                    'good_units' => $good,
                    'ng_units' => $ng
                ];
            }
        }
        return $result;
    };
    $yield = $toYield($daily);
    $currentGood = array_sum(array_column($daily, 'good_units'));
    $currentNg = array_sum(array_column($daily, 'ng_units'));
    $previousGood = array_sum(array_column($previousDaily, 'good_units'));
    $previousNg = array_sum(array_column($previousDaily, 'ng_units'));
    $currentYield = $currentGood + $currentNg > 0 ? round($currentGood / ($currentGood + $currentNg) * 100, 1) : null;
    $previousYield = $previousGood + $previousNg > 0 ? round($previousGood / ($previousGood + $previousNg) * 100, 1) : null;

    $defectTypes = db_all($conn, "SELECT category_name AS name FROM defect_categories ORDER BY category_name");
    $requestedDefect = trim($_GET['defect_type'] ?? '');
    $selectedDefect = $requestedDefect !== '' ? $requestedDefect : ($defectTypes[0]['name'] ?? '');
    if ($selectedDefect !== '' && !in_array($selectedDefect, array_column($defectTypes, 'name'), true)) {
        http_response_code(400);
        throw new InvalidArgumentException('Choose a defect type managed in Master Data.');
    }
    $factors = [];
    if ($selectedDefect !== '') {
        $factorParams = [$from, date('Y-m-d'), $selectedDefect];
        if ($line !== 'all') $factorParams[] = $line;
        $defectByLine = db_all($conn,
            "SELECT COALESCE(ms.workstation_name, dr.workstation, 'Unknown workstation') AS factor_name,
                    SUM(dr.quantity_rejected) AS defect_units
             FROM defect_reports dr
             LEFT JOIN master_stations ms ON ms.station_code = dr.station
             WHERE dr.status = 'Confirmed' AND DATE(dr.created_at) BETWEEN ? AND ?
               AND dr.defect_category = ? $lineFilter
             GROUP BY COALESCE(ms.workstation_name, dr.workstation, 'Unknown workstation')
             ORDER BY defect_units DESC",
            'sss' . $lineTypes, $factorParams);
        $outputParams = [$from, date('Y-m-d')];
        if ($line !== 'all') $outputParams[] = $line;
        $outputByLine = db_all($conn,
            "SELECT COALESCE(ms.workstation_name, w.workstation_name, 'Unknown workstation') AS workstation_name,
                    SUM(w.output_units + w.ng_units) AS output_units
             FROM shift_records sr
             JOIN shift_workstation_yield w ON w.shift_record_id = sr.id
             LEFT JOIN master_stations ms ON ms.station_code = w.station_code
             WHERE sr.status = 'CLOSED' AND sr.shift_date BETWEEN ? AND ? $lineFilter
             GROUP BY COALESCE(ms.workstation_name, w.workstation_name, 'Unknown workstation')",
            'ss' . $lineTypes, $outputParams);
        $outputMap = [];
        $allOutput = 0;
        foreach ($outputByLine as $outputRow) {
            $outputMap[$outputRow['workstation_name']] = (int)$outputRow['output_units'];
            $allOutput += (int)$outputRow['output_units'];
        }
        $allDefects = array_sum(array_column($defectByLine, 'defect_units'));
        foreach ($defectByLine as $row) {
            $defectShare = $allDefects > 0 ? round((int)$row['defect_units'] / $allDefects * 100, 1) : 0;
            $outputShare = $allOutput > 0 ? round(($outputMap[$row['factor_name']] ?? 0) / $allOutput * 100, 1) : 0;
            $factors[] = [
                'factor_name' => $row['factor_name'],
                'factor_category' => 'Workstation',
                'multiplier' => $outputShare > 0 ? number_format($defectShare / $outputShare, 1) . 'x' : '—',
                'defect_share_pct' => $defectShare,
                'output_share_pct' => $outputShare
            ];
        }
    }
    $lines = db_all($conn, 'SELECT line_name FROM master_lines ORDER BY line_name');
    $targetDefaults = db_one($conn, 'SELECT yield_target_pct FROM master_target_defaults WHERE id = 1');
    if (!$targetDefaults) {
        throw new RuntimeException('Yield target is not configured in Master Data.');
    }

    echo json_encode([
        'status' => 'success',
        'daily_yield' => $yield,
        'factors' => $factors,
        'defect_types' => $defectTypes,
        'lines' => $lines,
        'comparison' => ['current_yield' => $currentYield, 'previous_yield' => $previousYield],
        'days' => $days,
        'line' => $line,
        'selected_defect_type' => $selectedDefect,
        'yield_target_pct' => (float)$targetDefaults['yield_target_pct'],
        'source' => 'closed shifts and confirmed defect reports'
    ]);

} catch (Throwable $e) {
    error_log('Manager analytics: ' . $e->getMessage());

    if (http_response_code() < 400) {
        http_response_code(500);
    }

    echo json_encode([
        'status' => 'error',
        'message' => $e instanceof InvalidArgumentException
            ? $e->getMessage()
            : 'Analytics data is temporarily unavailable.'
    ]);
}

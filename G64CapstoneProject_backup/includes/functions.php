<?php
// includes/functions.php
// Shared helpers for the production-counting chain (counter -> station -> shift -> hourly -> logs).
date_default_timezone_set('Asia/Manila');

/* ---------- small DB helpers (prepared statements) ---------- */

function db_one(mysqli $conn, string $sql, string $types = '', array $params = []) {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    return $row ?: null;
}

/** Every matching row (prepared statement). Returns [] when nothing matches. */
function db_all(mysqli $conn, string $sql, string $types = '', array $params = []): array {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
    return $rows;
}

function db_run(mysqli $conn, string $sql, string $types = '', array $params = []): int {
    $stmt = $conn->prepare($sql);
    if ($types !== '') {
        $stmt->bind_param($types, ...$params);
    }
    $stmt->execute();
    $affected = $stmt->affected_rows;
    $stmt->close();
    return $affected;
}

function json_out(array $payload, int $http = 200): void {
    http_response_code($http);
    echo json_encode($payload);
    exit;
}

function yield_pct($good, $ng, int $decimals = 1) {
    $total = (int)$good + (int)$ng;
    return $total > 0 ? round(((int)$good / $total) * 100, $decimals) : 100.0;
}

/* ---------- device heartbeat ---------- */

const DEVICE_OFFLINE_AFTER_SEC = 60;

/** Record that a station's device just contacted the server. False if the station does not exist. */
function touch_station(mysqli $conn, string $station_code): bool {
    db_run($conn, "UPDATE master_stations SET last_seen = NOW() WHERE station_code = ?", "s", [$station_code]);
    return db_one($conn, "SELECT id FROM master_stations WHERE station_code = ?", "s", [$station_code]) !== null;
}

/** True when the station's device has been heard from within DEVICE_OFFLINE_AFTER_SEC seconds. */
function station_is_online(mysqli $conn, string $station_code): bool {
    $r = db_one($conn,
        "SELECT TIMESTAMPDIFF(SECOND, last_seen, NOW()) AS age FROM master_stations WHERE station_code = ?",
        "s", [$station_code]);
    return $r !== null && $r['age'] !== null && (int)$r['age'] <= DEVICE_OFFLINE_AFTER_SEC;
}

/* ---------- line session / shift lookups ---------- */

function get_line_session(mysqli $conn) {
    return db_one($conn, "SELECT * FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1");
}

function get_open_shift(mysqli $conn) {
    return db_one($conn, "SELECT * FROM shift_records WHERE status = 'OPEN' ORDER BY id DESC LIMIT 1");
}

function floor_alert(mysqli $conn, string $type, string $title, string $subtitle = ''): void {
    $time = date('h:i A');
    db_run($conn,
        "INSERT INTO supervisor_floor_alerts (alert_type, title, subtitle, alert_time) VALUES (?, ?, ?, ?)",
        "ssss", [$type, $title, $subtitle, $time]);
}

/**
 * Staff activity log. $staff_id is optional: when omitted, the staff member
 * assigned to $station_code is used (if there is one).
 */
function log_activity(mysqli $conn, string $station_code, string $type, string $details, ?int $staff_id = null): void {
    if ($staff_id === null) {
        $u = db_one($conn,
            "SELECT id FROM users WHERE assigned_station = ? AND role = 'staff' ORDER BY id LIMIT 1",
            "s", [$station_code]);
        if (!$u) {
            return;
        }
        $staff_id = (int)$u['id'];
    }
    $time = date('H:i:s');
    try {
        db_run($conn,
            "INSERT INTO staff_activity_logs (staff_id, log_date, log_time, log_type, details, station_id) VALUES (?, ?, ?, ?, ?, ?)",
            "isssss", [$staff_id, date('Y-m-d'), $time, $type, $details, $station_code]);
    } catch (mysqli_sql_exception $e) {
        // group4_migration.sql not run yet: keep counting working, just without a date
        db_run($conn,
            "INSERT INTO staff_activity_logs (staff_id, log_time, log_type, details, station_id) VALUES (?, ?, ?, ?, ?)",
            "issss", [$staff_id, $time, $type, $details, $station_code]);
    }
}

/* ---------- counter chain ---------- */

/** One production_sessions row per station (created on demand). */
function ensure_production_session(mysqli $conn, string $station_code) {
    $row = db_one($conn,
        "SELECT * FROM production_sessions WHERE machine_id = ? ORDER BY id DESC LIMIT 1",
        "s", [$station_code]);
    if ($row) {
        return $row;
    }
    $st = db_one($conn, "SELECT target_units FROM supervisor_station_status WHERE station_code = ?", "s", [$station_code]);
    $target = $st ? (int)$st['target_units'] : 120;
    db_run($conn,
        "INSERT INTO production_sessions (machine_id, target_goal, status) VALUES (?, ?, 'active')",
        "si", [$station_code, $target]);
    return db_one($conn, "SELECT * FROM production_sessions WHERE id = ?", "i", [$conn->insert_id]);
}

/** Why counting must be refused right now (null = counting allowed). */
function counting_block_reason(mysqli $conn, string $station_code) {
    $line = get_line_session($conn);
    if (!$line) {
        return 'No line session has been opened.';
    }
    if ($line['status'] !== 'RUNNING') {
        return 'Line is ' . strtolower($line['status']) . '.';
    }
    $st = db_one($conn, "SELECT status FROM supervisor_station_status WHERE station_code = ?", "s", [$station_code]);
    if (!$st) {
        return 'Unknown station.';
    }
    if ($st['status'] !== 'RUNNING') {
        return 'Station is paused.';
    }
    return null;
}

/** Recompute a shift's totals from its per-workstation rows. */
function recalc_shift(mysqli $conn, int $shift_id): void {
    $t = db_one($conn,
        "SELECT COALESCE(SUM(output_units),0) AS g, COALESCE(SUM(ng_units),0) AS n, COALESCE(SUM(pending_units),0) AS p
         FROM shift_workstation_yield WHERE shift_record_id = ?",
        "i", [$shift_id]);
    $g = (int)$t['g'];
    $n = (int)$t['n'];
    $p = (int)$t['p'];
    $produced = $g + $n;
    $rate = yield_pct($g, $n, 2);
    $pct  = yield_pct($g, $n, 1);
    db_run($conn,
        "UPDATE shift_records
         SET good_units = ?, ng_units = ?, rejected_units = ?, pending_units = ?, produced_units = ?, yield_rate = ?, yield_pct = ?
         WHERE id = ?",
        "iiiiiddi", [$g, $n, $n, $p, $produced, $rate, $pct, $shift_id]);
}

/** Adjust one workstation row of a shift (creating it if needed), then refresh the shift. */
function ws_adjust(mysqli $conn, int $shift_id, string $station_code, int $dGood, int $dNg, int $dPending): void {
    $row = db_one($conn,
        "SELECT id FROM shift_workstation_yield WHERE shift_record_id = ? AND station_code = ? LIMIT 1",
        "is", [$shift_id, $station_code]);
    if ($row) {
        $ws_id = (int)$row['id'];
    } else {
        $nm = db_one($conn, "SELECT workstation_name FROM supervisor_station_status WHERE station_code = ?", "s", [$station_code]);
        $name = $nm['workstation_name'] ?? $station_code;
        db_run($conn,
            "INSERT INTO shift_workstation_yield (shift_record_id, station_code, workstation_name) VALUES (?, ?, ?)",
            "iss", [$shift_id, $station_code, $name]);
        $ws_id = (int)$conn->insert_id;
    }

    db_run($conn,
        "UPDATE shift_workstation_yield
         SET output_units  = GREATEST(0, output_units + ?),
             ng_units      = GREATEST(0, ng_units + ?),
             pending_units = GREATEST(0, pending_units + ?)
         WHERE id = ?",
        "iiii", [$dGood, $dNg, $dPending, $ws_id]);

    db_run($conn,
        "UPDATE shift_workstation_yield
         SET yield_pct = IF(output_units + ng_units > 0, ROUND(output_units / (output_units + ng_units) * 100, 1), 100.0)
         WHERE id = ?",
        "i", [$ws_id]);

    // flag the lowest-yield workstation (only when something is actually below 100%)
    db_run($conn,
        "UPDATE shift_workstation_yield w
         JOIN (SELECT MIN(yield_pct) AS m FROM shift_workstation_yield WHERE shift_record_id = ?) x
         SET w.is_lowest = IF(x.m < 100 AND w.yield_pct = x.m, 1, 0)
         WHERE w.shift_record_id = ?",
        "ii", [$shift_id, $shift_id]);

    recalc_shift($conn, $shift_id);
}

/** Add to the current hour's bar on the supervisor dashboard (today only). */
function hourly_adjust(mysqli $conn, int $dGood, int $dNg): void {
    $label = date('g A');
    $today = date('Y-m-d');
    db_run($conn, "DELETE FROM supervisor_hourly_output WHERE log_date IS NULL OR log_date <> ?", "s", [$today]);
    $row = db_one($conn,
        "SELECT id FROM supervisor_hourly_output WHERE hour_label = ? AND log_date = ? LIMIT 1",
        "ss", [$label, $today]);
    if ($row) {
        db_run($conn,
            "UPDATE supervisor_hourly_output SET good_units = good_units + ?, ng_units = ng_units + ? WHERE id = ?",
            "iii", [$dGood, $dNg, (int)$row['id']]);
    } else {
        db_run($conn,
            "INSERT INTO supervisor_hourly_output (hour_label, good_units, ng_units, log_date) VALUES (?, ?, ?, ?)",
            "siis", [$label, $dGood, $dNg, $today]);
    }
}

/**
 * The one place that changes production numbers. Updates, in sync:
 * production_sessions, supervisor_station_status, the open shift (+ its workstation row), hourly output.
 * Returns the station's production_sessions row (id, good_count, ng_count).
 * Call inside a transaction.
 */
function apply_counts(mysqli $conn, string $station_code, int $dGood, int $dNg, int $dPending = 0): array {
    $ps = ensure_production_session($conn, $station_code);
    $ps_id = (int)$ps['id'];

    db_run($conn,
        "UPDATE production_sessions SET good_count = good_count + ?, ng_count = ng_count + ? WHERE id = ?",
        "iii", [$dGood, $dNg, $ps_id]);

    db_run($conn,
        "UPDATE supervisor_station_status SET good_units = good_units + ?, ng_units = ng_units + ? WHERE station_code = ?",
        "iis", [$dGood, $dNg, $station_code]);

    $shift = get_open_shift($conn);
    if ($shift) {
        ws_adjust($conn, (int)$shift['id'], $station_code, $dGood, $dNg, $dPending);
    }

    if ($dGood !== 0 || $dNg !== 0) {
        hourly_adjust($conn, $dGood, $dNg);
    }

    return db_one($conn, "SELECT id, good_count, ng_count FROM production_sessions WHERE id = ?", "i", [$ps_id]);
}

/* ---------- OEE / yield roll-ups (called when a line session closes) ---------- */

/**
 * Rebuilds the manager-dashboard OEE tables from real shifts.
 * Window: CLOSED shifts of the last 7 days that have run_minutes saved (i.e. closed by the app).
 *   Availability = run minutes / planned minutes
 *   Performance  = units produced / (rated units per hour x run hours), capped at 100%
 *   Quality      = good / (good + NG)
 *   OEE          = A x P x Q
 * Lines with no real shift in the window keep their previous row.
 */
function compute_oee(mysqli $conn): void {
    $from = date('Y-m-d', strtotime('-6 days'));

    // one row per (shift, station) with the station's line and rated speed
    $rows = $conn->prepare(
        "SELECT s.id AS shift_id, s.planned_minutes, s.run_minutes,
                w.station_code, w.output_units, w.ng_units,
                COALESCE(m.line_name, 'Line 1') AS line_name,
                COALESCE(m.rate_per_hr, 120)    AS rate_per_hr
         FROM shift_records s
         JOIN shift_workstation_yield w ON w.shift_record_id = s.id
         LEFT JOIN master_stations m ON m.station_code = w.station_code
         WHERE s.status = 'CLOSED' AND s.run_minutes IS NOT NULL AND s.planned_minutes > 0
           AND s.shift_date >= ?");
    $rows->bind_param('s', $from);
    $rows->execute();
    $data = $rows->get_result()->fetch_all(MYSQLI_ASSOC);
    $rows->close();
    if (!$data) {
        return;
    }

    $mk = fn() => ['good' => 0, 'ng' => 0, 'ideal' => 0.0, 'planned' => 0.0, 'run' => 0.0, 'shifts' => []];
    $lines = [];
    $plant = $mk();
    foreach ($data as $r) {
        $name = $r['line_name'];
        if (!isset($lines[$name])) {
            $lines[$name] = $mk();
        }
        foreach ([$name, null] as $key) {              // null = the whole plant
            $a = ($key === null) ? $plant : $lines[$key];
            $a['good']  += (int)$r['output_units'];
            $a['ng']    += (int)$r['ng_units'];
            $a['ideal'] += (int)$r['rate_per_hr'] * ((int)$r['run_minutes'] / 60);
            if (!isset($a['shifts'][$r['shift_id']])) {          // count each shift's time once
                $a['shifts'][$r['shift_id']] = true;
                $a['planned'] += (int)$r['planned_minutes'];
                $a['run']     += (int)$r['run_minutes'];
            }
            if ($key === null) { $plant = $a; } else { $lines[$key] = $a; }
        }
    }

    $calc = function (array $a): array {
        $total = $a['good'] + $a['ng'];
        $avail = $a['planned'] > 0 ? min(1, $a['run'] / $a['planned']) : 0;
        $perf  = $a['ideal'] > 0 ? min(1, $total / $a['ideal']) : 0;
        $qual  = $total > 0 ? $a['good'] / $total : 1;
        return [
            'total' => $total,
            'a' => round($avail * 100, 1), 'p' => round($perf * 100, 1), 'q' => round($qual * 100, 1),
            'oee' => round($avail * $perf * $qual * 100, 1),
        ];
    };

    $conn->begin_transaction();
    try {
        // per line: update the existing row (by name) or add one
        foreach ($lines as $name => $a) {
            $c = $calc($a);
            $ex = db_one($conn, "SELECT id FROM oee_line_performance WHERE line_name = ?", "s", [$name]);
            if ($ex) {
                db_run($conn,
                    "UPDATE oee_line_performance SET total_output=?, availability=?, performance=?, quality=?, oee=? WHERE id=?",
                    "iddddi", [$c['total'], $c['a'], $c['p'], $c['q'], $c['oee'], (int)$ex['id']]);
            } else {
                db_run($conn,
                    "INSERT INTO oee_line_performance (line_name, total_output, availability, performance, quality, oee) VALUES (?,?,?,?,?,?)",
                    "sidddd", [$name, $c['total'], $c['a'], $c['p'], $c['q'], $c['oee']]);
            }
        }
        // share of output + lowest-OEE flag across all line rows
        $all = db_all($conn, "SELECT id, total_output, oee FROM oee_line_performance");
        $sum = array_sum(array_column($all, 'total_output'));
        $min = $all ? min(array_column($all, 'oee')) : 0;
        foreach ($all as $l) {
            $share = $sum > 0 ? number_format($l['total_output'] / $sum * 100, 1) . '% of output' : '0.0% of output';
            db_run($conn, "UPDATE oee_line_performance SET share_pct=?, is_lowest=? WHERE id=?",
                   "sii", [$share, ($l['oee'] <= $min && count($all) > 1) ? 1 : 0, (int)$l['id']]);
        }

        // plant headline
        $p = $calc($plant);
        db_run($conn,
            "INSERT INTO oee_plant_metrics (overall_oee, availability, performance, quality, total_output, good_units, ng_units)
             VALUES (?,?,?,?,?,?,?)",
            "ddddiii", [$p['oee'], $p['a'], $p['p'], $p['q'], $p['total'], $plant['good'], $plant['ng']]);
        db_run($conn, "DELETE FROM oee_plant_metrics WHERE id < (SELECT m FROM (SELECT MAX(id) - 29 AS m FROM oee_plant_metrics) t)");

        // weekly trend point for the current ISO week
        $wk = 'Wk ' . (int)date('W');
        $ex = db_one($conn, "SELECT id FROM oee_weekly_trend WHERE week_label = ?", "s", [$wk]);
        if ($ex) {
            db_run($conn, "UPDATE oee_weekly_trend SET oee_value = ? WHERE id = ?", "di", [$p['oee'], (int)$ex['id']]);
        } else {
            db_run($conn, "INSERT INTO oee_weekly_trend (week_label, oee_value) VALUES (?, ?)", "sd", [$wk, $p['oee']]);
        }
        db_run($conn, "DELETE FROM oee_weekly_trend WHERE id < (SELECT m FROM (SELECT MAX(id) - 7 AS m FROM oee_weekly_trend) t)");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        throw $e;
    }
}

/** Overall yield for one calendar day (all shifts of that date) -> analytics_daily_yield. */
function rollup_daily_yield(mysqli $conn, string $date): void {
    $t = db_one($conn,
        "SELECT COALESCE(SUM(good_units),0) AS g, COALESCE(SUM(ng_units),0) AS n
         FROM shift_records WHERE shift_date = ? AND status = 'CLOSED'",
        "s", [$date]);
    if (!$t || ((int)$t['g'] + (int)$t['n']) === 0) {
        return;
    }
    $label = date('M d', strtotime($date));
    $y = yield_pct($t['g'], $t['n'], 1);
    $ex = db_one($conn, "SELECT id FROM analytics_daily_yield WHERE day_label = ?", "s", [$label]);
    if ($ex) {
        db_run($conn, "UPDATE analytics_daily_yield SET yield_value = ? WHERE id = ?", "di", [$y, (int)$ex['id']]);
    } else {
        db_run($conn, "INSERT INTO analytics_daily_yield (day_label, yield_value) VALUES (?, ?)", "sd", [$label, $y]);
    }
    db_run($conn, "DELETE FROM analytics_daily_yield WHERE id < (SELECT m FROM (SELECT MAX(id) - 29 AS m FROM analytics_daily_yield) t)");
}
?>
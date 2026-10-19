<?php
// backend/process_line_control.php
// Line & Session Control (supervisor).
// Actions: get_session_data, toggle_session, toggle_station, open_session, save_targets
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('supervisor');

$action  = $_GET['action'] ?? '';
$user_id = (int)$_SESSION['user_id'];

/* ---------- local helpers ---------- */

/** Latest line session, row-locked. Call inside a transaction. */
function lock_line_session(mysqli $conn) {
    return db_one($conn, "SELECT * FROM supervisor_line_sessions ORDER BY id DESC LIMIT 1 FOR UPDATE");
}

/** Reports the supervisor still has to decide on (Pending + Recheck Requested). */
function count_unreviewed_defects(mysqli $conn): int {
    $r = db_one($conn, "SELECT COUNT(*) AS n FROM defect_reports WHERE status IN ('Pending', 'Recheck Requested')");
    return (int)($r['n'] ?? 0);
}

/** Whole hours + minutes since the session started (until it closed, if closed). */
function session_elapsed(array $line): array {
    if (empty($line['started_at'])) {
        return [(int)$line['elapsed_hours'], 0];
    }
    $start = strtotime($line['started_at']);
    $end   = (!empty($line['closed_at'])) ? strtotime($line['closed_at']) : time();
    $mins  = max(0, (int)floor(($end - $start) / 60));
    return [intdiv($mins, 60), $mins % 60];
}

/** Which configured shift owns the current clock time: [title, start, end, range label, shift date]. */
function current_shift_slot(mysqli $conn): ?array {
    $manilaNow = new DateTimeImmutable('now', new DateTimeZone('Asia/Manila'));
    $now = $manilaNow->format('H:i:s');
    $shifts = db_all($conn, "SELECT shift_name, start_time, end_time
                             FROM master_shift_schedule
                             WHERE status = 'Scheduled' AND start_time IS NOT NULL AND end_time IS NOT NULL
                             ORDER BY start_time");
    foreach ($shifts as $shift) {
        $start = (string)$shift['start_time'];
        $end = (string)$shift['end_time'];
        $overnight = $start > $end;
        $matches = $overnight ? ($now >= $start || $now < $end) : ($now >= $start && $now < $end);
        if (!$matches) continue;

        $shiftDate = ($overnight && $now < $end)
            ? $manilaNow->modify('-1 day')->format('Y-m-d')
            : $manilaNow->format('Y-m-d');
        $range = date('h:i A', strtotime($start)) . ' – ' . date('h:i A', strtotime($end));
        return [(string)$shift['shift_name'], $start, $end, $range, $shiftDate];
    }
    return null;
}

/** Validate a positive whole number from POST. Returns null if invalid. */
function post_positive_int(string $key, int $max = 100000): ?int {
    $v = $_POST[$key] ?? '';
    if (!is_string($v) && !is_int($v)) {
        return null;
    }
    $v = trim((string)$v);
    if ($v === '' || !ctype_digit($v)) {
        return null;
    }
    $n = (int)$v;
    return ($n >= 1 && $n <= $max) ? $n : null;
}

/** Read a currently active project type from POST. */
function post_product_name(mysqli $conn): ?string {
    $p = trim($_POST['product_name'] ?? '');
    if ($p === '' || mb_strlen($p) > 100) return null;
    try {
        return db_one($conn, 'SELECT project_type FROM master_project_types WHERE project_type = ? AND is_active = 1', 's', [$p])
            ? $p
            : null;
    } catch (mysqli_sql_exception $e) {
        error_log('Line-control project validation failed: ' . $e->getMessage());
        json_out(['status'=>'error','message'=>'Master Data needs a database update before products can be selected.'], 503);
    }
}

if ($action !== 'get_session_data') {
    require_post('POST required');
}

/* ====================================================================
   GET: everything the Line Control page shows
   ==================================================================== */
if ($action === 'get_session_data') {
    $line = get_line_session($conn);

    $session = null;
    if ($line) {
        [$eh, $em] = session_elapsed($line);
        $session = [
            'id'              => (int)$line['id'],
            'session_code'    => $line['session_code'],
            'status'          => $line['status'],
            'product_name'    => $line['product_name'],
            'start_time'      => $line['start_time'],
            'elapsed_hours'   => $eh,
            'elapsed_minutes' => $em,
            'opened_by'       => $line['opened_by'],
            'line_target'     => (int)$line['line_target'],
            'station_target'  => (int)$line['station_target'],
            'pause_reason'    => $line['pause_reason'],
        ];
    }

    // Staff identity and assignment come exclusively from active user accounts,
    // never from the legacy supervisor_station_status.assigned_staff seed column.
    $stations = db_all($conn, "SELECT st.station_code, st.workstation_name, st.status,
                                      st.good_units, st.ng_units, st.target_units,
                                      ms.line_name, ms.status AS master_status,
                                      COALESCE(operators.names, '') AS assigned_staff,
                                      COALESCE(operators.operator_count, 0) AS operator_count
                               FROM supervisor_station_status st
                               LEFT JOIN master_stations ms ON ms.station_code = st.station_code
                               LEFT JOIN (
                                   SELECT assigned_station,
                                          GROUP_CONCAT(full_name ORDER BY full_name SEPARATOR ', ') AS names,
                                          COUNT(*) AS operator_count
                                   FROM users
                                   WHERE role = 'staff' AND status = 'Active'
                                     AND assigned_station IS NOT NULL AND assigned_station <> ''
                                   GROUP BY assigned_station
                               ) operators ON operators.assigned_station = st.station_code
                               ORDER BY st.id ASC");

    json_out([
        'status'          => 'success',
        'session'         => $session,
        'stations'        => $stations,
        'assigned_count'  => array_sum(array_map(static fn($s) => (int)$s['operator_count'], $stations)),
        'station_count'   => count($stations),
        'pending_defects' => count_unreviewed_defects($conn),
    ]);
}

/* ====================================================================
   POST: pause / resume / close the whole line
   ==================================================================== */
if ($action === 'toggle_session') {
    $new_status = $_POST['status'] ?? '';
    if (!in_array($new_status, ['RUNNING', 'PAUSED', 'CLOSED'], true)) {
        json_out(['status' => 'error', 'message' => 'Invalid status.'], 400);
    }
    $reason = mb_substr(trim($_POST['pause_reason'] ?? ''), 0, 100);
    $closeout_comment = mb_substr(trim($_POST['closeout_comment'] ?? ''), 0, 2000);
    $closed_shift_date = null;
    $closed_shift_id = null;
    $closed_session_code = null;

    try {
        $conn->begin_transaction();

        $line = lock_line_session($conn);
        if (!$line) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'No line session has been opened.'], 404);
        }
        if ($line['status'] === 'CLOSED') {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'This session is already closed. Start a new session instead.'], 409);
        }
        if ($line['status'] === $new_status) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'The line is already ' . strtolower($new_status) . '.'], 409);
        }

        $sid = (int)$line['id'];

        if ($new_status === 'PAUSED') {
            db_run($conn,
                "UPDATE supervisor_line_sessions SET status = 'PAUSED', pause_reason = ?, paused_at = ? WHERE id = ?",
                "ssi", [$reason, date('Y-m-d H:i:s'), $sid]);
            db_run($conn, "UPDATE production_sessions SET status = 'paused' WHERE status = 'active'");
            floor_alert($conn, 'warning', 'Line paused', $reason !== '' ? $reason : 'Paused by supervisor');
            $msg = 'Line paused.';

        } elseif ($new_status === 'RUNNING') {
            // add the pause that just ended to the session's downtime (all times in PHP's timezone)
            $paused_secs = (int)$line['paused_seconds'];
            if (!empty($line['paused_at'])) {
                $paused_secs += max(0, time() - strtotime($line['paused_at']));
            }
            db_run($conn,
                "UPDATE supervisor_line_sessions
                 SET status = 'RUNNING', pause_reason = '', paused_seconds = ?, paused_at = NULL
                 WHERE id = ?",
                "ii", [$paused_secs, $sid]);
            db_run($conn,
                "UPDATE production_sessions ps
                 JOIN supervisor_station_status st ON st.station_code = ps.machine_id
                 JOIN master_stations ms ON ms.station_code = st.station_code
                 SET ps.status = 'active'
                 WHERE ps.status = 'paused' AND st.status = 'RUNNING' AND ms.status = 'Online'");
            floor_alert($conn, 'info', 'Line resumed', $line['session_code']);
            $msg = 'Line resumed.';

        } else { // CLOSED
            $pending = count_unreviewed_defects($conn);
            if ($pending > 0) {
                $conn->rollback();
                json_out(['status' => 'error',
                          'message' => "$pending defect report(s) still need review before the session can be closed."], 409);
            }

            $now = date('Y-m-d H:i:s');
            // Downtime = every pause, including one still open at the moment of closing
            $paused_secs = (int)$line['paused_seconds'];
            if (!empty($line['paused_at'])) {
                $paused_secs += max(0, strtotime($now) - strtotime($line['paused_at']));
            }
            db_run($conn,
                "UPDATE supervisor_line_sessions
                 SET status = 'CLOSED', pause_reason = '', closed_at = ?, paused_at = NULL, paused_seconds = ? WHERE id = ?",
                "sii", [$now, $paused_secs, $sid]);
            db_run($conn, "UPDATE production_sessions SET status = 'completed' WHERE status <> 'completed'");

            $shift = get_open_shift($conn);
            if (!$shift) {
                $conn->rollback();
                json_out(['status' => 'error', 'message' => 'No open shift record is linked to this session. Closeout was cancelled to avoid losing shift history.'], 409);
            }
            if (!empty($shift['session_id']) && (int)$shift['session_id'] !== $sid) {
                $conn->rollback();
                json_out(['status' => 'error', 'message' => 'The open shift belongs to a different production session. Please resolve the shift assignment first.'], 409);
            }
            if ($shift) {
                recalc_shift($conn, (int)$shift['id']);
                $planned = !empty($line['started_at'])
                    ? max(0, (int)floor((strtotime($now) - strtotime($line['started_at'])) / 60))
                    : ((int)$line['elapsed_hours'] * 60);
                $run = max(0, $planned - (int)floor($paused_secs / 60));
                db_run($conn,
                    "UPDATE shift_records SET status = 'CLOSED', session_id = ?, planned_minutes = ?, run_minutes = ?, closeout_comment = ? WHERE id = ?",
                    "iiisi", [$sid, $planned, $run, $closeout_comment, (int)$shift['id']]);
                $closed_shift_id = (int)$shift['id'];
                $closed_shift_date = $shift['shift_date'];
                $closed_session_code = $line['session_code'];
            }
            floor_alert($conn, 'info', 'Session closed', $line['session_code']);
            $msg = 'Session closed and shift record saved.';
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to update the session.'], 500);
    }

    // Roll the closed shift up into the manager dashboard / analytics tables.
    // Done after the commit so a roll-up problem can never block closing the session.
    if ($new_status === 'CLOSED' && $closed_shift_date !== null) {
        try {
            compute_oee($conn);
            rollup_daily_yield($conn, $closed_shift_date);
        } catch (Throwable $e) {
            error_log('OEE roll-up failed: ' . $e->getMessage());
        }
    }

    json_out(['status' => 'success', 'message' => $msg, 'shift_id' => $closed_shift_id, 'session_code' => $closed_session_code]);
}

/* ====================================================================
   POST: pause / resume one station
   ==================================================================== */
if ($action === 'toggle_station') {
    $code       = trim($_POST['station_code'] ?? '');
    $new_status = $_POST['status'] ?? '';
    if ($code === '' || !in_array($new_status, ['RUNNING', 'PAUSED'], true)) {
        json_out(['status' => 'error', 'message' => 'Invalid station or status.'], 400);
    }

    try {
        $conn->begin_transaction();

        $line = lock_line_session($conn);
        if (!$line || $line['status'] === 'CLOSED') {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'There is no open session.'], 409);
        }

        $st = db_one($conn, "SELECT st.*, ms.status AS master_status FROM supervisor_station_status st
                             JOIN master_stations ms ON ms.station_code = st.station_code
                             WHERE st.station_code = ? FOR UPDATE", "s", [$code]);
        if (!$st) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'Unknown station.'], 404);
        }
        if ($st['status'] === $new_status) {
            $conn->rollback();
            json_out(['status' => 'success', 'message' => 'Station is already ' . strtolower($new_status) . '.']);
        }
        if ($new_status === 'RUNNING' && $st['master_status'] === 'Maintenance') {
            $conn->rollback();
            json_out(['status'=>'error','message'=>'This station is under maintenance and cannot be resumed.'], 409);
        }

        db_run($conn, "UPDATE supervisor_station_status SET status = ? WHERE station_code = ?", "ss", [$new_status, $code]);
        if ($new_status === 'PAUSED') {
            db_run($conn, "UPDATE production_sessions SET status = 'paused' WHERE machine_id = ? AND status = 'active'", "s", [$code]);
        } elseif ($line['status'] === 'RUNNING') {
            db_run($conn, "UPDATE production_sessions SET status = 'active' WHERE machine_id = ? AND status = 'paused'", "s", [$code]);
        }

        $operator = db_one($conn, "SELECT GROUP_CONCAT(full_name ORDER BY full_name SEPARATOR ', ') AS names FROM users WHERE role = 'staff' AND status = 'Active' AND assigned_station = ?", 's', [$code]);
        $label = $st['workstation_name'] . (!empty($operator['names']) ? ' · ' . $operator['names'] : ' · Unassigned');
        if ($new_status === 'PAUSED') {
            floor_alert($conn, 'warning', "$code paused", $label);
            log_activity($conn, $code, 'System', 'Station paused by supervisor');
        } else {
            floor_alert($conn, 'info', "$code resumed", $label);
            log_activity($conn, $code, 'System', 'Station resumed by supervisor');
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to update the station.'], 500);
    }

    json_out(['status' => 'success', 'message' => "$code is now " . strtolower($new_status) . '.']);
}

/* ====================================================================
   POST: start a new session (only when the last one is closed)
   Opens a shift record, resets station counters, and starts fresh
   production_sessions rows so counting restarts at zero.
   ==================================================================== */
if ($action === 'open_session') {
    $product = post_product_name($conn);
    $line_t  = post_positive_int('line_target');
    $stn_t   = post_positive_int('station_target');
    if ($product === null) {
        json_out(['status' => 'error', 'message' => 'Please choose a product.'], 400);
    }
    if ($line_t === null || $stn_t === null) {
        json_out(['status' => 'error', 'message' => 'Targets must be whole numbers between 1 and 100000.'], 400);
    }
    $scheduledShift = current_shift_slot($conn);
    if (!get_open_shift($conn) && $scheduledShift === null) {
        json_out(['status' => 'error', 'message' => 'No scheduled shift covers the current time. Update the shift schedule in Master Data first.'], 409);
    }

    try {
        $conn->begin_transaction();

        $line = lock_line_session($conn);
        if ($line && $line['status'] !== 'CLOSED') {
            $conn->rollback();
            json_out(['status' => 'error',
                      'message' => 'Session ' . $line['session_code'] . ' is still open. Close it first.'], 409);
        }

        // Session code: SES-YYYYMMDD-NN
        $prefix = 'SES-' . date('Ymd') . '-';
        $n = db_one($conn,
            "SELECT COUNT(*) AS n FROM supervisor_line_sessions WHERE session_code LIKE ?",
            "s", [$prefix . '%']);
        $code = $prefix . str_pad((string)((int)$n['n'] + 1), 2, '0', STR_PAD_LEFT);

        $opened_by = $_SESSION['full_name'] ?? 'Supervisor';
        db_run($conn,
            "INSERT INTO supervisor_line_sessions
                (session_code, status, product_name, start_time, elapsed_hours, opened_by, line_target, station_target, pause_reason, started_at)
             VALUES (?, 'RUNNING', ?, ?, 0, ?, ?, ?, '', ?)",
            "ssssiis", [$code, $product, date('h:i A'), $opened_by, $line_t, $stn_t, date('Y-m-d H:i:s')]);

        // Open a shift record unless one is somehow still open
        if (!get_open_shift($conn)) {
            [$title, $t_start, $t_end, $range, $s_date] = $scheduledShift;
            $newLineSession = get_line_session($conn);
            db_run($conn,
                "INSERT INTO shift_records (staff_id, shift_title, shift_date, time_start, time_end, time_range, line_target, session_id, status)
                 VALUES (NULL, ?, ?, ?, ?, ?, ?, ?, 'OPEN')",
                "sssssii", [$title, $s_date, $t_start, $t_end, $range, $line_t, (int)$newLineSession['id']]);
        }

        // Fresh counters for every station
        db_run($conn,
            "UPDATE supervisor_station_status st
             JOIN master_stations ms ON ms.station_code = st.station_code
             SET st.status = IF(ms.status = 'Maintenance', 'PAUSED', 'RUNNING'),
                 st.good_units = 0, st.ng_units = 0, st.target_units = ?",
            "i", [$stn_t]);
        // New counting row per station (ensure_production_session / get_metrics read the latest row)
        db_run($conn, "UPDATE production_sessions SET status = 'completed' WHERE status <> 'completed'");
        $stations = db_all($conn,
            "SELECT st.station_code, ms.status AS master_status
             FROM supervisor_station_status st
             JOIN master_stations ms ON ms.station_code = st.station_code
             ORDER BY st.id ASC");
        foreach ($stations as $s) {
            db_run($conn,
                "INSERT INTO production_sessions (machine_id, target_goal, status, created_by) VALUES (?, ?, ?, ?)",
                "sisi", [$s['station_code'], $stn_t, $s['master_status'] === 'Maintenance' ? 'paused' : 'active', $user_id]);
        }

        floor_alert($conn, 'info', 'Session started', "$product · target $line_t units");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to start the session.'], 500);
    }

    json_out(['status' => 'success', 'message' => "Session $code started."]);
}

/* ====================================================================
   POST: change product / targets of the running session
   ==================================================================== */
if ($action === 'save_targets') {
    $product = post_product_name($conn);
    $line_t  = post_positive_int('line_target');
    $stn_t   = post_positive_int('station_target');
    if ($product === null) {
        json_out(['status' => 'error', 'message' => 'Please choose a product.'], 400);
    }
    if ($line_t === null || $stn_t === null) {
        json_out(['status' => 'error', 'message' => 'Targets must be whole numbers between 1 and 100000.'], 400);
    }

    try {
        $conn->begin_transaction();

        $line = lock_line_session($conn);
        if (!$line || $line['status'] === 'CLOSED') {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'There is no open session. Start a new session to use these targets.'], 409);
        }

        db_run($conn,
            "UPDATE supervisor_line_sessions SET product_name = ?, line_target = ?, station_target = ? WHERE id = ?",
            "siii", [$product, $line_t, $stn_t, (int)$line['id']]);
        db_run($conn, "UPDATE supervisor_station_status SET target_units = ?", "i", [$stn_t]);
        db_run($conn, "UPDATE production_sessions SET target_goal = ? WHERE status <> 'completed'", "i", [$stn_t]);

        floor_alert($conn, 'info', 'Targets updated', "$product · line $line_t · per station $stn_t");

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Failed to save targets.'], 500);
    }

    json_out(['status' => 'success', 'message' => 'Targets saved.']);
}

json_out(['status' => 'error', 'message' => 'Unknown action'], 400);
?>
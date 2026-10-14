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

/** Which shift the current clock time belongs to: [title, start, end, range label, shift date]. */
function current_shift_slot(): array {
    $h = (int)date('G');
    if ($h >= 6 && $h < 14) {
        return ['Shift 1', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', date('Y-m-d')];
    }
    if ($h >= 14 && $h < 22) {
        return ['Shift 2', '14:00:00', '22:00:00', '02:00 PM – 10:00 PM', date('Y-m-d')];
    }
    // Night shift runs 10 PM -> 6 AM; after midnight it belongs to the previous day
    $date = ($h < 6) ? date('Y-m-d', strtotime('-1 day')) : date('Y-m-d');
    return ['Shift 3', '22:00:00', '06:00:00', '10:00 PM – 06:00 AM', $date];
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

/** Read and validate product_name from POST. Returns null if invalid. */
function post_product_name(): ?string {
    $p = trim($_POST['product_name'] ?? '');
    return ($p !== '' && mb_strlen($p) <= 80) ? $p : null;
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

    $stations = db_all($conn, "SELECT * FROM supervisor_station_status ORDER BY id ASC");

    json_out([
        'status'          => 'success',
        'session'         => $session,
        'stations'        => $stations,
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
    $closed_shift_date = null;

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
            db_run($conn, "UPDATE production_sessions SET status = 'active' WHERE status = 'paused'");
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
            if ($shift) {
                recalc_shift($conn, (int)$shift['id']);
                $planned = !empty($line['started_at'])
                    ? max(0, (int)floor((strtotime($now) - strtotime($line['started_at'])) / 60))
                    : ((int)$line['elapsed_hours'] * 60);
                $run = max(0, $planned - (int)floor($paused_secs / 60));
                db_run($conn,
                    "UPDATE shift_records SET status = 'CLOSED', planned_minutes = ?, run_minutes = ? WHERE id = ?",
                    "iii", [$planned, $run, (int)$shift['id']]);
                $closed_shift_id = (int)$shift['id'];
                $closed_shift_date = $shift['shift_date'];
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

    json_out(['status' => 'success', 'message' => $msg]);
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

        $st = db_one($conn, "SELECT * FROM supervisor_station_status WHERE station_code = ? FOR UPDATE", "s", [$code]);
        if (!$st) {
            $conn->rollback();
            json_out(['status' => 'error', 'message' => 'Unknown station.'], 404);
        }
        if ($st['status'] === $new_status) {
            $conn->rollback();
            json_out(['status' => 'success', 'message' => 'Station is already ' . strtolower($new_status) . '.']);
        }

        db_run($conn, "UPDATE supervisor_station_status SET status = ? WHERE station_code = ?", "ss", [$new_status, $code]);

        $label = $st['workstation_name'] . ($st['assigned_staff'] ? ' · ' . $st['assigned_staff'] : '');
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
    $product = post_product_name();
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
            [$title, $t_start, $t_end, $range, $s_date] = current_shift_slot();
            db_run($conn,
                "INSERT INTO shift_records (staff_id, shift_title, shift_date, time_start, time_end, time_range, status)
                 VALUES (NULL, ?, ?, ?, ?, ?, 'OPEN')",
                "sssss", [$title, $s_date, $t_start, $t_end, $range]);
        }

        // Fresh counters for every station
        db_run($conn,
            "UPDATE supervisor_station_status SET status = 'RUNNING', good_units = 0, ng_units = 0, target_units = ?",
            "i", [$stn_t]);
        db_run($conn, "DELETE FROM supervisor_hourly_output WHERE log_date = ?", "s", [date('Y-m-d')]);

        // New counting row per station (ensure_production_session / get_metrics read the latest row)
        db_run($conn, "UPDATE production_sessions SET status = 'completed' WHERE status <> 'completed'");
        $stations = db_all($conn, "SELECT station_code FROM supervisor_station_status ORDER BY id ASC");
        foreach ($stations as $s) {
            db_run($conn,
                "INSERT INTO production_sessions (machine_id, target_goal, status, created_by) VALUES (?, ?, 'active', ?)",
                "sii", [$s['station_code'], $stn_t, $user_id]);
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
    $product = post_product_name();
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
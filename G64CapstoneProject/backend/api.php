<?php
// backend/api.php
// Counter API. Used by the ESP32 auto-counter (device key) and by logged-in users (session).
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$action = $_GET['action'] ?? '';

/* ---------- authentication: device key OR logged-in session ---------- */
$device_ok = false;
$supplied_key = $_SERVER['HTTP_X_DEVICE_KEY'] ?? ($_POST['key'] ?? ($_GET['key'] ?? ''));
if (is_string($supplied_key) && $supplied_key !== '') {
    $cfg = db_one($conn, "SELECT device_api_key FROM system_settings WHERE id = 1");
    $device_ok = $cfg && !empty($cfg['device_api_key']) && hash_equals($cfg['device_api_key'], $supplied_key);
}
if (!$device_ok && !isset($_SESSION['user_id'])) {
    json_out(['status' => 'error', 'message' => 'Unauthorized'], 401);
}

// Browser (session) requests that change data need the CSRF token; the ESP32 is authenticated by its key instead
if (!$device_ok) {
    check_role(['staff', 'supervisor', 'manager']);
}

/* ---------- Heartbeat (ESP32 only) ----------
   POST/GET: action=heartbeat&station_code=ESP32-STN04  (send every ~15-30 s) */
if ($action === 'heartbeat') {
    if (!$device_ok) {
        json_out(['status' => 'error', 'message' => 'Device key required'], 403);
    }
    $code = trim($_POST['station_code'] ?? ($_GET['station_code'] ?? ''));
    if ($code === '' || !touch_station($conn, $code)) {
        json_out(['status' => 'error', 'message' => 'Unknown station'], 404);
    }
    json_out(['status' => 'success', 'server_time' => date('c')]);
}

/* ---------- Fetch real-time dashboard data ---------- */
if ($action === 'get_metrics') {
    $station = trim($_GET['station_code'] ?? '');

    // A logged-in operator always sees their own station (the station_code they send is ignored)
    if (!$device_ok && ($_SESSION['role'] ?? '') === 'staff') {
        $me = db_one($conn, "SELECT assigned_station FROM users WHERE id = ?", "i", [(int)$_SESSION['user_id']]);
        $station = trim($me['assigned_station'] ?? '');
        if ($station === '') {
            json_out(['status' => 'error', 'message' => 'No station is assigned to your account.'], 404);
        }
    }

    if ($station !== '') {
        $session = db_one($conn,
            "SELECT * FROM production_sessions WHERE machine_id = ? ORDER BY id DESC LIMIT 1",
            "s", [$station]);
    } elseif (isset($_GET['session_id'])) {
        $session = db_one($conn, "SELECT * FROM production_sessions WHERE id = ?", "i", [intval($_GET['session_id'])]);
    } else {
        json_out(['status' => 'error', 'message' => 'station_code is required'], 400);
    }

    if (!$session) {
        json_out(['status' => 'error', 'message' => 'Active session not found'], 404);
    }

    $total      = $session['good_count'] + $session['ng_count'];
    $progress   = $session['target_goal'] > 0 ? round(($session['good_count'] / $session['target_goal']) * 100, 2) : 0;
    $yield_rate = $total > 0 ? round(($session['good_count'] / $total) * 100, 2) : 0;

    json_out([
        'status' => 'success',
        'data' => [
            'machine_id'     => $session['machine_id'],
            'target_goal'    => $session['target_goal'],
            'good_count'     => $session['good_count'],
            'ng_count'       => $session['ng_count'],
            'total_count'    => $total,
            'progress'       => $progress,
            'yield_rate'     => $yield_rate,
            'session_status' => $session['status'],
            'device_online'  => station_is_online($conn, $session['machine_id']),
            'last_seen'      => (db_one($conn, "SELECT last_seen FROM master_stations WHERE station_code = ?", "s", [$session['machine_id']])['last_seen'] ?? null)
        ]
    ]);
}

/* ---------- Increment count (ESP32 or manual entry) ----------
   POST: type=good|ng, station_code=ESP32-STN04 (or session_id=1), qty=1, defect_type=... (optional, ng only) */
if ($action === 'log_count') {
    require_post('POST required');

    $type = $_POST['type'] ?? 'good';
    if (!in_array($type, ['good', 'ng'], true)) {
        json_out(['status' => 'error', 'message' => "type must be 'good' or 'ng'"], 400);
    }
    $qty = max(1, min(100, intval($_POST['qty'] ?? 1)));

    $station = trim($_POST['station_code'] ?? '');
    if ($station === '') {
        $sid = intval($_POST['session_id'] ?? 1);
        $row = db_one($conn, "SELECT machine_id FROM production_sessions WHERE id = ?", "i", [$sid]);
        if (!$row) {
            json_out(['status' => 'error', 'message' => 'Session not found'], 404);
        }
        $station = $row['machine_id'];
    }

    // A logged-in operator may only count at their own station
    if (!$device_ok && ($_SESSION['role'] ?? '') === 'staff') {
        $me = db_one($conn, "SELECT assigned_station FROM users WHERE id = ?", "i", [(int)$_SESSION['user_id']]);
        if (!$me || $me['assigned_station'] !== $station) {
            json_out(['status' => 'error', 'message' => 'Not your station'], 403);
        }
    }

    // Any counted request from the device also proves it is alive
    if ($device_ok) {
        touch_station($conn, $station);
    }

    // Counting stops while the line or the station is paused/closed
    $block = counting_block_reason($conn, $station);
    if ($block !== null) {
        json_out(['status' => 'error', 'message' => $block], 409);
    }

    $dGood  = ($type === 'good') ? $qty : 0;
    $dNg    = ($type === 'ng') ? $qty : 0;
    $defect = trim($_POST['defect_type'] ?? '');

    try {
        $conn->begin_transaction();

        $ps = apply_counts($conn, $station, $dGood, $dNg);

        if ($type === 'ng' && $defect !== '') {
            $reporter = $device_ok ? null : (int)$_SESSION['user_id'];
            db_run($conn,
                "INSERT INTO defect_logs (session_id, defect_type, reported_by) VALUES (?, ?, ?)",
                "isi", [(int)$ps['id'], $defect, $reporter]);
        }

        if ($type === 'ng') {
            $detail = "NG recorded: $qty unit(s)" . ($defect !== '' ? " ($defect)" : '');
            log_activity($conn, $station, 'Defect', $detail);
        } elseif (((int)$ps['good_count']) % 10 === 0) {
            log_activity($conn, $station, 'Count', "Count milestone: " . (int)$ps['good_count'] . " good units");
        }

        $conn->commit();
    } catch (Throwable $e) {
        $conn->rollback();
        json_out(['status' => 'error', 'message' => 'Database update failed'], 500);
    }

    json_out([
        'status'     => 'success',
        'station'    => $station,
        'good_count' => (int)$ps['good_count'],
        'ng_count'   => (int)$ps['ng_count']
    ]);
}

json_out(['status' => 'error', 'message' => 'Unknown action'], 400);
?>
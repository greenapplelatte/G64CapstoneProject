<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

// Sign-in throttle: this many failures per username (or per IP) inside the window locks sign-in
const LOGIN_MAX_FAILS_USER = 5;
const LOGIN_MAX_FAILS_IP   = 20;
const LOGIN_WINDOW_MIN     = 5;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!csrf_valid()) {
        header("Location: ../login.php?error=csrf");
        exit();
    }
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $ip       = substr($_SERVER['REMOTE_ADDR'] ?? 'unknown', 0, 45);
    $uname_key = mb_substr($username, 0, 50);

    if ($username === '' || $password === '') {
        header("Location: ../login.php?error=empty");
        exit();
    }

    // ---- throttle: refuse before touching the password ----
    // (MySQL's clock is used for every comparison so PHP/MySQL timezone differences cannot matter)
    $throttle_ok = true;
    try {
    db_run($conn, "DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY");
    $u = db_one($conn,
        "SELECT COUNT(*) AS n, MIN(attempted_at) AS first_at,
                GREATEST(0, " . LOGIN_WINDOW_MIN . " * 60 - TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW())) AS wait_s
         FROM login_attempts
         WHERE username = ? AND attempted_at > NOW() - INTERVAL " . LOGIN_WINDOW_MIN . " MINUTE",
        "s", [$uname_key]);
    $i = db_one($conn,
        "SELECT COUNT(*) AS n,
                GREATEST(0, " . LOGIN_WINDOW_MIN . " * 60 - TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW())) AS wait_s
         FROM login_attempts
         WHERE ip = ? AND attempted_at > NOW() - INTERVAL " . LOGIN_WINDOW_MIN . " MINUTE",
        "s", [$ip]);
    $wait = 0;
    if ((int)$u['n'] >= LOGIN_MAX_FAILS_USER) { $wait = max($wait, (int)$u['wait_s']); }
    if ((int)$i['n'] >= LOGIN_MAX_FAILS_IP)   { $wait = max($wait, (int)$i['wait_s']); }
    if ($wait > 0) {
        header("Location: ../login.php?error=locked&wait=" . max(1, (int)ceil($wait / 60)));
        exit();
    }
    } catch (mysqli_sql_exception $e) {
        // login_attempts table missing (group3_migration.sql not run yet): do not block sign-in
        error_log('Login throttle disabled: ' . $e->getMessage());
        $throttle_ok = false;
    }

    $user = db_one($conn,
        "SELECT id, username, password, role, full_name, status, must_change_password FROM users WHERE username = ?",
        "s", [$username]);

    // Real password check only (no shared/plain-text fallback), and disabled accounts cannot sign in.
    // A disabled account gets the same generic message so account status is not revealed.
    if ($user
        && ($user['status'] ?? 'Active') === 'Active'
        && in_array($user['role'], ['manager', 'supervisor', 'staff'], true)
        && password_verify($password, $user['password'])) {

        if ($throttle_ok) { db_run($conn, "DELETE FROM login_attempts WHERE username = ?", "s", [$uname_key]); }
        session_regenerate_id(true);
        // "Keep me signed in on this device". The session is already running, so PHP will not let us change its
        // cookie settings now; send the session cookie again ourselves (the browser keeps the last one it receives),
        // plus a small flag cookie that app.php reads on later requests to keep the 30-day lifetime.
        $keep = !empty($_POST['remember']);
        $co = session_cookie_options($keep);
        $expires = $keep ? time() + $co['lifetime'] : 0;
        setcookie(session_name(), session_id(), [
            'expires' => $expires, 'path' => $co['path'], 'secure' => $co['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        setcookie('mv_keep', $keep ? '1' : '', [
            'expires' => $keep ? $expires : time() - 3600, 'path' => '/', 'secure' => $co['secure'],
            'httponly' => true, 'samesite' => 'Lax',
        ]);
        unset($_SESSION['csrf_token']);   // fresh token for the new login
        $_SESSION['user_id']   = $user['id'];
        $_SESSION['username']  = $user['username'];
        $_SESSION['role']      = $user['role'];
        $_SESSION['full_name'] = $user['full_name'];
        $_SESSION['must_change_password'] = (int)$user['must_change_password'] === 1;

        $stamp = date('M d, Y · h:i A');
        db_run($conn, "UPDATE users SET last_signin = ?, last_active = 'Now' WHERE id = ?", "si", [$stamp, (int)$user['id']]);

        if ($_SESSION['must_change_password']) {
            header("Location: ../views/" . $user['role'] . "/profile.php?force=1");
            exit();
        }
        header("Location: ../views/" . $user['role'] . "/dashboard.php");
        exit();
    }

    if ($throttle_ok) { db_run($conn, "INSERT INTO login_attempts (username, ip) VALUES (?, ?)", "ss", [$uname_key, $ip]); }
    header("Location: ../login.php?error=invalid");
    exit();
} else {
    header("Location: ../login.php");
    exit();
}
?>
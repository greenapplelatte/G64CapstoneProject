<?php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

/* ---------- CSRF protection ----------
   csrf_token()  : the session's token (created on first use)
   csrf_meta()   : put inside <head> of every page; adds the token + assets/js/csrf.js,
                   which attaches it to every fetch() that is not a GET
   csrf_field()  : hidden <input> for plain HTML forms
   csrf_verify() : call in every POST endpoint (check_role() already does it for POST)   */
function csrf_token(): string {
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf_token'];
}

function csrf_meta(): string {
    return '<meta name="csrf-token" content="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

/**
 * Put <?= head_assets() ?> as the first line inside <head> of every page. It gives the page:
 *   - the CSRF token (meta tag) and assets/js/csrf.js (adds it to every fetch POST)
 *   - the BASE_URL JavaScript constant
 *   - assets/js/main.js (esc() and other shared helpers)
 */
function head_assets(): string {
    return csrf_meta() . "\n"
         . '    <script>window.BASE_URL = ' . json_encode(BASE_URL) . ';</script>' . "\n"
         . '    <script src="' . BASE_URL . '/assets/js/csrf.js"></script>' . "\n"
         . '    <script src="' . BASE_URL . '/assets/js/main.js"></script>';
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars(csrf_token(), ENT_QUOTES) . '">';
}

function csrf_valid(): bool {
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? ($_POST['csrf_token'] ?? '');
    return is_string($sent) && $sent !== ''
        && !empty($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $sent);
}

/** Stops the request (403) unless it is a safe method or carries the right token. */
function csrf_verify(): void {
    if (in_array($_SERVER['REQUEST_METHOD'] ?? 'GET', ['GET', 'HEAD', 'OPTIONS'], true) || csrf_valid()) {
        return;
    }
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Your session security token is missing or expired. Please refresh the page and try again.']);
    exit();
}

/** Stops the request with a 405 JSON error unless it is a POST. */
function require_post(string $message = 'POST request required.'): void {
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        http_response_code(405);
        header('Content-Type: application/json');
        echo json_encode(['status' => 'error', 'message' => $message]);
        exit();
    }
}

/**
 * Checks if the user is logged in and has the required role.
 * If $allowed_roles is string, converts to array.
 */
function check_role($allowed_roles) {
    global $conn;
    // 1. Check if user is logged in
    if (!isset($_SESSION['user_id']) || !isset($_SESSION['role'])) {
        header("Location: " . BASE_URL . "/login.php?error=unauthorized");
        exit();
    }

    // Revalidate account state and role on every request (sessions may last 30 days).
    if (!isset($conn) || !($conn instanceof mysqli)) {
        require_once __DIR__ . '/../config/database.php';
    }
    try {
        $accountStmt = $conn->prepare("SELECT role, status FROM users WHERE id = ? LIMIT 1");
        $accountStmt->bind_param('i', $_SESSION['user_id']);
        $accountStmt->execute();
        $currentAccount = $accountStmt->get_result()->fetch_assoc();
        $accountStmt->close();
    } catch (Throwable $e) {
        $currentAccount = null;
    }
    if (!$currentAccount || strcasecmp((string)($currentAccount['status'] ?? 'Active'), 'Active') !== 0) {
        $_SESSION = [];
        if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
        if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/') !== false) {
            http_response_code(401); header('Content-Type: application/json');
            echo json_encode(['status' => 'error', 'message' => 'Your account is disabled or no longer available. Please sign in again.']);
        } else {
            header('Location: ' . BASE_URL . '/login.php?error=account_disabled');
        }
        exit();
    }
    $_SESSION['role'] = $currentAccount['role'];

    // Convert single string role to array if needed
    if (!is_array($allowed_roles)) {
        $allowed_roles = [$allowed_roles];
    }

    // 2. Check if user's role is allowed
    if (!in_array($_SESSION['role'], $allowed_roles, true)) {
        header("Location: " . BASE_URL . "/login.php?error=unauthorized");
        exit();
    }

    // 3. A temporary password must be replaced before anything else is allowed
    if (!empty($_SESSION['must_change_password'])) {
        $script  = basename($_SERVER['SCRIPT_NAME'] ?? '');
        $allowed = ['profile.php', 'process_profile.php', 'process_manager_profile.php',
                    'process_supervisor_profile.php', 'process_logout.php'];
        if (!in_array($script, $allowed, true)) {
            if (strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/') !== false) {
                http_response_code(403);
                header('Content-Type: application/json');
                echo json_encode(['status' => 'error', 'message' => 'Please change your temporary password first.']);
                exit();
            }
            header("Location: " . BASE_URL . "/views/" . $_SESSION['role'] . "/profile.php?force=1");
            exit();
        }
    }

    // 4. Every state-changing request must carry the CSRF token
    csrf_verify();
}
?>
<?php
// config/app.php   (REPLACES the existing config/app.php)
// One place for the folder the project lives in, the environment (local / production),
// error reporting, and the session cookie settings.
//
// Nothing changes on your XAMPP machine. For a live server, create config/env.local.php
// (copy env.local.sample.php) and set 'env' => 'production' and the right 'base_url'.

// ---- optional per-server settings (kept out of the code so you never edit this file per host) ----
if (!isset($GLOBALS['__app_local'])) {
    $local = is_file(__DIR__ . '/env.local.php') ? require __DIR__ . '/env.local.php' : [];
    $GLOBALS['__app_local'] = is_array($local) ? $local : [];
}
$__local = $GLOBALS['__app_local'];

// 'local' (default) or 'production'
if (!defined('APP_ENV')) {
    define('APP_ENV', ($__local['env'] ?? 'local') === 'production' ? 'production' : 'local');
}

// Folder the project lives in. Use '' (empty) when the site is at the domain root.
if (!defined('BASE_URL')) {
    $base = array_key_exists('base_url', $__local) ? (string)$__local['base_url'] : '/G64CapstoneProject';
    define('BASE_URL', rtrim($base, '/'));
}

// ---- error reporting: show errors on screen only while developing ----
error_reporting(E_ALL);
if (APP_ENV === 'production') {
    ini_set('display_errors', '0');   // never show file paths / SQL to visitors
    ini_set('log_errors', '1');       // errors go to the server's error log instead
} else {
    ini_set('display_errors', '1');
}

// Backend endpoints answer in JSON: never print PHP warnings into the response, and turn any
// uncaught error (for example a missing table) into a clean JSON message instead of a blank page or HTML.
if (PHP_SAPI !== 'cli' && strpos($_SERVER['SCRIPT_NAME'] ?? '', '/backend/') !== false) {
    ini_set('display_errors', '0');
    set_exception_handler(function (Throwable $e) {
        error_log('Unhandled error in ' . ($_SERVER['SCRIPT_NAME'] ?? '') . ': ' . $e->getMessage());
        if (!headers_sent()) {
            http_response_code(500);
            header('Content-Type: application/json');
        }
        echo json_encode(['status' => 'error', 'message' => 'Server error. Please try again.']);
    });
}

// Session cookie settings (shared by every page, because every page loads this file first).
// "Keep me signed in" sets a small mv_keep cookie at login; while it exists the session cookie lasts 30 days,
// otherwise it ends when the browser closes. HttpOnly + SameSite stop scripts and other sites from using it.
// HTTPS is also detected behind a proxy / Cloudflare (X-Forwarded-Proto), so the Secure flag turns on there too.
if (!function_exists('session_cookie_options')) {
    function session_cookie_options(bool $keep): array {
        $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
              || (strtolower($_SERVER['HTTP_X_FORWARDED_PROTO'] ?? '') === 'https');
        return ['lifetime' => $keep ? 30 * 86400 : 0, 'path' => '/', 'secure' => $https,
                'httponly' => true, 'samesite' => 'Lax'];
    }
}
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.gc_maxlifetime', (string)(30 * 86400));   // server must not delete a remembered session early
    session_set_cookie_params(session_cookie_options(!empty($_COOKIE['mv_keep'])));
    session_start();
}
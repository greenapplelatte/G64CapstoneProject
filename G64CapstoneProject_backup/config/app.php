<?php
// config/app.php
// One place for the folder the project lives in (change it here if you rename or move the folder).
if (!defined('BASE_URL')) {
    define('BASE_URL', '/G64CapstoneProject');
}

// Session cookie settings (shared by every page, because every page loads this file first).
// "Keep me signed in" sets a small mv_keep cookie at login; while it exists the session cookie lasts 30 days,
// otherwise it ends when the browser closes. HttpOnly + SameSite stop scripts and other sites from using it.
if (!function_exists('session_cookie_options')) {
    function session_cookie_options(bool $keep): array {
        $https = !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off';
        return ['lifetime' => $keep ? 30 * 86400 : 0, 'path' => '/', 'secure' => $https,
                'httponly' => true, 'samesite' => 'Lax'];
    }
}
if (session_status() === PHP_SESSION_NONE && PHP_SAPI !== 'cli') {
    ini_set('session.gc_maxlifetime', (string)(30 * 86400));   // server must not delete a remembered session early
    session_set_cookie_params(session_cookie_options(!empty($_COOKIE['mv_keep'])));
    session_start();
}
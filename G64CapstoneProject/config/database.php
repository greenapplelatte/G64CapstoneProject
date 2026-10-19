<?php
// config/database.php   (REPLACES the existing config/database.php)
// Local XAMPP keeps working with no changes (root / no password / g64capstoneproject).
// On a live server put the real credentials in config/env.local.php, not here.
require_once __DIR__ . '/app.php';
// Prevent session warnings if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable exception reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$__db   = (is_array($GLOBALS['__app_local']['db'] ?? null)) ? $GLOBALS['__app_local']['db'] : [];
$host   = $__db['host'] ?? 'localhost';
$user   = $__db['user'] ?? 'root';
$pass   = $__db['pass'] ?? '';
$dbname = $__db['name'] ?? 'g64capstoneproject';
$dbport = (int)($__db['port'] ?? 3306);

try {
    $conn = new mysqli($host, $user, $pass, $dbname, $dbport);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    error_log('Database connection failed: ' . $e->getMessage());
    http_response_code(500);
    if (APP_ENV === 'production') {
        // visitors get a plain message; the real reason is only in the server error log
        die("<div style='font-family:Arial; padding:20px;'><h3>Service temporarily unavailable</h3><p>Please try again later.</p></div>");
    }
    die("<div style='font-family:Arial; padding:20px; background:#fff3f3; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;'>
        <h3>Database Connection Failure</h3>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please make sure the database <code>" . htmlspecialchars($dbname) . "</code> exists in phpMyAdmin.</p>
    </div>");
}
?>
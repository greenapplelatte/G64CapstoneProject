<?php
require_once __DIR__ . '/app.php';
// Prevent session warnings if session is already active
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Enable exception reporting for MySQLi
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$host = "localhost";
$user = "root";
$pass = "";
$dbname = "g64capstoneproject"; // <-- Updated to match your database name

try {
    $conn = new mysqli($host, $user, $pass, $dbname);
    $conn->set_charset("utf8mb4");
} catch (mysqli_sql_exception $e) {
    die("<div style='font-family:Arial; padding:20px; background:#fff3f3; color:#721c24; border:1px solid #f5c6cb; border-radius:5px;'>
        <h3>Database Connection Failure</h3>
        <p><strong>Error:</strong> " . htmlspecialchars($e->getMessage()) . "</p>
        <p>Please make sure the database <code>g64capstoneproject</code> exists in phpMyAdmin.</p>
    </div>");
}
?>
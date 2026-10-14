<?php
session_start();

// Redirect to login page if there is no active session
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

// Safely assign session variables with a fallback to prevent undefined variable warnings in the sidebar
$user_name = $_SESSION['username'] ?? 'Maria Alvarez';
$user_role = $_SESSION['role'] ?? 'Administrator';
?>
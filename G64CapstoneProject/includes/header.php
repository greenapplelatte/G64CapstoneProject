<?php
// includes/header.php
require_once __DIR__ . '/../config/app.php';
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Redirect if unauthenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: " . BASE_URL . "/login.php");
    exit();
}

$pageTitle = $pageTitle ?? 'Dashboard';
$userRole  = $_SESSION['role'] ?? 'staff';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle); ?> | Capstone System</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a href="#" class="brand">G64 Capstone System</a>
    <div class="user-info">
        <span>Welcome, <strong><?= htmlspecialchars($_SESSION['full_name']); ?></strong></span>
        <span class="role-badge role-<?= htmlspecialchars($userRole); ?>">
            <?= htmlspecialchars($userRole); ?>
        </span>
        <a href="<?= BASE_URL ?>/backend/process_logout.php">Logout</a>
    </div>
</nav>

<main class="main-container"></main>
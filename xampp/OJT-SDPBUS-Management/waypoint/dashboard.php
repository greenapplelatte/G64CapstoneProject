<?php
session_start();

$isGuest = isset($_GET['guest']) && $_GET['guest'] === '1';

if (!$isGuest && empty($_SESSION['user_id'])) {
    header('Location: index.php');
    exit;
}

$username = $_SESSION['username'] ?? 'Guest';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>DPBUS | Dashboard</title>
    <link rel="stylesheet" href="css/style.css">
</head>
<body class="dashboard-page">
    <main class="dashboard-card">
        <div class="brand-small">
            <span class="compass-logo" aria-hidden="true"><span>S</span></span>
            <span>DPBUS</span>
        </div>

        <h1>Welcome, <?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?>!</h1>
        <p><?= $isGuest ? 'You are browsing as a guest.' : 'You are successfully logged in.' ?></p>

        <?php if (!$isGuest): ?>
            <a href="logout.php">Logout</a>
        <?php else: ?>
            <a href="index.php">Back to Login</a>
        <?php endif; ?>
    </main>
</body>
</html>

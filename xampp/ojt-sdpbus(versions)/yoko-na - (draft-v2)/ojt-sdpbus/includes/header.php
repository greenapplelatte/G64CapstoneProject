<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
$pageTitle = $pageTitle ?? 'SDPBUS Management System';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?= htmlspecialchars($pageTitle) ?></title>

    <!-- CSS Dependencies -->
    <link rel="stylesheet" href="/ojt-sdpbus/assets/css/style.css">
    <link rel="stylesheet" href="/ojt-sdpbus/assets/css/dashboard.css">
    <link rel="stylesheet" href="/ojt-sdpbus/assets/css/forms.css">
    <link rel="stylesheet" href="/ojt-sdpbus/assets/css/profile.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
    
    <!-- Font Awesome Icons CDN -->
    <link rel="stylesheet" href="/ojt-sdpbus/assets/fontawesome/css/all.min.css">
    <?php if (!empty($extraCss)): ?>
        <link rel="stylesheet" href="/ojt-sdpbus/assets/css/<?= htmlspecialchars(basename($extraCss), ENT_QUOTES, 'UTF-8') ?>">
    <?php endif; ?>
</head>
<body>
<div class="app-container">
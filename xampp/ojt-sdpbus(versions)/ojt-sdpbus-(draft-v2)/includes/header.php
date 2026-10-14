<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDPBUS Management System</title>
    
    <!-- Global Styles with Cache Busting -->
    <link rel="stylesheet" href="assets/css/global.css?v=<?php echo filemtime('assets/css/global.css'); ?>">
    <link rel="stylesheet" href="assets/css/sidebar.css?v=<?php echo filemtime('assets/css/sidebar.css'); ?>">

    <!-- Page-Specific Stylesheet Loading with Cache Busting -->
    <?php 
    $current_page = basename($_SERVER['PHP_SELF']);
    
    if ($current_page === 'dashboard.php'): ?>
        <link rel="stylesheet" href="assets/css/dashboard.css?v=<?php echo filemtime('assets/css/dashboard.css'); ?>">
    <?php elseif ($current_page === 'dispatch_log.php'): ?>
        <link rel="stylesheet" href="assets/css/dispatch_log.css?v=<?php echo filemtime('assets/css/dispatch_log.css'); ?>">
    <?php endif; ?>

    <!-- Chart.js CDN -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>
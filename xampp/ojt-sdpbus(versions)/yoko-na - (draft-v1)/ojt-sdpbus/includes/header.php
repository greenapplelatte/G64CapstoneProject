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
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
<div class="app-container">
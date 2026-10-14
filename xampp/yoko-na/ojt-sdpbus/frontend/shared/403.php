<?php
// frontend/shared/403.php
$pageTitle = "403 Access Forbidden - SDPBUS";
include __DIR__ . '/../../includes/header.php';
?>

<div class="app-container" style="justify-content: center; align-items: center; min-height: 100vh;">
    <div class="card" style="text-align: center; max-width: 480px; padding: 40px;">
        <h1 style="font-size: 3rem; color: #ef4444; margin-bottom: 10px;">403</h1>
        <h2>Access Forbidden</h2>
        <p style="color: #94a3b8; margin-top: 10px; margin-bottom: 24px;">
            You do not have administrative permission to access this page.
        </p>
        <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/dashboard.php" class="btn btn-primary">Return to Staff Dashboard</a>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
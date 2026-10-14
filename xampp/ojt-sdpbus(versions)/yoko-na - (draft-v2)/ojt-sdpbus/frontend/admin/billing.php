<?php
// frontend/admin/billing.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();

// Summary calculations
$openSummary = $db->query("SELECT COUNT(*) as count, SUM(ticket_total) as total 
                           FROM tickets 
                           WHERE review_status = 'Approved' AND billing_closed = 0")->fetch();

$closedSummary = $db->query("SELECT COUNT(*) as count, SUM(ticket_total) as total 
                             FROM tickets 
                             WHERE review_status = 'Approved' AND billing_closed = 1")->fetch();

$pageTitle = "Billing & Financial Reports - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Billing & Period Closing</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card border-warning">
            <div class="stat-value text-warning">₱<?= number_format((float)($openSummary['total'] ?? 0), 2) ?></div>
            <div class="stat-label">Unbilled Approved Revenue (<?= $openSummary['count'] ?> tickets)</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">₱<?= number_format((float)($closedSummary['total'] ?? 0), 2) ?></div>
            <div class="stat-label">Closed Billing Revenue (<?= $closedSummary['count'] ?> tickets)</div>
        </div>
    </div>

    <!-- Period Close Action Box -->
    <div class="card margin-top">
        <h3>Close Billing Period</h3>
        <p style="color: #94a3b8; font-size: 0.9rem; margin-bottom: 15px;">
            Closing a period permanently locks all approved tickets within the selected date range to prevent further updates or modifications.
        </p>

        <form action="/ojt-sdpbus/backend/report/close_period.php" method="POST" style="display: flex; gap: 15px; align-items: flex-end;">
            <div>
                <label for="start_date">Period Start Date *</label>
                <input type="date" id="start_date" name="start_date" required>
            </div>
            <div>
                <label for="end_date">Period End Date *</label>
                <input type="date" id="end_date" name="end_date" required>
            </div>
            <button type="submit" class="btn btn-danger" onclick="return confirm('Are you sure you want to lock all approved tickets in this date range?')">
                🔒 Close & Lock Period
            </button>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
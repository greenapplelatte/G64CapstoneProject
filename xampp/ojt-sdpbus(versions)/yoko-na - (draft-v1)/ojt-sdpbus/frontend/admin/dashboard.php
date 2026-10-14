<?php
// frontend/admin/dashboard.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();

// System High-Level Metrics
$pendingCount = $db->query("SELECT COUNT(*) FROM tickets WHERE review_status = 'Submitted'")->fetchColumn();
$approvedCount = $db->query("SELECT COUNT(*) FROM tickets WHERE review_status = 'Approved'")->fetchColumn();
$totalBuses = $db->query("SELECT COUNT(*) FROM buses WHERE status = 'ACTIVE'")->fetchColumn();
$totalRevenue = $db->query("SELECT SUM(ticket_total) FROM tickets WHERE review_status = 'Approved'")->fetchColumn() ?: 0.00;

// Fetch tickets pending review
$pendingTickets = $db->query("SELECT t.id, t.ticket_number, t.trip_date, t.shift, u.username, r.route_name 
                              FROM tickets t
                              JOIN users u ON t.user_id = u.id
                              JOIN routes r ON t.route_id = r.id
                              WHERE t.review_status = 'Submitted' 
                              ORDER BY t.created_at ASC LIMIT 5")->fetchAll();

$pageTitle = "Admin Dashboard - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Admin Executive Overview 👋</h1>
        <span><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Counters -->
    <div class="stats-grid">
        <div class="stat-card border-warning">
            <div class="stat-value text-warning"><?= $pendingCount ?></div>
            <div class="stat-label">Tickets Awaiting Review</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $approvedCount ?></div>
            <div class="stat-label">Approved Tickets</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $totalBuses ?></div>
            <div class="stat-label">Active Bus Fleet</div>
        </div>
        <div class="stat-card">
            <div class="stat-value">₱<?= number_format((float)$totalRevenue, 2) ?></div>
            <div class="stat-label">Total Approved Revenue</div>
        </div>
    </div>

    <!-- Review Attention Table -->
    <div class="card margin-top">
        <div class="top-bar">
            <h3>Tickets Needing Approval</h3>
            <a href="/ojt-sdpbus/frontend/admin/review_queue.php" class="btn btn-sm btn-primary">Go to Review Queue →</a>
        </div>
        <?php if (empty($pendingTickets)): ?>
            <p class="empty-msg">No tickets currently pending review.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Date & Shift</th>
                        <th>Route</th>
                        <th>Encoded By</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTickets as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($t['trip_date']) ?> (<?= htmlspecialchars($t['shift']) ?>)</td>
                            <td><?= htmlspecialchars($t['route_name']) ?></td>
                            <td>👤 <?= htmlspecialchars($t['username']) ?></td>
                            <td>
                                <a href="/ojt-sdpbus/frontend/admin/review_queue.php" class="btn btn-sm btn-warning">Review</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
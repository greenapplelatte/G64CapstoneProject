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
    <div class="page-header">
        <div>
            <h1>Admin Executive Overview 👋</h1>
            <p>Real-time fleet activity, review queue, and revenue summary</p>
        </div>
        <span class="badge badge-locked"><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Top Counters -->
   <div class="metrics-grid">
    <!-- Pending Review (Orange/Amber) -->
    <div class="metric-card" style="color: var(--status-pending);">
        <div class="metric-value"><?= number_format($pendingCount) ?></div>
        <div class="metric-label">Tickets Awaiting Review</div>
    </div>

    <!-- Approved (Green) -->
    <div class="metric-card" style="color: var(--status-approved);">
        <div class="metric-value"><?= number_format($approvedCount) ?></div>
        <div class="metric-label">Approved Tickets</div>
    </div>

    <!-- Active Fleet (Brand Blue) -->
    <div class="metric-card" style="color: var(--brand-blue-text);">
        <div class="metric-value"><?= number_format($totalBuses) ?></div>
        <div class="metric-label">Active Bus Fleet</div>
    </div>

    <!-- Revenue (Dark Blue/Navy) -->
    <div class="metric-card" style="color: #0f3c7e;">
        <div class="metric-value">₱<?= number_format((float)$totalRevenue, 2) ?></div>
        <div class="metric-label">Total Approved Revenue</div>
    </div>
</div>

    <!-- Review Queue Table Card -->
    <div class="card">
        <div class="page-header" style="margin-bottom: 16px;">
            <div>
                <h3 style="font-size: 1.25rem;">Tickets Needing Approval</h3>
                <p>Pending submissions requiring admin action</p>
            </div>
            <a href="/ojt-sdpbus/frontend/admin/review_queue.php" class="btn btn-primary">
                Go to Review Queue ➔
            </a>
        </div>

        <?php if (empty($pendingTickets)): ?>
            <p style="color: var(--text-secondary); padding: 16px 0;">No tickets currently pending review.</p>
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
                            <td><strong style="color: var(--brand-blue);"><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($t['trip_date']) ?> (<?= htmlspecialchars($t['shift']) ?>)</td>
                            <td><?= htmlspecialchars($t['route_name']) ?></td>
                            <td>👤 <?= htmlspecialchars($t['username']) ?></td>
                            <td>
                                <a href="/ojt-sdpbus/frontend/admin/review_queue.php" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem;">
                                    Review ➔
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
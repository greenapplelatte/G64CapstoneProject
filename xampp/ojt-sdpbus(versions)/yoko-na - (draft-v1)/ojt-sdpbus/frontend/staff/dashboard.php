<?php
// frontend/staff/dashboard.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$userId = (int)$_SESSION['user_id'];
$db = Database::getConnection();

// Fetch summary metrics for active user
$stmtDraft = $db->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND review_status = 'Draft'");
$stmtDraft->execute([':id' => $userId]);
$draftCount = $stmtDraft->fetchColumn();

$stmtSubmitted = $db->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND review_status = 'Submitted'");
$stmtSubmitted->execute([':id' => $userId]);
$submittedCount = $stmtSubmitted->fetchColumn();

$stmtReturned = $db->prepare("SELECT COUNT(*) FROM tickets WHERE user_id = :id AND review_status = 'Returned'");
$stmtReturned->execute([':id' => $userId]);
$returnedCount = $stmtReturned->fetchColumn();

// Fetch recent returned tickets needing attention
$stmtAlerts = $db->prepare("SELECT id, ticket_number, trip_date, admin_comment FROM tickets WHERE user_id = :id AND review_status = 'Returned' ORDER BY id DESC LIMIT 5");
$stmtAlerts->execute([':id' => $userId]);
$returnedTickets = $stmtAlerts->fetchAll();

$pageTitle = "Staff Dashboard - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> 👋</h1>
        <span><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Alert Banner for Returned Tickets -->
    <?php if (!empty($returnedTickets)): ?>
        <div class="alert alert-warning">
            <strong>⚠️ Attention Required:</strong> You have <?= count($returnedTickets) ?> ticket(s) returned for review/correction. Please check below.
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-value"><?= $draftCount ?></div>
            <div class="stat-label">Draft Tickets</div>
        </div>
        <div class="stat-card">
            <div class="stat-value"><?= $submittedCount ?></div>
            <div class="stat-label">Pending Approval</div>
        </div>
        <div class="stat-card border-warning">
            <div class="stat-value text-warning"><?= $returnedCount ?></div>
            <div class="stat-label">Returned Tickets</div>
        </div>
    </div>

    <!-- Quick Actions -->
    <div class="quick-actions">
        <h3>Quick Actions</h3>
        <div class="actions-group">
            <a href="/ojt-sdpbus/frontend/staff/new_ticket.php" class="btn btn-primary">➕ Record New Trip Ticket</a>
            <a href="/ojt-sdpbus/frontend/staff/todays_trips.php" class="btn btn-secondary">🚍 View Today's Scheduled Trips</a>
            <a href="/ojt-sdpbus/frontend/staff/my_tickets.php" class="btn btn-outline">📋 View My Tickets History</a>
        </div>
    </div>

    <!-- Returned Tickets Action List -->
    <?php if (!empty($returnedTickets)): ?>
        <div class="card margin-top">
            <h3>Returned Tickets Requiring Revision</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Trip Date</th>
                        <th>Admin Reason / Remarks</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($returnedTickets as $ticket): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($ticket['trip_date']) ?></td>
                            <td class="text-danger"><?= htmlspecialchars($ticket['admin_comment'] ?? 'Needs review') ?></td>
                            <td>
                                <a href="/ojt-sdpbus/frontend/staff/new_ticket.php?edit_id=<?= $ticket['id'] ?>" class="btn btn-sm btn-warning">Fix & Resubmit</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
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
    <div class="page-header">
        <div>
            <h1>Welcome, <?= htmlspecialchars($_SESSION['username']) ?> 👋</h1>
            <p>Overview of your trip tickets, status updates, and quick actions</p>
        </div>
        <span class="badge badge-locked"><?= date('l, F j, Y') ?></span>
    </div>

    <!-- Alert Banner for Returned Tickets -->
    <?php if (!empty($returnedTickets)): ?>
        <div class="card" style="background-color: rgba(239, 68, 68, 0.08); border-color: rgba(239, 68, 68, 0.2); padding: 16px 20px; display: flex; align-items: center; justify-content: space-between;">
            <div style="display: flex; align-items: center; gap: 12px;">
                <span style="font-size: 1.25rem;">⚠️</span>
                <div>
                    <strong style="color: var(--status-returned); display: block;">Attention Required</strong>
                    <span style="color: var(--text-secondary); font-size: 0.875rem;">
                        You have <strong><?= count($returnedTickets) ?></strong> ticket(s) returned for review/correction.
                    </span>
                </div>
            </div>
            <a href="#returned-section" class="btn btn-secondary" style="padding: 6px 14px; font-size: 0.8rem;">View Items ↓</a>
        </div>
    <?php endif; ?>

    <!-- Summary Cards -->
    <div class="metrics-grid">
    <!-- Draft Tickets (Muted/Gray) -->
    <div class="metric-card" style="color: var(--status-draft);">
        <div class="metric-value"><?= number_format($draftCount) ?></div>
        <div class="metric-label">Draft Tickets</div>
    </div>

    <!-- Pending Approval (Orange/Amber) -->
    <div class="metric-card" style="color: var(--status-pending);">
        <div class="metric-value"><?= number_format($submittedCount) ?></div>
        <div class="metric-label">Pending Approval</div>
    </div>

    <!-- Returned Tickets (Red) -->
    <div class="metric-card" style="color: var(--status-returned);">
        <div class="metric-value"><?= number_format($returnedCount) ?></div>
        <div class="metric-label">Returned Tickets</div>
    </div>
</div>

    <!-- Quick Actions Card -->
    <div class="card">
        <h3 style="font-size: 1.1rem; margin-bottom: 16px;">Quick Actions</h3>
        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
            <a href="/ojt-sdpbus/frontend/staff/new_ticket.php" class="btn btn-primary">
                ➕ Record New Trip Ticket
            </a>
            <a href="/ojt-sdpbus/frontend/staff/todays_trips.php" class="btn btn-secondary">
                🚍 View Today's Scheduled Trips
            </a>
            <a href="/ojt-sdpbus/frontend/staff/my_tickets.php" class="btn btn-secondary">
                📋 View My Tickets History
            </a>
        </div>
    </div>

    <!-- Returned Tickets Action List -->
    <?php if (!empty($returnedTickets)): ?>
        <div class="card" id="returned-section">
            <div class="page-header" style="margin-bottom: 16px;">
                <div>
                    <h3 style="font-size: 1.25rem;">Returned Tickets Requiring Revision</h3>
                    <p>Review remarks and make needed corrections</p>
                </div>
                <span class="badge badge-returned"><?= count($returnedTickets) ?> Needs Fix</span>
            </div>

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
                            <td><strong style="color: var(--brand-blue);"><?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($ticket['trip_date']) ?></td>
                            <td><span style="color: var(--status-returned); font-weight: 600;"><?= htmlspecialchars($ticket['admin_comment'] ?? 'Needs review') ?></span></td>
                            <td>
                                <a href="/ojt-sdpbus/frontend/staff/new_ticket.php?edit_id=<?= $ticket['id'] ?>" class="btn btn-secondary" style="padding: 6px 12px; font-size: 0.8rem; color: var(--status-returned);">
                                    Fix & Resubmit ➔
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
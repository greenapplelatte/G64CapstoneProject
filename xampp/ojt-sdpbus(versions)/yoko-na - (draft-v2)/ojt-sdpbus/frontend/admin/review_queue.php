<?php
// frontend/admin/review_queue.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();

// Fetch submitted tickets awaiting review
$stmt = $db->query("SELECT t.*, r.route_name, b.bus_code, d.driver_name, u.username
                    FROM tickets t
                    JOIN routes r ON t.route_id = r.id
                    JOIN buses b ON t.bus_id = b.id
                    JOIN drivers d ON t.driver_id = d.id
                    JOIN users u ON t.user_id = u.id
                    WHERE t.review_status = 'Submitted'
                    ORDER BY t.created_at ASC");
$pendingTickets = $stmt->fetchAll();

$pageTitle = "Ticket Review Queue - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Ticket Review & Approval Queue</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card">
        <?php if (empty($pendingTickets)): ?>
            <p class="empty-msg">🎉 All caught up! No tickets currently pending review.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Trip Date & Shift</th>
                        <th>Route / Bus / Driver</th>
                        <th>Encoded By</th>
                        <th>Passengers (Act / Fcst)</th>
                        <th>Total Fare</th>
                        <th>Review Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pendingTickets as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($t['trip_date']) ?><br><small><?= htmlspecialchars($t['shift']) ?></small></td>
                            <td>
                                <strong><?= htmlspecialchars($t['route_name']) ?></strong><br>
                                <small><?= htmlspecialchars($t['bus_code']) ?> | <?= htmlspecialchars($t['driver_name']) ?></small>
                            </td>
                            <td>👤 <?= htmlspecialchars($t['username']) ?></td>
                            <td><?= $t['actual_passengers'] ?> / <?= $t['forecast_passengers'] ?></td>
                            <td><strong>₱<?= number_format((float)$t['ticket_total'], 2) ?></strong></td>
                            <td>
                                <form action="/ojt-sdpbus/backend/tickets/review_action.php" method="POST" style="display: flex; flex-direction: column; gap: 6px;">
                                    <input type="hidden" name="ticket_id" value="<?= $t['id'] ?>">
                                    <input type="text" name="admin_comment" placeholder="Optional remark / return reason" style="font-size: 0.8rem; padding: 4px 8px;">
                                    <div style="display: flex; gap: 4px;">
                                        <button type="submit" name="action" value="approve" class="btn btn-sm btn-success">Approve</button>
                                        <button type="submit" name="action" value="return" class="btn btn-sm btn-danger">Return</button>
                                    </div>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
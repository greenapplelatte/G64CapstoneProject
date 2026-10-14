<?php
// frontend/staff/my_tickets.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$userId = (int)$_SESSION['user_id'];
$db = Database::getConnection();

$statusFilter = sanitize($_GET['status'] ?? '');

$query = "SELECT t.*, r.route_name, b.bus_code, d.driver_name 
          FROM tickets t 
          LEFT JOIN routes r ON t.route_id = r.id 
          LEFT JOIN buses b ON t.bus_id = b.id 
          LEFT JOIN drivers d ON t.driver_id = d.id 
          WHERE t.user_id = :uid";

$params = [':uid' => $userId];

if (!empty($statusFilter)) {
    $query .= " AND t.review_status = :status";
    $params[':status'] = $statusFilter;
}

$query .= " ORDER BY t.created_at DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = "My Tickets - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>My Submitted Tickets</h1>
        <a href="/ojt-sdpbus/frontend/staff/new_ticket.php" class="btn btn-primary">➕ Create Ticket</a>
    </div>

    <!-- Status Filters -->
    <div class="filter-bar">
        <a href="?" class="btn-filter <?= empty($statusFilter) ? 'active' : '' ?>">All</a>
        <a href="?status=Draft" class="btn-filter <?= $statusFilter === 'Draft' ? 'active' : '' ?>">Drafts</a>
        <a href="?status=Submitted" class="btn-filter <?= $statusFilter === 'Submitted' ? 'active' : '' ?>">Submitted</a>
        <a href="?status=Approved" class="btn-filter <?= $statusFilter === 'Approved' ? 'active' : '' ?>">Approved</a>
        <a href="?status=Returned" class="btn-filter <?= $statusFilter === 'Returned' ? 'active' : '' ?>">Returned</a>
    </div>

    <div class="card margin-top">
        <?php if (empty($tickets)): ?>
            <p class="empty-msg">No tickets found matching the selected criteria.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Date & Shift</th>
                        <th>Route</th>
                        <th>Bus / Driver</th>
                        <th>Total (₱)</th>
                        <th>Review Status</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($t['trip_date']) ?><br><small><?= htmlspecialchars($t['shift']) ?></small></td>
                            <td><?= htmlspecialchars($t['route_name']) ?></td>
                            <td><?= htmlspecialchars($t['bus_code']) ?> / <?= htmlspecialchars($t['driver_name']) ?></td>
                            <td>₱<?= number_format((float)$t['ticket_total'], 2) ?></td>
                            <td>
                                <span class="badge badge-<?= strtolower($t['review_status']) ?>">
                                    <?= htmlspecialchars($t['review_status']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if (in_array($t['review_status'], ['Draft', 'Returned'], true) && (int)$t['billing_closed'] === 0): ?>
                                    <a href="/ojt-sdpbus/frontend/staff/new_ticket.php?edit_id=<?= $t['id'] ?>" class="btn btn-sm btn-warning">Edit</a>
                                <?php else: ?>
                                    <span class="text-muted">Locked</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
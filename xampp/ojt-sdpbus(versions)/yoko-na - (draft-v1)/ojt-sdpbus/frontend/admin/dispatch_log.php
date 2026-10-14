<?php
// frontend/admin/dispatch_log.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();

$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');
$statusFilter = sanitize($_GET['status'] ?? '');

$query = "SELECT t.*, r.route_name, b.bus_code, d.driver_name, u.username
          FROM tickets t
          LEFT JOIN routes r ON t.route_id = r.id
          LEFT JOIN buses b ON t.bus_id = b.id
          LEFT JOIN drivers d ON t.driver_id = d.id
          LEFT JOIN users u ON t.user_id = u.id
          WHERE 1=1";

$params = [];

if (!empty($startDate) && !empty($endDate)) {
    $query .= " AND t.trip_date BETWEEN :start_date AND :end_date";
    $params[':start_date'] = $startDate;
    $params[':end_date'] = $endDate;
}

if (!empty($statusFilter)) {
    $query .= " AND t.review_status = :status";
    $params[':status'] = $statusFilter;
}

$query .= " ORDER BY t.trip_date DESC, t.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll();

$pageTitle = "Trip Dispatch Log - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Master Trip Dispatch Log</h1>
        <a href="/ojt-sdpbus/backend/report/export_csv.php?<?= http_build_query($_GET) ?>" class="btn btn-secondary">📥 Export CSV</a>
    </div>

    <!-- Filter Form -->
    <div class="card margin-top">
        <form method="GET" action="" style="display: flex; gap: 15px; align-items: flex-end; flex-wrap: wrap;">
            <div>
                <label for="start_date">Start Date</label>
                <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($startDate) ?>">
            </div>
            <div>
                <label for="end_date">End Date</label>
                <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($endDate) ?>">
            </div>
            <div>
                <label for="status">Review Status</label>
                <select name="status" id="status">
                    <option value="">All Statuses</option>
                    <option value="Draft" <?= $statusFilter === 'Draft' ? 'selected' : '' ?>>Draft</option>
                    <option value="Submitted" <?= $statusFilter === 'Submitted' ? 'selected' : '' ?>>Submitted</option>
                    <option value="Approved" <?= $statusFilter === 'Approved' ? 'selected' : '' ?>>Approved</option>
                    <option value="Returned" <?= $statusFilter === 'Returned' ? 'selected' : '' ?>>Returned</option>
                </select>
            </div>
            <button type="submit" class="btn btn-primary">Filter</button>
            <a href="?" class="btn btn-outline">Reset</a>
        </form>
    </div>

    <!-- Table -->
    <div class="card margin-top">
        <?php if (empty($tickets)): ?>
            <p class="empty-msg">No dispatch records found matching criteria.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Ticket #</th>
                        <th>Trip Date & Shift</th>
                        <th>Route</th>
                        <th>Bus & Driver</th>
                        <th>Encoder</th>
                        <th>Actual Pax</th>
                        <th>Total</th>
                        <th>Status</th>
                        <th>Billing</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $t): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($t['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars($t['trip_date']) ?><br><small><?= htmlspecialchars($t['shift']) ?></small></td>
                            <td><?= htmlspecialchars($t['route_name']) ?></td>
                            <td><?= htmlspecialchars($t['bus_code']) ?> / <?= htmlspecialchars($t['driver_name']) ?></td>
                            <td>👤 <?= htmlspecialchars($t['username']) ?></td>
                            <td><?= $t['actual_passengers'] ?></td>
                            <td>₱<?= number_format((float)$t['ticket_total'], 2) ?></td>
                            <td><span class="badge badge-<?= strtolower($t['review_status']) ?>"><?= htmlspecialchars($t['review_status']) ?></span></td>
                            <td><?= (int)$t['billing_closed'] === 1 ? '<span class="text-danger">🔒 Closed</span>' : '<span class="text-success">Open</span>' ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
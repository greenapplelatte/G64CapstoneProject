<?php
// frontend/admin/dispatch_log.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();

$startDate = sanitize($_GET['start_date'] ?? '');
$endDate = sanitize($_GET['end_date'] ?? '');
$allowedStatuses = ['All', 'Draft', 'Submitted', 'Approved', 'Returned'];
$statusFilter = sanitize($_GET['status'] ?? 'All');
if (!in_array($statusFilter, $allowedStatuses, true)) {
    $statusFilter = 'All';
}

$query = "SELECT t.*, r.route_name, b.bus_code, d.driver_name,
                 COALESCE(u.username, t.username_snapshot, 'Deleted account') AS username
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

$query .= " ORDER BY t.trip_date DESC, t.id DESC";

$stmt = $db->prepare($query);
$stmt->execute($params);
$tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
$ticketCount = count($tickets);
$passengersCarried = array_sum(array_map(static fn($ticket) => (int)$ticket['actual_passengers'], $tickets));
$approvedRevenue = array_sum(array_map(static fn($ticket) => $ticket['review_status'] === 'Approved' ? (float)$ticket['ticket_total'] : 0, $tickets));
$billingOpenCount = count(array_filter($tickets, static fn($ticket) => $ticket['review_status'] === 'Approved' && (int)$ticket['billing_closed'] === 0));
$statusCounts = ['All' => $ticketCount, 'Draft' => 0, 'Submitted' => 0, 'Approved' => 0, 'Returned' => 0];
foreach ($tickets as $ticket) {
    if (isset($statusCounts[$ticket['review_status']])) {
        $statusCounts[$ticket['review_status']]++;
    }
}
$selectedTicket = $tickets[0] ?? null;

$pageTitle = "Trip Dispatch Log - SDPBUS";
$extraCss = 'dispatch-log.css';
$extraScript = 'dispatch-log.js';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content dispatch-page">
    <div class="dispatch-content">
        <header class="dispatch-page-header">
            <div><h1>Trip Dispatch Log</h1><p>Review trip records, billing status, and ticket history.</p></div>
            <div class="dispatch-header-actions">
                <button type="button" class="dispatch-action-button" id="print-dispatch"><i class="fas fa-print" aria-hidden="true"></i> Print</button>
                <a class="dispatch-action-button" id="dispatch-export-csv" href="/ojt-sdpbus/backend/report/export_csv.php?<?= htmlspecialchars(http_build_query(array_filter(['start_date' => $startDate, 'end_date' => $endDate, 'status' => $statusFilter !== 'All' ? $statusFilter : '']), ENT_QUOTES, 'UTF-8')) ?>"><i class="fas fa-download" aria-hidden="true"></i> Export CSV</a>
            </div>
        </header>

        <section class="dispatch-metrics" aria-label="Trip log summary">
            <article class="dispatch-metric"><span class="dispatch-metric-icon metric-violet"><i class="fas fa-list" aria-hidden="true"></i></span><span><strong><?= $ticketCount ?></strong><small>Tickets in log</small></span></article>
            <article class="dispatch-metric"><span class="dispatch-metric-icon metric-blue"><i class="fas fa-users" aria-hidden="true"></i></span><span><strong><?= number_format($passengersCarried) ?></strong><small>Passengers carried</small></span></article>
            <article class="dispatch-metric"><span class="dispatch-metric-icon metric-green"><i class="fas fa-wallet" aria-hidden="true"></i></span><span><strong>₱<?= number_format($approvedRevenue, 2) ?></strong><small>Approved revenue</small></span></article>
            <article class="dispatch-metric"><span class="dispatch-metric-icon metric-amber"><i class="fas fa-lock" aria-hidden="true"></i></span><span><strong><?= $billingOpenCount ?> tickets</strong><small>Billing still open</small></span></article>
        </section>

        <form method="GET" action="" class="dispatch-filters" id="dispatch-filter-form">
            <label class="dispatch-search"><i class="fas fa-search" aria-hidden="true"></i><span class="visually-hidden">Search tickets, routes, buses, or drivers</span><input type="search" id="dispatch-search" placeholder="Search ticket, route, bus or driver" autocomplete="off"></label>
            <label class="dispatch-date"><span class="visually-hidden">Start date</span><i class="far fa-calendar" aria-hidden="true"></i><input type="date" name="start_date" value="<?= htmlspecialchars($startDate) ?>" aria-label="Start date"></label>
            <label class="dispatch-date"><span class="visually-hidden">End date</span><i class="far fa-calendar" aria-hidden="true"></i><input type="date" name="end_date" value="<?= htmlspecialchars($endDate) ?>" aria-label="End date"></label>
            <input type="hidden" name="status" id="dispatch-status-input" value="<?= htmlspecialchars($statusFilter) ?>">
            <button type="submit" class="dispatch-filter-apply">Apply</button>
            <a href="/ojt-sdpbus/frontend/admin/dispatch_log.php" class="dispatch-filter-reset">Reset</a>
        </form>

        <nav class="dispatch-status-filters" aria-label="Filter tickets by review status" data-initial-status="<?= htmlspecialchars($statusFilter) ?>">
            <?php foreach ($statusCounts as $status => $count): ?>
                <button type="button" class="dispatch-status-filter <?= $statusFilter === $status ? 'is-active' : '' ?>" data-dispatch-status="<?= htmlspecialchars($status) ?>" aria-pressed="<?= $statusFilter === $status ? 'true' : 'false' ?>"><?= htmlspecialchars($status) ?> <span><?= $count ?></span></button>
            <?php endforeach; ?>
        </nav>

        <div class="dispatch-layout">
            <section class="dispatch-panel dispatch-table-panel" aria-label="Trip tickets">
                <div class="dispatch-table-wrap">
                    <table class="dispatch-table">
                        <thead><tr><th>Ticket</th><th>Trip date &amp; shift</th><th>Bus &amp; driver</th><th>Encoder</th><th>Pax</th><th>Total</th><th>Status</th></tr></thead>
                        <tbody id="dispatch-ticket-rows">
                            <?php foreach ($tickets as $index => $ticket): ?>
                                <?php
                                $ticketStatus = (string)$ticket['review_status'];
                                $isBillingClosed = (int)$ticket['billing_closed'] === 1;
                                $selected = $index === 0;
                                ?>
                                <tr class="dispatch-ticket-row <?= $selected ? 'is-selected' : '' ?>" tabindex="0" role="button" aria-pressed="<?= $selected ? 'true' : 'false' ?>"
                                    data-status="<?= htmlspecialchars($ticketStatus) ?>"
                                    data-search="<?= htmlspecialchars(strtolower($ticket['ticket_number'] . ' ' . $ticket['route_name'] . ' ' . $ticket['bus_code'] . ' ' . $ticket['driver_name'] . ' ' . $ticket['username'])) ?>"
                                    data-ticket-number="<?= htmlspecialchars($ticket['ticket_number'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-route="<?= htmlspecialchars($ticket['route_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-trip-date="<?= htmlspecialchars($ticket['trip_date']) ?>"
                                    data-trip-time="<?= htmlspecialchars($ticket['trip_time']) ?>"
                                    data-shift="<?= htmlspecialchars($ticket['shift'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-bus="<?= htmlspecialchars($ticket['bus_code'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-driver="<?= htmlspecialchars($ticket['driver_name'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-encoder="<?= htmlspecialchars($ticket['username'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-passengers="<?= (int)$ticket['actual_passengers'] ?>"
                                    data-forecast="<?= (int)$ticket['forecast_passengers'] ?>"
                                    data-total="<?= number_format((float)$ticket['ticket_total'], 2, '.', '') ?>"
                                    data-trip-status="<?= htmlspecialchars($ticket['trip_status'], ENT_QUOTES, 'UTF-8') ?>"
                                    data-review-status="<?= htmlspecialchars($ticketStatus, ENT_QUOTES, 'UTF-8') ?>"
                                    data-billing-status="<?= $isBillingClosed ? 'Closed' : 'Open' ?>"
                                    data-created-at="<?= htmlspecialchars($ticket['created_at']) ?>"
                                    data-admin-comment="<?= htmlspecialchars((string)($ticket['admin_comment'] ?? ''), ENT_QUOTES, 'UTF-8') ?>">
                                    <td><strong class="dispatch-ticket-number"><?= htmlspecialchars($ticket['ticket_number']) ?></strong><small><?= htmlspecialchars($ticket['route_name']) ?></small></td>
                                    <td><strong><?= htmlspecialchars(date('M j', strtotime($ticket['trip_date']))) ?></strong><small><i class="far fa-calendar" aria-hidden="true"></i> <?= htmlspecialchars($ticket['shift']) ?></small></td>
                                    <td><strong><?= htmlspecialchars($ticket['bus_code']) ?></strong><small><?= htmlspecialchars($ticket['driver_name']) ?></small></td>
                                    <td><span class="dispatch-encoder"><?= htmlspecialchars($ticket['username']) ?></span></td>
                                    <td><strong><?= (int)$ticket['actual_passengers'] ?></strong></td>
                                    <td><strong>₱<?= number_format((float)$ticket['ticket_total'], 2) ?></strong></td>
                                    <td><span class="dispatch-status-badge status-<?= strtolower($ticketStatus) ?>"><i class="fas <?= $ticketStatus === 'Approved' ? 'fa-check' : ($ticketStatus === 'Returned' ? 'fa-rotate-left' : ($ticketStatus === 'Submitted' ? 'fa-clock' : 'fa-file')) ?>" aria-hidden="true"></i><?= htmlspecialchars($ticketStatus) ?></span><small class="dispatch-billing-state <?= $isBillingClosed ? 'is-closed' : '' ?>"><i class="fas <?= $isBillingClosed ? 'fa-lock' : 'fa-lock-open' ?>" aria-hidden="true"></i> Billing <?= $isBillingClosed ? 'closed' : 'open' ?></small></td>
                                </tr>
                            <?php endforeach; ?>
                            <tr id="dispatch-empty-row" hidden><td colspan="7">No tickets match these filters.</td></tr>
                        </tbody>
                    </table>
                </div>
                <footer class="dispatch-table-footer"><span id="dispatch-result-count">Showing <?= $ticketCount ?> of <?= $ticketCount ?> tickets</span><span>Click a row to see its details</span></footer>
            </section>

            <aside class="dispatch-panel dispatch-detail-panel" id="dispatch-detail-panel" aria-label="Selected ticket details" <?= $selectedTicket ? '' : 'hidden' ?>>
                <div class="dispatch-detail-topline"><span class="dispatch-detail-ticket" id="detail-ticket-number"><?= htmlspecialchars($selectedTicket['ticket_number'] ?? '') ?></span><span class="dispatch-status-badge" id="detail-status-badge"><?= htmlspecialchars($selectedTicket['review_status'] ?? '') ?></span></div>
                <h2 id="detail-route-name"><?= htmlspecialchars($selectedTicket['route_name'] ?? '') ?></h2>
                <dl class="dispatch-detail-list">
                    <div><dt>Trip</dt><dd id="detail-trip"></dd></div>
                    <div><dt>Bus</dt><dd id="detail-bus"></dd></div>
                    <div><dt>Driver</dt><dd id="detail-driver"></dd></div>
                    <div><dt>Encoded by</dt><dd id="detail-encoder"></dd></div>
                    <div><dt>Passengers</dt><dd id="detail-passengers"></dd></div>
                    <div><dt>Total fare</dt><dd id="detail-total"></dd></div>
                    <div><dt>Trip status</dt><dd id="detail-trip-status"></dd></div>
                </dl>
                <p class="dispatch-admin-comment" id="detail-admin-comment" hidden></p>
                <h3>Ticket lifecycle</h3>
                <ol class="ticket-lifecycle" id="ticket-lifecycle"></ol>
            </aside>
            <p class="dispatch-no-results" id="dispatch-no-results" hidden>No tickets match these filters.</p>
        </div>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
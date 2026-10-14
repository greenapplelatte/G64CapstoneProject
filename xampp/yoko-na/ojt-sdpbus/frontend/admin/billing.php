<?php
// frontend/admin/billing.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$billingCloseToken = $_SESSION['billing_close_token'] ??= bin2hex(random_bytes(32));
$db = Database::getConnection();

$defaultUnbilledRange = $db->query("SELECT MIN(trip_date) AS start_date, MAX(trip_date) AS end_date
                                   FROM tickets
                                   WHERE review_status = 'Approved' AND billing_closed = 0")->fetch(PDO::FETCH_ASSOC);
$defaultUnbilledStart = $defaultUnbilledRange['start_date'] ?: date('Y-m-01');
$defaultUnbilledEnd = $defaultUnbilledRange['end_date'] ?: date('Y-m-d');
$dateStart = $defaultUnbilledStart;
$dateEnd = $defaultUnbilledEnd;
$dateError = '';
$parseDate = static function (string $value): ?DateTimeImmutable {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $value);
    return $date && $date->format('Y-m-d') === $value ? $date : null;
};

if (isset($_GET['start_date'], $_GET['end_date'])) {
    $requestedStart = (string)$_GET['start_date'];
    $requestedEnd = (string)$_GET['end_date'];
    $parsedStart = $parseDate($requestedStart);
    $parsedEnd = $parseDate($requestedEnd);
    if (!$parsedStart || !$parsedEnd || $parsedStart > $parsedEnd) {
        $dateError = 'Choose a valid date range. The start date must be on or before the end date.';
    } else {
        $dateStart = $requestedStart;
        $dateEnd = $requestedEnd;
    }
}

$closedPeriodForRange = null;
if ($dateError === '') {
    $closedPeriodStmt = $db->prepare('SELECT bp.*, COALESCE(u.username, \'Former admin\') AS closed_by_name
                                      FROM billing_periods bp
                                      LEFT JOIN users u ON u.id = bp.closed_by
                                      WHERE bp.start_date = :start_date AND bp.end_date = :end_date
                                      ORDER BY bp.id DESC LIMIT 1');
    $closedPeriodStmt->execute([':start_date' => $dateStart, ':end_date' => $dateEnd]);
    $closedPeriodForRange = $closedPeriodStmt->fetch(PDO::FETCH_ASSOC) ?: null;
}
$periodIsClosed = $closedPeriodForRange !== null;

$approvedSummary = $db->query("SELECT
    COALESCE(SUM(CASE WHEN billing_closed = 0 THEN ticket_total ELSE 0 END), 0) AS unbilled_total,
    COALESCE(SUM(CASE WHEN billing_closed = 0 THEN 1 ELSE 0 END), 0) AS unbilled_count,
    COALESCE(SUM(CASE WHEN billing_closed = 1 THEN ticket_total ELSE 0 END), 0) AS closed_total,
    COALESCE(SUM(CASE WHEN billing_closed = 1 THEN 1 ELSE 0 END), 0) AS closed_count
    FROM tickets WHERE review_status = 'Approved'")->fetch(PDO::FETCH_ASSOC);
$waitingReviewCount = (int)$db->query("SELECT COUNT(*) FROM tickets WHERE review_status = 'Submitted'")->fetchColumn();
$approvedTotal = (float)$approvedSummary['unbilled_total'] + (float)$approvedSummary['closed_total'];
$billedPercent = $approvedTotal > 0 ? min(100, (int)round(((float)$approvedSummary['closed_total'] / $approvedTotal) * 100)) : 0;

$previewTickets = [];
$routeTotals = [];
$previewTotal = 0.0;
$waitingReviewInRange = 0;
if ($dateError === '') {
        $previewStmt = $db->prepare("SELECT t.id, t.ticket_number, t.trip_date, t.shift, t.ticket_total, r.route_name
                                 FROM tickets t
                                 JOIN routes r ON r.id = t.route_id
                                 WHERE t.trip_date BETWEEN :start_date AND :end_date
                                                                     AND t.review_status = 'Approved' AND t.billing_closed = :billing_closed
                                 ORDER BY t.trip_date, t.trip_time, t.ticket_number");
        $previewStmt->execute([
                ':start_date' => $dateStart,
                ':end_date' => $dateEnd,
                ':billing_closed' => $periodIsClosed ? 1 : 0
        ]);
    $previewTickets = $previewStmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($previewTickets as $ticket) {
        $previewTotal += (float)$ticket['ticket_total'];
    }

        $waitingReviewStmt = $db->prepare("SELECT COUNT(*) FROM tickets
                                                                             WHERE trip_date BETWEEN :start_date AND :end_date
                                                                                 AND review_status = 'Submitted'");
    $waitingReviewStmt->execute([':start_date' => $dateStart, ':end_date' => $dateEnd]);
    $waitingReviewInRange = (int)$waitingReviewStmt->fetchColumn();

    $routeTotalsStmt = $db->prepare("SELECT r.route_name, COUNT(t.id) AS ticket_count,
                                            COALESCE(SUM(t.ticket_total), 0) AS total_amount
                                     FROM tickets t
                                     JOIN routes r ON r.id = t.route_id
                                     WHERE t.review_status = 'Approved'
                                     GROUP BY r.id, r.route_name
                                     ORDER BY total_amount DESC, r.route_name COLLATE NOCASE");
    $routeTotalsStmt->execute();
    $routeTotals = $routeTotalsStmt->fetchAll(PDO::FETCH_ASSOC);
}

$periods = $db->query("SELECT bp.*, COALESCE(u.username, 'Former admin') AS closed_by_name
                       FROM billing_periods bp
                       LEFT JOIN users u ON u.id = bp.closed_by
                       ORDER BY bp.closed_at DESC, bp.id DESC")->fetchAll(PDO::FETCH_ASSOC);

$pageTitle = "Billing & Financial Reports - SDPBUS";
$extraCss = 'billing.css';
$extraScript = 'billing.js';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content billing-page">
    <div class="billing-content">
        <header class="billing-page-header">
            <div>
                <h1>Billing &amp; Reports</h1>
                <p>Review approved tickets, close billing periods, and track locked revenue.</p>
            </div>
            <div class="billing-page-actions">
                <button type="button" class="billing-toolbar-button" id="print-billing-report"><i class="fas fa-print" aria-hidden="true"></i> Print</button>
                <a class="billing-toolbar-button" href="/xampp/yoko-na/ojt-sdpbus/backend/report/export_billing.php?start_date=<?= urlencode($dateStart) ?>&amp;end_date=<?= urlencode($dateEnd) ?>"><i class="fas fa-download" aria-hidden="true"></i> Export CSV</a>
            </div>
        </header>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="billing-notice notice-error" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><p><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p></div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="billing-notice notice-success" role="status"><i class="fas fa-check-circle" aria-hidden="true"></i><p><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p></div>
        <?php endif; ?>
        <?php if ($dateError !== ''): ?>
            <div class="billing-notice notice-error" role="alert"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><p><?= htmlspecialchars($dateError) ?></p></div>
        <?php endif; ?>

        <section class="billing-metrics" aria-label="Billing summary">
            <article class="billing-metric">
                <span class="billing-metric-icon metric-amber"><i class="fas fa-wallet" aria-hidden="true"></i></span>
                <span><strong>₱<?= number_format((float)$approvedSummary['unbilled_total'], 2) ?></strong><small>Unbilled approved revenue</small><small><?= (int)$approvedSummary['unbilled_count'] ?> tickets</small></span>
            </article>
            <article class="billing-metric">
                <span class="billing-metric-icon metric-green"><i class="fas fa-lock" aria-hidden="true"></i></span>
                <span><strong>₱<?= number_format((float)$approvedSummary['closed_total'], 2) ?></strong><small>Closed billing revenue</small><small><?= (int)$approvedSummary['closed_count'] ?> tickets</small></span>
            </article>
            <article class="billing-metric">
                <span class="billing-metric-icon metric-blue"><i class="fas fa-hourglass-half" aria-hidden="true"></i></span>
                <span><strong><?= $waitingReviewCount ?></strong><small>Waiting for review</small><small>Not counted in billing</small></span>
            </article>
            <article class="billing-metric billed-progress-metric">
                <span class="billing-progress-label">Billed so far</span>
                <strong><?= $billedPercent ?>%</strong>
                <span class="billing-progress-track" role="progressbar" aria-label="Approved revenue billed" aria-valuemin="0" aria-valuemax="100" aria-valuenow="<?= $billedPercent ?>"><span style="width: <?= $billedPercent ?>%"></span></span>
                <small>of approved revenue is locked</small>
            </article>
        </section>

        <div class="billing-layout">
            <section class="billing-panel close-period-panel" aria-labelledby="close-period-title">
                <header class="billing-panel-heading">
                    <h2 id="close-period-title"><?= $periodIsClosed ? 'Closed billing period' : 'Close billing period' ?></h2>
                    <p><?= $periodIsClosed ? 'This billing period is locked. Review the final ticket details below or print the receipt.' : 'Closing a period permanently locks all approved tickets inside the dates, so totals cannot change after the bill is sent.' ?></p>
                </header>

                <?php if ($periodIsClosed): ?>
                    <div class="billing-locked-banner"><i class="fas fa-lock" aria-hidden="true"></i><span>Locked <?= htmlspecialchars(date('M j, Y g:i A', strtotime($closedPeriodForRange['closed_at']))) ?> by <?= htmlspecialchars($closedPeriodForRange['closed_by_name']) ?></span></div>
                <?php else: ?>
                    <ol class="billing-stepper" aria-label="Billing close steps">
                        <li class="is-complete"><span>1</span> Choose dates</li>
                        <li class="is-current"><span>2</span> Review tickets</li>
                        <li><span>3</span> Confirm and lock</li>
                    </ol>
                <?php endif; ?>

                <form method="GET" action="" class="billing-date-form" id="billing-date-form" data-outstanding-start="<?= htmlspecialchars($defaultUnbilledStart) ?>" data-outstanding-end="<?= htmlspecialchars($defaultUnbilledEnd) ?>">
                    <div class="billing-date-fields">
                        <div class="billing-field">
                            <label for="start_date">Period start date *</label>
                            <input type="date" id="start_date" name="start_date" value="<?= htmlspecialchars($dateStart) ?>" required>
                        </div>
                        <div class="billing-field">
                            <label for="end_date">Period end date *</label>
                            <input type="date" id="end_date" name="end_date" value="<?= htmlspecialchars($dateEnd) ?>" required>
                        </div>
                        <button type="submit" class="billing-review-button">Review tickets</button>
                    </div>
                    <div class="billing-date-presets" role="group" aria-label="Date range shortcuts">
                        <button type="button" data-date-range="outstanding" class="<?= !isset($_GET['start_date'], $_GET['end_date']) ? 'is-active' : '' ?>">Outstanding</button>
                        <button type="button" data-date-range="this-week">This week</button>
                        <button type="button" data-date-range="last-week">Last week</button>
                        <button type="button" data-date-range="this-month">This month</button>
                        <button type="button" class="<?= isset($_GET['start_date'], $_GET['end_date']) ? 'is-active' : '' ?>" data-date-range="custom">Custom</button>
                    </div>
                </form>

                <form action="/xampp/yoko-na/ojt-sdpbus/backend/report/close_period.php" method="POST" class="billing-close-form" id="billing-close-form">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($billingCloseToken, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="start_date" value="<?= htmlspecialchars($dateStart) ?>">
                    <input type="hidden" name="end_date" value="<?= htmlspecialchars($dateEnd) ?>">
                    <input type="hidden" name="expected_ticket_count" value="<?= $periodIsClosed ? 0 : count($previewTickets) ?>">
                    <div class="billing-ticket-preview">
                        <?php if (empty($previewTickets)): ?>
                            <p class="billing-preview-empty"><?= $periodIsClosed ? 'No locked ticket details were found for this period.' : 'No approved, unbilled tickets are in this date range.' ?></p>
                        <?php else: ?>
                            <?php foreach ($previewTickets as $ticket): ?>
                                <article class="billing-ticket-row">
                                    <span><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong><small><?= date('M j', strtotime($ticket['trip_date'])) ?> · <?= htmlspecialchars($ticket['shift']) ?> · <?= htmlspecialchars($ticket['route_name']) ?></small></span>
                                    <strong>₱<?= number_format((float)$ticket['ticket_total'], 2) ?></strong>
                                </article>
                            <?php endforeach; ?>
                        <?php endif; ?>
                        <div class="billing-total-row"><span><?= $periodIsClosed ? 'Closed total' : 'Total to lock' ?></span><strong>₱<?= number_format($periodIsClosed ? (float)$closedPeriodForRange['total_amount'] : $previewTotal, 2) ?></strong></div>
                    </div>

                    <?php if ($periodIsClosed): ?>
                        <div class="billing-status-note note-success"><i class="fas fa-lock" aria-hidden="true"></i><span>This period is closed. The locked ticket records and total below are final.</span></div>
                    <?php else: ?>
                        <div class="billing-status-note note-success"><i class="fas fa-check" aria-hidden="true"></i><span><?= $waitingReviewInRange ?> tickets in this date range are still waiting for review, so nothing is left out of the bill.</span></div>
                        <div class="billing-status-note note-danger"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i><strong>This cannot be undone. Locked tickets can no longer be edited or deleted.</strong></div>
                    <?php endif; ?>

                    <?php if (!$periodIsClosed): ?>
                        <label class="billing-confirm-check">
                            <input type="checkbox" id="confirm-lock" required <?= empty($previewTickets) || $dateError !== '' ? 'disabled' : '' ?>>
                            <span>I understand that these <?= count($previewTickets) ?> ticket(s) will be locked.</span>
                        </label>
                        <div class="billing-form-actions">
                            <button type="submit" class="billing-close-button" id="billing-close-button" disabled><i class="fas fa-lock" aria-hidden="true"></i> Close &amp; lock period</button>
                            <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php" class="billing-cancel-button">Cancel</a>
                        </div>
                    <?php endif; ?>
                </form>
            </section>

            <aside class="billing-side-column">
                <section class="billing-panel route-revenue-panel" aria-labelledby="route-revenue-title">
                    <header class="billing-panel-heading">
                        <h2 id="route-revenue-title">Approved revenue by route</h2>
                        <p>Unbilled and closed, all time</p>
                    </header>
                    <?php if (empty($routeTotals)): ?>
                        <p class="billing-empty-side">No approved ticket revenue yet.</p>
                    <?php else: ?>
                        <div class="route-revenue-list">
                            <?php foreach ($routeTotals as $routeTotal): ?>
                                <?php $routeShare = $approvedTotal > 0 ? min(100, ((float)$routeTotal['total_amount'] / $approvedTotal) * 100) : 0; ?>
                                <article class="route-revenue-row">
                                    <div><strong><?= htmlspecialchars($routeTotal['route_name']) ?></strong><b>₱<?= number_format((float)$routeTotal['total_amount'], 2) ?></b></div>
                                    <span class="route-revenue-track"><span style="width: <?= number_format($routeShare, 2, '.', '') ?>%"></span></span>
                                    <small><?= (int)$routeTotal['ticket_count'] ?> trips · ₱<?= number_format((float)$routeTotal['total_amount'] / max(1, (int)$routeTotal['ticket_count']), 2) ?> per trip</small>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>

                <section class="billing-panel closed-periods-panel" aria-labelledby="closed-periods-title">
                    <header class="billing-panel-heading">
                        <h2 id="closed-periods-title">Closed billing periods</h2>
                        <p>History of locked periods</p>
                    </header>
                    <?php if (empty($periods)): ?>
                        <div class="billing-periods-empty"><span><i class="fas fa-lock" aria-hidden="true"></i></span><strong>No periods closed yet</strong><small>Closed billing periods will be listed here with their totals.</small></div>
                    <?php else: ?>
                        <div class="closed-period-list">
                            <?php foreach ($periods as $period): ?>
                                <article class="closed-period-row">
                                    <div><strong><?= htmlspecialchars(date('M j, Y', strtotime($period['start_date']))) ?> – <?= htmlspecialchars(date('M j, Y', strtotime($period['end_date']))) ?></strong><small><?= (int)$period['ticket_count'] ?> tickets · Closed by <?= htmlspecialchars($period['closed_by_name']) ?></small></div>
                                    <b>₱<?= number_format((float)$period['total_amount'], 2) ?></b>
                                    <a class="closed-period-print-link" href="?start_date=<?= urlencode($period['start_date']) ?>&amp;end_date=<?= urlencode($period['end_date']) ?>&amp;print=1" aria-label="Print receipt for this billing period" title="Print receipt"><i class="fas fa-print" aria-hidden="true"></i></a>
                                </article>
                            <?php endforeach; ?>
                        </div>
                    <?php endif; ?>
                </section>
            </aside>
        </div>
    </div>
</main>

<article class="billing-print-receipt" id="billing-receipt" aria-label="Billing receipt">
    <header class="receipt-brand">
        <span class="receipt-brand-icon"><i class="fas fa-bus" aria-hidden="true"></i></span>
        <div>
            <strong>Shindengen Philippines Corporation</strong>
            <small>SDPBUS Dispatching &amp; Ticketing System</small>
        </div>
    </header>
    <div class="receipt-title-row">
        <div><h1>Billing Receipt</h1><p class="receipt-subtitle">Approved trip statement</p></div>
        <span class="receipt-status <?= $periodIsClosed ? 'receipt-status-locked' : '' ?>"><?= $periodIsClosed ? 'LOCKED' : 'PREVIEW · NOT LOCKED' ?></span>
    </div>
    <dl class="receipt-meta">
        <div><dt>Statement reference</dt><dd>BILL-<?= date('Ymd', strtotime($dateStart)) ?>-<?= date('Ymd', strtotime($dateEnd)) ?></dd></div>
        <div><dt>Billing period</dt><dd><?= htmlspecialchars(date('M j, Y', strtotime($dateStart))) ?> – <?= htmlspecialchars(date('M j, Y', strtotime($dateEnd))) ?></dd></div>
        <div><dt>Issued</dt><dd><?= htmlspecialchars(date('M j, Y g:i A')) ?></dd></div>
    </dl>
    <section class="receipt-lines" aria-label="Approved ticket details">
        <?php if (empty($previewTickets)): ?>
            <p class="receipt-empty">No approved, unbilled tickets in this period.</p>
        <?php else: ?>
            <table class="receipt-ticket-table">
                <thead><tr><th>Ticket no.</th><th>Trip date / shift</th><th>Route</th><th>Amount</th></tr></thead>
                <tbody>
                    <?php foreach ($previewTickets as $ticket): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($ticket['ticket_number']) ?></strong></td>
                            <td><?= htmlspecialchars(date('M j, Y', strtotime($ticket['trip_date']))) ?><small><?= htmlspecialchars($ticket['shift']) ?></small></td>
                            <td><?= htmlspecialchars($ticket['route_name']) ?></td>
                            <td class="receipt-amount">₱<?= number_format((float)$ticket['ticket_total'], 2) ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </section>
    <div class="receipt-total">
        <span><small><?= count($previewTickets) ?> approved ticket(s)</small><strong>Total amount due</strong></span>
        <b>₱<?= number_format($periodIsClosed ? (float)$closedPeriodForRange['total_amount'] : $previewTotal, 2) ?></b>
    </div>
    <footer class="receipt-footer">
        <p><?= $periodIsClosed ? 'This billing period is closed. The ticket records and total are final.' : 'This document is a billing preview. Amounts become final when the period is closed.' ?></p>
        <div>Generated by SDPBUS · <?= htmlspecialchars(date('Y-m-d H:i:s')) ?></div>
    </footer>
</article>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
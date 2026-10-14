<?php
// frontend/admin/activity_log.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$activityClearToken = $_SESSION['activity_clear_token'] ??= bin2hex(random_bytes(32));
$db = Database::getConnection();
$stmt = $db->query("SELECT a.*, COALESCE(u.username, a.username_snapshot, 'Deleted account') AS username
                    FROM activity_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.created_at DESC, a.id DESC LIMIT 100");
$logs = $stmt->fetchAll();

$timezone = new DateTimeZone(date_default_timezone_get());
$today = new DateTimeImmutable('today', $timezone);
$todayKey = $today->format('Y-m-d');
$yesterdayKey = $today->modify('-1 day')->format('Y-m-d');
$groups = [];
$categoryCounts = ['all' => 0, 'auth' => 0, 'tickets' => 0, 'master' => 0];

foreach ($logs as $log) {
    $action = (string)($log['action_type'] ?? 'Activity');
    $actionLower = strtolower($action);
    $details = (string)($log['details'] ?? '');
    $username = $log['username'] ?? 'System';
    $timestamp = new DateTimeImmutable((string)$log['created_at'], new DateTimeZone('UTC'));
    $localTimestamp = $timestamp->setTimezone($timezone);
    $dayKey = $localTimestamp->format('Y-m-d');

    if (str_contains($actionLower, 'login') || str_contains($actionLower, 'logout')) {
        $category = 'auth';
        $icon = str_contains($actionLower, 'logout') ? 'fa-arrow-right-from-bracket' : 'fa-arrow-right-to-bracket';
        $eventSummary = str_contains($actionLower, 'logout') ? 'signed out' : 'signed in';
    } elseif (str_contains($actionLower, 'ticket')) {
        $category = 'tickets';
        $icon = 'fa-ticket';
        if (stripos($action . ' ' . $details, 'approv') !== false) {
            $eventSummary = 'approved a ticket';
        } elseif (stripos($action . ' ' . $details, 'submit') !== false) {
            $eventSummary = 'submitted a ticket';
        } else {
            $eventSummary = 'updated a ticket';
        }
    } elseif (preg_match('/\b(route|bus|driver)\b/i', $action) === 1) {
        $category = 'master';
        $icon = 'fa-database';
        $eventSummary = stripos($action, 'create') !== false ? 'added master data' : 'updated master data';
    } else {
        $category = 'other';
        $icon = 'fa-clock-rotate-left';
        $eventSummary = $action;
    }

    $log['username'] = $username;
    $log['category'] = $category;
    $log['icon'] = $icon;
    $log['event_summary'] = $eventSummary;
    $log['display_time'] = $localTimestamp->format('H:i');
    $log['timestamp_iso'] = $timestamp->format('Y-m-d\TH:i:s\Z');
    $log['day_key'] = $dayKey;

    $groups[$dayKey]['label'] = $dayKey === $todayKey
        ? 'Today'
        : ($dayKey === $yesterdayKey ? 'Yesterday' : $localTimestamp->format('l, F j'));
    $groups[$dayKey]['date'] = $localTimestamp->format('l, F j');
    $groups[$dayKey]['logs'][] = $log;

    $categoryCounts['all']++;
    if (isset($categoryCounts[$category])) {
        $categoryCounts[$category]++;
    }
}

$userActivityStmt = $db->query("SELECT COALESCE(u.username, a.username_snapshot, 'Deleted account') AS username,
                        MAX(u.profile_image) AS profile_image,
                                       COUNT(a.id) AS activity_count
                                FROM activity_logs a
                                LEFT JOIN users u ON a.user_id = u.id
                    GROUP BY COALESCE(u.username, a.username_snapshot, 'Deleted account') COLLATE NOCASE
                                ORDER BY activity_count DESC, username COLLATE NOCASE ASC
                                LIMIT 4");
$mostActiveUsers = $userActivityStmt->fetchAll(PDO::FETCH_ASSOC);
$mostActiveCount = max(array_column($mostActiveUsers, 'activity_count') ?: [1]);
$latestActivityId = (int)$db->query('SELECT COALESCE(MAX(id), 0) FROM activity_logs')->fetchColumn();
$pageTitle = 'Activity Audit Log - SDPBUS';
$extraCss = 'activity-log.css';
$extraScript = 'activity-log.js';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="activity-page-header">
        <h1>Activity Log</h1>
        <p>A running timeline of every sign-in, ticket action and change.</p>
    </div>

    <div class="activity-layout" data-latest-activity-id="<?= $latestActivityId ?>">
        <section class="activity-panel activity-timeline-panel" aria-labelledby="activityHeading">
            <div class="activity-panel-heading">
                <span class="activity-heading-icon"><i class="fa-solid fa-bus" aria-hidden="true"></i></span>
                <div>
                    <h1 id="activityHeading">Latest activity at the top</h1>
                    <p>Recent system events from all accounts</p>
                </div>
            </div>

            <div class="activity-timeline" id="activityTimeline">
                <?php if (empty($groups)): ?>
                    <p class="activity-empty">No activity log entries captured yet.</p>
                <?php else: ?>
                    <?php foreach ($groups as $group): ?>
                        <section class="activity-day-group">
                            <div class="activity-day-heading">
                                <strong><?= htmlspecialchars($group['label']) ?></strong>
                                <span><?= htmlspecialchars($group['date']) ?> · <?= count($group['logs']) ?> events</span>
                            </div>
                            <div class="activity-event-list">
                                <?php foreach ($group['logs'] as $log): ?>
                                    <?php
                                    $username = (string)$log['username'];
                                    $initials = strtoupper(substr($username, 0, 2));
                                    $searchText = strtolower(implode(' ', [
                                        $username,
                                        $log['action_type'] ?? '',
                                        $log['event_summary'],
                                        $log['ticket_number'] ?? '',
                                        $log['details'] ?? ''
                                    ]));
                                    ?>
                                    <article class="activity-event" data-category="<?= htmlspecialchars($log['category']) ?>" data-search="<?= htmlspecialchars($searchText, ENT_QUOTES, 'UTF-8') ?>">
                                        <time class="activity-event-time" datetime="<?= htmlspecialchars($log['timestamp_iso'], ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($log['display_time']) ?></time>
                                        <span class="activity-event-marker activity-marker-<?= htmlspecialchars($log['category']) ?>" aria-hidden="true">
                                            <i class="fa-solid <?= htmlspecialchars($log['icon']) ?>"></i>
                                        </span>
                                        <div class="activity-event-content">
                                            <div class="activity-event-summary">
                                                <strong><?= htmlspecialchars($username) ?></strong>
                                                <span><?= htmlspecialchars($log['event_summary']) ?></span>
                                                <?php if (!empty($log['ticket_number'])): ?>
                                                    <span class="activity-ticket-number"><?= htmlspecialchars($log['ticket_number']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                            <div class="activity-event-meta">
                                                <span class="activity-event-type activity-type-<?= htmlspecialchars($log['category']) ?>">
                                                    <i class="fa-solid <?= htmlspecialchars($log['icon']) ?>" aria-hidden="true"></i>
                                                    <?= htmlspecialchars($log['action_type'] ?? 'Activity') ?>
                                                </span>
                                                <?php if (!empty($log['details'])): ?>
                                                    <span class="activity-event-details"><?= htmlspecialchars($log['details']) ?></span>
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </article>
                                <?php endforeach; ?>
                            </div>
                        </section>
                    <?php endforeach; ?>
                <?php endif; ?>
                <p class="activity-no-results" id="activityNoResults" hidden>No events match your filters.</p>
            </div>
            <nav class="activity-pagination" id="activityPagination" aria-label="Activity pages"<?= empty($logs) ? ' hidden' : '' ?>>
                <span class="activity-page-summary" id="activityPageSummary" aria-live="polite"></span>
                <div class="activity-page-controls">
                    <button type="button" class="activity-page-button" id="activityPrevious" aria-label="Previous page">
                        <i class="fa-solid fa-chevron-left" aria-hidden="true"></i> Previous
                    </button>
                    <div class="activity-page-numbers" id="activityPageNumbers" aria-label="Page numbers"></div>
                    <button type="button" class="activity-page-button" id="activityNext" aria-label="Next page">
                        Next <i class="fa-solid fa-chevron-right" aria-hidden="true"></i>
                    </button>
                </div>
            </nav>
            <div class="activity-timeline-footer">Most recent activity appears first.</div>
        </section>

        <aside class="activity-sidebar" aria-label="Activity tools and summaries">
            <section class="activity-panel activity-filter-panel" aria-labelledby="filterHeading">
                <h2 id="filterHeading">Filter events</h2>
                <div class="activity-filter-list" role="group" aria-label="Filter events by type">
                    <button type="button" class="activity-filter is-active" data-activity-filter="all" aria-pressed="true">
                        <span>All activity</span><span><?= $categoryCounts['all'] ?></span>
                    </button>
                    <button type="button" class="activity-filter" data-activity-filter="auth" aria-pressed="false">
                        <span>Sign-ins and sign-outs</span><span><?= $categoryCounts['auth'] ?></span>
                    </button>
                    <button type="button" class="activity-filter" data-activity-filter="tickets" aria-pressed="false">
                        <span>Ticket actions</span><span><?= $categoryCounts['tickets'] ?></span>
                    </button>
                    <button type="button" class="activity-filter" data-activity-filter="master" aria-pressed="false">
                        <span>Master data changes</span><span><?= $categoryCounts['master'] ?></span>
                    </button>
                </div>
                <label class="activity-search">
                    <i class="fa-solid fa-magnifying-glass" aria-hidden="true"></i>
                    <span class="visually-hidden">Search activity log</span>
                    <input type="search" id="activitySearch" placeholder="Search the log">
                </label>
                <button type="button" class="activity-export-button" id="exportActivityButton">
                    <i class="fa-solid fa-download" aria-hidden="true"></i> Export CSV
                </button>
            </section>

            <section class="activity-panel activity-users-panel" aria-labelledby="activeUsersHeading">
                <h2 id="activeUsersHeading">Most active users</h2>
                <div class="activity-user-list" id="activityUserList">
                    <?php if (empty($mostActiveUsers)): ?>
                        <p class="activity-muted">No user activity yet.</p>
                    <?php else: ?>
                        <?php foreach ($mostActiveUsers as $activeUser): ?>
                            <?php
                            $activeUsername = (string)$activeUser['username'];
                            $activeCount = (int)$activeUser['activity_count'];
                            $userInitials = strtoupper(substr($activeUsername, 0, 2));
                            $activeUserImageUrl = (string)($activeUser['profile_image'] ?? '');
                            $legacyAvatarPrefix = '/ojt-sdpbus/uploads/avatars/';
                            if (str_starts_with($activeUserImageUrl, $legacyAvatarPrefix)) {
                                $activeUserImageUrl = '/xampp/yoko-na' . $activeUserImageUrl;
                            }
                            ?>
                            <div class="activity-user-row">
                                <?php if ($activeUserImageUrl !== ''): ?>
                                    <img class="activity-user-avatar" src="<?= htmlspecialchars($activeUserImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="" loading="lazy">
                                <?php else: ?>
                                    <span class="activity-user-initials" aria-hidden="true"><?= htmlspecialchars($userInitials) ?></span>
                                <?php endif; ?>
                                <strong><?= htmlspecialchars($activeUsername) ?></strong>
                                <span class="activity-user-bar" aria-label="<?= $activeCount ?> activities">
                                    <span style="width: <?= (int)round(($activeCount / $mostActiveCount) * 100) ?>%"></span>
                                </span>
                                <span class="activity-user-count" data-activity-count><?= $activeCount ?></span>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </section>

            <section class="activity-audit-note" aria-labelledby="auditNoteHeading">
                <h2 id="auditNoteHeading"><i class="fa-solid fa-lock" aria-hidden="true"></i> Read-only audit trail</h2>
                <p>Entries cannot be edited or deleted, so this log stays a reliable record.</p>
            </section>
        </aside>
    </div>
</main>

<dialog class="activity-export-dialog" id="activity-export-confirm" aria-labelledby="activity-export-title" aria-describedby="activity-export-description">
    <span class="activity-export-dialog-icon"><i class="fa-solid fa-download" aria-hidden="true"></i></span>
    <p class="activity-export-eyebrow">Export activity log</p>
    <h2 id="activity-export-title">Prepare CSV download?</h2>
    <p id="activity-export-description">The CSV will include the activity records currently in the log. You can choose whether to keep or clear those records after it is prepared.</p>
    <p class="activity-export-feedback" id="activity-export-feedback" role="status" aria-live="polite"></p>
    <div class="activity-export-actions">
        <button type="button" class="activity-export-cancel" id="cancel-activity-export" autofocus>Cancel</button>
        <button type="button" class="activity-export-confirm" id="confirm-activity-export"><i class="fa-solid fa-download" aria-hidden="true"></i> Continue</button>
    </div>
</dialog>

<dialog class="activity-export-dialog" id="activity-export-choice" data-clear-token="<?= htmlspecialchars($activityClearToken, ENT_QUOTES, 'UTF-8') ?>" aria-labelledby="activity-export-choice-title" aria-describedby="activity-export-choice-description">
    <span class="activity-export-dialog-icon"><i class="fa-solid fa-file-csv" aria-hidden="true"></i></span>
    <p class="activity-export-eyebrow">CSV ready</p>
    <h2 id="activity-export-choice-title">Download and keep or clear the log?</h2>
    <p id="activity-export-choice-description">Choose whether to keep the current activity records after the CSV download starts. Clearing permanently removes the rows and resets the next activity ID to 1.</p>
    <p class="activity-export-feedback" id="activity-clear-feedback" role="status" aria-live="polite"></p>
    <div class="activity-export-actions activity-export-actions-wrap">
        <button type="button" class="activity-export-cancel" id="cancel-activity-choice">Cancel</button>
        <button type="button" class="activity-export-keep" id="download-keep-activity"><i class="fa-solid fa-download" aria-hidden="true"></i> Download &amp; keep</button>
        <button type="button" class="activity-export-clear" id="download-clear-activity"><i class="fa-solid fa-trash-can" aria-hidden="true"></i> Download &amp; clear</button>
    </div>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
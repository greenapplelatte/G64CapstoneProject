<?php
// frontend/admin/activity_log.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$stmt = $db->query("SELECT a.*, u.username 
                    FROM activity_logs a 
                    LEFT JOIN users u ON a.user_id = u.id 
                    ORDER BY a.created_at DESC LIMIT 100");
$logs = $stmt->fetchAll();

$pageTitle = "Activity Audit Log - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>System Activity Audit Log</h1>
    </div>

    <div class="card margin-top">
        <?php if (empty($logs)): ?>
            <p class="empty-msg">No activity log entries captured yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Timestamp</th>
                        <th>User</th>
                        <th>Action Type</th>
                        <th>Ticket #</th>
                        <th>Details</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($logs as $log): ?>
                        <tr>
                            <td><small><?= htmlspecialchars($log['created_at']) ?></small></td>
                            <td><strong>👤 <?= htmlspecialchars($log['username'] ?? 'System') ?></strong></td>
                            <td><span class="badge badge-info"><?= htmlspecialchars($log['action_type']) ?></span></td>
                            <td><?= $log['ticket_number'] ? htmlspecialchars($log['ticket_number']) : '-' ?></td>
                            <td><?= htmlspecialchars($log['details'] ?? '') ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
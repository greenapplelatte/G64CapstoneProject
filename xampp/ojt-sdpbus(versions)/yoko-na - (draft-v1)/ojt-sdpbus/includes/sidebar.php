<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'staff';
$username = $_SESSION['username'] ?? 'User';
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div>
        <div class="sidebar-header">
            <img src="/ojt-sdpbus/assets/images/logo.svg" alt="SDPBUS Logo" width="32" height="32">
            <span class="logo">SDPBUS</span>
        </div>

        <nav class="sidebar-nav">
            <?php if ($role === 'staff'): ?>
                <a href="/ojt-sdpbus/frontend/staff/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <span>📊</span> Dashboard
                </a>
                <a href="/ojt-sdpbus/frontend/staff/todays_trips.php" class="<?= $currentPage === 'todays_trips.php' ? 'active' : '' ?>">
                    <span>🚍</span> Today's Trips
                </a>
                <a href="/ojt-sdpbus/frontend/staff/new_ticket.php" class="<?= $currentPage === 'new_ticket.php' ? 'active' : '' ?>">
                    <span>➕</span> New Trip Ticket
                </a>
                <a href="/ojt-sdpbus/frontend/staff/my_tickets.php" class="<?= $currentPage === 'my_tickets.php' ? 'active' : '' ?>">
                    <span>📋</span> My Tickets
                </a>
            <?php elseif ($role === 'admin'): ?>
                <a href="/ojt-sdpbus/frontend/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <span>📊</span> Dashboard
                </a>
                <a href="/ojt-sdpbus/frontend/admin/trip_schedule.php" class="<?= $currentPage === 'trip_schedule.php' ? 'active' : '' ?>">
                    <span>📅</span> Trip Schedule
                </a>
                <a href="/ojt-sdpbus/frontend/admin/review_queue.php" class="<?= $currentPage === 'review_queue.php' ? 'active' : '' ?>">
                    <span>⏳</span> Review Queue
                </a>
                <a href="/ojt-sdpbus/frontend/admin/dispatch_log.php" class="<?= $currentPage === 'dispatch_log.php' ? 'active' : '' ?>">
                    <span>📑</span> Trip Dispatch Log
                </a>
                <a href="/ojt-sdpbus/frontend/admin/billing.php" class="<?= $currentPage === 'billing.php' ? 'active' : '' ?>">
                    <span>💳</span> Billing & Reports
                </a>
                <a href="/ojt-sdpbus/frontend/admin/master_data.php" class="<?= $currentPage === 'master_data.php' ? 'active' : '' ?>">
                    <span>🗂️</span> Master Data
                </a>
                <a href="/ojt-sdpbus/frontend/admin/user_mgmt.php" class="<?= $currentPage === 'user_mgmt.php' ? 'active' : '' ?>">
                    <span>👥</span> User Management
                </a>
                <a href="/ojt-sdpbus/frontend/admin/activity_log.php" class="<?= $currentPage === 'activity_log.php' ? 'active' : '' ?>">
                    <span>📜</span> Activity Log
                </a>
            <?php endif; ?>
            <a href="/ojt-sdpbus/frontend/shared/profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <span>⚙️</span> Profile
            </a>
        </nav>
    </div>

    <div class="sidebar-footer">
        <span class="user-display">👤 <?= htmlspecialchars($username) ?></span>
        <a href="/ojt-sdpbus/backend/auth/logout.php" class="btn-logout" title="Logout">Logout ➔</a>
    </div>
</aside>
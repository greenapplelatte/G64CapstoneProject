<?php
require_once __DIR__ . '/functions.php';

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$role = $_SESSION['role'] ?? 'staff';
$username = $_SESSION['username'] ?? 'User';
$profileImage = $_SESSION['profile_image'] ?? null;
$profileImageUrl = (string)($profileImage ?? '');
$legacyAvatarPrefix = '/ojt-sdpbus/uploads/avatars/';
if (str_starts_with($profileImageUrl, $legacyAvatarPrefix)) {
    $profileImageUrl = '/xampp/yoko-na' . $profileImageUrl;
}
$currentPage = basename($_SERVER['PHP_SELF']);
?>
<aside class="sidebar">
    <div>
        <!-- Sidebar Brand / Logo Header -->
        <div class="sidebar-header">
            <div class="brand-logo">
                <i class="fa-solid fa-bus"></i>
            </div>
            <div class="brand-info">
                <span class="brand-title">SDPBUS</span>
                <span class="brand-sub">Management System</span>
            </div>
            <button type="button" class="sidebar-toggle-btn" aria-label="Collapse sidebar" title="Collapse sidebar">
                <i class="fa-solid fa-chevron-left" aria-hidden="true"></i>
            </button>
        </div>

        <!-- Navigation Links -->
        <nav class="sidebar-nav">
            <?php if ($role === 'staff'): ?>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie nav-icon"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/todays_trips.php" class="<?= $currentPage === 'todays_trips.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-bus-simple nav-icon"></i>
                    <span>Today's Trips</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/new_ticket.php" class="<?= $currentPage === 'new_ticket.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-ticket-simple nav-icon"></i>
                    <span>New Trip Ticket</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/my_tickets.php" class="<?= $currentPage === 'my_tickets.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-folder-open nav-icon"></i>
                    <span>My Tickets</span>
                </a>
            <?php elseif ($role === 'admin'): ?>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/dashboard.php" class="<?= $currentPage === 'dashboard.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-chart-pie nav-icon"></i>
                    <span>Dashboard</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/trip_schedule.php" class="<?= $currentPage === 'trip_schedule.php' ? 'active' : '' ?>">
                    <i class="fa-regular fa-calendar-days nav-icon"></i>
                    <span>Trip Schedule</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/review_queue.php" class="<?= $currentPage === 'review_queue.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-hourglass-half nav-icon"></i>
                    <span>Review Queue</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/dispatch_log.php" class="<?= $currentPage === 'dispatch_log.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-file-invoice nav-icon"></i>
                    <span>Trip Dispatch Log</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/billing.php" class="<?= $currentPage === 'billing.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-credit-card nav-icon"></i>
                    <span>Billing & Reports</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/master_data.php" class="<?= $currentPage === 'master_data.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-database nav-icon"></i>
                    <span>Master Data</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/user_mgmt.php" class="<?= $currentPage === 'user_mgmt.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-users nav-icon"></i>
                    <span>User Management</span>
                </a>
                <a href="/xampp/yoko-na/ojt-sdpbus/frontend/admin/activity_log.php" class="<?= $currentPage === 'activity_log.php' ? 'active' : '' ?>">
                    <i class="fa-solid fa-list-check nav-icon"></i>
                    <span>Activity Log</span>
                </a>
            <?php endif; ?>

            <a href="/xampp/yoko-na/ojt-sdpbus/frontend/shared/profile.php" class="<?= $currentPage === 'profile.php' ? 'active' : '' ?>">
                <i class="fa-solid fa-gear nav-icon"></i>
                <span>Profile</span>
            </a>
        </nav>
    </div>

    <!-- Sidebar Footer & User Profile Info -->
    <div class="sidebar-footer">
        <span class="user-display">
            <?php if ($profileImageUrl !== ''): ?>
                <img class="sidebar-user-avatar" src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="<?= htmlspecialchars($username, ENT_QUOTES, 'UTF-8') ?> profile photo">
            <?php else: ?>
                <span class="sidebar-user-avatar sidebar-user-avatar-fallback" aria-hidden="true">
                    <i class="fa-solid fa-user-gear"></i>
                </span>
            <?php endif; ?>
            <span class="sidebar-user-name"><?= htmlspecialchars($username) ?></span>
        </span>
        <a href="/xampp/yoko-na/ojt-sdpbus/backend/auth/logout.php" class="btn-logout" title="Logout">
            Logout <i class="fa-solid fa-arrow-right-from-bracket"></i>
        </a>
    </div>
</aside>

<header class="app-topbar">
    <div class="app-topbar-actions">
        <div class="app-topbar-clock" aria-label="Current date and time">
            <span class="app-topbar-date" id="appDate"></span>
            <time class="app-topbar-time" id="appTime"></time>
        </div>
        <a class="app-topbar-profile" href="/xampp/yoko-na/ojt-sdpbus/frontend/shared/profile.php" aria-label="Open Account Profile and Security">
            <?php if ($profileImageUrl !== ''): ?>
                <img src="<?= htmlspecialchars($profileImageUrl, ENT_QUOTES, 'UTF-8') ?>" alt="">
            <?php else: ?>
                <span class="app-topbar-avatar-fallback" aria-hidden="true"><i class="fa-solid fa-user-gear"></i></span>
            <?php endif; ?>
            <span class="app-topbar-identity">
                <strong><?= htmlspecialchars($username) ?></strong>
                <span><?= htmlspecialchars(ucfirst($role)) ?></span>
            </span>
            <i class="fa-solid fa-chevron-right app-topbar-profile-arrow" aria-hidden="true"></i>
        </a>
    </div>
</header>
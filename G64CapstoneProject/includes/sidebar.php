<?php
// Ensure session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = $_SESSION['role'] ?? 'supervisor';
$full_name    = $_SESSION['full_name'] ?? 'Supervisor1';

// Calculate User Initials for Avatar
$name_parts = explode(' ', trim($full_name));
$initials = strtoupper(substr($name_parts[0], 0, 1));
if (count($name_parts) > 1) {
    $initials .= strtoupper(substr(end($name_parts), 0, 1));
}

// Format Role Display Label
$role_labels = [
    'manager'    => 'Plant Manager',
    'supervisor' => 'Supervisor',
    'staff'      => 'Production Staff'
];
$display_role = $role_labels[$user_role] ?? ucfirst($user_role);

// Helper function to check if user role is allowed
function can_access($allowed_roles, $current_role) {
    return in_array($current_role, $allowed_roles, true);
}
?>

<aside class="sidebar">
    <!-- Brand Header -->
    <div class="brand">
        <span class="brand-logo">MVE</span>
        <div class="brand-info">
            <strong>Masters Vineyard</strong>
            <small>Enterprise</small>
        </div>
    </div>

    <!-- Navigation Area -->
    <nav class="sidebar-nav">
        
        <!-- SECTION 1: PRODUCTION FLOOR -->
        <div class="section-label">PRODUCTION FLOOR</div>
        
        <!-- Dashboard -->
        <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/dashboard.php" 
           class="nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
            <span class="nav-text">Dashboard</span>
        </a>

        <!-- Line & Session Control (Supervisor Only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/line_control.php" 
               class="nav-item <?= ($current_page === 'line_control.php') ? 'active' : '' ?>">
                <span class="nav-text">Line & Session Control</span>
            </a>
        <?php endif; ?>

        <!-- Defect Queue & Review (Staff & Supervisor) -->
        <?php if (can_access(['staff', 'supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/defect_reporting.php" 
               class="nav-item <?= ($current_page === 'defect_reporting.php') ? 'active' : '' ?>">
                <span class="nav-text"><?= ($user_role === 'supervisor') ? 'Defect Queue & Review' : 'Defect Reporting' ?></span>
                <?php if ($user_role === 'supervisor'): ?>
                    <span style="background:#f0883e; color:#000; font-weight:bold; font-size:0.7rem; padding:1px 6px; border-radius:10px; margin-left:auto;">2</span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- Access Requests (Supervisor, read-only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/access_requests.php"
               class="nav-item <?= ($current_page === 'access_requests.php') ? 'active' : '' ?>">
                <span class="nav-text">Access Requests</span>
            </a>
        <?php endif; ?>

        <!-- Production Analytics (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/analytics.php" 
               class="nav-item <?= ($current_page === 'analytics.php') ? 'active' : '' ?>">
                <span class="nav-text">Production Analytics</span>
            </a>
        <?php endif; ?>


        <!-- SECTION 2: SHIFTS AND RECORDS -->
        <div class="section-label">SHIFTS AND RECORDS</div>

        <!-- My Activity Logs (Staff Only) -->
        <?php if (can_access(['staff'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/staff/activity_logs.php" 
               class="nav-item <?= ($current_page === 'activity_logs.php') ? 'active' : '' ?>">
                <span class="nav-text">My Activity Logs</span>
            </a>
        <?php endif; ?>

        <!-- Shift Yield Reports (Supervisor Only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/shift_yield.php" 
               class="nav-item <?= ($current_page === 'shift_yield.php') ? 'active' : '' ?>">
                <span class="nav-text">Shift Yield Reports</span>
            </a>
        <?php endif; ?>

        <!-- Master Data (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/master_data.php" 
               class="nav-item <?= ($current_page === 'master_data.php') ? 'active' : '' ?>">
                <span class="nav-text">Master Data</span>
            </a>
        <?php endif; ?>


        <!-- SECTION 3: ACCOUNT -->
        <div class="section-label">ACCOUNT</div>

        <!-- User Management (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/users.php" 
               class="nav-item <?= ($current_page === 'users.php') ? 'active' : '' ?>">
                <span class="nav-text">User Management</span>
                <span style="background:#f0883e; color:#000; font-weight:bold; font-size:0.7rem; padding:1px 6px; border-radius:10px; margin-left:auto;">2</span>
            </a>
        <?php endif; ?>

        <!-- System Settings (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/settings.php" 
               class="nav-item <?= ($current_page === 'settings.php') ? 'active' : '' ?>">
                <span class="nav-text">System Settings</span>
            </a>
        <?php endif; ?>

        <!-- Profile (All Roles) -->
        <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/profile.php" 
           class="nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
            <span class="nav-text">Profile</span>
        </a>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-badge">
            <div class="avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <span class="user-name"><?= htmlspecialchars($full_name) ?></span>
                <span class="user-role-label"><?= htmlspecialchars($display_role) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/backend/process_logout.php" class="btn-logout">LOGOUT</a>
    </div>
</aside>
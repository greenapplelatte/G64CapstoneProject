<?php
require_once __DIR__ . '/functions.php';

// Ensure session exists
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$current_page = basename($_SERVER['PHP_SELF']);
$user_role    = $_SESSION['role'] ?? 'supervisor';
$full_name    = $_SESSION['full_name'] ?? 'User';
// Use the persisted user record as the source of truth on every page load.
// The logged-in user ID comes from the server session, never the request.
if (isset($conn) && $conn instanceof mysqli && !empty($_SESSION['user_id'])) {
    try {
        $profileStmt = $conn->prepare('SELECT full_name, department FROM users WHERE id = ? LIMIT 1');
        $sidebarUserId = (int) $_SESSION['user_id'];
        $profileStmt->bind_param('i', $sidebarUserId);
        $profileStmt->execute();
        $sidebarProfile = $profileStmt->get_result()->fetch_assoc();
        $profileStmt->close();
        if ($sidebarProfile) {
            $full_name = $sidebarProfile['full_name'];
            $_SESSION['full_name'] = $full_name;
            $_SESSION['department'] = $sidebarProfile['department'];
        }
    } catch (Throwable $e) {
        error_log('Sidebar profile read failed: ' . $e->getMessage());
    }
}
// Badge counts are cosmetic: if a table is missing or a query fails, show 0 instead of crashing the whole page.
$pending_defect_count = 0;
$pending_access_count = 0;
try {
    if ($user_role === 'supervisor') {
        $pending_defect_count = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM defect_reports WHERE status IN ('Pending', 'Recheck Requested')")['n'] ?? 0);
    } elseif ($user_role === 'manager') {
        $pending_access_count = (int)(db_one($conn, "SELECT COUNT(*) AS n FROM user_access_requests WHERE status = 'Pending'")['n'] ?? 0);
    }
} catch (Throwable $e) {
    error_log('Sidebar badge query failed: ' . $e->getMessage());
}

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

/** Inline SVG icon (24x24 outline set) for sidebar links. Decorative, so hidden from screen readers. */
function nav_icon(string $name): string {
    $paths = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1"/><rect x="14" y="3" width="7" height="5" rx="1"/><rect x="14" y="12" width="7" height="9" rx="1"/><rect x="3" y="16" width="7" height="5" rx="1"/>',
        'line'      => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'defect'    => '<path d="M10.29 3.86 1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'review'    => '<rect x="8" y="2" width="8" height="4" rx="1"/><path d="M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2"/><path d="m9 14 2 2 4-4"/>',
        'access'    => '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><line x1="19" y1="8" x2="19" y2="14"/><line x1="22" y1="11" x2="16" y2="11"/>',
        'analytics' => '<line x1="18" y1="20" x2="18" y2="10"/><line x1="12" y1="20" x2="12" y2="4"/><line x1="6" y1="20" x2="6" y2="14"/>',
        'activity'  => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'report'    => '<path d="M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/>',
        'database'  => '<ellipse cx="12" cy="5" rx="9" ry="3"/><path d="M3 5v14a9 3 0 0 0 18 0V5"/><path d="M3 12a9 3 0 0 0 18 0"/>',
        'users'     => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'settings'  => '<path d="M12.22 2h-.44a2 2 0 0 0-2 2v.18a2 2 0 0 1-1 1.73l-.43.25a2 2 0 0 1-2 0l-.15-.08a2 2 0 0 0-2.73.73l-.22.38a2 2 0 0 0 .73 2.73l.15.1a2 2 0 0 1 1 1.72v.51a2 2 0 0 1-1 1.74l-.15.09a2 2 0 0 0-.73 2.73l.22.38a2 2 0 0 0 2.73.73l.15-.08a2 2 0 0 1 2 0l.43.25a2 2 0 0 1 1 1.73V20a2 2 0 0 0 2 2h.44a2 2 0 0 0 2-2v-.18a2 2 0 0 1 1-1.73l.43-.25a2 2 0 0 1 2 0l.15.08a2 2 0 0 0 2.73-.73l.22-.39a2 2 0 0 0-.73-2.73l-.15-.08a2 2 0 0 1-1-1.74v-.5a2 2 0 0 1 1-1.74l.15-.09a2 2 0 0 0 .73-2.73l-.22-.38a2 2 0 0 0-2.73-.73l-.15.08a2 2 0 0 1-2 0l-.43-.25a2 2 0 0 1-1-1.73V4a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="3"/>',
        'profile'   => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'collapse'  => '<polyline points="11 17 6 12 11 7"/><polyline points="18 17 13 12 18 7"/>',
        'logout'    => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
    ];
    return '<svg class="nav-icon" viewBox="0 0 24 24" aria-hidden="true" focusable="false">' . ($paths[$name] ?? '') . '</svg>';
}
?>

<style>
    .sidebar .nav-item { display: flex; align-items: center; gap: 12px; }
    .sidebar .nav-icon {
        width: 18px; height: 18px; flex: 0 0 18px;
        fill: none; stroke: currentColor; stroke-width: 1.8; stroke-linecap: round; stroke-linejoin: round;
        opacity: 0.8; transition: opacity 0.15s;
    }
    .sidebar .nav-item:hover .nav-icon, .sidebar .nav-item.active .nav-icon { opacity: 1; }
    .sidebar .btn-logout { display: flex; align-items: center; justify-content: center; gap: 8px; }
    .sidebar .btn-logout .nav-icon { opacity: 1; }
    .sidebar .nav-item { position: relative; }

    /* ---- Collapsible sidebar ---- */
    .sidebar { transition: width 0.2s ease; }
    .main-content { transition: margin-left 0.2s ease; }
    @media (prefers-reduced-motion: reduce) { .sidebar, .main-content { transition: none; } }

    /* Icon-only toggle beside Masters Vineyard */
    .sidebar .brand { display: flex; align-items: center; gap: 8px; }
    .sidebar .brand-info { flex: 1; min-width: 0; }
    .sidebar .sidebar-collapse-btn {
        display: inline-flex; align-items: center; justify-content: center;
        flex: 0 0 28px; width: 28px; height: 30px; padding: 0; margin-left: auto;
        border: 1px solid var(--card-border, #30363d);
        border-radius: 7px; background: transparent;
        color: var(--text-secondary, #8b949e); cursor: pointer;
    }
    .sidebar .sidebar-collapse-btn:hover { color: var(--text-primary, #e6edf3); background: rgba(255,255,255,0.07); }
    .sidebar .sidebar-collapse-btn:focus-visible { outline: 2px solid #58a6ff; outline-offset: 2px; }
    .sidebar .sidebar-collapse-btn .nav-icon { width: 16px; height: 16px; flex: 0 0 16px; transition: transform .2s ease; }

    body.sidebar-collapsed .sidebar { width: 72px !important; min-width: 72px !important; overflow-x: hidden; }
    body.sidebar-collapsed .main-content { margin-left: 72px !important; }
    body.sidebar-collapsed .sidebar .brand { justify-content: center; gap: 3px; padding-left: 4px; padding-right: 4px; }
    body.sidebar-collapsed .sidebar .brand-mark { width: 30px !important; height: 30px !important; min-width: 30px !important; flex: 0 0 30px !important; }
    body.sidebar-collapsed .sidebar .brand-mark svg { width: 30px; height: 30px; }
    body.sidebar-collapsed .sidebar .brand-info,
    body.sidebar-collapsed .sidebar .nav-text,
    body.sidebar-collapsed .sidebar .user-info,
    body.sidebar-collapsed .sidebar .logout-text,
    body.sidebar-collapsed .sidebar .collapse-text { display: none; }
    body.sidebar-collapsed .sidebar .section-label {
        height: 1px; margin: 12px 16px; padding: 0; font-size: 0; overflow: hidden;
        background: var(--card-border, #30363d);
    }
    body.sidebar-collapsed .sidebar .nav-item { justify-content: center; padding-left: 0; padding-right: 0; }
    body.sidebar-collapsed .sidebar .nav-badge {
        position: absolute; top: 4px; right: 10px; margin: 0 !important;
        padding: 0 5px !important; font-size: 0.6rem !important;
    }
    body.sidebar-collapsed .sidebar .user-badge { justify-content: center; }
    body.sidebar-collapsed .sidebar .sidebar-collapse-btn { flex: 0 0 25px; width: 25px; height: 28px; margin: 0; padding: 0; }
    body.sidebar-collapsed .sidebar .sidebar-collapse-btn .nav-icon { transform: rotate(180deg); }
    body.sidebar-collapsed .sidebar .btn-logout { padding-left: 0; padding-right: 0; }

    /* Polished compact sidebar: full-size, uncropped brand emblem */
    .sidebar .brand-mark,
    .sidebar .brand-mark svg { flex-shrink: 0; display: block; }
    .sidebar .sidebar-collapse-btn { flex-shrink: 0; }
    body.sidebar-collapsed .sidebar {
        width: 84px !important;
        min-width: 84px !important;
        max-width: 84px !important;
        overflow-x: hidden;
    }
    body.sidebar-collapsed .main-content { margin-left: 84px !important; }
    body.sidebar-collapsed .sidebar .brand {
        display: flex !important;
        flex-direction: column !important;
        align-items: center !important;
        justify-content: center !important;
        gap: 10px !important;
        padding: 16px 8px 13px !important;
        box-sizing: border-box;
        width: 100%;
    }
    body.sidebar-collapsed .sidebar .brand-mark {
        width: 42px !important; height: 42px !important;
        min-width: 42px !important; flex: 0 0 42px !important;
        margin: 0 !important; overflow: visible !important;
    }
    body.sidebar-collapsed .sidebar .brand-mark svg {
        width: 42px !important; height: 42px !important;
        max-width: none !important; display: block !important;
    }
    body.sidebar-collapsed .sidebar .sidebar-collapse-btn {
        width: 34px !important; height: 28px !important;
        min-width: 34px !important; flex: 0 0 28px !important;
        padding: 0 !important; margin: 0 !important;
        border-radius: 7px;
        background: rgba(255,255,255,.035);
    }
    body.sidebar-collapsed .sidebar .sidebar-nav { padding-left: 9px !important; padding-right: 9px !important; }
    body.sidebar-collapsed .sidebar .nav-item {
        width: 46px !important; height: 44px !important;
        min-width: 46px !important;
        box-sizing: border-box;
        display: flex !important; align-items: center !important;
        justify-content: center !important;
        gap: 0 !important; padding: 0 !important;
        margin: 3px auto !important; border-radius: 10px;
    }
    body.sidebar-collapsed .sidebar .nav-item .nav-icon {
        width: 20px !important; height: 20px !important;
        min-width: 20px !important; flex: 0 0 20px !important;
    }
    body.sidebar-collapsed .sidebar .section-label {
        display: block; height: 1px !important;
        margin: 15px 10px !important;
    }
    body.sidebar-collapsed .sidebar .sidebar-footer {
        padding-left: 9px !important; padding-right: 9px !important;
        box-sizing: border-box;
    }
    body.sidebar-collapsed .sidebar .user-badge {
        width: 100%; min-height: 46px;
        padding: 6px 0 !important; box-sizing: border-box;
        justify-content: center !important;
    }
    body.sidebar-collapsed .sidebar .avatar { flex: 0 0 auto !important; }
    body.sidebar-collapsed .sidebar .btn-logout {
        width: 100% !important; min-height: 42px;
        margin: 0 !important; box-sizing: border-box;
        justify-content: center !important; padding: 0 !important;
    }
    @media (prefers-reduced-motion: reduce) {
        .sidebar .sidebar-collapse-btn .nav-icon { transition: none !important; }
    }
    /* Prevent the tiny horizontal scroll control in compact mode. */
    .sidebar,
    .sidebar .sidebar-nav {
        overflow-x: hidden !important;
        box-sizing: border-box;
    }
    body.sidebar-collapsed .sidebar,
    body.sidebar-collapsed .sidebar .sidebar-nav {
        overflow-x: clip !important;
    }
    .sidebar .sidebar-nav {
        min-width: 0;
        max-width: 100%;
    }
</style>

<script>
// Apply the saved state before the page paints, so the layout does not jump.
try { if (localStorage.getItem('mveSidebarCollapsed') === '1') document.body.classList.add('sidebar-collapsed'); } catch (e) {}
</script>

<aside class="sidebar" id="app-sidebar">
    <!-- Brand Header -->
    <div class="brand">
        <span class="brand-mark" aria-hidden="true" style="display:block;width:42px;height:42px;min-width:42px;flex:0 0 42px;">
            <svg width="42" height="42" viewBox="0 0 64 64" xmlns="http://www.w3.org/2000/svg">
                <defs>
                    <linearGradient id="sidebar-brand-gold" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#fbbf24"/>
                        <stop offset="1" stop-color="#f59e0b"/>
                    </linearGradient>
                    <linearGradient id="sidebar-brand-leaf" x1="0" y1="0" x2="1" y2="1">
                        <stop offset="0" stop-color="#a3e635"/>
                        <stop offset="1" stop-color="#22c55e"/>
                    </linearGradient>
                </defs>
                <rect width="64" height="64" rx="16" fill="#111827"/>
                <rect x="1" y="1" width="62" height="62" rx="15" fill="none" stroke="#374151" stroke-width="2"/>
                <path d="M13 48V25l10 12 9-15 9 15 10-12v23" fill="none" stroke="url(#sidebar-brand-gold)" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"/>
                <path d="M39 19c2-8 10-11 16-9-1 8-6 13-15 12" fill="url(#sidebar-brand-leaf)"/>
                <path d="M39 23c4-4 8-7 13-10" fill="none" stroke="#d9f99d" stroke-linecap="round" stroke-width="1.5"/>
                <path d="M17 54h30" fill="none" stroke="#84cc16" stroke-dasharray="1 6" stroke-linecap="round" stroke-width="4"/>
            </svg>
        </span>
        <div class="brand-info">
            <strong>Masters Vineyard</strong>
            <small>Enterprise</small>
        </div>
        <!-- Icon-only expand/collapse control, available in both states -->
        <button type="button" class="sidebar-collapse-btn" id="sidebar-collapse-btn"
                aria-controls="app-sidebar" aria-expanded="true"
                aria-label="Collapse sidebar" title="Collapse sidebar">
            <?= nav_icon('collapse') ?>
        </button>
    </div>

    <!-- Navigation Area -->
    <nav class="sidebar-nav">
        
        <!-- SECTION 1: PRODUCTION FLOOR -->
        <div class="section-label">PRODUCTION FLOOR</div>
        
        <!-- Dashboard -->
        <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/dashboard.php" 
           class="nav-item <?= ($current_page === 'dashboard.php') ? 'active' : '' ?>">
            <?= nav_icon('dashboard') ?><span class="nav-text">Dashboard</span>
        </a>

        <!-- Line & Session Control (Supervisor Only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/line_control.php" 
               class="nav-item <?= ($current_page === 'line_control.php') ? 'active' : '' ?>">
                <?= nav_icon('line') ?><span class="nav-text">Line & Session Control</span>
            </a>
        <?php endif; ?>

        <!-- Defect Queue & Review (Staff & Supervisor) -->
        <?php if (can_access(['staff', 'supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/defect_reporting.php" 
               class="nav-item <?= ($current_page === 'defect_reporting.php') ? 'active' : '' ?>">
                <?= nav_icon($user_role === 'supervisor' ? 'review' : 'defect') ?><span class="nav-text"><?= ($user_role === 'supervisor') ? 'Defect Queue & Review' : 'Defect Reporting' ?></span>
                <?php if ($user_role === 'supervisor' && $pending_defect_count > 0): ?>
                    <span class="nav-badge" aria-label="<?= $pending_defect_count ?> reports awaiting review" style="background:#f0883e; color:#000; font-weight:bold; font-size:0.7rem; padding:1px 6px; border-radius:10px; margin-left:auto;"><?= $pending_defect_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- Access Requests (Supervisor, read-only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/access_requests.php"
               class="nav-item <?= ($current_page === 'access_requests.php') ? 'active' : '' ?>">
                <?= nav_icon('access') ?><span class="nav-text">Access Requests</span>
            </a>
        <?php endif; ?>

        <!-- Production Analytics (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/analytics.php" 
               class="nav-item <?= ($current_page === 'analytics.php') ? 'active' : '' ?>">
                <?= nav_icon('analytics') ?><span class="nav-text">Production Analytics</span>
            </a>
        <?php endif; ?>


        <!-- SECTION 2: SHIFTS AND RECORDS -->
        <div class="section-label">SHIFTS AND RECORDS</div>

        <!-- My Activity Logs (Staff Only) -->
        <?php if (can_access(['staff'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/staff/activity_logs.php" 
               class="nav-item <?= ($current_page === 'activity_logs.php') ? 'active' : '' ?>">
                <?= nav_icon('activity') ?><span class="nav-text">My Activity Logs</span>
            </a>
        <?php endif; ?>

        <!-- Shift Yield Reports (Supervisor Only) -->
        <?php if (can_access(['supervisor'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/supervisor/shift_yield.php" 
               class="nav-item <?= ($current_page === 'shift_yield.php') ? 'active' : '' ?>">
                <?= nav_icon('report') ?><span class="nav-text">Shift Yield Reports</span>
            </a>
        <?php endif; ?>

        <!-- Master Data (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/master_data.php" 
               class="nav-item <?= ($current_page === 'master_data.php') ? 'active' : '' ?>">
                <?= nav_icon('database') ?><span class="nav-text">Master Data</span>
            </a>
        <?php endif; ?>


        <!-- SECTION 3: ACCOUNT -->
        <div class="section-label">ACCOUNT</div>

        <!-- User Management (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/users.php" 
               class="nav-item <?= ($current_page === 'users.php') ? 'active' : '' ?>">
                <?= nav_icon('users') ?><span class="nav-text">User Management</span>
                <?php if ($pending_access_count > 0): ?>
                    <span class="nav-badge" aria-label="<?= $pending_access_count ?> pending access requests" style="background:#f0883e; color:#000; font-weight:bold; font-size:0.7rem; padding:1px 6px; border-radius:10px; margin-left:auto;"><?= $pending_access_count ?></span>
                <?php endif; ?>
            </a>
        <?php endif; ?>

        <!-- System Settings (Manager Only) -->
        <?php if (can_access(['manager'], $user_role)): ?>
            <a href="<?= BASE_URL ?>/views/manager/settings.php" 
               class="nav-item <?= ($current_page === 'settings.php') ? 'active' : '' ?>">
                <?= nav_icon('settings') ?><span class="nav-text">System Settings</span>
            </a>
        <?php endif; ?>

        <!-- Profile (All Roles) -->
        <a href="<?= BASE_URL ?>/views/<?= $user_role ?>/profile.php" 
           class="nav-item <?= ($current_page === 'profile.php') ? 'active' : '' ?>">
            <?= nav_icon('profile') ?><span class="nav-text">Profile</span>
        </a>

    </nav>

    <!-- Sidebar Footer -->
    <div class="sidebar-footer">
        <div class="user-badge">
            <div class="avatar" id="sidebar-profile-avatar"><?= htmlspecialchars($initials) ?></div>
            <div class="user-info">
                <span class="user-name" id="sidebar-profile-name"><?= htmlspecialchars($full_name) ?></span>
                <span class="user-role-label"><?= htmlspecialchars($display_role) ?></span>
            </div>
        </div>
        <a href="<?= BASE_URL ?>/backend/process_logout.php" class="btn-logout" title="Logout"><?= nav_icon('logout') ?><span class="logout-text">LOGOUT</span></a>
    </div>
</aside>
<script>
// Keep the sidebar in sync with a successful autosave without refreshing.
window.addEventListener('profile:updated', function(event) {
    const name = String(event.detail?.full_name || '').trim();
    if (!name) return;
    const nameNode = document.getElementById('sidebar-profile-name');
    const avatarNode = document.getElementById('sidebar-profile-avatar');
    if (nameNode) nameNode.textContent = name;
    if (avatarNode) {
        const parts = name.split(/\s+/);
        avatarNode.textContent = (parts[0][0] + (parts.length > 1 ? parts[parts.length - 1][0] : '')).toUpperCase();
    }
});
</script>
<script>
(function () {
    var body = document.body;
    var btn = document.getElementById('sidebar-collapse-btn');
    if (!btn) return;
    var KEY = 'mveSidebarCollapsed';

    function apply(collapsed, save) {
        body.classList.toggle('sidebar-collapsed', collapsed);
        var label = collapsed ? 'Expand sidebar' : 'Collapse sidebar';
        btn.setAttribute('aria-expanded', String(!collapsed));
        btn.setAttribute('aria-label', label);
        btn.title = label;
        if (save) { try { localStorage.setItem(KEY, collapsed ? '1' : '0'); } catch (e) {} }
    }

    apply(body.classList.contains('sidebar-collapsed'), false);
    btn.addEventListener('click', function () { apply(!body.classList.contains('sidebar-collapsed'), true); });

    // Tooltips so the icon-only links stay readable when collapsed
    document.querySelectorAll('#app-sidebar .nav-item').forEach(function (link) {
        var text = link.querySelector('.nav-text');
        if (text && !link.title) link.title = text.textContent.trim();
    });
})();
</script>
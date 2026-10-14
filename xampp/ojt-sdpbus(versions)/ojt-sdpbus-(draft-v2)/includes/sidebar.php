<aside class="sidebar">
    <div>
        <div class="sidebar-header">
            <div class="sidebar-logo-icon">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
                    <rect x="3" y="3" width="18" height="15" rx="3" />
                    <line x1="3" y1="11" x2="21" y2="11" />
                    <circle cx="7.5" cy="14.5" r="1" fill="currentColor" />
                    <circle cx="16.5" cy="14.5" r="1" fill="currentColor" />
                </svg>
            </div>
            <div>
                <div class="sidebar-title">SDPBUS</div>
                <div class="sidebar-subtitle">Operations &amp; Billing</div>
            </div>
        </div>

        <div class="nav-section-label">WORKSPACE</div>
        <ul class="nav-list">
            <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'dashboard.php') ? 'active' : ''; ?>">
                <a href="dashboard.php">
                    <div class="nav-item-content">
                        <img src="icons/home.png" alt="" class="system-icon nav-icon">
                        Dashboard
                    </div>
                </a>
            </li>
            <li class="nav-item <?= (basename($_SERVER['PHP_SELF']) == 'dispatch_log.php') ? 'active' : ''; ?>">
                <a href="dispatch_log.php">
                    <div class="nav-item-content">
                        <img src="icons/report.png" alt="" class="system-icon nav-icon">
                        Trip Dispatch Log
                    </div>
                    
                </a>
            </li>
            <li class="nav-item">
                <a href="#">
                    <div class="nav-item-content">
                        <img src="icons/report-card.png" alt="" class="system-icon nav-icon">
                        Rate Card Matrix
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="#">
                    <div class="nav-item-content">
                        <img src="icons/toll.png" alt="" class="system-icon nav-icon">
                        Passway Toll &amp; Record
                    </div>
                </a>
            </li>
        </ul>

        <div class="nav-section-label" style="margin-top: 2rem;">PREFERENCES</div>
        <ul class="nav-list">
            <li class="nav-item">
                <a href="#">
                    <div class="nav-item-content">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="3"/><path d="M19.4 15a1.65 1.65 0 0 0 .33 1.82l.06.06a2 2 0 0 1 0 2.83 2 2 0 0 1-2.83 0l-.06-.06a1.65 1.65 0 0 0-1.82-.33 1.65 1.65 0 0 0-1 1.51V21a2 2 0 0 1-2 2 2 2 0 0 1-2-2v-.09A1.65 1.65 0 0 0 9 19.4a1.65 1.65 0 0 0-1.82.33l-.06.06a2 2 0 0 1-2.83 0 2 2 0 0 1 0-2.83l.06-.06a1.65 1.65 0 0 0 .33-1.82 1.65 1.65 0 0 0-1.51-1H3a2 2 0 0 1-2-2 2 2 0 0 1 2-2h.09A1.65 1.65 0 0 0 4.6 9a1.65 1.65 0 0 0-.33-1.82l-.06-.06a2 2 0 0 1 0-2.83 2 2 0 0 1 2.83 0l.06.06a1.65 1.65 0 0 0 1.82.33H9a1.65 1.65 0 0 0 1-1.51V3a2 2 0 0 1 2-2 2 2 0 0 1 2 2v.09a1.65 1.65 0 0 0 1 1.51 1.65 1.65 0 0 0 1.82-.33l.06-.06a2 2 0 0 1 2.83 0 2 2 0 0 1 0 2.83l-.06.06a1.65 1.65 0 0 0-.33 1.82V9a1.65 1.65 0 0 0 1.51 1H21a2 2 0 0 1 2 2 2 2 0 0 1-2 2h-.09a1.65 1.65 0 0 0-1.51 1z"/></svg>
                        Settings
                    </div>
                </a>
            </li>
            <li class="nav-item">
                <a href="#">
                    <div class="nav-item-content">
                        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                        About
                    </div>
                </a>
            </li>
        </ul>
    </div>

    <div class="sidebar-footer">
        <div class="user-profile-card">
            <div class="user-avatar">MA</div>
            <div class="user-info">
                <div class="name"><?= htmlspecialchars($user_name ?? 'Maria Alvarez') ?></div>
                <div class="role"><?= htmlspecialchars($user_role ?? 'Administrator') ?></div>
            </div>
        </div>
        <a href="logout.php" class="btn-logout">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/></svg>
            Log out
        </a>
    </div>
</aside>
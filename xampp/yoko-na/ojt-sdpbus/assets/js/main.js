document.addEventListener('DOMContentLoaded', () => {
    const dateDisplay = document.getElementById('appDate');
    const timeDisplay = document.getElementById('appTime');

    if (dateDisplay && timeDisplay) {
        const updateClock = () => {
            const now = new Date();
            dateDisplay.textContent = new Intl.DateTimeFormat(undefined, {
                weekday: 'long',
                month: 'long',
                day: 'numeric',
                year: 'numeric'
            }).format(now);
            timeDisplay.textContent = new Intl.DateTimeFormat(undefined, {
                hour: '2-digit',
                minute: '2-digit',
                second: '2-digit'
            }).format(now);
            timeDisplay.dateTime = now.toISOString();
        };

        updateClock();
        window.setInterval(updateClock, 1000);
    }

    // Mobile Sidebar Toggle (if smaller viewports are used)
    const sidebar = document.querySelector('.sidebar');
    const toggleBtn = document.querySelector('.sidebar-toggle-btn');

    if (toggleBtn && sidebar) {
        if (localStorage.getItem('sdpbus-sidebar-collapsed') === 'true') {
            sidebar.classList.add('collapsed');
        }

        const updateToggleLabel = () => {
            const isCollapsed = sidebar.classList.contains('collapsed');
            toggleBtn.setAttribute('aria-label', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
            toggleBtn.setAttribute('title', isCollapsed ? 'Expand sidebar' : 'Collapse sidebar');
        };

        updateToggleLabel();
        toggleBtn.addEventListener('click', () => {
            sidebar.classList.toggle('collapsed');
            localStorage.setItem('sdpbus-sidebar-collapsed', sidebar.classList.contains('collapsed'));
            updateToggleLabel();
        });
    }

    // Auto-dismiss alert boxes after 5 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(alert => {
        setTimeout(() => {
            alert.style.transition = 'opacity 0.5s ease';
            alert.style.opacity = '0';
            setTimeout(() => alert.remove(), 500);
        }, 5000);
    });
});
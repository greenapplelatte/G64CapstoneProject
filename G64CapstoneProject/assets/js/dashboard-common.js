
/**
 * G64 CAPSTONE PROJECT
 * SHARED DASHBOARD UTILITIES
 *
 * Used by Staff, Supervisor, and Manager dashboards.
 */

window.DashboardCommon = {

    // Refresh interval: 5 seconds
    refreshInterval: 5000,

    // Fetch latest data from PHP backend
    async fetchData(url) {
        const response = await fetch(url, {
            method: 'GET',
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json'
            }
        });

        const data = await response.json();

        if (!response.ok) {
            throw new Error(
                data.message || 'Failed to fetch dashboard data.'
            );
        }

        return data;
    },

    // Update text in dashboard elements
    updateText(elementId, value) {
        const element = document.getElementById(elementId);

        if (element) {
            element.textContent = value ?? '—';
        }
    },

    // Escape HTML for dynamically generated content
    escapeHTML(value) {
        return String(value ?? '').replace(/[&<>"']/g, char => ({
            '&': '&amp;',
            '<': '&lt;',
            '>': '&gt;',
            '"': '&quot;',
            "'": '&#39;'
        }[char]));
    },

    // Realtime monitoring with visibility awareness
    startRealtime(callback, interval = 5000) {
        let timer = null;
        let running = false;

        async function refresh() {
            if (running || document.hidden) return;

            running = true;

            try {
                await callback();
            } catch (error) {
                console.error('Dashboard refresh error:', error);
            } finally {
                running = false;
            }
        }

        function start() {
            if (timer !== null) return;

            refresh();
            timer = setInterval(refresh, interval);
        }

        function stop() {
            if (timer !== null) {
                clearInterval(timer);
                timer = null;
            }
        }

        document.addEventListener('visibilitychange', () => {
            if (document.hidden) {
                stop();
            } else {
                start();
            }
        });

        if (!document.hidden) start();

        return stop;
    },

    // Display successful refresh time
    showLastUpdated(elementId) {
        const element = document.getElementById(elementId);

        if (element) {
            element.textContent =
                'Updated ' + new Date().toLocaleTimeString();
        }
    }
};

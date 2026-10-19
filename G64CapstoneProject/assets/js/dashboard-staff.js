
document.addEventListener('DOMContentLoaded', () => {
    const clock = document.getElementById('current-datetime');

    function updateClock() {
        if (clock) clock.textContent = new Date().toLocaleString('en-US', {
            month: 'short',
            day: 'numeric',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit',
            hour12: true
        }).replace(',', ' · ');
    }

    updateClock();
    setInterval(updateClock, 30000);

    const setText = (id, value) => {
        const el = document.getElementById(id);
        if (el) el.textContent = value;
    };

    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[c]));

    async function fetchJson(url) {
        const response = await fetch(url, {
            cache: 'no-store',
            credentials: 'same-origin',
            headers: {
                'Accept': 'application/json',
                'Cache-Control': 'no-cache'
            }
        });

        const data = await response.json();

        if (!response.ok || data.status !== 'success') {
            throw new Error(data.message || 'Unable to load dashboard data.');
        }

        return data;
    }

    let countingInProgress = false;

    async function loadMetrics() {
        try {
            const res = await fetchJson(
                `${BASE_URL}/backend/api.php?action=get_metrics`
            );

            const d = res.data;

            ['good_count', 'good_units_val'].forEach(id =>
                setText(id, d.good_count)
            );

            setText('target_goal', d.target_goal);
            setText('ng_count', d.ng_count);

            setText(
                'yield_rate',
                Number(d.total_count) ? `${d.yield_rate}%` : '—'
            );

            setText('done-pct', `${d.progress}%`);
            setText('unit-name', d.machine_id || 'Assigned station');

            setText(
                'hw-status',
                d.counting_available ? 'COUNTER READY' : 'COUNTER PAUSED'
            );

            setText(
                'hardware-detail',
                d.counting_available
                    ? `${d.machine_id} · Software counting enabled`
                    : `${d.machine_id} · ${d.counting_block_reason || 'Counting unavailable'}`
            );

            const countButton = document.getElementById('count-submit');

            if (countButton) {
                countButton.disabled =
                    countingInProgress || !d.counting_available;
            }

            const countStatus = document.getElementById('counting-status');

            if (countStatus) {
                countStatus.textContent = d.counting_available
                    ? 'Enter the number of good units produced at your assigned station.'
                    : (d.counting_block_reason || 'Counting is unavailable.');
            }

            const card = document.querySelector('.status-card');

            if (card) {
                const dot = card.querySelector('.dot');

                if (dot) {
                    dot.style.background = d.counting_available
                        ? '#7ee787'
                        : '#e3b341';
                }

                card.style.background = d.counting_available
                    ? '#142a1b'
                    : '#332a16';

                card.style.borderColor = d.counting_available
                    ? '#238636'
                    : '#e3b341';
            }

        } catch (err) {
            setText('hw-status', 'STATUS UNAVAILABLE');
            setText('hardware-detail', 'Unable to verify station or session');
            showError(err.message);
        }
    }

    async function loadDetails() {
        try {
            const d = await fetchJson(
                `${BASE_URL}/backend/get_staff_dashboard.php`
            );

            const station = document.getElementById('station-id');

            if (station) {
                station.textContent =
                    `Station ${d.station}${d.line ? ' · ' + d.line : ''}`;
            }

            renderHourly(d.hourly || []);

            setText(
                'live-updated',
                `Updated ${new Date().toLocaleTimeString()} · Auto-refresh every 5s`
            );

            const categories = document.getElementById('category-list');

            if (categories) {
                const total = d.categories.reduce(
                    (sum, item) => sum + Number(item.total || 0),
                    0
                );

                setText('total-rejected', total);

                categories.innerHTML = d.categories.length
                    ? d.categories.map(item => `
                        <div class="category-row">
                            <div class="category-header">
                                <span>${esc(item.name)}</span>
                                <strong>${Number(item.total)}</strong>
                            </div>

                            <div class="progress-bg">
                                <div class="progress-fill"
                                     style="width:${total
                                         ? Math.round(Number(item.total) / total * 100)
                                         : 0}%">
                                </div>
                            </div>
                        </div>
                    `).join('')
                    : '<p style="color:var(--text-secondary);font-size:.85rem">No confirmed defect reports today.</p>';
            }

            const activity = document.getElementById('activity-log-list');

            if (activity) {
                activity.innerHTML = d.activity.length
                    ? d.activity.map(row => `
                        <div class="activity-item">
                            <span class="activity-time">
                                ● ${esc(row.log_time)}
                            </span>
                            <span>${esc(row.details)}</span>
                        </div>
                    `).join('')
                    : '<p style="color:var(--text-secondary);font-size:.85rem">No activity recorded today.</p>';
            }

            const reports = document.getElementById('latest-defect-list');

            if (reports) {
                reports.innerHTML = d.reports.length
                    ? d.reports.map(row => `
                        <div class="report-item">
                            <div>
                                <strong>
                                    ${esc(row.defect_category)} · ${esc(row.workstation)}
                                </strong>
                                <br>
                                <small style="color:var(--text-secondary)">
                                    ${esc(row.created_at || row.report_time || '')}
                                </small>
                            </div>

                            <span class="severity-pill severity-${esc(row.severity)}">
                                ${esc(row.severity)} · ${esc(row.status)}
                            </span>
                        </div>
                    `).join('')
                    : '<p style="color:var(--text-secondary);font-size:.85rem">You have not submitted any defect reports.</p>';
            }

        } catch (err) {
            showError(err.message);
        }
    }

    function renderHourly(rows) {
        const chart = document.getElementById('hourly-chart');

        if (!chart) return;

        if (!rows.length) {
            chart.innerHTML =
                '<p style="color:var(--text-secondary);font-size:.85rem">No hourly production activity for this station today.</p>';
            return;
        }

        const max = Math.max(
            1,
            ...rows.flatMap(r => [
                Number(r.good) || 0,
                Number(r.ng) || 0
            ])
        );

        chart.innerHTML = rows.map(r => {
            const good = Math.max(0, Number(r.good) || 0);
            const ng = Math.max(0, Number(r.ng) || 0);

            return `
                <div class="bar-group"
                     title="${esc(r.hour)}: ${good} good, ${ng} NG">
                    <div class="bar-track">
                        <div class="bar-good"
                             style="height:${Math.round(good / max * 100)}px"
                             title="${good} good">
                        </div>

                        <div class="bar-ng"
                             style="height:${Math.round(ng / max * 100)}px"
                             title="${ng} NG">
                        </div>
                    </div>

                    <div class="bar-labels">
                        ${esc(r.hour)}
                        <br>
                        <small>${good} / ${ng}</small>
                    </div>
                </div>
            `;
        }).join('');
    }

    function showError(message) {
        let box = document.getElementById('dashboard-error');

        if (!message) {
            if (box) box.remove();
            return;
        }

        if (!box) {
            box = document.createElement('div');
            box.id = 'dashboard-error';
            box.setAttribute('role', 'alert');

            box.style.cssText =
                'padding:12px 16px;margin:0 0 16px;border:1px solid #f85149;border-radius:8px;background:#351719;color:#ffb4ae';

            document.querySelector('.main-content')?.prepend(box);
        }

        box.textContent = message;
    }

    const countForm = document.getElementById('manual-count-form');

    if (countForm) {
        countForm.addEventListener('submit', async event => {
            event.preventDefault();

            const button = document.getElementById('count-submit');

            if (!button || button.disabled) return;

            countingInProgress = true;
            button.disabled = true;

            try {
                const formData = new FormData(countForm);

                const response = await fetch(
                    `${BASE_URL}/backend/api.php?action=log_count`,
                    {
                        method: 'POST',
                        body: formData,
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json'
                        }
                    }
                );

                const data = await response.json();

                if (!response.ok || data.status !== 'success') {
                    throw new Error(
                        data.message || 'Unable to record production count.'
                    );
                }

                countForm.querySelector('[name="qty"]').value = '1';

                showError('');

                await Promise.all([
                    loadMetrics(),
                    loadDetails()
                ]);

                setText(
                    'counting-status',
                    `${Number(formData.get('qty'))} good unit(s) recorded.`
                );

            } catch (err) {
                showError(err.message);

            } finally {
                countingInProgress = false;
                await loadMetrics();
            }
        });
    }

    let refreshing = false;

    async function refreshAll() {
        if (refreshing) return;

        refreshing = true;

        try {
            await Promise.all([
                loadMetrics(),
                loadDetails()
            ]);
        } finally {
            refreshing = false;
        }
    }

    refreshAll();

    let poll = null;

    const startPolling = () => {
        if (!poll) {
            poll = setInterval(refreshAll, 5000);
        }
    };

    const stopPolling = () => {
        if (poll) {
            clearInterval(poll);
            poll = null;
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.hidden) {
            stopPolling();
        } else {
            refreshAll();
            startPolling();
        }
    });

    startPolling();
});

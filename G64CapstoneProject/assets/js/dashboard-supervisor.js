
/**
 * G64 CAPSTONE PROJECT
 * SUPERVISOR DASHBOARD REALTIME MONITORING
 */

const BASE_URL = document.documentElement.dataset.baseUrl || '';

const esc = window.esc || (value =>
    String(value ?? '').replace(/[&<>"']/g, c => ({
        '&': '&amp;',
        '<': '&lt;',
        '>': '&gt;',
        '"': '&quot;',
        "'": '&#39;'
    }[c]))
);

function startRoleDashboardMonitoring(load) {
    let busy = false;
    let timer = null;
    let lastSuccess = null;

    const status = document.createElement('span');
    status.id = 'role-live-status';
    status.setAttribute('role', 'status');
    status.style.cssText =
        'display:inline-block;margin:8px 0;font-size:12px;opacity:.85';

    const header = document.querySelector('.dashboard-header');
    if (header) header.appendChild(status);

    async function tick() {
        if (busy || document.hidden) return;
        busy = true;

        try {
            await load();
            lastSuccess = new Date();
            status.textContent =
                '● Live · Updated ' + lastSuccess.toLocaleTimeString();
        } catch (error) {
            status.textContent =
                '⚠ Connection problem · last successful refresh: ' +
                (lastSuccess ? lastSuccess.toLocaleTimeString() : 'none');
            console.error('Dashboard update failed:', error);
        } finally {
            busy = false;
        }
    }

    function start() {
        if (timer !== null) return;
        tick();
        timer = setInterval(tick, 5000);
    }

    function stop() {
        if (timer !== null) clearInterval(timer);
        timer = null;
    }

    document.addEventListener('visibilitychange', () => {
        document.hidden ? stop() : start();
    });

    start();
}

document.addEventListener('DOMContentLoaded', () => {
    startRoleDashboardMonitoring(loadSupervisorDashboard);
});

function showSupervisorError(message) {
    let el = document.getElementById('supervisor-load-error');

    if (!el) {
        el = document.createElement('div');
        el.id = 'supervisor-load-error';
        el.setAttribute('role', 'alert');
        el.style.cssText =
            'padding:10px 14px;margin:10px 0;border:1px solid #b42318;border-radius:8px;color:#b42318;background:#fff5f5';

        document.querySelector('.main-content')?.prepend(el);
    }

    el.textContent = message;
}

function loadSupervisorDashboard() {
    return fetch(BASE_URL + '/backend/get_supervisor_dashboard.php', {
        cache: 'no-store',
        credentials: 'same-origin',
        headers: { Accept: 'application/json' }
    })
    .then(async res => {
        const data = await res.json();

        if (!res.ok || data.status !== 'success') {
            throw new Error(data.message || 'Dashboard request failed');
        }

        return data;
    })
    .then(data => {
        renderSummary(data.summary);
        renderStations(data.stations);
        renderHourlyChart(data.hourly);
        renderAlerts(data.alerts);
        renderReviews(data.pending_reviews);
    })
    .catch(err => {
        showSupervisorError(
            'Unable to load live floor data. Check the server/database connection and refresh.'
        );
        console.error('Error loading supervisor data:', err);
        throw err;
    });
}

function renderSummary(s) {
    const err = document.getElementById('supervisor-load-error');
    if (err) err.remove();

    const sessionBadge = document.getElementById('session-badge-text');

    if (sessionBadge) {
        sessionBadge.textContent = s.session_code
            ? `SESSION ${s.session_status || 'ACTIVE'} · ${s.session_code}`
            : 'No active session';
    }

    document.getElementById('stat-output').innerHTML =
        `${esc(s.output)} <span style="font-size:1.2rem;color:var(--text-secondary);font-weight:normal;">/ ${esc(s.target)}</span>`;

    document.getElementById('stat-progress-bar').style.width =
        `${s.pct_done}%`;

    document.getElementById('stat-project-sub').innerText =
        `${s.product_name || 'No product selected'} · ${s.pct_done}% done`;

    document.getElementById('stat-yield').innerText = s.yield;

    document.getElementById('stat-units-sub').innerText =
        `${s.good_units} good · ${s.ng_units} rejected`;

    document.getElementById('stat-reports').innerText =
        s.reports_to_review;

    document.getElementById('stat-stations').innerHTML =
        `${esc(s.stations_online)} <span style="font-size:1.2rem;color:var(--text-secondary);font-weight:normal;">/ ${esc(s.total_stations)}</span>`;

    document.getElementById('stat-stations-sub').innerText =
        `${s.running_count} running · ${s.paused_count} paused`;
}

function renderStations(stations) {
    const container =
        document.getElementById('stations-box-container');

    container.innerHTML = '';

    if (!Array.isArray(stations) || stations.length === 0) {
        container.innerHTML =
            '<div style="padding:18px;text-align:center;color:var(--text-secondary)">No active staff are assigned to stations yet. Assign a production line and station in Manager → User Management.</div>';
        return;
    }

    stations.forEach(st => {
        const underMaintenance =
            st.master_status === 'Maintenance';

        const displayStatus = underMaintenance
            ? 'MAINTENANCE'
            : st.status;

        const borderClass =
            st.status === 'RUNNING' && !underMaintenance
                ? 'border-running'
                : 'border-paused';

        const good = Number(st.good_units) || 0;
        const ng = Number(st.ng_units) || 0;
        const totalStationUnits = good + ng;

        const yieldPct = totalStationUnits > 0
            ? ((good / totalStationUnits) * 100).toFixed(1)
            : '—';

        const target = Number(st.target_units) || 0;

        const progressWidth = target > 0
            ? Math.min(100, Math.round(good / target * 100))
            : 0;

        container.innerHTML += `
            <div class="station-box ${borderClass}">
                <div class="station-box-header">
                    <div>
                        <span class="station-code">${esc(st.station_code)}</span>
                        <span class="station-staff">
                            ${esc(st.line_name || 'Unassigned line')} ·
                            ${esc(st.assigned_staff)} ·
                            ${esc(st.workstation_name)}
                        </span>
                    </div>
                    <span class="badge-status badge-${underMaintenance ? 'PAUSED' : esc(st.status)}">
                        ${esc(displayStatus)}
                    </span>
                </div>

                <div class="station-val-line">
                    <span class="good-val">${good} good</span>
                    <span class="ng-val">${ng} NG</span>
                </div>

                <div class="target-progress-bg">
                    <div class="target-progress-fill"
                         style="width:${progressWidth}%"></div>
                </div>

                <div class="station-meta-row">
                    <span>Yield ${yieldPct}%</span>
                    <span>${good} / ${target}</span>
                </div>
            </div>
        `;
    });
}

function renderHourlyChart(hourlyData) {
    const group = document.getElementById('svg-bars-group');
    group.innerHTML = '';

    if (!hourlyData || hourlyData.length === 0) return;

    const maxVal = Math.max(
        1,
        ...hourlyData.map(h =>
            Number(h.good_units || 0) + Number(h.ng_units || 0)
        )
    );

    const startX = 20;
    const stepX = 55;

    hourlyData.forEach((h, index) => {
        const cx = startX + index * stepX;

        const goodH =
            (Number(h.good_units) / maxVal) * 100;

        const ngH =
            (Number(h.ng_units) / maxVal) * 100;

        const goodY = 120 - goodH;
        const ngY = goodY - ngH;

        group.innerHTML += `
            <rect x="${cx}" y="${goodY}"
                  width="16" height="${goodH}"
                  fill="var(--accent-yellow)" rx="2"/>

            <rect x="${cx}" y="${ngY}"
                  width="16" height="${ngH}"
                  fill="var(--brand-orange)" rx="2"/>

            <text x="${cx + 8}" y="135"
                  fill="var(--text-primary)"
                  font-size="9" font-weight="bold"
                  text-anchor="middle">
                ${esc(h.good_units)}
            </text>

            <text x="${cx + 8}" y="147"
                  fill="var(--text-secondary)"
                  font-size="8" text-anchor="middle">
                ${esc(h.hour_label)}
            </text>
        `;
    });
}

function renderAlerts(alerts) {
    const container =
        document.getElementById('alerts-feed-container');

    container.innerHTML = '';

    (alerts || []).forEach(a => {
        container.innerHTML += `
            <div class="alert-feed-item">
                <div class="alert-title-group">
                    <span class="dot-alert dot-${esc(a.alert_type)}"></span>

                    <div class="alert-feed-text">
                        <strong>${esc(a.title)}</strong>
                        <small>${esc(a.subtitle)}</small>
                    </div>
                </div>

                <div class="alert-feed-time">
                    ${esc(a.alert_time)}
                </div>
            </div>
        `;
    });
}

function renderReviews(reviews) {
    const container =
        document.getElementById('reviews-list-container');

    container.innerHTML = '';

    if (!reviews || reviews.length === 0) {
        container.innerHTML =
            '<p style="color:var(--text-secondary);font-size:0.8rem;">No pending defect reports waiting for review.</p>';
        return;
    }

    reviews.forEach(r => {
        const timeFormatted = esc(
            r.report_time
                ? r.report_time.substring(0, 5)
                : 'Time unavailable'
        );

        container.innerHTML += `
            <div class="review-item">
                <div class="review-text">
                    <strong>
                        ${esc(r.defect_category)} · ${esc(r.workstation)}
                    </strong>
                    <small>
                        Reported by ${esc(r.reported_by || 'Unknown reporter')} ·
                        ${timeFormatted} ·
                        Qty ${esc(r.quantity_rejected || 1)}
                    </small>
                </div>

                <div style="display:flex;align-items:center;">
                    <span class="pill-sev-gold">
                        ${esc(r.severity || 'Major')}
                    </span>

                    <a href="${BASE_URL}/views/supervisor/defect_reporting.php"
                       class="btn-review-outline">
                        Review
                    </a>
                </div>
            </div>
        `;
    });
}

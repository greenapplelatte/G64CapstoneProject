
/**
 * G64 CAPSTONE PROJECT
 * MANAGER DASHBOARD REALTIME MONITORING
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
    document.getElementById('manager-generate-report')
        ?.addEventListener('click', () => window.print());

    document.getElementById('manager-export-pdf')
        ?.addEventListener('click', () => window.print());

    document.getElementById('manager-export-csv')
        ?.addEventListener('click', exportManagerCsv);

    startRoleDashboardMonitoring(loadManagerDashboard);
});

function loadManagerDashboard() {
    return fetch(BASE_URL + '/backend/get_manager_data.php', {
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
        window.managerDashboardData = data;

        document.getElementById('manager-data-scope').textContent =
            data.period;

        const target = Number(data.metrics.oee_target_pct);

        document.getElementById('oee-target-label').textContent =
            `OEE target ${target}%`;

        document.getElementById('chart-target-label').textContent =
            `Target ${target}%`;

        const targetY =
            180 - ((Math.max(60, Math.min(90, target)) - 60) / 30) * 150;

        document.getElementById('chart-target-line')
            .setAttribute('y1', targetY);

        document.getElementById('chart-target-line')
            .setAttribute('y2', targetY);

        document.getElementById('chart-target-label')
            .setAttribute('y', targetY - 7);

        renderMetrics(data.metrics);
        renderLineTable(data.lines);
        renderWeeklyChart(data.weekly || []);
    })
    .catch(err => {
        console.error('Error loading manager data:', err);

        const alert =
            document.getElementById('manager-attention-list');

        if (alert) {
            alert.textContent = 'Unable to load live alerts.';
        }

        throw err;
    });
}

function renderMetrics(m) {
    if (!m) {
        [
            'oee-val',
            'avail-val',
            'perf-val',
            'qual-val',
            'vol-total',
            'vol-good',
            'vol-ng'
        ].forEach(id => {
            const el = document.getElementById(id);
            if (el) el.textContent = '—';
        });

        return;
    }

    document.getElementById('oee-val').innerText =
        m.overall_oee == null ? '—' : m.overall_oee + '%';

    document.getElementById('avail-val').innerText =
        m.availability == null ? '—' : m.availability + '%';

    document.getElementById('perf-val').innerText =
        m.performance == null ? '—' : m.performance + '%';

    document.getElementById('qual-val').innerText =
        m.quality == null ? '—' : m.quality + '%';

    document.getElementById('vol-total').innerText =
        (Number(m.total_output) || 0).toLocaleString() + ' units';

    document.getElementById('vol-good').innerText =
        (Number(m.good_units) || 0).toLocaleString();

    const ng = Number(m.ng_units) || 0;
    const total = Number(m.total_output) || 0;

    document.getElementById('vol-ng').innerText =
        ng.toLocaleString() +
        (total > 0
            ? ' - ' + ((ng / total) * 100).toFixed(1) + '%'
            : '');
}

function renderLineTable(lines) {
    const tbody = document.getElementById('line-table-body');
    tbody.innerHTML = '';

    if (!lines || !lines.length) {
        tbody.innerHTML =
            '<tr><td colspan="6">No verified line performance data available.</td></tr>';
        return;
    }

    lines.forEach(line => {
        tbody.innerHTML += `
            <tr>
                <td>
                    <strong>${esc(line.line_name)}</strong>

                    ${line.is_lowest == 1
                        ? '<span class="badge-lowest">Lowest</span>'
                        : ''}

                    <br>

                    <small style="color:var(--text-secondary);">
                        ${esc(line.share_pct)}
                    </small>
                </td>

                <td>
                    ${(Number(line.total_output) || 0).toLocaleString()}
                </td>

                <td>
                    ${line.availability == null
                        ? '—'
                        : esc(line.availability) + '%'}
                </td>

                <td>
                    ${line.performance == null
                        ? '—'
                        : esc(line.performance) + '%'}
                </td>

                <td>
                    ${line.quality == null
                        ? '—'
                        : esc(line.quality) + '%'}
                </td>

                <td style="color:var(--accent-yellow);font-weight:bold;">
                    ${line.oee == null
                        ? '—'
                        : esc(line.oee) + '%'}
                </td>
            </tr>
        `;
    });
}

function csvValue(value) {
    let text = String(value ?? '');

    if (/^[=+\-@]/.test(text)) {
        text = "'" + text;
    }

    return '"' + text.replace(/"/g, '""') + '"';
}

function exportManagerCsv() {
    const data = window.managerDashboardData;

    if (!data) {
        alert('Manager data is not loaded yet.');
        return;
    }

    const rows = [['section', 'metric', 'value']];

    const selected = section =>
        document.querySelector(
            `[data-report-section="${section}"]`
        )?.checked;

    if (selected('oee')) {
        Object.entries(data.metrics || {}).forEach(([key, value]) => {
            rows.push([
                'Current session metrics',
                key,
                value ?? 'Not available until verified shift data exists'
            ]);
        });
    }

    if (selected('lines')) {
        (data.lines || []).forEach(line => {
            rows.push([
                'Line performance',
                line.line_name,
                `Output=${line.total_output}; Good=${line.good_units ?? ''}; NG=${line.ng_units ?? ''}; Yield=${line.quality ?? '—'}%; OEE=${line.oee ?? '—'}`
            ]);
        });
    }

    if (selected('defects')) {
        rows.push([
            'Recurring defects',
            'status',
            'No verified defect/root-cause analytics available'
        ]);
    }

    const csv = rows
        .map(row => row.map(csvValue).join(','))
        .join('\r\n');

    const url = URL.createObjectURL(
        new Blob([csv], { type: 'text/csv;charset=utf-8' })
    );

    const link = document.createElement('a');
    link.href = url;
    link.download =
        `manager-production-${new Date().toISOString().slice(0, 10)}.csv`;

    link.click();

    setTimeout(() => URL.revokeObjectURL(url), 1000);
}

function renderWeeklyChart(weeklyData) {
    const linePath = document.getElementById('chart-line-path');
    const areaFill = document.getElementById('chart-area-fill');
    const pointsGroup = document.getElementById('chart-points-group');

    if (!weeklyData || weeklyData.length === 0) {
        linePath.setAttribute('d', '');
        areaFill.setAttribute('points', '');

        pointsGroup.innerHTML =
            '<text x="300" y="110" text-anchor="middle" class="chart-axis-text">No verified trend data available</text>';

        return;
    }

    const startX = 65;
    const endX = 550;
    const minY = 180;
    const maxY = 30;

    const stepX = weeklyData.length > 1
        ? (endX - startX) / (weeklyData.length - 1)
        : 0;

    let pathD = '';
    let areaPoints = `${startX},${minY} `;
    let pointsHTML = '';

    weeklyData.forEach((item, index) => {
        const val = parseFloat(item.oee_value);
        const cx = startX + index * stepX;

        const cy =
            minY - ((val - 60) / 30) * (minY - maxY);

        if (index === 0) {
            pathD += `M ${cx} ${cy}`;
        } else {
            pathD += ` L ${cx} ${cy}`;
        }

        areaPoints += `${cx},${cy} `;

        const weekSubText =
            index === weeklyData.length - 1
                ? `<tspan x="${cx}" dy="12" style="font-size:9px;fill:#8b949e;">to date</tspan>`
                : '';

        pointsHTML += `
            <circle cx="${cx}" cy="${cy}"
                    class="data-point"></circle>

            <text x="${cx}" y="${cy - 12}"
                  class="val-label">
                ${esc(val)}
            </text>

            <text x="${cx}" y="195"
                  class="chart-axis-text"
                  text-anchor="middle">
                ${esc(item.week_label)}${weekSubText}
            </text>
        `;
    });

    areaPoints +=
        `${startX + (weeklyData.length - 1) * stepX},${minY}`;

    linePath.setAttribute('d', pathD);
    areaFill.setAttribute('points', areaPoints);
    pointsGroup.innerHTML = pointsHTML;
}

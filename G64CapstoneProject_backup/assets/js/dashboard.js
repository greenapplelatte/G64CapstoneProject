document.addEventListener('DOMContentLoaded', () => {
    const clock = document.getElementById('current-datetime');
    function updateClock() {
        if (clock) clock.textContent = new Date().toLocaleString('en-US', {month:'short', day:'numeric', year:'numeric', hour:'2-digit', minute:'2-digit', hour12:true}).replace(',', ' ·');
    }
    updateClock();
    setInterval(updateClock, 30000);

    const setText = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value; };
    const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
    async function fetchJson(url) {
        const response = await fetch(url, {headers:{'Accept':'application/json'}});
        const data = await response.json();
        if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to load dashboard data.');
        return data;
    }
    async function loadMetrics() {
        try {
            const res = await fetchJson(`${BASE_URL}/backend/api.php?action=get_metrics`);
            const d = res.data;
            ['good_count','good_units_val'].forEach(id => setText(id, d.good_count));
            setText('target_goal', d.target_goal); setText('ng_count', d.ng_count);
            setText('yield_rate', Number(d.total_count) ? `${d.yield_rate}%` : '—');
            setText('done-pct', `${d.progress}%`);
            setText('unit-name', d.machine_id || 'Assigned station');
            const status = d.device_online === false ? 'HARDWARE OFFLINE' : (d.session_status === 'paused' ? 'LINE PAUSED' : (d.session_status === 'completed' ? 'SESSION CLOSED' : 'HARDWARE ONLINE'));
            setText('hw-status', status);
            setText('hardware-detail', `${d.machine_id} · ${d.last_seen ? `last seen ${new Date(d.last_seen.replace(' ', 'T')).toLocaleString()}` : 'no heartbeat received'}`);
            const card = document.querySelector('.status-card');
            if (card) { const dot = card.querySelector('.dot'); if (dot) dot.style.background = d.device_online === false ? '#f85149' : (d.session_status === 'paused' || d.session_status === 'completed' ? '#e3b341' : '#7ee787'); card.style.background = d.device_online === false ? '#351719' : (d.session_status === 'paused' || d.session_status === 'completed' ? '#332a16' : '#142a1b'); card.style.borderColor = d.device_online === false ? '#f85149' : (d.session_status === 'paused' || d.session_status === 'completed' ? '#e3b341' : '#238636'); }
            setText('total-rejected', d.ng_count);
        } catch (err) { setText('hw-status', 'STATUS UNAVAILABLE'); setText('hardware-detail', 'Unable to verify station or session'); showError(err.message); }
    }
    async function loadDetails() {
        try {
            const d = await fetchJson(`${BASE_URL}/backend/get_staff_dashboard.php`);
            const station = document.getElementById('station-id');
            if (station) station.textContent = `Station ${d.station}`;
            const categories = document.getElementById('category-list');
            if (categories) {
                const total = d.categories.reduce((sum, item) => sum + Number(item.total || 0), 0);
                setText('total-rejected', total);
                categories.innerHTML = d.categories.length ? d.categories.map(item => `<div class="category-row"><div class="category-header"><span>${esc(item.name)}</span><strong>${Number(item.total)}</strong></div><div class="progress-bg"><div class="progress-fill" style="width:${total ? Math.round(Number(item.total)/total*100) : 0}%"></div></div></div>`).join('') : '<p style="color:var(--text-secondary);font-size:.85rem">No confirmed defect reports today.</p>';
            }
            const activity = document.getElementById('activity-log-list');
            if (activity) activity.innerHTML = d.activity.length ? d.activity.map(row => `<div class="activity-item"><span class="activity-time">● ${esc(row.log_time)}</span><span>${esc(row.details)}</span></div>`).join('') : '<p style="color:var(--text-secondary);font-size:.85rem">No activity recorded today.</p>';
            const reports = document.getElementById('latest-defect-list');
            if (reports) reports.innerHTML = d.reports.length ? d.reports.map(row => `<div class="report-item"><div><strong>${esc(row.defect_category)} · ${esc(row.workstation)}</strong><br><small style="color:var(--text-secondary)">${esc(row.created_at || row.report_time || '')}</small></div><span class="severity-pill severity-${esc(row.severity)}">${esc(row.severity)} · ${esc(row.status)}</span></div>`).join('') : '<p style="color:var(--text-secondary);font-size:.85rem">You have not submitted any defect reports.</p>';
        } catch (err) { showError(err.message); }
    }
    function showError(message) {
        let box = document.getElementById('dashboard-error');
        if (!box) { box = document.createElement('div'); box.id = 'dashboard-error'; box.setAttribute('role','alert'); box.style.cssText = 'padding:12px 16px;margin:0 0 16px;border:1px solid #f85149;border-radius:8px;background:#351719;color:#ffb4ae'; document.querySelector('.main-content')?.prepend(box); }
        box.textContent = message;
    }
    loadMetrics(); loadDetails();
    let poll = null;
    const startPolling = () => { if (!poll) poll = setInterval(loadMetrics, 10000); };
    const stopPolling = () => { if (poll) { clearInterval(poll); poll = null; } };
    document.addEventListener('visibilitychange', () => document.hidden ? stopPolling() : (loadMetrics(), startPolling()));
    startPolling();
});

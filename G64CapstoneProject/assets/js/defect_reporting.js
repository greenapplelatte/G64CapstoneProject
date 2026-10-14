document.addEventListener('DOMContentLoaded', () => {
    const form = document.getElementById('defectForm');
    if (form) form.addEventListener('submit', async event => {
        event.preventDefault();
        const button = form.querySelector('[type="submit"]');
        if (button.disabled) return;
        button.disabled = true; const oldText = button.textContent; button.textContent = 'SUBMITTING…';
        try {
            const response = await fetch(`${BASE_URL}/backend/process_defect.php?action=submit_report`, {method:'POST', body:new FormData(form), headers:{'Accept':'application/json'}});
            const data = await response.json();
            if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to submit report.');
            showMessage(data.message, 'success'); resetForm(); await loadReportsData();
        } catch (error) { showMessage(error.message || 'Network error. Please check your connection and try again.', 'error'); }
        finally { button.disabled = false; button.textContent = oldText; }
    });
    const clock = document.getElementById('current-datetime');
    const updateClock = () => { if (clock) clock.textContent = new Date().toLocaleString('en-US', {month:'short',day:'numeric',year:'numeric',hour:'2-digit',minute:'2-digit',hour12:true}).replace(',', ' ·'); };
    updateClock(); setInterval(updateClock, 30000);
    loadReportsData();
});

function showMessage(message, type) {
    let box = document.getElementById('defect-feedback');
    if (!box) { box = document.createElement('div'); box.id = 'defect-feedback'; box.setAttribute('role','status'); document.querySelector('.main-content')?.prepend(box); }
    box.textContent = message; box.style.cssText = `padding:12px 16px;margin-bottom:16px;border-radius:8px;border:1px solid ${type === 'success' ? '#238636' : '#f85149'};background:${type === 'success' ? '#142a1b' : '#351719'};color:${type === 'success' ? '#7ee787' : '#ffb4ae'}`;
}
function selectSeverity(val) {
    document.getElementById('severity_val').value = val;
    document.querySelectorAll('.btn-severity').forEach(btn => btn.classList.toggle('active', btn.dataset.val === val));
}
function handleFileSelect(input) {
    const preview = document.getElementById('photo-preview');
    const label = document.getElementById('photo-filename');
    if (!input.files || !input.files[0]) { preview.style.display = 'none'; preview.removeAttribute('src'); label.textContent = '📷 Add a photo of the defective unit'; return; }
    const file = input.files[0];
    if (!['image/jpeg','image/png','image/webp'].includes(file.type) || file.size > 5 * 1024 * 1024) {
        input.value = ''; showMessage('Choose a JPG, PNG, or WEBP image no larger than 5 MB.', 'error'); return;
    }
    label.textContent = `📷 ${file.name}`; preview.src = URL.createObjectURL(file); preview.style.display = 'block';
}
function resetForm() {
    const form = document.getElementById('defectForm'); form.reset(); selectSeverity('Major');
    const preview = document.getElementById('photo-preview'); if (preview) { if (preview.src.startsWith('blob:')) URL.revokeObjectURL(preview.src); preview.removeAttribute('src'); preview.style.display = 'none'; }
    const label = document.getElementById('photo-filename'); if (label) label.textContent = '📷 Add a photo of the defective unit';
}
async function loadReportsData() {
    try {
        const response = await fetch(`${BASE_URL}/backend/process_defect.php?action=get_reports`, {headers:{'Accept':'application/json'}});
        const data = await response.json(); if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to load your reports.');
        updateStats(data.stats); renderRecentList(data.reports || []);
    } catch (error) { showMessage(error.message || 'Could not load reports. Refresh to try again.', 'error'); }
}
function updateStats(stats) {
    if (!stats) return;
    document.getElementById('stat-total').textContent = stats.total_today || 0;
    document.getElementById('stat-minor').textContent = stats.minor_count || 0;
    document.getElementById('stat-major').textContent = stats.major_count || 0;
    document.getElementById('stat-critical').textContent = stats.critical_count || 0;
}
function esc(value) { return String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
function renderRecentList(reports) {
    const list = document.getElementById('recent-reports-list'); list.innerHTML = '';
    if (!reports.length) { list.innerHTML = '<p style="color:var(--text-secondary);font-size:.85rem">You have not submitted any defect reports yet.</p>'; return; }
    list.innerHTML = reports.map(item => `<div class="report-item"><div class="report-info"><strong>${esc(item.defect_category)} · ${esc(item.workstation)}</strong><br><small>${esc(item.created_at || item.report_time || '')}</small>${item.supervisor_comment ? `<br><small>Review: ${esc(item.supervisor_comment)}</small>` : ''}</div><div class="report-tags"><span class="pill-sev pill-${esc(item.severity)}">${esc(item.severity)}</span><span class="status-label">${esc(item.status)}</span>${item.status === 'Pending' ? `<button type="button" class="btn-clear" onclick="cancelReport(${Number(item.id)})">Cancel</button>` : ''}</div></div>`).join('');
}

async function cancelReport(reportId) {
    if (!window.confirm('Cancel this pending defect report?')) return;
    const body = new FormData(); body.append('report_id', String(reportId));
    try { const response = await fetch(`${BASE_URL}/backend/process_defect.php?action=cancel_report`, {method:'POST',body,headers:{'Accept':'application/json'}}); const data = await response.json(); if (!response.ok || data.status !== 'success') throw new Error(data.message || 'Unable to cancel report.'); showMessage(data.message, 'success'); await loadReportsData(); }
    catch (error) { showMessage(error.message || 'Network error. Please try again.', 'error'); }
}

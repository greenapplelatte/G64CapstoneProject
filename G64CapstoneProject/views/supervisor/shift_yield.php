<?php
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/auth.php';

check_role('supervisor');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Shift Yield Reports - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Shift Yield Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/shift_yield.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Shift Yield Reports</h1>
                <p class="subtitle">Total shift summaries. Check yield against target, see where units were rejected, then close out the shift once every defect report is reviewed.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <section class="grid-syr-main">
            <!-- Left Primary Column -->
            <div>
                <!-- Shift Summary Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>SHIFT SUMMARY</span>
                        <span id="shift-label">No shift selected</span>
                    </div>
                    <div class="card-body">
                        <div class="shift-summary-grid">
                            <div class="metric-box-sm">
                                <label>SHIFT YIELD</label>
                                <div class="val" style="color:var(--accent-green);" id="sum-yield">—</div>
                                <small id="yield-note">Available when a shift is selected</small>
                            </div>
                            <div class="metric-box-sm">
                                <label>GOOD UNITS</label>
                                <div class="val" id="sum-good">—</div>
                                <small id="good-note">No production data</small>
                            </div>
                            <div class="metric-box-sm">
                                <label>REJECTED (NG)</label>
                                <div class="val" style="color:var(--brand-orange);" id="sum-rejected">—</div>
                                <small id="rejected-note">No confirmed reports</small>
                            </div>
                            <div class="metric-box-sm">
                                <label>PENDING REVIEW</label>
                                <div class="val" style="color:var(--accent-yellow);" id="sum-pending">—</div>
                                <small id="pending-note">No pending reports</small>
                            </div>
                        </div>

                        <!-- Progress Bar & Pace Text -->
                        <div style="font-size:0.78rem; display:flex; justify-content:space-between; margin-bottom:5px;">
                            <strong>Output vs target</strong>
                            <span id="output-target-label" style="color:var(--text-secondary);">— / — units</span>
                        </div>
                        <div class="yield-progress-track" style="width:100%; height:8px;">
                            <div id="output-progress" class="yield-progress-bar" style="width:0%; background:var(--accent-green);"></div>
                        </div>
                        <p id="pace-message" style="font-size:0.75rem; color:var(--text-secondary); margin-top:8px;">Pace estimate will appear when production data is available.</p>

                        <!-- Pace Warning Box -->
                        <div class="warning-callout-gold"><div class="warning-callout-text"><strong id="pending-warning-title">Pending quantity unavailable</strong><small id="pending-warning-text">Pending report impact will be calculated from actual reports.</small></div><a href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php" class="btn-open-queue-outline">Open Defect Queue</a></div></div></div><!-- Hourly Output SVG Chart Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>HOURLY OUTPUT</span>
                        <span>TARGET 40 UNITS / HR</span>
                    </div>
                    <div class="card-body">
                        <div class="legend-chart-row">
                            <span><span class="legend-dot-square" style="background:var(--accent-green);"></span> Good</span>
                            <span><span class="legend-dot-square" style="background:var(--brand-orange);"></span> Rejected, confirmed</span>
                            <span><span class="legend-dot-square" style="background:var(--accent-yellow);"></span> Pending review</span>
                        </div>
                        <div class="hourly-chart-container">
                            <svg class="hourly-svg-chart" viewBox="0 0 500 120" preserveAspectRatio="none"><g id="hourly-bars-group"></g></svg>
                        </div>
                    </div>
                </div>

                <!-- Yield By Workstation Card -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>YIELD BY WORKSTATION</span>
                        <small style="text-transform:none; opacity:0.8;">Pending units are not in the yield</small>
                    </div>
                    <div class="card-body" style="padding:0;">
                        <table class="yield-table">
                            <thead>
                                <tr>
                                    <th>WORKSTATION</th>
                                    <th>OUTPUT</th>
                                    <th>NG</th>
                                    <th>PENDING</th>
                                    <th>YIELD</th>
                                </tr>
                            </thead>
                            <tbody id="workstations-table-body">
                                <!-- Populated dynamically via JS -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Right Column Widgets -->
            <div>
                <!-- Shift Records List -->
                <div class="card">
                    <div class="card-header-bar header-blue">SHIFT RECORDS</div>
                    <div class="card-body">
                        <div class="filter-pills-row">
                            <button class="btn-filter-pill active" data-status="ALL">All · <span id="count-all">0</span></button>
                            <button class="btn-filter-pill" data-status="OPEN">Open · <span id="count-open">0</span></button>
                            <button class="btn-filter-pill" data-status="CLOSED">Closed · <span id="count-closed">0</span></button>
                        </div>
                        <div id="records-list-container">
                            <!-- Populated dynamically via JS -->
                        </div>
                    </div>
                </div>

                <!-- Rejects By Defect Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>REJECTS BY DEFECT</span>
                        <small style="text-transform:none; opacity:0.8;">Units this shift</small>
                    </div>
                    <div class="card-body">
                        <div id="rejects-by-defect-container"><p class="empty-state">No defect data available for this shift.</p></div></div></div><!-- Close Out Shift Widget -->
                <div class="card">
                    <div class="card-header-bar header-green">
                        <span>CLOSE OUT SHIFT</span>
                        <small id="closeout-status" style="color:#fff;">NO SHIFT SELECTED</small>
                    </div>
                    <div class="card-body">
                        <div class="check-step-item">
                            <div class="icon-check">✓</div>
                            <div>
                                <strong>Production target set</strong>
                                <div id="closeout-target" style="color:var(--text-secondary); font-size:0.75rem;">Target unavailable until a shift is selected.</div>
                            </div>
                        </div>

                        <div class="check-step-item">
                            <div class="icon-warn">!</div>
                            <div>
                                <strong>Defect reports reviewed</strong>
                                <div id="closeout-pending" style="color:var(--accent-yellow); font-size:0.75rem;">Pending review status unavailable.</div>
                            </div>
                        </div>

                        <div class="check-step-item">
                            <div class="icon-check">✓</div>
                            <div>
                                <strong>Shift time complete</strong>
                                <div id="closeout-time" style="color:var(--text-secondary); font-size:0.75rem;">Shift timing unavailable.</div>
                            </div>
                        </div>

                        <label style="font-size:0.75rem; color:var(--text-secondary); display:block; margin-top:10px;">Supervisor comment (optional)</label>
                        <textarea class="textarea-closeout" placeholder="Add optional shift summary comment..."></textarea>

                        <button class="btn-closeout-disabled" disabled>CLOSE OUT SHIFT</button>

                        <div class="export-btn-group">
                            <button class="btn-export-outline">Export PDF</button>
                            <button class="btn-export-outline">Export CSV</button>
                        </div>

                        <p style="font-size:0.7rem; color:var(--text-secondary); margin-top:10px; line-height:1.3;">
                            Closing out locks the shift totals and adds them to the yield history when the shift is eligible to close.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.btn-filter-pill').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active'); renderRecords(window.shiftRecords || [], btn.dataset.status || 'ALL');
      }));
      loadShiftYieldData();
    });
    function esc(v) { return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
    async function loadShiftYieldData() {
      try {
        const res = await fetch(BASE_URL + '/backend/get_shift_yield_data.php', {headers:{'Accept':'application/json'}});
        const data = await res.json();
        if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Could not load shift data.');
        const summary = data.summary;
        const empty = !summary;
        document.getElementById('shift-label').textContent = empty ? 'No shift selected' : `${summary.shift_title || 'Shift'} · ${summary.time_range || ''}`;
        document.getElementById('sum-yield').textContent = empty || Number(summary.produced_units || 0) === 0 ? '—' : `${Number(summary.yield_pct || 0).toFixed(1)}%`;
        document.getElementById('sum-good').textContent = empty ? '—' : Number(summary.good_units || 0);
        document.getElementById('sum-rejected').textContent = empty ? '—' : Number(summary.rejected_units ?? summary.ng_units ?? 0);
        document.getElementById('sum-pending').textContent = empty ? '—' : Number(summary.pending_units || 0);
        document.getElementById('yield-note').textContent = empty ? 'No active shift data' : `Target: ${summary.yield_target ?? '—'}%`;
        document.getElementById('good-note').textContent = empty ? 'No production data' : `of ${Number(summary.produced_units || 0)} produced`;
        document.getElementById('rejected-note').textContent = empty ? 'No confirmed reports' : 'Confirmed rejected units';
        document.getElementById('pending-note').textContent = empty ? 'No pending reports' : 'Units awaiting review';
        const produced = Number(summary?.produced_units || 0), target = Number(summary?.line_target || 0), pct = target > 0 ? Math.min(100, produced / target * 100) : 0;
        document.getElementById('output-target-label').textContent = empty ? '— / — units' : `${produced} / ${target || '—'} units · ${target ? Math.round(pct) + '%' : 'target not set'}`;
        document.getElementById('output-progress').style.width = `${pct}%`;
        document.getElementById('pace-message').textContent = empty ? 'Pace estimate will appear when current shift production data is available.' : 'Hourly pace estimates are unavailable until timestamped production counts are recorded.';
        document.getElementById('pending-warning-title').textContent = empty ? 'No active shift' : `${Number(summary.pending_units || 0)} pending units`;
        document.getElementById('pending-warning-text').textContent = empty ? 'Select or start a shift to view its defect impact.' : 'Pending reports are excluded from confirmed rejected totals until reviewed.';
        document.getElementById('closeout-status').textContent = empty ? 'NO SHIFT SELECTED' : esc(summary.status || 'OPEN');
        document.getElementById('closeout-target').textContent = empty ? 'Target unavailable until a shift is selected.' : `Target: ${target || 'Not set'} units`;
        document.getElementById('closeout-pending').textContent = empty ? 'No shift selected.' : `${Number(summary.pending_units || 0)} pending units`;
        document.getElementById('closeout-time').textContent = empty ? 'Shift timing unavailable.' : `Shift date: ${esc(summary.shift_date || '—')}`;
        renderWorkstations(data.workstations || []); window.shiftRecords = data.records || []; renderRecords(window.shiftRecords);
        renderDefects(data.defects || []); renderHourly(data.hourly || []);
        document.getElementById('count-all').textContent = window.shiftRecords.length;
        document.getElementById('count-open').textContent = window.shiftRecords.filter(r => String(r.status).toUpperCase() === 'OPEN').length;
        document.getElementById('count-closed').textContent = window.shiftRecords.filter(r => String(r.status).toUpperCase() === 'CLOSED').length;
      } catch (err) { document.getElementById('records-list-container').innerHTML = `<p class="empty-state">Unable to load shift records: ${esc(err.message)}</p>`; console.error(err); }
    }
    function renderWorkstations(rows) {
      const tbody=document.getElementById('workstations-table-body');
      if (!rows.length) { tbody.innerHTML='<tr><td colspan="5">No workstation yield records for this shift.</td></tr>'; return; }
      let out=0,ng=0,pending=0; tbody.innerHTML=rows.map(w=>{out+=Number(w.output_units||0);ng+=Number(w.ng_units||0);pending+=Number(w.pending_units||0);const y=Number(w.yield_pct||0);return `<tr><td><strong>${esc(w.workstation_name)}</strong><div style="font-size:.72rem;color:var(--text-secondary)">${esc(w.station_code)}</div></td><td>${Number(w.output_units||0)}</td><td>${Number(w.ng_units||0)}</td><td>${Number(w.pending_units||0)}</td><td><div class="yield-progress-track"><div class="yield-progress-bar" style="width:${Math.max(0,Math.min(100,y))}%;background:${y<98?'var(--brand-orange)':'var(--accent-green)'}"></div></div><strong>${y.toFixed(1)}%</strong></td></tr>`}).join('')+`<tr><td>Line total</td><td>${out}</td><td>${ng}</td><td>${pending}</td><td>${out+ng ? (out/(out+ng)*100).toFixed(1)+'%' : '—'}</td></tr>`;
    }
    function renderRecords(rows, status='ALL') {
      const filtered=rows.filter(r=>status==='ALL'||String(r.status).toUpperCase()===status), box=document.getElementById('records-list-container');
      box.innerHTML=filtered.length?filtered.map(r=>`<div class="record-item"><div class="record-info"><strong>${esc(r.shift_title)} · ${esc(r.shift_date)}</strong><small>${esc(r.time_range||'')} · ${Number(r.produced_units||0)} units</small></div><div>${String(r.status).toUpperCase()==='OPEN'?'<span class="record-badge-open">OPEN</span>':`<span class="record-badge-green">${Number(r.yield_pct||0).toFixed(1)}%<br><small>CLOSED</small></span>`}</div></div>`).join(''):'<p class="empty-state">No shift records found.</p>';
    }
    function renderDefects(rows) { const box=document.getElementById('rejects-by-defect-container'); if(!rows.length){box.innerHTML='<p class="empty-state">No defect records for this shift.</p>';return;} const max=Math.max(...rows.map(r=>Number(r.units||r.cnt||0)),1); box.innerHTML=rows.map(r=>{const n=Number(r.units||r.cnt||0);return `<div class="reject-item-row"><div class="reject-item-header"><strong>${esc(r.defect_category||'Unspecified')}</strong><span>${n} ${esc(r.status||'recorded')}</span></div><div class="reject-bar-track"><div style="width:${n/max*100}%;height:100%;background:${String(r.status).toLowerCase()==='pending'?'var(--accent-yellow)':'var(--brand-orange)'}"></div></div></div>`}).join(''); }
    function renderHourly(rows) { const g=document.getElementById('hourly-bars-group'); if(!rows.length){g.innerHTML='<text x="250" y="60" fill="var(--text-secondary)" font-size="12" text-anchor="middle">No timestamped hourly production data available</text>';return;} const max=Math.max(40,...rows.map(r=>Number(r.good_units||0)+Number(r.ng_units||0))), scale=72/max; g.innerHTML=rows.slice(0,8).map((r,i)=>{const x=35+i*55,good=Number(r.good_units||0),ng=Number(r.ng_units||0),h=good*scale,nh=ng*scale;return `<rect x="${x}" y="${104-h}" width="22" height="${h}" fill="var(--accent-green)" rx="2"/><rect x="${x}" y="${104-h-nh}" width="22" height="${nh}" fill="var(--brand-orange)" rx="2"/><text x="${x+11}" y="${98-h-nh}" fill="var(--text-primary)" font-size="9" text-anchor="middle">${good+ng}</text><text x="${x+11}" y="115" fill="var(--text-secondary)" font-size="8" text-anchor="middle">${esc(r.hour_label)}</text>`}).join(''); }
    </script>
</body>
</html>

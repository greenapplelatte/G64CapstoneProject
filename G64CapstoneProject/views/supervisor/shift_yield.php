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
    <!-- Builds the formatted Excel report (.xlsx) -->
    <script src="https://cdn.jsdelivr.net/npm/exceljs@4.4.0/dist/exceljs.min.js"></script>
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Shift Yield Reports</h1>
                <p class="subtitle">Total shift summaries. Check yield against target, inspect workstation defects, and close out shifts with automatic report downloads and real-time records.</p>
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
                        <div class="warning-callout-gold">
                            <div class="warning-callout-text">
                                <strong id="pending-warning-title">Pending quantity unavailable</strong>
                                <small id="pending-warning-text">Pending report impact will be calculated from actual reports.</small>
                            </div>
                            <a href="<?= BASE_URL ?>/views/supervisor/defect_reporting.php" class="btn-open-queue-outline">Open Defect Queue</a>
                        </div>
                    </div>
                </div>

                <!-- Hourly Output SVG Chart Card -->
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
                        <div id="records-list-container" aria-live="polite"></div>
                        <div class="shift-pagination" id="shift-pagination" aria-label="Shift record pages">
                            <button type="button" id="shift-prev" class="shift-page-btn">← Previous</button>
                            <span id="shift-page-status" class="shift-page-count">Page 1 of 1</span>
                            <button type="button" id="shift-next" class="shift-page-btn">Next →</button>
                        </div>
                    </div>
                </div>

                <!-- Rejects By Defect Widget -->
                <div class="card">
                    <div class="card-header-bar header-blue">
                        <span>REJECTS BY DEFECT</span>
                        <small id="defect-live-status" style="text-transform:none; opacity:0.8;" aria-live="polite">Confirmed units · updating every 5s</small>
                    </div>
                    <div class="card-body">
                        <div id="rejects-by-defect-container"><p class="empty-state">No defect data available for this shift.</p></div>
                    </div>
                </div>

                <!-- Close Out Shift Widget -->
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
                        <textarea class="textarea-closeout" id="closeout-comment" placeholder="Add optional shift summary comment..."></textarea>

                        <button class="btn-closeout-disabled" id="btn-closeout-shift" type="button" disabled>CLOSE OUT SHIFT</button>

                        <div class="export-btn-group">
                            <button class="btn-export-outline" id="export-shift-pdf" type="button">Export PDF</button>
                            <button class="btn-export-outline" id="export-shift-xlsx" type="button">Export Excel</button>
                        </div>

                        <p style="font-size:0.7rem; color:var(--text-secondary); margin-top:10px; line-height:1.3;">
                            Closing out locks totals, triggers confirmation, automatically downloads the Excel report, and updates shift records in real-time.
                        </p>
                    </div>
                </div>
            </div>
        </section>
    </main>

    <style>
      .shift-pagination{display:flex;justify-content:space-between;align-items:center;gap:10px;margin-top:12px;padding-top:12px;border-top:1px solid var(--border-color)}
      .shift-page-btn{border:1px solid var(--border-color);background:var(--bg-card);color:var(--text-primary);padding:9px 12px;border-radius:8px;font-weight:700;font-size:.76rem;cursor:pointer;transition:background .15s}
      .shift-page-btn:not(:disabled):hover{background:#25436c;border-color:#4d79b0}
      .shift-page-btn:disabled{opacity:.4;cursor:not-allowed}
      .shift-page-count{font-size:.76rem;color:var(--text-secondary);white-space:nowrap}
      .shift-record-card{border:1px solid var(--border-color);background:var(--bg-card);border-radius:10px;padding:13px;margin-bottom:10px}
      .shift-record-head{display:flex;justify-content:space-between;align-items:start;gap:8px}
      .shift-record-code{font-weight:800;font-size:.93rem;overflow-wrap:anywhere}
      .shift-record-sub{font-size:.72rem;color:var(--text-secondary);margin-top:3px}
      .shift-record-badge{font-size:.66rem;font-weight:800;padding:4px 8px;border-radius:5px;white-space:nowrap}
      .shift-record-badge.is-closed{background:#174e34;color:#8ff0ad}
      .shift-record-badge.is-open{background:#54420d;color:#ffdb70}
      .shift-record-dates{display:grid;grid-template-columns:1fr 1fr;gap:10px;margin:11px 0;padding:10px 0;border-top:1px solid var(--border-color);border-bottom:1px solid var(--border-color)}
      .shift-record-stats{display:grid;grid-template-columns:repeat(3,minmax(0,1fr));gap:8px;margin:10px 0}
      .shift-record-stat{background:rgba(127,145,166,.08);padding:9px 7px;border-radius:7px;min-width:0}
      .shift-record-stat strong{font-size:.94rem;display:block;margin-top:3px;overflow-wrap:anywhere}
      .shift-record-card small{display:block;color:var(--text-secondary);font-size:.68rem}
      .shift-record-extra{font-size:.74rem;line-height:1.5;color:var(--text-secondary)}
      .shift-record-extra strong{color:var(--text-primary)}
      .shift-record-notes{border-top:1px solid var(--border-color);margin-top:10px;padding-top:9px;font-size:.75rem;overflow-wrap:anywhere}
      @media(max-width:390px){.shift-record-dates{grid-template-columns:1fr}.shift-page-btn{padding:8px 7px}.shift-record-stats{gap:4px}.shift-record-stat{padding:7px 5px}}
    </style>
    <script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;
    const SHIFT_PAGE_SIZE = 2;
    let shiftPage = 1;
    let lastShiftFilter = 'ALL';
    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('.btn-filter-pill').forEach(btn => btn.addEventListener('click', () => {
        document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
        btn.classList.add('active'); 
        shiftPage = 1;
        renderRecords(window.shiftRecords || [], btn.dataset.status || 'ALL');
      }));
      
      document.getElementById('shift-prev').addEventListener('click', () => { shiftPage--; renderRecords(window.shiftRecords || [], lastShiftFilter); });
      document.getElementById('shift-next').addEventListener('click', () => { shiftPage++; renderRecords(window.shiftRecords || [], lastShiftFilter); });
      document.getElementById('btn-closeout-shift').addEventListener('click', closeOutShift);
      document.getElementById('export-shift-pdf').addEventListener('click', exportShiftPdf);
      document.getElementById('export-shift-xlsx').addEventListener('click', exportShiftXlsx);
      
      loadShiftYieldData();
      setInterval(() => { if (!document.hidden) loadShiftYieldData(true); }, 5000);
    });

    function esc(v) { 
        return String(v ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); 
    }

    let shiftYieldLoading = false;
    async function loadShiftYieldData(silent = false) {
      if (shiftYieldLoading) return;
      shiftYieldLoading = true;
      try {
        const res = await fetch(BASE_URL + '/backend/get_shift_yield_data.php?_=' + Date.now(), {cache:'no-store', headers:{'Accept':'application/json'}});
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
        document.getElementById('pace-message').textContent = empty ? 'Pace estimate will appear when current shift production data is available.' : 'Hourly pace estimates are active.';
        
        document.getElementById('pending-warning-title').textContent = empty ? 'No active shift' : `${Number(summary.pending_units || 0)} pending units`;
        document.getElementById('pending-warning-text').textContent = empty ? 'Select or start a shift to view its defect impact.' : 'Pending reports are excluded from confirmed rejected totals until reviewed.';
        
        document.getElementById('closeout-status').textContent = empty ? 'NO SHIFT SELECTED' : esc(summary.status || 'OPEN');
        document.getElementById('closeout-target').textContent = empty ? 'Target unavailable until a shift is selected.' : `Target: ${target || 'Not set'} units`;
        document.getElementById('closeout-pending').textContent = empty ? 'No shift selected.' : `${Number(data.closeout?.pending_reports || 0)} report(s) awaiting review`;
        document.getElementById('closeout-time').textContent = empty ? 'Shift timing unavailable.' : `Shift date: ${esc(summary.shift_date || '—')}`;
        
        const closeButton = document.getElementById('btn-closeout-shift');
        const canClose = Boolean(data.closeout?.can_close);
        closeButton.disabled = !canClose;
        closeButton.classList.toggle('btn-closeout-enabled', canClose);
        closeButton.classList.toggle('btn-closeout-disabled', !canClose);
        closeButton.title = canClose ? '' : (empty ? 'No open shift to close.' : Number(data.closeout?.pending_reports || 0) > 0 ? 'Review all defect reports before closing.' : 'The production session is already closed.');
        document.getElementById('closeout-status').textContent = empty ? 'NO SHIFT SELECTED' : canClose ? 'READY TO CLOSE' : Number(data.closeout?.pending_reports || 0) > 0 ? 'REVIEW DEFECTS FIRST' : 'SESSION CLOSED';
        
        renderWorkstations(data.workstations || []); 
        window.shiftRecords = data.records || []; 
        const selectedFilter = document.querySelector('.btn-filter-pill.active')?.dataset.status || 'ALL';
        renderRecords(window.shiftRecords, selectedFilter);
        window.shiftYieldData = data;
        
        renderDefects(data.defects || []);
        document.getElementById('defect-live-status').textContent = 'Confirmed units · updated ' + new Date().toLocaleTimeString('en-PH', {hour:'2-digit', minute:'2-digit', second:'2-digit'});
        renderHourly(data.hourly || []);
        
        document.getElementById('count-all').textContent = window.shiftRecords.length;
        document.getElementById('count-open').textContent = window.shiftRecords.filter(r => String(r.status).toUpperCase() === 'OPEN').length;
        document.getElementById('count-closed').textContent = window.shiftRecords.filter(r => String(r.status).toUpperCase() === 'CLOSED').length;
      } catch (err) { 
          if (!silent) document.getElementById('records-list-container').innerHTML = `<p class="empty-state">Unable to load shift records: ${esc(err.message)}</p>`; 
          document.getElementById('defect-live-status').textContent = 'Update failed · retrying';
          console.error(err); 
      } finally {
          shiftYieldLoading = false;
      }
    }

    // Close Out Shift with Confirmation, Automatic Report Download, and Real-Time Update
    async function closeOutShift() {
      const button = document.getElementById('btn-closeout-shift');
      if (button.disabled) return;

      // 1. Notification / Confirmation Prompt
      const confirmed = await systemConfirm(
        'Are you sure you want to close out this shift?\n\nClosing out locks the shift totals, adds them to yield history, and will automatically download the Excel report.', 
        'Close Shift & Download Report'
      );
      if (!confirmed) return;

      button.disabled = true;
      const form = new FormData();
      form.append('status', 'CLOSED');
      form.append('closeout_comment', document.getElementById('closeout-comment').value);
      
      try {
        const response = await fetch(BASE_URL + '/backend/process_line_control.php?action=toggle_session', {
            method: 'POST', 
            body: form, 
            headers: {'Accept': 'application/json'}
        });
        const result = await response.json();
        
        if (!response.ok || result.status !== 'success') {
            throw new Error(result.message || 'Unable to close the shift.');
        }
        
        document.getElementById('closeout-comment').value = '';

        // 2. Refresh the data so the report reflects the closed shift, then download the formatted Excel file
        while (shiftYieldLoading) await new Promise(r => setTimeout(r, 100));
        await loadShiftYieldData();
        if (window.shiftYieldData) {
            await exportShiftXlsx(); // falls back to a CSV by itself if the Excel library is missing
        }

      } catch (err) {
        alert(err.message);
        await loadShiftYieldData();
      }
    }

    // A real PDF download, generated from MySQL on the server (not window.print()).
    // The server rechecks the Supervisor role before exporting.
    async function exportShiftPdf() {
      const button = document.getElementById('export-shift-pdf');
      const oldText = button.textContent;
      if (button.disabled) return;
      button.disabled = true;
      button.textContent = 'Generating PDF...';
      try {
        const response = await fetch(BASE_URL + '/backend/export_shift_pdf.php?_=' + Date.now(), {
          method: 'GET', credentials: 'same-origin', cache: 'no-store',
          headers: {'Accept': 'application/pdf'}
        });
        const type = response.headers.get('content-type') || '';
        if (!response.ok || !type.includes('application/pdf')) {
          const message = await response.text();
          throw new Error(response.status === 401 || response.status === 403
            ? 'Your session expired or you do not have permission to export this report.'
            : (message && message.length < 250 ? message : 'PDF report generation failed.'));
        }
        const blob = await response.blob();
        if (!blob.size) throw new Error('The PDF report was empty.');
        const url = URL.createObjectURL(blob);
        const link = document.createElement('a');
        link.href = url;
        const local = new Date();
        const stamp = [local.getFullYear(), String(local.getMonth() + 1).padStart(2, '0'), String(local.getDate()).padStart(2, '0')].join('-');
        link.download = `shift-yield-${stamp}.pdf`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
      } catch (error) {
        console.error('PDF export error:', error);
        alert(error.message || 'Unable to generate PDF.');
      } finally {
        button.disabled = false;
        button.textContent = oldText;
      }
    }

    // ---------- Formatted Excel (.xlsx) export ----------
    function buildShiftWorkbook(data) {
      const C = {
        navy:'FF1F3A5F', green:'FF2E7D32', greenBg:'FFE8F5E9', orange:'FFC55A11', orangeBg:'FFFDECDC',
        amber:'FF8A6D00', amberBg:'FFFFF4CC', band:'FFF4F6F9', line:'FFD0D7E2', label:'FFEEF2F7',
        ink:'FF1B2430', muted:'FF5A6675', white:'FFFFFFFF'
      };
      const thin = {style:'thin', color:{argb:C.line}};
      const box = {top:thin, left:thin, bottom:thin, right:thin};
      const solid = argb => ({type:'pattern', pattern:'solid', fgColor:{argb}});
      const num = v => Number(v || 0);
      const pct = v => num(v) / 100;
      const fmtTime = value => {
        if (!value) return '—';
        const raw = String(value);
        const date = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/.test(raw) ? raw : raw.replace(' ', 'T') + '+08:00');
        return Number.isNaN(date.getTime()) ? raw : new Intl.DateTimeFormat('en-PH', {
          timeZone:'Asia/Manila', month:'short', day:'2-digit', year:'numeric',
          hour:'2-digit', minute:'2-digit', hour12:true
        }).format(date);
      };
      const put = (cell, value, o = {}) => {
        cell.value = value;
        cell.font = {name:'Calibri', size:o.size || 11, bold:!!o.bold, italic:!!o.italic, color:{argb:o.color || C.ink}};
        if (o.fill) cell.fill = solid(o.fill);
        cell.alignment = {vertical:'middle', horizontal:o.h || 'left', wrapText:!!o.wrap, indent:o.indent || 0};
        if (o.fmt) cell.numFmt = o.fmt;
        if (o.border !== false) cell.border = box;
        return cell;
      };

      const wb = new ExcelJS.Workbook();
      wb.creator = 'Masters Vineyard Enterprise';
      wb.created = new Date();

      // ===== Sheet 1: Summary =====
      const s = data.summary || {};
      const ws = wb.addWorksheet('Summary', {
        views:[{showGridLines:false}],
        pageSetup:{paperSize:9, orientation:'portrait', fitToPage:true, fitToWidth:1, fitToHeight:0}
      });
      ws.columns = [{width:30},{width:16},{width:14},{width:14},{width:14},{width:14}];

      ws.mergeCells('A1:F1');
      put(ws.getCell('A1'), 'MASTERS VINEYARD ENTERPRISE', {size:16, bold:true, color:C.white, fill:C.navy, indent:1, border:false});
      ws.getRow(1).height = 32;
      ws.mergeCells('A2:F2');
      put(ws.getCell('A2'), 'Shift Yield Report', {size:12, color:C.white, fill:C.navy, indent:1, border:false});
      ws.getRow(2).height = 22;
      ws.mergeCells('A3:F3');
      put(ws.getCell('A3'), 'Generated ' + new Intl.DateTimeFormat('en-PH', {timeZone:'Asia/Manila', dateStyle:'medium', timeStyle:'short'}).format(new Date()),
          {size:9, italic:true, color:C.muted, indent:1, border:false});

      let r = 5;
      const section = text => {
        ws.mergeCells(`A${r}:F${r}`);
        put(ws.getCell(`A${r}`), text, {bold:true, color:C.white, fill:C.green, indent:1, border:false});
        ws.getRow(r).height = 20;
        r++;
      };

      // Shift details
      section('SHIFT DETAILS');
      const details = [
        ['Shift', s.shift_title || '—'],
        ['Date', s.shift_date || '—'],
        ['Status', String(s.status || '—').toUpperCase()],
        ['Time range', s.time_range || '—'],
        ['Line target (units)', num(s.line_target), '#,##0'],
        ['Yield target', s.yield_target != null ? pct(s.yield_target) : '—', '0.0%']
      ];
      details.forEach(([label, value, fmt]) => {
        put(ws.getCell(`A${r}`), label, {bold:true, fill:C.label, indent:1});
        ws.mergeCells(`B${r}:F${r}`);
        ['B','C','D','E','F'].forEach(col => { const c = ws.getCell(`${col}${r}`); c.fill = solid(C.white); c.border = box; });
        put(ws.getCell(`B${r}`), value, {fmt, indent:1, fill:C.white});
        r++;
      });
      r++;

      // KPI strip
      section('SHIFT SUMMARY');
      const produced = num(s.produced_units), target = num(s.line_target);
      const kpis = [
        ['PRODUCED', produced, '#,##0', C.ink],
        ['GOOD UNITS', num(s.good_units), '#,##0', C.green],
        ['REJECTED (NG)', num(s.rejected_units ?? s.ng_units), '#,##0', C.orange],
        ['PENDING REVIEW', num(s.pending_units), '#,##0', C.amber],
        ['YIELD', produced > 0 ? pct(s.yield_pct) : '—', '0.0%', C.green],
        ['OUTPUT VS TARGET', target > 0 ? produced / target : '—', '0%', C.ink]
      ];
      kpis.forEach(([label, value, fmt, color], i) => {
        const col = 'ABCDEF'[i];
        put(ws.getCell(`${col}${r}`), label, {size:9, bold:true, color:C.muted, fill:C.label, h:'center'});
        put(ws.getCell(`${col}${r + 1}`), value, {size:18, bold:true, color, fmt, h:'center'});
      });
      ws.getRow(r).height = 20;
      ws.getRow(r + 1).height = 36;
      r += 3;

      // Yield by workstation
      section('YIELD BY WORKSTATION');
      ['Workstation','Station code','Output','NG','Pending','Yield'].forEach((h, i) => {
        put(ws.getCell(`${'ABCDEF'[i]}${r}`), h, {bold:true, color:C.white, fill:C.navy, h:i < 2 ? 'left' : 'center', indent:i === 0 ? 1 : 0});
      });
      ws.getRow(r).height = 22;
      r++;
      const stations = data.workstations || [];
      if (!stations.length) {
        ws.mergeCells(`A${r}:F${r}`);
        put(ws.getCell(`A${r}`), 'No workstation yield records for this shift.', {italic:true, color:C.muted, indent:1});
        r++;
      } else {
        const first = r;
        let out = 0, ng = 0, pend = 0;
        stations.forEach((w, i) => {
          const band = i % 2 ? C.band : C.white;
          const y = num(w.yield_pct);
          out += num(w.output_units); ng += num(w.ng_units); pend += num(w.pending_units);
          put(ws.getCell(`A${r}`), w.workstation_name || '—', {bold:true, fill:band, indent:1});
          put(ws.getCell(`B${r}`), w.station_code || '—', {fill:band, color:C.muted});
          put(ws.getCell(`C${r}`), num(w.output_units), {fill:band, fmt:'#,##0', h:'center'});
          put(ws.getCell(`D${r}`), num(w.ng_units), {fill:band, fmt:'#,##0', h:'center'});
          put(ws.getCell(`E${r}`), num(w.pending_units), {fill:band, fmt:'#,##0', h:'center'});
          put(ws.getCell(`F${r}`), y / 100, {fmt:'0.0%', h:'center', bold:true,
              fill:y < 98 ? C.orangeBg : C.greenBg, color:y < 98 ? C.orange : C.green});
          r++;
        });
        const last = r - 1;
        put(ws.getCell(`A${r}`), 'Line total', {bold:true, fill:C.label, indent:1});
        put(ws.getCell(`B${r}`), '', {fill:C.label});
        put(ws.getCell(`C${r}`), {formula:`SUM(C${first}:C${last})`, result:out}, {bold:true, fill:C.label, fmt:'#,##0', h:'center'});
        put(ws.getCell(`D${r}`), {formula:`SUM(D${first}:D${last})`, result:ng}, {bold:true, fill:C.label, fmt:'#,##0', h:'center'});
        put(ws.getCell(`E${r}`), {formula:`SUM(E${first}:E${last})`, result:pend}, {bold:true, fill:C.label, fmt:'#,##0', h:'center'});
        put(ws.getCell(`F${r}`), {formula:`IF(C${r}+D${r}=0,"",C${r}/(C${r}+D${r}))`, result:(out + ng) ? out / (out + ng) : ''},
            {bold:true, fill:C.label, fmt:'0.0%', h:'center'});
        ['A','B','C','D','E','F'].forEach(col => { ws.getCell(`${col}${r}`).border = {...box, top:{style:'medium', color:{argb:C.navy}}}; });
        r++;
      }
      ws.mergeCells(`A${r}:F${r}`);
      put(ws.getCell(`A${r}`), 'Pending units are not included in the yield.', {size:9, italic:true, color:C.muted, indent:1, border:false});
      r += 2;

      // Rejects by defect
      section('REJECTS BY DEFECT');
      ['Defect category','Status','Units'].forEach((h, i) => {
        put(ws.getCell(`${'ABC'[i]}${r}`), h, {bold:true, color:C.white, fill:C.navy, h:i === 2 ? 'center' : 'left', indent:i === 0 ? 1 : 0});
      });
      ws.getRow(r).height = 22;
      r++;
      const defects = data.defects || [];
      if (!defects.length) {
        ws.mergeCells(`A${r}:F${r}`);
        put(ws.getCell(`A${r}`), 'No confirmed rejects recorded for this shift.', {italic:true, color:C.muted, indent:1});
      } else {
        defects.forEach((d, i) => {
          const band = i % 2 ? C.band : C.white;
          const status = String(d.status || 'recorded');
          const pending = status.toLowerCase() === 'pending';
          put(ws.getCell(`A${r}`), d.defect_category || 'Unspecified', {bold:true, fill:band, indent:1});
          put(ws.getCell(`B${r}`), status.charAt(0).toUpperCase() + status.slice(1),
              {fill:pending ? C.amberBg : C.orangeBg, color:pending ? C.amber : C.orange, bold:true});
          put(ws.getCell(`C${r}`), num(d.units || d.cnt), {fill:band, fmt:'#,##0', h:'center'});
          r++;
        });
      }

      // ===== Sheet 2: Shift Records =====
      const rs = wb.addWorksheet('Shift Records', {
        views:[{state:'frozen', xSplit:1, ySplit:1, showGridLines:false}],
        pageSetup:{paperSize:9, orientation:'landscape', fitToPage:true, fitToWidth:1, fitToHeight:0}
      });
      const cols = [
        ['Session Code',20],['Date',13],['Shift',16],['Status',11],['Product',22],['Opened By',18],
        ['Started',20],['Closed',20],['Good',10],['Rejected',10],['Pending',10],['Produced',11],
        ['Yield',10],['Target',10],['Planned (min)',14],['Run (min)',11],['Closeout Notes',40]
      ];
      rs.columns = cols.map(([header, width]) => ({header, width}));
      const head = rs.getRow(1);
      head.height = 24;
      cols.forEach(([h], i) => put(head.getCell(i + 1), h, {bold:true, color:C.white, fill:C.navy, h:i >= 8 && i <= 15 ? 'center' : 'left', indent:i === 0 ? 1 : 0}));

      (data.records || []).forEach((rec, i) => {
        const row = rs.getRow(i + 2);
        const band = i % 2 ? C.band : C.white;
        const closed = String(rec.status || '').toUpperCase() === 'CLOSED';
        const good = num(rec.good_units), rejected = num(rec.rejected_units);
        const hasYield = good + rejected > 0;
        const y = num(rec.yield_pct);
        const values = [
          rec.session_code || 'Legacy record', rec.shift_date || '', rec.shift_title || 'Shift', closed ? 'CLOSED' : 'OPEN',
          rec.product_name || '—', rec.opened_by || '—', fmtTime(rec.started_at), closed ? fmtTime(rec.closed_at) : 'In progress',
          good, rejected, num(rec.pending_units), num(rec.produced_units),
          hasYield ? y / 100 : 'N/A', num(rec.line_target),
          rec.planned_minutes == null ? '—' : num(rec.planned_minutes), rec.run_minutes == null ? '—' : num(rec.run_minutes),
          rec.closeout_comment || ''
        ];
        values.forEach((v, c) => {
          const o = {fill:band, h:c >= 8 && c <= 15 ? 'center' : 'left', indent:c === 0 ? 1 : 0};
          if (c === 0) o.bold = true;
          if (c >= 8 && c <= 11 || c === 13) o.fmt = '#,##0';
          if (c === 12) {
            o.fmt = '0.0%'; o.bold = true;
            if (hasYield) { o.fill = y < 98 ? C.orangeBg : C.greenBg; o.color = y < 98 ? C.orange : C.green; }
            else o.color = C.muted;
          }
          if (c === 3) { o.bold = true; o.h = 'center'; o.fill = closed ? C.greenBg : C.amberBg; o.color = closed ? C.green : C.amber; }
          if (c === 16) o.wrap = true;
          put(row.getCell(c + 1), v, o);
        });
        row.alignment = {vertical:'middle'};
      });
      rs.autoFilter = {from:{row:1, column:1}, to:{row:1, column:cols.length}};

      return wb;
    }

    async function exportShiftXlsx() {
      const data = window.shiftYieldData;
      if (!data) { alert('Shift data is not loaded yet.'); return; }
      if (typeof ExcelJS === 'undefined') {
        alert('The Excel library could not be loaded. Downloading a CSV instead.');
        exportShiftCsv();
        return;
      }
      const button = document.getElementById('export-shift-xlsx');
      const oldText = button ? button.textContent : '';
      if (button) { button.disabled = true; button.textContent = 'Building Excel...'; }
      try {
        const buffer = await buildShiftWorkbook(data).xlsx.writeBuffer();
        const url = URL.createObjectURL(new Blob([buffer], {type:'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'}));
        const local = new Date();
        const stamp = [local.getFullYear(), String(local.getMonth() + 1).padStart(2, '0'), String(local.getDate()).padStart(2, '0')].join('-');
        const link = document.createElement('a');
        link.href = url;
        link.download = `shift-yield-${stamp}.xlsx`;
        document.body.appendChild(link);
        link.click();
        link.remove();
        setTimeout(() => URL.revokeObjectURL(url), 30000);
      } catch (error) {
        console.error('Excel export error:', error);
        alert(error.message || 'Unable to generate the Excel file.');
      } finally {
        if (button) { button.disabled = false; button.textContent = oldText; }
      }
    }

    function csvCell(value) {
      let text = String(value ?? '');
      if (/^[=+\-@]/.test(text)) text = "'" + text;
      return '"' + text.replace(/"/g, '""') + '"';
    }

    function exportShiftCsv() {
      const data = window.shiftYieldData;
      if (!data) { alert('Shift data is not loaded yet.'); return; }
      const headers = ['record_type','shift_date','shift_title','status','workstation','station_code','good_units','rejected_units','pending_units','yield_pct','line_target','closeout_comment'];
      const rows = (data.records || []).map(r => ['shift',r.shift_date,r.shift_title,r.status,'','',r.good_units,r.rejected_units,r.pending_units,r.yield_pct,r.line_target,r.closeout_comment]);
      if (data.summary) {
        (data.workstations || []).forEach(w => rows.push(['workstation',data.summary.shift_date,data.summary.shift_title,data.summary.status,w.workstation_name,w.station_code,w.output_units,w.ng_units,w.pending_units,w.yield_pct,data.summary.line_target,'']));
      }
      const csv = [headers, ...rows].map(row => row.map(csvCell).join(',')).join('\r\n');
      const url = URL.createObjectURL(new Blob([csv], {type:'text/csv;charset=utf-8'}));
      const link = document.createElement('a');
      link.href = url;
      link.download = `shift-yield-${new Date().toISOString().slice(0,10)}.csv`;
      link.click();
      URL.revokeObjectURL(url);
    }

    function renderWorkstations(rows) {
      const tbody = document.getElementById('workstations-table-body');
      if (!rows.length) { tbody.innerHTML='<tr><td colspan="5">No workstation yield records for this shift.</td></tr>'; return; }
      let out=0, ng=0, pending=0; 
      tbody.innerHTML = rows.map(w => {
          out += Number(w.output_units || 0);
          ng += Number(w.ng_units || 0);
          pending += Number(w.pending_units || 0);
          const y = Number(w.yield_pct || 0);
          return `<tr><td><strong>${esc(w.workstation_name)}</strong><div style="font-size:.72rem;color:var(--text-secondary)">${esc(w.station_code)}</div></td><td>${Number(w.output_units||0)}</td><td>${Number(w.ng_units||0)}</td><td>${Number(w.pending_units||0)}</td><td><div class="yield-progress-track"><div class="yield-progress-bar" style="width:${Math.max(0,Math.min(100,y))}%;background:${y<98?'var(--brand-orange)':'var(--accent-green)'}"></div></div><strong>${y.toFixed(1)}%</strong></td></tr>`;
      }).join('') + `<tr><td>Line total</td><td>${out}</td><td>${ng}</td><td>${pending}</td><td>${out+ng ? (out/(out+ng)*100).toFixed(1)+'%' : '—'}</td></tr>`;
    }

    function renderRecords(rows, status='ALL') {
      lastShiftFilter = status;
      const filtered = rows.filter(r => status === 'ALL' || String(r.status).toUpperCase() === status);
      const box = document.getElementById('records-list-container');
      const pages = Math.max(1, Math.ceil(filtered.length / SHIFT_PAGE_SIZE));
      shiftPage = Math.max(1, Math.min(shiftPage, pages));
      document.getElementById('shift-page-status').textContent = filtered.length
        ? `Page ${shiftPage} of ${pages} · ${filtered.length} records` : 'No records';
      document.getElementById('shift-prev').disabled = shiftPage <= 1;
      document.getElementById('shift-next').disabled = shiftPage >= pages;
      document.getElementById('shift-pagination').style.display = filtered.length ? 'flex' : 'none';
      if (!filtered.length) {
        box.innerHTML = '<p class="empty-state">No shift records found for this filter.</p>';
        return;
      }
      const timeFmt = value => {
        if (!value) return 'Not recorded';
        const raw = String(value);
        const date = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/.test(raw) ? raw : raw.replace(' ', 'T') + '+08:00');
        return Number.isNaN(date.getTime()) ? raw : new Intl.DateTimeFormat('en-PH', {
          timeZone:'Asia/Manila',month:'short',day:'2-digit',year:'numeric',
          hour:'2-digit',minute:'2-digit',hour12:true
        }).format(date);
      };
      const current = filtered.slice((shiftPage - 1) * SHIFT_PAGE_SIZE, shiftPage * SHIFT_PAGE_SIZE);
      box.innerHTML = current.map(r => {
        const closed = String(r.status).toUpperCase() === 'CLOSED';
        const good = Number(r.good_units || 0);
        const rejected = Number(r.rejected_units || 0);
        const total = Number(r.produced_units || 0);
        const yieldText = good + rejected > 0 ? `${Number(r.yield_pct || 0).toFixed(1)}%` : 'N/A';
        const elapsed = r.planned_minutes == null ? 'Not recorded' : `${Number(r.planned_minutes)} min`;
        const runtime = r.run_minutes == null ? 'Not recorded' : `${Number(r.run_minutes)} min`;
        return `<article class="shift-record-card">
          <div class="shift-record-head">
            <div><div class="shift-record-code">${esc(r.session_code || 'Legacy record')}</div>
              <div class="shift-record-sub">${esc(r.shift_title || 'Shift')} · ${esc(r.shift_date || '')}</div></div>
            <span class="shift-record-badge ${closed ? 'is-closed' : 'is-open'}">${closed ? 'CLOSED' : 'OPEN'}</span>
          </div>
          <div class="shift-record-extra" style="margin-top:10px;"><strong>${esc(r.product_name || 'Product not recorded')}</strong> · Opened by ${esc(r.opened_by || 'Not recorded')}</div>
          <div class="shift-record-dates">
            <div><small>STARTED</small><strong style="font-size:.75rem">${esc(timeFmt(r.started_at))}</strong></div>
            <div><small>CLOSED</small><strong style="font-size:.75rem">${closed ? esc(timeFmt(r.closed_at)) : 'In progress'}</strong></div>
          </div>
          <div class="shift-record-stats">
            <div class="shift-record-stat"><small>GOOD</small><strong>${good}</strong></div>
            <div class="shift-record-stat"><small>REJECTED</small><strong>${rejected}</strong></div>
            <div class="shift-record-stat"><small>YIELD</small><strong>${yieldText}</strong></div>
          </div>
          <div class="shift-record-extra">Total produced: <strong>${total}</strong> · Target: <strong>${Number(r.line_target || 0)}</strong></div>
          <div class="shift-record-extra">Elapsed / Run: <strong>${esc(elapsed)} / ${esc(runtime)}</strong></div>
          ${r.closeout_comment ? `<div class="shift-record-notes">Notes: ${esc(r.closeout_comment)}</div>` : ''}
        </article>`;
      }).join('');
    }

    function renderDefects(rows) { 
      const box = document.getElementById('rejects-by-defect-container'); 
      if (!rows.length) { box.innerHTML = '<p class="empty-state">No confirmed rejects recorded for this shift.</p>'; return; } 
      const max = Math.max(...rows.map(r => Number(r.units || r.cnt || 0)), 1); 
      box.innerHTML = rows.map(r => {
          const n = Number(r.units || r.cnt || 0);
          return `<div class="reject-item-row" style="margin-bottom:10px;"><div class="reject-item-header" style="display:flex; justify-content:space-between; font-size:0.8rem; margin-bottom:4px;"><strong>${esc(r.defect_category || 'Unspecified')}</strong><span>${n} ${esc(r.status || 'recorded')}</span></div><div class="reject-bar-track" style="background:var(--border-color); height:6px; border-radius:3px; overflow:hidden;"><div style="width:${n/max*100}%; height:100%; background:${String(r.status).toLowerCase()==='pending'?'var(--accent-yellow)':'var(--brand-orange)'};"></div></div></div>`;
      }).join(''); 
    }

    function renderHourly(rows) { 
      const g = document.getElementById('hourly-bars-group'); 
      if (!rows || !rows.length) { g.innerHTML = '<text x="250" y="60" fill="var(--text-secondary)" font-size="12" text-anchor="middle">No timestamped hourly production data available</text>'; return; } 
      const max = Math.max(40, ...rows.map(r => Number(r.good_units || 0) + Number(r.ng_units || 0))), scale = 72/max; 
      g.innerHTML = rows.slice(0, 8).map((r, i) => {
          const x = 35 + i * 55, good = Number(r.good_units || 0), ng = Number(r.ng_units || 0), h = good * scale, nh = ng * scale;
          return `<rect x="${x}" y="${104-h}" width="22" height="${h}" fill="var(--accent-green)" rx="2"/><rect x="${x}" y="${104-h-nh}" width="22" height="${nh}" fill="var(--brand-orange)" rx="2"/><text x="${x+11}" y="${98-h-nh}" fill="var(--text-primary)" font-size="9" text-anchor="middle">${good+ng}</text><text x="${x+11}" y="115" fill="var(--text-secondary)" font-size="8" text-anchor="middle">${esc(r.hour_label)}</text>`;
      }).join(''); 
    }
    </script>
</body>
</html>
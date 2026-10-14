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
    <title>Defect Queue & Review - Masters Vineyard Enterprise</title>
    
    <!-- Shared Sidebar & Review Styles -->
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/defect_review.css">
</head>
<body>

    <!-- Shared Role-Filtered Sidebar -->
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <!-- Header Bar -->
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Defect Queue & Review</h1>
                <p class="subtitle">Defect reports sent by operators. Confirm them to count as rejected units, or send them back.</p>
            </div>
            <div class="time-area">
                <div><?= date('M d, Y · h:i A'); ?></div>
            </div>
        </header>

        <section class="grid-dqr-main">
            <!-- Left Queue Column -->
            <div class="card">
                <div class="card-header-bar header-blue">REPORT QUEUE</div>
                <div class="card-body">
                    <!-- Filter Pills Toolbar -->
                    <div class="filter-pills-row">
                        <button class="btn-filter-pill active" onclick="filterQueue('Pending', this)" id="pill-pending">Pending · 0</button>
                        <button class="btn-filter-pill" onclick="filterQueue('Confirmed', this)" id="pill-confirmed">Confirmed · 0</button>
                        <button class="btn-filter-pill" onclick="filterQueue('Rejected', this)" id="pill-rejected">Rejected · 0</button>
                        <button class="btn-filter-pill" onclick="filterQueue('All', this)" id="pill-all">All · 0</button>
                    </div>

                    <!-- Defect List Queue Container -->
                    <div id="queue-list-container">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>

            <!-- Right Details & Action Panel -->
            <div class="card">
                <div class="card-header-bar header-green">
                    <span>REVIEW REPORT</span>
                    <span id="detail-code-badge">—</span>
                </div>
                <div class="card-body">
                    <div id="review-panel-content">
                        <!-- Populated dynamically via JS -->
                    </div>
                </div>
            </div>
        </section>
    </main>

    <!-- Inline Logic -->
    <script>
        let allReports = [];
        let currentSelectedId = null;
        let currentFilter = 'Pending';

        document.addEventListener("DOMContentLoaded", () => {
            loadDefectQueue();
        });

        function loadDefectQueue() {
            fetch(BASE_URL + '/backend/process_defect_review.php?action=get_queue')
                .then(res => res.json())
                .then(data => {
                    if (data.status === 'success') {
                        allReports = data.reports;
                        
                        // Update pill counts
                        document.getElementById('pill-pending').innerText = `Pending · ${data.stats.pending}`;
                        document.getElementById('pill-confirmed').innerText = `Confirmed · ${data.stats.confirmed}`;
                        document.getElementById('pill-rejected').innerText = `Rejected · ${data.stats.rejected}`;
                        document.getElementById('pill-all').innerText = `All · ${data.stats.all}`;

                        renderQueue(currentFilter);
                    }
                })
                .catch(err => { const c = document.getElementById('queue-container') || document.querySelector('.main-content'); c.insertAdjacentHTML('afterbegin','<p role="alert" style="color:#b42318">Unable to load defect queue. Refresh the page or check the server connection.</p>'); console.error("Error loading defect queue:", err); });
        }

        function filterQueue(statusFilter, btnElement) {
            currentFilter = statusFilter;
            if (btnElement) {
                document.querySelectorAll('.btn-filter-pill').forEach(b => b.classList.remove('active'));
                btnElement.classList.add('active');
            }

            renderQueue(statusFilter);
        }

        function renderQueue(filter) {
            const container = document.getElementById('queue-list-container');
            container.innerHTML = '';

            let filtered = allReports;
            if (filter !== 'All') {
                filtered = allReports.filter(r => r.status === filter);
            }

            if (filtered.length === 0) {
                container.innerHTML = '<p style="color:var(--text-secondary); font-size:0.85rem; padding:15px 0;">No reports found in this queue.</p>';
                document.getElementById('review-panel-content').innerHTML = '<p style="color:var(--text-secondary); font-size:0.85rem;">Select a defect report from the queue to review.</p>';
                return;
            }

            filtered.forEach((r, idx) => {
                let isSelected = currentSelectedId ? r.id === currentSelectedId : idx === 0;
                if (isSelected && !currentSelectedId) currentSelectedId = r.id;

                container.innerHTML += `
                    <div class="queue-item ${isSelected ? 'selected' : ''}" onclick="selectReport(${esc(r.id)})">
                        <div>
                            <div class="queue-title">${esc(r.defect_category)} · ${esc(r.workstation)} <small>${esc(r.report_code)}</small></div>
                            <div class="queue-sub">${esc(r.reported_by)} · ${esc(r.station)} · ${esc(r.report_time)} · Qty ${esc(r.quantity_rejected)}</div>
                        </div>
                        <div class="badge-group">
                            <span class="pill-sev pill-${esc(r.severity)}">${esc(r.severity)}</span>
                            <span class="status-text-${esc(r.status)}">${esc(r.status)}</span>
                        </div>
                    </div>
                `;
            });

            // Auto-render selected report details
            const activeReport = allReports.find(r => r.id === currentSelectedId) || filtered[0];
            if (activeReport) {
                renderDetailsPanel(activeReport);
            }
        }

        function selectReport(id) {
            currentSelectedId = id;
            renderQueue(currentFilter);
        }

        function renderDetailsPanel(r) {
            document.getElementById('detail-code-badge').innerText = r.report_code;
            const container = document.getElementById('review-panel-content');

            let isPending = r.status === 'Pending';
            let photoHtml = r.photo_url 
                ? `<img src="${esc(r.photo_url)}" style="width:100%; border-radius:6px; margin-bottom:15px;" alt="Operator photo">`
                : `<div class="photo-preview-placeholder">📷 Photo from operator</div>`;

            container.innerHTML = `
                <div class="review-header-title">
                    <h2>${esc(r.defect_category)}</h2>
                    <span class="pill-sev pill-${esc(r.severity)}">${esc(r.severity)}</span>
                </div>

                <div class="meta-two-col">
                    <div>
                        <label>Station</label>
                        <strong>${esc(r.station)}</strong>
                    </div>
                    <div>
                        <label>Workstation</label>
                        <strong>${esc(r.workstation)}</strong>
                    </div>
                </div>

                <div class="meta-two-col">
                    <div>
                        <label>Reported by</label>
                        <strong>${esc(r.reported_by)}</strong>
                    </div>
                    <div>
                        <label>Time · Qty</label>
                        <strong>${esc(r.report_time)} · ${esc(r.quantity_rejected)} unit${r.quantity_rejected > 1 ? 's' : ''}</strong>
                    </div>
                </div>

                <div class="form-group-review">
                    <label>Operator notes</label>
                    <div class="notes-box">${esc(r.operator_notes || 'No notes provided.')}</div>
                </div>

                ${photoHtml}

                <div class="form-group-review">
                    <label>Supervisor comment (optional)</label>
                    <textarea id="supervisor_comment_input" class="textarea-comment" placeholder="Add comment for operator...">${esc(r.supervisor_comment || '')}</textarea>
                </div>

                ${isPending ? `
                    <button class="btn-confirm-green" onclick="submitReview(\${r.id}, 'Confirmed')">CONFIRM DEFECT</button>
                    <div class="btn-row-dual">
                        <button class="btn-recheck-gold" onclick="submitReview(\${r.id}, 'Recheck Requested')">Request recheck</button>
                        <button class="btn-reject-gray" onclick="submitReview(\${r.id}, 'Rejected')">Reject report</button>
                    </div>
                ` : `
                    <div style="background:#161b22; border:1px solid var(--card-border); padding:10px; border-radius:6px; text-align:center; margin-bottom:12px;">
                        Status: <strong class="status-text-${r.status}">${r.status}</strong>
                    </div>
                `}

                <div class="footer-notice">Confirmed defects are added to the NG count and to the shift yield report.</div>
            `;
        }

        function submitReview(reportId, decision) {
            let comment = document.getElementById('supervisor_comment_input').value;
            let formData = new FormData();
            formData.append('report_id', reportId);
            formData.append('decision', decision);
            formData.append('supervisor_comment', comment);

            fetch(BASE_URL + '/backend/process_defect_review.php?action=review_report', {
                method: 'POST',
                body: formData
            })
            .then(res => res.json())
            .then(data => {
                alert(data.message);
                if (data.status === 'success') {
                    loadDefectQueue();
                }
            })
            .catch(err => { alert('Review submission failed. Please check the connection and try again.'); console.error("Error submitting review:", err); });
        }
    </script>
</body>
</html>
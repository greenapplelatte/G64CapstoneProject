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
    <title>Access Requests - Masters Vineyard Enterprise</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/sidebar.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/shift_yield.css">
</head>
<body>
    <?php include_once __DIR__ . '/../../includes/sidebar.php'; ?>

    <main class="main-content">
        <header class="dashboard-header">
            <div class="title-area">
                <h1>Access Requests</h1>
                <p class="subtitle">Requests for new operator or supervisor accounts. A manager approves or declines them; this page shows where each one stands.</p>
            </div>
            <div class="time-area"><div><?= date('M d, Y · h:i A'); ?></div></div>
        </header>

        <div class="card">
            <div class="card-header-bar header-blue">
                <span>REQUESTS</span>
                <span id="pending-count">0 pending</span>
            </div>
            <div class="card-body">
                <label for="status-filter" style="font-size:0.8rem;">Show </label>
                <select id="status-filter" style="margin-bottom:12px;">
                    <option value="all">All</option>
                    <option value="Pending">Pending</option>
                    <option value="Approved">Approved</option>
                    <option value="Declined">Declined</option>
                </select>
                <div style="overflow-x:auto;">
                    <table style="width:100%; border-collapse:collapse; font-size:0.85rem;">
                        <thead>
                            <tr style="text-align:left; color:var(--text-secondary);">
                                <th style="padding:8px;">USERNAME</th>
                                <th style="padding:8px;">NAME</th>
                                <th style="padding:8px;">ROLE</th>
                                <th style="padding:8px;">LINE</th>
                                <th style="padding:8px;">REQUESTED</th>
                                <th style="padding:8px;">STATUS</th>
                            </tr>
                        </thead>
                        <tbody id="req-body"></tbody>
                    </table>
                </div>
            </div>
        </div>
    </main>

    <script>
        const statusColor = {Pending: '#b7791f', Approved: '#2f855a', Declined: '#b42318'};

        function showRequestError(message) { let el=document.getElementById('request-error'); if(!el){el=document.createElement('p');el.id='request-error';el.setAttribute('role','alert');el.style.color='#b42318';document.querySelector('.card-body').prepend(el);}el.textContent=message;}
        function clearRequestError(){const el=document.getElementById('request-error');if(el)el.remove();}
        function loadRequests() {
            const s = document.getElementById('status-filter').value;
            fetch(BASE_URL + '/backend/get_access_requests.php?status=' + encodeURIComponent(s))
                .then(res => res.json())
                .then(data => {
                    if (data.status !== 'success') { showRequestError(data.message || 'Unable to load access requests.'); return; }
                    clearRequestError();
                    document.getElementById('pending-count').innerText = data.pending + ' pending';
                    const body = document.getElementById('req-body');
                    if (!data.requests.length) {
                        body.innerHTML = '<tr><td colspan="6" style="padding:12px; text-align:center; color:var(--text-secondary);">No requests.</td></tr>';
                        return;
                    }
                    body.innerHTML = data.requests.map(r => `
                        <tr style="border-top:1px solid rgba(128,128,128,0.2);">
                            <td style="padding:8px;"><strong>${esc(r.username)}</strong></td>
                            <td style="padding:8px;">${esc(r.requested_by)}</td>
                            <td style="padding:8px;">${esc(r.requested_role)}</td>
                            <td style="padding:8px;">${esc(r.assignment_line)}</td>
                            <td style="padding:8px;">${esc(r.request_date)}</td>
                            <td style="padding:8px; font-weight:600; color:${statusColor[r.status] || 'inherit'};">${esc(r.status)}</td>
                        </tr>`).join('');
                })
                .catch(err => { showRequestError('Unable to load access requests. Check the server connection and try again.'); console.error('Error loading requests:', err); });
        }
        document.addEventListener('DOMContentLoaded', () => {
            loadRequests();
            document.getElementById('status-filter').addEventListener('change', loadRequests);
        });
    </script>
</body>
</html>
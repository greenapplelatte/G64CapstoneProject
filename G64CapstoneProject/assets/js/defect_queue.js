(function () {
    'use strict';

    var API = window.BASE_URL + '/backend/process_defect_review.php';
    var PAGE_SIZE = 6;
    var POLL_MS = 10000;

    var FILTERS = {
        review:    { test: function (r) { return r.status === 'Pending' || r.status === 'Recheck Requested'; }, empty: 'No reports are waiting for review.' },
        confirmed: { test: function (r) { return r.status === 'Confirmed'; }, empty: 'No confirmed reports yet.' },
        rejected:  { test: function (r) { return r.status === 'Rejected'; },  empty: 'No rejected reports yet.' },
        all:       { test: function () { return true; },                      empty: 'No defect reports have been submitted yet.' }
    };

    var reports = [];
    var filter = 'review';
    var page = 1;
    var loading = false;
    var busy = false;
    var drafts = {};   // review comments typed but not yet submitted, kept across refreshes

    function $(id) { return document.getElementById(id); }
    function h(value) {
        return String(value == null ? '' : value).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    function showMessage(message, type) {
        var box = $('queue-feedback');
        if (!box) {
            box = document.createElement('div');
            box.id = 'queue-feedback';
            box.setAttribute('role', 'status');
            var main = document.querySelector('.main-content');
            if (main) main.prepend(box);
        }
        box.textContent = message;
        box.className = 'queue-feedback ' + (type === 'success' ? 'is-success' : 'is-error');
    }

    function fmtTime(r) {
        var raw = r.created_at || r.report_time || '';
        if (!raw) return 'Not recorded';
        var text = String(raw);
        var date = new Date(/(?:Z|[+-]\d{2}:?\d{2})$/.test(text) ? text : text.replace(' ', 'T') + '+08:00');
        if (isNaN(date.getTime())) return text;
        return new Intl.DateTimeFormat('en-PH', {
            timeZone: 'Asia/Manila', month: 'short', day: '2-digit', year: 'numeric',
            hour: '2-digit', minute: '2-digit', hour12: true
        }).format(date);
    }

    function itemHtml(r) {
        var id = Number(r.id);
        var open = r.status === 'Pending' || r.status === 'Recheck Requested';
        var rechecked = r.status === 'Recheck Requested';
        var statusClass = { 'Pending': 'is-pending', 'Recheck Requested': 'is-recheck', 'Confirmed': 'is-confirmed', 'Rejected': 'is-rejected' }[r.status] || 'is-pending';
        var qty = Number(r.quantity_rejected || 0);

        var actions = '';
        if (open) {
            actions =
                '<div class="queue-actions">' +
                    '<label class="visually-hidden" for="comment-' + id + '">Review comment for report #' + id + '</label>' +
                    '<textarea id="comment-' + id + '" class="queue-comment" data-comment="' + id + '" placeholder="Comment for the operator (optional)">' + h(drafts[id] || '') + '</textarea>' +
                    '<div class="queue-buttons">' +
                        '<button type="button" class="btn-decision btn-confirm" data-id="' + id + '" data-decision="Confirmed">Confirm defect</button>' +
                        '<button type="button" class="btn-decision btn-reject" data-id="' + id + '" data-decision="Rejected">Reject report</button>' +
                        (rechecked ? '' : '<button type="button" class="btn-decision btn-recheck" data-id="' + id + '" data-decision="Recheck Requested">Request recheck</button>') +
                    '</div>' +
                '</div>';
        }

        return '<article class="queue-item" data-id="' + id + '">' +
            '<div class="queue-item-head">' +
                '<div>' +
                    '<div class="queue-item-title">' + h(r.defect_category) + ' · ' + h(r.workstation) + '</div>' +
                    '<div class="queue-item-sub">Report #' + id + '</div>' +
                '</div>' +
                '<div class="report-tags">' +
                    '<span class="pill-sev pill-' + h(r.severity) + '">' + h(r.severity) + '</span>' +
                    '<span class="queue-status ' + statusClass + '">' + h(r.status) + '</span>' +
                '</div>' +
            '</div>' +
            '<dl class="queue-facts">' +
                '<div><dt>Station</dt><dd>' + h(r.station || '—') + '</dd></div>' +
                '<div><dt>Project</dt><dd>' + h(r.project_type || '—') + '</dd></div>' +
                '<div><dt>Units</dt><dd>' + qty + '</dd></div>' +
                '<div><dt>Reported by</dt><dd>' + h(r.reported_by || '—') + '</dd></div>' +
                '<div><dt>Reported</dt><dd>' + h(fmtTime(r)) + '</dd></div>' +
            '</dl>' +
            (r.notes ? '<p class="queue-note"><strong>Operator notes:</strong> ' + h(r.notes) + '</p>' : '') +
            (r.supervisor_comment ? '<p class="queue-note"><strong>Review comment:</strong> ' + h(r.supervisor_comment) + '</p>' : '') +
            actions +
        '</article>';
    }

    function renderStats(stats) {
        if (!stats) return;
        $('stat-pending').textContent = stats.pending || 0;
        $('stat-recheck').textContent = stats.recheck_requested || 0;
        $('stat-confirmed').textContent = stats.confirmed || 0;
        $('stat-rejected').textContent = stats.rejected || 0;
    }

    function render() {
        var f = FILTERS[filter];
        var rows = reports.filter(f.test);
        var pages = Math.max(1, Math.ceil(rows.length / PAGE_SIZE));
        page = Math.min(Math.max(1, page), pages);

        Object.keys(FILTERS).forEach(function (key) {
            var n = reports.filter(FILTERS[key].test).length;
            var el = $('count-' + key);
            if (el) el.textContent = n;
        });
        document.querySelectorAll('.filter-pill').forEach(function (btn) {
            btn.classList.toggle('active', btn.dataset.filter === filter);
        });

        var list = $('queue-list');
        if (!rows.length) {
            list.innerHTML = '<p class="queue-empty">' + h(f.empty) + '</p>';
        } else {
            list.innerHTML = rows.slice((page - 1) * PAGE_SIZE, page * PAGE_SIZE).map(itemHtml).join('');
        }

        $('queue-pagination').style.display = rows.length > PAGE_SIZE ? 'flex' : 'none';
        $('queue-page-status').textContent = 'Page ' + page + ' of ' + pages + ' · ' + rows.length + ' reports';
        $('queue-prev').disabled = page <= 1;
        $('queue-next').disabled = page >= pages;
    }

    async function load(silent) {
        if (loading) return;
        loading = true;
        try {
            var res = await fetch(API + '?action=get_queue&_=' + Date.now(), { cache: 'no-store', headers: { 'Accept': 'application/json' } });
            var data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Could not load the defect queue.');
            reports = data.reports || [];
            renderStats(data.stats);

            // Do not redraw while the supervisor is typing a comment or a decision is being saved
            var active = document.activeElement;
            var typing = active && active.matches && active.matches('textarea.queue-comment');
            if (!(silent && (typing || busy))) render();

            $('queue-live').textContent = 'Updated ' + new Date().toLocaleTimeString('en-PH', { hour: '2-digit', minute: '2-digit', second: '2-digit' });
        } catch (err) {
            if (!silent) $('queue-list').innerHTML = '<p class="queue-empty">Unable to load the queue: ' + h(err.message) + '</p>';
            $('queue-live').textContent = 'Update failed · retrying';
            console.error(err);
        } finally {
            loading = false;
        }
    }

    var DECISIONS = {
        'Confirmed': {
            title: 'Confirm defect',
            text: function (r) { return 'Confirm this defect? Its ' + Number(r.quantity_rejected || 0) + ' unit(s) will be counted as rejected (NG) for ' + (r.station || 'this station') + '.'; }
        },
        'Rejected': {
            title: 'Reject report',
            text: function (r) { return 'Reject this report? Its ' + Number(r.quantity_rejected || 0) + ' pending unit(s) will be released and not counted as rejected.'; }
        },
        'Recheck Requested': {
            title: 'Request recheck',
            text: function (r) { return 'Request a recheck? The ' + Number(r.quantity_rejected || 0) + ' unit(s) stay pending until you confirm or reject the report.'; }
        }
    };

    async function review(id, decision) {
        var report = reports.find(function (r) { return Number(r.id) === id; });
        if (!report || !DECISIONS[decision]) return;

        var d = DECISIONS[decision];
        var message = d.text(report);
        var ok = typeof window.systemConfirm === 'function' ? await window.systemConfirm(message, d.title) : window.confirm(message);
        if (!ok) return;

        var card = document.querySelector('.queue-item[data-id="' + id + '"]');
        var buttons = card ? card.querySelectorAll('button') : [];
        buttons.forEach(function (b) { b.disabled = true; });
        busy = true;

        var body = new FormData();
        body.append('report_id', String(id));
        body.append('decision', decision);
        body.append('supervisor_comment', (drafts[id] || '').trim());

        try {
            var res = await fetch(API + '?action=review_report', { method: 'POST', body: body, headers: { 'Accept': 'application/json' } });
            var data = await res.json();
            if (!res.ok || data.status !== 'success') throw new Error(data.message || 'Unable to update the report.');
            delete drafts[id];
            showMessage(data.message, 'success');
        } catch (err) {
            showMessage(err.message || 'Network error. Please try again.', 'error');
        } finally {
            busy = false;
            await load();
        }
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.filter-pill').forEach(function (btn) {
            btn.addEventListener('click', function () {
                filter = btn.dataset.filter || 'review';
                page = 1;
                render();
            });
        });
        $('queue-prev').addEventListener('click', function () { page--; render(); });
        $('queue-next').addEventListener('click', function () { page++; render(); });

        var list = $('queue-list');
        list.addEventListener('click', function (event) {
            var btn = event.target.closest('.btn-decision');
            if (btn) review(Number(btn.dataset.id), btn.dataset.decision);
        });
        list.addEventListener('input', function (event) {
            var box = event.target.closest('textarea.queue-comment');
            if (box) drafts[box.dataset.comment] = box.value;
        });

        var clock = $('current-datetime');
        var tick = function () {
            if (clock) clock.textContent = new Date().toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: '2-digit', minute: '2-digit', hour12: true }).replace(',', ' ·');
        };
        tick();
        setInterval(tick, 30000);

        load();
        setInterval(function () { if (!document.hidden) load(true); }, POLL_MS);
    });
})();
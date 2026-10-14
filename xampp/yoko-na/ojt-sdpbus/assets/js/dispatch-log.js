document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('.dispatch-ticket-row'));
    const statusButtons = Array.from(document.querySelectorAll('[data-dispatch-status]'));
    const statusInput = document.getElementById('dispatch-status-input');
    const searchInput = document.getElementById('dispatch-search');
    const resultCount = document.getElementById('dispatch-result-count');
    const emptyRow = document.getElementById('dispatch-empty-row');
    const noResults = document.getElementById('dispatch-no-results');
    const detailPanel = document.getElementById('dispatch-detail-panel');
    const lifecycle = document.getElementById('ticket-lifecycle');
    const exportLink = document.getElementById('dispatch-export-csv');
    const statusNav = document.querySelector('.dispatch-status-filters');
    let activeStatus = statusNav?.dataset.initialStatus ?? 'All';
    let selectedRow = null;

    const setText = (id, value) => {
        const element = document.getElementById(id);
        if (element) element.textContent = value ?? '';
    };

    const formatDate = value => {
        const date = new Date(`${value}T00:00:00`);
        return Number.isNaN(date.getTime())
            ? value
            : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', year: 'numeric' }).format(date);
    };

    const addLifecycleStep = (title, subtitle, state, icon) => {
        const item = document.createElement('li');
        item.className = state;
        const iconElement = document.createElement('span');
        iconElement.className = 'lifecycle-icon';
        const iconNode = document.createElement('i');
        iconNode.className = `fas ${icon}`;
        iconNode.setAttribute('aria-hidden', 'true');
        iconElement.append(iconNode);

        const content = document.createElement('span');
        const titleElement = document.createElement('strong');
        titleElement.textContent = title;
        const subtitleElement = document.createElement('small');
        subtitleElement.textContent = subtitle;
        content.append(titleElement, subtitleElement);
        item.append(iconElement, content);
        lifecycle?.append(item);
    };

    const selectTicket = row => {
        if (!row || !detailPanel) return;
        selectedRow?.classList.remove('is-selected');
        selectedRow?.setAttribute('aria-pressed', 'false');
        selectedRow = row;
        row.classList.add('is-selected');
        row.setAttribute('aria-pressed', 'true');

        const data = row.dataset;
        setText('detail-ticket-number', data.ticketNumber);
        setText('detail-route-name', data.route);
        setText('detail-trip', `${formatDate(data.tripDate)} · ${data.tripTime} · ${data.shift}`);
        setText('detail-bus', data.bus);
        setText('detail-driver', data.driver);
        setText('detail-encoder', data.encoder);
        setText('detail-passengers', `${data.passengers} / ${data.forecast} forecast`);
        setText('detail-total', `₱${Number(data.total).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 })}`);
        setText('detail-trip-status', data.tripStatus);

        const statusBadge = document.getElementById('detail-status-badge');
        if (statusBadge) {
            statusBadge.textContent = data.reviewStatus;
            statusBadge.className = `dispatch-status-badge status-${data.reviewStatus.toLowerCase()}`;
        }

        const adminComment = document.getElementById('detail-admin-comment');
        if (adminComment) {
            adminComment.hidden = !data.adminComment;
            adminComment.textContent = data.adminComment ? `Admin note: ${data.adminComment}` : '';
        }

        const created = data.createdAt ? new Date(`${data.createdAt.replace(' ', 'T')}Z`) : null;
        const recordedAt = created && !Number.isNaN(created.getTime())
            ? new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric', hour: '2-digit', minute: '2-digit' }).format(created)
            : 'Recorded';
        const isSubmitted = ['Submitted', 'Approved', 'Returned'].includes(data.reviewStatus);
        const isApproved = data.reviewStatus === 'Approved';
        const isReturned = data.reviewStatus === 'Returned';
        const isClosed = data.billingStatus === 'Closed';

        lifecycle?.replaceChildren();
        addLifecycleStep('Recorded', `by ${data.encoder} · ${recordedAt}`, 'is-complete', 'fa-check');
        addLifecycleStep(isSubmitted ? 'Submitted' : 'Draft', isSubmitted ? 'Sent for review' : 'Not submitted for review', isSubmitted ? 'is-complete' : 'is-current', isSubmitted ? 'fa-check' : 'fa-file');
        addLifecycleStep(isApproved ? 'Approved' : (isReturned ? 'Returned' : 'Review pending'), isApproved ? 'Approved for billing' : (isReturned ? 'Returned for changes' : 'Awaiting review'), isApproved ? 'is-complete' : (isReturned || data.reviewStatus === 'Submitted' ? 'is-current' : 'is-pending'), isApproved ? 'fa-check' : (isReturned ? 'fa-rotate-left' : 'fa-clock'));
        addLifecycleStep(isClosed ? 'Billing closed' : (isApproved ? 'Billing open' : 'Billing pending'), isClosed ? 'Included in a closed billing period' : (isApproved ? 'Locks when its period is closed' : 'Available after approval'), isClosed ? 'is-complete' : (isApproved ? 'is-current' : 'is-pending'), isClosed ? 'fa-check' : 'fa-lock');
    };

    const updateRows = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();
        const matching = rows.filter(row => {
            const matchesStatus = activeStatus === 'All' || row.dataset.status === activeStatus;
            const matchesSearch = (row.dataset.search ?? '').includes(query);
            return matchesStatus && matchesSearch;
        });
        const visibleRows = new Set(matching);
        rows.forEach(row => { row.hidden = !visibleRows.has(row); });
        if (emptyRow) emptyRow.hidden = matching.length !== 0;
        if (noResults) noResults.hidden = matching.length !== 0;
        if (resultCount) resultCount.textContent = `Showing ${matching.length} of ${rows.length} tickets`;
        if (!visibleRows.has(selectedRow)) {
            if (matching[0]) selectTicket(matching[0]);
            else {
                selectedRow?.classList.remove('is-selected');
                selectedRow = null;
                if (detailPanel) detailPanel.hidden = true;
            }
        }
    };

    rows.forEach(row => {
        row.addEventListener('click', () => selectTicket(row));
        row.addEventListener('keydown', event => {
            if (event.key === 'Enter' || event.key === ' ') {
                event.preventDefault();
                selectTicket(row);
            }
        });
    });

    statusButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeStatus = button.dataset.dispatchStatus ?? 'All';
            if (statusInput) statusInput.value = activeStatus;
            statusButtons.forEach(statusButton => {
                const isActive = statusButton === button;
                statusButton.classList.toggle('is-active', isActive);
                statusButton.setAttribute('aria-pressed', String(isActive));
            });
            if (exportLink) {
                const exportUrl = new URL(exportLink.href);
                if (activeStatus === 'All') exportUrl.searchParams.delete('status');
                else exportUrl.searchParams.set('status', activeStatus);
                exportLink.href = exportUrl.toString();
            }
            updateRows();
        });
    });

    searchInput?.addEventListener('input', updateRows);
    document.getElementById('print-dispatch')?.addEventListener('click', () => window.print());

    if (rows.length) selectTicket(rows[0]);
    updateRows();
});
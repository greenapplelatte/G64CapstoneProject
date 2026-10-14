document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('.master-row'));
    const filters = Array.from(document.querySelectorAll('.master-filter'));
    const searchInput = document.getElementById('master-search');
    const emptyRow = document.getElementById('master-empty-row');
    const footer = document.getElementById('master-table-footer');
    const pageSummary = document.getElementById('master-page-summary');
    const previousButton = document.getElementById('master-previous');
    const nextButton = document.getElementById('master-next');
    const masterLayout = document.querySelector('.master-data-layout');
    const itemLabel = masterLayout?.dataset.itemsLabel ?? 'items';
    const pageSize = 6;
    let activeStatus = 'all';
    let currentPage = 1;

    const updateRows = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();
        const filteredRows = rows.filter(row => {
            const statusMatches = activeStatus === 'all' || row.dataset.status === activeStatus;
            const searchMatches = (row.dataset.search ?? '').includes(query);
            return statusMatches && searchMatches;
        });
        const pageCount = Math.max(1, Math.ceil(filteredRows.length / pageSize));
        currentPage = Math.min(currentPage, pageCount);
        const pageStart = (currentPage - 1) * pageSize;
        const pageRows = new Set(filteredRows.slice(pageStart, pageStart + pageSize));

        rows.forEach(row => {
            row.hidden = !pageRows.has(row);
        });

        if (emptyRow) emptyRow.hidden = filteredRows.length !== 0;
        if (footer) {
            const firstShown = filteredRows.length === 0 ? 0 : pageStart + 1;
            const lastShown = Math.min(pageStart + pageSize, filteredRows.length);
            footer.textContent = `Showing ${firstShown}-${lastShown} of ${filteredRows.length} ${itemLabel}`;
        }
        if (pageSummary) pageSummary.textContent = `Page ${currentPage} of ${pageCount}`;
        if (previousButton) previousButton.disabled = currentPage <= 1;
        if (nextButton) nextButton.disabled = currentPage >= pageCount;
    };

    filters.forEach(filter => {
        filter.addEventListener('click', () => {
            activeStatus = filter.dataset.statusFilter ?? 'all';
            currentPage = 1;
            filters.forEach(button => {
                const isActive = button === filter;
                button.classList.toggle('is-active', isActive);
                button.setAttribute('aria-pressed', String(isActive));
            });
            updateRows();
        });
    });

    searchInput?.addEventListener('input', () => {
        currentPage = 1;
        updateRows();
    });

    previousButton?.addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage -= 1;
            updateRows();
        }
    });

    nextButton?.addEventListener('click', () => {
        currentPage += 1;
        updateRows();
    });

    document.querySelectorAll('[data-master-notification]').forEach(notification => {
        const dismiss = () => {
            notification.classList.add('is-dismissing');
            window.setTimeout(() => notification.remove(), 180);
        };
        notification.querySelector('.notification-dismiss')?.addEventListener('click', dismiss);
        window.setTimeout(dismiss, 6500);
    });

    const rateInput = document.getElementById('route-rate');
    const tollInput = document.getElementById('route-toll');
    const formatPeso = value => `₱${Number.isFinite(value) ? value.toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 }) : '0.00'}`;
    const updateFeePreview = () => {
        const rate = Number.parseFloat(rateInput?.value ?? '0') || 0;
        const toll = Number.parseFloat(tollInput?.value ?? '0') || 0;
        const ratePreview = document.getElementById('preview-rate');
        const tollPreview = document.getElementById('preview-toll');
        const totalPreview = document.getElementById('preview-total');
        if (ratePreview) ratePreview.textContent = formatPeso(rate);
        if (tollPreview) tollPreview.textContent = formatPeso(toll);
        if (totalPreview) totalPreview.textContent = formatPeso(rate + toll);
    };

    rateInput?.addEventListener('input', updateFeePreview);
    tollInput?.addEventListener('input', updateFeePreview);
    updateFeePreview();

    document.getElementById('focus-master-form')?.addEventListener('click', () => {
        const firstField = document.querySelector('.master-form input:not([type="hidden"]), .master-form select');
        firstField?.focus();
        firstField?.scrollIntoView({ behavior: 'smooth', block: 'center' });
    });

    const deleteDialog = document.getElementById('master-delete-dialog');
    const deleteTarget = document.getElementById('master-delete-target');
    const cancelDeleteButton = document.getElementById('cancel-master-delete');
    const confirmDeleteButton = document.getElementById('confirm-master-delete');
    let pendingDeleteForm = null;
    let pendingDeleteTrigger = null;
    let deleteConfirmed = false;

    document.querySelectorAll('.delete-assignment-form').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!(deleteDialog instanceof HTMLDialogElement)) return;
            pendingDeleteForm = form;
            pendingDeleteTrigger = form.querySelector('button[type="submit"]');
            if (deleteTarget) deleteTarget.textContent = form.dataset.assignmentLabel ?? '';
            deleteDialog.showModal();
        });
    });

    cancelDeleteButton?.addEventListener('click', () => deleteDialog?.close());
    confirmDeleteButton?.addEventListener('click', () => {
        if (!pendingDeleteForm) return;
        const formToSubmit = pendingDeleteForm;
        deleteConfirmed = true;
        deleteDialog?.close();
        HTMLFormElement.prototype.submit.call(formToSubmit);
    });

    deleteDialog?.addEventListener('close', () => {
        if (!deleteConfirmed) pendingDeleteTrigger?.focus();
        pendingDeleteForm = null;
        pendingDeleteTrigger = null;
        deleteConfirmed = false;
    });

    updateRows();
});
document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('print-billing-report')?.addEventListener('click', () => window.print());

    const printUrl = new URL(window.location.href);
    if (printUrl.searchParams.get('print') === '1') {
        printUrl.searchParams.delete('print');
        window.history.replaceState({}, '', printUrl);
        window.setTimeout(() => window.print(), 450);
    }

    const dateForm = document.getElementById('billing-date-form');
    const startDateInput = document.getElementById('start_date');
    const endDateInput = document.getElementById('end_date');
    const outstandingStart = dateForm?.dataset.outstandingStart;
    const outstandingEnd = dateForm?.dataset.outstandingEnd;
    const presets = Array.from(document.querySelectorAll('[data-date-range]'));
    const confirmCheckbox = document.getElementById('confirm-lock');
    const closeButton = document.getElementById('billing-close-button');
    const closeForm = document.getElementById('billing-close-form');
    const expectedTicketCount = Number(closeForm?.querySelector('[name="expected_ticket_count"]')?.value ?? 0);

    const toDateValue = date => {
        const year = date.getFullYear();
        const month = String(date.getMonth() + 1).padStart(2, '0');
        const day = String(date.getDate()).padStart(2, '0');
        return `${year}-${month}-${day}`;
    };

    const setPresetDates = preset => {
        if (!startDateInput || !endDateInput) return;
        endDateInput.setCustomValidity('');
        if (preset === 'outstanding') {
            startDateInput.value = outstandingStart ?? '';
            endDateInput.value = outstandingEnd ?? '';
            presets.forEach(button => button.classList.toggle('is-active', button.dataset.dateRange === preset));
            dateForm?.requestSubmit();
            return;
        }
        const today = new Date();
        today.setHours(0, 0, 0, 0);
        let start = new Date(today);
        let end = new Date(today);

        if (preset === 'this-week') {
            const day = (today.getDay() + 6) % 7;
            start.setDate(today.getDate() - day);
        } else if (preset === 'last-week') {
            const day = (today.getDay() + 6) % 7;
            start.setDate(today.getDate() - day - 7);
            end = new Date(start);
            end.setDate(start.getDate() + 6);
        } else if (preset === 'this-month') {
            start = new Date(today.getFullYear(), today.getMonth(), 1);
        } else {
            return;
        }

        startDateInput.value = toDateValue(start);
        endDateInput.value = toDateValue(end);
        presets.forEach(button => button.classList.toggle('is-active', button.dataset.dateRange === preset));
        dateForm?.requestSubmit();
    };

    presets.forEach(button => {
        button.addEventListener('click', () => setPresetDates(button.dataset.dateRange ?? 'custom'));
    });

    [startDateInput, endDateInput].forEach(input => {
        input?.addEventListener('input', () => {
            endDateInput?.setCustomValidity('');
        });
        input?.addEventListener('change', () => {
            presets.forEach(button => button.classList.toggle('is-active', button.dataset.dateRange === 'custom'));
        });
    });

    dateForm?.addEventListener('submit', event => {
        if (startDateInput && endDateInput && startDateInput.value && endDateInput.value
            && startDateInput.value > endDateInput.value) {
            event.preventDefault();
            endDateInput.setCustomValidity('End date must be on or after the start date.');
            endDateInput.reportValidity();
        } else {
            endDateInput?.setCustomValidity('');
        }
    });

    const updateCloseButton = () => {
        if (closeButton) closeButton.disabled = !confirmCheckbox?.checked || expectedTicketCount === 0;
    };

    confirmCheckbox?.addEventListener('change', updateCloseButton);
    closeForm?.addEventListener('submit', event => {
        if (expectedTicketCount === 0 || !confirmCheckbox?.checked) {
            event.preventDefault();
            updateCloseButton();
        }
    });
    updateCloseButton();
});
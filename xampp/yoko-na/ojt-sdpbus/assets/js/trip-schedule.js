document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('routePickerModal');
    const searchInput = document.querySelector('.route-picker-search');
    const routeOptions = Array.from(document.querySelectorAll('.route-option'));
    const countEl = document.querySelector('.route-picker-count');
    const applyButton = document.getElementById('applyRouteSelection');
    const cancelButton = document.getElementById('cancelRouteSelection');
    const openButtons = document.querySelectorAll('[data-open-route-modal]');

    const updateSelectionCount = () => {
        const selected = routeOptions.filter((option) => option.querySelector('input').checked).length;
        if (countEl) {
            countEl.textContent = selected === 1 ? '1 route selected' : `${selected} routes selected`;
        }
    };

    if (searchInput) {
        searchInput.addEventListener('input', (event) => {
            const term = event.target.value.trim().toLowerCase();

            routeOptions.forEach((option) => {
                const label = option.dataset.label || option.textContent.toLowerCase();
                const match = !term || label.includes(term);
                option.style.display = match ? 'flex' : 'none';
            });
        });
    }

    routeOptions.forEach((option) => {
        const checkbox = option.querySelector('input');
        checkbox.addEventListener('change', updateSelectionCount);
    });

    openButtons.forEach((button) => {
        button.addEventListener('click', () => {
            const shift = button.dataset.shift || 'Route';
            const driver = button.dataset.driver || 'driver';
            const title = document.getElementById('routePickerTitle');
            if (title) {
                title.textContent = `Add routes: ${driver}, ${shift}`;
            }

            if (modal) {
                modal.classList.add('open');
            }
        });
    });

    const closeModal = () => {
        if (modal) {
            modal.classList.remove('open');
        }
    };

    if (cancelButton) {
        cancelButton.addEventListener('click', closeModal);
    }

    if (modal) {
        modal.addEventListener('click', (event) => {
            if (event.target === modal) {
                closeModal();
            }
        });
    }

    if (applyButton) {
        applyButton.addEventListener('click', () => {
            closeModal();
        });
    }

    updateSelectionCount();
});

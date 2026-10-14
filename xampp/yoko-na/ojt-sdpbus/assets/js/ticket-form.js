document.addEventListener('DOMContentLoaded', () => {
    const routeSelect = document.getElementById('route_id');
    const rateInput = document.getElementById('rate');
    const tollInput = document.getElementById('toll');
    const totalInput = document.getElementById('ticket_total');
    
    const forecastInput = document.getElementById('forecast_passengers');
    const actualInput = document.getElementById('actual_passengers');
    const varianceWarning = document.getElementById('variance_warning');
    const busSelect = document.getElementById('bus_id');
    const driverSelect = document.getElementById('driver_id');
    const assignmentHelp = document.getElementById('assignment-help');
    let driverAssignments = [];

    try {
        driverAssignments = JSON.parse(document.querySelector('main')?.dataset.driverAssignments ?? '[]');
    } catch {
        driverAssignments = [];
    }

    const updateAssignmentOptions = () => {
        if (!routeSelect || !busSelect || !driverSelect || routeSelect.disabled) return;
        const routeId = routeSelect.value;
        const busId = busSelect.value;
        let availableBusCount = 0;
        let availableDriverCount = 0;

        Array.from(busSelect.options).forEach(option => {
            const isAvailable = !option.value || driverAssignments.some(assignment =>
                String(assignment.route_id) === routeId && String(assignment.bus_id) === option.value
            );
            option.hidden = !isAvailable;
            if (isAvailable && option.value) availableBusCount++;
        });

        Array.from(driverSelect.options).forEach(option => {
            const isAvailable = !option.value || driverAssignments.some(assignment =>
                String(assignment.route_id) === routeId
                && String(assignment.bus_id) === busId
                && String(assignment.driver_id) === option.value
            );
            option.hidden = !isAvailable;
            if (isAvailable && option.value) availableDriverCount++;
        });

        if (busSelect.selectedOptions[0]?.hidden) busSelect.value = '';
        if (driverSelect.selectedOptions[0]?.hidden) driverSelect.value = '';
        if (assignmentHelp) {
            assignmentHelp.textContent = routeId && busId && availableDriverCount === 0
                ? 'No active driver assignment exists for this route and bus.'
                : (routeId && availableBusCount === 0
                    ? 'No active bus assignments exist for this route. Ask an admin to configure one.'
                    : 'Only configured driver, bus, and route combinations are available.');
        }
    };

    routeSelect?.addEventListener('change', updateAssignmentOptions);
    busSelect?.addEventListener('change', updateAssignmentOptions);

    // Auto-populate rate and toll when a route is chosen from Master Data
    if (routeSelect) {
        routeSelect.addEventListener('change', (e) => {
            const selectedOption = e.target.options[e.target.selectedIndex];
            if (selectedOption && selectedOption.dataset.rate) {
                const rate = parseFloat(selectedOption.dataset.rate) || 0;
                const toll = parseFloat(selectedOption.dataset.toll) || 0;

                if (rateInput) rateInput.value = rate.toFixed(2);
                if (tollInput) tollInput.value = toll.toFixed(2);

                calculateTicketTotal();
            }
        });
    }

    updateAssignmentOptions();

    // Dynamic total ticket cost calculation (Rate + Toll)
    function calculateTicketTotal() {
        const rate = parseFloat(rateInput?.value) || 0;
        const toll = parseFloat(tollInput?.value) || 0;
        if (totalInput) {
            totalInput.value = (rate + toll).toFixed(2);
        }
    }

    // Passenger variance warning logic
    if (actualInput && forecastInput) {
        actualInput.addEventListener('input', () => {
            const forecast = parseInt(forecastInput.value, 10) || 0;
            const actual = parseInt(actualInput.value, 10) || 0;

            if (forecast > 0 && actual < forecast) {
                const diff = forecast - actual;
                if (varianceWarning) {
                    varianceWarning.style.display = 'block';
                    varianceWarning.textContent = `⚠️ Warning: Actual passenger count is ${diff} below forecast.`;
                }
            } else {
                if (varianceWarning) {
                    varianceWarning.style.display = 'none';
                }
            }
        });
    }
});
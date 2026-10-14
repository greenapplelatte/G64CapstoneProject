document.addEventListener('DOMContentLoaded', () => {
    const routeSelect = document.getElementById('route_id');
    const rateInput = document.getElementById('rate');
    const tollInput = document.getElementById('toll');
    const totalInput = document.getElementById('ticket_total');
    
    const forecastInput = document.getElementById('forecast_passengers');
    const actualInput = document.getElementById('actual_passengers');
    const varianceWarning = document.getElementById('variance_warning');

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
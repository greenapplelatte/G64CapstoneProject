document.addEventListener('DOMContentLoaded', () => {
    const ctx = document.getElementById('passengerTrendChart');

    if (ctx) {
        new Chart(ctx, {
            type: 'bar',
            data: {
                labels: ['1', '2', '3', '4', '5', '6', '7', '8', '9', '10', '11', '12', '13', '14', '15'],
                datasets: [{
                    data: [23, 28, 31, 38, 29, 36, 27, 41, 33, 46, 39, 52, 35, 43, 38],
                    backgroundColor: (context) => {
                        return context.dataIndex === 11 ? '#070919' : '#a5b4fc';
                    },
                    borderRadius: 4,
                    barThickness: 14
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false }
                },
                scales: {
                    x: {
                        grid: { display: false },
                        ticks: { color: '#9ca3af', font: { size: 11 } }
                    },
                    y: {
                        grid: { color: '#f3f4f6' },
                        ticks: { color: '#9ca3af', font: { size: 11 } }
                    }
                }
            }
        });
    }
});
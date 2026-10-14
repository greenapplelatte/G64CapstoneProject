// --- TRIP DISPATCH LOG EVENT LISTENERS ---
document.addEventListener('DOMContentLoaded', function () {
    const btnAddTicket = document.getElementById('openAddModalBtn');
    if (btnAddTicket) {
        btnAddTicket.addEventListener('click', function () {
            alert('Opening trip ticket creation modal...');
        });
    }

    const deleteBtns = document.querySelectorAll('.link-delete');
    deleteBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            if (confirm('Are you sure you want to delete this trip ticket?')) {
                const row = this.closest('tr');
                if (row) {
                    row.style.transition = 'opacity 0.3s ease';
                    row.style.opacity = '0';
                    setTimeout(() => row.remove(), 300);
                }
            }
        });
    });
});
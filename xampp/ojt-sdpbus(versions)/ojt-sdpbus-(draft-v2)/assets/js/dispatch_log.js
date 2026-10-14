// --- TRIP DISPATCH LOG EVENT LISTENERS ---
document.addEventListener('DOMContentLoaded', function () {

    // --- ADD MODAL HANDLERS ---
    const addModal = document.getElementById('addTicketModal');
    const btnOpenAdd = document.getElementById('openAddModalBtn');
    const btnCloseAdd = document.getElementById('closeModalBtn');
    const btnCancelAdd = document.getElementById('cancelModalBtn');

    if (btnOpenAdd && addModal) {
        btnOpenAdd.addEventListener('click', function () {
            // Auto-set trip date input to current local date (YYYY-MM-DD) if empty or opening modal
            const dateInput = document.getElementById('add_trip_date');
            if (dateInput && !dateInput.value) {
                const today = new Date().toISOString().split('T')[0];
                dateInput.value = today;
            }

            addModal.style.display = 'flex';
        });
    }

    const closeAddModal = () => {
        if (addModal) addModal.style.display = 'none';
    };

    if (btnCloseAdd) btnCloseAdd.addEventListener('click', closeAddModal);
    if (btnCancelAdd) btnCancelAdd.addEventListener('click', closeAddModal);


    // --- EDIT MODAL HANDLERS ---
    const editModal = document.getElementById('editTicketModal');
    const btnCloseEdit = document.getElementById('closeEditModalBtn');
    const btnCancelEdit = document.getElementById('cancelEditModalBtn');

    // Attach click listeners to all Edit links
    const editBtns = document.querySelectorAll('.link-edit');
    editBtns.forEach(btn => {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const ds = this.dataset;

            // Populate Edit Form fields
            const editId = document.getElementById('edit_id');
            const editTicket = document.getElementById('edit_ticket_number');
            const editDate = document.getElementById('edit_trip_date');
            const editTime = document.getElementById('edit_trip_time');
            const editRoute = document.getElementById('edit_route');
            const editShift = document.getElementById('edit_shift');
            const editBus = document.getElementById('edit_bus_number');
            const editDriver = document.getElementById('edit_driver_name');
            const editActual = document.getElementById('edit_actual_passengers');
            const editForecast = document.getElementById('edit_forecast_passengers');
            const editStatus = document.getElementById('edit_status');
            const editRate = document.getElementById('edit_rate');

            if (editId) editId.value = ds.id || '';
            if (editTicket) editTicket.value = ds.ticket || '';
            if (editDate) editDate.value = ds.date || '';
            if (editTime) editTime.value = ds.time || '';
            if (editRoute) editRoute.value = ds.route || '';
            if (editShift) editShift.value = ds.shift || '';
            if (editBus) editBus.value = ds.bus || '';
            if (editDriver) editDriver.value = ds.driver || '';
            if (editActual) editActual.value = ds.actual || 0;
            if (editForecast) editForecast.value = ds.forecast || 0;
            if (editStatus) editStatus.value = ds.status || 'On time';
            if (editRate) editRate.value = ds.rate || 0;

            if (editModal) editModal.style.display = 'flex';
        });
    });

    const closeEditModal = () => {
        if (editModal) editModal.style.display = 'none';
    };

    if (btnCloseEdit) btnCloseEdit.addEventListener('click', closeEditModal);
    if (btnCancelEdit) btnCancelEdit.addEventListener('click', closeEditModal);


    // --- CLOSE MODALS ON BACKDROP CLICK ---
    window.addEventListener('click', function (e) {
        if (e.target === addModal) closeAddModal();
        if (e.target === editModal) closeEditModal();
    });
});
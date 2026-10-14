document.addEventListener('DOMContentLoaded', () => {
    const scheduleTable = document.querySelector('.schedule-table');
    let driverAssignments = [];

    try {
        driverAssignments = JSON.parse(scheduleTable?.dataset.driverAssignments ?? '[]');
    } catch {
        driverAssignments = [];
    }

    const hasActiveCombination = (routeId, busId, driverId) => driverAssignments.some(assignment =>
        String(assignment.route_id) === String(routeId)
        && String(assignment.bus_id) === String(busId)
        && String(assignment.driver_id) === String(driverId)
        && assignment.status === 'ACTIVE'
    );

    const hasSavedCombination = (row, busId, driverId) =>
        String(row.dataset.initialBus) === String(busId)
        && String(row.dataset.initialDriver) === String(driverId);

    const updateAssignmentOptions = () => {
        document.querySelectorAll('.schedule-row').forEach(row => {
            const routeId = row.dataset.routeId;
            const busSelect = row.querySelector('.select-bus');
            const driverSelect = row.querySelector('.select-driver');
            if (!busSelect || !driverSelect) return;

            const selectedBus = busSelect.value;
            Array.from(busSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                const hasRouteAssignment = driverAssignments.some(assignment =>
                    String(assignment.route_id) === String(routeId)
                    && String(assignment.bus_id) === option.value
                    && assignment.status === 'ACTIVE'
                );
                const isSavedBus = option.value === row.dataset.initialBus
                    && Number(row.dataset.initialDriver) > 0;
                option.hidden = !hasRouteAssignment && !isSavedBus;
            });

            Array.from(driverSelect.options).forEach(option => {
                if (!option.value) {
                    option.hidden = false;
                    return;
                }
                const hasBusAssignment = selectedBus && driverAssignments.some(assignment =>
                    String(assignment.route_id) === String(routeId)
                    && String(assignment.bus_id) === String(selectedBus)
                    && String(assignment.driver_id) === option.value
                    && assignment.status === 'ACTIVE'
                );
                const isSavedDriver = selectedBus && option.value === row.dataset.initialDriver
                    && hasSavedCombination(row, selectedBus, option.value);
                option.hidden = !hasBusAssignment && !isSavedDriver;
            });

            const selectedDriverOption = driverSelect.options[driverSelect.selectedIndex];
            if (selectedDriverOption?.hidden) driverSelect.value = '';
        });
    };

    if (scheduleTable) {
        scheduleTable.addEventListener('change', (e) => {
            if (e.target.classList.contains('select-bus') || e.target.classList.contains('select-driver')) {
                updateAssignmentOptions();
                validateScheduleConflicts();
            }
        });
    }

    function validateScheduleConflicts() {
        const busSelections = new Map();
        const driverSelections = new Map();
        let conflictFound = false;

        const rows = document.querySelectorAll('.schedule-row');

        rows.forEach((row, index) => {
            const shift = row.querySelector('.select-shift')?.value;
            const bus = row.querySelector('.select-bus')?.value;
            const driver = row.querySelector('.select-driver')?.value;
            const warningBox = row.querySelector('.conflict-alert');

            if (warningBox) warningBox.style.display = 'none';

            if (Boolean(bus) !== Boolean(driver)) {
                conflictFound = true;
                showConflict(row, warningBox, 'Assign both a bus and driver, or leave both unassigned');
            } else if (bus && driver && !hasActiveCombination(row.dataset.routeId, bus, driver)
                && !hasSavedCombination(row, bus, driver)) {
                conflictFound = true;
                showConflict(row, warningBox, 'This driver, bus, and route combination is not active');
            }

            if (shift && bus) {
                const busKey = `${shift}_${bus}`;
                if (busSelections.has(busKey)) {
                    conflictFound = true;
                    showConflict(row, warningBox, `Bus is already scheduled for ${shift}`);
                } else {
                    busSelections.set(busKey, index);
                }
            }

            if (shift && driver) {
                const driverKey = `${shift}_${driver}`;
                if (driverSelections.has(driverKey)) {
                    conflictFound = true;
                    showConflict(row, warningBox, `Driver is already assigned for ${shift}`);
                } else {
                    driverSelections.set(driverKey, index);
                }
            }
        });

        const saveBtn = document.getElementById('saveScheduleBtn');
        if (saveBtn) {
            saveBtn.disabled = conflictFound;
            saveBtn.style.opacity = conflictFound ? '0.5' : '1';
        }
    }

    function showConflict(row, warningBox, message) {
        if (warningBox) {
            warningBox.textContent = `⚠️ ${message}`;
            warningBox.style.display = 'block';
        }
    }

    updateAssignmentOptions();
    validateScheduleConflicts();
});
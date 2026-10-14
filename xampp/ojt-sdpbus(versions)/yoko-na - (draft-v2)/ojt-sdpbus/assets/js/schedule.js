document.addEventListener('DOMContentLoaded', () => {
    const scheduleTable = document.querySelector('.schedule-table');

    if (scheduleTable) {
        scheduleTable.addEventListener('change', (e) => {
            if (e.target.classList.contains('select-bus') || e.target.classList.contains('select-driver')) {
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
});
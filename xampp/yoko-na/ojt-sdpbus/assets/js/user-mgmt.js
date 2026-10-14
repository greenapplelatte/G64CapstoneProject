document.addEventListener('DOMContentLoaded', () => {
    const rows = Array.from(document.querySelectorAll('.account-row'));
    const filterButtons = Array.from(document.querySelectorAll('.user-filter'));
    const searchInput = document.getElementById('user-search');
    const emptyMessage = document.getElementById('empty-users');
    const footer = document.getElementById('accounts-footer');
    let activeFilter = 'all';

    const updateAccounts = () => {
        const query = (searchInput?.value ?? '').trim().toLowerCase();
        let visibleCount = 0;

        rows.forEach(row => {
            const matchesFilter = activeFilter === 'all'
                || row.dataset.role === activeFilter
                || (activeFilter === 'password' && row.dataset.password === 'change');
            const matchesSearch = (row.dataset.search ?? '').includes(query);
            const isVisible = matchesFilter && matchesSearch;
            row.hidden = !isVisible;
            if (isVisible) visibleCount += 1;
        });

        if (emptyMessage) emptyMessage.hidden = visibleCount !== 0;
        if (footer) footer.textContent = `Showing ${visibleCount} of ${rows.length} accounts`;
    };

    filterButtons.forEach(button => {
        button.addEventListener('click', () => {
            activeFilter = button.dataset.filter ?? 'all';
            filterButtons.forEach(filterButton => {
                const isActive = filterButton === button;
                filterButton.classList.toggle('is-active', isActive);
                filterButton.setAttribute('aria-pressed', String(isActive));
            });
            updateAccounts();
        });
    });

    searchInput?.addEventListener('input', updateAccounts);

    const renderLastLogin = (cell, value) => {
        cell.dataset.lastLogin = value ?? '';
        cell.replaceChildren();

        if (!value) {
            const neverLabel = document.createElement('span');
            neverLabel.className = 'never-login';
            neverLabel.textContent = 'Never';
            cell.append(neverLabel);
            return;
        }

        const loginDate = new Date(`${value.replace(' ', 'T')}Z`);
        if (Number.isNaN(loginDate.getTime())) return;

        const timeLabel = document.createElement('strong');
        timeLabel.textContent = new Intl.DateTimeFormat(undefined, {
            hour: '2-digit',
            minute: '2-digit',
            hourCycle: 'h23'
        }).format(loginDate);

        const today = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric'
        }).format(new Date());
        const loginDay = new Intl.DateTimeFormat(undefined, {
            year: 'numeric',
            month: 'numeric',
            day: 'numeric'
        }).format(loginDate);
        const dateLabel = document.createElement('small');
        dateLabel.textContent = loginDay === today
            ? 'Today'
            : new Intl.DateTimeFormat(undefined, { month: 'short', day: 'numeric' }).format(loginDate);

        cell.append(timeLabel, dateLabel);
    };

    const refreshLastLogins = async () => {
        if (document.hidden) return;
        try {
            const response = await fetch('/ojt-sdpbus/backend/user/last_logins.php', {
                cache: 'no-store',
                credentials: 'same-origin'
            });
            if (!response.ok) return;
            const data = await response.json();
            data.users?.forEach(user => {
                const row = rows.find(accountRow => accountRow.dataset.userId === String(user.id));
                const cell = row?.querySelector('.last-login');
                if (cell) renderLastLogin(cell, user.last_login);
            });
        } catch {
            // Keep the last rendered login values when a refresh is unavailable.
        }
    };

    refreshLastLogins();
    window.setInterval(refreshLastLogins, 15000);
    document.addEventListener('visibilitychange', () => {
        if (!document.hidden) refreshLastLogins();
    });

    const passwordInput = document.getElementById('password');
    const makeGeneratedPassword = () => {
        if (!window.crypto?.getRandomValues) return '';
        const alphabet = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz23456789!@#$%&*?';
        const randomValues = new Uint32Array(18);
        window.crypto.getRandomValues(randomValues);
        return Array.from(randomValues, value => alphabet[value % alphabet.length]).join('');
    };

    const visibilityButton = document.getElementById('toggle-password-visibility');
    visibilityButton?.addEventListener('click', () => {
        if (!passwordInput) return;
        const isVisible = passwordInput.type === 'text';
        const nextLabel = isVisible ? 'Show password' : 'Hide password';
        passwordInput.type = isVisible ? 'password' : 'text';
        visibilityButton.setAttribute('aria-label', nextLabel);
        visibilityButton.setAttribute('title', nextLabel);
        visibilityButton.setAttribute('aria-pressed', String(!isVisible));
        const icon = visibilityButton.querySelector('i');
        icon?.classList.toggle('fa-eye', !isVisible);
        icon?.classList.toggle('fa-eye-slash', isVisible);
    });

    document.getElementById('generate-password')?.addEventListener('click', () => {
        if (!passwordInput) return;
        passwordInput.value = makeGeneratedPassword();
        passwordInput.dispatchEvent(new Event('input', { bubbles: true }));
    });

    const resetDialog = document.getElementById('reset-password-dialog');
    const resetUsername = document.getElementById('reset-password-username');
    const generatedResetPassword = document.getElementById('generated-reset-password');
    const customResetPassword = document.getElementById('custom-reset-password');
    const generatedPanel = document.getElementById('reset-generated-panel');
    const customPanel = document.getElementById('reset-custom-panel');
    const resetFeedback = document.getElementById('reset-password-feedback');
    const confirmResetButton = document.getElementById('confirm-reset-password');
    const cancelResetButton = document.getElementById('cancel-reset-password');
    let pendingResetForm = null;
    let pendingResetTrigger = null;
    let resetConfirmed = false;

    const setGeneratedResetPassword = () => {
        if (generatedResetPassword) generatedResetPassword.value = makeGeneratedPassword();
    };

    document.querySelectorAll('.reset-password-form').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!(resetDialog instanceof HTMLDialogElement)) return;
            pendingResetForm = form;
            pendingResetTrigger = form.querySelector('button[type="submit"]');
            if (resetUsername) resetUsername.textContent = form.dataset.username ?? 'this user';
            if (generatedResetPassword) generatedResetPassword.value = '';
            if (customResetPassword) customResetPassword.value = '';
            if (resetFeedback) resetFeedback.textContent = '';
            const generateChoice = document.querySelector('input[name="reset-password-mode-choice"][value="generate"]');
            if (generateChoice) generateChoice.checked = true;
            if (generatedPanel) generatedPanel.hidden = false;
            if (customPanel) customPanel.hidden = true;
            setGeneratedResetPassword();
            resetDialog.showModal();
        });
    });

    document.querySelectorAll('input[name="reset-password-mode-choice"]').forEach(choice => {
        choice.addEventListener('change', () => {
            const useCustomPassword = choice.value === 'custom' && choice.checked;
            if (generatedPanel) generatedPanel.hidden = useCustomPassword;
            if (customPanel) customPanel.hidden = !useCustomPassword;
            if (resetFeedback) resetFeedback.textContent = '';
            if (useCustomPassword) customResetPassword?.focus();
        });
    });

    document.getElementById('regenerate-reset-password')?.addEventListener('click', setGeneratedResetPassword);
    document.getElementById('copy-reset-password')?.addEventListener('click', async event => {
        if (!generatedResetPassword?.value || !navigator.clipboard) return;
        try {
            await navigator.clipboard.writeText(generatedResetPassword.value);
            if (resetFeedback) resetFeedback.textContent = 'Generated password copied.';
        } catch {
            if (resetFeedback) resetFeedback.textContent = 'Unable to copy. Select and copy the password instead.';
        }
        event.currentTarget.setAttribute('title', 'Copy password');
    });

    const toggleCustomResetPassword = document.getElementById('toggle-custom-reset-password');
    toggleCustomResetPassword?.addEventListener('click', () => {
        if (!customResetPassword) return;
        const isVisible = customResetPassword.type === 'text';
        const label = isVisible ? 'Show password' : 'Hide password';
        customResetPassword.type = isVisible ? 'password' : 'text';
        toggleCustomResetPassword.setAttribute('aria-label', label);
        toggleCustomResetPassword.setAttribute('title', label);
        toggleCustomResetPassword.setAttribute('aria-pressed', String(!isVisible));
        const icon = toggleCustomResetPassword.querySelector('i');
        icon?.classList.toggle('fa-eye', !isVisible);
        icon?.classList.toggle('fa-eye-slash', isVisible);
    });

    confirmResetButton?.addEventListener('click', () => {
        if (!pendingResetForm) return;
        const mode = document.querySelector('input[name="reset-password-mode-choice"]:checked')?.value ?? 'generate';
        const password = mode === 'custom' ? (customResetPassword?.value ?? '') : (generatedResetPassword?.value ?? '');
        if (password.length < 8) {
            if (resetFeedback) resetFeedback.textContent = 'Password must be at least 8 characters.';
            if (mode === 'custom') customResetPassword?.focus();
            return;
        }

        const passwordField = pendingResetForm.querySelector('.reset-password-value');
        const modeField = pendingResetForm.querySelector('.reset-password-mode');
        if (!(passwordField instanceof HTMLInputElement) || !(modeField instanceof HTMLInputElement)) return;
        passwordField.value = password;
        modeField.value = mode;

        const formToSubmit = pendingResetForm;
        resetConfirmed = true;
        resetDialog?.close();
        HTMLFormElement.prototype.submit.call(formToSubmit);
    });

    cancelResetButton?.addEventListener('click', () => resetDialog?.close());
    resetDialog?.addEventListener('close', () => {
        if (!resetConfirmed) pendingResetTrigger?.focus();
        pendingResetForm = null;
        pendingResetTrigger = null;
        resetConfirmed = false;
    });

    const deleteDialog = document.getElementById('delete-user-dialog');
    const deleteUserName = document.getElementById('delete-user-name');
    const cancelDeleteButton = document.getElementById('cancel-delete-user');
    const confirmDeleteButton = document.getElementById('confirm-delete-user');
    let pendingDeleteForm = null;
    let pendingDeleteTrigger = null;
    let deleteConfirmed = false;

    document.querySelectorAll('.delete-user-form').forEach(form => {
        form.addEventListener('submit', event => {
            event.preventDefault();
            if (!(deleteDialog instanceof HTMLDialogElement)) return;
            pendingDeleteForm = form;
            pendingDeleteTrigger = form.querySelector('button[type="submit"]');
            if (deleteUserName) deleteUserName.textContent = form.dataset.username ?? 'this user';
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

    updateAccounts();
});

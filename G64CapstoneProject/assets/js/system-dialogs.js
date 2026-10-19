(() => {
    function ensureHost() {
        let host = document.getElementById('system-dialog-host');
        if (host) return host;
        host = document.createElement('div');
        host.id = 'system-dialog-host';
        host.setAttribute('aria-live', 'polite');
        document.body.appendChild(host);
        return host;
    }

    window.systemNotify = (message, type = 'success', title = '') => {
        const host = ensureHost();
        const toast = document.createElement('div');
        const labels = {success: 'Completed', error: 'Action needed', info: 'System notice'};
        toast.className = `system-toast system-toast-${type}`;
        toast.setAttribute('role', type === 'error' ? 'alert' : 'status');
        const heading = document.createElement('strong');
        heading.textContent = title || labels[type] || labels.info;
        const text = document.createElement('span');
        text.textContent = String(message);
        const close = document.createElement('button');
        close.type = 'button';
        close.className = 'system-toast-close';
        close.setAttribute('aria-label', 'Dismiss notification');
        close.textContent = '×';
        close.addEventListener('click', () => toast.remove());
        toast.append(heading, text, close);
        host.appendChild(toast);
        window.setTimeout(() => toast.remove(), 6000);
    };
    window.alert = message => window.systemNotify(message, 'info', 'System notification');

    window.systemConfirm = (message, title = 'Please confirm') => new Promise(resolve => {
        const host = ensureHost();
        const overlay = document.createElement('div');
        overlay.className = 'system-dialog-backdrop';
        const dialog = document.createElement('section');
        dialog.className = 'system-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'system-dialog-title');
        const heading = document.createElement('h2');
        heading.id = 'system-dialog-title';
        heading.textContent = title;
        const text = document.createElement('p');
        text.textContent = String(message);
        const actions = document.createElement('div');
        actions.className = 'system-dialog-actions';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'system-dialog-secondary';
        cancel.textContent = 'Cancel';
        const confirm = document.createElement('button');
        confirm.type = 'button';
        confirm.className = 'system-dialog-primary';
        confirm.textContent = 'Continue';
        const finish = result => {
            overlay.remove();
            resolve(result);
        };
        cancel.addEventListener('click', () => finish(false));
        confirm.addEventListener('click', () => finish(true));
        overlay.addEventListener('click', event => {
            if (event.target === overlay) finish(false);
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') finish(false);
        });
        actions.append(cancel, confirm);
        dialog.append(heading, text, actions);
        overlay.appendChild(dialog);
        host.appendChild(overlay);
        confirm.focus();
    });

    window.systemPrompt = (message, initialValue = '', title = 'Enter details') => new Promise(resolve => {
        const host = ensureHost();
        const overlay = document.createElement('div');
        overlay.className = 'system-dialog-backdrop';
        const dialog = document.createElement('form');
        dialog.className = 'system-dialog';
        dialog.setAttribute('role', 'dialog');
        dialog.setAttribute('aria-modal', 'true');
        dialog.setAttribute('aria-labelledby', 'system-dialog-title');
        const heading = document.createElement('h2');
        heading.id = 'system-dialog-title';
        heading.textContent = title;
        const label = document.createElement('label');
        label.className = 'system-dialog-label';
        label.textContent = String(message);
        const input = document.createElement('input');
        input.className = 'system-dialog-input';
        input.type = 'text';
        input.value = initialValue == null ? '' : String(initialValue);
        label.appendChild(input);
        const actions = document.createElement('div');
        actions.className = 'system-dialog-actions';
        const cancel = document.createElement('button');
        cancel.type = 'button';
        cancel.className = 'system-dialog-secondary';
        cancel.textContent = 'Cancel';
        const submit = document.createElement('button');
        submit.type = 'submit';
        submit.className = 'system-dialog-primary';
        submit.textContent = 'Save';
        const finish = value => {
            overlay.remove();
            resolve(value);
        };
        cancel.addEventListener('click', () => finish(null));
        overlay.addEventListener('click', event => {
            if (event.target === overlay) finish(null);
        });
        dialog.addEventListener('keydown', event => {
            if (event.key === 'Escape') {
                event.preventDefault();
                finish(null);
            }
        });
        dialog.addEventListener('submit', event => {
            event.preventDefault();
            finish(input.value);
        });
        actions.append(cancel, submit);
        dialog.append(heading, label, actions);
        overlay.appendChild(dialog);
        host.appendChild(overlay);
        input.focus();
        input.select();
    });
})();

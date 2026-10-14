document.addEventListener('DOMContentLoaded', () => {
    const roleTabs = document.querySelectorAll('.role-tabs .tab-btn');
    const submitBtn = document.getElementById('submitBtn');
    const roleInput = document.getElementById('selected_role'); // Hidden field if passing role

    roleTabs.forEach(tab => {
        tab.addEventListener('click', () => {
            // Remove active class from all tabs
            roleTabs.forEach(t => t.classList.remove('active'));
            tab.classList.add('active');

            const role = tab.dataset.role;
            if (roleInput) roleInput.value = role;

            // Update UI feedback based on selected role
            if (role === 'admin') {
                submitBtn.textContent = 'Sign In to Admin Dashboard';
                submitBtn.style.backgroundColor = '#6366f1';
                submitBtn.style.color = '#ffffff';
            } else {
                submitBtn.textContent = 'Sign In to Staff Dashboard';
                submitBtn.style.backgroundColor = '#38bdf8';
                submitBtn.style.color = '#0f172a';
            }
        });
    });
});

// Password Toggle Functionality
function togglePassword() {
    const pwdField = document.getElementById('password');
    const pwdBtn = document.querySelector('.toggle-pwd');
    if (pwdField) {
        if (pwdField.type === 'password') {
            pwdField.type = 'text';
            if (pwdBtn) pwdBtn.textContent = '🔒';
        } else {
            pwdField.type = 'password';
            if (pwdBtn) pwdBtn.textContent = '👁️';
        }
    }
}
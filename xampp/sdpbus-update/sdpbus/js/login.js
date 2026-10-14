// draft/sdpbus/js/login.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const alertBox = document.getElementById('alert-box');
    const passwordInput = document.getElementById('password');
    const showPassword = document.getElementById('show-password');

    if (passwordInput && showPassword) {
        showPassword.addEventListener('click', () => {
            const isVisible = passwordInput.type === 'password';
            passwordInput.type = isVisible ? 'text' : 'password';
            showPassword.setAttribute('aria-pressed', String(isVisible));
            showPassword.setAttribute('aria-label', isVisible ? 'Hide password' : 'Show password');
        });
    }

    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();

            if (alertBox) {
                alertBox.className = 'alert-box hidden';
            }

            const formData = new FormData(loginForm);

            try {
                const response = await fetch('auth.php', {
                    method: 'POST',
                    body: formData
                });

                const data = await response.json();

                if (data.status === 'success' || data.success) {
                    if (alertBox) {
                        alertBox.textContent = 'Login successful! Redirecting...';
                        alertBox.classList.remove('hidden');
                        alertBox.classList.add('success');
                    }
                    
                    setTimeout(() => {
                        window.location.href = data.redirect || 'dashboard.php';
                    }, 600);
                } else {
                    if (alertBox) {
                        alertBox.textContent = data.message || 'Invalid username or password.';
                        alertBox.classList.remove('hidden');
                        alertBox.classList.add('error');
                    }
                }
            } catch (error) {
                if (alertBox) {
                    alertBox.textContent = 'Connection error. Please try again.';
                    alertBox.classList.remove('hidden');
                    alertBox.classList.add('error');
                }
            }
        });
    }

});
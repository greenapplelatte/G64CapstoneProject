// sdpbus/js/signup.js
document.addEventListener('DOMContentLoaded', () => {
    const signupForm = document.getElementById('signupForm');
    const alertBox = document.getElementById('alert-box');

    if (!signupForm) return;

    signupForm.addEventListener('submit', async (e) => {
        e.preventDefault();

        if (alertBox) {
            alertBox.className = 'alert-box hidden';
        }

        const password = document.getElementById('password').value;
        const confirmPassword = document.getElementById('confirm_password').value;

        // Quick client-side check before hitting the server —
        // register.php still re-checks this itself, so this is
        // just for faster feedback.
        if (password !== confirmPassword) {
            if (alertBox) {
                alertBox.textContent = 'Passwords do not match.';
                alertBox.classList.remove('hidden');
                alertBox.classList.add('error');
            }
            return;
        }

        const formData = new FormData(signupForm);

        try {
            const response = await fetch('register.php', {
                method: 'POST',
                body: formData
            });

            const data = await response.json();

            if (data.status === 'success') {
                if (alertBox) {
                    alertBox.textContent = 'Account created! Redirecting...';
                    alertBox.classList.remove('hidden');
                    alertBox.classList.add('success');
                }

                setTimeout(() => {
                    window.location.href = data.redirect || 'dashboard.php';
                }, 600);
            } else {
                if (alertBox) {
                    alertBox.textContent = data.message || 'Registration failed. Please try again.';
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
});

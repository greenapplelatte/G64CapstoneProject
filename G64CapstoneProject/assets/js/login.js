// assets/js/login.js
document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    const emailInput = document.getElementById('email');
    const passwordInput = document.getElementById('password');
    const emailError = document.getElementById('emailError');
    const passwordError = document.getElementById('passwordError');

    loginForm.addEventListener('submit', (e) => {
        let valid = true;

        // Reset errors
        emailError.textContent = '';
        passwordError.textContent = '';

        // Validate Email
        if (!emailInput.value.trim()) {
            emailError.textContent = 'Email address is required.';
            valid = false;
        }

        // Validate Password
        if (!passwordInput.value.trim()) {
            passwordError.textContent = 'Password is required.';
            valid = false;
        }

        if (!valid) {
            e.preventDefault(); // Stop form submission
        }
    });
});
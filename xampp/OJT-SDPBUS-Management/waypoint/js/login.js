document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('#loginForm');
    if (!form) return;

    form.addEventListener('submit', (event) => {
        const username = document.querySelector('#username');
        const password = document.querySelector('#password');

        if (!username.value.trim() || !password.value) {
            event.preventDefault();
            alert('Please enter your username and password.');
        }
    });
});

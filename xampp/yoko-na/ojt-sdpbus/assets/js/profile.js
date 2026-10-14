document.addEventListener('DOMContentLoaded', function () {
    const accountStatus = document.getElementById('accountStatus');
    const lastSignin = document.getElementById('lastSignin');
    const passwordStatus = document.getElementById('passwordStatus');

    if (accountStatus && lastSignin && passwordStatus) {
        const formatLastSignin = (timestamp) => {
            if (!timestamp) {
                const currentTime = new Intl.DateTimeFormat(undefined, {
                    hour: '2-digit',
                    minute: '2-digit'
                }).format(new Date());
                return `Today, ${currentTime}`;
            }

            const date = new Date(`${timestamp.replace(' ', 'T')}Z`);
            if (Number.isNaN(date.getTime())) return 'N/A';

            return new Intl.DateTimeFormat(undefined, {
                month: 'short',
                day: '2-digit',
                year: 'numeric',
                hour: '2-digit',
                minute: '2-digit'
            }).format(date);
        };

        lastSignin.textContent = formatLastSignin(lastSignin.dataset.lastLogin);

        const refreshProfileStatus = async () => {
            try {
                const response = await fetch('/ojt-sdpbus/backend/user/profile_status.php', {
                    headers: { Accept: 'application/json' },
                    cache: 'no-store'
                });
                if (!response.ok) return;

                const data = await response.json();
                accountStatus.textContent = data.account_status;
                lastSignin.dataset.lastLogin = data.last_login ?? '';
                lastSignin.textContent = formatLastSignin(data.last_login);
                passwordStatus.textContent = data.force_password_change ? 'Default, needs change' : 'Updated';
                passwordStatus.classList.toggle('text-warning', data.force_password_change);
            } catch (error) {
                console.error('Unable to refresh profile status:', error);
            }
        };

        refreshProfileStatus();
        window.setInterval(() => {
            if (!document.hidden) refreshProfileStatus();
        }, 15000);
    }

    // --- 1. Profile Picture AJAX Upload ---
    const avatarInput = document.getElementById('avatarInput');
    const avatarImage = document.getElementById('avatarImage');
    const avatarPlaceholder = document.getElementById('avatarPlaceholder');
    const deleteAvatarButton = document.getElementById('deleteAvatarButton');

    if (deleteAvatarButton) {
        deleteAvatarButton.addEventListener('click', function (event) {
            if (!window.confirm('Remove your profile photo?')) {
                event.preventDefault();
            }
        });
    }

    if (avatarInput) {
        avatarInput.addEventListener('change', function () {
            if (this.files && this.files[0]) {
                const formData = new FormData();
                formData.append('avatar', this.files[0]);

                fetch('/ojt-sdpbus/backend/auth/update_profile.php', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        avatarImage.src = data.image_url;
                        avatarImage.style.display = 'block';
                        if (avatarPlaceholder) avatarPlaceholder.style.display = 'none';
                    } else {
                        alert('Upload failed: ' + data.message);
                    }
                })
                .catch(error => {
                    console.error('Error uploading image:', error);
                    alert('An error occurred while uploading the profile image.');
                });
            }
        });
    }

    // --- 2. Password Visibility Toggle ---
    document.querySelectorAll('.toggle-password').forEach(icon => {
        icon.addEventListener('click', function () {
            const targetId = this.getAttribute('data-target');
            const input = document.getElementById(targetId);

            if (input.type === 'password') {
                input.type = 'text';
                this.classList.replace('fa-eye', 'fa-eye-slash');
            } else {
                input.type = 'password';
                this.classList.replace('fa-eye-slash', 'fa-eye');
            }
        });
    });

    // --- 3. Live Password Validation & Strength Meter ---
    const newPassword = document.getElementById('new_password');
    const confirmPassword = document.getElementById('confirm_password');
    const matchIcon = document.getElementById('matchIcon');

    const ruleMinLength = document.getElementById('ruleMinLength');
    const ruleCasing = document.getElementById('ruleCasing');
    const ruleNumber = document.getElementById('ruleNumber');
    const ruleSymbol = document.getElementById('ruleSymbol');
    const ruleMatch = document.getElementById('ruleMatch');

    const strengthLabel = document.getElementById('strengthLabel');
    const strengthAdvice = document.getElementById('strengthAdvice');
    const meterLine = document.getElementById('meterLine');
    const dot0 = document.getElementById('dot0');
    const dot1 = document.getElementById('dot1');
    const dot2 = document.getElementById('dot2');
    const dot3 = document.getElementById('dot3');
    const passwordForm = newPassword && newPassword.closest('form');

    function updateRule(element, isValid) {
        if (!element) return;
        const icon = element.querySelector('i');
        if (isValid) {
            element.classList.add('valid');
            if (icon) icon.className = 'fa-solid fa-circle-check';
        } else {
            element.classList.remove('valid');
            if (icon) icon.className = 'fa-regular fa-circle';
        }
    }

    function getPasswordState() {
        const value = newPassword.value;
        const confirmValue = confirmPassword.value;

        return {
            hasMinLength: value.length >= 8,
            hasCasing: /[a-z]/.test(value) && /[A-Z]/.test(value),
            hasNumber: /\d/.test(value),
            hasSymbol: /[^A-Za-z0-9\s]/.test(value),
            isMatching: value.length > 0 && value === confirmValue
        };
    }

    function checkPassword() {
        if (!newPassword || !confirmPassword) return;

        const { hasMinLength, hasCasing, hasNumber, hasSymbol, isMatching } = getPasswordState();
        newPassword.setCustomValidity('');
        confirmPassword.setCustomValidity('');

        updateRule(ruleMinLength, hasMinLength);
        updateRule(ruleCasing, hasCasing);
        updateRule(ruleNumber, hasNumber);
        updateRule(ruleSymbol, hasSymbol);
        updateRule(ruleMatch, isMatching);

        if (matchIcon) {
            matchIcon.style.display = isMatching ? 'inline-block' : 'none';
        }

        const score = [hasMinLength, hasCasing, hasNumber, hasSymbol].filter(Boolean).length;
        const strengthDots = [dot0, dot1, dot2, dot3];

        if (strengthLabel) {
            strengthLabel.textContent = score === 4 ? 'Strong' : score >= 3 ? 'Good' : 'Weak';
            strengthLabel.classList.remove('weak', 'good', 'strong');
            strengthLabel.classList.add(score === 4 ? 'strong' : score >= 3 ? 'good' : 'weak');
        }
        if (strengthAdvice) {
            strengthAdvice.textContent = score === 4
                ? 'Your password meets all requirements.'
                : score === 0
                    ? 'Use at least 8 characters, mixed case, a number, and a symbol.'
                    : 'Meet all requirements to make it strong.';
        }
        if (meterLine) {
            meterLine.style.width = `${score * 25}%`;
            meterLine.classList.toggle('strong', score === 4);
        }
        strengthDots.forEach((dot, index) => {
            if (dot) dot.classList.toggle('active', index < score);
        });
    }

    if (newPassword && confirmPassword) {
        newPassword.addEventListener('input', checkPassword);
        confirmPassword.addEventListener('input', checkPassword);
        checkPassword();

        if (passwordForm) {
            passwordForm.addEventListener('submit', function (event) {
                const state = getPasswordState();
                const isStrong = state.hasMinLength && state.hasCasing && state.hasNumber && state.hasSymbol;

                if (!isStrong) {
                    event.preventDefault();
                    newPassword.setCustomValidity('Use at least 8 characters, uppercase and lowercase letters, a number, and a symbol.');
                    newPassword.reportValidity();
                } else if (!state.isMatching) {
                    event.preventDefault();
                    confirmPassword.setCustomValidity('The passwords must match.');
                    confirmPassword.reportValidity();
                }
            });
        }
    }
});
<?php require_once __DIR__ . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Masters Vineyard Enterprise</title>

    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/login.css?v=20261019-2">
</head>
<body>

<div class="login-shell">

    <!-- BACKGROUND DECOR -->
    <div class="login-bg-orb orb-1"></div>
    <div class="login-bg-orb orb-2"></div>
    <div class="login-bg-orb orb-3"></div>
    <div class="grid-overlay"></div>

    <div class="login-container">

        <!-- LEFT SIDE -->
        <section class="hero-panel">
            <div class="brand-row">
                <div class="brand-logo">
                    <img src="<?= BASE_URL ?>/assets/images/mve-brand.svg" alt="Masters Vineyard Enterprise">
                </div>
                <div class="brand-copy">
                    <span class="brand-eyebrow">Production Monitoring System</span>
                    <h1>Masters Vineyard Enterprise</h1>
                </div>
            </div>

            <div class="hero-copy">
                <div class="hero-badge">
                    <i class="fa-solid fa-wave-square"></i>
                    <span>Realtime plant visibility</span>
                </div>

                <h2>
                    Smart Monitoring,
                    <span>Better Production</span>
                </h2>

                <p>
                    Centralized production monitoring for operators, supervisors,
                    and managers — with defect reporting, line oversight, shift
                    monitoring, and production analytics in one place.
                </p>
            </div>

            <div class="hero-stats">
                <div class="hero-stat-card">
                    <div class="hero-stat-top">
                        <span class="stat-dot green"></span>
                        <span>Live Monitoring</span>
                    </div>
                    <strong>Station-Based Tracking</strong>
                    <p>Record good units and defect reports in real time.</p>
                </div>

                <div class="hero-stat-card">
                    <div class="hero-stat-top">
                        <span class="stat-dot blue"></span>
                        <span>Supervisor Control</span>
                    </div>
                    <strong>Review & Oversight</strong>
                    <p>Validate reports, monitor the floor, and review shift yield.</p>
                </div>

                <div class="hero-stat-card">
                    <div class="hero-stat-top">
                        <span class="stat-dot amber"></span>
                        <span>Management View</span>
                    </div>
                    <strong>Production Insights</strong>
                    <p>Track plant performance, users, and operational progress.</p>
                </div>
            </div>

            <div class="role-grid">
                <div class="role-card">
                    <span class="role-pill role-operator">Operator</span>
                    <h3>Fast floor reporting</h3>
                    <p>Submit defects and record station activity quickly and clearly.</p>
                </div>

                <div class="role-card">
                    <span class="role-pill role-supervisor">Supervisor</span>
                    <h3>Line monitoring</h3>
                    <p>See station status, review issues, and supervise production flow.</p>
                </div>

                <div class="role-card">
                    <span class="role-pill role-manager">Manager</span>
                    <h3>Operational visibility</h3>
                    <p>Access analytics, user control, and production-wide summaries.</p>
                </div>
            </div>

            <div class="hero-footer-note">
                <span class="pulse-dot"></span>
                <span>System access is available to authorized users only.</span>
            </div>
        </section>

        <!-- RIGHT SIDE -->
        <section class="auth-panel">
            <div class="auth-card">

                <div class="auth-top-badge">
                    <i class="fa-solid fa-gauge-high"></i>
                    <span>Secure access to production monitoring</span>
                </div>

                <div class="auth-card-inner">
                    <div class="auth-head">
                        <div>
                            <span class="auth-kicker">SIGN IN</span>
                            <h3>Welcome back</h3>
                            <p>Enter your credentials to continue.</p>
                        </div>
                        <div class="auth-date"><?= date('M d, Y'); ?></div>
                    </div>

                    <?php if (isset($_GET['error'])): ?>
                        <div class="alert-error">
                            <i class="fa-solid fa-circle-exclamation"></i>
                            <span>
                                <?php
                                    if ($_GET['error'] == 'invalid') echo "Invalid username or password.";
                                    elseif ($_GET['error'] == 'empty') echo "Please fill in all fields.";
                                    elseif ($_GET['error'] == 'csrf') echo "Your session expired. Please try again.";
                                    elseif ($_GET['error'] == 'deactivated') echo "This account has been deactivated. Please contact your manager to request reactivation.";
                                    elseif ($_GET['error'] == 'locked') {
                                        $wait = max(1, (int)($_GET['wait'] ?? 5));
                                        echo "Too many failed attempts. Try again in " . $wait . " minute" . ($wait === 1 ? '' : 's') . ".";
                                    } else {
                                        echo "An error occurred. Please try again.";
                                    }
                                ?>
                            </span>
                        </div>
                    <?php endif; ?>

                    <form action="backend/process_login.php" method="POST" class="auth-form">
                        <?= csrf_field() ?>

                        <div class="form-group">
                            <label for="username">Username</label>
                            <div class="input-wrapper">
                                <i class="fa-regular fa-user input-icon"></i>
                                <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="off">
                            </div>
                        </div>

                        <div class="form-group">
                            <label for="password">Password</label>
                            <div class="input-wrapper">
                                <i class="fa-solid fa-lock input-icon"></i>
                                <input type="password" id="password" name="password" placeholder="Enter your password" required>
                                <button type="button" class="btn-toggle-show" onclick="togglePassword()">Show</button>
                            </div>
                        </div>

                        <div class="form-row-inline">
                            <label class="checkbox-group">
                                <input type="checkbox" id="remember" name="remember">
                                <span>Keep me signed in on this device</span>
                            </label>
                        </div>

                        <button type="submit" class="btn-submit">
                            <span>Login</span>
                            <i class="fa-solid fa-arrow-right-long"></i>
                        </button>
                    </form>

                    <button type="button" class="btn-request" id="btnRequestAccess">
                        <i class="fa-regular fa-id-card"></i>
                        <span>Request Access</span>
                    </button>

                    <div class="login-card-links">
                        <p>Forgot your password? Ask a manager to reset it.</p>
                        <p>No account yet? <a href="#" id="linkRequestAccess">Request access</a> and a manager will review it.</p>
                    </div>
                </div>

                <div class="support-strip">
                    <div class="support-copy">
                        <strong>Need assistance?</strong>
                        <span>We can guide new users through the system.</span>
                    </div>
                    <button type="button" class="btn-contact">Get in touch</button>
                </div>

                <div class="bottom-metrics-tag">
                    Software-based production monitoring
                </div>
            </div>
        </section>

    </div>
</div>

<!-- REQUEST ACCESS MODAL -->
<div class="modal-backdrop" id="requestModal" role="dialog" aria-modal="true" aria-labelledby="requestTitle">
    <div class="modal-card">
        <h3 id="requestTitle">Request access</h3>
        <p class="modal-sub">Fill this in and a manager will review it. You will get your login details once it is approved.</p>

        <div class="req-msg" id="reqMsg" role="alert"></div>

        <form id="requestForm" novalidate>
            <div class="form-group">
                <label for="req_full_name">Full name</label>
                <div class="input-wrapper">
                    <input type="text" id="req_full_name" name="full_name" maxlength="100" placeholder="e.g. Juan Dela Cruz" required autocomplete="name">
                </div>
            </div>

            <div class="form-group">
                <label for="req_username">Preferred username</label>
                <div class="input-wrapper">
                    <input type="text" id="req_username" name="username" maxlength="50" placeholder="letters, numbers, . _ -" required autocomplete="off">
                </div>
            </div>

            <div class="modal-row">
                <div class="form-group">
                    <label for="req_role">Role</label>
                    <select id="req_role" name="role" required>
                        <option value="Operator">Operator</option>
                        <option value="Supervisor">Supervisor</option>
                    </select>
                </div>
                <div class="form-group">
                    <label for="req_line">Line</label>
                    <select id="req_line" name="line" required>
                        <option value="">Loading production lines…</option>
                    </select>
                </div>
            </div>

            <div class="hp-field" aria-hidden="true">
                <label for="req_website">Website</label>
                <input type="text" id="req_website" name="website" tabindex="-1" autocomplete="off">
            </div>

            <div class="modal-actions">
                <button type="button" class="btn-cancel" id="btnCancelRequest">Close</button>
                <button type="submit" class="btn-submit" id="btnSubmitRequest">Send request</button>
            </div>
        </form>
    </div>
</div>

<script>
    const BASE_URL = <?= json_encode(BASE_URL) ?>;

    function togglePassword() {
        const passInput = document.getElementById('password');
        const btn = document.querySelector('.btn-toggle-show');

        if (passInput.type === 'password') {
            passInput.type = 'text';
            btn.textContent = 'Hide';
        } else {
            passInput.type = 'password';
            btn.textContent = 'Show';
        }
    }

    (function () {
        const modal  = document.getElementById('requestModal');
        const form   = document.getElementById('requestForm');
        const msg    = document.getElementById('reqMsg');
        const submit = document.getElementById('btnSubmitRequest');

        fetch(BASE_URL + '/backend/process_access_request.php?action=get_lines')
            .then(res => res.json())
            .then(data => {
                const lineSelect = document.getElementById('req_line');
                if (!data.status || data.status !== 'success') throw new Error(data.message || 'Unable to load production lines.');
                lineSelect.replaceChildren(new Option('Choose a production line', ''));
                data.lines.forEach(line => lineSelect.add(new Option(line.line_name, line.line_name)));
                if (!data.lines.length) lineSelect.replaceChildren(new Option('No active production lines', ''));
            })
            .catch(error => {
                const lineSelect = document.getElementById('req_line');
                lineSelect.replaceChildren(new Option('Production lines unavailable', ''));
                showMsg('error', error.message);
            });

        function showMsg(type, text) {
            msg.className = 'req-msg ' + type;
            msg.textContent = text;
        }

        function openModal(e) {
            if (e) e.preventDefault();
            msg.className = 'req-msg';
            modal.classList.add('open');
            document.getElementById('req_full_name').focus();
        }

        function closeModal() {
            modal.classList.remove('open');
        }

        document.getElementById('btnRequestAccess').addEventListener('click', openModal);
        document.getElementById('linkRequestAccess').addEventListener('click', openModal);
        document.getElementById('btnCancelRequest').addEventListener('click', closeModal);
        modal.addEventListener('click', e => { if (e.target === modal) closeModal(); });
        document.addEventListener('keydown', e => { if (e.key === 'Escape') closeModal(); });

        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const name = form.full_name.value.trim();
            const user = form.username.value.trim();

            if (name.length < 2) return showMsg('error', 'Please enter your full name.');
            if (!/^[A-Za-z0-9_.-]{3,50}$/.test(user)) {
                return showMsg('error', 'Username must be 3–50 characters: letters, numbers, dots, dashes or underscores.');
            }

            submit.disabled = true;
            submit.textContent = 'Sending...';

            fetch(BASE_URL + '/backend/process_access_request.php', {
                method: 'POST',
                body: new FormData(form)
            })
            .then(res => res.json())
            .then(data => {
                if (data.status === 'success') {
                    showMsg('success', data.message);
                    form.reset();
                } else {
                    showMsg('error', data.message || 'Could not send the request.');
                }
            })
            .catch(() => showMsg('error', 'Could not reach the server. Please try again.'))
            .finally(() => {
                submit.disabled = false;
                submit.textContent = 'Send request';
            });
        });
    })();
</script>

</body>
</html>
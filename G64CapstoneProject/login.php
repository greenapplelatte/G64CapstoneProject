<?php require_once __DIR__ . '/includes/auth.php'; ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <?= head_assets() ?>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Masters Vineyard Enterprise</title>
    <!-- Google Fonts & FontAwesome -->
    <link href="https://fonts.googleapis.com/css2?family=Plus+Jakarta+Sans:wght@400;500;600;700;800&family=JetBrains+Mono:wght@500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    
    <style>
        :root {
            --bg-dark: #0a0c0e;
            --panel-dark: #121518;
            --card-dark: #1a1e23;
            --accent-yellow: #f59e0b;
            --accent-lime: #84cc16;
            --accent-green: #22c55e;
            --text-main: #f8fafc;
            --text-muted: #94a3b8;
            --border-dark: #27272a;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: 'Plus Jakarta Sans', sans-serif;
        }

        /* HIDDEN SCROLLBAR WITH FUNCTIONAL SCROLLING */
        html, body {
            width: 100vw;
            min-height: 100vh;
            overflow-y: auto;          /* Allows vertical scrolling */
            overflow-x: hidden;         /* Blocks horizontal overflow */
            scrollbar-width: none;      /* Firefox */
            -ms-overflow-style: none;   /* Internet Explorer & Edge */
            background-color: var(--bg-dark);
            color: var(--text-main);
        }

        /* WebKit Browsers (Chrome, Safari, Opera) */
        html::-webkit-scrollbar,
        body::-webkit-scrollbar {
            display: none;
            width: 0;
            height: 0;
        }

        body {
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 2.5rem 3rem;
        }

        /* 1920x1080 MAIN CONTAINER */
        .app-container {
            width: 100%;
            max-width: 1420px;
            display: grid;
            grid-template-columns: 1.35fr 0.95fr;
            gap: 4rem;
            align-items: center;
            margin: auto;
        }

        /* LEFT HERO PANEL */
        .hero-section {
            padding: 1rem 0;
            display: flex;
            flex-direction: column;
            gap: 2rem;
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 0.85rem;
        }

        .brand-logo {
            width: 42px;
            height: 42px;
            background: var(--accent-yellow);
            color: #000000;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: 800;
            font-size: 0.95rem;
        }

        .brand-title {
            font-size: 1.25rem;
            font-weight: 700;
            letter-spacing: -0.2px;
            color: #ffffff;
        }

        .hero-heading {
            font-size: 3.6rem;
            font-weight: 800;
            line-height: 1.08;
            letter-spacing: -1.5px;
            color: #ffffff;
        }

        .hero-heading span {
            color: var(--accent-yellow);
        }

        .hero-description {
            color: var(--text-muted);
            font-size: 1.05rem;
            line-height: 1.6;
            max-width: 600px;
        }

        /* REAL-TIME PILL COUNTER DISPLAY */
        .realtime-widget {
            display: flex;
            flex-direction: column;
            gap: 0.75rem;
        }

        .counter-bar {
            display: flex;
            gap: 6px;
            align-items: center;
            flex-wrap: wrap;
        }

        .pill-unit {
            width: 13px;
            height: 28px;
            border-radius: 3px;
            background: var(--accent-lime);
        }

        .pill-unit.defect {
            background: var(--accent-yellow);
        }

        .counter-stats {
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.85rem;
            color: var(--text-muted);
            display: flex;
            gap: 0.85rem;
        }

        /* ROLE BADGES */
        .role-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 1rem;
        }

        .role-card {
            background: #14181c;
            border: 1px solid var(--border-dark);
            border-radius: 14px;
            padding: 1.1rem;
            display: flex;
            flex-direction: column;
            gap: 0.5rem;
        }

        .role-badge {
            display: inline-block;
            align-self: flex-start;
            padding: 0.22rem 0.7rem;
            border-radius: 10px;
            font-size: 0.725rem;
            font-weight: 700;
            border: 1px solid transparent;
        }

        .role-badge.operator {
            background: rgba(255, 255, 255, 0.08);
            color: #ffffff;
            border-color: rgba(255, 255, 255, 0.2);
        }

        .role-badge.supervisor {
            background: rgba(59, 130, 246, 0.15);
            color: #60a5fa;
            border-color: rgba(59, 130, 246, 0.3);
        }

        .role-badge.manager {
            background: rgba(245, 158, 11, 0.15);
            color: var(--accent-yellow);
            border-color: rgba(245, 158, 11, 0.3);
        }

        .role-card p {
            font-size: 0.8rem;
            color: var(--text-muted);
            line-height: 1.4;
        }

        .system-status {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.825rem;
            color: var(--text-muted);
        }

        .status-dot {
            width: 8px;
            height: 8px;
            background: var(--accent-green);
            border-radius: 50%;
            box-shadow: 0 0 8px var(--accent-green);
        }

        /* RIGHT SIDE LOGIN CARD */
        .login-card-wrapper {
            background: var(--panel-dark);
            border: 1px solid var(--border-dark);
            border-radius: 22px;
            padding: 1.75rem;
            display: flex;
            flex-direction: column;
            gap: 1.25rem;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.4);
        }

        .top-status-badge {
            align-self: flex-start;
            background: var(--accent-yellow);
            color: #000000;
            font-size: 0.8rem;
            font-weight: 700;
            padding: 0.4rem 1rem;
            border-radius: 20px;
            display: flex;
            align-items: center;
            gap: 0.45rem;
        }

        .login-card-inner {
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 18px;
            padding: 2rem;
            display: flex;
            flex-direction: column;
            gap: 1.35rem;
        }

        .card-header-bar {
            display: flex;
            justify-content: space-between;
            align-items: center;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.775rem;
            font-weight: 700;
        }

        .card-header-bar .tag {
            color: var(--accent-lime);
        }

        .card-header-bar .date {
            color: var(--text-muted);
        }

        .login-title h2 {
            font-size: 1.75rem;
            font-weight: 800;
            color: #ffffff;
        }

        .login-title p {
            font-size: 0.825rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        .form-group {
            display: flex;
            flex-direction: column;
            gap: 0.45rem;
            margin-bottom: 0.85rem;
        }

        .form-group label {
            font-size: 0.8rem;
            font-weight: 700;
            color: #ffffff;
        }

        .input-wrapper {
            position: relative;
            display: flex;
            align-items: center;
        }

        .input-wrapper input {
            width: 100%;
            padding: 0.85rem 1.1rem;
            background: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0f172a;
            outline: none;
        }

        .btn-toggle-show {
            position: absolute;
            right: 0.6rem;
            background: #e2e8f0;
            border: none;
            padding: 0.35rem 0.7rem;
            border-radius: 6px;
            font-size: 0.725rem;
            font-weight: 700;
            color: #475569;
            cursor: pointer;
        }

        .checkbox-group {
            display: flex;
            align-items: center;
            gap: 0.55rem;
            font-size: 0.775rem;
            color: var(--text-muted);
            margin-bottom: 0.5rem;
        }

        .checkbox-group input {
            width: 16px;
            height: 16px;
            accent-color: var(--accent-lime);
        }

        .btn-submit {
            width: 100%;
            padding: 0.95rem;
            background: var(--accent-lime);
            color: #000000;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: UPPERCASE;
            cursor: pointer;
            transition: opacity 0.2s ease, transform 0.1s ease;
        }

        .btn-submit:hover {
            opacity: 0.92;
            transform: translateY(-1px);
        }

        .login-card-links {
            text-align: center;
            font-size: 0.75rem;
            color: var(--text-muted);
            line-height: 1.5;
        }

        .login-card-links a {
            color: #ffffff;
            text-decoration: underline;
        }

        /* BOTTOM ASSISTANCE PANEL */
        .bottom-support-box {
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 14px;
            padding: 0.9rem 1.1rem;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .bottom-support-box span {
            font-size: 0.775rem;
            color: var(--text-muted);
        }

        .btn-contact {
            background: var(--accent-yellow);
            color: #000000;
            border: none;
            padding: 0.5rem 0.9rem;
            border-radius: 8px;
            font-size: 0.775rem;
            font-weight: 700;
            cursor: pointer;
        }

        .bottom-metrics-tag {
            align-self: flex-end;
            background: var(--accent-yellow);
            color: #000000;
            font-family: 'JetBrains Mono', monospace;
            font-size: 0.8rem;
            font-weight: 800;
            padding: 0.4rem 0.9rem;
            border-radius: 8px;
        }

        /* REQUEST ACCESS BUTTON + MODAL */
        .btn-request {
            width: 100%;
            margin-top: 0.6rem;
            margin-bottom: 1rem;
            padding: 0.85rem;
            background: transparent;
            color: var(--accent-lime);
            border: 1.5px solid var(--accent-lime);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 800;
            letter-spacing: 1px;
            text-transform: uppercase;
            cursor: pointer;
            transition: background 0.2s ease;
        }

        .btn-request:hover {
            background: rgba(132, 204, 22, 0.12);
        }

        .modal-backdrop {
            position: fixed;
            inset: 0;
            background: rgba(0, 0, 0, 0.7);
            display: none;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            z-index: 100;
        }

        .modal-backdrop.open {
            display: flex;
        }

        .modal-card {
            width: 100%;
            max-width: 420px;
            max-height: 92vh;
            overflow-y: auto;
            background: var(--card-dark);
            border: 1px solid var(--border-dark);
            border-radius: 16px;
            padding: 1.5rem;
        }

        .modal-card h3 {
            font-size: 1.15rem;
            color: var(--text-main);
        }

        .modal-card .modal-sub {
            font-size: 0.8rem;
            color: var(--text-muted);
            margin: 0.25rem 0 1.1rem;
        }

        .modal-card select {
            width: 100%;
            padding: 0.85rem 1.1rem;
            background: #ffffff;
            border: none;
            border-radius: 12px;
            font-size: 0.9rem;
            color: #0f172a;
            outline: none;
        }

        .modal-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 0.75rem;
        }

        .modal-actions {
            display: flex;
            gap: 0.6rem;
            margin-top: 0.4rem;
        }

        .modal-actions .btn-submit {
            flex: 1;
        }

        .btn-cancel {
            padding: 0.95rem 1.2rem;
            background: transparent;
            color: var(--text-muted);
            border: 1px solid var(--border-dark);
            border-radius: 12px;
            font-size: 0.85rem;
            font-weight: 700;
            cursor: pointer;
        }

        .req-msg {
            display: none;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 0.9rem;
        }

        .req-msg.error {
            display: block;
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
        }

        .req-msg.success {
            display: block;
            background: rgba(34, 197, 94, 0.15);
            border: 1px solid rgba(34, 197, 94, 0.35);
            color: #86efac;
        }

        .hp-field {
            position: absolute;
            left: -9999px;
            height: 0;
            overflow: hidden;
        }

        /* PHP ALERT BLOCK */
        .alert-error {
            background: rgba(239, 68, 68, 0.15);
            border: 1px solid rgba(239, 68, 68, 0.3);
            color: #fca5a5;
            padding: 0.7rem 0.9rem;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 0.4rem;
        }
    </style>
</head>
<body>

<div class="app-container">
    <!-- LEFT COLUMN HERO SECTION -->
    <div class="hero-section">
        <div class="brand-header">
            <div class="brand-logo">MVE</div>
            <div class="brand-title">Masters Vineyard Enterprise</div>
        </div>

        <h1 class="hero-heading">
            Smart Monitoring,<br>
            <span>Better Production</span>
        </h1>

        <p class="hero-description">
            Real-time discrepancy alerts and batch logging for line operators, floor supervisors, and enterprise managers.
        </p>

        <!-- AUTO-COUNTER PILLS -->
        <div class="realtime-widget">
            <div class="counter-bar">
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit defect"></div>
                <div class="pill-unit defect"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit defect"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
                <div class="pill-unit"></div>
            </div>
            <div class="counter-stats">
                <span>236 units today</span>
                <span>•</span>
                <span>4 rejected</span>
                <span>•</span>
                <span>3 pending review</span>
            </div>
        </div>

        <!-- USER ROLES SUMMARY -->
        <div class="role-grid">
            <div class="role-card">
                <span class="role-badge operator">Operator</span>
                <p>Report defects from your station as they happen.</p>
            </div>
            <div class="role-card">
                <span class="role-badge supervisor">Supervisor</span>
                <p>Run the line, review defects, close out shifts.</p>
            </div>
            <div class="role-card">
                <span class="role-badge manager">Manager</span>
                <p>Track plant OEE and manage who has access.</p>
            </div>
        </div>

        <!-- OPERATIONAL FOOTER -->
        <div class="system-status">
            <div class="status-dot"></div>
            <span>All services running</span>
            <span>•</span>
            <span>11 of 12 stations online</span>
        </div>
    </div>

    <!-- RIGHT COLUMN LOGIN CARD -->
    <div class="login-card-wrapper">
        <div class="top-status-badge">
            <i class="fa-solid fa-gauge-high"></i>
            <span>Efficiency OEE 78.4%</span>
        </div>

        <div class="login-card-inner">
            <div class="card-header-bar">
                <span class="tag">SIGN IN</span>
                <span class="date"><?php echo date('M d, Y'); ?></span>
            </div>

            <div class="login-title">
                <h2>Welcome back</h2>
                <p>Use your credentials to access the system.</p>
            </div>

            <!-- PHP ERROR MESSAGE BLOCK -->
            <?php if (isset($_GET['error'])): ?>
                <div class="alert-error">
                    <i class="fa-solid fa-circle-exclamation"></i>
                    <?php 
                        if ($_GET['error'] == 'invalid') echo "Invalid username or password.";
                        elseif ($_GET['error'] == 'empty') echo "Please fill in all fields.";
                        elseif ($_GET['error'] == 'csrf') echo "Your session expired. Please try again.";
                        elseif ($_GET['error'] == 'locked') {
                            $wait = max(1, (int)($_GET['wait'] ?? 5));
                            echo "Too many failed attempts. Try again in " . $wait . " minute" . ($wait === 1 ? '' : 's') . ".";
                        }
                        else echo "An error occurred. Please try again.";
                    ?>
                </div>
            <?php endif; ?>

            <form action="backend/process_login.php" method="POST">
                <?= csrf_field() ?>
                <div class="form-group">
                    <label for="username">Username</label>
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" placeholder="Enter your username" required autocomplete="off">
                    </div>
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="Enter your password" required>
                        <button type="button" class="btn-toggle-show" onclick="togglePassword()">Show</button>
                    </div>
                </div>

                <div class="checkbox-group">
                    <input type="checkbox" id="remember" name="remember">
                    <label for="remember">Keep me signed in on this device</label>
                </div>

                <button type="submit" class="btn-submit">LOGIN</button>
            </form>

            <button type="button" class="btn-request" id="btnRequestAccess">Request access</button>

            <div class="login-card-links">
                <p>Forgot your password? Ask a manager to reset it.</p>
                <p>No account yet? <a href="#" id="linkRequestAccess">Request access</a> and a manager will review it.</p>
            </div>
        </div>

        <div class="bottom-support-box">
            <span>New to the system? We can walk you through it.</span>
            <button type="button" class="btn-contact">Get in touch</button>
        </div>

        <div class="bottom-metrics-tag">
            Productivity 236 / 320
        </div>
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
                        <option value="Line 1">Line 1</option>
                        <option value="Line 2">Line 2</option>
                        <option value="Line 3">Line 3</option>
                    </select>
                </div>
            </div>

            <!-- honeypot: real users never see or fill this -->
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

    // ---- Request access ----
    (function () {
        const modal  = document.getElementById('requestModal');
        const form   = document.getElementById('requestForm');
        const msg    = document.getElementById('reqMsg');
        const submit = document.getElementById('btnSubmitRequest');

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

            fetch(BASE_URL + '/backend/process_access_request.php', { method: 'POST', body: new FormData(form) })
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
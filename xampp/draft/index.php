<?php
// Temporary error reporting to catch backend issues
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

session_start();
if (isset($_SESSION['user_id'])) {
    header("Location: dashboard.php");
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDPBUS - Login</title>
    <link rel="stylesheet" href="sdpbus/css/style.css">
    <script src="sdpbus/js/login.js" defer></script>
    <style>
        :root {
            --navy-bg: #030832;
            --navy-btn: #090b3b;
            --navy-btn-hover: #131763;
            --text-dark: #1e1e1e;
            --text-muted: #666666;
            --border-color: #cccccc;
            --input-focus: #090b3b;
            --error-color: #d9534f;
            --success-color: #5cb85c;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        html, body {
            height: 100%;
            width: 100%;
            background-color: var(--navy-bg);
            overflow: hidden;
        }

        .login-wrapper {
            display: flex;
            width: 100vw;
            height: 100vh;
        }

        /* Left White Section */
        .form-section {
            width: 44%;
            min-width: 420px;
            height: 100vh;
            background-color: #ffffff;
            border-top-right-radius: 40px;
            border-bottom-right-radius: 40px;
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            padding: 2.5rem 4rem;
            z-index: 10;
            box-shadow: 10px 0 30px rgba(0, 0, 0, 0.3);
        }

        .brand-header {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .star-logo-sm {
            width: 32px;
            height: 32px;
            flex-shrink: 0;
        }

        .star-logo-lg {
            width: 110px;
            height: 110px;
            flex-shrink: 0;
        }

        .brand-title-sm {
            font-size: 1.25rem;
            font-weight: 800;
            color: var(--navy-bg);
            letter-spacing: 0.5px;
        }

        .form-body {
            max-width: 380px;
            width: 100%;
            margin: 0 auto;
        }

        .form-body h1 {
            font-size: 1.75rem;
            font-weight: 700;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }

        .form-body .sub-text {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-bottom: 1.8rem;
        }

        .input-group {
            margin-bottom: 1.2rem;
        }

        .input-group label {
            display: block;
            font-size: 0.875rem;
            font-weight: 500;
            color: var(--text-dark);
            margin-bottom: 0.4rem;
        }

        .input-group input {
            width: 100%;
            padding: 0.75rem 1rem;
            border: 1px solid var(--border-color);
            border-radius: 8px;
            font-size: 0.9rem;
            outline: none;
            transition: border-color 0.2s ease;
        }

        .input-group input:focus {
            border-color: var(--input-focus);
        }

        .btn-primary {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--navy-btn);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background-color 0.2s ease;
        }

        .btn-primary:hover {
            background-color: var(--navy-btn-hover);
        }

        .signup-prompt {
            text-align: center;
            font-size: 0.85rem;
            color: var(--text-muted);
            margin: 1.2rem 0;
        }

        .signup-prompt a {
            color: var(--text-dark);
            font-weight: 700;
            text-decoration: none;
        }

        .divider {
            display: flex;
            align-items: center;
            text-align: center;
            color: var(--text-muted);
            font-size: 0.75rem;
            margin: 1.2rem 0;
        }

        .divider::before,
        .divider::after {
            content: '';
            flex: 1;
            border-bottom: 1px solid var(--border-color);
        }

        .divider span {
            padding: 0 0.75rem;
        }

        .btn-secondary {
            width: 100%;
            padding: 0.8rem;
            background-color: var(--navy-btn);
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 0.95rem;
            font-weight: 600;
            cursor: pointer;
            transition: background-color 0.2s ease;
        }

        .btn-secondary:hover {
            background-color: var(--navy-btn-hover);
        }

        .form-footer {
            font-size: 0.8rem;
            color: var(--text-muted);
            text-align: center;
        }

        /* Right Navy Section */
        .visual-section {
            flex: 1;
            height: 100vh;
            background-color: var(--navy-bg);
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            position: relative;
            padding: 3rem;
            color: #ffffff;
        }

        .brand-hero {
            display: flex;
            align-items: center;
            gap: 1.2rem;
            margin-bottom: 1.5rem;
        }

        .brand-hero h2 {
            font-size: 3.5rem;
            font-weight: 800;
            letter-spacing: 2px;
        }

        .hero-description {
            max-width: 380px;
            text-align: center;
            font-size: 1rem;
            line-height: 1.5;
            color: #d1d5db;
        }

        .visual-footer {
            position: absolute;
            bottom: 2.5rem;
            text-align: center;
        }

        .visual-footer .tagline {
            font-size: 0.9rem;
            color: #ffffff;
            margin-bottom: 0.4rem;
        }

        .visual-footer .copyright {
            font-size: 0.8rem;
            color: #9ca3af;
        }

        .alert-box {
            padding: 0.6rem 0.8rem;
            border-radius: 6px;
            font-size: 0.85rem;
            margin-bottom: 1rem;
            text-align: center;
        }
        .alert-box.error { background-color: #fde8e8; color: var(--error-color); }
        .alert-box.success { background-color: #e8f8e8; color: var(--success-color); }
        .hidden { display: none; }
    </style>
</head>
<body>

<div class="login-wrapper">
    <!-- Left Section -->
    <div class="form-section">
        <div class="brand-header">
            <svg class="star-logo-sm" viewBox="0 0 100 100" fill="#030832">
                <polygon points="50,5 62,38 95,50 62,62 50,95 38,62 5,50 38,38"/>
                <text x="50" y="58" font-size="24" font-weight="bold" fill="#ffffff" text-anchor="middle">S</text>
            </svg>
            <span class="brand-title-sm">DPBUS</span>
        </div>

        <div class="form-body">
            <h1>Welcome back</h1>
            <p class="sub-text">Enter your credentials to access the system.</p>

            <div id="alert-box" class="alert-box hidden"></div>

            <form id="loginForm" action="auth.php" method="POST" autocomplete="off">
                <div class="input-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" placeholder="Please enter your username" required>
                </div>

                <div class="input-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" placeholder="Please enter your password" required>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="signup-prompt">
                New to the system? Please <a href="#">sign up</a>.
            </div>

            <div class="divider">
                <span>OR</span>
            </div>

            <a href="includes/auth_check.php?guest=true" style="text-decoration: none;">
                <button type="button" class="btn-secondary">Continue as Guest</button>
            </a>
        </div>

        <div class="form-footer">
            &copy; 2026 SDPBUS System
        </div>
    </div>

    <!-- Right Section -->
    <div class="visual-section">
        <div class="brand-hero">
            <svg class="star-logo-lg" viewBox="0 0 100 100" fill="#ffffff">
                <polygon points="50,5 62,38 95,50 62,62 50,95 38,62 5,50 38,38"/>
                <text x="50" y="59" font-size="26" font-weight="bold" fill="#030832" text-anchor="middle">S</text>
            </svg>
            <h2>DPBUS</h2>
        </div>

        <p class="hero-description">
            The easiest way to keep every trip, route, and driver in sync — all in one place.
        </p>

        <div class="visual-footer">
            <p class="tagline">Smooth transit, every trip.</p>
            <p class="copyright">&copy; 2026 Shindengen Philippines</p>
        </div>
    </div>
</div>

<script src="sdpbus/js/login.js"></script>
</body>
</html>
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
    <link rel="stylesheet" href="sdpbus/css/login.css">
    <link rel="stylesheet" href="sdpbus/css/floating-stars.css">
    <script src="sdpbus/js/login.js" defer></script>
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
            <span class="brand-title-sm">SDPBUS</span>
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
                    <div class="password-field">
                        <input type="password" id="password" name="password" placeholder="Please enter your password" required>
                        <button type="button" class="password-toggle" id="show-password" aria-label="Show password" aria-pressed="false">
                            <svg class="eye-icon eye-open" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"></path>
                                <circle cx="12" cy="12" r="2.5"></circle>
                            </svg>
                            <svg class="eye-icon eye-closed" viewBox="0 0 24 24" aria-hidden="true">
                                <path d="m3 3 18 18"></path>
                                <path d="M10.6 6.2A10.7 10.7 0 0 1 12 6c6.5 0 10 6 10 6a18.7 18.7 0 0 1-3.1 3.6M6.2 6.7C3.5 8.3 2 12 2 12s3.5 6 10 6a10.5 10.5 0 0 0 3.4-.6"></path>
                            </svg>
                        </button>
                    </div>
                </div>

                <button type="submit" class="btn-primary">Login</button>
            </form>

            <div class="signup-prompt">
                New to the system? Please <a href="signup.php">crerate account</a>.
            </div>
        </div>

        <div class="form-footer">
            &copy; 2026 SDPBUS System
        </div>
    </div>

    <!-- Right Section -->
    <div class="visual-section">
        <link rel="stylesheet" href="sdpbus/css/floating-stars.css">
        <div class="brand-hero">
            <svg class="star-logo-lg" viewBox="0 0 100 100" fill="#ffffff">
                <polygon points="50,5 62,38 95,50 62,62 50,95 38,62 5,50 38,38"/>
                <text x="50" y="59" font-size="26" font-weight="bold" fill="#030832" text-anchor="middle">S</text>
            </svg>
            <h2>SDPBUS</h2>
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

</body>
</html>
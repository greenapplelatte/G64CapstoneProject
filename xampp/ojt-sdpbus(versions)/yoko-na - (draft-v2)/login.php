<?php
// login.php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

require_once __DIR__ . '/ojt-sdpbus/config/database.php';
require_once __DIR__ . '/ojt-sdpbus/includes/functions.php';

// Redirect to dashboard if logged in
if (isset($_SESSION['user_id'])) {
    header("Location: " . ($_SESSION['role'] === 'admin' ? '/ojt-sdpbus/frontend/admin/dashboard.php' : '/ojt-sdpbus/frontend/staff/dashboard.php'));
    exit;
}

$errorMsg = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = sanitize($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $errorMsg = "Please enter both username and password.";
    } else {
        try {
            $db = Database::getConnection();
            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch();

            if ($user && password_verify($password, $user['password_hash'])) {
                $updateStmt = $db->prepare("UPDATE users SET last_login = CURRENT_TIMESTAMP WHERE id = :id");
                $updateStmt->execute([':id' => $user['id']]);

                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];
                $_SESSION['profile_image'] = $user['profile_image'] ?? null;

                log_activity($user['id'], "Login", null, "User logged in successfully");

                if ((int)$user['force_password_change'] === 1) {
                    header("Location: /ojt-sdpbus/frontend/shared/profile.php?force_change=1");
                    exit;
                }

                if ($user['role'] === 'admin') {
                    header("Location: /ojt-sdpbus/frontend/admin/dashboard.php");
                } else {
                    header("Location: /ojt-sdpbus/frontend/staff/dashboard.php");
                }
                exit;
            } else {
                $errorMsg = "Invalid username or password.";
            }
        } catch (Exception $e) {
            error_log("Login Error: " . $e->getMessage());
            $errorMsg = "An internal error occurred. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - SDPBUS Management System</title>
    <!-- Font Awesome Icons -->
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <!-- Updated CSS path -->
    <link rel="stylesheet" href="/ojt-sdpbus/assets/css/login.css">
</head>
<body class="login-page">

    <!-- Bottom Dashed Path SVG -->
    <svg class="bg-dashed-path" viewBox="0 0 1440 60" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M-100 20 Q 360 55, 720 30 T 1540 40" stroke="#b0cdfa" stroke-width="2" stroke-dasharray="6 6"/>
    </svg>

    <div class="ticket-wrapper">
        <!-- Floating Bus Icon -->
        <div class="bg-bus-icon"><i class="fa-solid fa-bus-simple"></i></div>

        <!-- Left Ticket Stub -->
        <div class="ticket-stub">
            <div class="ticket-brand">
                <div class="brand-icon-box">
                    <i class="fa-solid fa-bus"></i>
                </div>
                <div class="brand-text">
                    <h2>SDPBUS</h2>
                    <span>Dispatching & Ticketing System</span>
                </div>
            </div>

            <div class="ticket-route-box">
                <div class="route-labels">
                    <span>FROM</span>
                    <span>TO</span>
                </div>
                <div class="route-points">
                    <span class="point-title">Route A</span>
                    <span class="route-dotted-line"></span>
                    <span class="point-title">Shindengen Phil.</span>
                </div>
                <p class="stub-description">
                    Plan, record, review, and bill every shuttle trip in one workspace.
                </p>
            </div>

            <div class="ticket-barcode-area">
                <div class="pseudo-barcode">
                    <span style="width: 3px;"></span><span style="width: 1px;"></span>
                    <span style="width: 4px;"></span><span style="width: 2px;"></span>
                    <span style="width: 1px;"></span><span style="width: 5px;"></span>
                    <span style="width: 2px;"></span><span style="width: 1px;"></span>
                    <span style="width: 4px;"></span><span style="width: 2px;"></span>
                    <span style="width: 3px;"></span><span style="width: 1px;"></span>
                </div>
                <span class="barcode-subtext">© 2026 SDPBUS Management System</span>
            </div>
        </div>

        <!-- Perforated Notches & Line -->
        <div class="ticket-notch top"></div>
        <div class="ticket-divider-line"></div>
        <div class="ticket-notch bottom"></div>

        <!-- Right Form Body -->
        <div class="ticket-body">
            <div class="ticket-header-access">
                Access
                <strong>Staff / Admin</strong>
            </div>

            <h1 class="welcome-title">Welcome Back!</h1>
            <p class="welcome-sub">Sign in to continue to your workspace.</p>

            <?php if (!empty($errorMsg)): ?>
                <div style="background-color: #fef2f2; border: 1px solid #fca5a5; color: #991b1b; padding: 10px; border-radius: 8px; font-size: 0.85rem; margin-bottom: 16px;">
                    <i class="fa-solid fa-triangle-exclamation"></i> <?= htmlspecialchars($errorMsg) ?>
                </div>
            <?php endif; ?>

            <form action="" method="POST">
                <div class="form-group">
                    <label for="username" class="form-label">Username</label>
                    <div class="input-icon-group">
                        <i class="fa-regular fa-user"></i>
                        <input type="text" id="username" name="username" class="form-input" placeholder="Enter your username" required autofocus>
                    </div>
                </div>

                <div class="form-group">
                    <label for="password" class="form-label">Password</label>
                    <div class="input-icon-group">
                        <i class="fa-solid fa-lock"></i>
                        <input type="password" id="password" name="password" class="form-input" placeholder="Enter your password" required>
                    </div>
                </div>

                <button type="submit" class="btn-submit">
                    Sign in <i class="fa-solid fa-arrow-right"></i>
                </button>
            </form>

            <p class="reset-notice">Forgot your password? Ask the admin to reset it.</p>
        </div>
    </div>

</body>
</html>
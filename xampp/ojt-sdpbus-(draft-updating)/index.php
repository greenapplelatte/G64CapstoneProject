<?php
session_start();

$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');

    if (!empty($username) && !empty($password)) {
        try {
            $db = new PDO('sqlite:' . __DIR__ . '/database/sdpbus.db');
            $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

            $stmt = $db->prepare("SELECT * FROM users WHERE username = :username");
            $stmt->execute([':username' => $username]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($user && password_verify($password, $user['password'])) {
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['username'] = $user['username'];
                $_SESSION['role'] = $user['role'];

                header("Location: dashboard.php");
                exit;
            } else {
                $error = "Invalid username or password.";
            }
        } catch (PDOException $e) {
            $error = "Database connection error.";
        }
    } else {
        $error = "Please fill in all required fields.";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title">SDPBUS Management System </title>
    <link rel="preload" href="icons/bg.jpg" as="image" fetchpriority="high">
    <link rel="preload" href="icons/bg2.jpg" as="image" fetchpriority="high">
    <link rel="prefetch" href="dashboard.php">
    <link rel="prefetch" href="dispatch_log.php">
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

<div class="login-container">
    <!-- Left Branding Panel -->
    <div class="brand-panel">
        <div class="brand-header">
    <div class="brand-logo-icon">
        <!-- Minimalist Bus Vector Logo -->
        <svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
            <rect x="3" y="3" width="18" height="15" rx="3" />
            <line x1="3" y1="11" x2="21" y2="11" />
            <path d="M19 18v2a1 1 0 0 1-1 1h-1a1 1 0 0 1-1-1v-2" />
            <path d="M7 18v2a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-2" />
            <circle cx="7.5" cy="14.5" r="1" fill="currentColor" />
            <circle cx="16.5" cy="14.5" r="1" fill="currentColor" />
        </svg>
    </div>
    <div>
        <div class="brand-title">SDPBUS</div>
        <div class="brand-subtitle">Operations &amp; Billing</div>
    </div>
</div>

        <div class="brand-hero">
            <div class="brand-tag">SHUTTLE MANAGEMENT</div>
            <h1>Every trip.<br>Fully accounted for.</h1>
            <p>One dependable workspace for dispatch, passenger records, toll tracking, and automated billing.</p>
        </div>

        <div class="brand-stats">
            <div class="stat-item">
                <h3>22</h3>
                <p>Trips today</p>
            </div>
            <div class="stat-item">
                <h3>742</h3>
                <p>Passengers moved</p>
            </div>
            <div class="stat-item">
                <h3>20</h3>
                <p>Active routes</p>
            </div>
        </div>
    </div>

    <!-- Right Login Form Panel -->
    <div class="form-panel">
        <div class="form-wrapper">
            <div class="form-tag">SECURE ACCESS</div>
            <h2>Welcome back</h2>
            <p class="form-subtext">Sign in to continue to your operations workspace.</p>

            <?php if (!empty($error)): ?>
                <div class="error-message"><?= htmlspecialchars($error) ?></div>
            <?php endif; ?>

            <form action="index.php" method="POST" id="loginForm">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" value="<?= htmlspecialchars($_POST['username'] ?? 'admin') ?>" required autocomplete="username">
                </div>

                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" value="password123" required autocomplete="current-password">
                </div>

                <button type="submit" class="btn-submit">
                    Sign in &rsaquo;
                </button>
            </form>

            <div class="system-status">
                <span class="status-dot"></span>
                <span>Local server secure session · Offline ready</span>
            </div>
        </div>
    </div>
</div>

</body>
</html>
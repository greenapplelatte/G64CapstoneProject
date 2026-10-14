<?php

session_start();

require_once __DIR__ . '/database/database.php';

if (!empty($_SESSION['user_id'])) {
    header('Location: dashboard.php');
    exit;
}

$error = $_GET['error'] ?? '';
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">

    <meta
        name="viewport"
        content="width=device-width, initial-scale=1.0"
    >

    <title>SDPBUS - Login</title>

    <link
        rel="stylesheet"
        href="css/style.css"
    >
</head>

<body>

<div class="login-page">

    <!-- =====================================
         LEFT SIDE
         ===================================== -->

    <div class="login-left">

        <!-- Small Logo -->

        <div class="small-logo">

            <div class="logo-star-small">
                S
            </div>

            <span>DPBUS</span>

        </div>


        <!-- Login Form -->

        <div class="login-container">

            <h1>Welcome back</h1>

            <p class="login-subtitle">
                Enter your credentials to access the system.
            </p>


            <!-- Error Message -->

            <?php if (!empty($error)): ?>

                <div class="alert alert-error">
                    <?= htmlspecialchars($error) ?>
                </div>

            <?php endif; ?>


            <!-- Success Message -->

            <?php if (!empty($success)): ?>

                <div class="alert alert-success">
                    <?= htmlspecialchars($success) ?>
                </div>

            <?php endif; ?>


            <form
                action="auth.php"
                method="POST"
                id="loginForm"
            >

                <!-- Username -->

                <div class="form-group">

                    <label for="username">
                        Username
                    </label>

                    <input
                        type="text"
                        id="username"
                        name="username"
                        placeholder="Please enter your username"
                        autocomplete="username"
                        required
                    >

                </div>


                <!-- Password -->

                <div class="form-group">

                    <label for="password">
                        Password
                    </label>

                    <input
                        type="password"
                        id="password"
                        name="password"
                        placeholder="Please enter your password"
                        autocomplete="current-password"
                        required
                    >

                </div>


                <!-- Login -->

                <button
                    type="submit"
                    class="btn login-btn"
                >
                    Login
                </button>

            </form>


            <!-- Sign Up -->

            <p class="signup-text">
                New to the system?
                Please
                <a href="signup.php">
                    sign up.
                </a>
            </p>


            <!-- OR -->

            <div class="divider">

                <span>OR</span>

            </div>


            <!-- Guest -->

            <form
                action="auth.php"
                method="POST"
            >

                <input
                    type="hidden"
                    name="guest"
                    value="1"
                >

                <button
                    type="submit"
                    class="btn guest-btn"
                >
                    Continue as Guess
                </button>

            </form>

        </div>


        <!-- Footer -->

        <div class="login-footer">

            © 2026 SDPBus System

        </div>

    </div>


    <!-- =====================================
         RIGHT SIDE
         ===================================== -->

    <div class="login-right">

        <div class="brand-area">

            <div class="large-logo">

                <div class="logo-star-large">
                    S
                </div>

                <div class="brand-name">
                    DPBUS
                </div>

            </div>


            <p class="brand-description">

                The easiest way to keep every trip, route,
                <br>
                and driver in sync – all in one place.

            </p>

        </div>


        <!-- Right Footer -->

        <div class="right-footer">

            Smooth transit, every trip.
            <br>

            © 2026 Shimendgen Philippines

        </div>

    </div>

</div>


<script src="js/login.js"></script>

</body>
</html>

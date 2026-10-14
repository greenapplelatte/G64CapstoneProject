<?php
session_start();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

require_once __DIR__ . '/database/database.php';

$username = trim($_POST['username'] ?? '');
$password = $_POST['password'] ?? '';

if ($username === '' || $password === '') {
    header('Location: index.php?error=' . urlencode('Please enter your username and password.'));
    exit;
}

try {
    $stmt = $pdo->prepare('SELECT id, username, password FROM users WHERE username = :username LIMIT 1');
    $stmt->execute(['username' => $username]);
    $user = $stmt->fetch();

    if ($user && password_verify($password, $user['password'])) {
        session_regenerate_id(true);
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['username'] = $user['username'];
        $_SESSION['is_guest'] = false;

        header('Location: dashboard.php');
        exit;
    }

    header('Location: index.php?error=' . urlencode('Invalid username or password.'));
    exit;
} catch (PDOException $e) {
    header('Location: index.php?error=' . urlencode('Unable to process your login right now.'));
    exit;
}

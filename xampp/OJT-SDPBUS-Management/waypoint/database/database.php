<?php

$dbFile = __DIR__ . DIRECTORY_SEPARATOR . 'waypoint.sqlite';

try {
    $pdo = new PDO('sqlite:' . $dbFile);

    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT NOT NULL UNIQUE,
            password TEXT NOT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )
    ");

    $stmt = $pdo->prepare(
        "SELECT id FROM users WHERE username = ? LIMIT 1"
    );

    $stmt->execute(['admin']);

    if (!$stmt->fetch()) {
        $stmt = $pdo->prepare(
            "INSERT INTO users (username, password)
             VALUES (?, ?)"
        );

        $stmt->execute([
            'admin',
            password_hash('admin123', PASSWORD_DEFAULT)
        ]);
    }

} catch (PDOException $e) {
    die("Database error: " . $e->getMessage());
}
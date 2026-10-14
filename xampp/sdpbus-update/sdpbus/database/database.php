<?php
// sdpbus/database/database.php

$dbPath = __DIR__ . '/sdpbus.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // WAL mode relies on shared-memory (-wal/-shm) companion files,
    // which OneDrive's cloud-sync layer frequently locks or delays —
    // producing exactly this "database is locked" error even when no
    // other program has the file open. DELETE mode (SQLite's original
    // default journal) doesn't use those companion files, so it works
    // reliably on OneDrive, network drives, and USB storage. For a
    // small local app like this, the performance difference is not
    // noticeable.
    $db->exec("PRAGMA journal_mode = DELETE;");
    $db->exec("PRAGMA busy_timeout = 5000;");

    // Create 'users' table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        fullname TEXT NOT NULL,
        role TEXT NOT NULL DEFAULT 'admin',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Create 'shuttle_logs' table
    $db->exec("CREATE TABLE IF NOT EXISTS shuttle_logs (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        trip_id TEXT NOT NULL,
        route TEXT NOT NULL,
        driver TEXT NOT NULL,
        bus_unit TEXT NOT NULL,
        departure_time TEXT NOT NULL,
        status TEXT DEFAULT 'Pending',
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    // Seed default admin account if table is empty
    $checkUsers = $db->query("SELECT COUNT(*) FROM users")->fetchColumn();
    if ($checkUsers == 0) {
        $hash = password_hash('admin123', PASSWORD_BCRYPT);
        $stmt = $db->prepare("INSERT INTO users (username, password, fullname, role) VALUES (:u, :p, :f, :r)");
        $stmt->execute([
            ':u' => 'fridz',
            ':p' => $hash,
            ':f' => 'Fridz Admin',
            ':r' => 'admin'
        ]);
    }

} catch (PDOException $e) {
    header('Content-Type: application/json');
    echo json_encode(['status' => 'error', 'message' => 'Database error: ' . $e->getMessage()]);
    exit;
}
?>
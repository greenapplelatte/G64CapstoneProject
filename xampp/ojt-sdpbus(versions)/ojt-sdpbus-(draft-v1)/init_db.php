<?php
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database.sqlite');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // Create users table with role support
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL
    )");

    // Insert default demo accounts
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, role) VALUES (:username, :password, :role)");
    
    // Admin user
    $stmt->execute([
        ':username' => 'admin',
        ':password' => password_hash('password123', PASSWORD_BCRYPT),
        ':role' => 'Administrator'
    ]);

    // Staff user
    $stmt->execute([
        ':username' => 'staff',
        ':password' => password_hash('password123', PASSWORD_BCRYPT),
        ':role' => 'Staff'
    ]);

    echo "Database initialized successfully! Created default accounts (admin / staff with password 'password123').";
} catch (PDOException $e) {
    echo "Database error: " . $e->getMessage();
}
?>
<?php
// config/database.php

class Database {
    private static ?PDO $instance = null;

    /**
     * Get singleton PDO SQLite Connection
     */
    public static function getConnection(): PDO {
        if (self::$instance === null) {
            try {
                $dbPath = __DIR__ . '/../data/sdpbus.db';
                
                // Ensure data directory exists
                if (!file_exists(__DIR__ . '/../data')) {
                    mkdir(__DIR__ . '/../data', 0777, true);
                }

                self::$instance = new PDO("sqlite:" . $dbPath);
                self::$instance->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
                self::$instance->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);

                // Enable foreign keys in SQLite
                self::$instance->exec("PRAGMA foreign_keys = ON;");

                // Auto-initialize schema if DB is newly created
                self::initSchema(self::$instance);

            } catch (PDOException $e) {
                die("Database Connection Error: " . $e->getMessage());
            }
        }
        return self::$instance;
    }

    /**
     * Initializes core tables and seeds default demo accounts
     */
    private static function initSchema(PDO $db): void {
        // 1. Users Table
        $db->exec("CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            username TEXT UNIQUE NOT NULL,
            password_hash TEXT NOT NULL,
            role TEXT CHECK(role IN ('staff', 'admin')) NOT NULL,
            force_password_change INTEGER DEFAULT 0,
            last_login DATETIME,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP
        )");

        // 2. Master Data: Routes
        $db->exec("CREATE TABLE IF NOT EXISTS routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            route_name TEXT UNIQUE NOT NULL,
            rate REAL NOT NULL DEFAULT 0.00,
            toll REAL NOT NULL DEFAULT 0.00,
            effective_date DATE,
            status TEXT CHECK(status IN ('ACTIVE', 'INACTIVE')) DEFAULT 'ACTIVE'
        )");

        // 3. Master Data: Buses
        $db->exec("CREATE TABLE IF NOT EXISTS buses (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            bus_code TEXT UNIQUE NOT NULL,
            capacity INTEGER DEFAULT 40,
            status TEXT CHECK(status IN ('ACTIVE', 'INACTIVE')) DEFAULT 'ACTIVE'
        )");

        // 4. Master Data: Drivers
        $db->exec("CREATE TABLE IF NOT EXISTS drivers (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            driver_name TEXT NOT NULL,
            status TEXT CHECK(status IN ('ACTIVE', 'INACTIVE')) DEFAULT 'ACTIVE'
        )");

        // 5. Trip Schedule
        $db->exec("CREATE TABLE IF NOT EXISTS trip_schedules (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            schedule_date DATE NOT NULL,
            shift TEXT NOT NULL,
            route_id INTEGER NOT NULL,
            bus_id INTEGER,
            driver_id INTEGER,
            forecast_passengers INTEGER DEFAULT 0,
            FOREIGN KEY (route_id) REFERENCES routes(id),
            FOREIGN KEY (bus_id) REFERENCES buses(id),
            FOREIGN KEY (driver_id) REFERENCES drivers(id)
        )");

        // 6. Trip Tickets
        $db->exec("CREATE TABLE IF NOT EXISTS tickets (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            ticket_number TEXT UNIQUE NOT NULL,
            schedule_id INTEGER,
            user_id INTEGER NOT NULL,
            route_id INTEGER NOT NULL,
            bus_id INTEGER NOT NULL,
            driver_id INTEGER NOT NULL,
            shift TEXT NOT NULL,
            trip_date DATE NOT NULL,
            trip_time TIME NOT NULL,
            forecast_passengers INTEGER NOT NULL,
            actual_passengers INTEGER DEFAULT 0,
            rate REAL NOT NULL,
            toll REAL NOT NULL,
            ticket_total REAL NOT NULL,
            trip_status TEXT CHECK(trip_status IN ('On time', 'Delayed', 'Completed')) DEFAULT 'On time',
            review_status TEXT CHECK(review_status IN ('Draft', 'Submitted', 'Approved', 'Returned')) DEFAULT 'Draft',
            remarks TEXT,
            admin_comment TEXT,
            billing_closed INTEGER DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id),
            FOREIGN KEY (route_id) REFERENCES routes(id),
            FOREIGN KEY (bus_id) REFERENCES buses(id),
            FOREIGN KEY (driver_id) REFERENCES drivers(id)
        )");

        // 7. Activity Log
        $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL,
            action_type TEXT NOT NULL,
            ticket_number TEXT,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id)
        )");

        self::seedDemoUser($db, 'admin_demo', 'admin');
        self::seedDemoUser($db, 'staff_demo', 'staff');
    }

    private static function seedDemoUser(PDO $db, string $username, string $role): void {
        $check = $db->prepare('SELECT 1 FROM users WHERE username = :user');
        $check->execute([':user' => $username]);

        if ($check->fetchColumn()) {
            return;
        }

        $insert = $db->prepare('INSERT OR IGNORE INTO users (username, password_hash, role) VALUES (:user, :pass, :role)');
        $insert->execute([
            ':user' => $username,
            ':pass' => password_hash('Password123!', PASSWORD_BCRYPT),
            ':role' => $role
        ]);
    }
}
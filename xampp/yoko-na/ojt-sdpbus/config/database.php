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

        $columnInfo = $db->query('PRAGMA table_info(users)')->fetchAll(PDO::FETCH_ASSOC);
        $userColumns = array_column($columnInfo, 'name');
        if (!in_array('last_login', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN last_login DATETIME');
        }
        if (!in_array('profile_image', $userColumns, true)) {
            $db->exec('ALTER TABLE users ADD COLUMN profile_image TEXT');
        }

        // 2. Master Data: Routes
        $db->exec("CREATE TABLE IF NOT EXISTS routes (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            route_name TEXT UNIQUE NOT NULL,
            rate REAL NOT NULL DEFAULT 0.00,
            toll REAL NOT NULL DEFAULT 0.00,
            effective_date DATE,
            status TEXT CHECK(status IN ('ACTIVE', 'INACTIVE')) DEFAULT 'ACTIVE'
        )");

        $routeColumns = $db->query('PRAGMA table_info(routes)')->fetchAll(PDO::FETCH_ASSOC);
        if (!in_array('effective_date', array_column($routeColumns, 'name'), true)) {
            $db->exec('ALTER TABLE routes ADD COLUMN effective_date DATE');
        }

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
            user_id INTEGER,
            username_snapshot TEXT,
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
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
            FOREIGN KEY (route_id) REFERENCES routes(id),
            FOREIGN KEY (bus_id) REFERENCES buses(id),
            FOREIGN KEY (driver_id) REFERENCES drivers(id)
        )");

        $billingPeriodsTableExists = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'billing_periods'")->fetchColumn();
        $db->exec("CREATE TABLE IF NOT EXISTS billing_periods (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            start_date DATE NOT NULL,
            end_date DATE NOT NULL,
            ticket_count INTEGER NOT NULL DEFAULT 0,
            total_amount REAL NOT NULL DEFAULT 0.00,
            closed_by INTEGER,
            closed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            CHECK (start_date <= end_date),
            FOREIGN KEY (closed_by) REFERENCES users(id) ON DELETE SET NULL
        )");

        if (!$billingPeriodsTableExists) {
            $legacyPeriod = $db->query("SELECT MIN(trip_date) AS start_date,
                                               MAX(trip_date) AS end_date,
                                               COUNT(*) AS ticket_count,
                                               COALESCE(SUM(ticket_total), 0) AS total_amount
                                        FROM tickets
                                        WHERE billing_closed = 1")->fetch(PDO::FETCH_ASSOC);
            if ((int)($legacyPeriod['ticket_count'] ?? 0) > 0) {
                $legacyInsert = $db->prepare("INSERT INTO billing_periods
                    (start_date, end_date, ticket_count, total_amount, closed_by)
                    VALUES (:start_date, :end_date, :ticket_count, :total_amount, NULL)");
                $legacyInsert->execute([
                    ':start_date' => $legacyPeriod['start_date'],
                    ':end_date' => $legacyPeriod['end_date'],
                    ':ticket_count' => (int)$legacyPeriod['ticket_count'],
                    ':total_amount' => (float)$legacyPeriod['total_amount']
                ]);
            }
        }

        // 7. Activity Log
        $db->exec("CREATE TABLE IF NOT EXISTS activity_logs (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER,
            username_snapshot TEXT,
            action_type TEXT NOT NULL,
            ticket_number TEXT,
            details TEXT,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
        )");

        // 8. Allowed driver, bus, and route combinations
        $driverAssignmentsTableExists = (bool)$db->query("SELECT 1 FROM sqlite_master WHERE type = 'table' AND name = 'driver_assignments'")->fetchColumn();
        $db->exec("CREATE TABLE IF NOT EXISTS driver_assignments (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            driver_id INTEGER NOT NULL,
            bus_id INTEGER NOT NULL,
            route_id INTEGER NOT NULL,
            status TEXT CHECK(status IN ('ACTIVE', 'INACTIVE')) DEFAULT 'ACTIVE',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            UNIQUE (driver_id, bus_id, route_id),
            FOREIGN KEY (driver_id) REFERENCES drivers(id),
            FOREIGN KEY (bus_id) REFERENCES buses(id),
            FOREIGN KEY (route_id) REFERENCES routes(id)
        )");

        if (!$driverAssignmentsTableExists) {
            $db->exec("INSERT OR IGNORE INTO driver_assignments (driver_id, bus_id, route_id)
                       SELECT DISTINCT driver_id, bus_id, route_id
                       FROM trip_schedules
                       WHERE driver_id IS NOT NULL AND bus_id IS NOT NULL");
            $db->exec("INSERT OR IGNORE INTO driver_assignments (driver_id, bus_id, route_id)
                       SELECT DISTINCT driver_id, bus_id, route_id
                       FROM tickets
                       WHERE driver_id IS NOT NULL AND bus_id IS NOT NULL");
        }

        self::migrateUserHistoryReferences($db);

        if ((int)$db->query('SELECT COUNT(*) FROM users')->fetchColumn() === 0) {
            self::seedDemoUser($db, 'admin_demo', 'admin');
            self::seedDemoUser($db, 'staff_demo', 'staff');
        }
    }

    private static function migrateUserHistoryReferences(PDO $db): void {
        $needsMigration = false;
        foreach (['tickets', 'activity_logs'] as $table) {
            $columns = $db->query("PRAGMA table_info({$table})")->fetchAll(PDO::FETCH_ASSOC);
            $userColumn = null;
            $hasUsernameSnapshot = false;
            foreach ($columns as $column) {
                if ($column['name'] === 'user_id') {
                    $userColumn = $column;
                }
                if ($column['name'] === 'username_snapshot') {
                    $hasUsernameSnapshot = true;
                }
            }

            $userForeignKey = null;
            foreach ($db->query("PRAGMA foreign_key_list({$table})")->fetchAll(PDO::FETCH_ASSOC) as $foreignKey) {
                if ($foreignKey['table'] === 'users' && $foreignKey['from'] === 'user_id') {
                    $userForeignKey = $foreignKey;
                    break;
                }
            }

            if (!$hasUsernameSnapshot || !$userColumn || (int)$userColumn['notnull'] === 1
                || !$userForeignKey || strtoupper($userForeignKey['on_delete']) !== 'SET NULL') {
                $needsMigration = true;
                break;
            }
        }

        if (!$needsMigration) {
            return;
        }

        $ticketColumns = array_column($db->query('PRAGMA table_info(tickets)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $activityColumns = array_column($db->query('PRAGMA table_info(activity_logs)')->fetchAll(PDO::FETCH_ASSOC), 'name');
        $ticketUsername = in_array('username_snapshot', $ticketColumns, true)
            ? 'COALESCE(t.username_snapshot, u.username)'
            : 'u.username';
        $activityUsername = in_array('username_snapshot', $activityColumns, true)
            ? 'COALESCE(a.username_snapshot, u.username)'
            : 'u.username';

        $db->exec('PRAGMA foreign_keys = OFF');
        try {
            $db->beginTransaction();
            $db->exec("CREATE TABLE tickets_account_migration (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                ticket_number TEXT UNIQUE NOT NULL,
                schedule_id INTEGER,
                user_id INTEGER,
                username_snapshot TEXT,
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
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL,
                FOREIGN KEY (route_id) REFERENCES routes(id),
                FOREIGN KEY (bus_id) REFERENCES buses(id),
                FOREIGN KEY (driver_id) REFERENCES drivers(id)
            )");
            $db->exec("INSERT INTO tickets_account_migration (
                id, ticket_number, schedule_id, user_id, username_snapshot, route_id, bus_id, driver_id,
                shift, trip_date, trip_time, forecast_passengers, actual_passengers, rate, toll, ticket_total,
                trip_status, review_status, remarks, admin_comment, billing_closed, created_at
            ) SELECT t.id, t.ticket_number, t.schedule_id, t.user_id, {$ticketUsername}, t.route_id, t.bus_id,
                     t.driver_id, t.shift, t.trip_date, t.trip_time, t.forecast_passengers, t.actual_passengers,
                     t.rate, t.toll, t.ticket_total, t.trip_status, t.review_status, t.remarks, t.admin_comment,
                     t.billing_closed, t.created_at
              FROM tickets t LEFT JOIN users u ON u.id = t.user_id");
            $db->exec('DROP TABLE tickets');
            $db->exec('ALTER TABLE tickets_account_migration RENAME TO tickets');

            $db->exec("CREATE TABLE activity_logs_account_migration (
                id INTEGER PRIMARY KEY AUTOINCREMENT,
                user_id INTEGER,
                username_snapshot TEXT,
                action_type TEXT NOT NULL,
                ticket_number TEXT,
                details TEXT,
                created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
            )");
            $db->exec("INSERT INTO activity_logs_account_migration (
                id, user_id, username_snapshot, action_type, ticket_number, details, created_at
            ) SELECT a.id, a.user_id, {$activityUsername}, a.action_type, a.ticket_number, a.details, a.created_at
              FROM activity_logs a LEFT JOIN users u ON u.id = a.user_id");
            $db->exec('DROP TABLE activity_logs');
            $db->exec('ALTER TABLE activity_logs_account_migration RENAME TO activity_logs');
            $db->commit();
        } catch (Throwable $e) {
            if ($db->inTransaction()) {
                $db->rollBack();
            }
            throw $e;
        } finally {
            $db->exec('PRAGMA foreign_keys = ON');
        }
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
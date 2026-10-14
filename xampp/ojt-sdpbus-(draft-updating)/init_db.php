<?php
// init_db.php
$dbPath = __DIR__ . '/database/sdpbus.db';

try {
    $db = new PDO('sqlite:' . $dbPath);
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

    // 1. Create users table
    $db->exec("CREATE TABLE IF NOT EXISTS users (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        username TEXT UNIQUE NOT NULL,
        password TEXT NOT NULL,
        role TEXT NOT NULL
    )");

    // Insert default demo accounts
    $stmt = $db->prepare("INSERT OR IGNORE INTO users (username, password, role) VALUES (:username, :password, :role)");
    $stmt->execute([':username' => 'admin', ':password' => password_hash('password123', PASSWORD_BCRYPT), ':role' => 'Administrator']);
    $stmt->execute([':username' => 'staff', ':password' => password_hash('password123', PASSWORD_BCRYPT), ':role' => 'Staff']);

    // 2. Create trip_tickets table in the schema the app expects
    $db->exec("CREATE TABLE IF NOT EXISTS trip_tickets (
        id INTEGER PRIMARY KEY AUTOINCREMENT,
        ticket_number TEXT,
        trip_date TEXT,
        trip_time TEXT,
        route TEXT,
        shift TEXT,
        bus_number TEXT,
        driver_name TEXT,
        actual_passengers INTEGER DEFAULT 0,
        forecast_passengers INTEGER DEFAULT 0,
        status TEXT DEFAULT 'On time',
        rate REAL DEFAULT 0.00,
        created_at DATETIME DEFAULT CURRENT_TIMESTAMP
    )");

    $columns = $db->query("PRAGMA table_info(trip_tickets)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $required = [
        'ticket_number', 'trip_date', 'trip_time', 'route', 'shift', 'bus_number',
        'driver_name', 'actual_passengers', 'forecast_passengers', 'status', 'rate', 'created_at'
    ];

    foreach ($required as $column) {
        if (!in_array($column, $columns, true)) {
            $db->exec("ALTER TABLE trip_tickets ADD COLUMN $column TEXT");
        }
    }

    try {
        $db->exec("CREATE VIEW IF NOT EXISTS trip_ticket AS SELECT * FROM trip_tickets");
    } catch (Throwable $e) {
        // Ignore compatibility view failure if SQLite does not allow it.
    }

    // Seed dummy data if table is empty
    $check = $db->query("SELECT COUNT(*) FROM trip_tickets")->fetchColumn();
    if ($check == 0) {
        $db->exec("INSERT INTO trip_tickets (ticket_number, trip_date, trip_time, route, shift, bus_number, driver_name, actual_passengers, forecast_passengers, status, rate) VALUES
            ('TT-2026-0001', '2026-09-24', '08:10', 'Plant A – Terminal', '7AM Incoming', 'BUS-09', 'R. Santiago', 42, 45, 'On time', 1000.00),
            ('TT-2026-0002', '2026-09-24', '07:45', 'Terminal – Plant B', '7AM Outgoing', 'BUS-12', 'M. Navarro', 37, 40, 'Completed', 1250.00)
        ");
    }

    echo "<h2 style='color:green;'>Database successfully initialized!</h2>";

} catch (PDOException $e) {
    die("<h2 style='color:red;'>Database Setup Failed: " . htmlspecialchars($e->getMessage()) . "</h2>");
}
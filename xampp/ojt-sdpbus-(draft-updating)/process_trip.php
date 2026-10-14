<?php
require_once __DIR__ . '/auth_check.php';

$dbPath = __DIR__ . '/database/sdpbus.db';

function ensureTripTicketsTable(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS trip_tickets (
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

    $columns = $pdo->query("PRAGMA table_info(trip_tickets)")->fetchAll(PDO::FETCH_COLUMN, 1);
    $required = [
        'ticket_number', 'trip_date', 'trip_time', 'route', 'shift', 'bus_number',
        'driver_name', 'actual_passengers', 'forecast_passengers', 'status', 'rate', 'created_at'
    ];

    foreach ($required as $column) {
        if (!in_array($column, $columns, true)) {
            $pdo->exec("ALTER TABLE trip_tickets ADD COLUMN $column TEXT");
        }
    }

    try {
        $pdo->exec("CREATE VIEW IF NOT EXISTS trip_ticket AS SELECT * FROM trip_tickets");
    } catch (Throwable $e) {
        // Ignore compatibility view failure if SQLite does not allow it.
    }
}

try {
    $pdo = new PDO("sqlite:" . $dbPath);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensureTripTicketsTable($pdo);

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $ticketNumber = trim($_POST['ticket_number'] ?? '');

        if (!empty($ticketNumber)) {
            $checkStmt = $pdo->prepare("SELECT COUNT(*) FROM trip_tickets WHERE ticket_number = ?");
            $checkStmt->execute([$ticketNumber]);
            $exists = $checkStmt->fetchColumn();
        } else {
            $exists = 0;
        }

        // Auto-generate sequential ticket number if field was empty or duplicate
        if (empty($ticketNumber) || $exists > 0) {
            $lastId = $pdo->query("SELECT MAX(id) FROM trip_tickets")->fetchColumn() ?: 0;
            $nextNum = $lastId + 1;
            $ticketNumber = 'TT-' . date('Y') . '-' . str_pad($nextNum, 4, '0', STR_PAD_LEFT);
        }

        // --- AUTOMATIC SHIFT CALCULATION BASED ON TRIP TIME ---
        $tripTime = $_POST['trip_time'] ?? date('H:i');
        $shift = '7AM Incoming';

        if (!empty($tripTime)) {
            $hour = (int) explode(':', $tripTime)[0];

            if ($hour < 12) {
                // Morning / AM Hours (00:00 - 11:59)
                $shift = '7AM Incoming';
            } elseif ($hour >= 12 && $hour < 16) {
                // Afternoon Hours (12:00 - 15:59)
                $shift = '3PM Outgoing';
            } elseif ($hour >= 16 && $hour < 20) {
                // Late Afternoon / Evening (16:00 - 19:59)
                $shift = '7PM Outgoing';
            } else {
                // Late Night Hours (20:00 - 23:59)
                $shift = '7PM Outgoing';
            }
        }

        $stmt = $pdo->prepare("INSERT INTO trip_tickets 
            (ticket_number, trip_date, trip_time, route, shift, bus_number, driver_name, actual_passengers, forecast_passengers, status, rate)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");

        $stmt->execute([
            $ticketNumber,
            $_POST['trip_date'] ?? date('Y-m-d'),
            $tripTime,
            $_POST['route'] ?? '',
            $shift, // Auto-calculated shift value inserted here
            $_POST['bus_number'] ?? '',
            $_POST['driver_name'] ?? '',
            (int)($_POST['actual_passengers'] ?? 0),
            (int)($_POST['forecast_passengers'] ?? 0),
            $_POST['status'] ?? 'On time',
            (float)($_POST['rate'] ?? 0.00)
        ]);

        header("Location: dispatch_log.php?status=success");
        exit;
    }
} catch (PDOException $e) {
    die("Error saving trip ticket: " . htmlspecialchars($e->getMessage()));
}
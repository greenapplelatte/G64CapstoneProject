<?php
require_once __DIR__ . '/auth_check.php';
include 'includes/header.php';

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

// Database Connection & Safety Fallback
try {
    $db = new PDO('sqlite:' . __DIR__ . '/database/sdpbus.db');
    $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    ensureTripTicketsTable($db);
    $stmt = $db->query("SELECT * FROM trip_tickets ORDER BY id DESC");
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);

    try {
        $busStmt = $db->query("SELECT bus_id, bus_code FROM buses ORDER BY bus_id ASC");
        $buses = $busStmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (PDOException $e) {
        $buses = [];
    }

    // Auto-generate next ticket number based on latest MAX(id)
    $lastId = $db->query("SELECT MAX(id) FROM trip_tickets")->fetchColumn() ?: 0;
    $nextTicketNum = 'TK-' . date('Y') . '-' . str_pad($lastId + 1, 4, '0', STR_PAD_LEFT);

} catch (PDOException $e) {
    $tickets = []; // Fallback to empty array if table error occurs
    $buses = [];
    $nextTicketNum = 'TK-' . date('Y') . '-0001';
}

// Dynamic calculations using correct database column 'rate_amount'
$totalTripsToday = count($tickets);
$dailySubtotal = array_sum(array_column($tickets, 'rate'));
?>
<link rel="stylesheet" href="assets/css/dispatch_log.css">

<div class="app-container">
    <?php include 'includes/sidebar.php'; ?>

    <div class="main-wrapper">
        <?php include 'includes/topbar.php'; ?>

        <main class="main-content" style="padding: 1.5rem;">
            <!-- Header Section -->
            <div class="dispatch-header-bar">
                <div>
                    <div class="breadcrumb-tag">SDPBUS WORKSPACE</div>
                    <h1 class="page-title">Trip Dispatch Log</h1>
                    <p class="page-subtitle">Create, review, and reconcile daily trip tickets.</p>
                </div>
                <button class="btn-add-ticket" id="openAddModalBtn">
                    <span>+</span> Add trip ticket
                </button>
            </div>

            <!-- Top Metric Cards -->
            <div class="dispatch-metrics-grid">
                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">TRIPS TODAY</span>
                        <div class="card-icon">🚌</div>
                    </div>
                    <div class="card-value"><?= $totalTripsToday ?></div>
                    <div class="card-sub">Recorded tickets</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">DAILY SUBTOTAL</span>
                        <div class="card-icon">💳</div>
                    </div>
                    <div class="card-value">₱<?= number_format($dailySubtotal, 2) ?></div>
                    <div class="card-sub">Auto-calculated</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">PASSENGER VARIANCE</span>
                        <div class="card-icon warning">⚠️</div>
                    </div>
                    <div class="card-value">2</div>
                    <div class="card-sub">Requires checking</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">NEXT TICKET</span>
                        <div class="card-icon">🎟️</div>
                    </div>
                    <div class="card-value">TK-<?= date('Y') ?>-<?= str_pad($totalTripsToday + 1, 4, '0', STR_PAD_LEFT) ?></div>
                    <div class="card-sub">Sequence auto-generated</div>
                </div>
            </div>

            <!-- Dispatch Table -->
            <div class="dispatch-table-card">
                <div class="table-responsive">
                    <table class="dispatch-data-table">
                        <thead>
                            <tr>
                                <th>TICKET</th>
                                <th>ROUTE &amp; SHIFT</th>
                                <th>BUS / DRIVER</th>
                                <th>PASSENGERS</th>
                                <th>STATUS</th>
                                <th>RATE</th>
                                <th>ACTIONS</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if (empty($tickets)): ?>
                                <tr>
                                    <td colspan="7" style="text-align:center; padding: 2rem; color: #6b7280;">No trip tickets found. Click "+ Add trip ticket" to create one.</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach ($tickets as $row): ?>
    <tr>
        <td>
            <div class="ticket-code"><?= htmlspecialchars($row['ticket_number'] ?? 'N/A') ?></div>
            <div class="ticket-date">
                <?= !empty($row['trip_date']) ? date('d M Y', strtotime($row['trip_date'])) : '' ?>
                &middot; 
                <?= !empty($row['trip_time']) ? date('h:i A', strtotime($row['trip_time'])) : '' ?>
            </div>
        </td>
        <td>
            <div class="route-title"><?= htmlspecialchars($row['route'] ?? 'N/A') ?></div>
            <div class="shift-sub"><?= htmlspecialchars($row['shift'] ?? 'N/A') ?></div>
        </td>
        <td>
            <div class="bus-id"><?= htmlspecialchars($row['bus_number'] ?? 'N/A') ?></div>
            <div class="driver-name"><?= htmlspecialchars($row['driver_name'] ?? 'N/A') ?></div>
        </td>
        <td>
            <div class="pax-val"><?= htmlspecialchars($row['actual_passengers'] ?? 0) ?> / <?= htmlspecialchars($row['forecast_passengers'] ?? 0) ?></div>
            <div class="pax-sub">actual / forecast</div>
        </td>
        <td>
            <?php 
                $status = $row['status'] ?? 'On time';
                $statusClass = 'info';
                if (strtolower($status) === 'completed') $statusClass = 'success';
                if (strtolower($status) === 'delayed') $statusClass = 'warning';
            ?>
            <span class="status-pill <?= $statusClass ?>"><?= htmlspecialchars($status) ?></span>
        </td>
        <td class="rate-amount">₱<?= number_format((float)($row['rate'] ?? 0), 2) ?></td>
        <td>
            <div class="action-links">
        <a href="#" class="link-edit" 
           data-id="<?= $row['id'] ?>"
           data-ticket="<?= htmlspecialchars($row['ticket_number'] ?? '') ?>"
           data-date="<?= htmlspecialchars($row['trip_date'] ?? '') ?>"
           data-time="<?= htmlspecialchars($row['trip_time'] ?? '') ?>"
           data-route="<?= htmlspecialchars($row['route'] ?? '') ?>"
           data-shift="<?= htmlspecialchars($row['shift'] ?? '') ?>"
           data-bus="<?= htmlspecialchars($row['bus_number'] ?? '') ?>"
           data-driver="<?= htmlspecialchars($row['driver_name'] ?? '') ?>"
           data-actual="<?= htmlspecialchars($row['actual_passengers'] ?? 0) ?>"
           data-forecast="<?= htmlspecialchars($row['forecast_passengers'] ?? 0) ?>"
           data-status="<?= htmlspecialchars($row['status'] ?? 'On time') ?>"
           data-rate="<?= htmlspecialchars($row['rate'] ?? 0) ?>">Edit</a>
        
        <a href="delete_trip.php?id=<?= $row['id'] ?>" 
           class="link-delete" 
           onclick="return confirm('Are you sure you want to delete ticket <?= htmlspecialchars($row['ticket_number']) ?>?');">Delete</a>
    </div>
        </td>
    </tr>
<?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </main>
    </div>
</div>

<!-- Add Trip Ticket Modal -->
<div id="addTicketModal" class="modal-overlay" style="display: none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Add Trip Ticket</h3>
            <button type="button" class="close-modal-btn" id="closeModalBtn">&times;</button>
        </div>
        <form action="process_trip.php" method="POST" class="modal-body-form">
            <div class="form-grid">
                <!-- Ticket Number Field -->
                <div class="form-group full-width">
                    <label>Ticket Number (Auto-Generated)</label>
                    <input type="text" class="form-control" name="ticket_number" 
                        value="<?php echo htmlspecialchars($nextTicketNum); ?>" 
                        readonly style="background-color: #e9ecef; cursor: not-allowed;">
                </div>

                <!-- Single Trip Date Field (Auto-Synced & Editable) -->
                <div class="form-group">
                    <label for="add_trip_date">Trip Date</label>
                    <input 
                        type="date" 
                        id="add_trip_date" 
                        name="trip_date" 
                        class="form-control" 
                        value="<?php echo date('Y-m-d'); ?>" 
                        required>
                </div>
                <div class="form-group">
                    <label>Trip Time</label>
                    <input type="time" name="trip_time" required>
                </div>
                <div class="form-group">
                    <label>Route</label>
                    <input type="text" name="route" required>
                </div>
                
                <div class="form-group">
                    <label for="add_bus_number">Bus Code/ID</label>
                    <select id="add_bus_number" name="bus_number" required>
                        <option value="" selected disabled>Select a bus</option>
                        <?php foreach ($buses as $bus): ?>
                            <option value="<?= htmlspecialchars($bus['bus_code']) ?>">
                                <?= htmlspecialchars($bus['bus_code']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" required>
                </div>
                <div class="form-group">
                    <label>Actual Passengers</label>
                    <input type="number" name="actual_passengers" min="0" required>
                </div>
                <div class="form-group">
                    <label>Forecast Passengers</label>
                    <input type="number" name="forecast_passengers" min="0" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status">
                        <option value="On time">On time</option>
                        <option value="Completed">Completed</option>
                        <option value="Delayed">Delayed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rate (₱)</label>
                    <input type="number" step="0.01" name="rate" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelModalBtn">Cancel</button>
                <button type="submit" class="btn-save">Add Trip Ticket</button>
            </div>
        </form>
    </div>
</div>

<!-- Edit Trip Ticket Modal -->
<div id="editTicketModal" class="modal-overlay" style="display:none;">
    <div class="modal-container">
        <div class="modal-header">
            <h3>Edit Trip Ticket</h3>
            <button type="button" class="close-modal-btn" id="closeEditModalBtn">&times;</button>
        </div>
        <form action="edit_trip.php" method="POST" class="modal-body-form">
            <input type="hidden" name="id" id="edit_id">
            <div class="form-grid">
                <!-- Inside #editTicketModal -->
                     <div class="form-group">
                        <label>Ticket Number</label>
                        <input type="text" class="form-control" name="ticket_number" id="edit_ticket_number" 
                        readonly style="background-color: #e9ecef; cursor: not-allowed;">
             </div>
                <div class="form-group">
                    <label>Trip Date</label>
                    <input type="date" name="trip_date" id="edit_trip_date" required>
                </div>
                <div class="form-group">
                    <label>Trip Time</label>
                    <input type="time" name="trip_time" id="edit_trip_time" required>
                </div>
                <div class="form-group">
                    <label>Route</label>
                    <input type="text" name="route" id="edit_route" required>
                </div>
                <div class="form-group">
                    <label>Shift</label>
                    <select name="shift" id="edit_shift" required>
                        <option value="7AM Incoming">7AM Incoming</option>
                        <option value="3PM Outgoing">3PM Outgoing</option>
                        <option value="7PM Incoming">7PM Incoming</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Bus Code/ID</label>
                    <input type="text" name="bus_number" id="edit_bus_number" required>
                </div>
                <div class="form-group">
                    <label>Driver Name</label>
                    <input type="text" name="driver_name" id="edit_driver_name" required>
                </div>
                <div class="form-group">
                    <label>Actual Passengers</label>
                    <input type="number" name="actual_passengers" id="edit_actual_passengers" min="0" required>
                </div>
                <div class="form-group">
                    <label>Forecast Passengers</label>
                    <input type="number" name="forecast_passengers" id="edit_forecast_passengers" min="0" required>
                </div>
                <div class="form-group">
                    <label>Status</label>
                    <select name="status" id="edit_status">
                        <option value="On time">On time</option>
                        <option value="Completed">Completed</option>
                        <option value="Delayed">Delayed</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Rate (₱)</label>
                    <input type="number" step="0.01" name="rate" id="edit_rate" required>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" id="cancelEditModalBtn">Cancel</button>
                <button type="submit" class="btn-save">Update Trip Ticket</button>
            </div>
        </form>
    </div>
</div>

    <!-- JavaScript scripts -->
    <script src="assets/js/dispatch_log.js"></script>
</body>
</html>
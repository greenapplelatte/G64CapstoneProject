<?php
// frontend/staff/new_ticket.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$db = Database::getConnection();

// Fetch options for dropdowns
$routes = $db->query("SELECT id, route_name, rate, toll FROM routes WHERE status = 'ACTIVE'")->fetchAll();
$buses = $db->query("SELECT id, bus_code, capacity FROM buses WHERE status = 'ACTIVE'")->fetchAll();
$drivers = $db->query("SELECT id, driver_name FROM drivers WHERE status = 'ACTIVE'")->fetchAll();
$driverAssignments = $db->query("SELECT driver_id, bus_id, route_id FROM driver_assignments WHERE status = 'ACTIVE'")->fetchAll(PDO::FETCH_ASSOC);

// Pre-fill parameters if arriving from todays_trips.php or editing an existing ticket
$editTicket = null;
if (isset($_GET['edit_id'])) {
    $stmt = $db->prepare("SELECT * FROM tickets WHERE id = :id AND user_id = :uid");
    $stmt->execute([':id' => (int)$_GET['edit_id'], ':uid' => $_SESSION['user_id']]);
    $editTicket = $stmt->fetch();
}

$preRoute = $_GET['route_id'] ?? $editTicket['route_id'] ?? '';
$preBus = $_GET['bus_id'] ?? $editTicket['bus_id'] ?? '';
$preDriver = $_GET['driver_id'] ?? $editTicket['driver_id'] ?? '';
$preForecast = $_GET['forecast'] ?? $editTicket['forecast_passengers'] ?? 0;
$preScheduleId = $_GET['schedule_id'] ?? $editTicket['schedule_id'] ?? '';

if (!$editTicket && (int)$preScheduleId > 0) {
    $scheduledAssignmentStmt = $db->prepare('SELECT route_id, bus_id, driver_id FROM trip_schedules WHERE id = :id');
    $scheduledAssignmentStmt->execute([':id' => (int)$preScheduleId]);
    $scheduledAssignment = $scheduledAssignmentStmt->fetch(PDO::FETCH_ASSOC);
    if ($scheduledAssignment && $scheduledAssignment['bus_id'] && $scheduledAssignment['driver_id']) {
        $driverAssignments[] = $scheduledAssignment;
    }
}

$driverAssignmentsJson = json_encode($driverAssignments, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT);

$pageTitle = $editTicket ? "Edit Trip Ticket" : "Record New Trip Ticket";
$extraScript = "ticket-form.js";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content" data-driver-assignments="<?= htmlspecialchars($driverAssignmentsJson ?: '[]', ENT_QUOTES, 'UTF-8') ?>">
    <div class="top-bar">
        <h1><?= $editTicket ? "Edit Ticket: " . htmlspecialchars($editTicket['ticket_number']) : "Record New Trip Ticket" ?></h1>
        <a href="/xampp/yoko-na/ojt-sdpbus/frontend/staff/my_tickets.php" class="btn btn-secondary">← Back to My Tickets</a>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>

    <div class="card form-container">
                    <form action="<?= $editTicket ? '/xampp/yoko-na/ojt-sdpbus/backend/tickets/update_ticket.php' : '/xampp/yoko-na/ojt-sdpbus/backend/tickets/create_ticket.php' ?>" method="POST">
            <?php if ($editTicket): ?>
                <input type="hidden" name="ticket_id" value="<?= $editTicket['id'] ?>">
            <?php endif; ?>
            <input type="hidden" name="schedule_id" value="<?= htmlspecialchars((string)$preScheduleId) ?>">

            <div class="form-grid">
                <!-- Route Selection -->
                <div class="form-group">
                    <label for="route_id">Route *</label>
                    <select name="route_id" id="route_id" required <?= $editTicket ? 'disabled' : '' ?>>
                        <option value="">-- Select Route --</option>
                        <?php foreach ($routes as $route): ?>
                            <option value="<?= $route['id'] ?>" 
                                    data-rate="<?= $route['rate'] ?>" 
                                    data-toll="<?= $route['toll'] ?>"
                                    <?= $preRoute == $route['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($route['route_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Bus Selection -->
                <div class="form-group">
                    <label for="bus_id">Bus Code *</label>
                    <select name="bus_id" id="bus_id" required <?= $editTicket ? 'disabled' : '' ?>>
                        <option value="">-- Select Bus --</option>
                        <?php foreach ($buses as $bus): ?>
                            <option value="<?= $bus['id'] ?>" <?= $preBus == $bus['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($bus['bus_code']) ?> (Cap: <?= $bus['capacity'] ?>)
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>

                <!-- Driver Selection -->
                <div class="form-group">
                    <label for="driver_id">Driver Name *</label>
                    <select name="driver_id" id="driver_id" required <?= $editTicket ? 'disabled' : '' ?>>
                        <option value="">-- Select Driver --</option>
                        <?php foreach ($drivers as $driver): ?>
                            <option value="<?= $driver['id'] ?>" <?= $preDriver == $driver['id'] ? 'selected' : '' ?>>
                                <?= htmlspecialchars($driver['driver_name']) ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <?php if (!$editTicket): ?><small id="assignment-help" class="text-muted">Only configured driver, bus, and route combinations are available.</small><?php endif; ?>
                </div>

                <!-- Date & Time -->
                <div class="form-group">
                    <label for="trip_date">Trip Date *</label>
                    <input type="date" id="trip_date" name="trip_date" value="<?= $editTicket['trip_date'] ?? date('Y-m-d') ?>" required <?= $editTicket ? 'readonly' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="trip_time">Trip Time *</label>
                    <input type="time" id="trip_time" name="trip_time" value="<?= $editTicket['trip_time'] ?? date('H:i') ?>" required>
                </div>

                <!-- Passenger Counts -->
                <div class="form-group">
                    <label for="forecast_passengers">Forecast Passengers</label>
                    <input type="number" id="forecast_passengers" name="forecast_passengers" min="0" value="<?= htmlspecialchars((string)$preForecast) ?>" <?= $editTicket ? 'readonly' : '' ?>>
                </div>

                <div class="form-group">
                    <label for="actual_passengers">Actual Passengers *</label>
                    <input type="number" id="actual_passengers" name="actual_passengers" min="0" value="<?= $editTicket['actual_passengers'] ?? 0 ?>" required>
                </div>

                <!-- Financial Auto-computations -->
                <div class="form-group">
                    <label>Rate (₱)</label>
                    <input type="text" id="rate" name="rate" value="<?= number_format((float)($editTicket['rate'] ?? 0), 2) ?>" readonly class="input-readonly">
                </div>

                <div class="form-group">
                    <label>Toll (₱)</label>
                    <input type="text" id="toll" name="toll" value="<?= number_format((float)($editTicket['toll'] ?? 0), 2) ?>" readonly class="input-readonly">
                </div>

                <div class="form-group">
                    <label>Total Fare (₱)</label>
                    <input type="text" id="ticket_total" name="ticket_total" value="<?= number_format((float)($editTicket['ticket_total'] ?? 0), 2) ?>" readonly class="input-readonly text-bold">
                </div>

                <!-- Status & Remarks -->
                <div class="form-group">
                    <label for="trip_status">Trip Status</label>
                    <select name="trip_status" id="trip_status">
                        <option value="On time" <?= ($editTicket['trip_status'] ?? '') === 'On time' ? 'selected' : '' ?>>On time</option>
                        <option value="Delayed" <?= ($editTicket['trip_status'] ?? '') === 'Delayed' ? 'selected' : '' ?>>Delayed</option>
                        <option value="Completed" <?= ($editTicket['trip_status'] ?? '') === 'Completed' ? 'selected' : '' ?>>Completed</option>
                    </select>
                </div>
            </div>

            <!-- Variance Warning Container -->
            <div id="variance_warning" class="alert alert-warning margin-top" style="display: none;"></div>

            <div class="form-group margin-top">
                <label for="remarks">Staff Remarks / Notes</label>
                <textarea id="remarks" name="remarks" rows="3" placeholder="Add optional remarks..."><?= htmlspecialchars($editTicket['remarks'] ?? '') ?></textarea>
            </div>

            <div class="form-actions margin-top">
                <button type="submit" name="action" value="draft" class="btn btn-secondary">💾 Save as Draft</button>
                <button type="submit" name="action" value="submit" class="btn btn-primary">🚀 Submit for Review</button>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
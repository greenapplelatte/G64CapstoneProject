<?php
// frontend/admin/trip_schedule.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$selectedDate = sanitize($_GET['date'] ?? date('Y-m-d'));

// Master options
$routes = $db->query("SELECT id, route_name FROM routes WHERE status = 'ACTIVE'")->fetchAll();
$buses = $db->query("SELECT id, bus_code FROM buses WHERE status = 'ACTIVE'")->fetchAll();
$drivers = $db->query("SELECT id, driver_name FROM drivers WHERE status = 'ACTIVE'")->fetchAll();

// Existing Schedules for Date
$stmt = $db->prepare("SELECT * FROM trip_schedules WHERE schedule_date = :date");
$stmt->execute([':date' => $selectedDate]);
$schedulesRaw = $stmt->fetchAll();

$schedulesMap = [];
foreach ($schedulesRaw as $s) {
    $schedulesMap[$s['shift'] . '_' . $s['route_id']] = $s;
}

$shifts = ['7AM Incoming', '3PM Outgoing', '7PM Outgoing'];

$pageTitle = "Trip Schedule Planning - SDPBUS";
$extraScript = "schedule.js";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Daily Trip Schedule Planning</h1>
        <form method="GET" action="" style="display: flex; gap: 10px; align-items: center;">
            <label for="date" style="font-weight: 600;">Date:</label>
            <input type="date" id="date" name="date" value="<?= htmlspecialchars($selectedDate) ?>" onchange="this.form.submit()">
        </form>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <div class="card margin-top">
        <form action="/ojt-sdpbus/backend/schedule/save_schedule.php" method="POST">
            <input type="hidden" name="schedule_date" value="<?= htmlspecialchars($selectedDate) ?>">

            <table class="table schedule-table">
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Route</th>
                        <th>Assigned Bus</th>
                        <th>Assigned Driver</th>
                        <th>Forecast Passengers</th>
                    </tr>
                </thead>
                <tbody>
                    <?php $index = 0; ?>
                    <?php foreach ($shifts as $shift): ?>
                        <?php foreach ($routes as $route): ?>
                            <?php 
                                $key = $shift . '_' . $route['id'];
                                $sched = $schedulesMap[$key] ?? null;
                            ?>
                            <tr class="schedule-row">
                                <td>
                                    <strong><?= htmlspecialchars($shift) ?></strong>
                                    <input type="hidden" name="schedules[<?= $index ?>][shift]" class="select-shift" value="<?= htmlspecialchars($shift) ?>">
                                    <input type="hidden" name="schedules[<?= $index ?>][route_id]" value="<?= $route['id'] ?>">
                                </td>
                                <td><?= htmlspecialchars($route['route_name']) ?></td>
                                <td>
                                    <select name="schedules[<?= $index ?>][bus_id]" class="select-bus">
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($buses as $b): ?>
                                            <option value="<?= $b['id'] ?>" <?= ($sched && $sched['bus_id'] == $b['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($b['bus_code']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <div class="conflict-alert alert-danger" style="display:none; font-size:0.75rem; padding:2px 6px; margin-top:4px;"></div>
                                </td>
                                <td>
                                    <select name="schedules[<?= $index ?>][driver_id]" class="select-driver">
                                        <option value="">-- Unassigned --</option>
                                        <?php foreach ($drivers as $d): ?>
                                            <option value="<?= $d['id'] ?>" <?= ($sched && $sched['driver_id'] == $d['id']) ? 'selected' : '' ?>>
                                                <?= htmlspecialchars($d['driver_name']) ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td>
                                    <input type="number" name="schedules[<?= $index ?>][forecast_passengers]" min="0" value="<?= $sched['forecast_passengers'] ?? 0 ?>" style="width: 90px;">
                                </td>
                            </tr>
                            <?php $index++; ?>
                        <?php endforeach; ?>
                    <?php endforeach; ?>
                </tbody>
            </table>

            <div class="form-actions margin-top">
                <button type="submit" id="saveScheduleBtn" class="btn btn-primary">💾 Save All Schedule Assignments</button>
            </div>
        </form>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
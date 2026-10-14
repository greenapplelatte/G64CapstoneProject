<?php
// frontend/staff/todays_trips.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['staff', 'admin']);

$db = Database::getConnection();
$today = date('Y-m-d');

// Query today's scheduled trips
$stmt = $db->prepare("SELECT ts.id, ts.shift, ts.forecast_passengers,
                             r.id as route_id, r.route_name, r.rate, r.toll,
                             b.id as bus_id, b.bus_code,
                             d.id as driver_id, d.driver_name
                      FROM trip_schedules ts
                      JOIN routes r ON ts.route_id = r.id
                      LEFT JOIN buses b ON ts.bus_id = b.id
                      LEFT JOIN drivers d ON ts.driver_id = d.id
                      WHERE ts.schedule_date = :today
                      ORDER BY ts.shift ASC");
$stmt->execute([':today' => $today]);
$schedules = $stmt->fetchAll();

$pageTitle = "Today's Planned Trips - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Today's Planned Trips</h1>
        <span>Date: <strong><?= date('F j, Y') ?></strong></span>
    </div>

    <div class="card">
        <?php if (empty($schedules)): ?>
            <p class="empty-msg">No trip schedules published for today yet.</p>
        <?php else: ?>
            <table class="table">
                <thead>
                    <tr>
                        <th>Shift</th>
                        <th>Route</th>
                        <th>Assigned Bus</th>
                        <th>Assigned Driver</th>
                        <th>Forecast Passengers</th>
                        <th>Action</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($schedules as $sched): ?>
                        <tr>
                            <td><span class="badge badge-info"><?= htmlspecialchars($sched['shift']) ?></span></td>
                            <td><strong><?= htmlspecialchars($sched['route_name']) ?></strong></td>
                            <td><?= htmlspecialchars($sched['bus_code'] ?? 'Unassigned') ?></td>
                            <td><?= htmlspecialchars($sched['driver_name'] ?? 'Unassigned') ?></td>
                            <td><?= htmlspecialchars((string)$sched['forecast_passengers']) ?></td>
                            <td>
                                <a href="/ojt-sdpbus/frontend/staff/new_ticket.php?schedule_id=<?= $sched['id'] ?>&route_id=<?= $sched['route_id'] ?>&bus_id=<?= $sched['bus_id'] ?>&driver_id=<?= $sched['driver_id'] ?>&forecast=<?= $sched['forecast_passengers'] ?>"
                                   class="btn btn-sm btn-primary">
                                   📝 Record Ticket
                                </a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<?php
// frontend/admin/master_data.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$activeTab = sanitize($_GET['tab'] ?? 'routes');

$routes = $db->query("SELECT * FROM routes ORDER BY route_name ASC")->fetchAll();
$buses = $db->query("SELECT * FROM buses ORDER BY bus_code ASC")->fetchAll();
$drivers = $db->query("SELECT * FROM drivers ORDER BY driver_name ASC")->fetchAll();

$pageTitle = "Master Data Management - SDPBUS";
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content">
    <div class="top-bar">
        <h1>Master Data Management</h1>
    </div>

    <?php if (isset($_SESSION['error'])): ?>
        <div class="alert alert-danger alert-dismissible"><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></div>
    <?php endif; ?>
    <?php if (isset($_SESSION['success'])): ?>
        <div class="alert alert-success alert-dismissible"><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></div>
    <?php endif; ?>

    <!-- Master Data Navigation Tabs -->
    <div class="filter-bar">
        <a href="?tab=routes" class="btn-filter <?= $activeTab === 'routes' ? 'active' : '' ?>">Routes</a>
        <a href="?tab=buses" class="btn-filter <?= $activeTab === 'buses' ? 'active' : '' ?>">Buses</a>
        <a href="?tab=drivers" class="btn-filter <?= $activeTab === 'drivers' ? 'active' : '' ?>">Drivers</a>
    </div>

    <!-- TAB 1: ROUTES -->
    <?php if ($activeTab === 'routes'): ?>
        <div class="card margin-top">
            <h3>Add New Route</h3>
            <form action="/ojt-sdpbus/backend/master_data/manage_routes.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end; flex-wrap: wrap;">
                <input type="hidden" name="action" value="create">
                <div>
                    <label>Route Name *</label>
                    <input type="text" name="route_name" required placeholder="e.g. Route A - Main Line">
                </div>
                <div>
                    <label>Rate (₱)</label>
                    <input type="number" step="0.01" name="rate" value="0.00" required style="width: 110px;">
                </div>
                <div>
                    <label>Toll (₱)</label>
                    <input type="number" step="0.01" name="toll" value="0.00" required style="width: 110px;">
                </div>
                <button type="submit" class="btn btn-primary">Add Route</button>
            </form>
        </div>

        <div class="card margin-top">
            <h3>Existing Routes</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Route Name</th>
                        <th>Rate</th>
                        <th>Toll</th>
                        <th>Total Fee</th>
                        <th>Status</th>
                        <th>Toggle Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($routes as $r): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($r['route_name']) ?></strong></td>
                            <td>₱<?= number_format((float)$r['rate'], 2) ?></td>
                            <td>₱<?= number_format((float)$r['toll'], 2) ?></td>
                            <td>₱<?= number_format((float)$r['rate'] + (float)$r['toll'], 2) ?></td>
                            <td><span class="badge badge-<?= strtolower($r['status']) ?>"><?= $r['status'] ?></span></td>
                            <td>
                                <form action="/ojt-sdpbus/backend/master_data/manage_routes.php" method="POST">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="route_id" value="<?= $r['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $r['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline"><?= $r['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <!-- TAB 2: BUSES -->
    <?php elseif ($activeTab === 'buses'): ?>
        <div class="card margin-top">
            <h3>Add New Bus</h3>
            <form action="/ojt-sdpbus/backend/master_data/manage_buses.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                <input type="hidden" name="action" value="create">
                <div>
                    <label>Bus Code *</label>
                    <input type="text" name="bus_code" required placeholder="e.g. BUS-101">
                </div>
                <div>
                    <label>Seating Capacity</label>
                    <input type="number" name="capacity" value="40" required style="width: 100px;">
                </div>
                <button type="submit" class="btn btn-primary">Add Bus</button>
            </form>
        </div>

        <div class="card margin-top">
            <h3>Bus Fleet List</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Bus Code</th>
                        <th>Capacity</th>
                        <th>Status</th>
                        <th>Toggle Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($buses as $b): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($b['bus_code']) ?></strong></td>
                            <td><?= $b['capacity'] ?> seats</td>
                            <td><span class="badge badge-<?= strtolower($b['status']) ?>"><?= $b['status'] ?></span></td>
                            <td>
                                <form action="/ojt-sdpbus/backend/master_data/manage_buses.php" method="POST">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="bus_id" value="<?= $b['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $b['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline"><?= $b['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

    <!-- TAB 3: DRIVERS -->
    <?php elseif ($activeTab === 'drivers'): ?>
        <div class="card margin-top">
            <h3>Add New Driver</h3>
            <form action="/ojt-sdpbus/backend/master_data/manage_drivers.php" method="POST" style="display: flex; gap: 10px; align-items: flex-end;">
                <input type="hidden" name="action" value="create">
                <div>
                    <label>Driver Full Name *</label>
                    <input type="text" name="driver_name" required placeholder="e.g. Juan Dela Cruz">
                </div>
                <button type="submit" class="btn btn-primary">Add Driver</button>
            </form>
        </div>

        <div class="card margin-top">
            <h3>Driver Roster</h3>
            <table class="table">
                <thead>
                    <tr>
                        <th>Driver Name</th>
                        <th>Status</th>
                        <th>Toggle Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($drivers as $d): ?>
                        <tr>
                            <td><strong><?= htmlspecialchars($d['driver_name']) ?></strong></td>
                            <td><span class="badge badge-<?= strtolower($d['status']) ?>"><?= $d['status'] ?></span></td>
                            <td>
                                <form action="/ojt-sdpbus/backend/master_data/manage_drivers.php" method="POST">
                                    <input type="hidden" name="action" value="toggle_status">
                                    <input type="hidden" name="driver_id" value="<?= $d['id'] ?>">
                                    <input type="hidden" name="status" value="<?= $d['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                                    <button type="submit" class="btn btn-sm btn-outline"><?= $d['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</main>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
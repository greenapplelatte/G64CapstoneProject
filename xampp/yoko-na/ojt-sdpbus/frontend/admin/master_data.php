<?php
// frontend/admin/master_data.php
require_once __DIR__ . '/../../includes/auth_check.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

require_auth(['admin']);

$db = Database::getConnection();
$activeTab = strtolower(sanitize($_GET['tab'] ?? 'routes'));
if (!in_array($activeTab, ['routes', 'buses', 'drivers', 'assignments'], true)) {
    $activeTab = 'routes';
}

$routes = $db->query("SELECT * FROM routes ORDER BY route_name ASC")->fetchAll();
$buses = $db->query("SELECT * FROM buses ORDER BY bus_code ASC")->fetchAll();
$drivers = $db->query("SELECT * FROM drivers ORDER BY driver_name ASC")->fetchAll();
$assignments = $db->query("SELECT da.id, da.driver_id, da.bus_id, da.route_id, da.status,
                                  d.driver_name, b.bus_code, r.route_name
                           FROM driver_assignments da
                           JOIN drivers d ON d.id = da.driver_id
                           JOIN buses b ON b.id = da.bus_id
                           JOIN routes r ON r.id = da.route_id
                           ORDER BY d.driver_name, b.bus_code, r.route_name")->fetchAll();

$masterTabs = [
    'routes' => ['label' => 'Routes', 'icon' => 'fa-route', 'items' => $routes, 'count' => count($routes)],
    'buses' => ['label' => 'Buses', 'icon' => 'fa-bus', 'items' => $buses, 'count' => count($buses)],
    'drivers' => ['label' => 'Drivers', 'icon' => 'fa-user', 'items' => $drivers, 'count' => count($drivers)],
    'assignments' => ['label' => 'Assignments', 'icon' => 'fa-link', 'items' => $assignments, 'count' => count($assignments)]
];
$currentItems = $masterTabs[$activeTab]['items'];
$activeCount = count(array_filter($currentItems, static fn($item) => $item['status'] === 'ACTIVE'));
$inactiveCount = count($currentItems) - $activeCount;
$thirdMetric = match ($activeTab) {
    'routes' => [
        'label' => 'Highest total fee',
        'value' => '₱' . number_format(max(array_map(static fn($route) => (float)$route['rate'] + (float)$route['toll'], $routes) ?: [0]), 2),
        'icon' => 'fa-tag',
        'tone' => 'blue'
    ],
    'buses' => [
        'label' => 'Total seating capacity',
        'value' => number_format(array_sum(array_map(static fn($bus) => (int)$bus['capacity'], $buses)), 0),
        'icon' => 'fa-chair',
        'tone' => 'blue'
    ],
    'assignments' => [
        'label' => 'Assigned drivers',
        'value' => number_format(count(array_unique(array_column(array_filter($assignments, static fn($assignment) => $assignment['status'] === 'ACTIVE'), 'driver_id')))),
        'icon' => 'fa-users',
        'tone' => 'blue'
    ],
    default => [
        'label' => 'Total drivers',
        'value' => number_format(count($drivers)),
        'icon' => 'fa-users',
        'tone' => 'blue'
    ]
};

$pageTitle = "Master Data Management - SDPBUS";
$extraCss = 'master-data.css';
$extraScript = 'master-data.js';
include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';
?>

<main class="main-content master-data-page">
    <div class="master-data-content">
        <header class="master-page-header">
            <h1>Master Data</h1>
            <p>Routes, buses and drivers used in schedules and tickets.</p>
        </header>
        <nav class="master-tabs" aria-label="Master data types">
            <?php foreach ($masterTabs as $tabKey => $tab): ?>
                <a href="?tab=<?= $tabKey ?>" class="master-tab <?= $activeTab === $tabKey ? 'is-active' : '' ?>" <?= $activeTab === $tabKey ? 'aria-current="page"' : '' ?>>
                    <i class="fas <?= $tab['icon'] ?>" aria-hidden="true"></i>
                    <span><?= $tab['label'] ?></span>
                    <small><?= $tab['count'] ?></small>
                </a>
            <?php endforeach; ?>
        </nav>

        <section class="master-summary" aria-label="<?= htmlspecialchars($masterTabs[$activeTab]['label']) ?> summary">
            <article class="master-stat">
                <span class="master-stat-icon stat-green"><i class="fas <?= $masterTabs[$activeTab]['icon'] ?>" aria-hidden="true"></i></span>
                <span><strong><?= $activeCount ?></strong><small>Active <?= strtolower($masterTabs[$activeTab]['label']) ?></small></span>
            </article>
            <article class="master-stat">
                <span class="master-stat-icon stat-gray"><i class="fas fa-pause-circle" aria-hidden="true"></i></span>
                <span><strong><?= $inactiveCount ?></strong><small>Inactive <?= strtolower($masterTabs[$activeTab]['label']) ?></small></span>
            </article>
            <article class="master-stat">
                <span class="master-stat-icon stat-blue"><i class="fas <?= $thirdMetric['icon'] ?>" aria-hidden="true"></i></span>
                <span><strong><?= htmlspecialchars($thirdMetric['value']) ?></strong><small><?= htmlspecialchars($thirdMetric['label']) ?></small></span>
            </article>
        </section>

        <?php if (isset($_SESSION['error'])): ?>
            <div class="master-notification notification-error" role="alert" data-master-notification>
                <span class="notification-icon"><i class="fas fa-exclamation-triangle" aria-hidden="true"></i></span>
                <p><?= htmlspecialchars($_SESSION['error']); unset($_SESSION['error']); ?></p>
                <button type="button" class="notification-dismiss" aria-label="Dismiss notification"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>
        <?php if (isset($_SESSION['success'])): ?>
            <div class="master-notification notification-success" role="status" data-master-notification>
                <span class="notification-icon"><i class="fas fa-check" aria-hidden="true"></i></span>
                <p><?= htmlspecialchars($_SESSION['success']); unset($_SESSION['success']); ?></p>
                <button type="button" class="notification-dismiss" aria-label="Dismiss notification"><i class="fas fa-times" aria-hidden="true"></i></button>
            </div>
        <?php endif; ?>

        <div class="master-data-layout" data-items-label="<?= strtolower($masterTabs[$activeTab]['label']) ?>">
            <section class="master-panel master-create-panel" aria-labelledby="master-create-title">
                <?php if ($activeTab === 'routes'): ?>
                    <header class="master-panel-heading">
                        <h1 id="master-create-title">Add new route</h1>
                        <p>Rate and toll are applied automatically to every new ticket on this route.</p>
                    </header>
                    <form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_routes.php" method="POST" class="master-form" autocomplete="off">
                        <input type="hidden" name="action" value="create">
                        <div class="master-field">
                            <label for="route-name">Route name *</label>
                            <div class="master-input-with-icon"><i class="fas fa-route" aria-hidden="true"></i><input type="text" id="route-name" name="route_name" required maxlength="100" autocomplete="off" placeholder="e.g. Route A - Main Line"></div>
                        </div>
                        <div class="master-money-fields">
                            <div class="master-field">
                                <label for="route-rate">Rate (₱)</label>
                                <input type="number" id="route-rate" name="rate" min="0" step="0.01" value="0.00" required>
                            </div>
                            <div class="master-field">
                                <label for="route-toll">Toll (₱)</label>
                                <input type="number" id="route-toll" name="toll" min="0" step="0.01" value="0.00" required>
                            </div>
                        </div>
                        <div class="route-fee-preview" aria-live="polite">
                            <div><span>Rate</span><strong id="preview-rate">₱0.00</strong></div>
                            <div><span>Toll</span><strong id="preview-toll">₱0.00</strong></div>
                            <div class="route-preview-total"><span>Total fee per trip</span><strong id="preview-total">₱0.00</strong></div>
                        </div>
                        <button type="submit" class="master-submit"><i class="fas fa-plus" aria-hidden="true"></i> Add route</button>
                    </form>
                <?php elseif ($activeTab === 'buses'): ?>
                    <header class="master-panel-heading">
                        <h1 id="master-create-title">Add new bus</h1>
                        <p>Keep the fleet list and seating capacity up to date for trip scheduling.</p>
                    </header>
                    <form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_buses.php" method="POST" class="master-form">
                        <input type="hidden" name="action" value="create">
                        <div class="master-field">
                            <label for="bus-code">Bus code *</label>
                            <div class="master-input-with-icon"><i class="fas fa-bus" aria-hidden="true"></i><input type="text" id="bus-code" name="bus_code" required maxlength="32" placeholder="e.g. BUS-101"></div>
                        </div>
                        <div class="master-field">
                            <label for="bus-capacity">Seating capacity</label>
                            <div class="master-input-with-icon"><i class="fas fa-chair" aria-hidden="true"></i><input type="number" id="bus-capacity" name="capacity" min="1" value="40" required></div>
                        </div>
                        <button type="submit" class="master-submit"><i class="fas fa-plus" aria-hidden="true"></i> Add bus</button>
                    </form>
                <?php elseif ($activeTab === 'drivers'): ?>
                    <header class="master-panel-heading">
                        <h1 id="master-create-title">Add new driver</h1>
                        <p>Add a driver to make them available for daily trip assignments.</p>
                    </header>
                    <form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_drivers.php" method="POST" class="master-form">
                        <input type="hidden" name="action" value="create">
                        <div class="master-field">
                            <label for="driver-name">Driver full name *</label>
                            <div class="master-input-with-icon"><i class="fas fa-user" aria-hidden="true"></i><input type="text" id="driver-name" name="driver_name" required maxlength="100" placeholder="e.g. Juan Dela Cruz"></div>
                        </div>
                        <button type="submit" class="master-submit"><i class="fas fa-plus" aria-hidden="true"></i> Add driver</button>
                    </form>
                <?php else: ?>
                    <header class="master-panel-heading">
                        <h1 id="master-create-title">Assign driver, bus and route</h1>
                        <p>A driver can have multiple bus and route combinations. Add each permitted combination separately.</p>
                    </header>
                    <form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_assignments.php" method="POST" class="master-form">
                        <input type="hidden" name="action" value="create">
                        <div class="master-field">
                            <label for="assignment-driver">Driver *</label>
                            <select id="assignment-driver" name="driver_id" required>
                                <option value="">Select a driver</option>
                                <?php foreach ($drivers as $driver): ?>
                                    <?php if ($driver['status'] === 'ACTIVE'): ?>
                                        <option value="<?= (int)$driver['id'] ?>"><?= htmlspecialchars($driver['driver_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="master-field">
                            <label for="assignment-bus">Bus *</label>
                            <select id="assignment-bus" name="bus_id" required>
                                <option value="">Select a bus</option>
                                <?php foreach ($buses as $bus): ?>
                                    <?php if ($bus['status'] === 'ACTIVE'): ?>
                                        <option value="<?= (int)$bus['id'] ?>"><?= htmlspecialchars($bus['bus_code']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="master-field">
                            <label for="assignment-route">Route *</label>
                            <select id="assignment-route" name="route_id" required>
                                <option value="">Select a route</option>
                                <?php foreach ($routes as $route): ?>
                                    <?php if ($route['status'] === 'ACTIVE'): ?>
                                        <option value="<?= (int)$route['id'] ?>"><?= htmlspecialchars($route['route_name']) ?></option>
                                    <?php endif; ?>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <button type="submit" class="master-submit"><i class="fas fa-link" aria-hidden="true"></i> Add assignment</button>
                    </form>
                <?php endif; ?>
            </section>

            <section class="master-panel master-list-panel" aria-labelledby="master-list-title">
                <div class="master-list-heading">
                    <h2 id="master-list-title"><?= $activeTab === 'routes' ? 'Existing routes' : ($activeTab === 'buses' ? 'Bus fleet' : ($activeTab === 'drivers' ? 'Driver roster' : 'Driver assignments')) ?></h2>
                    <label class="master-search"><i class="fas fa-search" aria-hidden="true"></i><span class="visually-hidden">Search <?= strtolower($masterTabs[$activeTab]['label']) ?></span><input type="search" id="master-search" placeholder="Search <?= strtolower($masterTabs[$activeTab]['label']) ?>" autocomplete="off"></label>
                </div>
                <div class="master-filters" role="group" aria-label="Filter <?= strtolower($masterTabs[$activeTab]['label']) ?>">
                    <button type="button" class="master-filter is-active" data-status-filter="all" aria-pressed="true">All <span><?= count($currentItems) ?></span></button>
                    <button type="button" class="master-filter" data-status-filter="ACTIVE" aria-pressed="false">Active <span><?= $activeCount ?></span></button>
                    <button type="button" class="master-filter" data-status-filter="INACTIVE" aria-pressed="false">Inactive <span><?= $inactiveCount ?></span></button>
                </div>
                <div class="master-table-wrap">
                    <table class="master-table">
                        <thead>
                            <?php if ($activeTab === 'routes'): ?>
                                <tr><th scope="col">Route name</th><th scope="col">Rate</th><th scope="col">Toll</th><th scope="col">Total fee</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                            <?php elseif ($activeTab === 'buses'): ?>
                                <tr><th scope="col">Bus code</th><th scope="col">Capacity</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                            <?php elseif ($activeTab === 'drivers'): ?>
                                <tr><th scope="col">Driver name</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                            <?php else: ?>
                                <tr><th scope="col">Driver</th><th scope="col">Bus</th><th scope="col">Route</th><th scope="col">Status</th><th scope="col">Actions</th></tr>
                            <?php endif; ?>
                        </thead>
                        <tbody id="master-rows">
                            <?php if ($activeTab === 'routes'): ?>
                                <?php foreach ($routes as $route): ?>
                                    <tr class="master-row" data-status="<?= htmlspecialchars($route['status']) ?>" data-search="<?= htmlspecialchars(strtolower($route['route_name'])) ?>">
                                        <td data-label="Route name"><span class="master-item-icon icon-route"><i class="fas fa-route" aria-hidden="true"></i></span><strong><?= htmlspecialchars($route['route_name']) ?></strong></td>
                                        <td data-label="Rate">₱<?= number_format((float)$route['rate'], 2) ?></td>
                                        <td data-label="Toll">₱<?= number_format((float)$route['toll'], 2) ?></td>
                                        <td data-label="Total fee"><strong>₱<?= number_format((float)$route['rate'] + (float)$route['toll'], 2) ?></strong></td>
                                        <td data-label="Status"><span class="master-status <?= $route['status'] === 'ACTIVE' ? 'status-active' : 'status-inactive' ?>"><i class="fas <?= $route['status'] === 'ACTIVE' ? 'fa-check' : 'fa-pause' ?>" aria-hidden="true"></i><?= htmlspecialchars(ucfirst(strtolower($route['status']))) ?></span></td>
                                        <td data-label="Actions"><form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_routes.php" method="POST"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="route_id" value="<?= (int)$route['id'] ?>"><input type="hidden" name="status" value="<?= $route['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>"><button type="submit" class="master-toggle"><i class="fas <?= $route['status'] === 'ACTIVE' ? 'fa-pause-circle' : 'fa-play-circle' ?>" aria-hidden="true"></i><?= $route['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button></form></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($activeTab === 'buses'): ?>
                                <?php foreach ($buses as $bus): ?>
                                    <tr class="master-row" data-status="<?= htmlspecialchars($bus['status']) ?>" data-search="<?= htmlspecialchars(strtolower($bus['bus_code'])) ?>">
                                        <td data-label="Bus code"><span class="master-item-icon icon-bus"><i class="fas fa-bus" aria-hidden="true"></i></span><strong><?= htmlspecialchars($bus['bus_code']) ?></strong></td>
                                        <td data-label="Capacity"><?= (int)$bus['capacity'] ?> seats</td>
                                        <td data-label="Status"><span class="master-status <?= $bus['status'] === 'ACTIVE' ? 'status-active' : 'status-inactive' ?>"><i class="fas <?= $bus['status'] === 'ACTIVE' ? 'fa-check' : 'fa-pause' ?>" aria-hidden="true"></i><?= htmlspecialchars(ucfirst(strtolower($bus['status']))) ?></span></td>
                                        <td data-label="Actions"><form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_buses.php" method="POST"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="bus_id" value="<?= (int)$bus['id'] ?>"><input type="hidden" name="status" value="<?= $bus['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>"><button type="submit" class="master-toggle"><i class="fas <?= $bus['status'] === 'ACTIVE' ? 'fa-pause-circle' : 'fa-play-circle' ?>" aria-hidden="true"></i><?= $bus['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button></form></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php elseif ($activeTab === 'drivers'): ?>
                                <?php foreach ($drivers as $driver): ?>
                                    <tr class="master-row" data-status="<?= htmlspecialchars($driver['status']) ?>" data-search="<?= htmlspecialchars(strtolower($driver['driver_name'])) ?>">
                                        <td data-label="Driver name"><span class="master-item-icon icon-driver"><i class="fas fa-user" aria-hidden="true"></i></span><strong><?= htmlspecialchars($driver['driver_name']) ?></strong></td>
                                        <td data-label="Status"><span class="master-status <?= $driver['status'] === 'ACTIVE' ? 'status-active' : 'status-inactive' ?>"><i class="fas <?= $driver['status'] === 'ACTIVE' ? 'fa-check' : 'fa-pause' ?>" aria-hidden="true"></i><?= htmlspecialchars(ucfirst(strtolower($driver['status']))) ?></span></td>
                                        <td data-label="Actions"><form action="/xampp/yoko-na/ojt-sdpbus/backend/master_data/manage_drivers.php" method="POST"><input type="hidden" name="action" value="toggle_status"><input type="hidden" name="driver_id" value="<?= (int)$driver['id'] ?>"><input type="hidden" name="status" value="<?= $driver['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>"><button type="submit" class="master-toggle"><i class="fas <?= $driver['status'] === 'ACTIVE' ? 'fa-pause-circle' : 'fa-play-circle' ?>" aria-hidden="true"></i><?= $driver['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button></form></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <?php foreach ($assignments as $assignment): ?>
                                    <tr class="master-row" data-status="<?= htmlspecialchars($assignment['status']) ?>" data-search="<?= htmlspecialchars(strtolower($assignment['driver_name'] . ' ' . $assignment['bus_code'] . ' ' . $assignment['route_name'])) ?>">
                                        <td data-label="Driver"><span class="master-item-icon icon-driver"><i class="fas fa-user" aria-hidden="true"></i></span><strong><?= htmlspecialchars($assignment['driver_name']) ?></strong></td>
                                        <td data-label="Bus"><span class="master-item-icon icon-bus"><i class="fas fa-bus" aria-hidden="true"></i></span><strong><?= htmlspecialchars($assignment['bus_code']) ?></strong></td>
                                        <td data-label="Route"><span class="master-item-icon icon-route"><i class="fas fa-route" aria-hidden="true"></i></span><?= htmlspecialchars($assignment['route_name']) ?></td>
                                        <td data-label="Status"><span class="master-status <?= $assignment['status'] === 'ACTIVE' ? 'status-active' : 'status-inactive' ?>"><i class="fas <?= $assignment['status'] === 'ACTIVE' ? 'fa-check' : 'fa-pause' ?>" aria-hidden="true"></i><?= htmlspecialchars(ucfirst(strtolower($assignment['status']))) ?></span></td>
                                        <td data-label="Actions">
                                            <div class="master-assignment-actions">
                                                <form action="/ojt-sdpbus/backend/master_data/manage_assignments.php" method="POST">
                                                    <input type="hidden" name="action" value="toggle_status">
                                                    <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                                    <input type="hidden" name="status" value="<?= $assignment['status'] === 'ACTIVE' ? 'INACTIVE' : 'ACTIVE' ?>">
                                                    <button type="submit" class="master-toggle"><i class="fas <?= $assignment['status'] === 'ACTIVE' ? 'fa-pause-circle' : 'fa-play-circle' ?>" aria-hidden="true"></i><?= $assignment['status'] === 'ACTIVE' ? 'Deactivate' : 'Activate' ?></button>
                                                </form>
                                                <form action="/ojt-sdpbus/backend/master_data/manage_assignments.php" method="POST" class="delete-assignment-form" data-assignment-label="<?= htmlspecialchars($assignment['driver_name'] . ' · ' . $assignment['bus_code'] . ' · ' . $assignment['route_name'], ENT_QUOTES, 'UTF-8') ?>">
                                                    <input type="hidden" name="action" value="delete">
                                                    <input type="hidden" name="assignment_id" value="<?= (int)$assignment['id'] ?>">
                                                    <button type="submit" class="master-delete" aria-label="Delete assignment for <?= htmlspecialchars($assignment['driver_name'], ENT_QUOTES, 'UTF-8') ?>"><i class="fas fa-trash-alt" aria-hidden="true"></i><span>Delete</span></button>
                                                </form>
                                            </div>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                            <tr id="master-empty-row" class="master-empty-row" hidden><td colspan="<?= $activeTab === 'routes' ? 6 : (in_array($activeTab, ['buses', 'assignments'], true) ? ($activeTab === 'buses' ? 4 : 5) : 3) ?>">No matching records.</td></tr>
                        </tbody>
                    </table>
                </div>
                <?php if ($activeTab === 'assignments'): ?>
                    <a href="#assignment-driver" class="master-add-prompt">
                        <span class="master-prompt-icon"><i class="fas fa-plus" aria-hidden="true"></i></span>
                        <span><strong>Add another driver, bus and route combination</strong><small>Drivers can have multiple active assignments for trip schedules.</small></span>
                    </a>
                <?php else: ?>
                    <button type="button" class="master-add-prompt" id="focus-master-form">
                        <span class="master-prompt-icon"><i class="fas fa-plus" aria-hidden="true"></i></span>
                        <span><strong><?= $activeTab === 'routes' ? 'Add more routes to plan more trips' : ($activeTab === 'buses' ? 'Add buses to keep the fleet ready' : 'Add drivers to complete your roster') ?></strong><small><?= $activeTab === 'routes' ? 'Each active route appears in the daily Trip Schedule.' : ($activeTab === 'buses' ? 'Active buses are available for daily trip assignments.' : 'Active drivers can be selected for scheduled trips.') ?></small></span>
                    </button>
                <?php endif; ?>
                <footer class="master-table-footer">
                    <span id="master-table-footer" aria-live="polite">Showing <?= count($currentItems) ?> of <?= count($currentItems) ?> <?= strtolower($masterTabs[$activeTab]['label']) ?></span>
                    <nav class="master-pagination-controls" aria-label="Master data pages">
                        <button type="button" id="master-previous" aria-label="Previous page" disabled><i class="fas fa-chevron-left" aria-hidden="true"></i> Previous</button>
                        <span id="master-page-summary" aria-live="polite">Page 1 of 1</span>
                        <button type="button" id="master-next" aria-label="Next page" disabled>Next <i class="fas fa-chevron-right" aria-hidden="true"></i></button>
                    </nav>
                </footer>
            </section>
        </div>
    </div>

</main>

<dialog class="master-delete-dialog" id="master-delete-dialog" aria-labelledby="master-delete-title" aria-describedby="master-delete-description">
    <span class="master-delete-dialog-icon"><i class="fas fa-trash-alt" aria-hidden="true"></i></span>
    <p class="master-delete-eyebrow">Remove assignment</p>
    <h2 id="master-delete-title">Delete this combination?</h2>
    <p id="master-delete-description">This removes the assignment from the database. The driver, bus, route, and existing trip records will remain.</p>
    <p class="master-delete-target" id="master-delete-target"></p>
    <div class="master-delete-actions">
        <button type="button" class="master-delete-cancel" id="cancel-master-delete" autofocus>Keep assignment</button>
        <button type="button" class="master-delete-confirm" id="confirm-master-delete"><i class="fas fa-trash-alt" aria-hidden="true"></i> Delete assignment</button>
    </div>
</dialog>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
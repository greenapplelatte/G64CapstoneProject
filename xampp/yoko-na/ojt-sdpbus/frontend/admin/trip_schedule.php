<?php

// frontend/admin/trip_schedule.php

require_once __DIR__ . '/../../includes/auth_check.php';

require_once __DIR__ . '/../../config/database.php';

require_once __DIR__ . '/../../includes/functions.php';



require_auth(['admin']);

$pageTitle = "Trip Schedule - SDPBUS";
$extraCss = 'trip-schedule.css';
$extraScript = 'trip-schedule.js';

include __DIR__ . '/../../includes/header.php';
include __DIR__ . '/../../includes/sidebar.php';

$summaryCards = [
    ['value' => '3 of 10', 'label' => 'Drivers working', 'icon' => 'fa-user', 'tone' => 'blue'],
    ['value' => '10', 'label' => 'Routes planned', 'icon' => 'fa-route', 'tone' => 'green'],
    ['value' => '3.3', 'label' => 'Routes per driver', 'icon' => 'fa-map-location-dot', 'tone' => 'purple'],
    ['value' => '6', 'label' => 'Route-shifts without a driver', 'icon' => 'fa-triangle-exclamation', 'tone' => 'amber'],
];

$drivers = [
    ['initials' => 'AD', 'name' => 'A. Dimpalis', 'bus' => 'Bus 2101', 'runs' => '5 runs', 'pax' => '135 pax', 'route' => 'Majada Van', 'tone' => 'blue'],
    ['initials' => 'RG', 'name' => 'R. Gino', 'bus' => 'Bus 2020', 'runs' => '5 runs', 'pax' => '115 pax', 'route' => 'Alabang Bus', 'tone' => 'green'],
    ['initials' => 'MS', 'name' => 'M. Santos', 'bus' => 'Bus 2102', 'runs' => '2 runs', 'pax' => '45 pax', 'route' => 'Bicutan Van', 'tone' => 'purple'],
    ['initials' => 'JR', 'name' => 'J. Reyes', 'bus' => 'Bus 2103', 'runs' => '0 runs', 'pax' => '0 pax', 'route' => 'No routes', 'tone' => 'amber'],
];

$scheduleGroups = [
    '7AM Incoming' => [
        ['route' => 'Majada Van', 'location' => 'Majada', 'bus' => '30', 'driver' => 'A. Dimpalis', 'color' => 'blue'],
        ['route' => 'Alabang Bus', 'location' => 'Alabang', 'bus' => '40', 'driver' => 'R. Gino', 'color' => 'green'],
    ],
    '3PM Outgoing' => [
        ['route' => 'Bicutan Van', 'location' => 'Bicutan', 'bus' => '20', 'driver' => 'M. Santos', 'color' => 'purple'],
        ['route' => 'Majada Van', 'location' => 'Majada', 'bus' => '30', 'driver' => 'A. Dimpalis', 'color' => 'blue'],
        ['route' => 'Alabang Bus', 'location' => 'Alabang', 'bus' => '40', 'driver' => 'R. Gino', 'color' => 'green'],
    ],
    '7PM Outgoing' => [
        ['route' => 'Majada Van', 'location' => 'Majada', 'bus' => '30', 'driver' => 'A. Dimpalis', 'color' => 'blue'],
        ['route' => 'Alabang Bus', 'location' => 'Alabang', 'bus' => '40', 'driver' => 'R. Gino', 'color' => 'green'],
    ],
];

$routeOptions = [
    ['label' => 'Majada Van', 'detail' => 'Majada', 'driver' => 'A. Dimpalis'],
    ['label' => 'Alabang Bus (Regular 2 trips)', 'detail' => 'Alabang', 'driver' => 'R. Gino'],
    ['label' => 'Alabang Bus (Regular 4 trips)', 'detail' => 'Alabang', 'driver' => 'R. Gino'],
    ['label' => 'Alabang Van', 'detail' => 'Alabang', 'driver' => 'No driver'],
    ['label' => 'Bicutan Van', 'detail' => 'Bicutan', 'driver' => 'A. Dimpalis'],
];









?>

<main class="main-content trip-schedule-page">
    <div class="trip-schedule-shell">
        <div class="schedule-summary-row">
            <?php foreach ($summaryCards as $card): ?>
                <div class="summary-card" data-tone="<?= htmlspecialchars($card['tone']) ?>">
                    <div class="summary-icon">
                        <i class="fa-solid <?= htmlspecialchars($card['icon']) ?>" aria-hidden="true"></i>
                    </div>
                    <div>
                        <div class="summary-value"><?= htmlspecialchars($card['value']) ?></div>
                        <div class="summary-label"><?= htmlspecialchars($card['label']) ?></div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="schedule-toolbar">
            <p>Click + in any cell to add routes. Colors show the route's location.</p>
            <div class="toolbar-actions">
                <button type="button" class="schedule-btn">Copy from yesterday</button>
                <button type="button" class="schedule-btn primary">Save all</button>
            </div>
        </div>

        <div class="schedule-board">
            <aside class="driver-panel">
                <div class="driver-panel-header">Driver and Bus</div>
                <div class="driver-list">
                    <?php foreach ($drivers as $driver): ?>
                        <div class="driver-card" data-tone="<?= htmlspecialchars($driver['tone']) ?>">
                            <div class="driver-avatar"><?= htmlspecialchars($driver['initials']) ?></div>
                            <div class="driver-meta">
                                <div class="driver-name"><?= htmlspecialchars($driver['name']) ?></div>
                                <div class="driver-details"><?= htmlspecialchars($driver['bus']) ?> · <?= htmlspecialchars($driver['runs']) ?> · <?= htmlspecialchars($driver['pax']) ?></div>
                                <span class="driver-route-badge <?= htmlspecialchars($driver['tone']) ?>"><?= htmlspecialchars($driver['route']) ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </aside>

            <div class="schedule-columns">
                <?php foreach ($scheduleGroups as $shift => $routes): ?>
                    <section class="schedule-column">
                        <div class="column-header">
                            <div class="column-title">
                                <i class="fa-regular fa-clock" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($shift) ?></span>
                            </div>
                            <span class="column-meta"><?= count($routes) ?> runs planned</span>
                        </div>

                        <div class="route-cell-list">
                            <?php foreach ($routes as $route): ?>
                                <div class="route-cell">
                                    <span class="route-chip <?= htmlspecialchars($route['color']) ?>">
                                        <span class="route-chip-marker"><i class="fa-solid fa-location-dot" aria-hidden="true"></i></span>
                                        <span class="route-chip-label"><?= htmlspecialchars($route['route']) ?></span>
                                        <span class="route-chip-bus"><?= htmlspecialchars($route['bus']) ?></span>
                                    </span>
                                    <button
                                        type="button"
                                        class="slot-plus"
                                        aria-label="Add route"
                                        data-open-route-modal
                                        data-shift="<?= htmlspecialchars($shift) ?>"
                                        data-driver="<?= htmlspecialchars($route['driver']) ?>"
                                    >+</button>
                                </div>
                            <?php endforeach; ?>

                            <?php if (count($routes) === 1): ?>
                                <div class="route-cell empty">
                                    <button
                                        type="button"
                                        class="slot-plus"
                                        aria-label="Add route"
                                        data-open-route-modal
                                        data-shift="<?= htmlspecialchars($shift) ?>"
                                        data-driver="Available driver"
                                    >+</button>
                                </div>
                            <?php endif; ?>
                        </div>
                    </section>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
</main>

<div class="route-picker-modal" id="routePickerModal" aria-hidden="true">
    <div class="route-picker" role="dialog" aria-modal="true" aria-labelledby="routePickerTitle">
        <h3 id="routePickerTitle">Add routes: R. Gino, 3PM Outgoing</h3>
        <p class="route-picker-sub">Tick every route this driver covers in this shift.</p>

        <input type="text" class="route-picker-search" placeholder="Search routes" aria-label="Search routes">

        <div class="route-options">
            <?php foreach ($routeOptions as $option): ?>
                <label class="route-option" data-label="<?= strtolower(htmlspecialchars($option['label'], ENT_QUOTES, 'UTF-8')) ?>">
                    <span class="route-option-main">
                        <input type="checkbox" value="<?= htmlspecialchars($option['label']) ?>">
                        <strong><?= htmlspecialchars($option['label']) ?></strong>
                    </span>
                    <span><?= htmlspecialchars($option['detail']) ?></span>
                </label>
            <?php endforeach; ?>
        </div>

        <div class="route-picker-actions">
            <span class="route-picker-count">0 routes selected</span>
            <div class="route-picker-controls">
                <button type="button" id="cancelRouteSelection" class="route-picker-btn">Cancel</button>
                <button type="button" id="applyRouteSelection" class="route-picker-btn primary">Apply</button>
            </div>
        </div>
    </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>


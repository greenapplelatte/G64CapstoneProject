<?php
require_once __DIR__ . '/auth_check.php';
include 'includes/header.php';
?>

<div class="app-container">
    <!-- Sidebar -->
    <?php include 'includes/sidebar.php'; ?>

    <!-- Main Content Wrapper -->
    <div class="main-wrapper">
        <?php include 'includes/topbar.php'; ?>

        <!-- Main Content Area -->
        <main class="main-content">

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

            <div class="dispatch-metrics-grid">
                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">TRIPS TODAY</span>
                        <div class="card-icon"><img src="icons/delivery-truck.png" alt=""></div>
                    </div>
                    <div class="card-value">4</div>
                    <div class="card-sub">Across 8 routes</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">DAILY SUBTOTAL</span>
                        <div class="card-icon"><img src="icons/toll.png" alt=""></div>
                    </div>
                    <div class="card-value">&#8369;15,030</div>
                    <div class="card-sub">Auto-calculated</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">PASSENGER VARIANCE</span>
                        <div class="card-icon warning"><img src="icons/report.png" alt=""></div>
                    </div>
                    <div class="card-value">2</div>
                    <div class="card-sub">Requires checking</div>
                </div>

                <div class="dispatch-card">
                    <div class="card-top">
                        <span class="card-title">NEXT TICKET</span>
                        <div class="card-icon"><img src="icons/report-card.png" alt=""></div>
                    </div>
                    <div class="card-value">0149</div>
                    <div class="card-sub">Sequence auto-generated</div>
                </div>
            </div>

            <div class="dispatch-table-card">
                <div class="table-toolbar">
                    <div class="filter-group">
                        <select class="select-field">
                            <option value="all">All records</option>
                            <option value="7am">7AM Records</option>
                            <option value="7pm">7PM Records</option>
                        </select>
                        <div class="date-picker-wrapper">
                            <input type="date" value="2025-09-15" class="date-field">
                        </div>
                    </div>
                    <div class="export-group">
                        <button class="btn-outline">&darr; CSV</button>
                        <button class="btn-outline">&darr; PDF</button>
                        <button class="btn-primary-light">+ New trip</button>
                    </div>
                </div>

                <div style="overflow-x: auto;">
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
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0148</div>
                                    <div class="ticket-date">15 Sep 2025 &middot; 08:10 AM</div>
                                </td>
                                <td>
                                    <div class="route-title">Plant A &ndash; Terminal</div>
                                    <div class="shift-sub">1AM Incoming</div>
                                </td>
                                <td>
                                    <div class="bus-id">BUS-09</div>
                                    <div class="driver-name">R. Santiago</div>
                                </td>
                                <td>
                                    <div class="pax-val">42 / 45</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill info">On time</span></td>
                                <td class="rate-amount">&#8369;4,850</td>
                                <td>
                                    <div class="action-links">
                                        <a href="#" class="link-edit">Edit</a>
                                        <a href="#" class="link-delete">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0147</div>
                                    <div class="ticket-date">15 Sep 2025 &middot; 07:45 AM</div>
                                </td>
                                <td>
                                    <div class="route-title">Terminal &ndash; Plant B</div>
                                    <div class="shift-sub">1AM Outgoing</div>
                                </td>
                                <td>
                                    <div class="bus-id">BUS-12</div>
                                    <div class="driver-name">M. Navarro</div>
                                </td>
                                <td>
                                    <div class="pax-val">37 / 40</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill success">Completed</span></td>
                                <td class="rate-amount">&#8369;4,250</td>
                                <td>
                                    <div class="action-links">
                                        <a href="#" class="link-edit">Edit</a>
                                        <a href="#" class="link-delete">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0146</div>
                                    <div class="ticket-date">15 Sep 2025 &middot; 07:20 AM</div>
                                </td>
                                <td>
                                    <div class="route-title">North Hub &ndash; Plant A</div>
                                    <div class="shift-sub">1AM Incoming</div>
                                </td>
                                <td>
                                    <div class="bus-id">KIA-03</div>
                                    <div class="driver-name">A. Mendoza</div>
                                </td>
                                <td>
                                    <div class="pax-val">18 / 28</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill warning">Delayed</span></td>
                                <td class="rate-amount">&#8369;3,180</td>
                                <td>
                                    <div class="action-links">
                                        <a href="#" class="link-edit">Edit</a>
                                        <a href="#" class="link-delete">Delete</a>
                                    </div>
                                </td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0145</div>
                                    <div class="ticket-date">15 Sep 2025 &middot; 06:50 AM</div>
                                </td>
                                <td>
                                    <div class="route-title">Plant B &ndash; South Hub</div>
                                    <div class="shift-sub">1AM Outgoing</div>
                                </td>
                                <td>
                                    <div class="bus-id">VAN-05</div>
                                    <div class="driver-name">J. Reyes</div>
                                </td>
                                <td>
                                    <div class="pax-val">14 / 15</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill success">Completed</span></td>
                                <td class="rate-amount">&#8369;2,750</td>
                                <td>
                                    <div class="action-links">
                                        <a href="#" class="link-edit">Edit</a>
                                        <a href="#" class="link-delete">Delete</a>
                                    </div>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

        </main>
    </div>
</div>

<script src="assets/js/dispatch_log.js"></script>
</body>
</html>
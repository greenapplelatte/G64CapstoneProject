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

        <main class="main-content">
            <!-- Dashboard Header -->
            <div class="dashboard-header">
                <div>
                    <div class="breadcrumb-tag">OPERATIONS OVERVIEW</div>
                    <h1 class="page-title">Dashboard</h1>
                    <p class="page-subtitle">Monitor today's shuttle activity, billing, and passenger movement.</p>
                </div>
                <button class="btn-action">
                    <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"><line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/></svg>
                    Add trip ticket
                </button>
            </div>

            <!-- Metric Cards Grid -->
            <div class="metrics-grid">
                <div class="metric-card">
                    <div class="metric-card-top">
                        <span>TODAY'S TRIPS</span>
                        <div class="metric-icon"><img src="icons/delivery-truck.png" alt=""></div>
                    </div>
                    <div class="metric-value">24</div>
                    <div class="metric-footer">14 incoming &middot; 10 outgoing</div>
                </div>

                <div class="metric-card">
                    <div class="metric-card-top">
                        <span>PASSENGERS</span>
                        <div class="metric-icon"><img src="icons/profile.png" alt=""></div>
                    </div>
                    <div class="metric-value">742</div>
                    <div class="metric-footer" style="color: #10b981;">+8.4% from yesterday</div>
                </div>

                <div class="metric-card">
                    <div class="metric-card-top">
                        <span>TODAY'S BILLING</span>
                        <div class="metric-icon"><img src="icons/report.png" alt=""></div>
                    </div>
                    <div class="metric-value">&#8369;118,420</div>
                    <div class="metric-footer">Rates + toll charges</div>
                </div>

                <div class="metric-card">
                    <div class="metric-card-top">
                        <span>ACTIVE ROUTES</span>
                        <div class="metric-icon"><img src="icons/signal.png" alt=""></div>
                    </div>
                    <div class="metric-value">8</div>
                    <div class="metric-footer">3 trips currently running</div>
                </div>
            </div>

            <!-- Main Content Area: Chart and Notifications -->
            <div class="dashboard-content-grid">
                <!-- Passenger Load Chart Panel -->
                <div class="card-panel">
                    <div class="panel-header">
                        <div>
                            <h3>Passenger load trend</h3>
                            <div style="font-size: 0.75rem; color: var(--text-muted);">September 1&ndash;15, 2025</div>
                        </div>
                        <select class="select-dropdown">
                            <option>Last 15 days</option>
                            <option>Last 30 days</option>
                        </select>
                    </div>
                    <div class="chart-container">
                        <canvas id="passengerTrendChart"></canvas>
                    </div>
                    <div class="chart-legend-bar">
                        <span><span class="legend-dot"></span> Actual passengers</span>
                        <span class="avg-stat">Avg. <strong>49</strong> passengers / day</span>
                    </div>
                </div>

                <!-- Alerts Panel -->
                <div class="card-panel">
                    <div class="panel-header">
                        <div style="display:flex; align-items:center; gap:0.5rem;">
                            <h3>Needs attention</h3>
                            <span class="badge-count">3</span>
                        </div>
                    </div>
                    <div class="alert-list">
                        <div class="alert-item">
                            <div class="alert-left">
                                <div class="alert-icon"><img src="icons/report.png" alt=""></div>
                                <div>
                                    <div class="alert-title">TT-2025-0146 &middot; North Hub - Place A</div>
                                    <div class="alert-sub">10 below forecast</div>
                                </div>
                            </div>
                            <span class="chevron">&rsaquo;</span>
                        </div>

                        <div class="alert-item">
                            <div class="alert-left">
                                <div class="alert-icon"><img src="icons/report.png" alt=""></div>
                                <div>
                                    <div class="alert-title">TT-2025-0139 &middot; Terminal 1 - Place B</div>
                                    <div class="alert-sub">8 below forecast</div>
                                </div>
                            </div>
                            <span class="chevron">&rsaquo;</span>
                        </div>

                        <div class="alert-item">
                            <div class="alert-left">
                                <div class="alert-icon warning"><img src="icons/calendar.png" alt=""></div>
                                <div>
                                    <div class="alert-title">TT-2025-0142 &middot; Place A - South Hub</div>
                                    <div class="alert-sub">Late review required</div>
                                </div>
                            </div>
                            <span class="chevron">&rsaquo;</span>
                        </div>
                    </div>
                    <a href="#" class="btn-link">View all alerts &rsaquo;</a>
                </div>
            </div>

            <!-- Recent Trip Tickets Table Section -->
            <div class="card-panel table-panel">
                <div class="panel-header table-header-flex">
                    <div>
                        <h3>Recent trip tickets</h3>
                        <p class="panel-subtitle">Latest dispatch activity for today</p>
                    </div>
                    <div class="table-actions">
                        <button class="btn-secondary">
                            <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/></svg>
                            Export
                        </button>
                        <button class="btn-primary-soft">View all trips &rsaquo;</button>
                    </div>
                </div>

                <div class="table-responsive">
                    <table class="data-table">
                        <thead>
                            <tr>
                                <th>TICKET</th>
                                <th>ROUTE &amp; SHIFT</th>
                                <th>BUS / DRIVER</th>
                                <th>PASSENGERS</th>
                                <th>STATUS</th>
                                <th>RATE</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0148</div>
                                    <div class="ticket-time">08:10 AM</div>
                                </td>
                                <td>
                                    <div class="route-name">Plant A &ndash; Terminal</div>
                                    <div class="route-sub">1AM Incoming</div>
                                </td>
                                <td>
                                    <div class="bus-code">BUS-09</div>
                                    <div class="driver-name">R. Santiago</div>
                                </td>
                                <td>
                                    <div class="pax-count">42 / 45</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill info">On time</span></td>
                                <td class="rate-val">&#8369;4,850</td>
                                <td><button class="btn-more">&bull;&bull;&bull;</button></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0147</div>
                                    <div class="ticket-time">07:45 AM</div>
                                </td>
                                <td>
                                    <div class="route-name">Terminal &ndash; Plant B</div>
                                    <div class="route-sub">1AM Incoming</div>
                                </td>
                                <td>
                                    <div class="bus-code">BUS-12</div>
                                    <div class="driver-name">M. Navarro</div>
                                </td>
                                <td>
                                    <div class="pax-count">37 / 40</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill success">Completed</span></td>
                                <td class="rate-val">&#8369;4,250</td>
                                <td><button class="btn-more">&bull;&bull;&bull;</button></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0146</div>
                                    <div class="ticket-time">07:20 AM</div>
                                </td>
                                <td>
                                    <div class="route-name">North Hub &ndash; Plant A</div>
                                    <div class="route-sub">1AM Incoming</div>
                                </td>
                                <td>
                                    <div class="bus-code">KIA-03</div>
                                    <div class="driver-name">A. Mendoza</div>
                                </td>
                                <td>
                                    <div class="pax-count">18 / 28</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill warning">Delayed</span></td>
                                <td class="rate-val">&#8369;3,180</td>
                                <td><button class="btn-more">&bull;&bull;&bull;</button></td>
                            </tr>
                            <tr>
                                <td>
                                    <div class="ticket-code">TT-2025-0145</div>
                                    <div class="ticket-time">06:50 AM</div>
                                </td>
                                <td>
                                    <div class="route-name">Plant B &ndash; South Hub</div>
                                    <div class="route-sub">1AM Outgoing</div>
                                </td>
                                <td>
                                    <div class="bus-code">VAN-05</div>
                                    <div class="driver-name">J. Reyes</div>
                                </td>
                                <td>
                                    <div class="pax-count">14 / 15</div>
                                    <div class="pax-sub">actual / forecast</div>
                                </td>
                                <td><span class="status-pill success">Completed</span></td>
                                <td class="rate-val">&#8369;2,750</td>
                                <td><button class="btn-more">&bull;&bull;&bull;</button></td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Footer Meta -->
            <div class="dashboard-footer-meta">
                <span>SDPBUS Operations System v1.00</span>
                <span>Last synced today at 10:27 AM</span>
            </div>
        </main>
    </div>
</div>

<script src="assets/js/dashboard.js"></script>
</body>
</html>
<?php
session_start();

// Redirect to login if not authenticated
if (!isset($_SESSION['user_id'])) {
    header("Location: index.php");
    exit;
}

$fullname =$_SESSION['fullname'] ?? 'System Administrator';
$username =$_SESSION['username'] ?? 'admin';
$role     =$_SESSION['role']     ?? 'admin';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>SDPBUS - Dispatch Dashboard</title>
    <style>
        :root {
            --navy-bg: #030832;
            --card-bg: #0c1248;
            --accent-blue: #1e295d;
            --text-light: #ffffff;
            --text-muted: #9ca3af;
            --border-color: #1f2963;
            --btn-red: #e11d48;
            --btn-red-hover: #be123c;
            --btn-blue: #2563eb;
            --btn-blue-hover: #1d4ed8;
            --btn-green: #10b981;
            --btn-green-hover: #059669;
        }

        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
            font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif;
        }

        body {
            background-color: var(--navy-bg);
            color: var(--text-light);
            min-height: 100vh;
            padding: 2rem;
        }

        /* Top Bar Header */
        header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 2rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        .header-title h1 {
            font-size: 1.75rem;
            font-weight: 700;
        }

        .header-title p {
            font-size: 0.9rem;
            color: var(--text-muted);
            margin-top: 0.2rem;
        }

        .btn-logout {
            background-color: var(--btn-red);
            color: #ffffff;
            padding: 0.5rem 1.25rem;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            text-decoration: none;
            transition: background-color 0.2s;
        }

        .btn-logout:hover {
            background-color: var(--btn-red-hover);
        }

        /* Dashboard Layout Grid */
        .dashboard-grid {
            display: grid;
            grid-template-columns: 1fr 2fr;
            gap: 1.5rem;
        }

        @media (max-width: 900px) {
            .dashboard-grid {
                grid-template-columns: 1fr;
            }
        }

        .card {
            background-color: var(--card-bg);
            border: 1px solid var(--border-color);
            border-radius: 12px;
            padding: 1.5rem;
        }

        .card-header {
            margin-bottom: 1.25rem;
        }

        .card-header h2 {
            font-size: 1.2rem;
            font-weight: 600;
        }

        .card-header p {
            font-size: 0.85rem;
            color: var(--text-muted);
            margin-top: 0.25rem;
        }

        /* Form Controls */
        .form-group {
            margin-bottom: 1rem;
        }

        .form-group label {
            display: block;
            font-size: 0.85rem;
            margin-bottom: 0.4rem;
            color: #d1d5db;
        }

        .form-control {
            width: 100%;
            padding: 0.65rem 0.85rem;
            background-color: var(--accent-blue);
            border: 1px solid var(--border-color);
            border-radius: 6px;
            color: #ffffff;
            font-size: 0.9rem;
            outline: none;
        }

        .form-control:focus {
            border-color: #3b82f6;
        }

        .btn-submit {
            width: 100%;
            padding: 0.75rem;
            background-color: var(--btn-blue);
            color: #ffffff;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            margin-top: 0.5rem;
            transition: background-color 0.2s;
        }

        .btn-submit:hover {
            background-color: var(--btn-blue-hover);
        }

        /* Shuttle Log Table */
        .table-responsive {
            width: 100%;
            overflow-x: auto;
        }

        table {
            width: 100%;
            border-collapse: collapse;
            font-size: 0.875rem;
            text-align: left;
        }

        th {
            background-color: var(--accent-blue);
            padding: 0.75rem 1rem;
            color: var(--text-muted);
            font-weight: 600;
            border-bottom: 1px solid var(--border-color);
        }

        td {
            padding: 0.75rem 1rem;
            border-bottom: 1px solid var(--border-color);
        }

        tr:hover {
            background-color: rgba(255, 255, 255, 0.03);
        }

        .badge {
            padding: 0.25rem 0.5rem;
            border-radius: 4px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .badge-active { background-color: #065f46; color: #34d399; }
        .badge-pending { background-color: #92400e; color: #fcd34d; }
    </style>
</head>
<body>

<header>
    <div class="header-title">
        <h1>SDPBUS Dispatch Dashboard</h1>
        <p>Logged in as: <strong><?= htmlspecialchars($fullname) ?></strong> (<?= htmlspecialchars($username) ?>)</p>
    </div>
    <a href="logout.php" class="btn-logout">Logout</a>
</header>

<div class="dashboard-grid">
    <!-- Left Panel: Dispatch Parameters Form -->
    <div class="card">
        <div class="card-header">
            <h2>Dispatch Parameters</h2>
            <p>Input details to auto-fill daily shuttle logs.</p>
        </div>

        <form id="dispatchForm">
            <div class="form-group">
                <label for="route">Select Route</label>
                <select id="route" class="form-control" required>
                    <option value="">-- Choose Route --</option>
                    <option value="Route 1: Plant A -> City Center">Route 1: Plant A -> City Center</option>
                    <option value="Route 2: Main Terminal -> South Hub">Route 2: Main Terminal -> South Hub</option>
                    <option value="Route 3: Express Line">Route 3: Express Line</option>
                </select>
            </div>

            <div class="form-group">
                <label for="driver">Assigned Driver</label>
                <input type="text" id="driver" class="form-control" placeholder="e.g. Juan Dela Cruz" required>
            </div>

            <div class="form-group">
                <label for="bus_unit">Bus Unit / Plate No.</label>
                <input type="text" id="bus_unit" class="form-control" placeholder="e.g. BUS-102 (ABC 1234)" required>
            </div>

            <div class="form-group">
                <label for="departure_time">Scheduled Departure</label>
                <input type="time" id="departure_time" class="form-control" required>
            </div>

            <button type="submit" class="btn-submit">Dispatch Shuttle</button>
        </form>
    </div>

    <!-- Right Panel: Live Shuttle Logs -->
    <div class="card">
        <div class="card-header">
            <h2>Daily Shuttle Logs</h2>
            <p>Active and scheduled trips for today.</p>
        </div>

        <div class="table-responsive">
            <table>
                <thead>
                    <tr>
                        <th>Trip ID</th>
                        <th>Route</th>
                        <th>Driver</th>
                        <th>Bus Unit</th>
                        <th>Departure</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody id="logTableBody">
                    <tr>
                        <td>#TRIP-1001</td>
                        <td>Route 1: Plant A -> City Center</td>
                        <td>Juan Dela Cruz</td>
                        <td>BUS-101</td>
                        <td>07:30 AM</td>
                        <td><span class="badge badge-active">Active</span></td>
                    </tr>
                    <tr>
                        <td>#TRIP-1002</td>
                        <td>Route 2: Main Terminal -> South Hub</td>
                        <td>Pedro Penduko</td>
                        <td>BUS-105</td>
                        <td>08:15 AM</td>
                        <td><span class="badge badge-pending">Pending</span></td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
    document.getElementById('dispatchForm').addEventListener('submit', function(e) {
        e.preventDefault();
        
        const route = document.getElementById('route').value;
        const driver = document.getElementById('driver').value;
        const bus = document.getElementById('bus_unit').value;
        const time = document.getElementById('departure_time').value;

        const tbody = document.getElementById('logTableBody');
        const newRow = document.createElement('tr');
        const tripId = '#TRIP-' + Math.floor(1000 + Math.random() * 9000);

        newRow.innerHTML = `
            <td>${tripId}</td>
            <td>${route}</td>
            <td>${driver}</td>
            <td>${bus}</td>
            <td>${time}</td>
            <td><span class="badge badge-pending">Pending</span></td>
        `;

        tbody.prepend(newRow);
        this.reset();
    });
</script>

</body>
</html>
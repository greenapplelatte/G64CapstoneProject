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
    <link rel="stylesheet" href="sdpbus/css/dashboard.css">
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
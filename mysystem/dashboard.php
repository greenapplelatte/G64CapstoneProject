<?php
// You can include session checks or backend database connection code here later
$pageTitle = "Shuttle Operations Dashboard";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $pageTitle; ?></title>
    <link rel="stylesheet" href="assets/css/style.css">
</head>
<body>

    <div class="sidebar">
        <h2>Shuttle Tracker</h2>
        <nav>
            <a href="#" class="active">Dashboard</a>
            <a href="#">Routes</a>
            <a href="#">Vehicles</a>
            <a href="login.php">Logout</a>
        </nav>
    </div>

    <div class="main-content">
        <header>
            <h1>Real-Time Tracking Dashboard</h1>
        </header>

        <div class="metrics-grid">
            <div class="card">
                <h4>Active Trips Today</h4>
                <div class="number">12</div>
            </div>
            <div class="card">
                <h4>Total Passengers</h4>
                <div class="number">184</div>
            </div>
        </div>

        <div class="content-grid">
            <div class="form-card">
                <h3>Log New Trip</h3>
                <form id="tripForm">
                    <div class="form-group">
                        <label>Select Route</label>
                        <select id="routeSelect" required>
                            <option value="">Choose route...</option>
                            <option value="Main Office → Laguna Branch">Main Office → Laguna Branch</option>
                            <option value="Magallanes → Alabang">Magallanes → Alabang</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <label>Vehicle Plate No.</label>
                        <input type="text" id="vehicleInput" placeholder="e.g., DAA 8564" required>
                    </div>
                    <div class="form-group">
                        <label>Passenger Count</label>
                        <input type="number" id="passengerInput" placeholder="e.g., 15" required>
                    </div>
                    <button type="submit" class="btn-primary">Save Trip Log</button>
                </form>
            </div>

            <div class="table-card">
                <h3>Live Manifest Log</h3>
                <table>
                    <thead>
                        <tr>
                            <th>Trip ID</th>
                            <th>Route</th>
                            <th>Vehicle</th>
                            <th>Passengers</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="manifestTableBody">
                        <tr>
                            <td>TRIP-001</td>
                            <td>Main Office → Laguna Branch</td>
                            <td>DAA 8564</td>
                            <td>18</td>
                            <td><span class="badge badge-normal">Active</span></td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>

    <script src="assets/js/script.js"></script>
</body>
</html>
<?php
require_once __DIR__ . '/auth_check.php';

$dbPath = __DIR__ . '/database/sdpbus.db';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['id'])) {
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("UPDATE trip_tickets SET 
            ticket_number = ?, 
            trip_date = ?, 
            trip_time = ?, 
            route = ?, 
            shift = ?, 
            bus_number = ?, 
            driver_name = ?, 
            actual_passengers = ?, 
            forecast_passengers = ?, 
            status = ?, 
            rate = ?
            WHERE id = ?");

        $stmt->execute([
            $_POST['ticket_number'],
            $_POST['trip_date'],
            $_POST['trip_time'],
            $_POST['route'],
            $_POST['shift'],
            $_POST['bus_number'],
            $_POST['driver_name'],
            (int)$_POST['actual_passengers'],
            (int)$_POST['forecast_passengers'],
            $_POST['status'],
            (float)$_POST['rate'],
            (int)$_POST['id']
        ]);

        header("Location: dispatch_log.php?status=updated");
        exit;
    } catch (PDOException $e) {
        die("Error updating trip ticket: " . htmlspecialchars($e->getMessage()));
    }
} else {
    header("Location: dispatch_log.php");
    exit;
}
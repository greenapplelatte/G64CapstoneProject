<?php
require_once __DIR__ . '/auth_check.php';

$dbPath = __DIR__ . '/database/sdpbus.db';

if (isset($_GET['id'])) {
    $id = (int)$_GET['id'];
    
    try {
        $pdo = new PDO("sqlite:" . $dbPath);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

        $stmt = $pdo->prepare("DELETE FROM trip_tickets WHERE id = ?");
        $stmt->execute([$id]);

        header("Location: dispatch_log.php?status=deleted");
        exit;
    } catch (PDOException $e) {
        die("Error deleting ticket: " . htmlspecialchars($e->getMessage()));
    }
} else {
    header("Location: dispatch_log.php");
    exit;
}
<?php
// backend/process_system_settings.php
header('Content-Type: application/json');
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/auth.php';
require_once __DIR__ . '/../includes/functions.php';

check_role('manager');

$action = $_GET['action'] ?? '';

// Fetch Current Settings & History
if ($action === 'get_settings') {
    $settings = db_one($conn, "SELECT * FROM system_settings WHERE id = 1");
    $history  = db_all($conn, "SELECT * FROM system_settings_history ORDER BY id DESC LIMIT 5");

    echo json_encode([
        'status' => 'success',
        'settings' => $settings,
        'history' => $history
    ]);
    exit;
}

// Update System Settings
if ($action === 'save_settings') {
    require_post();
    $plant_name             = $_POST['plant_name'] ?? 'Masters Vineyard Enterprise';
    $time_zone              = $_POST['time_zone'] ?? 'Asia/Manila (UTC+08:00)';
    $time_format            = $_POST['time_format'] ?? '12-hour (12:00 PM)';
    
    $alert_defect_30m       = isset($_POST['alert_defect_30m']) ? 1 : 0;
    $alert_line_stop_15m    = isset($_POST['alert_line_stop_15m']) ? 1 : 0;
    $alert_oee_under_target = isset($_POST['alert_oee_under_target']) ? 1 : 0;
    $alert_station_offline  = isset($_POST['alert_station_offline']) ? 1 : 0;
    $alert_weekly_summary   = isset($_POST['alert_weekly_summary']) ? 1 : 0;
    
    $inactivity_timeout     = $_POST['inactivity_timeout'] ?? '30 minutes';
    $min_password_len       = intval($_POST['min_password_len'] ?? 10);
    $password_expiry        = $_POST['password_expiry'] ?? '90 days';
    $require_2fa_managers   = isset($_POST['require_2fa_managers']) ? 1 : 0;
    
    $history_retention      = $_POST['history_retention'] ?? '24 months';
    $weekly_export_schedule = $_POST['weekly_export_schedule'] ?? 'Monday, 06:00 AM';
    
    $checkin_interval_sec   = intval($_POST['checkin_interval_sec'] ?? 10);
    $offline_threshold_sec  = intval($_POST['offline_threshold_sec'] ?? 60);

    $stmt = $conn->prepare("UPDATE system_settings SET 
        plant_name=?, time_zone=?, time_format=?, 
        alert_defect_30m=?, alert_line_stop_15m=?, alert_oee_under_target=?, alert_station_offline=?, alert_weekly_summary=?, 
        inactivity_timeout=?, min_password_len=?, password_expiry=?, require_2fa_managers=?, 
        history_retention=?, weekly_export_schedule=?, checkin_interval_sec=?, offline_threshold_sec=? 
        WHERE id=1");

    $stmt->bind_param("sssiiiiisisiisii", 
        $plant_name, $time_zone, $time_format, 
        $alert_defect_30m, $alert_line_stop_15m, $alert_oee_under_target, $alert_station_offline, $alert_weekly_summary, 
        $inactivity_timeout, $min_password_len, $password_expiry, $require_2fa_managers, 
        $history_retention, $weekly_export_schedule, $checkin_interval_sec, $offline_threshold_sec
    );

    if ($stmt->execute()) {
        // Log modification audit
        $actor = $_SESSION['full_name'] ?? 'Manager1';
        $log_time = date('M d · h:i A');
        $desc = "updated plant-wide system settings";
        $log_stmt = $conn->prepare("INSERT INTO system_settings_history (log_timestamp, actor, action_description) VALUES (?, ?, ?)");
        $log_stmt->bind_param("sss", $log_time, $actor, $desc);
        $log_stmt->execute();

        echo json_encode(['status' => 'success', 'message' => 'System settings saved successfully.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Failed to save settings.']);
    }
    $stmt->close();
    exit;
}
?>
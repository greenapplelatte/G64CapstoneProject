-- ============================================================================
-- G64CapstoneProject - table restore script
-- Rebuilt from the queries in /backend/*.php (+ the views that read their JSON)
--
-- HOW TO USE (phpMyAdmin):
--   1. Select the g64capstoneproject database  ->  Import tab  ->  choose this file
--   2. Do NOT re-import the old config/database.sql afterwards: it runs
--      "DELETE FROM users" and only knows about 3 tables.
--
-- SAFE TO RE-RUN: every CREATE uses IF NOT EXISTS, every seed uses INSERT IGNORE
-- with fixed ids, and nothing is dropped or deleted.
--
-- Requires MariaDB (XAMPP default) because of "ADD COLUMN IF NOT EXISTS".
--
-- NOTE: the seed rows are SAMPLE/DEMO values so the dashboards render.
-- They are NOT your original data (that cannot be rebuilt from code).
-- ============================================================================

CREATE DATABASE IF NOT EXISTS g64capstoneproject
  CHARACTER SET utf8mb4 COLLATE utf8mb4_general_ci;
USE g64capstoneproject;
SET NAMES utf8mb4;

-- ----------------------------------------------------------------------------
-- 1. CORE TABLES (from the original database.sql) - kept as-is
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    role ENUM('manager', 'supervisor', 'staff') NOT NULL,
    full_name VARCHAR(100) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS production_sessions (
    id INT AUTO_INCREMENT PRIMARY KEY,
    machine_id VARCHAR(50) NOT NULL,
    target_goal INT NOT NULL,
    good_count INT DEFAULT 0,
    ng_count INT DEFAULT 0,
    status ENUM('active', 'paused', 'completed') DEFAULT 'active',
    created_by INT,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS defect_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    session_id INT,
    defect_type VARCHAR(100) NOT NULL,
    severity ENUM('low', 'medium', 'high') DEFAULT 'medium',
    reported_by INT,
    timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (session_id) REFERENCES production_sessions(id) ON DELETE CASCADE,
    FOREIGN KEY (reported_by) REFERENCES users(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ----------------------------------------------------------------------------
-- 2. USERS: extra columns used by process_*_profile.php + process_user_management.php
-- ----------------------------------------------------------------------------
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS employee_id          VARCHAR(20)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS department           VARCHAR(80)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS contact_number       VARCHAR(30)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS assigned_station     VARCHAR(60)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS shift_schedule       VARCHAR(80)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS last_signin          VARCHAR(40)  DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS pref_defect_alert    TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS pref_shift_reminder  TINYINT(1)   NOT NULL DEFAULT 1,
    ADD COLUMN IF NOT EXISTS pref_24h_format      TINYINT(1)   NOT NULL DEFAULT 0,
    ADD COLUMN IF NOT EXISTS assignment           VARCHAR(50)  NOT NULL DEFAULT 'Line 1',
    ADD COLUMN IF NOT EXISTS last_active          VARCHAR(40)  DEFAULT 'Now',
    ADD COLUMN IF NOT EXISTS status               VARCHAR(20)  NOT NULL DEFAULT 'Active';

-- Demo accounts (password for all three: password123). Real bcrypt hash this time,
-- so "Change password" in the profile pages works (the old seed hash was fake).
INSERT INTO users (id, username, password, role, full_name, employee_id, department,
                   assigned_station, shift_schedule, last_signin, assignment, last_active, status)
VALUES
(1, 'manager1',    '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'manager',
    'Production Manager', 'MV-0001', 'Production Management', 'All lines',
    'Shift 1 · 06:00 AM – 02:00 PM', 'Today', 'All Lines', 'Now', 'Active'),
(2, 'supervisor1', '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'supervisor',
    'Shift Supervisor', 'MV-0002', 'Production - Line 1', 'ESP32-STN01 – STN04',
    'Shift 1 · 06:00 AM – 02:00 PM', 'Today', 'Line 1', 'Now', 'Active'),
(3, 'staff1',      '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'staff',
    'Line Operator', 'MV-0003', 'Production - Line 1', 'ESP32-STN04',
    'Shift 1 · 06:00 AM – 02:00 PM', 'Today', 'Line 1', 'Now', 'Active')
ON DUPLICATE KEY UPDATE
    employee_id      = VALUES(employee_id),
    department       = VALUES(department),
    assigned_station = VALUES(assigned_station),
    shift_schedule   = VALUES(shift_schedule);

-- api.php / staff dashboard read production_sessions id = 1
INSERT IGNORE INTO production_sessions (id, machine_id, target_goal, good_count, ng_count, status, created_by) VALUES
(1, 'ESP32-STN04', 120, 71, 3, 'active', 3);

-- ----------------------------------------------------------------------------
-- 3. DEFECT REPORTS  (process_defect.php, process_defect_review.php, supervisor pages)
--    notes / image_path are what process_defect.php INSERTs.
--    operator_notes / photo_url are what the supervisor view READS, so they are
--    generated from the two real columns (no code change needed).
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS defect_reports (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    report_code        VARCHAR(20)  DEFAULT NULL,
    project_type       VARCHAR(100) DEFAULT NULL,
    workstation        VARCHAR(100) DEFAULT NULL,
    station            VARCHAR(30)  DEFAULT 'ESP32-STN04',
    defect_category    VARCHAR(100) NOT NULL,
    quantity_rejected  INT          NOT NULL DEFAULT 1,
    severity           VARCHAR(20)  NOT NULL DEFAULT 'Minor',      -- Minor / Major / Critical
    notes              TEXT         DEFAULT NULL,
    image_path         VARCHAR(255) DEFAULT NULL,
    reported_by        VARCHAR(100) DEFAULT NULL,
    report_time        VARCHAR(20)  DEFAULT NULL,
    status             VARCHAR(30)  NOT NULL DEFAULT 'Pending',    -- Pending / Confirmed / Rejected / Recheck Requested
    supervisor_comment TEXT         DEFAULT NULL,
    created_at         TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
    operator_notes     TEXT GENERATED ALWAYS AS (notes) VIRTUAL,
    photo_url          VARCHAR(300) GENERATED ALWAYS AS (
                          IF(image_path IS NULL OR image_path = '', NULL,
                             CONCAT('/G64CapstoneProject/', image_path))) VIRTUAL,
    KEY idx_status (status),
    KEY idx_created (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO defect_reports
 (id, report_code, project_type, workstation, station, defect_category, quantity_rejected, severity,
  notes, reported_by, report_time, status, supervisor_comment, created_at) VALUES
(1, 'DR-0001', 'Vent Caps (A2)', 'Inspection',        'ESP32-STN04', 'Short shot', 1, 'Major',
    'Cap not fully formed at the rim.',            'Line Operator', '08:12:00', 'Pending',   NULL,                    NOW() - INTERVAL 90 MINUTE),
(2, 'DR-0002', 'Vent Caps (A2)', 'Assembly',          'ESP32-STN03', 'Scratch',    2, 'Minor',
    'Surface scratches after trimming.',           'staff3',        '08:40:00', 'Pending',   NULL,                    NOW() - INTERVAL 60 MINUTE),
(3, 'DR-0003', 'Vent Caps (A2)', 'Injection Molding','ESP32-STN01', 'Flash',      3, 'Major',
    'Excess material on parting line.',            'staff1',        '07:20:00', 'Confirmed', 'Confirmed, adjust clamp.', NOW() - INTERVAL 150 MINUTE),
(4, 'DR-0004', 'Vent Caps (A2)', 'Trimming',          'ESP32-STN02', 'Crack',      1, 'Critical',
    'Hairline crack near the vent hole.',          'staff2',        '07:05:00', 'Confirmed', 'Pulled lot for check.',    NOW() - INTERVAL 170 MINUTE),
(5, 'DR-0005', 'Vent Caps (A2)', 'Inspection',        'ESP32-STN04', 'Warpage',    1, 'Minor',
    'Slight bend, within tolerance on re-measure.','Line Operator', '06:50:00', 'Rejected',  'Within tolerance.',       NOW() - INTERVAL 190 MINUTE),
(6, 'DR-0006', 'Vent Caps (A2)', 'Assembly',          'ESP32-STN03', 'Scratch',    1, 'Minor',
    'Possible handling mark, please recheck.',     'staff3',        '09:05:00', 'Recheck Requested', 'Photo is unclear.', NOW() - INTERVAL 30 MINUTE);

-- ----------------------------------------------------------------------------
-- 4. SHIFT RECORDS  (get_activity_data.php, get_shift_yield_data.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS shift_records (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    staff_id        INT           DEFAULT NULL,
    shift_title     VARCHAR(50)   NOT NULL,
    shift_date      DATE          NOT NULL,
    time_start      TIME          NOT NULL,
    time_end        TIME          NOT NULL,
    time_range      VARCHAR(40)   DEFAULT NULL,
    produced_units  INT           NOT NULL DEFAULT 0,
    good_units      INT           NOT NULL DEFAULT 0,
    ng_units        INT           NOT NULL DEFAULT 0,
    rejected_units  INT           NOT NULL DEFAULT 0,
    pending_units   INT           NOT NULL DEFAULT 0,
    yield_rate      DECIMAL(5,2)  NOT NULL DEFAULT 100.00,
    yield_pct       DECIMAL(5,1)  NOT NULL DEFAULT 100.0,
    status          VARCHAR(10)   NOT NULL DEFAULT 'OPEN',        -- OPEN / CLOSED
    KEY idx_staff (staff_id),
    KEY idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO shift_records
 (id, staff_id, shift_title, shift_date, time_start, time_end, time_range, produced_units, good_units, ng_units, rejected_units, pending_units, yield_rate, yield_pct, status) VALUES
(1, 3, 'Shift 1', '2026-10-14', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 342, 334,  8,  8, 2, 97.66, 97.7, 'OPEN'),
(2, 3, 'Shift 1', '2026-10-13', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 468, 459,  9,  9, 0, 98.08, 98.1, 'CLOSED'),
(3, 3, 'Shift 1', '2026-10-12', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 455, 441, 14, 14, 0, 96.92, 96.9, 'CLOSED'),
(4, 3, 'Shift 1', '2026-10-09', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 472, 466,  6,  6, 0, 98.73, 98.7, 'CLOSED'),
(5, 3, 'Shift 1', '2026-10-08', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 449, 437, 12, 12, 0, 97.33, 97.3, 'CLOSED'),
(6, 3, 'Shift 1', '2026-10-07', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 460, 448, 12, 12, 0, 97.39, 97.4, 'CLOSED');

CREATE TABLE IF NOT EXISTS shift_workstation_yield (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    shift_record_id  INT           NOT NULL,
    station_code     VARCHAR(30)   NOT NULL,
    workstation_name VARCHAR(80)   NOT NULL,
    output_units     INT           NOT NULL DEFAULT 0,
    ng_units         INT           NOT NULL DEFAULT 0,
    pending_units    INT           NOT NULL DEFAULT 0,
    yield_pct        DECIMAL(5,1)  NOT NULL DEFAULT 100.0,
    is_lowest        TINYINT(1)    NOT NULL DEFAULT 0,
    KEY idx_shift (shift_record_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO shift_workstation_yield
 (id, shift_record_id, station_code, workstation_name, output_units, ng_units, pending_units, yield_pct, is_lowest) VALUES
(1, 1, 'ESP32-STN01', 'Injection Molding', 96, 2, 0, 98.0, 0),
(2, 1, 'ESP32-STN02', 'Trimming',          88, 1, 0, 98.9, 0),
(3, 1, 'ESP32-STN03', 'Assembly',          79, 2, 1, 97.5, 0),
(4, 1, 'ESP32-STN04', 'Inspection',        71, 3, 1, 95.9, 1);

-- ----------------------------------------------------------------------------
-- 5. STAFF ACTIVITY LOG  (get_activity_data.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS staff_activity_logs (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    staff_id   INT          NOT NULL,
    log_time   TIME         NOT NULL,
    log_type   VARCHAR(20)  NOT NULL,          -- Count / Defect / Shift / System
    details    VARCHAR(255) NOT NULL,
    station_id VARCHAR(30)  DEFAULT NULL,
    KEY idx_staff (staff_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO staff_activity_logs (id, staff_id, log_time, log_type, details, station_id) VALUES
(1, 3, '06:00:00', 'Shift',  'Shift 1 started',                              'ESP32-STN04'),
(2, 3, '06:01:00', 'System', 'Station ESP32-STN04 connected',                'ESP32-STN04'),
(3, 3, '07:00:00', 'Count',  'Hourly count logged: 17 good, 0 NG',           'ESP32-STN04'),
(4, 3, '08:12:00', 'Defect', 'Defect report submitted: Short shot (1 unit)', 'ESP32-STN04'),
(5, 3, '08:00:00', 'Count',  'Hourly count logged: 18 good, 1 NG',           'ESP32-STN04'),
(6, 3, '09:00:00', 'Count',  'Hourly count logged: 17 good, 1 NG',           'ESP32-STN04'),
(7, 3, '09:30:00', 'System', 'Auto-counter sensor check passed',             'ESP32-STN04'),
(8, 3, '10:00:00', 'Count',  'Hourly count logged: 19 good, 1 NG',           'ESP32-STN04');

-- ----------------------------------------------------------------------------
-- 6. SUPERVISOR TABLES  (get_supervisor_dashboard.php, process_line_control.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS supervisor_line_sessions (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    session_code   VARCHAR(30)  NOT NULL,
    status         VARCHAR(10)  NOT NULL DEFAULT 'RUNNING',   -- RUNNING / PAUSED / CLOSED
    product_name   VARCHAR(80)  NOT NULL DEFAULT 'Vent Caps (A2)',
    start_time     VARCHAR(20)  DEFAULT NULL,
    elapsed_hours  INT          NOT NULL DEFAULT 0,
    opened_by      VARCHAR(80)  DEFAULT NULL,
    line_target    INT          NOT NULL DEFAULT 480,
    station_target INT          NOT NULL DEFAULT 120,
    pause_reason   VARCHAR(100) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO supervisor_line_sessions
 (id, session_code, status, product_name, start_time, elapsed_hours, opened_by, line_target, station_target, pause_reason) VALUES
(1, 'SES-20261014-01', 'RUNNING', 'Vent Caps (A2)', '06:00 AM', 4, 'Shift Supervisor', 480, 120, '');

CREATE TABLE IF NOT EXISTS supervisor_station_status (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    station_code     VARCHAR(30)  NOT NULL UNIQUE,
    workstation_name VARCHAR(80)  NOT NULL,
    assigned_staff   VARCHAR(80)  DEFAULT NULL,
    status           VARCHAR(10)  NOT NULL DEFAULT 'RUNNING',  -- RUNNING / PAUSED
    good_units       INT          NOT NULL DEFAULT 0,
    ng_units         INT          NOT NULL DEFAULT 0,
    target_units     INT          NOT NULL DEFAULT 120
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO supervisor_station_status
 (id, station_code, workstation_name, assigned_staff, status, good_units, ng_units, target_units) VALUES
(1, 'ESP32-STN01', 'Injection Molding', 'staff1', 'RUNNING', 96, 2, 120),
(2, 'ESP32-STN02', 'Trimming',          'staff2', 'RUNNING', 88, 1, 120),
(3, 'ESP32-STN03', 'Assembly',          'staff3', 'PAUSED',  79, 2, 120),
(4, 'ESP32-STN04', 'Inspection',        'staff4', 'RUNNING', 71, 3, 120);

CREATE TABLE IF NOT EXISTS supervisor_hourly_output (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    hour_label VARCHAR(20) NOT NULL,
    good_units INT NOT NULL DEFAULT 0,
    ng_units   INT NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO supervisor_hourly_output (id, hour_label, good_units, ng_units) VALUES
(1, '6 AM', 78, 2),
(2, '7 AM', 85, 2),
(3, '8 AM', 88, 2),
(4, '9 AM', 83, 2);

CREATE TABLE IF NOT EXISTS supervisor_floor_alerts (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    alert_type VARCHAR(20)  NOT NULL,          -- defect / warning / info
    title      VARCHAR(150) NOT NULL,
    subtitle   VARCHAR(200) DEFAULT NULL,
    alert_time VARCHAR(20)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO supervisor_floor_alerts (id, alert_type, title, subtitle, alert_time) VALUES
(1, 'info',    'Session started',                 'Vent Caps (A2) · target 480 units', '06:00 AM'),
(2, 'defect',  'New defect report: Short shot',   'ESP32-STN04 · Inspection',          '08:12 AM'),
(3, 'warning', 'ESP32-STN03 paused',              'Operator break',                    '08:55 AM'),
(4, 'defect',  'New defect report: Scratch',      'ESP32-STN03 · Assembly',            '08:40 AM');

-- ----------------------------------------------------------------------------
-- 7. MANAGER DASHBOARD / OEE  (get_manager_data.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS oee_plant_metrics (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    overall_oee  DECIMAL(5,1) NOT NULL,
    availability DECIMAL(5,1) NOT NULL,
    performance  DECIMAL(5,1) NOT NULL,
    quality      DECIMAL(5,1) NOT NULL,
    total_output INT NOT NULL,
    good_units   INT NOT NULL,
    ng_units     INT NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oee_plant_metrics (id, overall_oee, availability, performance, quality, total_output, good_units, ng_units) VALUES
(1, 78.4, 88.0, 94.8, 94.0, 13533, 12721, 812);

CREATE TABLE IF NOT EXISTS oee_line_performance (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    line_name    VARCHAR(30)  NOT NULL,
    share_pct    VARCHAR(40)  DEFAULT NULL,
    total_output INT          NOT NULL DEFAULT 0,
    availability DECIMAL(5,1) NOT NULL,
    performance  DECIMAL(5,1) NOT NULL,
    quality      DECIMAL(5,1) NOT NULL,
    oee          DECIMAL(5,1) NOT NULL,
    is_lowest    TINYINT(1)   NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oee_line_performance
 (id, line_name, share_pct, total_output, availability, performance, quality, oee, is_lowest) VALUES
(1, 'Line 1', '38.4% of output', 5200, 90.0, 96.0, 96.0, 82.9, 0),
(2, 'Line 2', '35.5% of output', 4800, 88.0, 95.0, 95.0, 79.4, 0),
(3, 'Line 3', '26.1% of output', 3533, 84.0, 91.0, 93.0, 71.1, 1);

CREATE TABLE IF NOT EXISTS defect_categories (
    id            INT AUTO_INCREMENT PRIMARY KEY,
    category_name VARCHAR(100) NOT NULL,
    count         INT          NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO defect_categories (id, category_name, count) VALUES
(1, 'Short shot', 301),
(2, 'Scratch',    198),
(3, 'Flash',      112),
(4, 'Crack',       79),
(5, 'Warpage',     62),
(6, 'Other',       60);

CREATE TABLE IF NOT EXISTS oee_weekly_trend (
    id         INT AUTO_INCREMENT PRIMARY KEY,
    week_label VARCHAR(20)  NOT NULL,
    oee_value  DECIMAL(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO oee_weekly_trend (id, week_label, oee_value) VALUES
(1, 'Wk 38', 74.2),
(2, 'Wk 39', 76.5),
(3, 'Wk 40', 77.1),
(4, 'Wk 41', 79.0),
(5, 'Wk 42', 78.4);

-- ----------------------------------------------------------------------------
-- 8. ANALYTICS  (get_analytics_data.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS analytics_daily_yield (
    id          INT AUTO_INCREMENT PRIMARY KEY,
    day_label   VARCHAR(20)  NOT NULL,
    yield_value DECIMAL(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO analytics_daily_yield (id, day_label, yield_value) VALUES
(1, 'Oct 08', 96.1),
(2, 'Oct 09', 95.3),
(3, 'Oct 10', 93.2),
(4, 'Oct 11', 94.8),
(5, 'Oct 12', 95.9),
(6, 'Oct 13', 96.4),
(7, 'Oct 14', 94.0);

CREATE TABLE IF NOT EXISTS analytics_root_cause (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    defect_type      VARCHAR(100) NOT NULL,
    factor_name      VARCHAR(100) NOT NULL,
    factor_category  VARCHAR(50)  NOT NULL,
    multiplier       VARCHAR(10)  NOT NULL,
    defect_share_pct DECIMAL(5,1) NOT NULL,
    output_share_pct DECIMAL(5,1) NOT NULL,
    KEY idx_defect (defect_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO analytics_root_cause
 (id, defect_type, factor_name, factor_category, multiplier, defect_share_pct, output_share_pct) VALUES
( 1, 'Short shot', 'Line 3',             'Line',     '2.3x', 61.0, 26.1),
( 2, 'Short shot', 'Shift 2',            'Shift',    '1.5x', 48.0, 33.0),
( 3, 'Short shot', 'Material lot B-117', 'Material', '2.4x', 29.0, 12.0),
( 4, 'Scratch',    'ESP32-STN03',        'Station',  '1.8x', 44.0, 25.0),
( 5, 'Scratch',    'Night shift',        'Shift',    '1.4x', 41.0, 30.0),
( 6, 'Scratch',    'Manual handling',    'Process',  '2.0x', 38.0, 19.0),
( 7, 'Crack',      'Mold temp above 230C','Process', '2.9x', 52.0, 18.0),
( 8, 'Crack',      'Line 3',             'Line',     '2.2x', 58.0, 26.1),
( 9, 'Crack',      'Resin lot R-42',     'Material', '2.4x', 33.0, 14.0),
(10, 'Flash',      'Clamp pressure low', 'Process',  '2.4x', 47.0, 20.0),
(11, 'Flash',      'ESP32-STN01',        'Station',  '1.5x', 38.0, 25.0),
(12, 'Flash',      'Mold over 50k cycles','Equipment','2.3x', 35.0, 15.0),
(13, 'Warpage',    'Cooling time short', 'Process',  '2.5x', 55.0, 22.0),
(14, 'Warpage',    'Line 3',             'Line',     '1.9x', 49.0, 26.1),
(15, 'Warpage',    'Ambient humidity',   'Environment','2.1x',30.0, 14.0);

CREATE TABLE IF NOT EXISTS analytics_report_history (
    id             INT AUTO_INCREMENT PRIMARY KEY,
    title          VARCHAR(150) NOT NULL,
    file_format    VARCHAR(10)  NOT NULL,
    generated_time VARCHAR(40)  NOT NULL,
    download_url   VARCHAR(255) NOT NULL DEFAULT '#'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO analytics_report_history (id, title, file_format, generated_time, download_url) VALUES
(1, 'Weekly OEE Summary - Wk 41',      'PDF',  'Oct 12 · 06:00 AM', '#'),
(2, 'Defect Root-Cause Report - Sep',  'XLSX', 'Oct 01 · 09:30 AM', '#'),
(3, 'Daily Yield Export - Oct 01-07',  'CSV',  'Oct 08 · 08:15 AM', '#');

-- ----------------------------------------------------------------------------
-- 9. MASTER DATA  (process_master_data.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS master_stations (
    id               INT AUTO_INCREMENT PRIMARY KEY,
    station_code     VARCHAR(30)  NOT NULL UNIQUE,
    line_name        VARCHAR(30)  NOT NULL,
    workstation_name VARCHAR(80)  NOT NULL,
    ideal_cycle_sec  INT          NOT NULL DEFAULT 30,
    rate_per_hr      INT          NOT NULL DEFAULT 120,
    status           VARCHAR(20)  NOT NULL DEFAULT 'Online',   -- Online / Maintenance
    last_calibrated  VARCHAR(30)  DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO master_stations
 (id, station_code, line_name, workstation_name, ideal_cycle_sec, rate_per_hr, status, last_calibrated) VALUES
(1, 'ESP32-STN01', 'Line 1', 'Injection Molding', 30, 120, 'Online',      'Oct 02, 2026'),
(2, 'ESP32-STN02', 'Line 1', 'Trimming',          30, 120, 'Online',      'Oct 02, 2026'),
(3, 'ESP32-STN03', 'Line 1', 'Assembly',          36, 100, 'Online',      'Oct 03, 2026'),
(4, 'ESP32-STN04', 'Line 1', 'Inspection',        30, 120, 'Online',      'Oct 03, 2026'),
(5, 'ESP32-STN05', 'Line 2', 'Injection Molding', 30, 120, 'Online',      'Oct 01, 2026'),
(6, 'ESP32-STN06', 'Line 2', 'Inspection',        30, 120, 'Online',      'Oct 01, 2026'),
(7, 'ESP32-STN07', 'Line 3', 'Injection Molding', 30, 120, 'Maintenance', 'Sep 28, 2026');

CREATE TABLE IF NOT EXISTS master_target_defaults (
    id                      INT AUTO_INCREMENT PRIMARY KEY,
    oee_target_pct          DECIMAL(5,1) NOT NULL DEFAULT 85.0,
    yield_target_pct        DECIMAL(5,1) NOT NULL DEFAULT 98.0,
    shift_target_units      INT          NOT NULL DEFAULT 320,
    shift_length_hours      INT          NOT NULL DEFAULT 8,
    availability_target_pct DECIMAL(5,1) NOT NULL DEFAULT 92.0,
    performance_target_pct  DECIMAL(5,1) NOT NULL DEFAULT 96.0,
    quality_target_pct      DECIMAL(5,1) NOT NULL DEFAULT 97.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO master_target_defaults
 (id, oee_target_pct, yield_target_pct, shift_target_units, shift_length_hours,
  availability_target_pct, performance_target_pct, quality_target_pct) VALUES
(1, 85.0, 98.0, 320, 8, 92.0, 96.0, 97.0);

CREATE TABLE IF NOT EXISTS master_shift_schedule (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    shift_name   VARCHAR(60) NOT NULL,
    time_range   VARCHAR(40) NOT NULL,
    target_units INT         NOT NULL DEFAULT 0,
    status       VARCHAR(20) NOT NULL DEFAULT 'Scheduled'      -- Scheduled / Off
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO master_shift_schedule (id, shift_name, time_range, target_units, status) VALUES
(1, 'Shift 1 (Morning)',   '06:00 AM – 02:00 PM', 320, 'Scheduled'),
(2, 'Shift 2 (Afternoon)', '02:00 PM – 10:00 PM', 320, 'Scheduled'),
(3, 'Shift 3 (Night)',     '10:00 PM – 06:00 AM',   0, 'Off');

-- ----------------------------------------------------------------------------
-- 10. SYSTEM SETTINGS  (process_system_settings.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS system_settings (
    id                     INT AUTO_INCREMENT PRIMARY KEY,
    plant_name             VARCHAR(120) NOT NULL DEFAULT 'Masters Vineyard Enterprise',
    time_zone              VARCHAR(60)  NOT NULL DEFAULT 'Asia/Manila (UTC+08:00)',
    time_format            VARCHAR(30)  NOT NULL DEFAULT '12-hour (12:00 PM)',
    alert_defect_30m       TINYINT(1)   NOT NULL DEFAULT 1,
    alert_line_stop_15m    TINYINT(1)   NOT NULL DEFAULT 1,
    alert_oee_under_target TINYINT(1)   NOT NULL DEFAULT 1,
    alert_station_offline  TINYINT(1)   NOT NULL DEFAULT 1,
    alert_weekly_summary   TINYINT(1)   NOT NULL DEFAULT 0,
    inactivity_timeout     VARCHAR(20)  NOT NULL DEFAULT '30 minutes',
    min_password_len       INT          NOT NULL DEFAULT 10,
    password_expiry        VARCHAR(20)  NOT NULL DEFAULT '90 days',
    require_2fa_managers   TINYINT(1)   NOT NULL DEFAULT 0,
    history_retention      VARCHAR(20)  NOT NULL DEFAULT '24 months',
    weekly_export_schedule VARCHAR(40)  NOT NULL DEFAULT 'Monday, 06:00 AM',
    checkin_interval_sec   INT          NOT NULL DEFAULT 10,
    offline_threshold_sec  INT          NOT NULL DEFAULT 60
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings (id) VALUES (1);   -- all defaults above

CREATE TABLE IF NOT EXISTS system_settings_history (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    log_timestamp      VARCHAR(40)  NOT NULL,
    actor              VARCHAR(100) NOT NULL,
    action_description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO system_settings_history (id, log_timestamp, actor, action_description) VALUES
(1, 'Oct 06 · 02:15 PM', 'Production Manager', 'set ESP32-STN07 to maintenance'),
(2, 'Oct 01 · 09:00 AM', 'Production Manager', 'updated plant-wide system settings');

-- ----------------------------------------------------------------------------
-- 11. USER MANAGEMENT  (process_user_management.php)
-- ----------------------------------------------------------------------------
CREATE TABLE IF NOT EXISTS user_access_requests (
    id              INT AUTO_INCREMENT PRIMARY KEY,
    username        VARCHAR(50) NOT NULL,
    requested_role  VARCHAR(20) NOT NULL,
    assignment_line VARCHAR(30) NOT NULL,
    requested_by    VARCHAR(100) NOT NULL,
    request_date    VARCHAR(30) NOT NULL,
    status          VARCHAR(20) NOT NULL DEFAULT 'Pending'     -- Pending / Approved / Declined
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO user_access_requests (id, username, requested_role, assignment_line, requested_by, request_date, status) VALUES
(1, 'operator_new1', 'Operator',   'Line 2', 'Shift Supervisor', 'Oct 12', 'Pending'),
(2, 'operator_new2', 'Operator',   'Line 3', 'Shift Supervisor', 'Oct 13', 'Pending');

CREATE TABLE IF NOT EXISTS user_access_changes (
    id                 INT AUTO_INCREMENT PRIMARY KEY,
    log_timestamp      VARCHAR(40)  NOT NULL,
    actor              VARCHAR(100) NOT NULL,
    action_description VARCHAR(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT IGNORE INTO user_access_changes (id, log_timestamp, actor, action_description) VALUES
(1, 'Oct 08 · 10:05 AM', 'Production Manager', 'created account for supervisor1 (supervisor)'),
(2, 'Oct 08 · 10:07 AM', 'Production Manager', 'created account for staff1 (staff)');

-- ----------------------------------------------------------------------------
-- 12. OPTIONAL: auto-fill report_code (DR-0007, DR-0008 ...) for new defect reports.
--     process_defect.php does not set report_code, so without this the supervisor
--     queue shows "null" next to new reports. Keep this block LAST: if your import
--     tool rejects DELIMITER, everything above is already saved.
-- ----------------------------------------------------------------------------
DROP TRIGGER IF EXISTS trg_defect_reports_code;
DELIMITER $$
CREATE TRIGGER trg_defect_reports_code BEFORE INSERT ON defect_reports
FOR EACH ROW
BEGIN
    IF NEW.report_code IS NULL THEN
        SET NEW.report_code = CONCAT('DR-', LPAD((SELECT COALESCE(MAX(id), 0) + 1 FROM defect_reports), 4, '0'));
    END IF;
END$$
DELIMITER ;

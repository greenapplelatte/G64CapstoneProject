-- phpMyAdmin SQL Dump
-- version 5.2.1
-- https://www.phpmyadmin.net/
--
-- Host: 127.0.0.1
-- Generation Time: Oct 19, 2026 at 10:09 AM
-- Server version: 10.4.32-MariaDB
-- PHP Version: 8.2.12

SET SQL_MODE = "NO_AUTO_VALUE_ON_ZERO";
START TRANSACTION;
SET time_zone = "+00:00";


/*!40101 SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT */;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
/*!40101 SET @OLD_COLLATION_CONNECTION=@@COLLATION_CONNECTION */;
/*!40101 SET NAMES utf8mb4 */;

--
-- Database: `g64capstoneproject`
--

DELIMITER $$
--
-- Procedures
--
CREATE DEFINER=`root`@`localhost` PROCEDURE `cleanup_six_closed_shift_records` ()   BEGIN
    DECLARE n INT DEFAULT 0;
    DECLARE remaining INT DEFAULT 0;
    DECLARE other_refs INT DEFAULT 0;
    DECLARE active_refs INT DEFAULT 0;

    DECLARE EXIT HANDLER FOR SQLEXCEPTION
    BEGIN
        ROLLBACK;
        RESIGNAL;
    END;

    -- Check for unexpected foreign key relationships
    SELECT COUNT(*) INTO other_refs
    FROM information_schema.KEY_COLUMN_USAGE
    WHERE REFERENCED_TABLE_SCHEMA = DATABASE()
      AND REFERENCED_TABLE_NAME = 'shift_records'
      AND TABLE_NAME <> 'shift_workstation_yield';

    IF other_refs > 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Unexpected foreign key dependency. Cleanup cancelled.';
    END IF;

    -- Create backup tables
    CREATE TABLE IF NOT EXISTS
        backup_shift_records_before_cleanup
    LIKE shift_records;

    CREATE TABLE IF NOT EXISTS
        backup_shift_workstation_yield_before_cleanup
    LIKE shift_workstation_yield;

    START TRANSACTION;

    -- Identify the six CLOSED shift records
    DROP TEMPORARY TABLE IF EXISTS cleanup_target_shift_ids;

    CREATE TEMPORARY TABLE cleanup_target_shift_ids (
        id INT PRIMARY KEY
    ) ENGINE=MEMORY;

    INSERT INTO cleanup_target_shift_ids (id)
    SELECT id
    FROM shift_records
    WHERE status = 'CLOSED'
    ORDER BY shift_date DESC, id DESC
    LIMIT 6;

    SELECT COUNT(*) INTO n
    FROM cleanup_target_shift_ids;

    IF n <> 6 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Expected exactly six CLOSED records. Cleanup cancelled.';
    END IF;

    -- Confirm no OPEN shifts are selected
    SELECT COUNT(*) INTO active_refs
    FROM shift_records s
    JOIN cleanup_target_shift_ids t ON t.id = s.id
    WHERE s.status <> 'CLOSED';

    IF active_refs <> 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'An active shift was selected. Cleanup cancelled.';
    END IF;

    -- Back up selected shift records
    INSERT INTO backup_shift_records_before_cleanup
    SELECT s.*
    FROM shift_records s
    JOIN cleanup_target_shift_ids t ON t.id = s.id
    WHERE NOT EXISTS (
        SELECT 1
        FROM backup_shift_records_before_cleanup b
        WHERE b.id = s.id
    );

    -- Back up related workstation yield records
    INSERT INTO backup_shift_workstation_yield_before_cleanup
    SELECT w.*
    FROM shift_workstation_yield w
    JOIN cleanup_target_shift_ids t
        ON t.id = w.shift_record_id
    WHERE NOT EXISTS (
        SELECT 1
        FROM backup_shift_workstation_yield_before_cleanup b
        WHERE b.id = w.id
    );

    -- Delete related workstation yield rows
    DELETE w
    FROM shift_workstation_yield w
    JOIN cleanup_target_shift_ids t
        ON t.id = w.shift_record_id;

    -- Delete only selected CLOSED shifts
    DELETE s
    FROM shift_records s
    JOIN cleanup_target_shift_ids t ON t.id = s.id
    WHERE s.status = 'CLOSED';

    -- Verify deletion
    SELECT COUNT(*) INTO remaining
    FROM shift_records s
    JOIN cleanup_target_shift_ids t ON t.id = s.id;

    IF remaining <> 0 THEN
        SIGNAL SQLSTATE '45000'
        SET MESSAGE_TEXT = 'Cleanup incomplete. Changes rolled back.';
    END IF;

    COMMIT;

    SELECT
        'SUCCESS: Six CLOSED shift records removed. OPEN sessions preserved.'
        AS result;

END$$

DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `analytics_daily_yield`
--

CREATE TABLE `analytics_daily_yield` (
  `id` int(11) NOT NULL,
  `day_label` varchar(20) NOT NULL,
  `yield_value` decimal(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_daily_yield`
--

INSERT INTO `analytics_daily_yield` (`id`, `day_label`, `yield_value`) VALUES
(1, 'Oct 08', 96.1),
(2, 'Oct 09', 95.3),
(3, 'Oct 10', 93.2),
(4, 'Oct 11', 94.8),
(5, 'Oct 12', 95.9),
(6, 'Oct 13', 96.4),
(7, 'Oct 14', 97.4),
(8, 'Oct 19', 91.7);

-- --------------------------------------------------------

--
-- Table structure for table `analytics_report_history`
--

CREATE TABLE `analytics_report_history` (
  `id` int(11) NOT NULL,
  `title` varchar(150) NOT NULL,
  `file_format` varchar(10) NOT NULL,
  `generated_time` varchar(40) NOT NULL,
  `download_url` varchar(255) NOT NULL DEFAULT '#'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_report_history`
--

INSERT INTO `analytics_report_history` (`id`, `title`, `file_format`, `generated_time`, `download_url`) VALUES
(1, 'Weekly OEE Summary - Wk 41', 'PDF', 'Oct 12 · 06:00 AM', '#'),
(2, 'Defect Root-Cause Report - Sep', 'XLSX', 'Oct 01 · 09:30 AM', '#'),
(3, 'Daily Yield Export - Oct 01-07', 'CSV', 'Oct 08 · 08:15 AM', '#');

-- --------------------------------------------------------

--
-- Table structure for table `analytics_root_cause`
--

CREATE TABLE `analytics_root_cause` (
  `id` int(11) NOT NULL,
  `defect_type` varchar(100) NOT NULL,
  `factor_name` varchar(100) NOT NULL,
  `factor_category` varchar(50) NOT NULL,
  `multiplier` varchar(10) NOT NULL,
  `defect_share_pct` decimal(5,1) NOT NULL,
  `output_share_pct` decimal(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `analytics_root_cause`
--

INSERT INTO `analytics_root_cause` (`id`, `defect_type`, `factor_name`, `factor_category`, `multiplier`, `defect_share_pct`, `output_share_pct`) VALUES
(1, 'Short shot', 'Line 3', 'Line', '2.3x', 61.0, 26.1),
(2, 'Short shot', 'Shift 2', 'Shift', '1.5x', 48.0, 33.0),
(3, 'Short shot', 'Material lot B-117', 'Material', '2.4x', 29.0, 12.0),
(4, 'Scratch', 'ESP32-STN03', 'Station', '1.8x', 44.0, 25.0),
(5, 'Scratch', 'Night shift', 'Shift', '1.4x', 41.0, 30.0),
(6, 'Scratch', 'Manual handling', 'Process', '2.0x', 38.0, 19.0),
(7, 'Crack', 'Mold temp above 230C', 'Process', '2.9x', 52.0, 18.0),
(8, 'Crack', 'Line 3', 'Line', '2.2x', 58.0, 26.1),
(9, 'Crack', 'Resin lot R-42', 'Material', '2.4x', 33.0, 14.0),
(10, 'Flash', 'Clamp pressure low', 'Process', '2.4x', 47.0, 20.0),
(11, 'Flash', 'ESP32-STN01', 'Station', '1.5x', 38.0, 25.0),
(12, 'Flash', 'Mold over 50k cycles', 'Equipment', '2.3x', 35.0, 15.0),
(13, 'Warpage', 'Cooling time short', 'Process', '2.5x', 55.0, 22.0),
(14, 'Warpage', 'Line 3', 'Line', '1.9x', 49.0, 26.1),
(15, 'Warpage', 'Ambient humidity', 'Environment', '2.1x', 30.0, 14.0);

-- --------------------------------------------------------

--
-- Table structure for table `backup_shift_records_before_cleanup`
--

CREATE TABLE `backup_shift_records_before_cleanup` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `shift_title` varchar(50) NOT NULL,
  `shift_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `time_range` varchar(40) DEFAULT NULL,
  `produced_units` int(11) NOT NULL DEFAULT 0,
  `good_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `rejected_units` int(11) NOT NULL DEFAULT 0,
  `pending_units` int(11) NOT NULL DEFAULT 0,
  `yield_rate` decimal(5,2) NOT NULL DEFAULT 100.00,
  `yield_pct` decimal(5,1) NOT NULL DEFAULT 100.0,
  `status` varchar(10) NOT NULL DEFAULT 'OPEN',
  `planned_minutes` int(11) DEFAULT NULL,
  `run_minutes` int(11) DEFAULT NULL,
  `line_target` int(11) NOT NULL DEFAULT 0,
  `closeout_comment` text DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_shift_records_before_cleanup`
--

INSERT INTO `backup_shift_records_before_cleanup` (`id`, `staff_id`, `shift_title`, `shift_date`, `time_start`, `time_end`, `time_range`, `produced_units`, `good_units`, `ng_units`, `rejected_units`, `pending_units`, `yield_rate`, `yield_pct`, `status`, `planned_minutes`, `run_minutes`, `line_target`, `closeout_comment`, `session_id`) VALUES
(1, 3, 'Shift 1', '2026-10-14', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 453, 441, 12, 12, 0, 97.35, 97.4, 'CLOSED', 240, 240, 480, '', 1),
(2, 3, 'Shift 1', '2026-10-13', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 468, 459, 9, 9, 0, 98.08, 98.1, 'CLOSED', NULL, NULL, 0, NULL, NULL),
(3, 3, 'Shift 1', '2026-10-12', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 455, 441, 14, 14, 0, 96.92, 96.9, 'CLOSED', NULL, NULL, 0, NULL, NULL),
(4, 3, 'Shift 1', '2026-10-09', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 472, 466, 6, 6, 0, 98.73, 98.7, 'CLOSED', NULL, NULL, 0, NULL, NULL),
(5, 3, 'Shift 1', '2026-10-08', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 449, 437, 12, 12, 0, 97.33, 97.3, 'CLOSED', NULL, NULL, 0, NULL, NULL),
(6, 3, 'Shift 1', '2026-10-07', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 460, 448, 12, 12, 0, 97.39, 97.4, 'CLOSED', NULL, NULL, 0, NULL, NULL);

-- --------------------------------------------------------

--
-- Table structure for table `backup_shift_workstation_yield_before_cleanup`
--

CREATE TABLE `backup_shift_workstation_yield_before_cleanup` (
  `id` int(11) NOT NULL,
  `shift_record_id` int(11) NOT NULL,
  `station_code` varchar(30) NOT NULL,
  `workstation_name` varchar(80) NOT NULL,
  `output_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `pending_units` int(11) NOT NULL DEFAULT 0,
  `yield_pct` decimal(5,1) NOT NULL DEFAULT 100.0,
  `is_lowest` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `backup_shift_workstation_yield_before_cleanup`
--

INSERT INTO `backup_shift_workstation_yield_before_cleanup` (`id`, `shift_record_id`, `station_code`, `workstation_name`, `output_units`, `ng_units`, `pending_units`, `yield_pct`, `is_lowest`) VALUES
(1, 1, 'ESP32-STN01', 'Injection Molding', 194, 2, 0, 99.0, 0),
(2, 1, 'ESP32-STN02', 'Trimming', 88, 1, 0, 98.9, 0),
(3, 1, 'ESP32-STN03', 'Assembly', 79, 5, 0, 94.0, 1),
(4, 1, 'ESP32-STN04', 'Inspection', 80, 4, 0, 95.2, 0);

-- --------------------------------------------------------

--
-- Table structure for table `defect_categories`
--

CREATE TABLE `defect_categories` (
  `id` int(11) NOT NULL,
  `category_name` varchar(100) NOT NULL,
  `count` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `defect_categories`
--

INSERT INTO `defect_categories` (`id`, `category_name`, `count`) VALUES
(1, 'Short shot', 301),
(2, 'Scratch', 198),
(3, 'Flash', 112),
(4, 'Crack', 79),
(5, 'Warpage', 62),
(6, 'Other', 60);

-- --------------------------------------------------------

--
-- Table structure for table `defect_logs`
--

CREATE TABLE `defect_logs` (
  `id` int(11) NOT NULL,
  `session_id` int(11) DEFAULT NULL,
  `defect_type` varchar(100) NOT NULL,
  `severity` enum('low','medium','high') DEFAULT 'medium',
  `reported_by` int(11) DEFAULT NULL,
  `timestamp` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

-- --------------------------------------------------------

--
-- Table structure for table `defect_reports`
--

CREATE TABLE `defect_reports` (
  `id` int(11) NOT NULL,
  `report_code` varchar(20) DEFAULT NULL,
  `project_type` varchar(100) DEFAULT NULL,
  `workstation` varchar(100) DEFAULT NULL,
  `station` varchar(30) DEFAULT 'ESP32-STN04',
  `defect_category` varchar(100) NOT NULL,
  `quantity_rejected` int(11) NOT NULL DEFAULT 1,
  `severity` varchar(20) NOT NULL DEFAULT 'Minor',
  `notes` text DEFAULT NULL,
  `image_path` varchar(255) DEFAULT NULL,
  `reported_by` varchar(100) DEFAULT NULL,
  `reported_by_id` int(11) DEFAULT NULL,
  `report_time` varchar(20) DEFAULT NULL,
  `status` varchar(30) NOT NULL DEFAULT 'Pending',
  `supervisor_comment` text DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `operator_notes` text GENERATED ALWAYS AS (`notes`) VIRTUAL,
  `photo_url` varchar(300) GENERATED ALWAYS AS (if(`image_path` is null or `image_path` = '',NULL,concat('/G64CapstoneProject/',`image_path`))) VIRTUAL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `defect_reports`
--

INSERT INTO `defect_reports` (`id`, `report_code`, `project_type`, `workstation`, `station`, `defect_category`, `quantity_rejected`, `severity`, `notes`, `image_path`, `reported_by`, `reported_by_id`, `report_time`, `status`, `supervisor_comment`, `created_at`) VALUES
(1, 'DR-0001', 'Vent Caps (A2)', 'Inspection', 'ESP32-STN04', 'Short shot', 1, 'Major', 'Cap not fully formed at the rim.', NULL, 'Line Operator', NULL, '08:12:00', 'Confirmed', '', '2026-10-18 21:30:08'),
(2, 'DR-0002', 'Vent Caps (A2)', 'Assembly', 'ESP32-STN03', 'Scratch', 2, 'Minor', 'Surface scratches after trimming.', NULL, 'staff3', NULL, '08:40:00', 'Confirmed', '', '2026-10-18 22:00:08'),
(3, 'DR-0003', 'Vent Caps (A2)', 'Injection Molding', 'ESP32-STN01', 'Flash', 3, 'Major', 'Excess material on parting line.', NULL, 'staff1', NULL, '07:20:00', 'Confirmed', 'Confirmed, adjust clamp.', '2026-10-18 20:30:08'),
(4, 'DR-0004', 'Vent Caps (A2)', 'Trimming', 'ESP32-STN02', 'Crack', 1, 'Critical', 'Hairline crack near the vent hole.', NULL, 'staff2', NULL, '07:05:00', 'Confirmed', 'Pulled lot for check.', '2026-10-18 20:10:08'),
(5, 'DR-0005', 'Vent Caps (A2)', 'Inspection', 'ESP32-STN04', 'Warpage', 1, 'Minor', 'Slight bend, within tolerance on re-measure.', NULL, 'Line Operator', NULL, '06:50:00', 'Rejected', 'Within tolerance.', '2026-10-18 19:50:08'),
(6, 'DR-0006', 'Vent Caps (A2)', 'Assembly', 'ESP32-STN03', 'Scratch', 1, 'Minor', 'Possible handling mark, please recheck.', NULL, 'staff3', NULL, '09:05:00', 'Confirmed', 'Photo is unclear.', '2026-10-18 22:30:08'),
(7, 'DR-0007', 'Vent Caps (A2)', 'Injection Molding', 'ESP32-STN01', 'Flash', 1, 'Critical', 'tite', NULL, 'Line Operator', 3, '11:55:51', 'Confirmed', 'uy gagu batus', '2026-10-19 03:55:51'),
(8, 'DR-0008', 'Thailand Socket Cover', 'Inspection', 'ESP32-STN01', 'Flash', 1, 'Critical', '', NULL, 'Line Operator', 3, '12:03:28', 'Confirmed', '', '2026-10-19 04:03:28');

--
-- Triggers `defect_reports`
--
DELIMITER $$
CREATE TRIGGER `trg_defect_reports_code` BEFORE INSERT ON `defect_reports` FOR EACH ROW BEGIN
    IF NEW.report_code IS NULL THEN
        SET NEW.report_code = CONCAT('DR-', LPAD((SELECT COALESCE(MAX(id), 0) + 1 FROM defect_reports), 4, '0'));
    END IF;
END
$$
DELIMITER ;

-- --------------------------------------------------------

--
-- Table structure for table `login_attempts`
--

CREATE TABLE `login_attempts` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `ip` varchar(45) NOT NULL,
  `attempted_at` datetime NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `login_attempts`
--

INSERT INTO `login_attempts` (`id`, `username`, `ip`, `attempted_at`) VALUES
(27, 'supervidor123', '::1', '2026-10-19 13:35:44'),
(28, 'Fred', '::1', '2026-10-19 13:37:03'),
(29, 'Fred', '::1', '2026-10-19 14:04:44'),
(30, 'Fred', '::1', '2026-10-19 14:05:04'),
(32, 'staff', '::1', '2026-10-19 14:10:18'),
(33, 'staff123', '::1', '2026-10-19 14:58:50');

-- --------------------------------------------------------

--
-- Table structure for table `master_lines`
--

CREATE TABLE `master_lines` (
  `id` int(11) NOT NULL,
  `line_name` varchar(30) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_lines`
--

INSERT INTO `master_lines` (`id`, `line_name`) VALUES
(1, 'Line 1'),
(2, 'Line 2'),
(3, 'Line 3');

-- --------------------------------------------------------

--
-- Table structure for table `master_project_types`
--

CREATE TABLE `master_project_types` (
  `id` int(11) NOT NULL,
  `project_type` varchar(100) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_project_types`
--

INSERT INTO `master_project_types` (`id`, `project_type`, `is_active`) VALUES
(1, 'Vent Caps (A2)', 1),
(2, 'Thailand Socket Cover', 1);

-- --------------------------------------------------------

--
-- Table structure for table `master_shift_schedule`
--

CREATE TABLE `master_shift_schedule` (
  `id` int(11) NOT NULL,
  `shift_name` varchar(60) NOT NULL,
  `time_range` varchar(40) NOT NULL,
  `target_units` int(11) NOT NULL DEFAULT 0,
  `status` varchar(20) NOT NULL DEFAULT 'Scheduled',
  `start_time` time DEFAULT NULL,
  `end_time` time DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_shift_schedule`
--

INSERT INTO `master_shift_schedule` (`id`, `shift_name`, `time_range`, `target_units`, `status`, `start_time`, `end_time`) VALUES
(1, 'Shift 1 (Morning)', '06:00 AM – 02:00 PM', 320, 'Scheduled', '06:00:00', '14:00:00'),
(2, 'Shift 2 (Afternoon)', '02:00 PM – 10:00 PM', 320, 'Scheduled', '14:00:00', '22:00:00'),
(3, 'Shift 3 (Night)', '10:00 PM – 06:00 AM', 0, 'Off', '22:00:00', '06:00:00');

-- --------------------------------------------------------

--
-- Table structure for table `master_stations`
--

CREATE TABLE `master_stations` (
  `id` int(11) NOT NULL,
  `station_code` varchar(30) NOT NULL,
  `line_name` varchar(30) NOT NULL,
  `workstation_name` varchar(80) NOT NULL,
  `ideal_cycle_sec` int(11) NOT NULL DEFAULT 30,
  `rate_per_hr` int(11) NOT NULL DEFAULT 120,
  `status` varchar(20) NOT NULL DEFAULT 'Online',
  `last_calibrated` varchar(30) DEFAULT NULL,
  `last_seen` datetime DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_stations`
--

INSERT INTO `master_stations` (`id`, `station_code`, `line_name`, `workstation_name`, `ideal_cycle_sec`, `rate_per_hr`, `status`, `last_calibrated`, `last_seen`) VALUES
(1, 'ESP32-STN01', 'Line 1', 'Injection Molding', 30, 120, 'Online', 'Oct 02, 2026', NULL),
(2, 'ESP32-STN02', 'Line 1', 'Trimming', 30, 120, 'Online', 'Oct 02, 2026', NULL),
(3, 'ESP32-STN03', 'Line 1', 'Assembly', 36, 100, 'Online', 'Oct 03, 2026', NULL),
(4, 'ESP32-STN04', 'Line 1', 'Inspection', 30, 120, 'Online', 'Oct 03, 2026', NULL),
(5, 'ESP32-STN05', 'Line 2', 'Injection Molding', 30, 120, 'Online', 'Oct 01, 2026', NULL),
(6, 'ESP32-STN06', 'Line 2', 'Inspection', 30, 120, 'Online', 'Oct 01, 2026', NULL),
(7, 'ESP32-STN07', 'Line 3', 'Injection Molding', 30, 120, 'Maintenance', 'Sep 28, 2026', NULL);

-- --------------------------------------------------------

--
-- Table structure for table `master_target_defaults`
--

CREATE TABLE `master_target_defaults` (
  `id` int(11) NOT NULL,
  `oee_target_pct` decimal(5,1) NOT NULL DEFAULT 85.0,
  `yield_target_pct` decimal(5,1) NOT NULL DEFAULT 98.0,
  `shift_target_units` int(11) NOT NULL DEFAULT 320,
  `shift_length_hours` int(11) NOT NULL DEFAULT 8,
  `availability_target_pct` decimal(5,1) NOT NULL DEFAULT 92.0,
  `performance_target_pct` decimal(5,1) NOT NULL DEFAULT 96.0,
  `quality_target_pct` decimal(5,1) NOT NULL DEFAULT 97.0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_target_defaults`
--

INSERT INTO `master_target_defaults` (`id`, `oee_target_pct`, `yield_target_pct`, `shift_target_units`, `shift_length_hours`, `availability_target_pct`, `performance_target_pct`, `quality_target_pct`) VALUES
(1, 85.0, 98.0, 320, 8, 92.0, 96.0, 97.0);

-- --------------------------------------------------------

--
-- Table structure for table `master_workstations`
--

CREATE TABLE `master_workstations` (
  `id` int(11) NOT NULL,
  `workstation_name` varchar(80) NOT NULL,
  `is_active` tinyint(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `master_workstations`
--

INSERT INTO `master_workstations` (`id`, `workstation_name`, `is_active`) VALUES
(1, 'Injection Molding', 1),
(2, 'Trimming', 1),
(3, 'Assembly', 1),
(4, 'Inspection', 1);

-- --------------------------------------------------------

--
-- Table structure for table `oee_line_performance`
--

CREATE TABLE `oee_line_performance` (
  `id` int(11) NOT NULL,
  `line_name` varchar(30) NOT NULL,
  `share_pct` varchar(40) DEFAULT NULL,
  `total_output` int(11) NOT NULL DEFAULT 0,
  `availability` decimal(5,1) NOT NULL,
  `performance` decimal(5,1) NOT NULL,
  `quality` decimal(5,1) NOT NULL,
  `oee` decimal(5,1) NOT NULL,
  `is_lowest` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oee_line_performance`
--

INSERT INTO `oee_line_performance` (`id`, `line_name`, `share_pct`, `total_output`, `availability`, `performance`, `quality`, `oee`, `is_lowest`) VALUES
(1, 'Line 1', '0.3% of output', 24, 22.1, 31.6, 91.7, 6.4, 1),
(2, 'Line 2', '57.4% of output', 4800, 88.0, 95.0, 95.0, 79.4, 0),
(3, 'Line 3', '42.3% of output', 3533, 84.0, 91.0, 93.0, 71.1, 0);

-- --------------------------------------------------------

--
-- Table structure for table `oee_plant_metrics`
--

CREATE TABLE `oee_plant_metrics` (
  `id` int(11) NOT NULL,
  `overall_oee` decimal(5,1) NOT NULL,
  `availability` decimal(5,1) NOT NULL,
  `performance` decimal(5,1) NOT NULL,
  `quality` decimal(5,1) NOT NULL,
  `total_output` int(11) NOT NULL,
  `good_units` int(11) NOT NULL,
  `ng_units` int(11) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oee_plant_metrics`
--

INSERT INTO `oee_plant_metrics` (`id`, `overall_oee`, `availability`, `performance`, `quality`, `total_output`, `good_units`, `ng_units`) VALUES
(1, 78.4, 88.0, 94.8, 94.0, 13533, 12721, 812),
(2, 24.0, 100.0, 24.6, 97.4, 453, 441, 12),
(3, 6.4, 22.1, 31.6, 91.7, 24, 22, 2);

-- --------------------------------------------------------

--
-- Table structure for table `oee_weekly_trend`
--

CREATE TABLE `oee_weekly_trend` (
  `id` int(11) NOT NULL,
  `week_label` varchar(20) NOT NULL,
  `oee_value` decimal(5,1) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `oee_weekly_trend`
--

INSERT INTO `oee_weekly_trend` (`id`, `week_label`, `oee_value`) VALUES
(1, 'Wk 38', 74.2),
(2, 'Wk 39', 76.5),
(3, 'Wk 40', 77.1),
(4, 'Wk 41', 79.0),
(5, 'Wk 42', 78.4),
(6, 'Wk 43', 6.4);

-- --------------------------------------------------------

--
-- Table structure for table `production_sessions`
--

CREATE TABLE `production_sessions` (
  `id` int(11) NOT NULL,
  `machine_id` varchar(50) NOT NULL,
  `target_goal` int(11) NOT NULL,
  `good_count` int(11) DEFAULT 0,
  `ng_count` int(11) DEFAULT 0,
  `status` enum('active','paused','completed') DEFAULT 'active',
  `created_by` int(11) DEFAULT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp()
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `production_sessions`
--

INSERT INTO `production_sessions` (`id`, `machine_id`, `target_goal`, `good_count`, `ng_count`, `status`, `created_by`, `created_at`) VALUES
(1, 'ESP32-STN04', 112, 80, 4, 'completed', 3, '2026-10-14 00:13:32'),
(2, 'ESP32-STN03', 120, 0, 3, 'completed', NULL, '2026-10-18 23:09:52'),
(3, 'ESP32-STN01', 120, 98, 0, 'completed', NULL, '2026-10-18 23:52:41'),
(4, 'ESP32-STN01', 120, 22, 2, 'completed', 2, '2026-10-19 03:55:14'),
(5, 'ESP32-STN02', 120, 0, 0, 'completed', 2, '2026-10-19 03:55:14'),
(6, 'ESP32-STN03', 120, 0, 0, 'completed', 2, '2026-10-19 03:55:14'),
(7, 'ESP32-STN04', 120, 0, 0, 'completed', 2, '2026-10-19 03:55:14'),
(8, 'ESP32-STN01', 120, 26, 0, 'active', 2, '2026-10-19 06:48:27'),
(9, 'ESP32-STN02', 120, 0, 0, 'active', 2, '2026-10-19 06:48:28'),
(10, 'ESP32-STN03', 120, 0, 0, 'active', 2, '2026-10-19 06:48:28'),
(11, 'ESP32-STN04', 120, 0, 0, 'active', 2, '2026-10-19 06:48:28');

-- --------------------------------------------------------

--
-- Table structure for table `shift_records`
--

CREATE TABLE `shift_records` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) DEFAULT NULL,
  `shift_title` varchar(50) NOT NULL,
  `shift_date` date NOT NULL,
  `time_start` time NOT NULL,
  `time_end` time NOT NULL,
  `time_range` varchar(40) DEFAULT NULL,
  `produced_units` int(11) NOT NULL DEFAULT 0,
  `good_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `rejected_units` int(11) NOT NULL DEFAULT 0,
  `pending_units` int(11) NOT NULL DEFAULT 0,
  `yield_rate` decimal(5,2) NOT NULL DEFAULT 100.00,
  `yield_pct` decimal(5,1) NOT NULL DEFAULT 100.0,
  `status` varchar(10) NOT NULL DEFAULT 'OPEN',
  `planned_minutes` int(11) DEFAULT NULL,
  `run_minutes` int(11) DEFAULT NULL,
  `line_target` int(11) NOT NULL DEFAULT 0,
  `closeout_comment` text DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_records`
--

INSERT INTO `shift_records` (`id`, `staff_id`, `shift_title`, `shift_date`, `time_start`, `time_end`, `time_range`, `produced_units`, `good_units`, `ng_units`, `rejected_units`, `pending_units`, `yield_rate`, `yield_pct`, `status`, `planned_minutes`, `run_minutes`, `line_target`, `closeout_comment`, `session_id`) VALUES
(7, NULL, 'Shift 1 (Morning)', '2026-10-19', '06:00:00', '14:00:00', '06:00 AM – 02:00 PM', 24, 22, 2, 2, 0, 91.67, 91.7, 'CLOSED', 172, 38, 480, '', 2),
(8, NULL, 'Shift 2 (Afternoon)', '2026-10-19', '14:00:00', '22:00:00', '02:00 PM – 10:00 PM', 26, 26, 0, 0, 0, 100.00, 100.0, 'OPEN', NULL, NULL, 480, NULL, 3);

-- --------------------------------------------------------

--
-- Table structure for table `shift_workstation_yield`
--

CREATE TABLE `shift_workstation_yield` (
  `id` int(11) NOT NULL,
  `shift_record_id` int(11) NOT NULL,
  `station_code` varchar(30) NOT NULL,
  `workstation_name` varchar(80) NOT NULL,
  `output_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `pending_units` int(11) NOT NULL DEFAULT 0,
  `yield_pct` decimal(5,1) NOT NULL DEFAULT 100.0,
  `is_lowest` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `shift_workstation_yield`
--

INSERT INTO `shift_workstation_yield` (`id`, `shift_record_id`, `station_code`, `workstation_name`, `output_units`, `ng_units`, `pending_units`, `yield_pct`, `is_lowest`) VALUES
(5, 7, 'ESP32-STN01', 'Injection Molding', 22, 2, 0, 91.7, 1),
(6, 8, 'ESP32-STN01', 'Injection Molding', 26, 0, 0, 100.0, 0);

-- --------------------------------------------------------

--
-- Table structure for table `staff_activity_logs`
--

CREATE TABLE `staff_activity_logs` (
  `id` int(11) NOT NULL,
  `staff_id` int(11) NOT NULL,
  `log_time` time NOT NULL,
  `log_type` varchar(20) NOT NULL,
  `details` varchar(255) NOT NULL,
  `station_id` varchar(30) DEFAULT NULL,
  `log_date` date DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `staff_activity_logs`
--

INSERT INTO `staff_activity_logs` (`id`, `staff_id`, `log_time`, `log_type`, `details`, `station_id`, `log_date`) VALUES
(1, 3, '06:00:00', 'Shift', 'Shift 1 started', 'ESP32-STN04', '2026-10-14'),
(2, 3, '06:01:00', 'System', 'Station ESP32-STN04 connected', 'ESP32-STN04', '2026-10-14'),
(3, 3, '07:00:00', 'Count', 'Hourly count logged: 17 good, 0 NG', 'ESP32-STN04', '2026-10-14'),
(4, 3, '08:12:00', 'Defect', 'Defect report submitted: Short shot (1 unit)', 'ESP32-STN04', '2026-10-14'),
(5, 3, '08:00:00', 'Count', 'Hourly count logged: 18 good, 1 NG', 'ESP32-STN04', '2026-10-14'),
(6, 3, '09:00:00', 'Count', 'Hourly count logged: 17 good, 1 NG', 'ESP32-STN04', '2026-10-14'),
(7, 3, '09:30:00', 'System', 'Auto-counter sensor check passed', 'ESP32-STN04', '2026-10-14'),
(8, 3, '10:00:00', 'Count', 'Hourly count logged: 19 good, 1 NG', 'ESP32-STN04', '2026-10-14'),
(9, 3, '07:08:08', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(10, 3, '07:08:09', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(11, 3, '07:08:11', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(12, 3, '07:08:12', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(13, 3, '07:09:48', 'Defect', 'Defect report #1 confirmed', 'ESP32-STN04', '2026-10-19'),
(14, 3, '07:50:16', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(15, 3, '07:50:17', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(16, 3, '07:50:17', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(17, 3, '07:50:18', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(18, 3, '07:50:19', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN04', '2026-10-19'),
(19, 3, '07:52:41', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(20, 3, '07:52:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(21, 3, '07:52:48', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(22, 3, '07:52:49', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(23, 3, '07:52:50', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(24, 3, '07:52:51', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(25, 3, '07:52:51', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(26, 3, '07:52:52', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(27, 3, '07:52:52', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(28, 3, '07:52:53', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(29, 3, '07:52:53', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(30, 3, '07:52:54', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(31, 3, '07:52:54', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(32, 3, '07:52:55', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(33, 3, '07:52:55', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(34, 3, '07:52:55', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(35, 3, '07:52:56', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(36, 3, '07:52:56', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(37, 3, '07:52:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(38, 3, '07:52:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(39, 3, '07:52:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(40, 3, '08:58:44', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(41, 3, '08:58:44', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(42, 3, '08:58:44', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(43, 3, '08:58:45', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(44, 3, '08:58:45', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(45, 3, '08:58:45', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(46, 3, '08:58:45', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(47, 3, '08:58:45', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(48, 3, '08:58:46', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(49, 3, '08:58:46', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(50, 3, '08:58:46', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(51, 3, '08:58:46', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(52, 3, '08:58:46', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(53, 3, '08:58:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(54, 3, '08:58:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(55, 3, '08:58:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(56, 3, '08:58:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(57, 3, '08:58:47', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(58, 3, '08:58:48', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(59, 3, '08:58:48', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(60, 3, '08:58:48', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(61, 3, '08:58:48', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(62, 3, '08:58:49', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(63, 3, '08:58:49', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(64, 3, '08:58:49', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(65, 3, '08:58:50', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(66, 3, '08:58:50', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(67, 3, '08:58:50', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(68, 3, '08:58:51', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(69, 3, '08:58:51', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(70, 3, '08:58:52', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(71, 3, '08:58:52', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(72, 3, '08:58:53', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(73, 3, '08:58:53', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(74, 3, '08:58:53', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(75, 3, '08:58:54', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(76, 3, '08:58:54', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(77, 3, '08:58:55', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(78, 3, '08:58:55', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(79, 3, '08:58:56', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(80, 3, '08:58:56', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(81, 3, '08:58:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(82, 3, '08:58:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(83, 3, '08:58:57', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(84, 3, '08:58:58', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(85, 3, '08:58:58', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(86, 3, '08:58:59', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(87, 3, '08:58:59', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(88, 3, '08:59:00', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(89, 3, '08:59:01', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(90, 3, '08:59:01', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(91, 3, '08:59:01', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(92, 3, '08:59:02', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(93, 3, '08:59:02', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(94, 3, '08:59:02', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(95, 3, '08:59:02', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(96, 3, '08:59:02', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(97, 3, '08:59:03', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(98, 3, '08:59:03', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(99, 3, '08:59:03', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(100, 3, '08:59:04', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(101, 3, '08:59:04', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(102, 3, '08:59:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(103, 3, '08:59:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(104, 3, '08:59:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(105, 3, '08:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(106, 3, '08:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(107, 3, '08:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(108, 3, '08:59:08', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(109, 3, '08:59:09', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(110, 3, '08:59:09', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(111, 3, '08:59:09', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(112, 3, '10:01:58', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(113, 3, '10:01:59', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(114, 3, '10:01:59', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(115, 3, '10:01:59', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(116, 3, '10:02:00', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(117, 3, '11:55:32', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(118, 3, '11:55:33', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(119, 3, '11:55:33', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(120, 3, '11:55:33', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(121, 3, '11:55:33', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(122, 3, '11:55:33', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(123, 3, '11:55:34', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(124, 3, '11:55:34', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(125, 3, '11:55:34', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(126, 3, '11:55:34', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(127, 3, '11:55:34', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(128, 3, '11:55:35', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(129, 3, '11:55:35', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(130, 3, '11:55:35', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(131, 3, '11:55:35', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(132, 3, '11:55:36', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(133, 3, '11:55:36', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(134, 3, '11:55:36', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(135, 3, '11:55:36', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(136, 3, '11:55:36', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(137, 3, '11:55:37', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(138, 3, '11:55:37', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(139, 3, '11:55:51', 'Defect', 'Defect report submitted: Flash (1 unit(s))', 'ESP32-STN01', '2026-10-19'),
(140, 3, '11:57:12', 'Defect', 'Defect report #7 confirmed', 'ESP32-STN01', '2026-10-19'),
(141, 3, '12:03:28', 'Defect', 'Defect report submitted: Flash (1 unit(s))', 'ESP32-STN01', '2026-10-19'),
(142, 3, '12:10:02', 'Defect', 'Defect report #8 confirmed', 'ESP32-STN01', '2026-10-19'),
(143, 3, '14:57:22', 'System', 'Station paused by supervisor', 'ESP32-STN01', '2026-10-19'),
(144, 3, '14:58:35', 'System', 'Station resumed by supervisor', 'ESP32-STN01', '2026-10-19'),
(145, 3, '14:58:39', 'System', 'Station paused by supervisor', 'ESP32-STN01', '2026-10-19'),
(146, 3, '14:58:43', 'System', 'Station resumed by supervisor', 'ESP32-STN01', '2026-10-19'),
(147, 3, '14:59:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(148, 3, '14:59:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(149, 3, '14:59:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(150, 3, '14:59:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(151, 3, '14:59:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(152, 3, '14:59:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(153, 3, '14:59:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(154, 3, '14:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(155, 3, '14:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(156, 3, '14:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(157, 3, '14:59:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(158, 3, '14:59:08', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(159, 3, '14:59:08', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(160, 3, '14:59:08', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(161, 3, '15:31:04', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(162, 3, '15:31:04', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(163, 3, '15:31:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(164, 3, '15:31:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(165, 3, '15:31:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(166, 3, '15:31:05', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(167, 3, '15:31:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(168, 3, '15:31:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(169, 3, '15:31:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(170, 3, '15:31:06', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(171, 3, '15:31:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19'),
(172, 3, '15:31:07', 'Count', 'Count logged: +1 good unit(s)', 'ESP32-STN01', '2026-10-19');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_floor_alerts`
--

CREATE TABLE `supervisor_floor_alerts` (
  `id` int(11) NOT NULL,
  `alert_type` varchar(20) NOT NULL,
  `title` varchar(150) NOT NULL,
  `subtitle` varchar(200) DEFAULT NULL,
  `alert_time` varchar(20) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_floor_alerts`
--

INSERT INTO `supervisor_floor_alerts` (`id`, `alert_type`, `title`, `subtitle`, `alert_time`) VALUES
(1, 'info', 'Session started', 'Vent Caps (A2) · target 480 units', '06:00 AM'),
(2, 'defect', 'New defect report: Short shot', 'ESP32-STN04 · Inspection', '08:12 AM'),
(3, 'warning', 'ESP32-STN03 paused', 'Operator break', '08:55 AM'),
(4, 'defect', 'New defect report: Scratch', 'ESP32-STN03 · Assembly', '08:40 AM'),
(5, 'warning', 'Line paused', 'Machine jam', '01:34 PM'),
(6, 'info', 'Line resumed', 'SES-20261014-01', '01:34 PM'),
(7, 'info', 'Targets updated', 'Vent Caps (A2) · line 480 · per station 116', '01:34 PM'),
(8, 'info', 'Targets updated', 'Vent Caps (A2) · line 489 · per station 116', '01:35 PM'),
(9, 'info', 'Targets updated', 'Vent Caps (A2) · line 489 · per station 112', '01:35 PM'),
(10, 'info', 'ESP32-STN03 resumed', 'Assembly · staff3', '07:19 AM'),
(11, 'info', 'Session closed', 'SES-20261014-01', '11:12 AM'),
(12, 'info', 'Session started', 'Vent Caps (A2) · target 480 units', '11:55 AM'),
(13, 'defect', 'New defect report: Flash', 'ESP32-STN01 · Injection Molding · 1 unit(s) · Critical', '11:55 AM'),
(14, 'warning', 'Line paused', 'Machine jam', '12:03 PM'),
(15, 'defect', 'New defect report: Flash', 'ESP32-STN01 · Inspection · 1 unit(s) · Critical', '12:03 PM'),
(16, 'info', 'Line resumed', 'SES-20261019-01', '02:17 PM'),
(17, 'info', 'Session closed', 'SES-20261019-01', '02:48 PM'),
(18, 'info', 'Session started', 'Vent Caps (A2) · target 480 units', '02:48 PM'),
(19, 'warning', 'ESP32-STN01 paused', 'Injection Molding · Freds', '02:57 PM'),
(20, 'info', 'ESP32-STN01 resumed', 'Injection Molding · Freds', '02:58 PM'),
(21, 'warning', 'ESP32-STN01 paused', 'Injection Molding · Freds', '02:58 PM'),
(22, 'info', 'ESP32-STN01 resumed', 'Injection Molding · Freds', '02:58 PM');

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_hourly_output`
--

CREATE TABLE `supervisor_hourly_output` (
  `id` int(11) NOT NULL,
  `hour_label` varchar(20) NOT NULL,
  `good_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `log_date` date DEFAULT NULL,
  `session_id` int(11) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_hourly_output`
--

INSERT INTO `supervisor_hourly_output` (`id`, `hour_label`, `good_units`, `ng_units`, `log_date`, `session_id`) VALUES
(1, '6 AM', 78, 2, NULL, NULL),
(2, '7 AM', 85, 2, NULL, NULL),
(3, '8 AM', 88, 2, NULL, NULL),
(4, '9 AM', 83, 2, NULL, NULL),
(5, '7 AM', 30, 4, '2026-10-19', 1),
(38, '8 AM', 72, 0, '2026-10-19', 1),
(110, '10 AM', 5, 0, '2026-10-19', 1),
(115, '11 AM', 22, 1, '2026-10-19', 2),
(138, '2 PM', 14, 0, '2026-10-19', 3),
(152, '3 PM', 12, 0, '2026-10-19', 3);

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_line_sessions`
--

CREATE TABLE `supervisor_line_sessions` (
  `id` int(11) NOT NULL,
  `session_code` varchar(30) NOT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'RUNNING',
  `product_name` varchar(100) NOT NULL DEFAULT 'Vent Caps (A2)',
  `start_time` varchar(20) DEFAULT NULL,
  `elapsed_hours` int(11) NOT NULL DEFAULT 0,
  `opened_by` varchar(80) DEFAULT NULL,
  `line_target` int(11) NOT NULL DEFAULT 480,
  `station_target` int(11) NOT NULL DEFAULT 120,
  `pause_reason` varchar(100) DEFAULT NULL,
  `started_at` datetime DEFAULT NULL,
  `closed_at` datetime DEFAULT NULL,
  `paused_at` datetime DEFAULT NULL,
  `paused_seconds` int(11) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_line_sessions`
--

INSERT INTO `supervisor_line_sessions` (`id`, `session_code`, `status`, `product_name`, `start_time`, `elapsed_hours`, `opened_by`, `line_target`, `station_target`, `pause_reason`, `started_at`, `closed_at`, `paused_at`, `paused_seconds`) VALUES
(1, 'SES-20261014-01', 'CLOSED', 'Vent Caps (A2)', '06:00 AM', 4, 'Shift Supervisor', 480, 120, '', NULL, '2026-10-19 11:12:30', NULL, 0),
(2, 'SES-20261019-01', 'CLOSED', 'Vent Caps (A2)', '11:55 AM', 0, 'Shift Supervisor', 480, 120, '', '2026-10-19 11:55:14', '2026-10-19 14:48:03', NULL, 8094),
(3, 'SES-20261019-02', 'RUNNING', 'Vent Caps (A2)', '02:48 PM', 0, 'Shift Supervisor', 480, 120, '', '2026-10-19 14:48:27', NULL, NULL, 0);

-- --------------------------------------------------------

--
-- Table structure for table `supervisor_station_status`
--

CREATE TABLE `supervisor_station_status` (
  `id` int(11) NOT NULL,
  `station_code` varchar(30) NOT NULL,
  `workstation_name` varchar(80) NOT NULL,
  `assigned_staff` varchar(80) DEFAULT NULL,
  `status` varchar(10) NOT NULL DEFAULT 'RUNNING',
  `good_units` int(11) NOT NULL DEFAULT 0,
  `ng_units` int(11) NOT NULL DEFAULT 0,
  `target_units` int(11) NOT NULL DEFAULT 120
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `supervisor_station_status`
--

INSERT INTO `supervisor_station_status` (`id`, `station_code`, `workstation_name`, `assigned_staff`, `status`, `good_units`, `ng_units`, `target_units`) VALUES
(1, 'ESP32-STN01', 'Injection Molding', 'staff1', 'RUNNING', 26, 0, 120),
(2, 'ESP32-STN02', 'Trimming', 'staff2', 'RUNNING', 0, 0, 120),
(3, 'ESP32-STN03', 'Assembly', 'staff3', 'RUNNING', 0, 0, 120),
(4, 'ESP32-STN04', 'Inspection', 'staff4', 'RUNNING', 0, 0, 120);

-- --------------------------------------------------------

--
-- Table structure for table `system_settings`
--

CREATE TABLE `system_settings` (
  `id` int(11) NOT NULL,
  `plant_name` varchar(120) NOT NULL DEFAULT 'Masters Vineyard Enterprise',
  `time_zone` varchar(60) NOT NULL DEFAULT 'Asia/Manila (UTC+08:00)',
  `time_format` varchar(30) NOT NULL DEFAULT '12-hour (12:00 PM)',
  `alert_defect_30m` tinyint(1) NOT NULL DEFAULT 1,
  `alert_line_stop_15m` tinyint(1) NOT NULL DEFAULT 1,
  `alert_oee_under_target` tinyint(1) NOT NULL DEFAULT 1,
  `alert_station_offline` tinyint(1) NOT NULL DEFAULT 1,
  `alert_weekly_summary` tinyint(1) NOT NULL DEFAULT 0,
  `inactivity_timeout` varchar(20) NOT NULL DEFAULT '30 minutes',
  `min_password_len` int(11) NOT NULL DEFAULT 10,
  `password_expiry` varchar(20) NOT NULL DEFAULT '90 days',
  `require_2fa_managers` tinyint(1) NOT NULL DEFAULT 0,
  `history_retention` varchar(20) NOT NULL DEFAULT '24 months',
  `weekly_export_schedule` varchar(40) NOT NULL DEFAULT 'Monday, 06:00 AM',
  `checkin_interval_sec` int(11) NOT NULL DEFAULT 10,
  `offline_threshold_sec` int(11) NOT NULL DEFAULT 60,
  `device_api_key` varchar(64) DEFAULT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings`
--

INSERT INTO `system_settings` (`id`, `plant_name`, `time_zone`, `time_format`, `alert_defect_30m`, `alert_line_stop_15m`, `alert_oee_under_target`, `alert_station_offline`, `alert_weekly_summary`, `inactivity_timeout`, `min_password_len`, `password_expiry`, `require_2fa_managers`, `history_retention`, `weekly_export_schedule`, `checkin_interval_sec`, `offline_threshold_sec`, `device_api_key`) VALUES
(1, 'Masters Vineyard Enterprise', 'Asia/Manila (UTC+08:00)', '12-hour (12:00 PM)', 1, 1, 1, 1, 0, '30 minutes', 10, '90 days', 0, '24', 'Monday, 06:00 AM', 10, 60, '920e13e3d326a3944d4b2aff886b076a02c79f33dc26ae1e817ae91b40c69a89');

-- --------------------------------------------------------

--
-- Table structure for table `system_settings_history`
--

CREATE TABLE `system_settings_history` (
  `id` int(11) NOT NULL,
  `log_timestamp` varchar(40) NOT NULL,
  `actor` varchar(100) NOT NULL,
  `action_description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `system_settings_history`
--

INSERT INTO `system_settings_history` (`id`, `log_timestamp`, `actor`, `action_description`) VALUES
(1, 'Oct 06 · 02:15 PM', 'Production Manager', 'set ESP32-STN07 to maintenance'),
(2, 'Oct 01 · 09:00 AM', 'Production Manager', 'updated plant-wide system settings'),
(3, 'Oct 14 · 03:37 AM', 'Production Manager', 'updated plant-wide system settings'),
(4, 'Oct 14 · 03:37 AM', 'Production Manager', 'updated plant-wide system settings');

-- --------------------------------------------------------

--
-- Table structure for table `users`
--

CREATE TABLE `users` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `password` varchar(255) NOT NULL,
  `role` enum('manager','supervisor','staff') NOT NULL,
  `full_name` varchar(100) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `employee_id` varchar(20) DEFAULT NULL,
  `department` varchar(80) DEFAULT NULL,
  `contact_number` varchar(30) DEFAULT NULL,
  `assigned_station` varchar(60) DEFAULT NULL,
  `shift_schedule` varchar(80) DEFAULT NULL,
  `last_signin` varchar(40) DEFAULT NULL,
  `pref_defect_alert` tinyint(1) NOT NULL DEFAULT 1,
  `pref_shift_reminder` tinyint(1) NOT NULL DEFAULT 1,
  `pref_24h_format` tinyint(1) NOT NULL DEFAULT 0,
  `assignment` varchar(50) NOT NULL DEFAULT 'Line 1',
  `last_active` varchar(40) DEFAULT 'Now',
  `status` varchar(20) NOT NULL DEFAULT 'Active',
  `must_change_password` tinyint(1) NOT NULL DEFAULT 0
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `users`
--

INSERT INTO `users` (`id`, `username`, `password`, `role`, `full_name`, `created_at`, `employee_id`, `department`, `contact_number`, `assigned_station`, `shift_schedule`, `last_signin`, `pref_defect_alert`, `pref_shift_reminder`, `pref_24h_format`, `assignment`, `last_active`, `status`, `must_change_password`) VALUES
(2, 'supervisor1', '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'supervisor', 'Shift Supervisor', '2026-10-13 23:58:31', 'MV-0002', 'Production - Line 1', '', 'ESP32-STN01 – STN04', 'Shift 1 · 06:00 AM – 02:00 PM', 'Oct 19, 2026 · 03:50 PM', 1, 1, 0, 'Line 1', 'Now', 'Active', 0),
(3, 'staff1', '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'staff', 'Freds', '2026-10-13 23:58:31', 'MV-0003', 'Production - Line 1', '', 'ESP32-STN01', 'Shift 1 · 06:00 AM – 02:00 PM', 'Oct 19, 2026 · 02:58 PM', 1, 1, 0, 'Line 1', 'Now', 'Active', 0),
(7, 'Fred', '$2y$10$UaFZNJVPyFcL7BAed0LQhu4OEowOqkc9/0dUeIi4LV4MC8oJZRRGW', 'staff', 'Fred', '2026-10-14 03:28:58', NULL, NULL, '', NULL, NULL, 'Oct 14, 2026 · 04:07 PM', 1, 1, 0, 'Line 1', 'Now', 'Active', 0),
(8, 'manager1', '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe', 'manager', 'Prod Manager', '2026-10-18 23:35:44', NULL, 'Manager', '', NULL, NULL, 'Oct 19, 2026 · 03:44 PM', 1, 1, 0, 'Line 1', 'Now', 'Active', 0),
(11, 'jazlyn', '$2y$10$UmyjZuHlvUc5GaDhKZu9puJk0jFSSLxGHDLGjb8Kc7MEOUNEw4hM.', 'staff', 'Jazlyn', '2026-10-19 06:55:26', NULL, 'Staff', '', 'ESP32-STN05', NULL, 'Oct 19, 2026 · 02:55 PM', 1, 1, 0, 'Line 2', 'Now', 'Active', 0);

-- --------------------------------------------------------

--
-- Table structure for table `user_access_changes`
--

CREATE TABLE `user_access_changes` (
  `id` int(11) NOT NULL,
  `log_timestamp` varchar(40) NOT NULL,
  `actor` varchar(100) NOT NULL,
  `action_description` varchar(255) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_access_changes`
--

INSERT INTO `user_access_changes` (`id`, `log_timestamp`, `actor`, `action_description`) VALUES
(1, 'Oct 08 · 10:05 AM', 'Production Manager', 'created account for supervisor1 (supervisor)'),
(2, 'Oct 08 · 10:07 AM', 'Production Manager', 'created account for staff1 (staff)'),
(3, 'Oct 14 · 03:31 AM', 'Production Manager', 'created account for Fred (operator)'),
(4, 'Oct 14 · 03:32 AM', 'Production Manager', 'created account for Jazlyn (supervisor)'),
(5, 'Oct 14 · 03:38 AM', 'Production Manager', 'created account for Milli (operator)'),
(6, 'Oct 14 · 04:53 AM', 'Production Manager', 'deleted account for Milli'),
(7, 'Oct 14 · 04:53 AM', 'Production Manager', 'deleted account for Jazlyn'),
(8, 'Oct 14 · 04:53 AM', 'Production Manager', 'deleted account for Fred'),
(9, 'Oct 14 · 05:28 AM', 'Production Manager', 'approved access request #3 (Fred)'),
(10, 'Oct 14 · 12:54 PM', 'Production Manager', 'updated account for Frid'),
(11, 'Oct 19 · 07:36 AM', 'Production Manager', 'deleted account for Frid'),
(12, 'Oct 19 · 07:37 AM', 'Production Manager', 'declined access request #4 (Jazlyn)'),
(13, 'Oct 19 · 07:37 AM', 'Production Manager', 'declined access request #2 (operator_new2)'),
(14, 'Oct 19 · 07:37 AM', 'Production Manager', 'declined access request #1 (operator_new1)'),
(15, 'Oct 19 · 01:31 PM', 'Production Manager', 'deactivated account for Fred'),
(16, 'Oct 19 · 01:59 PM', 'Production Manager', 'reactivated account for Fred'),
(17, 'Oct 19 · 02:10 PM', 'Production Manager', 'deactivated account for staff1'),
(18, 'Oct 19 · 02:17 PM', 'Production Manager', 'reactivated account for staff1'),
(19, 'Oct 19 · 02:55 PM', 'Prod Manager', 'approved access request #5 (jazlyn)');

-- --------------------------------------------------------

--
-- Table structure for table `user_access_requests`
--

CREATE TABLE `user_access_requests` (
  `id` int(11) NOT NULL,
  `username` varchar(50) NOT NULL,
  `requested_role` varchar(20) NOT NULL,
  `assignment_line` varchar(30) NOT NULL,
  `requested_by` varchar(100) NOT NULL,
  `request_date` varchar(30) NOT NULL,
  `status` varchar(20) NOT NULL DEFAULT 'Pending'
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;

--
-- Dumping data for table `user_access_requests`
--

INSERT INTO `user_access_requests` (`id`, `username`, `requested_role`, `assignment_line`, `requested_by`, `request_date`, `status`) VALUES
(1, 'operator_new1', 'Operator', 'Line 2', 'Shift Supervisor', 'Oct 12', 'Declined'),
(2, 'operator_new2', 'Operator', 'Line 3', 'Shift Supervisor', 'Oct 13', 'Declined'),
(3, 'Fred', 'Operator', 'Line 1', 'Fred', 'Oct 14', 'Approved'),
(4, 'Jazlyn', 'Operator', 'Line 2', 'Jazlyn', 'Oct 14', 'Declined'),
(5, 'jazlyn', 'Operator', 'Line 2', 'Jazlyn', 'Oct 19', 'Approved');

--
-- Indexes for dumped tables
--

--
-- Indexes for table `analytics_daily_yield`
--
ALTER TABLE `analytics_daily_yield`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `analytics_report_history`
--
ALTER TABLE `analytics_report_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `analytics_root_cause`
--
ALTER TABLE `analytics_root_cause`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_defect` (`defect_type`);

--
-- Indexes for table `backup_shift_records_before_cleanup`
--
ALTER TABLE `backup_shift_records_before_cleanup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `backup_shift_workstation_yield_before_cleanup`
--
ALTER TABLE `backup_shift_workstation_yield_before_cleanup`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift` (`shift_record_id`),
  ADD KEY `fk_wsyield_station` (`station_code`);

--
-- Indexes for table `defect_categories`
--
ALTER TABLE `defect_categories`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_category_name` (`category_name`);

--
-- Indexes for table `defect_logs`
--
ALTER TABLE `defect_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `session_id` (`session_id`),
  ADD KEY `reported_by` (`reported_by`);

--
-- Indexes for table `defect_reports`
--
ALTER TABLE `defect_reports`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_created` (`created_at`),
  ADD KEY `fk_defect_station` (`station`),
  ADD KEY `idx_reported_by_created` (`reported_by_id`,`created_at`);

--
-- Indexes for table `login_attempts`
--
ALTER TABLE `login_attempts`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_user_time` (`username`,`attempted_at`),
  ADD KEY `idx_ip_time` (`ip`,`attempted_at`);

--
-- Indexes for table `master_lines`
--
ALTER TABLE `master_lines`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `line_name` (`line_name`);

--
-- Indexes for table `master_project_types`
--
ALTER TABLE `master_project_types`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `project_type` (`project_type`);

--
-- Indexes for table `master_shift_schedule`
--
ALTER TABLE `master_shift_schedule`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_stations`
--
ALTER TABLE `master_stations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `station_code` (`station_code`);

--
-- Indexes for table `master_target_defaults`
--
ALTER TABLE `master_target_defaults`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `master_workstations`
--
ALTER TABLE `master_workstations`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `workstation_name` (`workstation_name`);

--
-- Indexes for table `oee_line_performance`
--
ALTER TABLE `oee_line_performance`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oee_plant_metrics`
--
ALTER TABLE `oee_plant_metrics`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `oee_weekly_trend`
--
ALTER TABLE `oee_weekly_trend`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `production_sessions`
--
ALTER TABLE `production_sessions`
  ADD PRIMARY KEY (`id`),
  ADD KEY `created_by` (`created_by`),
  ADD KEY `fk_session_machine` (`machine_id`);

--
-- Indexes for table `shift_records`
--
ALTER TABLE `shift_records`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_status` (`status`),
  ADD KEY `idx_session_id` (`session_id`);

--
-- Indexes for table `shift_workstation_yield`
--
ALTER TABLE `shift_workstation_yield`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_shift` (`shift_record_id`),
  ADD KEY `fk_wsyield_station` (`station_code`);

--
-- Indexes for table `staff_activity_logs`
--
ALTER TABLE `staff_activity_logs`
  ADD PRIMARY KEY (`id`),
  ADD KEY `idx_staff` (`staff_id`),
  ADD KEY `idx_staff_date` (`staff_id`,`log_date`);

--
-- Indexes for table `supervisor_floor_alerts`
--
ALTER TABLE `supervisor_floor_alerts`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supervisor_hourly_output`
--
ALTER TABLE `supervisor_hourly_output`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `uq_session_hour` (`session_id`,`log_date`,`hour_label`);

--
-- Indexes for table `supervisor_line_sessions`
--
ALTER TABLE `supervisor_line_sessions`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `supervisor_station_status`
--
ALTER TABLE `supervisor_station_status`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `station_code` (`station_code`);

--
-- Indexes for table `system_settings`
--
ALTER TABLE `system_settings`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `system_settings_history`
--
ALTER TABLE `system_settings_history`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `users`
--
ALTER TABLE `users`
  ADD PRIMARY KEY (`id`),
  ADD UNIQUE KEY `username` (`username`);

--
-- Indexes for table `user_access_changes`
--
ALTER TABLE `user_access_changes`
  ADD PRIMARY KEY (`id`);

--
-- Indexes for table `user_access_requests`
--
ALTER TABLE `user_access_requests`
  ADD PRIMARY KEY (`id`);

--
-- AUTO_INCREMENT for dumped tables
--

--
-- AUTO_INCREMENT for table `analytics_daily_yield`
--
ALTER TABLE `analytics_daily_yield`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `analytics_report_history`
--
ALTER TABLE `analytics_report_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `analytics_root_cause`
--
ALTER TABLE `analytics_root_cause`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=16;

--
-- AUTO_INCREMENT for table `backup_shift_records_before_cleanup`
--
ALTER TABLE `backup_shift_records_before_cleanup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `backup_shift_workstation_yield_before_cleanup`
--
ALTER TABLE `backup_shift_workstation_yield_before_cleanup`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `defect_categories`
--
ALTER TABLE `defect_categories`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `defect_logs`
--
ALTER TABLE `defect_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT;

--
-- AUTO_INCREMENT for table `defect_reports`
--
ALTER TABLE `defect_reports`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `login_attempts`
--
ALTER TABLE `login_attempts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=34;

--
-- AUTO_INCREMENT for table `master_lines`
--
ALTER TABLE `master_lines`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_project_types`
--
ALTER TABLE `master_project_types`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_shift_schedule`
--
ALTER TABLE `master_shift_schedule`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `master_stations`
--
ALTER TABLE `master_stations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `master_target_defaults`
--
ALTER TABLE `master_target_defaults`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `master_workstations`
--
ALTER TABLE `master_workstations`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=8;

--
-- AUTO_INCREMENT for table `oee_line_performance`
--
ALTER TABLE `oee_line_performance`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `oee_plant_metrics`
--
ALTER TABLE `oee_plant_metrics`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `oee_weekly_trend`
--
ALTER TABLE `oee_weekly_trend`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `production_sessions`
--
ALTER TABLE `production_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `shift_records`
--
ALTER TABLE `shift_records`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=9;

--
-- AUTO_INCREMENT for table `shift_workstation_yield`
--
ALTER TABLE `shift_workstation_yield`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=7;

--
-- AUTO_INCREMENT for table `staff_activity_logs`
--
ALTER TABLE `staff_activity_logs`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=173;

--
-- AUTO_INCREMENT for table `supervisor_floor_alerts`
--
ALTER TABLE `supervisor_floor_alerts`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=23;

--
-- AUTO_INCREMENT for table `supervisor_hourly_output`
--
ALTER TABLE `supervisor_hourly_output`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=164;

--
-- AUTO_INCREMENT for table `supervisor_line_sessions`
--
ALTER TABLE `supervisor_line_sessions`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=4;

--
-- AUTO_INCREMENT for table `supervisor_station_status`
--
ALTER TABLE `supervisor_station_status`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `system_settings`
--
ALTER TABLE `system_settings`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=2;

--
-- AUTO_INCREMENT for table `system_settings_history`
--
ALTER TABLE `system_settings_history`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=5;

--
-- AUTO_INCREMENT for table `users`
--
ALTER TABLE `users`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=12;

--
-- AUTO_INCREMENT for table `user_access_changes`
--
ALTER TABLE `user_access_changes`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=20;

--
-- AUTO_INCREMENT for table `user_access_requests`
--
ALTER TABLE `user_access_requests`
  MODIFY `id` int(11) NOT NULL AUTO_INCREMENT, AUTO_INCREMENT=6;

--
-- Constraints for dumped tables
--

--
-- Constraints for table `analytics_root_cause`
--
ALTER TABLE `analytics_root_cause`
  ADD CONSTRAINT `fk_rootcause_category` FOREIGN KEY (`defect_type`) REFERENCES `defect_categories` (`category_name`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `defect_logs`
--
ALTER TABLE `defect_logs`
  ADD CONSTRAINT `defect_logs_ibfk_1` FOREIGN KEY (`session_id`) REFERENCES `production_sessions` (`id`) ON DELETE CASCADE,
  ADD CONSTRAINT `defect_logs_ibfk_2` FOREIGN KEY (`reported_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `defect_reports`
--
ALTER TABLE `defect_reports`
  ADD CONSTRAINT `fk_defect_station` FOREIGN KEY (`station`) REFERENCES `master_stations` (`station_code`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `production_sessions`
--
ALTER TABLE `production_sessions`
  ADD CONSTRAINT `fk_session_machine` FOREIGN KEY (`machine_id`) REFERENCES `master_stations` (`station_code`) ON UPDATE CASCADE,
  ADD CONSTRAINT `production_sessions_ibfk_1` FOREIGN KEY (`created_by`) REFERENCES `users` (`id`) ON DELETE CASCADE;

--
-- Constraints for table `shift_records`
--
ALTER TABLE `shift_records`
  ADD CONSTRAINT `fk_shift_staff` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE SET NULL ON UPDATE CASCADE;

--
-- Constraints for table `shift_workstation_yield`
--
ALTER TABLE `shift_workstation_yield`
  ADD CONSTRAINT `fk_wsyield_shift` FOREIGN KEY (`shift_record_id`) REFERENCES `shift_records` (`id`) ON DELETE CASCADE ON UPDATE CASCADE,
  ADD CONSTRAINT `fk_wsyield_station` FOREIGN KEY (`station_code`) REFERENCES `master_stations` (`station_code`) ON UPDATE CASCADE;

--
-- Constraints for table `staff_activity_logs`
--
ALTER TABLE `staff_activity_logs`
  ADD CONSTRAINT `fk_activity_user` FOREIGN KEY (`staff_id`) REFERENCES `users` (`id`) ON DELETE CASCADE ON UPDATE CASCADE;

--
-- Constraints for table `supervisor_station_status`
--
ALTER TABLE `supervisor_station_status`
  ADD CONSTRAINT `fk_status_station` FOREIGN KEY (`station_code`) REFERENCES `master_stations` (`station_code`) ON DELETE CASCADE ON UPDATE CASCADE;
COMMIT;

/*!40101 SET CHARACTER_SET_CLIENT=@OLD_CHARACTER_SET_CLIENT */;
/*!40101 SET CHARACTER_SET_RESULTS=@OLD_CHARACTER_SET_RESULTS */;
/*!40101 SET COLLATION_CONNECTION=@OLD_COLLATION_CONNECTION */;

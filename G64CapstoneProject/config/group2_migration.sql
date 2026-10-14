-- ============================================================================
-- Group 2 update - RUN THIS BEFORE copying the new PHP files
-- phpMyAdmin: select g64capstoneproject -> SQL tab -> paste -> Go
-- Safe to re-run. (Run group1_migration.sql first on a fresh install.)
-- ============================================================================
USE g64capstoneproject;

-- 1. Force password change after a temporary password (create user / approve request / reset)
--    Existing accounts (including the demo ones) are NOT forced.
ALTER TABLE users
    ADD COLUMN IF NOT EXISTS must_change_password TINYINT(1) NOT NULL DEFAULT 0;

-- 2. Heartbeat: when each station's device last contacted api.php
ALTER TABLE master_stations
    ADD COLUMN IF NOT EXISTS last_seen DATETIME DEFAULT NULL;

-- 3. Downtime tracking for OEE availability (line pauses)
ALTER TABLE supervisor_line_sessions
    ADD COLUMN IF NOT EXISTS paused_at      DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS paused_seconds INT      NOT NULL DEFAULT 0;

-- 4. Planned / run minutes saved on the shift when its session closes (used by compute_oee)
ALTER TABLE shift_records
    ADD COLUMN IF NOT EXISTS planned_minutes INT DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS run_minutes     INT DEFAULT NULL;

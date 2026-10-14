-- ============================================================================
-- Group 1 update - RUN THIS FIRST (before copying the new PHP files)
-- phpMyAdmin: select g64capstoneproject -> SQL tab -> paste -> Go
-- Safe to re-run.
-- ============================================================================
USE g64capstoneproject;

-- 1. Device key for the ESP32 (sent as header  X-Device-Key  or POST field  key)
ALTER TABLE system_settings ADD COLUMN IF NOT EXISTS device_api_key VARCHAR(64) DEFAULT NULL;
UPDATE system_settings
   SET device_api_key = SHA2(CONCAT(UUID(), RAND(), NOW()), 256)
 WHERE id = 1 AND (device_api_key IS NULL OR device_api_key = '');

-- 2. Hourly bars are now kept per day
ALTER TABLE supervisor_hourly_output ADD COLUMN IF NOT EXISTS log_date DATE DEFAULT NULL;
UPDATE supervisor_hourly_output SET log_date = CURDATE() WHERE log_date IS NULL;

-- 3. Real start/close times for line sessions (elapsed time is computed from these)
ALTER TABLE supervisor_line_sessions
    ADD COLUMN IF NOT EXISTS started_at DATETIME DEFAULT NULL,
    ADD COLUMN IF NOT EXISTS closed_at  DATETIME DEFAULT NULL;
UPDATE supervisor_line_sessions
   SET started_at = CONCAT(CURDATE(), ' 06:00:00')
 WHERE started_at IS NULL AND status <> 'CLOSED';

-- 4. Login: the old seed hash was fake and only worked through the password123 backdoor
--    (now removed). Give the demo accounts a real hash for:  password123
UPDATE users
   SET password = '$2b$10$A7TNEyUrs8vyDo9krn9S9e9Xh2wJ7/JgFbhzcOGkEhZz3huHSRNFe'
 WHERE password = '$2y$10$wT8m9M3H4k3x1qV9Q7z8ueL8L3S9v1K9R7p6e3T4W2A1B0C9D8E7F';

-- 5. Show the device key (copy it into the ESP32 code)
SELECT device_api_key FROM system_settings WHERE id = 1;

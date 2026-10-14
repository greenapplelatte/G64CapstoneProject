-- group4_migration.sql  (activity log dates)
-- Run once in phpMyAdmin on database g64capstoneproject, after the earlier migrations.
-- Adds a date to every activity log row so the Date filter and paging can work.
ALTER TABLE staff_activity_logs
    ADD COLUMN IF NOT EXISTS log_date DATE DEFAULT NULL;
UPDATE staff_activity_logs SET log_date = CURDATE() WHERE log_date IS NULL;
ALTER TABLE staff_activity_logs
    ADD INDEX IF NOT EXISTS idx_staff_date (staff_id, log_date);

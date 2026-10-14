-- ============================================================================
-- G64CapstoneProject - table relationships (foreign keys)  [v2, MariaDB-safe]
-- Run AFTER restore_tables.sql, on the g64capstoneproject database.
-- Safe to re-run: each key is dropped (if present) and re-added.
--
-- If a statement fails with "Cannot add or update a child row", some existing
-- row points to a parent that does not exist (e.g. a defect_reports.station
-- value not in master_stations). Fix or delete that row, then re-run.
-- ============================================================================
USE g64capstoneproject;

-- Parent column that analytics_root_cause will point at must be unique.
ALTER TABLE defect_categories DROP INDEX IF EXISTS uq_category_name;
ALTER TABLE defect_categories ADD UNIQUE KEY uq_category_name (category_name);

-- USERS -> things owned by a user
ALTER TABLE staff_activity_logs DROP FOREIGN KEY IF EXISTS fk_activity_user;
ALTER TABLE staff_activity_logs
    ADD CONSTRAINT fk_activity_user FOREIGN KEY (staff_id) REFERENCES users(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shift_records DROP FOREIGN KEY IF EXISTS fk_shift_staff;
ALTER TABLE shift_records
    ADD CONSTRAINT fk_shift_staff FOREIGN KEY (staff_id) REFERENCES users(id)
    ON DELETE SET NULL ON UPDATE CASCADE;

-- SHIFT RECORD -> its per-workstation breakdown
ALTER TABLE shift_workstation_yield DROP FOREIGN KEY IF EXISTS fk_wsyield_shift;
ALTER TABLE shift_workstation_yield
    ADD CONSTRAINT fk_wsyield_shift FOREIGN KEY (shift_record_id) REFERENCES shift_records(id)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- MASTER STATIONS -> every table that stores a station_code
ALTER TABLE supervisor_station_status DROP FOREIGN KEY IF EXISTS fk_status_station;
ALTER TABLE supervisor_station_status
    ADD CONSTRAINT fk_status_station FOREIGN KEY (station_code) REFERENCES master_stations(station_code)
    ON DELETE CASCADE ON UPDATE CASCADE;

ALTER TABLE shift_workstation_yield DROP FOREIGN KEY IF EXISTS fk_wsyield_station;
ALTER TABLE shift_workstation_yield
    ADD CONSTRAINT fk_wsyield_station FOREIGN KEY (station_code) REFERENCES master_stations(station_code)
    ON DELETE RESTRICT ON UPDATE CASCADE;

ALTER TABLE defect_reports DROP FOREIGN KEY IF EXISTS fk_defect_station;
ALTER TABLE defect_reports
    ADD CONSTRAINT fk_defect_station FOREIGN KEY (station) REFERENCES master_stations(station_code)
    ON DELETE SET NULL ON UPDATE CASCADE;

ALTER TABLE production_sessions DROP FOREIGN KEY IF EXISTS fk_session_machine;
ALTER TABLE production_sessions
    ADD CONSTRAINT fk_session_machine FOREIGN KEY (machine_id) REFERENCES master_stations(station_code)
    ON DELETE RESTRICT ON UPDATE CASCADE;

-- DEFECT CATEGORIES -> analytics root-cause rows
ALTER TABLE analytics_root_cause DROP FOREIGN KEY IF EXISTS fk_rootcause_category;
ALTER TABLE analytics_root_cause
    ADD CONSTRAINT fk_rootcause_category FOREIGN KEY (defect_type) REFERENCES defect_categories(category_name)
    ON DELETE CASCADE ON UPDATE CASCADE;

-- Already in your original schema (unchanged):
--   production_sessions.created_by -> users.id               ON DELETE CASCADE
--   defect_logs.session_id         -> production_sessions.id ON DELETE CASCADE
--   defect_logs.reported_by        -> users.id               ON DELETE CASCADE

-- Staff-page fixes: run once after the base schema and earlier group migrations.
ALTER TABLE defect_reports
    ADD COLUMN IF NOT EXISTS reported_by_id INT NULL AFTER reported_by,
    ADD INDEX IF NOT EXISTS idx_reported_by_created (reported_by_id, created_at);

-- Backfill legacy reports where the textual reporter matches a current username or full name.
UPDATE defect_reports dr
JOIN users u ON dr.reported_by = u.username OR dr.reported_by = u.full_name
SET dr.reported_by_id = u.id
WHERE dr.reported_by_id IS NULL;

-- There was no project-type master table; add one so the operator form uses real options.
CREATE TABLE IF NOT EXISTS master_project_types (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_type VARCHAR(100) NOT NULL UNIQUE,
    is_active TINYINT(1) NOT NULL DEFAULT 1
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT IGNORE INTO master_project_types (project_type)
SELECT DISTINCT project_type FROM defect_reports WHERE project_type IS NOT NULL AND project_type <> '';
INSERT IGNORE INTO master_project_types (project_type) VALUES ('Vent Caps (A2)'), ('Thailand Socket Cover');

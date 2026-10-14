-- Manager demo-data cleanup for G64CapstoneProject
-- BACK UP YOUR DATABASE FIRST. This clears only dashboard/analytics demo tables.
-- It intentionally preserves users, master stations, master categories, settings,
-- real production_sessions, defect_reports, and shift records.
USE g64capstoneproject;

DELETE FROM oee_plant_metrics;
DELETE FROM oee_line_performance;
DELETE FROM oee_weekly_trend;
DELETE FROM analytics_daily_yield;
DELETE FROM analytics_root_cause;
DELETE FROM analytics_report_history;

-- Clear seeded defect-category counts only if these are display aggregates.
-- Keep master category names/configuration intact; do not delete categories.
UPDATE defect_categories SET `count` = 0;

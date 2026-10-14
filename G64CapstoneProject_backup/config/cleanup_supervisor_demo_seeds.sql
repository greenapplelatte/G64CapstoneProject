-- BACK UP FIRST. Remove only fixed sample rows from the supplied database.sql seed.
DELETE FROM shift_workstation_yield WHERE id IN (1,2,3,4) AND shift_record_id = 1;
DELETE FROM shift_records WHERE id IN (1,2,3,4,5,6) AND shift_title = 'Shift 1';
DELETE FROM defect_reports WHERE id IN (1,2,3,4,5,6) AND report_code IN ('DR-0001','DR-0002','DR-0003','DR-0004','DR-0005','DR-0006');
DELETE FROM supervisor_hourly_output WHERE id IN (1,2,3,4) AND hour_label IN ('6 AM','7 AM','8 AM','9 AM');
DELETE FROM supervisor_floor_alerts WHERE id IN (1,2,3,4) AND title IN ('Session started','New defect report: Short shot','ESP32-STN03 paused','New defect report: Scratch');
DELETE FROM supervisor_line_sessions WHERE id = 1 AND session_code = 'SES-20261014-01';
DELETE FROM supervisor_station_status WHERE station_code IN ('ESP32-STN01','ESP32-STN02','ESP32-STN03','ESP32-STN04') AND assigned_staff IN ('staff1','staff2','staff3','staff4');
DELETE FROM user_access_requests WHERE id IN (1,2) AND username IN ('operator_new1','operator_new2');

DATE / HARDCODED SHIFT SEED FIX

This ZIP contains a targeted SQL migration to remove the six hardcoded demo shift records dated October 7-14, 2026, and their associated demo workstation-yield rows.

HOW TO APPLY
1. Back up your database first.
2. Open phpMyAdmin and select the g64capstoneproject database.
3. Import config/remove_hardcoded_shift_seeds.sql.
4. Refresh Shift Yield Report.

New shifts should be created by the application's normal Line & Session Control workflow. The existing process_line_control.php uses current_shift_slot() when a new shift is opened, rather than fixed historical dates.

This migration does not delete other shift records or change other tables. It only targets the known seeded demo rows.

IMPORTANT: This cleanup is optional. Do not run it if the dated sample shifts are wanted for demonstration or testing.

LIVE PRODUCTION WORKFLOW

The application production counter is software-based. Staff enter good-unit quantities in the production dashboard; each submission is validated against the staff member's assigned station, the active line session, and the station's Master Data and maintenance status. There is no physical sensor integration, so the application does not claim hardware heartbeat or sensor-verified counts.

Counts update the active production session and its hourly output records. Supervisors can monitor the session, review staff defect reports, and close out a shift after all Pending and Recheck Requested defects have been reviewed. Closed-shift records feed manager production analytics; defect analysis uses confirmed defect reports. Analytics will show no data when there are no qualifying records rather than substituting sample live metrics.

Before deploying schema changes to an existing database, back it up and apply the applicable reviewed migration scripts in config. The main config/database.sql describes the database schema for a fresh setup; it should not be treated as an instruction to delete or replace existing production data.

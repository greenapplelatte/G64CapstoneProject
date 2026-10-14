DATE / HARDCODED SHIFT SEED FIX

This ZIP contains a targeted SQL migration to remove the six hardcoded demo shift records dated October 7-14, 2026, and their associated demo workstation-yield rows.

HOW TO APPLY
1. Back up your database first.
2. Open phpMyAdmin and select the g64capstoneproject database.
3. Import config/remove_hardcoded_shift_seeds.sql.
4. Refresh Shift Yield Report.

New shifts should be created by the application's normal Line & Session Control workflow. The existing process_line_control.php uses current_shift_slot() when a new shift is opened, rather than fixed historical dates.

This migration does not delete other shift records or change other tables. It only targets the known seeded demo rows.

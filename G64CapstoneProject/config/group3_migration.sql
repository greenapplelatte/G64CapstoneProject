-- ============================================================================
-- Group 3 update (security) - RUN THIS BEFORE copying the new PHP files
-- phpMyAdmin: select g64capstoneproject -> SQL tab -> paste -> Go
-- Safe to re-run.
-- ============================================================================
USE g64capstoneproject;

-- Failed sign-in attempts, used by process_login.php to lock out password guessing
CREATE TABLE IF NOT EXISTS login_attempts (
    id           INT AUTO_INCREMENT PRIMARY KEY,
    username     VARCHAR(50) NOT NULL,
    ip           VARCHAR(45) NOT NULL,
    attempted_at DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
    KEY idx_user_time (username, attempted_at),
    KEY idx_ip_time (ip, attempted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

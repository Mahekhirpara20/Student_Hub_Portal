-- Practical 12 tables for the existing StudentHub database.
-- This script does not alter or remove Practical 8/11 data.
CREATE DATABASE IF NOT EXISTS studenthub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub_db;

CREATE TABLE IF NOT EXISTS practical12_events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name VARCHAR(150) NOT NULL,
    description TEXT NOT NULL,
    event_date DATE NOT NULL,
    event_time TIME NOT NULL,
    venue VARCHAR(150) NOT NULL,
    status ENUM('Upcoming', 'Completed', 'Cancelled') NOT NULL DEFAULT 'Upcoming',
    poster_path VARCHAR(255) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (event_id),
    KEY idx_practical12_events_date (event_date),
    KEY idx_practical12_events_status (status),
    KEY idx_practical12_events_name (event_name)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- The existing `users` table and its password hashes are reused.
-- If no Admin exists, create one through setup_admin.php on localhost.
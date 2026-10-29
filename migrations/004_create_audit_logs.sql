-- Practical 14: retain administrative actions without changing existing rows.
-- Apply after migrations/001_create_users.sql and 002_add_user_role.sql.
USE studenthub_db;

CREATE TABLE IF NOT EXISTS audit_logs (
    id INT NOT NULL AUTO_INCREMENT,
    user_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    ip_address VARCHAR(45) NULL,
    PRIMARY KEY (id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_general_ci;

-- Preserve IDs in databases that applied the earlier log_id definition.
SET @audit_logs_has_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'audit_logs'
      AND COLUMN_NAME = 'id'
);
SET @audit_logs_has_log_id = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'audit_logs'
      AND COLUMN_NAME = 'log_id'
);
SET @audit_logs_rename_id_sql = IF(
    @audit_logs_has_id = 0 AND @audit_logs_has_log_id > 0,
    'ALTER TABLE audit_logs CHANGE COLUMN log_id id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT',
    'SET @audit_logs_noop = 1'
);
PREPARE audit_logs_rename_id_statement FROM @audit_logs_rename_id_sql;
EXECUTE audit_logs_rename_id_statement;
DEALLOCATE PREPARE audit_logs_rename_id_statement;

-- Add the optional IP field to an older audit table without changing its rows.
SET @audit_logs_has_ip_address = (
    SELECT COUNT(*)
    FROM information_schema.COLUMNS
    WHERE TABLE_SCHEMA = DATABASE()
      AND TABLE_NAME = 'audit_logs'
      AND COLUMN_NAME = 'ip_address'
);
SET @audit_logs_add_ip_sql = IF(
    @audit_logs_has_ip_address = 0,
    'ALTER TABLE audit_logs ADD COLUMN ip_address VARCHAR(45) NULL AFTER created_at',
    'SET @audit_logs_noop = 1'
);
PREPARE audit_logs_add_ip_statement FROM @audit_logs_add_ip_sql;
EXECUTE audit_logs_add_ip_statement;
DEALLOCATE PREPARE audit_logs_add_ip_statement;

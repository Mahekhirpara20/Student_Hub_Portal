-- Practical 11: add soft-delete status to existing student records.
-- Existing rows receive Active through the non-null default.
-- One-time migration; do not rerun after the status column exists.
USE studenthub_db;

ALTER TABLE students
    ADD COLUMN status ENUM('Active', 'Inactive') NOT NULL DEFAULT 'Active'
    AFTER semester;
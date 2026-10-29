-- Practical 15/Profile: add optional contact and personal fields for profile editing.
USE studenthub_db;

ALTER TABLE users 
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS dob DATE NULL,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL;

ALTER TABLE students 
    ADD COLUMN IF NOT EXISTS phone VARCHAR(20) NULL,
    ADD COLUMN IF NOT EXISTS dob DATE NULL,
    ADD COLUMN IF NOT EXISTS address VARCHAR(255) NULL;

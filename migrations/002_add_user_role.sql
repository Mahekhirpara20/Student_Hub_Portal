-- StudentHub Practical 10: add role-based access to existing accounts.
-- Existing users receive Student by default. Assign Admin explicitly to a
-- designated account after verifying the account owner.
ALTER TABLE users
    ADD COLUMN role ENUM('Admin', 'Student') NOT NULL DEFAULT 'Student'
    AFTER password_hash;

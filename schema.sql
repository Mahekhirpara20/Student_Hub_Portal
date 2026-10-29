-- StudentHub Practical 8: normalized database schema and demonstration data.
-- Safe to import repeatedly: existing tables are not dropped and fixture rows
-- with duplicate keys are left unchanged.
CREATE DATABASE IF NOT EXISTS studenthub_db
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;

USE studenthub_db;

-- Account table used by the Practical 9 authentication migrations.
-- Keeping this base definition here lets the audit-log foreign key be created
-- when this schema is imported before the incremental migrations are applied.
CREATE TABLE IF NOT EXISTS users (
    user_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    username VARCHAR(50) NOT NULL,
    email VARCHAR(254) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (user_id),
    UNIQUE KEY uq_users_username (username),
    UNIQUE KEY uq_users_email (email)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Records real administrative changes. This matches the existing Practical 14
-- table; user_id is not a foreign key because the live column is signed INT
-- while users.user_id is unsigned INT.
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

-- Stores each student's details once.
CREATE TABLE IF NOT EXISTS students (
    student_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    full_name VARCHAR(100) NOT NULL,
    email VARCHAR(254) NOT NULL,
    branch VARCHAR(100) NOT NULL,
    semester TINYINT UNSIGNED NOT NULL,
    PRIMARY KEY (student_id),
    UNIQUE KEY uq_students_email (email),
    CONSTRAINT chk_students_semester CHECK (semester BETWEEN 1 AND 12)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Stores event details independently of the students who register.
CREATE TABLE IF NOT EXISTS events (
    event_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    event_name VARCHAR(150) NOT NULL,
    event_date DATE NOT NULL,
    venue VARCHAR(150) NOT NULL,
    description TEXT NULL,
    PRIMARY KEY (event_id)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- A registration links one student to one event. The foreign keys prevent
-- orphan records, and the composite unique key prevents duplicate sign-ups.
CREATE TABLE IF NOT EXISTS registrations (
    registration_id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    student_id INT UNSIGNED NOT NULL,
    event_id INT UNSIGNED NOT NULL,
    registration_date DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    status ENUM('registered', 'attended', 'cancelled') NOT NULL DEFAULT 'registered',
    PRIMARY KEY (registration_id),
    UNIQUE KEY uq_registrations_student_event (student_id, event_id),
    KEY idx_registrations_event_id (event_id),
    CONSTRAINT fk_registrations_student
        FOREIGN KEY (student_id) REFERENCES students (student_id)
        ON UPDATE CASCADE ON DELETE CASCADE,
    CONSTRAINT fk_registrations_event
        FOREIGN KEY (event_id) REFERENCES events (event_id)
        ON UPDATE CASCADE ON DELETE CASCADE
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- Demonstration students. INSERT IGNORE keeps a repeated import from
-- overwriting an existing row with the same primary key or unique email.
INSERT IGNORE INTO students (student_id, full_name, email, branch, semester) VALUES
    (1, 'Aanya Patel', 'aanya.patel@example.com', 'Computer Engineering', 5),
    (2, 'Rohan Shah', 'rohan.shah@example.com', 'Information Technology', 3),
    (3, 'Meera Joshi', 'meera.joshi@example.com', 'Computer Engineering', 7),
    (4, 'Kabir Desai', 'kabir.desai@example.com', 'Electronics Engineering', 5),
    (5, 'Isha Mehta', 'isha.mehta@example.com', 'Information Technology', 1);

-- Demonstration events.
INSERT IGNORE INTO events (event_id, event_name, event_date, venue, description) VALUES
    (1, 'Innovation and Coding Day', '2026-11-14', 'Main Auditorium', 'A student coding and project showcase.'),
    (2, 'Career Readiness Workshop', '2026-11-21', 'Seminar Hall A', 'A practical workshop on resumes and interviews.'),
    (3, 'Campus Technology Expo', '2026-12-05', 'Exhibition Centre', 'Student demonstrations of technology projects.'),
    (4, 'Annual Sports Meet', '2027-01-16', 'College Sports Ground', 'An inter-department sports and activities day.');

-- Each referenced student and event exists above; no student is registered
-- more than once for the same event.
INSERT IGNORE INTO registrations
    (registration_id, student_id, event_id, registration_date, status) VALUES
    (1, 1, 1, '2026-10-01 09:15:00', 'registered'),
    (2, 2, 1, '2026-10-02 10:30:00', 'registered'),
    (3, 3, 2, '2026-10-03 11:00:00', 'registered'),
    (4, 4, 2, '2026-10-04 12:20:00', 'registered'),
    (5, 5, 3, '2026-10-05 13:45:00', 'registered'),
    (6, 1, 4, '2026-10-06 14:10:00', 'registered');

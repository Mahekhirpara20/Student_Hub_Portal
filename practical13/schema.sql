-- Practical 13 inventory and role-preview navigation tables.
-- Run this in the existing studenthub_db database. It only creates missing
-- tables and inserts missing menu links; it never drops or resets data.

USE studenthub_db;

CREATE TABLE IF NOT EXISTS items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    name VARCHAR(100) NOT NULL,
    category VARCHAR(100) NOT NULL,
    price DECIMAL(10, 2) UNSIGNED NOT NULL,
    PRIMARY KEY (id),
    KEY idx_items_name (name),
    KEY idx_items_category (category),
    CONSTRAINT chk_items_price_positive CHECK (price > 0)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS menu_items (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT,
    title VARCHAR(80) NOT NULL,
    link VARCHAR(255) NOT NULL,
    min_role ENUM('user', 'editor', 'admin') NOT NULL DEFAULT 'user',
    PRIMARY KEY (id),
    UNIQUE KEY uq_menu_items_link (link),
    KEY idx_menu_items_min_role (min_role)
) ENGINE=InnoDB
  DEFAULT CHARSET=utf8mb4
  COLLATE=utf8mb4_unicode_ci;

-- INSERT IGNORE keeps repeated imports from replacing rows or duplicating links.
INSERT IGNORE INTO menu_items (title, link, min_role) VALUES
    ('Home', '../index.html', 'user'),
    ('Account overview', '../dashboard.php', 'user'),
    ('Events', '../events.html', 'editor'),
    ('Inventory', 'index.html', 'editor'),
    ('Student records', '../student_management.php', 'admin'),
    ('Admin dashboard', '../admin_dashboard.php', 'admin');

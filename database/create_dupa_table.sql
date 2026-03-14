-- ============================================
-- DUPA: One table for Manpower, Equipment, Materials
-- ============================================
-- Run this in phpMyAdmin (SQL tab) with your app database selected (e.g. bdpt_db).
-- Or run: mysql -u root -p bdpt_db < create_dupa_table.sql
-- ============================================

-- Use your app database (same as in config/database.php)
USE bdpt_db;

-- One table; category = 'manpower' | 'equipment' | 'materials'
CREATE TABLE IF NOT EXISTS project_dupa_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category ENUM('manpower','equipment','materials') NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(15,2) NOT NULL DEFAULT 1,
    unit VARCHAR(50) DEFAULT NULL COMMENT 'e.g. hrs, days, pcs',
    rate DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'hourly rate or unit cost',
    amount DECIMAL(15,2) GENERATED ALWAYS AS (quantity * rate) STORED,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_category (project_id, category),
    INDEX idx_project_sort (project_id, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

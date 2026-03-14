-- ============================================
-- DUPA Tab: Manpower, Equipment, Materials cost breakdown
-- ============================================
-- OPTION 1 (easiest): Run only the block below in phpMyAdmin (SQL tab, select bdpt_db first):
-- ============================================

USE bdpt_db;

CREATE TABLE IF NOT EXISTS project_dupa_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    category ENUM('manpower','equipment','materials') NOT NULL,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(15,2) NOT NULL DEFAULT 1,
    unit VARCHAR(50) DEFAULT NULL,
    rate DECIMAL(15,2) NOT NULL DEFAULT 0,
    amount DECIMAL(15,2) GENERATED ALWAYS AS (quantity * rate) STORED,
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_category (project_id, category),
    INDEX idx_project_sort (project_id, category, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- OPTION 2 (optional): Same table via stored procedure. Safe to skip if you used OPTION 1.
-- ============================================

DROP PROCEDURE IF EXISTS add_dupa_tables;
DELIMITER //
CREATE PROCEDURE add_dupa_tables()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_dupa_items'
    ) THEN
        CREATE TABLE project_dupa_items (
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
    END IF;
END //
DELIMITER ;

CALL add_dupa_tables();
DROP PROCEDURE IF EXISTS add_dupa_tables;

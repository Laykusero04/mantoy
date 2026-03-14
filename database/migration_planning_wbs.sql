-- ============================================
-- Planning / Design: WBS and Activity Planning
-- ============================================
-- Run against your project database (e.g. bdpt_db).
-- Creates tables for Work Breakdown Structure (WBS)
-- and detailed activity planning linked to projects.
-- Idempotent: safe to run multiple times.
-- ============================================

USE bdpt_db;

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_planning_wbs_tables()
BEGIN
    -- WBS items per project (hierarchical via parent_id, manual code like 1, 1.1, II-A)
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_wbs_items'
    ) THEN
        CREATE TABLE planning_wbs_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            parent_id INT DEFAULT NULL,
            code VARCHAR(50) NOT NULL,
            description VARCHAR(500) NOT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            quantity DECIMAL(15,2) DEFAULT NULL,
            output VARCHAR(255) DEFAULT NULL,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (parent_id) REFERENCES planning_wbs_items(id) ON DELETE SET NULL,
            INDEX idx_project (project_id),
            INDEX idx_project_sort (project_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;

    -- Activity planning linked to WBS items
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_activities'
    ) THEN
        CREATE TABLE planning_activities (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            wbs_item_id INT DEFAULT NULL,
            activity VARCHAR(255) NOT NULL,
            unit VARCHAR(50) DEFAULT NULL,
            quantity DECIMAL(15,2) DEFAULT NULL,
            productivity DECIMAL(15,4) DEFAULT NULL COMMENT 'e.g. qty per day per team',
            manpower DECIMAL(10,2) DEFAULT NULL COMMENT 'number of workers',
            duration_days DECIMAL(10,2) DEFAULT NULL,
            remarks VARCHAR(500) DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (wbs_item_id) REFERENCES planning_wbs_items(id) ON DELETE SET NULL,
            INDEX idx_project (project_id),
            INDEX idx_project_wbs (project_id, wbs_item_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END //
DELIMITER ;

CALL add_planning_wbs_tables();
DROP PROCEDURE IF EXISTS add_planning_wbs_tables;

-- Add budget_item_id to link WBS breakdowns to Scope of Work (budget_items)
DROP PROCEDURE IF EXISTS add_wbs_budget_item_id;
DELIMITER //
CREATE PROCEDURE add_wbs_budget_item_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_wbs_items' AND COLUMN_NAME = 'budget_item_id'
    ) THEN
        ALTER TABLE planning_wbs_items
            ADD COLUMN budget_item_id INT DEFAULT NULL AFTER parent_id,
            ADD INDEX idx_budget_item (budget_item_id);
        -- FK optional: only if budget_items exists
        IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budget_items') THEN
            ALTER TABLE planning_wbs_items
                ADD CONSTRAINT fk_wbs_budget_item
                FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL;
        END IF;
    END IF;
END //
DELIMITER ;

CALL add_wbs_budget_item_id();
DROP PROCEDURE IF EXISTS add_wbs_budget_item_id;

-- ============================================
-- Migration complete for Planning WBS & Activities
-- ============================================


-- ============================================
-- Scope of Work Tasks: hierarchical tasks under each SOW (budget_items)
-- Master for scheduling. Supports: 1, 1.1, 1.2, 2, 2.1 with duration, dates, dependency.
-- ============================================
-- Run after budget_items and migration_budget_duration. Safe to run if table exists.
-- ============================================

USE bdpt_db;

DROP PROCEDURE IF EXISTS add_sow_tasks_table;
DELIMITER //
CREATE PROCEDURE add_sow_tasks_table()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sow_tasks'
    ) THEN
        CREATE TABLE sow_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            budget_item_id INT NULL COMMENT 'Scope of Work header (budget_items)',
            parent_id INT NULL COMMENT 'Parent task (sow_tasks.id); NULL = top-level under SOW',
            code VARCHAR(50) NULL COMMENT 'Display code e.g. 1, 1.1, 1.2',
            description VARCHAR(500) NOT NULL DEFAULT '',
            unit VARCHAR(50) NULL,
            quantity DECIMAL(15,2) NULL,
            planned_duration_days INT NULL COMMENT 'Leaf: manual; parent: auto-sum of children',
            planned_start_date DATE NULL,
            planned_end_date DATE NULL,
            dependency_task_id INT NULL COMMENT 'Task must finish before this one starts',
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL,
            FOREIGN KEY (parent_id) REFERENCES sow_tasks(id) ON DELETE SET NULL,
            FOREIGN KEY (dependency_task_id) REFERENCES sow_tasks(id) ON DELETE SET NULL,
            INDEX idx_sow_project (project_id),
            INDEX idx_sow_budget (budget_item_id),
            INDEX idx_sow_parent (parent_id),
            INDEX idx_sow_sort (project_id, budget_item_id, parent_id, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END //
DELIMITER ;
CALL add_sow_tasks_table();
DROP PROCEDURE IF EXISTS add_sow_tasks_table;

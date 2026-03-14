-- ============================================
-- DUPA: Link items to Scope of Work (budget_items)
-- Run after migration_dupa.sql. Safe to run if column already exists.
-- ============================================

DROP PROCEDURE IF EXISTS add_dupa_budget_item_id;
DELIMITER //
CREATE PROCEDURE add_dupa_budget_item_id()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_dupa_items' AND COLUMN_NAME = 'budget_item_id'
    ) THEN
        ALTER TABLE project_dupa_items
            ADD COLUMN budget_item_id INT NULL AFTER project_id,
            ADD INDEX idx_dupa_budget_category (project_id, budget_item_id, category);
    END IF;
END //
DELIMITER ;
CALL add_dupa_budget_item_id();
DROP PROCEDURE IF EXISTS add_dupa_budget_item_id;

-- Add FK only if budget_items exists (optional, run once)
DROP PROCEDURE IF EXISTS add_dupa_budget_item_fk;
DELIMITER //
CREATE PROCEDURE add_dupa_budget_item_fk()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budget_items')
       AND EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_dupa_items' AND COLUMN_NAME = 'budget_item_id')
       AND NOT EXISTS (SELECT 1 FROM information_schema.TABLE_CONSTRAINTS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_dupa_items' AND CONSTRAINT_NAME = 'fk_dupa_budget_item')
    THEN
        ALTER TABLE project_dupa_items ADD CONSTRAINT fk_dupa_budget_item
            FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL;
    END IF;
END //
DELIMITER ;
CALL add_dupa_budget_item_fk();
DROP PROCEDURE IF EXISTS add_dupa_budget_item_fk;

-- ============================================
-- Budget items: duration (days) per Scope of Work — basis for planned timeline
-- ============================================
-- Run against your project database (e.g. bdpt_db). Requires budget_items table.
-- ============================================

USE bdpt_db;

DROP PROCEDURE IF EXISTS add_budget_duration;
DELIMITER //
CREATE PROCEDURE add_budget_duration()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budget_items' AND COLUMN_NAME = 'duration_days'
    ) THEN
        ALTER TABLE budget_items
            ADD COLUMN duration_days INT NULL COMMENT 'Planned duration in days (basis for timeline)' AFTER total_cost;
    END IF;
END //
DELIMITER ;
CALL add_budget_duration();
DROP PROCEDURE IF EXISTS add_budget_duration;

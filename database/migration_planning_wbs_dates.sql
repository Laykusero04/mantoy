-- ============================================
-- Planning WBS: add planned duration and dates
-- ============================================
-- Adds planned_duration_days, planned_start_date, planned_end_date
-- to planning_wbs_items for execution planning in Planning tab.
-- Idempotent: safe to run multiple times.
-- ============================================

USE bdpt_db;

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_wbs_planned_dates_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_wbs_items' AND COLUMN_NAME = 'planned_duration_days'
    ) THEN
        ALTER TABLE planning_wbs_items
            ADD COLUMN planned_duration_days INT NULL COMMENT 'Planned working days for execution' AFTER output;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_wbs_items' AND COLUMN_NAME = 'planned_start_date'
    ) THEN
        ALTER TABLE planning_wbs_items
            ADD COLUMN planned_start_date DATE NULL AFTER planned_duration_days;
    END IF;
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'planning_wbs_items' AND COLUMN_NAME = 'planned_end_date'
    ) THEN
        ALTER TABLE planning_wbs_items
            ADD COLUMN planned_end_date DATE NULL AFTER planned_start_date;
    END IF;
END //
DELIMITER ;

CALL add_wbs_planned_dates_columns();
DROP PROCEDURE IF EXISTS add_wbs_planned_dates_columns;

-- ============================================
-- Migration complete for WBS planned dates
-- ============================================

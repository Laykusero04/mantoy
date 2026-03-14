-- ============================================
-- Calendar event progress and daily cost
-- ============================================
-- Adds: daily_cost (project actual_cost = SUM of event daily costs),
--       actual_accomplishment, remaining_work (per-event progress).
-- Run after migration_v2 / base schema.
-- ============================================

DROP PROCEDURE IF EXISTS add_calendar_progress_columns;
DELIMITER //
CREATE PROCEDURE add_calendar_progress_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'daily_cost') THEN
        ALTER TABLE calendar_events ADD COLUMN daily_cost DECIMAL(15,2) DEFAULT NULL COMMENT 'Cost for this day; project actual_cost = SUM(daily_cost)' AFTER show_on_main;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'actual_accomplishment') THEN
        ALTER TABLE calendar_events ADD COLUMN actual_accomplishment TEXT DEFAULT NULL COMMENT 'What was accomplished on this event/day' AFTER daily_cost;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'remaining_work') THEN
        ALTER TABLE calendar_events ADD COLUMN remaining_work TEXT DEFAULT NULL COMMENT 'Remaining or unfinished work' AFTER actual_accomplishment;
    END IF;
END //
DELIMITER ;

CALL add_calendar_progress_columns();
DROP PROCEDURE IF EXISTS add_calendar_progress_columns;

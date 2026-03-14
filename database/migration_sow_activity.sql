-- ============================================
-- Scope of Work Activity Log (Calendar → Action Log)
-- ============================================
-- Run against your project database (e.g. bdpt_db).
-- Adds columns to action_logs for Scope of Work entries from calendar.
-- ============================================

DROP PROCEDURE IF EXISTS add_action_logs_sow_columns;
DELIMITER //
CREATE PROCEDURE add_action_logs_sow_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'is_scope_of_work') THEN
        ALTER TABLE action_logs ADD COLUMN is_scope_of_work TINYINT(1) NOT NULL DEFAULT 0 AFTER log_date;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'budget_item_id') THEN
        ALTER TABLE action_logs ADD COLUMN budget_item_id INT NULL AFTER is_scope_of_work;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'calendar_event_id') THEN
        ALTER TABLE action_logs ADD COLUMN calendar_event_id INT NULL AFTER budget_item_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'quantity') THEN
        ALTER TABLE action_logs ADD COLUMN quantity DECIMAL(10,2) NULL AFTER calendar_event_id;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'unit') THEN
        ALTER TABLE action_logs ADD COLUMN unit VARCHAR(50) NULL AFTER quantity;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'unit_cost') THEN
        ALTER TABLE action_logs ADD COLUMN unit_cost DECIMAL(15,2) NULL AFTER unit;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'amount') THEN
        ALTER TABLE action_logs ADD COLUMN amount DECIMAL(15,2) NULL AFTER unit_cost;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'quantity_delivered') THEN
        ALTER TABLE action_logs ADD COLUMN quantity_delivered DECIMAL(10,2) NULL AFTER amount;
    END IF;
END //
DELIMITER ;

CALL add_action_logs_sow_columns();
DROP PROCEDURE IF EXISTS add_action_logs_sow_columns;

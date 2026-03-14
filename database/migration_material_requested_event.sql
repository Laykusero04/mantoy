-- ============================================
-- Materials: quantity requested, notes, link to calendar event, scope of work
-- ============================================
-- Run after migration_material_withdrawals.sql.
-- Depends on budget_items and migration_dupa_budget_item (DUPA items linked to SOW).
-- ============================================

USE bdpt_db;

DROP PROCEDURE IF EXISTS add_material_withdrawal_requested_event;
DELIMITER //
CREATE PROCEDURE add_material_withdrawal_requested_event()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_material_withdrawals' AND COLUMN_NAME = 'quantity_requested') THEN
        ALTER TABLE project_material_withdrawals ADD COLUMN quantity_requested DECIMAL(15,2) NULL COMMENT 'Requested qty; quantity = delivered' AFTER withdrawal_date;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_material_withdrawals' AND COLUMN_NAME = 'calendar_event_id') THEN
        ALTER TABLE project_material_withdrawals ADD COLUMN calendar_event_id INT NULL COMMENT 'When set, row was created from calendar event' AFTER remarks;
        ALTER TABLE project_material_withdrawals ADD CONSTRAINT fk_withdrawal_calendar_event
            FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id) ON DELETE SET NULL;
        CREATE INDEX idx_withdrawals_calendar_event ON project_material_withdrawals(calendar_event_id);
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_material_withdrawals' AND COLUMN_NAME = 'budget_item_id') THEN
        ALTER TABLE project_material_withdrawals ADD COLUMN budget_item_id INT NULL COMMENT 'Scope of work this withdrawal is encoded under' AFTER calendar_event_id;
        IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'budget_items') THEN
            ALTER TABLE project_material_withdrawals ADD CONSTRAINT fk_withdrawal_budget_item
                FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL;
        END IF;
        CREATE INDEX idx_withdrawals_budget_item ON project_material_withdrawals(budget_item_id);
    END IF;
END //
DELIMITER ;
CALL add_material_withdrawal_requested_event();
DROP PROCEDURE IF EXISTS add_material_withdrawal_requested_event;

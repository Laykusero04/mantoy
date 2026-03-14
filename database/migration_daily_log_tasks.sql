-- ============================================
-- Daily log ↔ SOW tasks: link tasks worked per day and track actual progress
-- ============================================
-- Adds to sow_tasks: actual_quantity, actual_duration_days.
-- Creates daily_log_tasks (calendar_event_id, sow_task_id, quantity_done, duration_days_done).
-- Run after migration_sow_tasks.sql and migration_daily_log.sql. Safe to run if columns/table exist.
-- ============================================

USE bdpt_db;

-- Add actual progress columns to sow_tasks
DROP PROCEDURE IF EXISTS add_sow_tasks_actual_columns;
DELIMITER //
CREATE PROCEDURE add_sow_tasks_actual_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sow_tasks' AND COLUMN_NAME = 'actual_quantity') THEN
        ALTER TABLE sow_tasks ADD COLUMN actual_quantity DECIMAL(15,2) NULL COMMENT 'Actual quantity completed (from daily logs)' AFTER quantity;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'sow_tasks' AND COLUMN_NAME = 'actual_duration_days') THEN
        ALTER TABLE sow_tasks ADD COLUMN actual_duration_days DECIMAL(10,2) NULL COMMENT 'Actual duration in days (from daily logs)' AFTER planned_end_date;
    END IF;
END //
DELIMITER ;
CALL add_sow_tasks_actual_columns();
DROP PROCEDURE IF EXISTS add_sow_tasks_actual_columns;

-- Junction: which tasks were worked on which daily log, and how much
DROP PROCEDURE IF EXISTS add_daily_log_tasks_table;
DELIMITER //
CREATE PROCEDURE add_daily_log_tasks_table()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.TABLES
        WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'daily_log_tasks'
    ) THEN
        CREATE TABLE daily_log_tasks (
            id INT AUTO_INCREMENT PRIMARY KEY,
            calendar_event_id INT NOT NULL,
            sow_task_id INT NOT NULL,
            quantity_done DECIMAL(15,2) NULL COMMENT 'Quantity completed on this day',
            duration_days_done DECIMAL(10,2) NULL COMMENT 'Duration (days) worked on this day',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (calendar_event_id) REFERENCES calendar_events(id) ON DELETE CASCADE,
            FOREIGN KEY (sow_task_id) REFERENCES sow_tasks(id) ON DELETE CASCADE,
            UNIQUE KEY uq_event_task (calendar_event_id, sow_task_id),
            INDEX idx_dlt_event (calendar_event_id),
            INDEX idx_dlt_task (sow_task_id)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
    END IF;
END //
DELIMITER ;
CALL add_daily_log_tasks_table();
DROP PROCEDURE IF EXISTS add_daily_log_tasks_table;

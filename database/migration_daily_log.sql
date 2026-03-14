-- ============================================
-- Daily Construction Log: extend calendar_events and link photos
-- ============================================
-- Adds: weather, temperature, workers_count, equipment_used, issues.
-- Ensures attachments can use module_type = 'daily_log' for event photos.
-- Run after migration_calendar_progress.sql. Safe to run if columns exist.
-- ============================================

USE bdpt_db;

DROP PROCEDURE IF EXISTS add_daily_log_columns;
DELIMITER //
CREATE PROCEDURE add_daily_log_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'weather') THEN
        ALTER TABLE calendar_events ADD COLUMN weather VARCHAR(50) NULL COMMENT 'e.g. Sunny, Cloudy, Rain' AFTER remaining_work;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'temperature') THEN
        ALTER TABLE calendar_events ADD COLUMN temperature DECIMAL(5,2) NULL COMMENT 'e.g. 28.5' AFTER weather;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'workers_count') THEN
        ALTER TABLE calendar_events ADD COLUMN workers_count INT NULL COMMENT 'Number of workers this day' AFTER temperature;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'equipment_used') THEN
        ALTER TABLE calendar_events ADD COLUMN equipment_used TEXT NULL COMMENT 'Equipment used this day' AFTER workers_count;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'calendar_events' AND COLUMN_NAME = 'issues') THEN
        ALTER TABLE calendar_events ADD COLUMN issues TEXT NULL COMMENT 'Issues or concerns' AFTER equipment_used;
    END IF;
END //
DELIMITER ;
CALL add_daily_log_columns();
DROP PROCEDURE IF EXISTS add_daily_log_columns;

-- Add 'daily_log' to attachments.module_type ENUM if present
DROP PROCEDURE IF EXISTS add_attachments_daily_log_enum;
DELIMITER //
CREATE PROCEDURE add_attachments_daily_log_enum()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'attachments' AND COLUMN_NAME = 'module_type') THEN
        ALTER TABLE attachments MODIFY COLUMN module_type ENUM('inspection','administration','planning','construction','monitoring','turnover','general','daily_log') DEFAULT 'general';
    END IF;
END //
DELIMITER ;
CALL add_attachments_daily_log_enum();
DROP PROCEDURE IF EXISTS add_attachments_daily_log_enum;

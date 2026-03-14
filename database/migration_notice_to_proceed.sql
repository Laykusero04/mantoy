-- ============================================
-- Add notice_to_proceed to projects (NTP = official project start)
-- ============================================
-- Idempotent: safe to run multiple times.
-- Uses current database (no hardcoded schema name).
-- ============================================

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_notice_to_proceed_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = DATABASE()
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'notice_to_proceed'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN notice_to_proceed DATE DEFAULT NULL
            COMMENT 'Official project start date; used for timeline and planning baseline';
    END IF;
END //
DELIMITER ;

CALL add_notice_to_proceed_column();
DROP PROCEDURE IF EXISTS add_notice_to_proceed_column;

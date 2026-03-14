-- ============================================
-- Holidays (global no-work) + Project weekend + Monitoring (manpower, etc.)
-- ============================================
-- Run against bdpt_db. Adds: holidays table, project weekend flags,
-- action_logs.manpower, project_timeline_tasks.planned_manpower.
-- ============================================

-- Global holidays (no work for all projects)
CREATE TABLE IF NOT EXISTS holidays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    holiday_date DATE NOT NULL UNIQUE,
    description VARCHAR(255) NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_holidays_date (holiday_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project: Saturday/Sunday working (0 = no work that day, 1 = working)
DROP PROCEDURE IF EXISTS add_project_weekend_columns;
DELIMITER //
CREATE PROCEDURE add_project_weekend_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'saturday_working') THEN
        ALTER TABLE projects ADD COLUMN saturday_working TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Sat is working day';
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'sunday_working') THEN
        ALTER TABLE projects ADD COLUMN sunday_working TINYINT(1) NOT NULL DEFAULT 0 COMMENT '1=Sun is working day';
    END IF;
END //
DELIMITER ;
CALL add_project_weekend_columns();
DROP PROCEDURE IF EXISTS add_project_weekend_columns;

-- Action log: manpower (person-days or headcount for that log entry)
DROP PROCEDURE IF EXISTS add_action_logs_manpower;
DELIMITER //
CREATE PROCEDURE add_action_logs_manpower()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'action_logs' AND COLUMN_NAME = 'manpower') THEN
        ALTER TABLE action_logs ADD COLUMN manpower DECIMAL(10,2) NULL COMMENT 'Person-days or headcount' AFTER amount;
    END IF;
END //
DELIMITER ;
CALL add_action_logs_manpower();
DROP PROCEDURE IF EXISTS add_action_logs_manpower;

-- Timeline task: planned manpower (total for the task)
-- Use existing project_timeline_tasks; add column if table exists
DROP PROCEDURE IF EXISTS add_timeline_planned_manpower;
DELIMITER //
CREATE PROCEDURE add_timeline_planned_manpower()
BEGIN
    IF EXISTS (SELECT 1 FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_timeline_tasks') THEN
        IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_timeline_tasks' AND COLUMN_NAME = 'planned_manpower') THEN
            ALTER TABLE project_timeline_tasks ADD COLUMN planned_manpower DECIMAL(10,2) NULL COMMENT 'Total planned person-days for this task' AFTER planned_end_date;
        END IF;
    END IF;
END //
DELIMITER ;
CALL add_timeline_planned_manpower();
DROP PROCEDURE IF EXISTS add_timeline_planned_manpower;

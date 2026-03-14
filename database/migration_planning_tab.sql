-- ============================================
-- Planning / Design Tab Migration
-- Adds lightweight planning fields on projects
-- and a category column on attachments for
-- classifying planning-related documents.
-- ============================================

USE bdpt_db;

-- ============================================
-- 1. Add planning fields to projects (idempotent)
-- ============================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_planning_project_columns()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'planning_scope'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN planning_scope TEXT NULL
            AFTER remarks;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'planning_schedule_summary'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN planning_schedule_summary TEXT NULL
            AFTER planning_scope;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'planning_risks_notes'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN planning_risks_notes TEXT NULL
            AFTER planning_schedule_summary;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'planning_resources_notes'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN planning_resources_notes TEXT NULL
            AFTER planning_risks_notes;
    END IF;

    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'projects'
          AND COLUMN_NAME = 'planning_procurement_notes'
    ) THEN
        ALTER TABLE projects
            ADD COLUMN planning_procurement_notes TEXT NULL
            AFTER planning_resources_notes;
    END IF;
END //
DELIMITER ;

CALL add_planning_project_columns();
DROP PROCEDURE IF EXISTS add_planning_project_columns;


-- ============================================
-- 2. Add category column to attachments (idempotent)
-- ============================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_attachments_category_column()
BEGIN
    IF NOT EXISTS (
        SELECT 1 FROM information_schema.COLUMNS
        WHERE TABLE_SCHEMA = 'bdpt_db'
          AND TABLE_NAME = 'attachments'
          AND COLUMN_NAME = 'category'
    ) THEN
        ALTER TABLE attachments
            ADD COLUMN category VARCHAR(64) NULL
            AFTER module_record_id;
    END IF;
END //
DELIMITER ;

CALL add_attachments_category_column();
DROP PROCEDURE IF EXISTS add_attachments_category_column;

-- ============================================
-- Migration complete for Planning / Design tab
-- ============================================


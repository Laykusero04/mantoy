-- ============================================
-- BDPT v2 Migration: Pyramid Project Management System
-- Offline-First Construction Lifecycle Management
-- ============================================
-- Run this AFTER the original bdpt_db.sql has been applied.
-- This script is idempotent — safe to run multiple times.
-- ============================================

USE bdpt_db;

-- ============================================
-- 1. USERS TABLE (future-ready, single user for now)
-- ============================================
CREATE TABLE IF NOT EXISTS users (
    id INT AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(50) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(150) NOT NULL,
    designation VARCHAR(100) DEFAULT NULL,
    department VARCHAR(50) DEFAULT NULL,
    role ENUM('admin','supervisor','encoder','viewer') NOT NULL DEFAULT 'admin',
    is_active TINYINT(1) NOT NULL DEFAULT 1,
    last_login DATETIME DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Default admin user (password: admin123 — change immediately in production)
INSERT IGNORE INTO users (username, password_hash, full_name, role)
VALUES ('admin', '$2y$10$92IXUNpkjO0rOQ5byMi.Ye4oKoEa3Ro9llC/.og/at2.uheWG/igi', 'System Administrator', 'admin');


-- ============================================
-- 2. ALTER PROJECTS TABLE — Add construction fields
-- ============================================
-- Check and add columns only if they don't exist (MySQL 8.0 doesn't support IF NOT EXISTS for ADD COLUMN directly)
-- We use a stored procedure for safety

DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_project_columns()
BEGIN
    -- contractor_name
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='contractor_name') THEN
        ALTER TABLE projects ADD COLUMN contractor_name VARCHAR(255) DEFAULT NULL AFTER contact_person;
    END IF;
    -- contract_amount
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='contract_amount') THEN
        ALTER TABLE projects ADD COLUMN contract_amount DECIMAL(15,2) DEFAULT NULL AFTER contractor_name;
    END IF;
    -- contract_duration
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='contract_duration') THEN
        ALTER TABLE projects ADD COLUMN contract_duration INT DEFAULT NULL COMMENT 'Calendar days' AFTER contract_amount;
    END IF;
    -- date_of_award
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='date_of_award') THEN
        ALTER TABLE projects ADD COLUMN date_of_award DATE DEFAULT NULL AFTER contract_duration;
    END IF;
    -- notice_to_proceed
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='notice_to_proceed') THEN
        ALTER TABLE projects ADD COLUMN notice_to_proceed DATE DEFAULT NULL AFTER date_of_award;
    END IF;
    -- contract_completion_date
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='contract_completion_date') THEN
        ALTER TABLE projects ADD COLUMN contract_completion_date DATE DEFAULT NULL AFTER notice_to_proceed;
    END IF;
    -- revised_completion_date
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='revised_completion_date') THEN
        ALTER TABLE projects ADD COLUMN revised_completion_date DATE DEFAULT NULL AFTER contract_completion_date;
    END IF;
    -- project_engineer
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='project_engineer') THEN
        ALTER TABLE projects ADD COLUMN project_engineer VARCHAR(150) DEFAULT NULL AFTER revised_completion_date;
    END IF;
    -- project_inspector
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='project_inspector') THEN
        ALTER TABLE projects ADD COLUMN project_inspector VARCHAR(150) DEFAULT NULL AFTER project_engineer;
    END IF;
    -- source_of_fund
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='source_of_fund') THEN
        ALTER TABLE projects ADD COLUMN source_of_fund VARCHAR(255) DEFAULT NULL AFTER project_inspector;
    END IF;
    -- appropriation_no
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='appropriation_no') THEN
        ALTER TABLE projects ADD COLUMN appropriation_no VARCHAR(100) DEFAULT NULL AFTER source_of_fund;
    END IF;
    -- created_by
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='projects' AND COLUMN_NAME='created_by') THEN
        ALTER TABLE projects ADD COLUMN created_by INT DEFAULT NULL AFTER fiscal_year;
        ALTER TABLE projects ADD INDEX idx_created_by (created_by);
    END IF;
END //
DELIMITER ;

CALL add_project_columns();
DROP PROCEDURE IF EXISTS add_project_columns;


-- ============================================
-- 3. ALTER ATTACHMENTS TABLE — Add module tagging
-- ============================================
DELIMITER //
CREATE PROCEDURE IF NOT EXISTS add_attachment_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA='bdpt_db' AND TABLE_NAME='attachments' AND COLUMN_NAME='module_type') THEN
        ALTER TABLE attachments
            ADD COLUMN module_type ENUM('inspection','administration','planning','construction','monitoring','turnover','general') DEFAULT 'general' AFTER project_id,
            ADD COLUMN module_record_id INT DEFAULT NULL AFTER module_type,
            ADD INDEX idx_module (project_id, module_type);
    END IF;
END //
DELIMITER ;

CALL add_attachment_columns();
DROP PROCEDURE IF EXISTS add_attachment_columns;


-- ============================================
-- 4. INSPECTION MODULE
-- ============================================
CREATE TABLE IF NOT EXISTS inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    inspection_date DATE NOT NULL,
    inspection_type ENUM('routine','pre_final','final','special') NOT NULL DEFAULT 'routine',
    inspector_name VARCHAR(150) NOT NULL,
    inspector_designation VARCHAR(100) DEFAULT NULL,
    percentage_completed DECIMAL(5,2) DEFAULT 0.00,
    weather_condition VARCHAR(100) DEFAULT NULL,
    findings TEXT DEFAULT NULL,
    recommendations TEXT DEFAULT NULL,
    overall_status ENUM('satisfactory','unsatisfactory','needs_improvement') DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_date (project_id, inspection_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS inspection_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    inspection_id INT NOT NULL,
    item_no INT NOT NULL DEFAULT 1,
    description VARCHAR(500) NOT NULL,
    finding VARCHAR(500) DEFAULT NULL,
    status ENUM('pass','fail','na') NOT NULL DEFAULT 'na',
    remarks VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (inspection_id) REFERENCES inspections(id) ON DELETE CASCADE,
    INDEX idx_inspection (inspection_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 5. ADMINISTRATION MODULE (Letters)
-- ============================================
CREATE TABLE IF NOT EXISTS letters (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    letter_type ENUM('incoming','outgoing') NOT NULL,
    reference_no VARCHAR(100) DEFAULT NULL,
    subject VARCHAR(500) NOT NULL,
    letter_date DATE NOT NULL,
    date_received DATE DEFAULT NULL COMMENT 'For incoming letters',
    date_sent DATE DEFAULT NULL COMMENT 'For outgoing letters',
    sender VARCHAR(255) DEFAULT NULL COMMENT 'For incoming',
    sender_office VARCHAR(255) DEFAULT NULL,
    recipient VARCHAR(255) DEFAULT NULL COMMENT 'For outgoing',
    recipient_office VARCHAR(255) DEFAULT NULL,
    action_required TEXT DEFAULT NULL,
    action_taken TEXT DEFAULT NULL,
    status ENUM('pending','acted_upon','filed') NOT NULL DEFAULT 'pending',
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_type (project_id, letter_type),
    INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 6. PLANNING & PROGRAMMING MODULE (Program of Works)
-- ============================================
CREATE TABLE IF NOT EXISTS program_of_works (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL UNIQUE COMMENT 'One POW per project',
    pow_no VARCHAR(50) DEFAULT NULL,
    prepared_by VARCHAR(150) DEFAULT NULL,
    checked_by VARCHAR(150) DEFAULT NULL,
    approved_by VARCHAR(150) DEFAULT NULL,
    total_project_cost DECIMAL(15,2) DEFAULT 0.00,
    status ENUM('draft','approved','revised') NOT NULL DEFAULT 'draft',
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS pow_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    pow_id INT NOT NULL,
    item_no VARCHAR(20) NOT NULL COMMENT 'e.g. 1, 1.1, 1.1.1, II-A',
    description VARCHAR(500) NOT NULL,
    unit VARCHAR(50) DEFAULT NULL COMMENT 'e.g. sq.m, cu.m, l.m, lot, each',
    quantity DECIMAL(12,4) DEFAULT 0,
    unit_cost DECIMAL(12,2) DEFAULT 0.00,
    total_amount DECIMAL(15,2) GENERATED ALWAYS AS (quantity * unit_cost) STORED,
    weight_percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Weight for accomplishment calc',
    scheduled_start DATE DEFAULT NULL,
    scheduled_end DATE DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    parent_item_id INT DEFAULT NULL COMMENT 'For sub-items hierarchy',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (pow_id) REFERENCES program_of_works(id) ON DELETE CASCADE,
    FOREIGN KEY (parent_item_id) REFERENCES pow_items(id) ON DELETE SET NULL,
    INDEX idx_pow (pow_id),
    INDEX idx_sort (pow_id, sort_order)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 7. CONSTRUCTION / IMPLEMENTATION MODULE
-- ============================================

-- Daily Weather Log (one per project per day)
CREATE TABLE IF NOT EXISTS weather_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    log_date DATE NOT NULL,
    am_weather ENUM('clear','partly_cloudy','cloudy','light_rain','heavy_rain','storm') DEFAULT 'clear',
    pm_weather ENUM('clear','partly_cloudy','cloudy','light_rain','heavy_rain','storm') DEFAULT 'clear',
    temperature_high DECIMAL(4,1) DEFAULT NULL COMMENT 'Celsius',
    temperature_low DECIMAL(4,1) DEFAULT NULL COMMENT 'Celsius',
    work_suspended TINYINT(1) NOT NULL DEFAULT 0,
    suspension_reason VARCHAR(500) DEFAULT NULL,
    remarks VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_date (project_id, log_date),
    INDEX idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Daily Activity Log (one per project per day)
CREATE TABLE IF NOT EXISTS daily_activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    log_date DATE NOT NULL,
    day_number INT DEFAULT NULL COMMENT 'Construction day count',
    overall_status ENUM('on_track','behind','ahead','suspended') DEFAULT 'on_track',
    activities_summary TEXT DEFAULT NULL,
    materials_delivered TEXT DEFAULT NULL,
    equipment_used TEXT DEFAULT NULL,
    problems_encountered TEXT DEFAULT NULL,
    instructions_received TEXT DEFAULT NULL,
    visitors TEXT DEFAULT NULL,
    prepared_by VARCHAR(150) DEFAULT NULL,
    noted_by VARCHAR(150) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_date (project_id, log_date),
    INDEX idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Activity line items (breakdown of work done per day)
CREATE TABLE IF NOT EXISTS daily_activity_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    daily_log_id INT NOT NULL,
    pow_item_id INT DEFAULT NULL COMMENT 'Links to POW item if applicable',
    description VARCHAR(500) NOT NULL,
    quantity_accomplished DECIMAL(12,4) DEFAULT 0,
    unit VARCHAR(50) DEFAULT NULL,
    location_area VARCHAR(255) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (daily_log_id) REFERENCES daily_activity_logs(id) ON DELETE CASCADE,
    FOREIGN KEY (pow_item_id) REFERENCES pow_items(id) ON DELETE SET NULL,
    INDEX idx_daily_log (daily_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manpower Log (one per project per day)
CREATE TABLE IF NOT EXISTS manpower_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    log_date DATE NOT NULL,
    total_workers INT NOT NULL DEFAULT 0,
    total_man_hours DECIMAL(8,2) DEFAULT 0.00,
    remarks VARCHAR(500) DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_date (project_id, log_date),
    INDEX idx_date (log_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Manpower by trade/position per day
CREATE TABLE IF NOT EXISTS manpower_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    manpower_log_id INT NOT NULL,
    trade VARCHAR(100) NOT NULL COMMENT 'e.g. Foreman, Carpenter, Laborer, Mason, Electrician',
    num_workers INT NOT NULL DEFAULT 0,
    hours_worked DECIMAL(4,1) NOT NULL DEFAULT 8.0,
    overtime_hours DECIMAL(4,1) DEFAULT 0.0,
    daily_rate DECIMAL(8,2) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (manpower_log_id) REFERENCES manpower_logs(id) ON DELETE CASCADE,
    INDEX idx_log (manpower_log_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 8. MONITORING & CONTROL MODULE
-- ============================================

-- Accomplishment period entries (weekly or monthly snapshots)
CREATE TABLE IF NOT EXISTS accomplishments (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    period_type ENUM('weekly','monthly') NOT NULL,
    period_start DATE NOT NULL,
    period_end DATE NOT NULL,
    overall_planned_pct DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Target % for this period',
    overall_actual_pct DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Actual % for this period',
    overall_cumulative_pct DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Cumulative % to date',
    slippage DECIMAL(5,2) GENERATED ALWAYS AS (overall_actual_pct - overall_planned_pct) STORED,
    weather_working_days INT DEFAULT NULL,
    weather_non_working_days INT DEFAULT NULL,
    prepared_by VARCHAR(150) DEFAULT NULL,
    checked_by VARCHAR(150) DEFAULT NULL,
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    UNIQUE KEY uk_project_period (project_id, period_type, period_start),
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Accomplishment per POW item (detailed breakdown)
CREATE TABLE IF NOT EXISTS accomplishment_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    accomplishment_id INT NOT NULL,
    pow_item_id INT NOT NULL,
    previous_qty DECIMAL(12,4) DEFAULT 0 COMMENT 'Qty accomplished before this period',
    this_period_qty DECIMAL(12,4) DEFAULT 0 COMMENT 'Qty accomplished this period',
    to_date_qty DECIMAL(12,4) GENERATED ALWAYS AS (previous_qty + this_period_qty) STORED,
    percentage DECIMAL(5,2) DEFAULT 0.00 COMMENT 'Computed: to_date_qty / pow_item.quantity * 100',
    remarks VARCHAR(500) DEFAULT NULL,
    FOREIGN KEY (accomplishment_id) REFERENCES accomplishments(id) ON DELETE CASCADE,
    FOREIGN KEY (pow_item_id) REFERENCES pow_items(id) ON DELETE CASCADE,
    INDEX idx_accomplishment (accomplishment_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project delay tracking
CREATE TABLE IF NOT EXISTS project_delays (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    delay_date DATE NOT NULL,
    delay_type ENUM('weather','material','labor','equipment','design_change','force_majeure','other') NOT NULL,
    days_delayed INT NOT NULL DEFAULT 1,
    description TEXT NOT NULL,
    action_taken TEXT DEFAULT NULL,
    status ENUM('ongoing','resolved') NOT NULL DEFAULT 'ongoing',
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 9. TURNOVER & CLOSEOUT MODULE
-- ============================================
CREATE TABLE IF NOT EXISTS turnovers (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    turnover_date DATE NOT NULL,
    turnover_type ENUM('partial','final') NOT NULL DEFAULT 'final',
    turned_over_by VARCHAR(150) NOT NULL,
    turned_over_designation VARCHAR(100) DEFAULT NULL,
    received_by VARCHAR(150) NOT NULL,
    received_designation VARCHAR(100) DEFAULT NULL,
    receiving_office VARCHAR(255) DEFAULT NULL,
    project_cost_final DECIMAL(15,2) DEFAULT NULL,
    overall_condition ENUM('good','fair','poor') DEFAULT 'good',
    deficiencies TEXT DEFAULT NULL,
    warranty_period VARCHAR(100) DEFAULT NULL COMMENT 'e.g. 1 year from turnover',
    status ENUM('draft','completed') NOT NULL DEFAULT 'draft',
    remarks TEXT DEFAULT NULL,
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project (project_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE IF NOT EXISTS turnover_items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    turnover_id INT NOT NULL,
    item_no INT NOT NULL DEFAULT 1,
    description VARCHAR(500) NOT NULL,
    quantity DECIMAL(12,4) DEFAULT NULL,
    unit VARCHAR(50) DEFAULT NULL,
    item_condition ENUM('good','fair','poor','defective') DEFAULT 'good',
    remarks VARCHAR(500) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    FOREIGN KEY (turnover_id) REFERENCES turnovers(id) ON DELETE CASCADE,
    INDEX idx_turnover (turnover_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 10. PHOTO & VIDEO DOCUMENTATION
-- ============================================
CREATE TABLE IF NOT EXISTS photos (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    module_type ENUM('inspection','administration','planning','construction','monitoring','turnover','general') NOT NULL DEFAULT 'general',
    module_record_id INT DEFAULT NULL COMMENT 'FK to specific record in module (nullable)',
    file_name VARCHAR(255) NOT NULL,
    file_original_name VARCHAR(255) NOT NULL,
    file_type VARCHAR(50) NOT NULL,
    file_size INT NOT NULL DEFAULT 0,
    media_type ENUM('photo','video') NOT NULL DEFAULT 'photo',
    caption VARCHAR(500) DEFAULT NULL,
    date_taken DATE DEFAULT NULL,
    gps_latitude DECIMAL(10,8) DEFAULT NULL,
    gps_longitude DECIMAL(11,8) DEFAULT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    uploaded_by INT DEFAULT NULL,
    uploaded_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_project_module (project_id, module_type),
    INDEX idx_date (date_taken)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 11. CALENDAR EVENTS
-- ============================================
CREATE TABLE IF NOT EXISTS calendar_events (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT DEFAULT NULL COMMENT 'NULL = global event',
    title VARCHAR(255) NOT NULL,
    description TEXT DEFAULT NULL,
    event_type ENUM('deadline','milestone','inspection','meeting','activity','reminder','other') NOT NULL DEFAULT 'other',
    event_date DATE NOT NULL,
    event_end_date DATE DEFAULT NULL,
    event_time TIME DEFAULT NULL,
    is_all_day TINYINT(1) NOT NULL DEFAULT 1,
    is_auto_generated TINYINT(1) NOT NULL DEFAULT 0 COMMENT 'System-generated from other data',
    source_table VARCHAR(50) DEFAULT NULL COMMENT 'Table that generated this event',
    source_id INT DEFAULT NULL COMMENT 'Record ID in source table',
    color VARCHAR(7) DEFAULT NULL COMMENT 'Hex color for display',
    created_by INT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_date (event_date),
    INDEX idx_project (project_id),
    INDEX idx_source (source_table, source_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


-- ============================================
-- 12. WORKFLOW DATA MIGRATION
-- ============================================
-- Migrate Incoming/Outgoing Communication stages to letters table
-- Only migrates stages that have notes content

INSERT IGNORE INTO letters (project_id, letter_type, subject, letter_date, action_taken, status, remarks, created_at)
SELECT
    ws.project_id,
    CASE
        WHEN ws.stage_type = 'Incoming Communication' THEN 'incoming'
        WHEN ws.stage_type = 'Outgoing Communication' THEN 'outgoing'
    END AS letter_type,
    CONCAT('[Migrated] ', ws.stage_type) AS subject,
    DATE(COALESCE(ws.completed_at, ws.created_at)) AS letter_date,
    ws.notes AS action_taken,
    CASE ws.status
        WHEN 'Completed' THEN 'acted_upon'
        ELSE 'pending'
    END AS status,
    CONCAT('Migrated from workflow stage. Original status: ', ws.status) AS remarks,
    ws.created_at
FROM workflow_stages ws
WHERE ws.stage_type IN ('Incoming Communication', 'Outgoing Communication')
AND ws.notes IS NOT NULL AND ws.notes != '';

-- Migrate workflow_attachments to enhanced attachments table
INSERT IGNORE INTO attachments (project_id, module_type, file_name, file_original_name, file_type, file_size, description, uploaded_at)
SELECT
    wa.project_id,
    'administration' AS module_type,
    wa.file_name,
    wa.file_original_name,
    wa.file_type,
    wa.file_size,
    CONCAT('[Migrated from ', ws.stage_type, '] ', COALESCE(wa.description, '')) AS description,
    wa.uploaded_at
FROM workflow_attachments wa
JOIN workflow_stages ws ON ws.id = wa.stage_id;


-- ============================================
-- 13. RETIRE OLD WORKFLOW TABLES (rename, don't drop)
-- ============================================
-- Rename to _old_ prefix — drop manually after verifying migration
-- These are commented out for safety. Uncomment when ready.

-- RENAME TABLE workflow_stages TO _old_workflow_stages;
-- RENAME TABLE workflow_attachments TO _old_workflow_attachments;


-- ============================================
-- MIGRATION COMPLETE
-- ============================================
-- Expected table count: 25 active tables
-- Run: SHOW TABLES FROM bdpt_db; to verify
-- ============================================

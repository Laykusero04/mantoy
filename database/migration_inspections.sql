-- ============================================
-- Project Inspections + calendar integration base schema
-- ============================================
-- Run against bdpt_db after base schema.
-- ============================================

USE bdpt_db;

-- Main inspections table
CREATE TABLE IF NOT EXISTS project_inspections (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_id INT NULL COMMENT 'Optional link to task / activity',
    inspection_type VARCHAR(100) NOT NULL,
    date_requested DATE NOT NULL,
    date_inspected DATE NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' COMMENT 'pending, approved, rejected',
    remarks TEXT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_inspections_project
        FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    INDEX idx_inspections_project_task (project_id, task_id),
    INDEX idx_inspections_dates (date_requested, date_inspected)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


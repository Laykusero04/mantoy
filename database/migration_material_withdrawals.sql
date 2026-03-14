-- ============================================
-- Materials Monitoring: withdrawals + log header + row remarks
-- ============================================
-- Run against bdpt_db. Requires project_dupa_items (migration_dupa.sql).
-- ============================================

USE bdpt_db;

-- Withdrawals: one row per (project, material, date)
CREATE TABLE IF NOT EXISTS project_material_withdrawals (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    dupa_item_id INT NOT NULL,
    withdrawal_date DATE NOT NULL,
    quantity DECIMAL(15,2) NOT NULL DEFAULT 0,
    unit_cost DECIMAL(15,2) NULL COMMENT 'Optional; if null use DUPA item rate',
    remarks VARCHAR(500) NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (dupa_item_id) REFERENCES project_dupa_items(id) ON DELETE CASCADE,
    UNIQUE KEY uq_material_withdrawal (project_id, dupa_item_id, withdrawal_date),
    INDEX idx_withdrawals_project_date (project_id, withdrawal_date)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Project-level log header (Supplier, P.O Number, Term Days, Alloted Amount)
DROP PROCEDURE IF EXISTS add_project_material_log_columns;
DELIMITER //
CREATE PROCEDURE add_project_material_log_columns()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'material_supplier') THEN
        ALTER TABLE projects ADD COLUMN material_supplier VARCHAR(255) NULL AFTER remarks;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'material_po_number') THEN
        ALTER TABLE projects ADD COLUMN material_po_number VARCHAR(100) NULL AFTER material_supplier;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'material_term_days') THEN
        ALTER TABLE projects ADD COLUMN material_term_days INT NULL COMMENT 'Term days for material log' AFTER material_po_number;
    END IF;
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'projects' AND COLUMN_NAME = 'material_alloted_amount') THEN
        ALTER TABLE projects ADD COLUMN material_alloted_amount DECIMAL(15,2) NULL AFTER material_term_days;
    END IF;
END //
DELIMITER ;
CALL add_project_material_log_columns();
DROP PROCEDURE IF EXISTS add_project_material_log_columns;

-- Row remarks on DUPA items (for material monitoring REMARKS column)
DROP PROCEDURE IF EXISTS add_dupa_remarks_column;
DELIMITER //
CREATE PROCEDURE add_dupa_remarks_column()
BEGIN
    IF NOT EXISTS (SELECT 1 FROM information_schema.COLUMNS WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = 'project_dupa_items' AND COLUMN_NAME = 'remarks') THEN
        ALTER TABLE project_dupa_items ADD COLUMN remarks VARCHAR(500) NULL AFTER sort_order;
    END IF;
END //
DELIMITER ;
CALL add_dupa_remarks_column();
DROP PROCEDURE IF EXISTS add_dupa_remarks_column;

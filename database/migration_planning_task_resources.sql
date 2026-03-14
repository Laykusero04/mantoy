-- ============================================
-- Planning: Task-level resource requirements
-- Links resources (DUPA: manpower, equipment, materials) to sow_tasks or wbs_items.
-- Per-day plan is derived in code from task dates + use_rule (start/end/spread).
-- ============================================
-- Run after: migration_sow_tasks.sql, migration_planning_wbs.sql, create_dupa_table.sql
-- ============================================

USE bdpt_db;

-- planning_task_resources: one row per resource assigned to a task
CREATE TABLE IF NOT EXISTS planning_task_resources (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    task_type ENUM('sow_task','wbs_item') NOT NULL,
    sow_task_id INT NULL,
    wbs_item_id INT NULL,
    dupa_item_id INT NULL COMMENT 'Link to project_dupa_items; NULL = use resource_description',
    resource_description VARCHAR(255) NULL COMMENT 'Free-text when dupa_item_id is NULL',
    quantity DECIMAL(15,2) NOT NULL DEFAULT 1,
    unit VARCHAR(50) NULL,
    use_rule ENUM('start','end','spread') NOT NULL DEFAULT 'spread' COMMENT 'start=need on start date, end=end date, spread=over duration',
    sort_order INT NOT NULL DEFAULT 0,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (sow_task_id) REFERENCES sow_tasks(id) ON DELETE CASCADE,
    FOREIGN KEY (wbs_item_id) REFERENCES planning_wbs_items(id) ON DELETE CASCADE,
    FOREIGN KEY (dupa_item_id) REFERENCES project_dupa_items(id) ON DELETE SET NULL,
    INDEX idx_ptr_project (project_id),
    INDEX idx_ptr_sow_task (sow_task_id),
    INDEX idx_ptr_wbs_item (wbs_item_id),
    INDEX idx_ptr_dupa (dupa_item_id),
    INDEX idx_ptr_task (task_type, sow_task_id, wbs_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Optional: day-level overrides (override derived quantity for a specific date)
CREATE TABLE IF NOT EXISTS planning_resource_day_overrides (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    plan_date DATE NOT NULL,
    dupa_item_id INT NULL COMMENT 'Which resource; NULL not used for overrides',
    resource_key VARCHAR(100) NULL COMMENT 'Alternative: task_type:task_id:dupa_id or similar',
    quantity_override DECIMAL(15,2) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (dupa_item_id) REFERENCES project_dupa_items(id) ON DELETE CASCADE,
    INDEX idx_pro_date (project_id, plan_date),
    INDEX idx_pro_dupa (project_id, dupa_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- ============================================
-- Project Timeline (planned vs actual)
-- ============================================
-- Tasks can be linked to Scope of Work (budget_item_id); actual dates
-- are then derived from action_logs (calendar events with SOW).
-- ============================================

CREATE TABLE IF NOT EXISTS project_timeline_tasks (
    id INT AUTO_INCREMENT PRIMARY KEY,
    project_id INT NOT NULL,
    budget_item_id INT NULL COMMENT 'When set, actual is derived from action_logs for this SOW',
    task_label VARCHAR(255) NOT NULL COMMENT 'Display name (from SOW item or custom)',
    planned_start_date DATE NOT NULL,
    planned_end_date DATE NOT NULL,
    sort_order INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
    FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL,
    INDEX idx_timeline_project (project_id),
    INDEX idx_timeline_budget (budget_item_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

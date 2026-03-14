<?php
/**
 * One-click DUPA table setup. Run once by opening in browser:
 * http://localhost/mantoy/database/run_migration_dupa.php
 * Then delete or ignore this file if you want.
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>DUPA migration</title>';
echo '<style>body{font-family:sans-serif;max-width:560px;margin:2rem auto;padding:1rem;} .ok{color:#0d6efd;} .err{color:#dc3545;} pre{background:#f5f5f5;padding:0.75rem;border-radius:6px;}</style></head><body>';

$tableName = 'project_dupa_items';

try {
    $chk = $pdo->query("SHOW TABLES LIKE '" . $tableName . "'");
    if ($chk && $chk->rowCount() > 0) {
        echo '<p class="ok"><strong>Table <code>' . htmlspecialchars($tableName) . '</code> already exists.</strong> Nothing to do.</p>';
        echo '<p><a href="' . BASE_URL . '/pages/projects/view.php">Back to projects</a></p>';
        echo '</body></html>';
        exit;
    }

    $pdo->exec("
        CREATE TABLE project_dupa_items (
            id INT AUTO_INCREMENT PRIMARY KEY,
            project_id INT NOT NULL,
            category ENUM('manpower','equipment','materials') NOT NULL,
            description VARCHAR(500) NOT NULL,
            quantity DECIMAL(15,2) NOT NULL DEFAULT 1,
            unit VARCHAR(50) DEFAULT NULL COMMENT 'e.g. hrs, days, pcs',
            rate DECIMAL(15,2) NOT NULL DEFAULT 0 COMMENT 'hourly rate or unit cost',
            amount DECIMAL(15,2) GENERATED ALWAYS AS (quantity * rate) STORED,
            sort_order INT NOT NULL DEFAULT 0,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
            INDEX idx_project_category (project_id, category),
            INDEX idx_project_sort (project_id, category, sort_order)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");

    echo '<p class="ok"><strong>Table <code>' . htmlspecialchars($tableName) . '</code> created successfully.</strong></p>';
    echo '<p>You can now use the DUPA tab on project view pages.</p>';
    echo '<p><a href="' . BASE_URL . '/pages/projects/view.php">Back to projects</a></p>';
} catch (PDOException $e) {
    echo '<p class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
    echo '<p>Ensure MySQL is running and the <code>projects</code> table exists (DUPA table references it).</p>';
}

echo '</body></html>';
?>

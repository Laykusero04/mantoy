<?php
/**
 * One-time migration: copy Planning/Design (planning_wbs_items) into Scope of Work tasks (sow_tasks).
 * Run after: migration_sow_tasks.sql, and that planning_wbs_items and budget_items exist.
 *
 * Open in browser: http://localhost/mantoy/database/migrate_planning_to_sow.php
 * Or run: php database/migrate_planning_to_sow.php
 */
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';

header('Content-Type: text/html; charset=utf-8');
echo '<!DOCTYPE html><html><head><meta charset="utf-8"><title>Planning → SOW migration</title>';
echo '<style>body{font-family:sans-serif;max-width:640px;margin:2rem auto;padding:1rem;} .ok{color:#198754;} .err{color:#dc3545;} .muted{color:#6c757d;} ul{margin:0.5rem 0;}</style></head><body>';
echo '<h5>Planning / Design → Scope of Work (sow_tasks)</h5>';

try {
    $chkSow = $pdo->query("SHOW TABLES LIKE 'sow_tasks'");
    if (!$chkSow || $chkSow->rowCount() === 0) {
        echo '<p class="err">Table <code>sow_tasks</code> not found. Run <code>database/migration_sow_tasks.sql</code> first.</p>';
        echo '</body></html>';
        exit;
    }

    $chkWbs = $pdo->query("SHOW TABLES LIKE 'planning_wbs_items'");
    if (!$chkWbs || $chkWbs->rowCount() === 0) {
        echo '<p class="muted">Table <code>planning_wbs_items</code> not found. Nothing to migrate.</p>';
        echo '<p><a href="' . BASE_URL . '/pages/projects/view.php">Back to projects</a></p>';
        echo '</body></html>';
        exit;
    }

    $stmt = $pdo->query("SELECT id, project_id, parent_id, budget_item_id, code, description, unit, quantity, output,
        COALESCE(planned_duration_days, 0) AS planned_duration_days, planned_start_date, planned_end_date, sort_order
        FROM planning_wbs_items ORDER BY COALESCE(parent_id, 0), sort_order, id");
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (empty($all)) {
        echo '<p class="muted">No rows in <code>planning_wbs_items</code>. Nothing to migrate.</p>';
        echo '</body></html>';
        exit;
    }

    // Order so parents come before children (topological)
    $byParent = [];
    foreach ($all as $r) {
        $pid = isset($r['parent_id']) && $r['parent_id'] !== '' ? (int)$r['parent_id'] : null;
        if (!isset($byParent[$pid])) $byParent[$pid] = [];
        $byParent[$pid][] = $r;
    }
    $ordered = [];
    $append = function ($parentId) use (&$append, &$ordered, $byParent) {
        if (!isset($byParent[$parentId])) return;
        foreach ($byParent[$parentId] as $r) {
            $ordered[] = $r;
            $append((int)$r['id']);
        }
    };
    $append(null);

    $idMap = []; // old wbs id => new sow_tasks id
    $ins = $pdo->prepare("
        INSERT INTO sow_tasks (project_id, budget_item_id, parent_id, code, description, unit, quantity, planned_duration_days, planned_start_date, planned_end_date, sort_order)
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($ordered as $r) {
        $parentId = isset($r['parent_id']) && $r['parent_id'] !== null && $r['parent_id'] !== '' ? ($idMap[(int)$r['parent_id']] ?? null) : null;
        $budgetItemId = isset($r['budget_item_id']) && $r['budget_item_id'] !== null && $r['budget_item_id'] !== '' ? (int)$r['budget_item_id'] : null;
        $ins->execute([
            (int)$r['project_id'],
            $budgetItemId ?: null,
            $parentId,
            $r['code'] ?? null,
            $r['description'] ?? '',
            $r['unit'] ?? null,
            $r['quantity'] ?? null,
            (int)$r['planned_duration_days'] ?: null,
            $r['planned_start_date'] ?? null,
            $r['planned_end_date'] ?? null,
            (int)($r['sort_order'] ?? 0),
        ]);
        $idMap[(int)$r['id']] = (int)$pdo->lastInsertId();
    }

    echo '<p class="ok"><strong>' . count($ordered) . ' task(s)</strong> copied from Planning WBS into <code>sow_tasks</code>.</p>';
    echo '<p class="muted">Scope of Work is now the master for these tasks. You can continue to use the Planning tab; it will be updated to read from sow_tasks.</p>';
    echo '<p><a href="' . BASE_URL . '/pages/projects/view.php">Back to projects</a></p>';
} catch (Exception $e) {
    echo '<p class="err"><strong>Error:</strong> ' . htmlspecialchars($e->getMessage()) . '</p>';
}

echo '</body></html>';

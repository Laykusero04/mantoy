<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/projects/list.php');
}

$action = $_POST['action'] ?? '';
$projectId = (int)($_POST['project_id'] ?? 0);
$redirectUrl = !empty($_POST['redirect_url']) && strpos($_POST['redirect_url'], 'id=' . $projectId) !== false
    ? $_POST['redirect_url']
    : BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=monitoring#timeline';

if ($projectId <= 0) {
    flashMessage('danger', 'Invalid project.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Ensure timeline table exists (create if migration not run yet)
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_timeline_tasks'");
    if (!$chk || $chk->rowCount() === 0) {
        $pdo->exec("
            CREATE TABLE IF NOT EXISTS project_timeline_tasks (
                id INT AUTO_INCREMENT PRIMARY KEY,
                project_id INT NOT NULL,
                budget_item_id INT NULL,
                task_label VARCHAR(255) NOT NULL,
                planned_start_date DATE NOT NULL,
                planned_end_date DATE NOT NULL,
                sort_order INT NOT NULL DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (project_id) REFERENCES projects(id) ON DELETE CASCADE,
                FOREIGN KEY (budget_item_id) REFERENCES budget_items(id) ON DELETE SET NULL,
                INDEX idx_timeline_project (project_id),
                INDEX idx_timeline_budget (budget_item_id)
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
        ");
    }
} catch (Exception $e) {
    flashMessage('danger', 'Timeline table is missing. Run database/migration_timeline.sql in your database.');
    redirect($redirectUrl);
}

switch ($action) {

    case 'add':
        $taskLabel = trim($_POST['task_label'] ?? '');
        $plannedStart = trim($_POST['planned_start_date'] ?? '');
        $plannedEnd = trim($_POST['planned_end_date'] ?? '');
        $budgetItemId = !empty($_POST['budget_item_id']) ? (int)$_POST['budget_item_id'] : null;

        if ($taskLabel === '' || $plannedStart === '' || $plannedEnd === '') {
            flashMessage('danger', 'Task description and planned dates are required.');
            redirect($redirectUrl);
        }
        if (strtotime($plannedStart) > strtotime($plannedEnd)) {
            flashMessage('danger', 'Planned start must be on or before planned end.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM project_timeline_tasks WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $nextOrder = $stmt->fetchColumn();

        $plannedManpower = isset($_POST['planned_manpower']) && $_POST['planned_manpower'] !== '' ? (float)$_POST['planned_manpower'] : null;
        $ins = $pdo->prepare("
            INSERT INTO project_timeline_tasks (project_id, budget_item_id, task_label, planned_start_date, planned_end_date, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([$projectId, $budgetItemId, $taskLabel, $plannedStart, $plannedEnd, $nextOrder]);
        $newId = (int)$pdo->lastInsertId();
        if ($newId > 0 && $plannedManpower !== null) {
            try {
                $pdo->prepare("UPDATE project_timeline_tasks SET planned_manpower = ? WHERE id = ? AND project_id = ?")->execute([$plannedManpower, $newId, $projectId]);
            } catch (PDOException $e) { }
        }
        flashMessage('success', 'Timeline task added.');
        redirect($redirectUrl);
        break;

    case 'update':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId <= 0) {
            flashMessage('danger', 'Invalid task.');
            redirect($redirectUrl);
        }
        $taskLabel = trim($_POST['task_label'] ?? '');
        $plannedStart = trim($_POST['planned_start_date'] ?? '');
        $plannedEnd = trim($_POST['planned_end_date'] ?? '');
        $budgetItemId = isset($_POST['budget_item_id']) && $_POST['budget_item_id'] !== '' ? (int)$_POST['budget_item_id'] : null;
        $durationWorkingDays = isset($_POST['planned_duration_working_days']) ? (int)$_POST['planned_duration_working_days'] : 0;

        if ($taskLabel === '' || $plannedStart === '') {
            flashMessage('danger', 'Task description and planned start date are required.');
            redirect($redirectUrl);
        }

        // When duration (working days) is provided, compute end date from start + working days so bar and dates stay in sync
        if ($durationWorkingDays >= 1 && $durationWorkingDays <= 365) {
            $satWorking = false;
            $sunWorking = false;
            try {
                $proj = $pdo->prepare("SELECT saturday_working, sunday_working FROM projects WHERE id = ? AND is_deleted = 0");
                $proj->execute([$projectId]);
                $projRow = $proj->fetch(PDO::FETCH_ASSOC);
                if ($projRow) {
                    $satWorking = !empty($projRow['saturday_working']);
                    $sunWorking = !empty($projRow['sunday_working']);
                }
            } catch (Exception $e) { }
            $holidayDates = [];
            try {
                $holidayRows = $pdo->query("SELECT holiday_date FROM holidays ORDER BY holiday_date")->fetchAll(PDO::FETCH_COLUMN);
                $holidayDates = array_map(function ($d) { return date('Y-m-d', strtotime($d)); }, $holidayRows);
            } catch (Exception $e) { }
            $isNoWork = function ($dateYmd) use ($holidayDates, $satWorking, $sunWorking) {
                $ts = strtotime($dateYmd);
                $dow = (int)date('N', $ts);
                $isSat = ($dow === 6);
                $isSun = ($dow === 7);
                $isHoliday = in_array($dateYmd, $holidayDates, true);
                return $isHoliday || (($isSat && !$satWorking) || ($isSun && !$sunWorking));
            };
            $count = 0;
            $endTs = strtotime($plannedStart);
            $maxDays = 400;
            while ($count < $durationWorkingDays && $maxDays-- > 0) {
                $d = date('Y-m-d', $endTs);
                if (!$isNoWork($d)) $count++;
                if ($count >= $durationWorkingDays) break;
                $endTs += 86400;
            }
            $plannedEnd = date('Y-m-d', $endTs);
        }

        if ($plannedEnd === '' || strtotime($plannedStart) > strtotime($plannedEnd)) {
            flashMessage('danger', 'Planned start must be on or before planned end.');
            redirect($redirectUrl);
        }

        $upd = $pdo->prepare("
            UPDATE project_timeline_tasks SET task_label = ?, budget_item_id = ?, planned_start_date = ?, planned_end_date = ?
            WHERE id = ? AND project_id = ?
        ");
        $upd->execute([$taskLabel, $budgetItemId, $plannedStart, $plannedEnd, $taskId, $projectId]);
        if ($upd->rowCount() === 0) {
            flashMessage('warning', 'Task not found or no change. Please refresh the page.');
        } else {
            flashMessage('success', 'Timeline task updated.');
        }
        $plannedManpower = isset($_POST['planned_manpower']) && $_POST['planned_manpower'] !== '' ? (float)$_POST['planned_manpower'] : null;
        try {
            $pdo->prepare("UPDATE project_timeline_tasks SET planned_manpower = ? WHERE id = ? AND project_id = ?")->execute([$plannedManpower, $taskId, $projectId]);
        } catch (PDOException $e) { }
        redirect($redirectUrl);
        break;

    case 'delete':
        $taskId = (int)($_POST['task_id'] ?? 0);
        if ($taskId > 0) {
            $del = $pdo->prepare("DELETE FROM project_timeline_tasks WHERE id = ? AND project_id = ?");
            $del->execute([$taskId, $projectId]);
            flashMessage('success', 'Timeline task removed.');
        }
        redirect($redirectUrl);
        break;

    case 'sync_sow':
        // Encode Scope of Work into timeline: create one timeline task per budget item that doesn't have one yet
        $proj = $pdo->prepare("SELECT notice_to_proceed, start_date, target_end_date FROM projects WHERE id = ? AND is_deleted = 0");
        $proj->execute([$projectId]);
        $projRow = $proj->fetch(PDO::FETCH_ASSOC);
        if (!$projRow) {
            flashMessage('danger', 'Project not found.');
            redirect($redirectUrl);
        }
        $defaultStart = ($projRow['notice_to_proceed'] ?? $projRow['start_date']) ?: date('Y-m-d');
        $defaultEnd = $projRow['target_end_date'] ?: date('Y-m-d', strtotime('+30 days'));

        $existing = $pdo->prepare("SELECT budget_item_id FROM project_timeline_tasks WHERE project_id = ? AND budget_item_id IS NOT NULL");
        $existing->execute([$projectId]);
        $existingIds = array_filter(array_column($existing->fetchAll(PDO::FETCH_ASSOC), 'budget_item_id'));

        $budgets = $pdo->prepare("SELECT id, item_name FROM budget_items WHERE project_id = ? ORDER BY id");
        $budgets->execute([$projectId]);
        $items = $budgets->fetchAll(PDO::FETCH_ASSOC);

        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM project_timeline_tasks WHERE project_id = ?");
        $maxOrder->execute([$projectId]);
        $nextOrder = (int)$maxOrder->fetchColumn() + 1;

        $ins = $pdo->prepare("
            INSERT INTO project_timeline_tasks (project_id, budget_item_id, task_label, planned_start_date, planned_end_date, sort_order)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $added = 0;
        foreach ($items as $item) {
            if (in_array((int)$item['id'], $existingIds)) continue;
            $ins->execute([$projectId, $item['id'], $item['item_name'], $defaultStart, $defaultEnd, $nextOrder++]);
            $added++;
        }
        flashMessage('success', $added ? "Scope of Work encoded: $added task(s) added to timeline. Set planned dates as needed." : 'All Scope of Work items are already on the timeline.');
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

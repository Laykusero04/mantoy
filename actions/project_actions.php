<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/projects/list.php');
}

$action = $_POST['action'] ?? '';
$planningTab = (isset($_POST['redirect_tab']) && $_POST['redirect_tab'] === 'budget') ? 'budget' : 'planning';

switch ($action) {

    // ── Create ──────────────────────────────────────────────
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $visibility = $_POST['visibility'] ?? 'Public';
        $department = trim($_POST['department'] ?? '');
        $projectSubtype = trim($_POST['project_subtype'] ?? '');
        $fiscalYear = $_POST['fiscal_year'] ?? date('Y');

        if ($title === '') {
            flashMessage('danger', 'Title is required.');
            redirect(BASE_URL . '/pages/projects/create.php');
        }

        // Validate classification
        if ($visibility === 'Private') {
            $department = null;
            $projectSubtype = null;
        } else {
            if ($department === '') {
                flashMessage('danger', 'Department is required for public projects.');
                redirect(BASE_URL . '/pages/projects/create.php');
            }
            $validSubtypes = getDepartmentSubtypes($pdo);
            if (!isset($validSubtypes[$department]) || !in_array($projectSubtype, $validSubtypes[$department])) {
                flashMessage('danger', 'Invalid sub-type for the selected department.');
                redirect(BASE_URL . '/pages/projects/create.php');
            }
        }

        // Map to legacy project_type for backward compatibility
        $projectTypeMap = [
            'BDP' => 'Root Project',
            'SEF' => 'SEF Project',
            'Special Projects' => 'Special Project',
            'Planning and Programming' => 'Root Project',
            'Construction' => 'Root Project',
        ];
        $projectType = $projectTypeMap[$projectSubtype] ?? 'Root Project';

        $location = trim($_POST['location'] ?? '');
        $contactPerson = trim($_POST['contact_person'] ?? '');

        $stmt = $pdo->prepare("
            INSERT INTO projects
                (title, project_type, visibility, department, project_subtype,
                 category_id, description, fiscal_year, location, contact_person)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $projectType,
            $visibility,
            $department,
            $projectSubtype,
            !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            trim($_POST['description'] ?? ''),
            $fiscalYear,
            $location ?: null,
            $contactPerson ?: null,
        ]);

        $newId = $pdo->lastInsertId();

        flashMessage('success', 'Project created successfully.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $newId);
        break;

    // ── Update ──────────────────────────────────────────────
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $visibility = $_POST['visibility'] ?? 'Public';
        $department = trim($_POST['department'] ?? '');
        $projectSubtype = trim($_POST['project_subtype'] ?? '');
        $fiscalYear = $_POST['fiscal_year'] ?? date('Y');

        if ($id <= 0 || $title === '') {
            flashMessage('danger', 'Title is required.');
            redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
        }

        // Validate classification
        if ($visibility === 'Private') {
            $department = null;
            $projectSubtype = null;
        } else {
            if ($department === '') {
                flashMessage('danger', 'Department is required for public projects.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
            }
            $validSubtypes = getDepartmentSubtypes($pdo);
            if (!isset($validSubtypes[$department]) || !in_array($projectSubtype, $validSubtypes[$department])) {
                flashMessage('danger', 'Invalid sub-type for the selected department.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
            }
        }

        // Map to legacy project_type
        $projectTypeMap = [
            'BDP' => 'Root Project',
            'SEF' => 'SEF Project',
            'Special Projects' => 'Special Project',
            'Planning and Programming' => 'Root Project',
            'Construction' => 'Root Project',
        ];
        $projectType = $projectTypeMap[$projectSubtype] ?? 'Root Project';

        $stmt = $pdo->prepare("
            UPDATE projects SET
                title = ?, project_type = ?, visibility = ?, department = ?,
                project_subtype = ?, category_id = ?, description = ?,
                priority = ?, status = ?, funding_source = ?, resolution_no = ?,
                budget_allocated = ?, actual_cost = ?, start_date = ?,
                target_end_date = ?, actual_end_date = ?, fiscal_year = ?,
                location = ?, contact_person = ?, remarks = ?,
                notice_to_proceed = ?
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([
            $title,
            $projectType,
            $visibility,
            $department,
            $projectSubtype,
            !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            trim($_POST['description'] ?? ''),
            $_POST['priority'] ?? 'Medium',
            $_POST['status'] ?? 'Not Started',
            trim($_POST['funding_source'] ?? ''),
            trim($_POST['resolution_no'] ?? ''),
            !empty($_POST['budget_allocated']) ? (float)$_POST['budget_allocated'] : 0,
            !empty($_POST['actual_cost']) ? (float)$_POST['actual_cost'] : 0,
            !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            !empty($_POST['target_end_date']) ? $_POST['target_end_date'] : null,
            !empty($_POST['actual_end_date']) ? $_POST['actual_end_date'] : null,
            $fiscalYear,
            trim($_POST['location'] ?? ''),
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['remarks'] ?? ''),
            !empty($_POST['notice_to_proceed']) ? $_POST['notice_to_proceed'] : null,
            $id,
        ]);

        $satWorking = !empty($_POST['saturday_working']) ? 1 : 0;
        $sunWorking = !empty($_POST['sunday_working']) ? 1 : 0;
        try {
            $pdo->prepare("UPDATE projects SET saturday_working = ?, sunday_working = ? WHERE id = ? AND is_deleted = 0")->execute([$satWorking, $sunWorking, $id]);
        } catch (PDOException $e) {
            // Columns may not exist if migration not run
        }

        flashMessage('success', 'Project updated successfully.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $id);
        break;

    // ── Update budget only (from Edit budget modal) ───────────
    case 'update_budget':
        $id = (int)($_POST['project_id'] ?? 0);
        if ($id <= 0) {
            flashMessage('danger', 'Invalid project.');
            redirect(BASE_URL . '/pages/projects/list.php');
        }
        $budgetAllocated = isset($_POST['budget_allocated']) ? max(0, (float)$_POST['budget_allocated']) : 0;
        $actualCost = isset($_POST['actual_cost']) ? max(0, (float)$_POST['actual_cost']) : 0;
        $stmt = $pdo->prepare("UPDATE projects SET budget_allocated = ?, actual_cost = ? WHERE id = ? AND is_deleted = 0");
        $stmt->execute([$budgetAllocated, $actualCost, $id]);
        flashMessage('success', 'Budget updated.');

        // Action log: budget change
        $msgParts = [];
        $msgParts[] = 'Budget updated';
        $msgParts[] = 'Allocated: ' . number_format($budgetAllocated, 2);
        $msgParts[] = 'Actual: ' . number_format($actualCost, 2);
        logProjectActivity(
            $pdo,
            $id,
            'Project',
            implode(' · ', $msgParts)
        );

        $back = $_POST['redirect_url'] ?? (BASE_URL . '/pages/projects/view.php?id=' . $id);
        redirect($back);
        break;

    // ── Update planning text fields (Planning / Design tab) ───
    case 'update_planning_text':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $field     = $_POST['field'] ?? '';
        $value     = trim($_POST['value'] ?? '');

        if ($projectId <= 0) {
            flashMessage('danger', 'Invalid project.');
            redirect(BASE_URL . '/pages/projects/list.php');
        }

        // Whitelist allowed planning fields
        $allowedFields = [
            'planning_scope',
            'planning_schedule_summary',
            'planning_risks_notes',
            'planning_resources_notes',
            'planning_procurement_notes',
        ];

        if (!in_array($field, $allowedFields, true)) {
            flashMessage('danger', 'Invalid planning field.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        // Optional: basic length guard
        if (mb_strlen($value) > 5000) {
            flashMessage('warning', 'Text is very long; please shorten it if possible.');
        }

        $sql = "UPDATE projects SET {$field} = :val WHERE id = :id AND is_deleted = 0";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':val' => $value !== '' ? $value : null,
            ':id'  => $projectId,
        ]);

        flashMessage('success', 'Planning information updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Planning WBS: quick add from Planning tab ─────────────
    case 'add_wbs_item_quick':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $budgetItemId = !empty($_POST['budget_item_id']) ? (int)$_POST['budget_item_id'] : null;
        $scopeIndex   = isset($_POST['scope_index']) ? (int)$_POST['scope_index'] : 0;
        $sowStart     = trim($_POST['sow_planned_start'] ?? '');
        $sowEnd       = trim($_POST['sow_planned_end'] ?? '');
        $sowDays      = isset($_POST['sow_planned_days']) && $_POST['sow_planned_days'] !== '' ? (int)$_POST['sow_planned_days'] : null;

        if ($projectId <= 0 || $budgetItemId === null) {
            flashMessage('danger', 'Invalid Scope of Work for quick breakdown.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        // Determine next sequence under this Scope of Work (top-level breakdown only: 1.1, 1.2 under SOW 1; 2.1, 2.2 under SOW 2; etc.)
        $seq = 1;
        $scopeIndex = $scopeIndex > 0 ? $scopeIndex : 1;
        try {
            // Per-SOW sequence: count only top-level items (parent_id IS NULL) for this budget_item_id
            $seqStmt = $pdo->prepare("
                SELECT COUNT(*) FROM planning_wbs_items
                WHERE project_id = ? AND budget_item_id = ? AND (parent_id IS NULL OR parent_id = 0)
            ");
            $seqStmt->execute([$projectId, $budgetItemId]);
            $seq = (int)$seqStmt->fetchColumn() + 1;
        } catch (PDOException $e) {
            // If budget_item_id or parent_id column missing, use scope_index for prefix and seq 1 so at least 2.1, 3.1 under SOW 2/3
            $seq = 1;
        }

        $code = $scopeIndex . '.' . $seq;      // e.g. 1.1, 1.2 under SOW 1; 2.1, 2.2 under SOW 2; 3.1 under SOW 3
        $desc = 'Activity ' . $seq;

        $plannedDurationDays = $sowDays ?? null;
        $plannedStartDate    = ($sowStart !== '' && strtotime($sowStart) !== false) ? $sowStart : null;
        $plannedEndDate      = ($sowEnd !== '' && strtotime($sowEnd) !== false) ? $sowEnd : null;

        // Insert new item first under this SOW (sort_order 0, then shift siblings)
        $nextOrder = 0;
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM planning_wbs_items WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $maxOrder = (int)$stmt->fetchColumn();
        $fallbackOrder = $maxOrder + 1;

        $parentId = null;
        $unit = null;
        $qty = null;
        $output = null;

        $inserted = false;
        $newId = null;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO planning_wbs_items (project_id, parent_id, budget_item_id, code, description, unit, quantity, output, planned_duration_days, planned_start_date, planned_end_date, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $projectId,
                $parentId,
                $budgetItemId,
                $code,
                $desc,
                $unit,
                $qty,
                $output,
                $plannedDurationDays,
                $plannedStartDate,
                $plannedEndDate,
                $nextOrder,
            ]);
            $newId = (int)$pdo->lastInsertId();
            $inserted = true;
            try {
                $pdo->prepare("UPDATE planning_wbs_items SET sort_order = sort_order + 1 WHERE project_id = ? AND budget_item_id = ? AND id != ?")->execute([$projectId, $budgetItemId, $newId]);
            } catch (PDOException $e) { /* column may not exist */ }
        } catch (PDOException $e) {
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO planning_wbs_items (project_id, parent_id, budget_item_id, code, description, unit, quantity, output, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $projectId,
                    $parentId,
                    $budgetItemId,
                    $code,
                    $desc,
                    $unit,
                    $qty,
                    $output,
                    $nextOrder,
                ]);
                $newId = (int)$pdo->lastInsertId();
                $inserted = true;
                try {
                    $pdo->prepare("UPDATE planning_wbs_items SET sort_order = sort_order + 1 WHERE project_id = ? AND budget_item_id = ? AND id != ?")->execute([$projectId, $budgetItemId, $newId]);
                } catch (PDOException $e2) { }
            } catch (PDOException $e2) {
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO planning_wbs_items (project_id, parent_id, code, description, unit, quantity, output, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $projectId,
                        $parentId,
                        $code,
                        $desc,
                        $unit,
                        $qty,
                        $output,
                        $fallbackOrder,
                    ]);
                    $inserted = true;
                } catch (PDOException $e3) {
                    flashMessage('danger', 'Could not add WBS item. Run database/migration_planning_wbs.sql.');
                    redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
                }
            }
        }

        flashMessage('success', 'Quick breakdown added.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Planning WBS: add / delete items ─────────────────────
    case 'add_wbs_item':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $code         = trim($_POST['code'] ?? '');
        $desc         = trim($_POST['description'] ?? '');
        $unit         = trim($_POST['unit'] ?? '');
        $qty          = $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
        $output       = trim($_POST['output'] ?? '');
        $parentId     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $budgetItemId = !empty($_POST['budget_item_id']) ? (int)$_POST['budget_item_id'] : null;
        $plannedDurationDays = isset($_POST['planned_duration_days']) && $_POST['planned_duration_days'] !== '' ? (int)$_POST['planned_duration_days'] : null;
        $plannedStartDate    = trim($_POST['planned_start_date'] ?? '');
        $plannedEndDate      = trim($_POST['planned_end_date'] ?? '');
        if ($plannedStartDate === '' || strtotime($plannedStartDate) === false) $plannedStartDate = null;
        if ($plannedEndDate === '' || strtotime($plannedEndDate) === false) $plannedEndDate = null;

        if ($projectId <= 0 || $code === '' || $desc === '') {
            flashMessage('danger', 'Code and description are required for WBS items.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        // When adding under a WBS parent, inherit budget_item_id from parent for traceability
        if ($parentId) {
            $parentRow = $pdo->prepare("SELECT budget_item_id FROM planning_wbs_items WHERE id = ? AND project_id = ?");
            $parentRow->execute([$parentId, $projectId]);
            $parent = $parentRow->fetch(PDO::FETCH_ASSOC);
            if ($parent && isset($parent['budget_item_id']) && $parent['budget_item_id'] !== null) {
                $budgetItemId = (int)$parent['budget_item_id'];
            }
        }

        // Determine next sort order within project
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM planning_wbs_items WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $nextOrder = (int)$stmt->fetchColumn() + 1;

        $inserted = false;
        try {
            $stmt = $pdo->prepare("
                INSERT INTO planning_wbs_items (project_id, parent_id, budget_item_id, code, description, unit, quantity, output, planned_duration_days, planned_start_date, planned_end_date, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $projectId,
                $parentId,
                $budgetItemId,
                $code,
                $desc,
                $unit !== '' ? $unit : null,
                $qty,
                $output !== '' ? $output : null,
                $plannedDurationDays,
                $plannedStartDate,
                $plannedEndDate,
                $nextOrder,
            ]);
            $inserted = true;
        } catch (PDOException $e) {
            // Fallback if planned_duration_days columns not yet migrated
            try {
                $stmt = $pdo->prepare("
                    INSERT INTO planning_wbs_items (project_id, parent_id, budget_item_id, code, description, unit, quantity, output, sort_order)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $stmt->execute([
                    $projectId,
                    $parentId,
                    $budgetItemId,
                    $code,
                    $desc,
                    $unit !== '' ? $unit : null,
                    $qty,
                    $output !== '' ? $output : null,
                    $nextOrder,
                ]);
                $inserted = true;
            } catch (PDOException $e2) {
                // Fallback if budget_item_id column not yet migrated (run database/migration_planning_wbs.sql)
                try {
                    $stmt = $pdo->prepare("
                        INSERT INTO planning_wbs_items (project_id, parent_id, code, description, unit, quantity, output, sort_order)
                        VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([
                        $projectId,
                        $parentId,
                        $code,
                        $desc,
                        $unit !== '' ? $unit : null,
                        $qty,
                        $output !== '' ? $output : null,
                        $nextOrder,
                    ]);
                    $inserted = true;
                } catch (PDOException $e3) {
                    flashMessage('danger', 'Could not add WBS item. Run database/migration_planning_wbs.sql.');
                    redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
                }
            }
        }

        sync_timeline_from_planning_wbs($pdo, $projectId);
        flashMessage('success', 'WBS item added.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'update_wbs_planned_dates':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $wbsId     = (int)($_POST['wbs_id'] ?? 0);
        $plannedDurationDays = isset($_POST['planned_duration_days']) && $_POST['planned_duration_days'] !== '' ? (int)$_POST['planned_duration_days'] : null;
        $plannedStartDate    = trim($_POST['planned_start_date'] ?? '');
        $plannedEndDate      = trim($_POST['planned_end_date'] ?? '');
        if ($plannedStartDate === '' || strtotime($plannedStartDate) === false) $plannedStartDate = null;
        if ($plannedEndDate === '' || strtotime($plannedEndDate) === false) $plannedEndDate = null;

        if ($projectId <= 0 || $wbsId <= 0) {
            flashMessage('danger', 'Invalid WBS item.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        try {
            $stmt = $pdo->prepare("
                UPDATE planning_wbs_items
                SET planned_duration_days = ?, planned_start_date = ?, planned_end_date = ?
                WHERE id = ? AND project_id = ?
            ");
            $stmt->execute([$plannedDurationDays, $plannedStartDate, $plannedEndDate, $wbsId, $projectId]);
        } catch (PDOException $e) {
            flashMessage('danger', 'Could not update planned dates. Run database/migration_planning_wbs_dates.sql.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        flashMessage('success', 'Planned dates updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'update_wbs_item':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $wbsId     = (int)($_POST['wbs_id'] ?? 0);
        $code      = trim($_POST['code'] ?? '');
        $desc      = trim($_POST['description'] ?? '');

        if ($projectId <= 0 || $wbsId <= 0 || $code === '' || $desc === '') {
            flashMessage('danger', 'Code and description are required.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        $stmt = $pdo->prepare("UPDATE planning_wbs_items SET code = ?, description = ? WHERE id = ? AND project_id = ?");
        $stmt->execute([$code, $desc, $wbsId, $projectId]);
        sync_timeline_from_planning_wbs($pdo, $projectId);
        flashMessage('success', 'Activity updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'delete_wbs_item':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $wbsId     = (int)($_POST['wbs_id'] ?? 0);

        if ($projectId <= 0 || $wbsId <= 0) {
            flashMessage('danger', 'Invalid WBS item.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        $stmt = $pdo->prepare("DELETE FROM planning_wbs_items WHERE id = ? AND project_id = ?");
        $stmt->execute([$wbsId, $projectId]);

        // Optionally also clear references from activities
        $stmt = $pdo->prepare("UPDATE planning_activities SET wbs_item_id = NULL WHERE wbs_item_id = ? AND project_id = ?");
        $stmt->execute([$wbsId, $projectId]);

        flashMessage('success', 'WBS item deleted.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Scope of Work Tasks (sow_tasks): master for scheduling ────────────
    case 'add_sow_task':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $budgetItemId = !empty($_POST['budget_item_id']) ? (int)$_POST['budget_item_id'] : null;
        $parentId     = !empty($_POST['parent_id']) ? (int)$_POST['parent_id'] : null;
        $code         = trim($_POST['code'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $unit         = trim($_POST['unit'] ?? '');
        $quantity     = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
        $plannedDurationDays = isset($_POST['planned_duration_days']) && $_POST['planned_duration_days'] !== '' ? (int)$_POST['planned_duration_days'] : null;
        $plannedStartDate    = trim($_POST['planned_start_date'] ?? '');
        $plannedEndDate      = trim($_POST['planned_end_date'] ?? '');
        $dependencyTaskId   = !empty($_POST['dependency_task_id']) ? (int)$_POST['dependency_task_id'] : null;
        if ($plannedStartDate === '' || strtotime($plannedStartDate) === false) $plannedStartDate = null;
        if ($plannedEndDate === '' || strtotime($plannedEndDate) === false) $plannedEndDate = null;

        if ($projectId <= 0) {
            flashMessage('danger', 'Invalid project.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if ($description === '') $description = 'New task';

        $chk = $pdo->query("SHOW TABLES LIKE 'sow_tasks'");
        if (!$chk || $chk->rowCount() === 0) {
            flashMessage('danger', 'sow_tasks table not found. Run database/migration_sow_tasks.sql.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM sow_tasks WHERE project_id = ? AND COALESCE(budget_item_id, 0) = COALESCE(?, 0) AND COALESCE(parent_id, 0) = COALESCE(?, 0)");
        $maxOrder->execute([$projectId, $budgetItemId, $parentId]);
        $nextOrder = (int)$maxOrder->fetchColumn() + 1;

        $ins = $pdo->prepare("
            INSERT INTO sow_tasks (project_id, budget_item_id, parent_id, code, description, unit, quantity, planned_duration_days, planned_start_date, planned_end_date, dependency_task_id, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $projectId, $budgetItemId, $parentId, $code !== '' ? $code : null, $description,
            $unit !== '' ? $unit : null, $quantity, $plannedDurationDays, $plannedStartDate, $plannedEndDate,
            $dependencyTaskId, $nextOrder,
        ]);
        flashMessage('success', 'Task added to Scope of Work.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'update_sow_task':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $taskId       = (int)($_POST['task_id'] ?? 0);
        $code         = trim($_POST['code'] ?? '');
        $description  = trim($_POST['description'] ?? '');
        $unit         = trim($_POST['unit'] ?? '');
        $quantity     = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
        $plannedDurationDays = isset($_POST['planned_duration_days']) && $_POST['planned_duration_days'] !== '' ? (int)$_POST['planned_duration_days'] : null;
        $plannedStartDate    = trim($_POST['planned_start_date'] ?? '');
        $plannedEndDate      = trim($_POST['planned_end_date'] ?? '');
        $dependencyTaskId   = !empty($_POST['dependency_task_id']) ? (int)$_POST['dependency_task_id'] : null;
        if ($plannedStartDate === '' || strtotime($plannedStartDate) === false) $plannedStartDate = null;
        if ($plannedEndDate === '' || strtotime($plannedEndDate) === false) $plannedEndDate = null;

        if ($projectId <= 0 || $taskId <= 0 || $description === '') {
            flashMessage('danger', 'Invalid input.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $stmt = $pdo->prepare("
            UPDATE sow_tasks SET code = ?, description = ?, unit = ?, quantity = ?, planned_duration_days = ?, planned_start_date = ?, planned_end_date = ?, dependency_task_id = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$code !== '' ? $code : null, $description, $unit !== '' ? $unit : null, $quantity, $plannedDurationDays, $plannedStartDate, $plannedEndDate, $dependencyTaskId, $taskId, $projectId]);
        flashMessage('success', 'Task updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'update_sow_task_dates':
        $projectId    = (int)($_POST['project_id'] ?? 0);
        $taskId       = (int)($_POST['task_id'] ?? 0);
        $plannedDurationDays = isset($_POST['planned_duration_days']) && $_POST['planned_duration_days'] !== '' ? (int)$_POST['planned_duration_days'] : null;
        $plannedStartDate    = trim($_POST['planned_start_date'] ?? '');
        $plannedEndDate      = trim($_POST['planned_end_date'] ?? '');
        if ($plannedStartDate === '' || strtotime($plannedStartDate) === false) $plannedStartDate = null;
        if ($plannedEndDate === '' || strtotime($plannedEndDate) === false) $plannedEndDate = null;

        if ($projectId <= 0 || $taskId <= 0) {
            flashMessage('danger', 'Invalid task.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $stmt = $pdo->prepare("UPDATE sow_tasks SET planned_duration_days = ?, planned_start_date = ?, planned_end_date = ? WHERE id = ? AND project_id = ?");
        $stmt->execute([$plannedDurationDays, $plannedStartDate, $plannedEndDate, $taskId, $projectId]);
        flashMessage('success', 'Planned dates updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'delete_sow_task':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $taskId    = (int)($_POST['task_id'] ?? 0);
        if ($projectId <= 0 || $taskId <= 0) {
            flashMessage('danger', 'Invalid task.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $pdo->prepare("DELETE FROM sow_tasks WHERE id = ? AND project_id = ?")->execute([$taskId, $projectId]);
        flashMessage('success', 'Task deleted.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Planning task resources (materials, equipment, people per task) ───
    // Helper: sync task planned_duration_days from manpower/equipment resources (days/hrs)
    $syncPlanningTaskDurationFromResources = function ($pdo, $taskType, $taskId, $projectId) {
        if (($taskType !== 'sow_task' && $taskType !== 'wbs_item') || empty($taskId)) return;
        $col = $taskType === 'sow_task' ? 'sow_task_id' : 'wbs_item_id';
        $table = $taskType === 'sow_task' ? 'sow_tasks' : 'planning_wbs_items';
        $idCol = $taskType === 'sow_task' ? 'id' : 'id';
        $chk = $pdo->query("SHOW TABLES LIKE 'planning_task_resources'");
        if (!$chk || $chk->rowCount() === 0) return;
        $stmt = $pdo->prepare("
            SELECT ptr.quantity, ptr.unit, d.category as dupa_category, d.unit as dupa_unit
            FROM planning_task_resources ptr
            LEFT JOIN project_dupa_items d ON d.id = ptr.dupa_item_id AND d.project_id = ptr.project_id
            WHERE ptr.project_id = ? AND ptr.task_type = ? AND ptr.{$col} = ?
        ");
        $stmt->execute([$projectId, $taskType, $taskId]);
        $rows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $days = 0;
        foreach ($rows as $r) {
            $cat = $r['dupa_category'] ?? '';
            if ($cat !== 'manpower' && $cat !== 'equipment') continue;
            $qty = (float)($r['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $u = strtolower(trim($r['unit'] ?? $r['dupa_unit'] ?? ''));
            if ($u === 'days' || $u === 'day') $days = max($days, $qty);
            elseif ($u === 'hrs' || $u === 'hours' || $u === 'hr') $days = max($days, (int)ceil($qty / 8));
        }
        $dur = $days > 0 ? (int)max(1, (int)ceil($days)) : 0;
        if ($dur > 0) {
            $chkTable = $pdo->query("SHOW TABLES LIKE '" . $table . "'");
            if ($chkTable && $chkTable->rowCount() > 0) {
                $getStart = $pdo->prepare("SELECT planned_start_date FROM {$table} WHERE id = ? AND project_id = ?");
                $getStart->execute([$taskId, $projectId]);
                $start = $getStart->fetchColumn();
                if ($start) {
                    $end = date('Y-m-d', strtotime($start . ' +' . ((int)$dur - 1) . ' days'));
                    $pdo->prepare("UPDATE {$table} SET planned_duration_days = ?, planned_end_date = ? WHERE id = ? AND project_id = ?")->execute([$dur, $end, $taskId, $projectId]);
                } else {
                    $pdo->prepare("UPDATE {$table} SET planned_duration_days = ? WHERE id = ? AND project_id = ?")->execute([$dur, $taskId, $projectId]);
                }
            }
        }
    };

    case 'add_planning_task_resource':
        $projectId   = (int)($_POST['project_id'] ?? 0);
        $taskType    = trim($_POST['task_type'] ?? '');
        $sowTaskId   = !empty($_POST['sow_task_id']) ? (int)$_POST['sow_task_id'] : null;
        $wbsItemId   = !empty($_POST['wbs_item_id']) ? (int)$_POST['wbs_item_id'] : null;
        $dupaItemId  = !empty($_POST['dupa_item_id']) ? (int)$_POST['dupa_item_id'] : null;
        $resourceDesc = trim($_POST['resource_description'] ?? '');
        $quantity    = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : 1;
        $unit        = trim($_POST['unit'] ?? '');
        $useRule     = in_array($_POST['use_rule'] ?? '', ['start', 'end', 'spread']) ? $_POST['use_rule'] : 'spread';

        if ($projectId <= 0) {
            flashMessage('danger', 'Invalid project.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if ($taskType !== 'sow_task' && $taskType !== 'wbs_item') {
            flashMessage('danger', 'Invalid task type.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if ($taskType === 'sow_task' && !$sowTaskId) {
            flashMessage('danger', 'SOW task is required.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if ($taskType === 'wbs_item' && !$wbsItemId) {
            flashMessage('danger', 'WBS item is required.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if (!$dupaItemId && $resourceDesc === '') {
            flashMessage('danger', 'Select a DUPA resource or enter a description.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        $chk = $pdo->query("SHOW TABLES LIKE 'planning_task_resources'");
        if (!$chk || $chk->rowCount() === 0) {
            flashMessage('danger', 'planning_task_resources table not found. Run database/migration_planning_task_resources.sql.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM planning_task_resources WHERE project_id = ? AND task_type = ? AND COALESCE(sow_task_id, 0) = COALESCE(?, 0) AND COALESCE(wbs_item_id, 0) = COALESCE(?, 0)");
        $maxOrder->execute([$projectId, $taskType, $sowTaskId ?? 0, $wbsItemId ?? 0]);
        $nextOrder = (int)$maxOrder->fetchColumn() + 1;

        $ins = $pdo->prepare("
            INSERT INTO planning_task_resources (project_id, task_type, sow_task_id, wbs_item_id, dupa_item_id, resource_description, quantity, unit, use_rule, sort_order)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $ins->execute([
            $projectId, $taskType, $sowTaskId, $wbsItemId, $dupaItemId ?: null,
            $resourceDesc !== '' ? $resourceDesc : null, $quantity, $unit !== '' ? $unit : null, $useRule, $nextOrder,
        ]);
        $syncPlanningTaskDurationFromResources($pdo, $taskType, $taskType === 'sow_task' ? $sowTaskId : $wbsItemId, $projectId);
        flashMessage('success', 'Resource added to task.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'update_planning_task_resource':
        $projectId   = (int)($_POST['project_id'] ?? 0);
        $resourceId  = (int)($_POST['resource_id'] ?? 0);
        $dupaItemId  = !empty($_POST['dupa_item_id']) ? (int)$_POST['dupa_item_id'] : null;
        $resourceDesc = trim($_POST['resource_description'] ?? '');
        $quantity    = isset($_POST['quantity']) && $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : 1;
        $unit        = trim($_POST['unit'] ?? '');
        $useRule     = in_array($_POST['use_rule'] ?? '', ['start', 'end', 'spread']) ? $_POST['use_rule'] : 'spread';

        if ($projectId <= 0 || $resourceId <= 0) {
            flashMessage('danger', 'Invalid resource.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        if (!$dupaItemId && $resourceDesc === '') {
            flashMessage('danger', 'Select a DUPA resource or enter a description.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $stmt = $pdo->prepare("
            UPDATE planning_task_resources SET dupa_item_id = ?, resource_description = ?, quantity = ?, unit = ?, use_rule = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$dupaItemId, $resourceDesc !== '' ? $resourceDesc : null, $quantity, $unit !== '' ? $unit : null, $useRule, $resourceId, $projectId]);
        $res = $pdo->prepare("SELECT task_type, sow_task_id, wbs_item_id FROM planning_task_resources WHERE id = ? AND project_id = ?");
        $res->execute([$resourceId, $projectId]);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        if ($row) {
            $tt = $row['task_type'];
            $tid = $tt === 'sow_task' ? (int)$row['sow_task_id'] : (int)$row['wbs_item_id'];
            $syncPlanningTaskDurationFromResources($pdo, $tt, $tid, $projectId);
        }
        flashMessage('success', 'Resource updated.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'delete_planning_task_resource':
        $projectId  = (int)($_POST['project_id'] ?? 0);
        $resourceId = (int)($_POST['resource_id'] ?? 0);
        if ($projectId <= 0 || $resourceId <= 0) {
            flashMessage('danger', 'Invalid resource.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }
        $res = $pdo->prepare("SELECT task_type, sow_task_id, wbs_item_id FROM planning_task_resources WHERE id = ? AND project_id = ?");
        $res->execute([$resourceId, $projectId]);
        $row = $res->fetch(PDO::FETCH_ASSOC);
        $pdo->prepare("DELETE FROM planning_task_resources WHERE id = ? AND project_id = ?")->execute([$resourceId, $projectId]);
        if ($row) {
            $tt = $row['task_type'];
            $tid = $tt === 'sow_task' ? (int)$row['sow_task_id'] : (int)$row['wbs_item_id'];
            $syncPlanningTaskDurationFromResources($pdo, $tt, $tid, $projectId);
        }
        flashMessage('success', 'Resource removed.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Planning Activity: add / delete activities ────────────
    case 'add_planning_activity':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $wbsId     = !empty($_POST['wbs_item_id']) ? (int)$_POST['wbs_item_id'] : null;
        $activity  = trim($_POST['activity'] ?? '');
        $unit      = trim($_POST['unit'] ?? '');
        $qty       = $_POST['quantity'] !== '' ? (float)$_POST['quantity'] : null;
        $prod      = $_POST['productivity'] !== '' ? (float)$_POST['productivity'] : null;
        $manpower  = $_POST['manpower'] !== '' ? (float)$_POST['manpower'] : null;
        $duration  = $_POST['duration_days'] !== '' ? (float)$_POST['duration_days'] : null;
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($projectId <= 0 || $activity === '') {
            flashMessage('danger', 'Activity name is required.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        $stmt = $pdo->prepare("
            INSERT INTO planning_activities
                (project_id, wbs_item_id, activity, unit, quantity, productivity, manpower, duration_days, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $wbsId,
            $activity,
            $unit !== '' ? $unit : null,
            $qty,
            $prod,
            $manpower,
            $duration,
            $remarks !== '' ? $remarks : null,
        ]);

        flashMessage('success', 'Activity added.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    case 'delete_planning_activity':
        $projectId = (int)($_POST['project_id'] ?? 0);
        $actId     = (int)($_POST['activity_id'] ?? 0);

        if ($projectId <= 0 || $actId <= 0) {
            flashMessage('danger', 'Invalid activity.');
            redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        }

        $stmt = $pdo->prepare("DELETE FROM planning_activities WHERE id = ? AND project_id = ?");
        $stmt->execute([$actId, $projectId]);

        flashMessage('success', 'Activity deleted.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=' . $planningTab . '');
        break;

    // ── Soft Delete ─────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project deleted.');
        }
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    // ── Archive ─────────────────────────────────────────────
    case 'archive':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project archived.');
        }
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    // ── Unarchive ───────────────────────────────────────────
    case 'unarchive':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project unarchived.');
        }
        redirect(BASE_URL . '/pages/projects/archive.php');
        break;

    // ── Duplicate ───────────────────────────────────────────
    case 'duplicate':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$id]);
            $orig = $stmt->fetch();

            if ($orig) {
                $insert = $pdo->prepare("
                    INSERT INTO projects
                        (title, project_type, visibility, department, project_subtype,
                         category_id, description, priority, status,
                         funding_source, resolution_no, budget_allocated, start_date,
                         target_end_date, fiscal_year, location, contact_person, remarks,
                         notice_to_proceed)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Not Started', ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $orig['title'] . ' (Copy)',
                    $orig['project_type'],
                    $orig['visibility'],
                    $orig['department'],
                    $orig['project_subtype'],
                    $orig['category_id'],
                    $orig['description'],
                    $orig['priority'],
                    $orig['funding_source'],
                    $orig['resolution_no'],
                    $orig['budget_allocated'],
                    $orig['start_date'],
                    $orig['target_end_date'],
                    $orig['fiscal_year'],
                    $orig['location'],
                    $orig['contact_person'],
                    $orig['remarks'],
                    $orig['notice_to_proceed'] ?? null,
                ]);
                $newId = $pdo->lastInsertId();
                flashMessage('success', 'Project duplicated.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $newId);
            }
        }
        flashMessage('danger', 'Project not found.');
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    default:
        redirect(BASE_URL . '/pages/projects/list.php');
}

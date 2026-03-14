<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/calendar/index.php');
}

$action = $_POST['action'] ?? '';
$redirectUrl = $_POST['redirect_url'] ?? (BASE_URL . '/pages/calendar/index.php');

function normalizeDate($value) {
    $value = trim($value ?? '');
    return $value !== '' ? $value : null;
}

function normalizeTime($value) {
    $value = trim($value ?? '');
    return $value !== '' ? $value : null;
}

function normalizeDailyLogWeather($value) {
    $v = trim($value ?? '');
    return $v !== '' ? $v : null;
}

function normalizeDailyLogTemperature($value) {
    if (!isset($value) || $value === '') return null;
    $t = (float) $value;
    if ($t < -50 || $t > 60) return null;
    return $t;
}

function normalizeDailyLogWorkersCount($value) {
    if (!isset($value) || $value === '') return null;
    $n = (int) $value;
    if ($n < 0 || $n > 10000) return null;
    return $n;
}

/**
 * Recalculate actual_quantity and actual_duration_days for given SOW tasks from daily_log_tasks.
 * Call after syncing daily_log_tasks. No-op if table missing or ids empty.
 */
function recalcSowTaskActuals(PDO $pdo, array $sowTaskIds) {
    $sowTaskIds = array_unique(array_filter(array_map('intval', $sowTaskIds)));
    if (empty($sowTaskIds)) return;
    try {
        $chk = $pdo->query("SHOW TABLES LIKE 'daily_log_tasks'");
        if (!$chk || $chk->rowCount() === 0) return;
        $colQ = $pdo->query("SHOW COLUMNS FROM sow_tasks LIKE 'actual_quantity'");
        if (!$colQ || $colQ->rowCount() === 0) return;
        foreach ($sowTaskIds as $tid) {
            if ($tid <= 0) continue;
            $pdo->prepare("
                UPDATE sow_tasks SET
                    actual_quantity = (SELECT COALESCE(SUM(quantity_done), 0) FROM daily_log_tasks WHERE sow_task_id = ?),
                    actual_duration_days = (SELECT COALESCE(SUM(duration_days_done), 0) FROM daily_log_tasks WHERE sow_task_id = ?)
                WHERE id = ?
            ")->execute([$tid, $tid, $tid]);
        }
    } catch (Exception $e) {}
}

switch ($action) {
    case 'create':
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $eventDate = normalizeDate($_POST['event_date'] ?? '');
        $eventEndDate = normalizeDate($_POST['event_end_date'] ?? '');
        $eventTime = normalizeTime($_POST['event_time'] ?? '');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $eventType = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $showOnMain = isset($_POST['show_on_main']) ? 1 : 0;
        $dailyCost = isset($_POST['daily_cost']) && $_POST['daily_cost'] !== '' ? (float)$_POST['daily_cost'] : null;
        $actualAccomplishment = trim($_POST['actual_accomplishment'] ?? '') ?: null;
        $remainingWork = trim($_POST['remaining_work'] ?? '') ?: null;
        $weather = normalizeDailyLogWeather($_POST['weather'] ?? '');
        $temperature = normalizeDailyLogTemperature($_POST['temperature'] ?? null);
        $workersCount = normalizeDailyLogWorkersCount($_POST['workers_count'] ?? null);
        $equipmentUsed = trim($_POST['equipment_used'] ?? '') ?: null;
        $issues = trim($_POST['issues'] ?? '') ?: null;

        if ($projectId <= 0 || $eventDate === null) {
            flashMessage('danger', 'Project and date are required.');
            redirect($redirectUrl);
        }

        $projStmt = $pdo->prepare("SELECT title FROM projects WHERE id = ? AND is_deleted = 0");
        $projStmt->execute([$projectId]);
        $project = $projStmt->fetch();
        if (!$project) {
            flashMessage('danger', 'Invalid project.');
            redirect($redirectUrl);
        }
        $title = $project['title'];

        if ($eventEndDate === null) {
            $eventEndDate = $eventDate;
        }

        $stmt = $pdo->prepare("
            INSERT INTO calendar_events
                (project_id, title, description, event_type, event_date, event_end_date,
                 event_time, is_all_day, is_auto_generated, source_table, source_id, color,
                 created_by, show_on_main, daily_cost, actual_accomplishment, remaining_work,
                 weather, temperature, workers_count, equipment_used, issues)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, NULL, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?)
        ");

        $stmt->execute([
            $projectId,
            $title,
            $description,
            $eventType,
            $eventDate,
            $eventEndDate,
            $eventTime,
            $isAllDay,
            $showOnMain,
            $dailyCost,
            $actualAccomplishment,
            $remainingWork,
            $weather,
            $temperature,
            $workersCount,
            $equipmentUsed,
            $issues,
        ]);

        $newEventId = (int)$pdo->lastInsertId();

        // Optional multi-file attachments linked to this event (project-specific only)
        if ($projectId && !empty($_FILES['attachments']['name'][0])) {
            $projectDir = UPLOAD_DIR . $projectId . '/';
            if (!is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            $desc = trim($_POST['attachment_description'] ?? '');
            $fCount = count($_FILES['attachments']['name']);
            for ($fi = 0; $fi < $fCount; $fi++) {
                if ($_FILES['attachments']['error'][$fi] !== UPLOAD_ERR_OK) continue;
                if (validateUpload($_FILES['attachments']['name'][$fi], $_FILES['attachments']['size'][$fi])) continue;

                $storedName = generateSafeFilename($_FILES['attachments']['name'][$fi], $fi);
                $destPath = $projectDir . $storedName;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$fi], $destPath)) {
                    $attStmt = $pdo->prepare("
                        INSERT INTO attachments
                            (project_id, module_type, module_record_id, file_name, file_original_name, file_type, file_size, description)
                        VALUES (?, 'daily_log', ?, ?, ?, ?, ?, ?)
                    ");
                    $attStmt->execute([
                        $projectId, $newEventId, $storedName, $_FILES['attachments']['name'][$fi],
                        $_FILES['attachments']['type'][$fi], $_FILES['attachments']['size'][$fi], $desc,
                    ]);
                }
            }
        }

        // Multiple Scope of Work lines → one action_log per line (record of accomplishment with date)
        $sowBudgetIds = isset($_POST['sow_budget_id']) ? (array)$_POST['sow_budget_id'] : [];
        $sowQuantities = isset($_POST['sow_quantity']) ? (array)$_POST['sow_quantity'] : [];
        $logDate = $eventDate . ' ' . ($isAllDay ? '00:00:00' : ($eventTime ?: '00:00:00'));
        if (strlen($logDate) === 10) $logDate .= ' 00:00:00';

        foreach ($sowBudgetIds as $idx => $bid) {
            $budgetItemId = !empty($bid) ? (int)$bid : null;
            if ($budgetItemId <= 0 || $newEventId <= 0) continue;
            $sowQty = isset($sowQuantities[$idx]) && $sowQuantities[$idx] !== '' ? (float)$sowQuantities[$idx] : null;
            if ($sowQty === null || $sowQty < 0) continue;

            $biStmt = $pdo->prepare("SELECT id, project_id, item_name, quantity, unit, unit_cost FROM budget_items WHERE id = ? AND project_id = ?");
            $biStmt->execute([$budgetItemId, $projectId]);
            $bi = $biStmt->fetch();
            if (!$bi) continue;

            $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM action_logs WHERE budget_item_id = ? AND is_scope_of_work = 1");
            $usedStmt->execute([$budgetItemId]);
            $used = (float)$usedStmt->fetchColumn();
            $remaining = (float)$bi['quantity'] - $used;
            if ($sowQty > $remaining) {
                flashMessage('danger', 'Quantity for "' . $bi['item_name'] . '" cannot exceed remaining (' . number_format($remaining, 2) . ').');
                redirect($redirectUrl);
            }

            $amount = $sowQty * (float)$bi['unit_cost'];
            $actionTaken = $description !== '' ? $description : $bi['item_name'];
            $manpower = isset($_POST['manpower']) && $_POST['manpower'] !== '' ? (float)$_POST['manpower'] : null;
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO action_logs
                        (project_id, action_taken, notes, logged_by, log_date, is_scope_of_work, budget_item_id, calendar_event_id, quantity, unit, unit_cost, amount, quantity_delivered, manpower)
                    VALUES (?, ?, '', NULL, ?, 1, ?, ?, ?, ?, ?, ?, NULL, ?)
                ");
                $logStmt->execute([
                    $projectId,
                    $actionTaken,
                    $logDate,
                    $budgetItemId,
                    $newEventId,
                    $sowQty,
                    $bi['unit'],
                    (float)$bi['unit_cost'],
                    $amount,
                    $manpower,
                ]);
            } catch (PDOException $e) {
                $logStmt = $pdo->prepare("
                    INSERT INTO action_logs
                        (project_id, action_taken, notes, logged_by, log_date, is_scope_of_work, budget_item_id, calendar_event_id, quantity, unit, unit_cost, amount, quantity_delivered)
                    VALUES (?, ?, '', NULL, ?, 1, ?, ?, ?, ?, ?, ?, NULL)
                ");
                $logStmt->execute([
                    $projectId,
                    $actionTaken,
                    $logDate,
                    $budgetItemId,
                    $newEventId,
                    $sowQty,
                    $bi['unit'],
                    (float)$bi['unit_cost'],
                    $amount,
                ]);
            }
        }

        // Event materials (DUPA): requested / delivered / notes → project_material_withdrawals
        $matDupaIds = isset($_POST['material_dupa_id']) ? (array)$_POST['material_dupa_id'] : [];
        $matRequested = isset($_POST['material_requested']) ? (array)$_POST['material_requested'] : [];
        $matDelivered = isset($_POST['material_delivered']) ? (array)$_POST['material_delivered'] : [];
        $matNotes = isset($_POST['material_notes']) ? (array)$_POST['material_notes'] : [];
        try {
            $colEv = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'calendar_event_id'");
            $colReq = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
            $colWithdrawalBi = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'budget_item_id'");
            $colDupaBi = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
            $hasWithdrawalBudgetId = $colWithdrawalBi && $colWithdrawalBi->rowCount() > 0;
            $hasDupaBudgetId = $colDupaBi && $colDupaBi->rowCount() > 0;
            if ($colEv && $colEv->rowCount() > 0 && $colReq && $colReq->rowCount() > 0 && $projectId && $newEventId && $eventDate) {
                $pdo->prepare("DELETE FROM project_material_withdrawals WHERE calendar_event_id = ?")->execute([$newEventId]);
                for ($mi = 0; $mi < count($matDupaIds); $mi++) {
                    $dupaId = !empty($matDupaIds[$mi]) ? (int)$matDupaIds[$mi] : 0;
                    if ($dupaId <= 0) continue;
                    $req = isset($matRequested[$mi]) && $matRequested[$mi] !== '' ? max(0, (float)$matRequested[$mi]) : null;
                    $del = isset($matDelivered[$mi]) && $matDelivered[$mi] !== '' ? max(0, (float)$matDelivered[$mi]) : 0;
                    $note = isset($matNotes[$mi]) ? trim($matNotes[$mi]) : '';
                    if ($req <= 0 && $del <= 0) continue;
                    $dupSql = "SELECT id" . ($hasDupaBudgetId ? ", budget_item_id" : "") . " FROM project_dupa_items WHERE id = ? AND project_id = ? AND category = 'materials'";
                    $dupCheck = $pdo->prepare($dupSql);
                    $dupCheck->execute([$dupaId, $projectId]);
                    $dupaRow = $dupCheck->fetch(PDO::FETCH_ASSOC);
                    if (!$dupaRow) continue;
                    $budgetItemId = ($hasDupaBudgetId && isset($dupaRow['budget_item_id'])) ? $dupaRow['budget_item_id'] : null;
                    if ($hasWithdrawalBudgetId) {
                        $ins = $pdo->prepare("
                            INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity_requested, quantity, remarks, calendar_event_id, budget_item_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([$projectId, $dupaId, $eventDate, $req, $del, $note !== '' ? $note : null, $newEventId, $budgetItemId]);
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity_requested, quantity, remarks, calendar_event_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([$projectId, $dupaId, $eventDate, $req, $del, $note !== '' ? $note : null, $newEventId]);
                    }
                }
            }
        } catch (Exception $e) {}

        // Daily log ↔ SOW tasks: link tasks worked and update progress
        $taskSowIds = isset($_POST['task_sow_id']) ? (array)$_POST['task_sow_id'] : [];
        $taskQuantities = isset($_POST['task_quantity']) ? (array)$_POST['task_quantity'] : [];
        $taskDurations = isset($_POST['task_duration']) ? (array)$_POST['task_duration'] : [];
        $affectedTaskIds = [];
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'daily_log_tasks'");
            if ($chk && $chk->rowCount() > 0) {
                $insDlt = $pdo->prepare("INSERT INTO daily_log_tasks (calendar_event_id, sow_task_id, quantity_done, duration_days_done) VALUES (?, ?, ?, ?)");
                for ($ti = 0; $ti < count($taskSowIds); $ti++) {
                    $sowTaskId = !empty($taskSowIds[$ti]) ? (int)$taskSowIds[$ti] : 0;
                    if ($sowTaskId <= 0 || $newEventId <= 0) continue;
                    $verify = $pdo->prepare("SELECT id FROM sow_tasks WHERE id = ? AND project_id = ?");
                    $verify->execute([$sowTaskId, $projectId]);
                    if (!$verify->fetch()) continue;
                    $qty = isset($taskQuantities[$ti]) && $taskQuantities[$ti] !== '' ? (float)$taskQuantities[$ti] : null;
                    $dur = isset($taskDurations[$ti]) && $taskDurations[$ti] !== '' ? (float)$taskDurations[$ti] : null;
                    if (($qty === null || $qty < 0) && ($dur === null || $dur < 0)) continue;
                    $insDlt->execute([$newEventId, $sowTaskId, $qty, $dur]);
                    $affectedTaskIds[] = $sowTaskId;
                }
                recalcSowTaskActuals($pdo, $affectedTaskIds);
            }
        } catch (Exception $e) {}

        recalcProjectActualCost($pdo, $projectId);
        flashMessage('success', 'Calendar event created.');
        redirect($redirectUrl);

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $eventDate = normalizeDate($_POST['event_date'] ?? '');
        $eventEndDate = normalizeDate($_POST['event_end_date'] ?? '');
        $eventTime = normalizeTime($_POST['event_time'] ?? '');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $eventType = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $showOnMain = isset($_POST['show_on_main']) ? 1 : 0;
        $dailyCost = isset($_POST['daily_cost']) && $_POST['daily_cost'] !== '' ? (float)$_POST['daily_cost'] : null;
        $actualAccomplishment = trim($_POST['actual_accomplishment'] ?? '') ?: null;
        $remainingWork = trim($_POST['remaining_work'] ?? '') ?: null;
        $weather = normalizeDailyLogWeather($_POST['weather'] ?? '');
        $temperature = normalizeDailyLogTemperature($_POST['temperature'] ?? null);
        $workersCount = normalizeDailyLogWorkersCount($_POST['workers_count'] ?? null);
        $equipmentUsed = trim($_POST['equipment_used'] ?? '') ?: null;
        $issues = trim($_POST['issues'] ?? '') ?: null;

        if ($id <= 0 || $projectId <= 0 || $eventDate === null) {
            flashMessage('danger', 'Invalid event data. Project and date are required.');
            redirect($redirectUrl);
        }

        $projStmt = $pdo->prepare("SELECT title FROM projects WHERE id = ? AND is_deleted = 0");
        $projStmt->execute([$projectId]);
        $project = $projStmt->fetch();
        if (!$project) {
            flashMessage('danger', 'Invalid project.');
            redirect($redirectUrl);
        }
        $title = $project['title'];

        if ($eventEndDate === null) {
            $eventEndDate = $eventDate;
        }

        $stmt = $pdo->prepare("
            UPDATE calendar_events SET
                project_id = ?,
                title = ?,
                description = ?,
                event_type = ?,
                event_date = ?,
                event_end_date = ?,
                event_time = ?,
                is_all_day = ?,
                show_on_main = ?,
                daily_cost = ?,
                actual_accomplishment = ?,
                remaining_work = ?,
                weather = ?,
                temperature = ?,
                workers_count = ?,
                equipment_used = ?,
                issues = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $projectId,
            $title,
            $description,
            $eventType,
            $eventDate,
            $eventEndDate,
            $eventTime,
            $isAllDay,
            $showOnMain,
            $dailyCost,
            $actualAccomplishment,
            $remainingWork,
            $weather,
            $temperature,
            $workersCount,
            $equipmentUsed,
            $issues,
            $id,
        ]);

        // Optional multi-file attachments when updating (does not remove existing ones)
        if ($projectId && !empty($_FILES['attachments']['name'][0])) {
            $projectDir = UPLOAD_DIR . $projectId . '/';
            if (!is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            $desc = trim($_POST['attachment_description'] ?? '');
            $fCount = count($_FILES['attachments']['name']);
            for ($fi = 0; $fi < $fCount; $fi++) {
                if ($_FILES['attachments']['error'][$fi] !== UPLOAD_ERR_OK) continue;
                if (validateUpload($_FILES['attachments']['name'][$fi], $_FILES['attachments']['size'][$fi])) continue;

                $storedName = generateSafeFilename($_FILES['attachments']['name'][$fi], $fi);
                $destPath = $projectDir . $storedName;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$fi], $destPath)) {
                    $attStmt = $pdo->prepare("
                        INSERT INTO attachments
                            (project_id, module_type, module_record_id, file_name, file_original_name, file_type, file_size, description)
                        VALUES (?, 'daily_log', ?, ?, ?, ?, ?, ?)
                    ");
                    $attStmt->execute([
                        $projectId, $id, $storedName, $_FILES['attachments']['name'][$fi],
                        $_FILES['attachments']['type'][$fi], $_FILES['attachments']['size'][$fi], $desc,
                    ]);
                }
            }
        }

        // Delete existing SOW action_logs for this event, then insert one per submitted line
        $delSow = $pdo->prepare("DELETE FROM action_logs WHERE calendar_event_id = ? AND is_scope_of_work = 1");
        $delSow->execute([$id]);

        $sowBudgetIds = isset($_POST['sow_budget_id']) ? (array)$_POST['sow_budget_id'] : [];
        $sowQuantities = isset($_POST['sow_quantity']) ? (array)$_POST['sow_quantity'] : [];
        $logDate = $eventDate . ' ' . ($isAllDay ? '00:00:00' : ($eventTime ?: '00:00:00'));
        if (strlen($logDate) === 10) $logDate .= ' 00:00:00';

        foreach ($sowBudgetIds as $idx => $bid) {
            $budgetItemId = !empty($bid) ? (int)$bid : null;
            if ($budgetItemId <= 0) continue;
            $sowQty = isset($sowQuantities[$idx]) && $sowQuantities[$idx] !== '' ? (float)$sowQuantities[$idx] : null;
            if ($sowQty === null || $sowQty < 0) continue;

            $biStmt = $pdo->prepare("SELECT id, project_id, item_name, quantity, unit, unit_cost FROM budget_items WHERE id = ? AND project_id = ?");
            $biStmt->execute([$budgetItemId, $projectId]);
            $bi = $biStmt->fetch();
            if (!$bi) continue;

            $usedStmt = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM action_logs WHERE budget_item_id = ? AND is_scope_of_work = 1 AND (calendar_event_id IS NULL OR calendar_event_id != ?)");
            $usedStmt->execute([$budgetItemId, $id]);
            $used = (float)$usedStmt->fetchColumn();
            $remaining = (float)$bi['quantity'] - $used;
            if ($sowQty > $remaining) {
                flashMessage('danger', 'Quantity for "' . $bi['item_name'] . '" cannot exceed remaining (' . number_format($remaining, 2) . ').');
                redirect($redirectUrl);
            }

            $amount = $sowQty * (float)$bi['unit_cost'];
            $actionTaken = $description !== '' ? $description : $bi['item_name'];
            $manpower = isset($_POST['manpower']) && $_POST['manpower'] !== '' ? (float)$_POST['manpower'] : null;
            try {
                $logStmt = $pdo->prepare("
                    INSERT INTO action_logs
                        (project_id, action_taken, notes, logged_by, log_date, is_scope_of_work, budget_item_id, calendar_event_id, quantity, unit, unit_cost, amount, quantity_delivered, manpower)
                    VALUES (?, ?, '', NULL, ?, 1, ?, ?, ?, ?, ?, ?, NULL, ?)
                ");
                $logStmt->execute([
                    $projectId,
                    $actionTaken,
                    $logDate,
                    $budgetItemId,
                    $id,
                    $sowQty,
                    $bi['unit'],
                    (float)$bi['unit_cost'],
                    $amount,
                    $manpower,
                ]);
            } catch (PDOException $e) {
                $logStmt = $pdo->prepare("
                    INSERT INTO action_logs
                        (project_id, action_taken, notes, logged_by, log_date, is_scope_of_work, budget_item_id, calendar_event_id, quantity, unit, unit_cost, amount, quantity_delivered)
                    VALUES (?, ?, '', NULL, ?, 1, ?, ?, ?, ?, ?, ?, NULL)
                ");
                $logStmt->execute([
                    $projectId,
                    $actionTaken,
                    $logDate,
                    $budgetItemId,
                    $id,
                    $sowQty,
                    $bi['unit'],
                    (float)$bi['unit_cost'],
                    $amount,
                ]);
            }
        }

        // Action log: Daily report updated (when at least one SOW line is provided)
        if (!empty($sowBudgetIds)) {
            logProjectActivity(
                $pdo,
                $projectId,
                'Calendar',
                'Daily report updated',
                'System',
                $logDate
            );
        }

        // Event materials (DUPA): sync requested / delivered / notes
        $matDupaIds = isset($_POST['material_dupa_id']) ? (array)$_POST['material_dupa_id'] : [];
        $matRequested = isset($_POST['material_requested']) ? (array)$_POST['material_requested'] : [];
        $matDelivered = isset($_POST['material_delivered']) ? (array)$_POST['material_delivered'] : [];
        $matNotes = isset($_POST['material_notes']) ? (array)$_POST['material_notes'] : [];
        try {
            $colEv = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'calendar_event_id'");
            $colReq = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
            $colWithdrawalBi = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'budget_item_id'");
            $colDupaBi = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
            $hasWithdrawalBudgetId = $colWithdrawalBi && $colWithdrawalBi->rowCount() > 0;
            $hasDupaBudgetId = $colDupaBi && $colDupaBi->rowCount() > 0;
            if ($colEv && $colEv->rowCount() > 0 && $colReq && $colReq->rowCount() > 0 && $projectId && $id && $eventDate) {
                $pdo->prepare("DELETE FROM project_material_withdrawals WHERE calendar_event_id = ?")->execute([$id]);
                for ($mi = 0; $mi < count($matDupaIds); $mi++) {
                    $dupaId = !empty($matDupaIds[$mi]) ? (int)$matDupaIds[$mi] : 0;
                    if ($dupaId <= 0) continue;
                    $req = isset($matRequested[$mi]) && $matRequested[$mi] !== '' ? max(0, (float)$matRequested[$mi]) : null;
                    $del = isset($matDelivered[$mi]) && $matDelivered[$mi] !== '' ? max(0, (float)$matDelivered[$mi]) : 0;
                    $note = isset($matNotes[$mi]) ? trim($matNotes[$mi]) : '';
                    if ($req <= 0 && $del <= 0) continue;
                    $dupSql = "SELECT id" . ($hasDupaBudgetId ? ", budget_item_id" : "") . " FROM project_dupa_items WHERE id = ? AND project_id = ? AND category = 'materials'";
                    $dupCheck = $pdo->prepare($dupSql);
                    $dupCheck->execute([$dupaId, $projectId]);
                    $dupaRow = $dupCheck->fetch(PDO::FETCH_ASSOC);
                    if (!$dupaRow) continue;
                    $budgetItemId = ($hasDupaBudgetId && isset($dupaRow['budget_item_id'])) ? $dupaRow['budget_item_id'] : null;
                    if ($hasWithdrawalBudgetId) {
                        $ins = $pdo->prepare("
                            INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity_requested, quantity, remarks, calendar_event_id, budget_item_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([$projectId, $dupaId, $eventDate, $req, $del, $note !== '' ? $note : null, $id, $budgetItemId]);
                    } else {
                        $ins = $pdo->prepare("
                            INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity_requested, quantity, remarks, calendar_event_id)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $ins->execute([$projectId, $dupaId, $eventDate, $req, $del, $note !== '' ? $note : null, $id]);
                    }
                }
            }
        } catch (Exception $e) {}

        // Daily log ↔ SOW tasks: replace links and update progress
        $affectedTaskIds = [];
        try {
            $chk = $pdo->query("SHOW TABLES LIKE 'daily_log_tasks'");
            if ($chk && $chk->rowCount() > 0) {
                $getOld = $pdo->prepare("SELECT sow_task_id FROM daily_log_tasks WHERE calendar_event_id = ?");
                $getOld->execute([$id]);
                while ($r = $getOld->fetch(PDO::FETCH_NUM)) $affectedTaskIds[] = (int)$r[0];
                $pdo->prepare("DELETE FROM daily_log_tasks WHERE calendar_event_id = ?")->execute([$id]);
                $taskSowIds = isset($_POST['task_sow_id']) ? (array)$_POST['task_sow_id'] : [];
                $taskQuantities = isset($_POST['task_quantity']) ? (array)$_POST['task_quantity'] : [];
                $taskDurations = isset($_POST['task_duration']) ? (array)$_POST['task_duration'] : [];
                $insDlt = $pdo->prepare("INSERT INTO daily_log_tasks (calendar_event_id, sow_task_id, quantity_done, duration_days_done) VALUES (?, ?, ?, ?)");
                for ($ti = 0; $ti < count($taskSowIds); $ti++) {
                    $sowTaskId = !empty($taskSowIds[$ti]) ? (int)$taskSowIds[$ti] : 0;
                    if ($sowTaskId <= 0 || $id <= 0) continue;
                    $verify = $pdo->prepare("SELECT id FROM sow_tasks WHERE id = ? AND project_id = ?");
                    $verify->execute([$sowTaskId, $projectId]);
                    if (!$verify->fetch()) continue;
                    $qty = isset($taskQuantities[$ti]) && $taskQuantities[$ti] !== '' ? (float)$taskQuantities[$ti] : null;
                    $dur = isset($taskDurations[$ti]) && $taskDurations[$ti] !== '' ? (float)$taskDurations[$ti] : null;
                    if (($qty === null || $qty < 0) && ($dur === null || $dur < 0)) continue;
                    $insDlt->execute([$id, $sowTaskId, $qty, $dur]);
                    $affectedTaskIds[] = $sowTaskId;
                }
                recalcSowTaskActuals($pdo, $affectedTaskIds);
            }
        } catch (Exception $e) {}

        recalcProjectActualCost($pdo, $projectId);
        flashMessage('success', 'Calendar event updated.');
        redirect($redirectUrl);

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $getProj = $pdo->prepare("SELECT project_id FROM calendar_events WHERE id = ?");
            $getProj->execute([$id]);
            $row = $getProj->fetch();
            $pdo->prepare("DELETE FROM action_logs WHERE calendar_event_id = ? AND is_scope_of_work = 1")->execute([$id]);
            try {
                $pdo->prepare("DELETE FROM project_material_withdrawals WHERE calendar_event_id = ?")->execute([$id]);
            } catch (Exception $e) {}
            try {
                $pdo->prepare("DELETE FROM attachments WHERE module_type = 'daily_log' AND module_record_id = ?")->execute([$id]);
            } catch (Exception $e) {}
            $affectedTaskIds = [];
            try {
                $chk = $pdo->query("SHOW TABLES LIKE 'daily_log_tasks'");
                if ($chk && $chk->rowCount() > 0) {
                    $getTasks = $pdo->prepare("SELECT sow_task_id FROM daily_log_tasks WHERE calendar_event_id = ?");
                    $getTasks->execute([$id]);
                    while ($t = $getTasks->fetch(PDO::FETCH_NUM)) $affectedTaskIds[] = (int)$t[0];
                }
            } catch (Exception $e) {}
            $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ?");
            $stmt->execute([$id]);
            recalcSowTaskActuals($pdo, $affectedTaskIds);
            if ($row && !empty($row['project_id'])) {
                recalcProjectActualCost($pdo, (int)$row['project_id']);
            }
            flashMessage('success', 'Calendar event deleted.');
        }
        redirect($redirectUrl);

    default:
        redirect($redirectUrl);
}

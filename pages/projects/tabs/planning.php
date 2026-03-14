<?php
// Tab: Planning — Scope of Work (read-only) + work breakdown with planned duration/start/end.
// SOW rows use project_timeline_tasks; WBS rows use planning_wbs_items with planned_duration_days, planned_start_date, planned_end_date.

// Ensure timeline table exists and sync SOW to timeline tasks (same as Timeline tab)
$tableExists = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_timeline_tasks'");
    $tableExists = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    $tableExists = false;
}
if (!$tableExists) {
    try {
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
    } catch (Exception $e) { }
    $chk = $pdo->query("SHOW TABLES LIKE 'project_timeline_tasks'");
    $tableExists = $chk && $chk->rowCount() > 0;
}

$sowItems = [];
$taskByBudgetId = [];  // budget_item_id => timeline task row
$timelineTasks = [];
$wbsItems = [];
$wbsByBudgetItem = []; // budget_item_id => [ top-level WBS items ]
$wbsByParent = [];     // parent_id => [ child WBS items ]

try {
    $budgetStmt = $pdo->prepare("SELECT * FROM budget_items WHERE project_id = ? ORDER BY id");
    $budgetStmt->execute([$id]);
    $sowItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $sowItems = [];
}

if ($tableExists) {
    $existingBudgetIds = [];
    $stmt = $pdo->prepare("SELECT budget_item_id FROM project_timeline_tasks WHERE project_id = ? AND budget_item_id IS NOT NULL");
    $stmt->execute([$id]);
    foreach ($stmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        $existingBudgetIds[(int)$row['budget_item_id']] = true;
    }
    $defaultStart = ($project['notice_to_proceed'] ?? $project['start_date']) ?: date('Y-m-d');
    $defaultEnd = $project['target_end_date'] ?: date('Y-m-d', strtotime('+30 days'));
    $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM project_timeline_tasks WHERE project_id = ?");
    $maxOrder->execute([$id]);
    $nextOrder = (int)$maxOrder->fetchColumn() + 1;
    $ins = $pdo->prepare("
        INSERT INTO project_timeline_tasks (project_id, budget_item_id, task_label, planned_start_date, planned_end_date, sort_order)
        VALUES (?, ?, ?, ?, ?, ?)
    ");
    foreach ($sowItems as $bi) {
        if (isset($existingBudgetIds[(int)$bi['id']])) continue;
        $ins->execute([$id, $bi['id'], $bi['item_name'], $defaultStart, $defaultEnd, $nextOrder++]);
    }
    $updLabel = $pdo->prepare("UPDATE project_timeline_tasks t INNER JOIN budget_items b ON b.id = t.budget_item_id AND b.project_id = t.project_id SET t.task_label = b.item_name WHERE t.project_id = ?");
    $updLabel->execute([$id]);

    $stmt = $pdo->prepare("
        SELECT t.id, t.project_id, t.budget_item_id, t.task_label, t.planned_start_date, t.planned_end_date, t.sort_order
        FROM project_timeline_tasks t
        LEFT JOIN budget_items b ON b.id = t.budget_item_id AND b.project_id = t.project_id
        WHERE t.project_id = ?
        ORDER BY t.sort_order, t.id
    ");
    $stmt->execute([$id]);
    $timelineTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    foreach ($timelineTasks as $t) {
        if (!empty($t['budget_item_id'])) {
            $taskByBudgetId[(int)$t['budget_item_id']] = $t;
        }
    }
}

$hasWbsTable = false;
$orphanWbsItems = [];
$wbsHasBudgetItemId = false;
try {
    $chkWbs = $pdo->query("SHOW TABLES LIKE 'planning_wbs_items'");
    $hasWbsTable = $chkWbs && $chkWbs->rowCount() > 0;
} catch (Exception $e) { }
if ($hasWbsTable) {
    try {
        $wbsStmt = $pdo->prepare("SELECT * FROM planning_wbs_items WHERE project_id = ? ORDER BY sort_order, id");
        $wbsStmt->execute([$id]);
        $wbsItems = $wbsStmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($wbsItems)) {
            $wbsHasBudgetItemId = array_key_exists('budget_item_id', $wbsItems[0]);
        }
    } catch (Exception $e) { }
    $orphanWbsItems = [];
    foreach ($wbsItems as $w) {
        $pid = isset($w['parent_id']) && $w['parent_id'] !== null ? (int)$w['parent_id'] : null;
        $bid = isset($w['budget_item_id']) && $w['budget_item_id'] !== null && $w['budget_item_id'] !== '' ? (int)$w['budget_item_id'] : null;
        if ($pid !== null) {
            if (!isset($wbsByParent[$pid])) $wbsByParent[$pid] = [];
            $wbsByParent[$pid][] = $w;
        }
        if ($bid !== null && $pid === null) {
            if (!isset($wbsByBudgetItem[$bid])) $wbsByBudgetItem[$bid] = [];
            $wbsByBudgetItem[$bid][] = $w;
        }
        if ($pid === null && $bid === null) {
            $orphanWbsItems[] = $w;
        }
    }
}

// Scope of Work Tasks (sow_tasks): master for scheduling when present
$useSowTasks = false;
$sowTaskItems = [];
$sowTasksByBudgetItem = []; // budget_item_id => [ root tasks ]
$sowTasksByParent = [];     // parent_id => [ child tasks ]
$sowTaskComputedDuration = []; // task_id => total duration (self or sum of children)
$sowTaskRangeByBudget = []; // budget_item_id => [ total_days, min_start, max_end ]
try {
    $chkSow = $pdo->query("SHOW TABLES LIKE 'sow_tasks'");
    if ($chkSow && $chkSow->rowCount() > 0) {
        $stmt = $pdo->prepare("SELECT * FROM sow_tasks WHERE project_id = ? ORDER BY budget_item_id, parent_id, sort_order, id");
        $stmt->execute([$id]);
        $sowTaskItems = $stmt->fetchAll(PDO::FETCH_ASSOC);
        if (!empty($sowTaskItems)) {
            $useSowTasks = true;
            foreach ($sowTaskItems as $t) {
                $pid = isset($t['parent_id']) && $t['parent_id'] !== null && $t['parent_id'] !== '' ? (int)$t['parent_id'] : null;
                $bid = isset($t['budget_item_id']) && $t['budget_item_id'] !== null && $t['budget_item_id'] !== '' ? (int)$t['budget_item_id'] : null;
                if ($pid !== null) {
                    if (!isset($sowTasksByParent[$pid])) $sowTasksByParent[$pid] = [];
                    $sowTasksByParent[$pid][] = $t;
                } else if ($bid !== null) {
                    if (!isset($sowTasksByBudgetItem[$bid])) $sowTasksByBudgetItem[$bid] = [];
                    $sowTasksByBudgetItem[$bid][] = $t;
                }
            }
            // Compute parent duration = sum of children (recursive)
            $sumChildrenDuration = function ($taskId) use (&$sumChildrenDuration, $sowTasksByParent) {
                $children = $sowTasksByParent[$taskId] ?? [];
                $total = 0;
                foreach ($children as $c) {
                    $d = isset($c['planned_duration_days']) && $c['planned_duration_days'] !== null ? (int)$c['planned_duration_days'] : 0;
                    $cid = (int)$c['id'];
                    $childSum = $sumChildrenDuration($cid);
                    $total += ($childSum > 0 ? $childSum : $d);
                }
                return $total;
            };
            foreach ($sowTaskItems as $t) {
                $tid = (int)$t['id'];
                $childTotal = $sumChildrenDuration($tid);
                if ($childTotal > 0) {
                    $sowTaskComputedDuration[$tid] = $childTotal;
                } else {
                    $d = isset($t['planned_duration_days']) && $t['planned_duration_days'] !== null ? (int)$t['planned_duration_days'] : 0;
                    if ($d > 0) $sowTaskComputedDuration[$tid] = $d;
                }
            }
            // Per-SOW range (min start, max end, total days) from all tasks under that budget_item
            $sowTaskRangeByBudget = [];
            foreach ($sowTaskItems as $t) {
                $bid = isset($t['budget_item_id']) && $t['budget_item_id'] !== null ? (int)$t['budget_item_id'] : null;
                if ($bid === null) continue;
                if (!isset($sowTaskRangeByBudget[$bid])) $sowTaskRangeByBudget[$bid] = ['total_days' => 0, 'min_start' => null, 'max_end' => null];
                $d = $sowTaskComputedDuration[(int)$t['id']] ?? (int)($t['planned_duration_days'] ?? 0);
                if ($d > 0) $sowTaskRangeByBudget[$bid]['total_days'] += $d;
                if (!empty($t['planned_start_date'])) {
                    $s = date('Y-m-d', strtotime($t['planned_start_date']));
                    $sowTaskRangeByBudget[$bid]['min_start'] = $sowTaskRangeByBudget[$bid]['min_start'] === null || $s < $sowTaskRangeByBudget[$bid]['min_start'] ? $s : $sowTaskRangeByBudget[$bid]['min_start'];
                }
                if (!empty($t['planned_end_date'])) {
                    $e = date('Y-m-d', strtotime($t['planned_end_date']));
                    $sowTaskRangeByBudget[$bid]['max_end'] = $sowTaskRangeByBudget[$bid]['max_end'] === null || $e > $sowTaskRangeByBudget[$bid]['max_end'] ? $e : $sowTaskRangeByBudget[$bid]['max_end'];
                }
            }
        }
    }
} catch (Exception $e) {
    $useSowTasks = false;
}

// Planning task resources (materials, equipment, people per task) and DUPA catalog
$hasPlanningTaskResourcesTable = false;
$planningTaskResourcesBySowTask = []; // sow_task_id => [ rows ]
$planningTaskResourcesByWbsItem = []; // wbs_item_id => [ rows ]
$planningDurationFromResourcesBySowTask = []; // sow_task_id => days (from manpower/equipment)
$planningDurationFromResourcesByWbsItem = []; // wbs_item_id => days (from manpower/equipment)
$dupaItemsForPlanning = []; // category => [ rows ] for dropdown
try {
    $chkPtr = $pdo->query("SHOW TABLES LIKE 'planning_task_resources'");
    $hasPlanningTaskResourcesTable = $chkPtr && $chkPtr->rowCount() > 0;
} catch (Exception $e) {}
if ($hasPlanningTaskResourcesTable) {
    $ptrStmt = $pdo->prepare("
        SELECT ptr.*, d.category as dupa_category, d.description as dupa_description, d.unit as dupa_unit
        FROM planning_task_resources ptr
        LEFT JOIN project_dupa_items d ON d.id = ptr.dupa_item_id AND d.project_id = ptr.project_id
        WHERE ptr.project_id = ?
        ORDER BY ptr.task_type, ptr.sow_task_id, ptr.wbs_item_id, ptr.sort_order, ptr.id
    ");
    $ptrStmt->execute([$id]);
    foreach ($ptrStmt->fetchAll(PDO::FETCH_ASSOC) as $row) {
        if ($row['task_type'] === 'sow_task' && !empty($row['sow_task_id'])) {
            $tid = (int)$row['sow_task_id'];
            if (!isset($planningTaskResourcesBySowTask[$tid])) $planningTaskResourcesBySowTask[$tid] = [];
            $planningTaskResourcesBySowTask[$tid][] = $row;
        }
        if ($row['task_type'] === 'wbs_item' && !empty($row['wbs_item_id'])) {
            $wid = (int)$row['wbs_item_id'];
            if (!isset($planningTaskResourcesByWbsItem[$wid])) $planningTaskResourcesByWbsItem[$wid] = [];
            $planningTaskResourcesByWbsItem[$wid][] = $row;
        }
    }
    // Duration from manpower + equipment (days/hrs) – activity duration auto-derived from resources
    $durationFromResources = function (array $resources) {
        $days = 0;
        $cat = ['manpower' => true, 'equipment' => true];
        foreach ($resources as $r) {
            if (empty($cat[$r['dupa_category'] ?? ''])) continue;
            $qty = (float)($r['quantity'] ?? 0);
            if ($qty <= 0) continue;
            $u = strtolower(trim($r['unit'] ?? $r['dupa_unit'] ?? ''));
            if ($u === 'days' || $u === 'day') $days = max($days, $qty);
            elseif ($u === 'hrs' || $u === 'hours' || $u === 'hr') $days = max($days, (int)ceil($qty / 8));
        }
        return $days > 0 ? (int)max(1, (int)ceil($days)) : 0;
    };
    foreach ($planningTaskResourcesBySowTask as $tid => $list) {
        $d = $durationFromResources($list);
        if ($d > 0) $planningDurationFromResourcesBySowTask[(int)$tid] = $d;
    }
    foreach ($planningTaskResourcesByWbsItem as $wid => $list) {
        $d = $durationFromResources($list);
        if ($d > 0) $planningDurationFromResourcesByWbsItem[(int)$wid] = $d;
    }
}
try {
    $chkDupa = $pdo->query("SHOW TABLES LIKE 'project_dupa_items'");
    if ($chkDupa && $chkDupa->rowCount() > 0) {
        $dupaStmt = $pdo->prepare("SELECT * FROM project_dupa_items WHERE project_id = ? ORDER BY category, sort_order, id");
        $dupaStmt->execute([$id]);
        foreach ($dupaStmt->fetchAll(PDO::FETCH_ASSOC) as $d) {
            $cat = $d['category'];
            if (!isset($dupaItemsForPlanning[$cat])) $dupaItemsForPlanning[$cat] = [];
            $dupaItemsForPlanning[$cat][] = $d;
        }
    }
} catch (Exception $e) {}

// Working days helper (for SOW duration display)
$holidayDates = [];
try {
    $holidayRows = $pdo->query("SELECT holiday_date FROM holidays ORDER BY holiday_date")->fetchAll(PDO::FETCH_COLUMN);
    $holidayDates = array_map(function ($d) { return date('Y-m-d', strtotime($d)); }, $holidayRows);
} catch (Exception $e) { }
$satWorking = !empty($project['saturday_working']);
$sunWorking = !empty($project['sunday_working']);
$isNoWorkDate = function ($dateYmd) use ($holidayDates, $satWorking, $sunWorking) {
    $ts = strtotime($dateYmd);
    $dow = (int)date('N', $ts);
    $isSat = ($dow === 6);
    $isSun = ($dow === 7);
    $isWeekend = $isSat || $isSun;
    $isHoliday = in_array($dateYmd, $holidayDates, true);
    return $isHoliday || ($isWeekend && (($isSat && !$satWorking) || ($isSun && !$sunWorking)));
};
$workingDaysInRange = function ($startYmd, $endYmd) use ($isNoWorkDate) {
    $count = 0;
    $first = null;
    $last = null;
    $startTs = strtotime($startYmd);
    $endTs = strtotime($endYmd);
    for ($t = $startTs; $t <= $endTs; $t += 86400) {
        $d = date('Y-m-d', $t);
        if (!$isNoWorkDate($d)) {
            $count++;
            if ($first === null) $first = $d;
            $last = $d;
        }
    }
    return ['count' => $count, 'first' => $first, 'last' => $last];
};

// Planning: day-based Gantt (Day 1, Day 2, ...). Baseline = NTP or project start or earliest planned date.
$rangeStart = ($project['notice_to_proceed'] ?? $project['start_date']) ?: null;
$rangeEnd = $project['target_end_date'] ?: null;
foreach ($timelineTasks as $t) {
    if (!empty($t['planned_start_date']) && (strtotime($t['planned_start_date']) < strtotime($rangeStart ?: '9999-12-31'))) $rangeStart = $t['planned_start_date'];
    if (!empty($t['planned_end_date']) && (strtotime($t['planned_end_date']) > strtotime($rangeEnd ?: '1970-01-01'))) $rangeEnd = $t['planned_end_date'];
}
foreach ($wbsItems as $w) {
    if (!empty($w['planned_start_date']) && strtotime($w['planned_start_date']) < strtotime($rangeStart ?: '9999-12-31')) $rangeStart = $w['planned_start_date'];
    if (!empty($w['planned_end_date']) && strtotime($w['planned_end_date']) > strtotime($rangeEnd ?: '1970-01-01')) $rangeEnd = $w['planned_end_date'];
}
if ($useSowTasks && !empty($sowTaskItems)) {
    foreach ($sowTaskItems as $w) {
        if (!empty($w['planned_start_date']) && strtotime($w['planned_start_date']) < strtotime($rangeStart ?: '9999-12-31')) $rangeStart = $w['planned_start_date'];
        if (!empty($w['planned_end_date']) && strtotime($w['planned_end_date']) > strtotime($rangeEnd ?: '1970-01-01')) $rangeEnd = $w['planned_end_date'];
    }
}
if (!$rangeStart) $rangeStart = date('Y-m-d');
if (!$rangeEnd) $rangeEnd = date('Y-m-d', strtotime('+1 month'));
$planningBaseline = $rangeStart;
$planningBaselineTs = strtotime($planningBaseline);
$planningTotalDays = (int)max(1, (strtotime($rangeEnd) - $planningBaselineTs) / 86400 + 1);
$ganttDays = [];
for ($n = 1; $n <= $planningTotalDays; $n++) {
    $ganttDays[] = ['day' => $n, 'label' => 'Day ' . $n];
}
function planningDateToDay($dateYmd, $baselineTs) {
    if (empty($dateYmd)) return null;
    $t = strtotime($dateYmd);
    return (int)max(1, floor(($t - $baselineTs) / 86400) + 1);
}
function planningDayToDate($dayNum, $baselineYmd) {
    if ($dayNum < 1) return $baselineYmd;
    return date('Y-m-d', strtotime($baselineYmd . ' +' . ((int)$dayNum - 1) . ' days'));
}

$planningRedirectTab = !empty($planningEmbeddedInScopeOfWork) ? 'budget' : 'planning';
$planningRedirectUrl = BASE_URL . '/pages/projects/view.php?id=' . (int)$id . '&tab=' . $planningRedirectTab;
$planningFormRedirectHidden = ($planningRedirectTab === 'budget') ? '<input type="hidden" name="redirect_tab" value="budget">' : '';

$planningLeftColsWidth = 52 + 280 + 110 + 120 + 120;
$planningDayWidth = 52;
$planningTableMinWidth = $planningLeftColsWidth + count($ganttDays) * $planningDayWidth;

// Per-day resource plan (derived from task resources + task dates)
$resourcePlanByDay = [];   // date_ymd => resource_key => quantity
$resourcePlanResourceLabels = []; // resource_key => display label
$resourcePlanDates = [];   // ordered list of date_ymd for grid columns
if ($hasPlanningTaskResourcesTable) {
    $tasksForResourcePlan = [];
    // Leaf SOW tasks with dates
    foreach ($sowTaskItems as $t) {
        $tid = (int)$t['id'];
        if (!empty($sowTasksByParent[$tid])) continue; // has children, skip
        $s = !empty($t['planned_start_date']) ? date('Y-m-d', strtotime($t['planned_start_date'])) : null;
        $e = !empty($t['planned_end_date']) ? date('Y-m-d', strtotime($t['planned_end_date'])) : null;
        if (!$s || !$e) continue;
        $dur = max(1, (int)($t['planned_duration_days'] ?? 0) ?: $workingDaysInRange($s, $e)['count']);
        $resources = $planningTaskResourcesBySowTask[$tid] ?? [];
        if (empty($resources)) continue;
        $tasksForResourcePlan[] = ['start' => $s, 'end' => $e, 'duration_days' => $dur, 'resources' => $resources];
    }
    // WBS items with dates
    foreach ($wbsItems as $w) {
        $wid = (int)$w['id'];
        $s = !empty($w['planned_start_date']) ? date('Y-m-d', strtotime($w['planned_start_date'])) : null;
        $e = !empty($w['planned_end_date']) ? date('Y-m-d', strtotime($w['planned_end_date'])) : null;
        if (!$s || !$e) continue;
        $dur = max(1, (int)($w['planned_duration_days'] ?? 0) ?: $workingDaysInRange($s, $e)['count']);
        $resources = $planningTaskResourcesByWbsItem[$wid] ?? [];
        if (empty($resources)) continue;
        $tasksForResourcePlan[] = ['start' => $s, 'end' => $e, 'duration_days' => $dur, 'resources' => $resources];
    }
    for ($n = 1; $n <= $planningTotalDays; $n++) {
        $resourcePlanDates[] = planningDayToDate($n, $planningBaseline);
    }
    foreach ($resourcePlanDates as $dateYmd) {
        $resourcePlanByDay[$dateYmd] = [];
    }
    foreach ($tasksForResourcePlan as $task) {
        $taskStart = $task['start'];
        $taskEnd = $task['end'];
        $durationDays = $task['duration_days'];
        foreach ($task['resources'] as $res) {
            $qty = (float)($res['quantity'] ?? 0);
            $useRule = $res['use_rule'] ?? 'spread';
            $rk = !empty($res['dupa_item_id']) ? 'd:' . (int)$res['dupa_item_id'] : 'f:' . (string)($res['resource_description'] ?? '');
            $label = $res['dupa_description'] ?? $res['resource_description'] ?? $rk;
            $resourcePlanResourceLabels[$rk] = $label;
            foreach ($resourcePlanDates as $dateYmd) {
                if ($dateYmd < $taskStart || $dateYmd > $taskEnd) continue;
                $dayQty = 0;
                if ($useRule === 'start' && $dateYmd === $taskStart) $dayQty = $qty;
                elseif ($useRule === 'end' && $dateYmd === $taskEnd) $dayQty = $qty;
                elseif ($useRule === 'spread' && $durationDays > 0) $dayQty = $qty / $durationDays;
                if ($dayQty > 0) {
                    if (!isset($resourcePlanByDay[$dateYmd][$rk])) $resourcePlanByDay[$dateYmd][$rk] = 0;
                    $resourcePlanByDay[$dateYmd][$rk] += $dayQty;
                }
            }
        }
    }
}
?>

<div class="card shadow-sm timeline-wrap" data-planning-project-id="<?= (int)$id ?>" id="planning-tab-card">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 section-title">Planning – Scope of Work & Execution Plan</h6>
        <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=budget" class="btn btn-outline-secondary btn-sm">
            <i class="bi bi-wallet2 me-1"></i> Edit Scope of Work (Budget)
        </a>
    </div>
    <div class="card-body">
        <p class="text-muted small mb-3">
            <?php if ($useSowTasks): ?>
            <strong>Scope of Work</strong> is the master for scheduling. Tasks under each SOW support hierarchy (1, 1.1, 1.2), duration, planned start/end, and dependencies. Parent duration is the sum of children. The <strong>planned duration (days)</strong> in the Scope of Work table is the <strong>maximum</strong> for that SOW; plan activities within it. If your execution plan needs more time, use <strong>Update SOW to X days</strong> to sync the Scope of Work—or edit duration in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=budget">Scope of Work</a> tab.
            <?php else: ?>
            Descriptions come from <strong>Scope of Work</strong> (read-only here). The <strong>planned duration</strong> in Scope of Work is the <strong>max</strong> for that SOW. Use <strong>+</strong> below each line to add sub-items; set planned duration and dates—bars update automatically. If the plan needs more time, update the SOW duration in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=budget">Scope of Work</a> tab.
            <?php endif; ?>
            <?php if ($hasPlanningTaskResourcesTable): ?> Use <strong>Resources (X)</strong> on each activity for comprehensive planning (materials, equipment, people); see the section below.<?php endif; ?>
        </p>
        <?php if (!empty($orphanWbsItems) && !$wbsHasBudgetItemId): ?>
        <div class="alert alert-info py-2 small mb-3">
            <strong>Tip:</strong> Run <code>database/migration_planning_wbs.sql</code> so new breakdown items link to the correct Scope. Until then, they appear under the first Scope.
        </div>
        <?php endif; ?>

        <?php if (!$tableExists): ?>
        <div class="alert alert-warning mb-0">
            <strong>Timeline table not found.</strong> Run <code>database/migration_timeline.sql</code> or create <code>project_timeline_tasks</code> so SOW rows can have planned dates.
        </div>
        <?php elseif (empty($sowItems)): ?>
        <div class="text-center text-muted py-5">
            <i class="bi bi-diagram-3 fs-1 d-block mb-2"></i>
            <p class="mb-2">No Scope of Work yet.</p>
            <p class="small mb-0">Add items in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=budget">Scope of Work (Budget)</a> tab; they will appear here with planned duration and dates.</p>
        </div>
        <?php else: ?>
        <p class="planning-scroll-hint mb-2">
            <i class="bi bi-arrow-left-right"></i>
            <span>Scroll to view all days · Day 1 = <?= formatDate($planningBaseline) ?> (baseline)</span>
        </p>
        <div class="timeline-gantt-scroll-wrapper">
            <div class="timeline-gantt-scroll">
                <table class="table table-bordered table-sm align-middle mb-0 timeline-spreadsheet timeline-gantt-table planning-gantt-table" style="min-width: <?= $planningTableMinWidth ?>px; width: <?= $planningTableMinWidth ?>px;">
                    <thead>
                        <tr class="table-light timeline-thead-row">
                            <th class="col-id gantt-task-cell" style="width:3rem"></th>
                            <th class="col-desc gantt-task-cell">Description</th>
                            <th class="col-duration" style="width:11rem">Planned duration (days)</th>
                            <th class="col-start" style="width:10rem">Start</th>
                            <th class="col-end" style="width:10rem">End</th>
                            <?php foreach ($ganttDays as $gd): ?>
                            <th class="date-header-cell gantt-day-header" style="width:<?= $planningDayWidth ?>px; min-width:<?= $planningDayWidth ?>px"><span class="d-block" style="font-size:0.65rem"><?= htmlspecialchars($gd['label']) ?></span></th>
                            <?php endforeach; ?>
                        </tr>
                    </thead>
                        <?php
                        $planningRowNum = 0;
                        $todayYmd = date('Y-m-d');
                        foreach ($sowItems as $sow):
                            $sowId = (int)$sow['id'];
                            $task = $taskByBudgetId[$sowId] ?? null;
                            if (!$task):
                                continue;
                            endif;
                            $planningRowNum++;
                            $children = $wbsByBudgetItem[$sowId] ?? [];
                            if ($planningRowNum === 1 && !empty($orphanWbsItems)) {
                                $children = array_merge($children, $orphanWbsItems);
                            }
                            $sowChildren = $useSowTasks ? ($sowTasksByBudgetItem[$sowId] ?? []) : [];
                            $sowTotalDays = 0;
                            $sowMinStart = null;
                            $sowMaxEnd = null;
                            if ($useSowTasks && !empty($sowTaskRangeByBudget[$sowId])) {
                                $r = $sowTaskRangeByBudget[$sowId];
                                $sowTotalDays = (int)($r['total_days'] ?? 0);
                                $sowMinStart = $r['min_start'] ?? null;
                                $sowMaxEnd = $r['max_end'] ?? null;
                            }
                            foreach ($children as $c) {
                                $d = isset($c['planned_duration_days']) && $c['planned_duration_days'] !== null && $c['planned_duration_days'] !== '' ? (int)$c['planned_duration_days'] : 0;
                                if ($d > 0) $sowTotalDays += $d;
                                if (!empty($c['planned_start_date'])) {
                                    $s = date('Y-m-d', strtotime($c['planned_start_date']));
                                    $sowMinStart = ($sowMinStart === null || $s < $sowMinStart) ? $s : $sowMinStart;
                                }
                                if (!empty($c['planned_end_date'])) {
                                    $e = date('Y-m-d', strtotime($c['planned_end_date']));
                                    $sowMaxEnd = ($sowMaxEnd === null || $e > $sowMaxEnd) ? $e : $sowMaxEnd;
                                }
                            }
                            $sowFromBreakdown = ($sowMinStart !== null && $sowMaxEnd !== null) || ($useSowTasks && !empty($sowChildren));
                            $plannedStart = date('Y-m-d', strtotime($task['planned_start_date']));
                            $plannedEnd = date('Y-m-d', strtotime($task['planned_end_date']));
                            if ($sowFromBreakdown && $sowMinStart !== null && $sowMaxEnd !== null) {
                                $plannedStart = $sowMinStart;
                                $plannedEnd = $sowMaxEnd;
                                $plannedDays = $sowTotalDays > 0 ? $sowTotalDays : max(1, $workingDaysInRange($plannedStart, $plannedEnd)['count']);
                            } else {
                                $plannedRange = $workingDaysInRange($plannedStart, $plannedEnd);
                                $plannedDays = max(1, $plannedRange['count']);
                            }
                            $plannedRange = $workingDaysInRange($plannedStart, $plannedEnd);
                            $plannedFirstWork = $plannedRange['first'];
                            $plannedLastWork = $plannedRange['last'];
                            $plannedStartDay = planningDateToDay($plannedStart, $planningBaselineTs);
                            $plannedEndDay = planningDateToDay($plannedEnd, $planningBaselineTs);
                            // Max planned duration from Scope of Work (same as budget table) — activities should not exceed this unless you update SOW
                            $sowMaxDuration = (isset($sow['duration_days']) && $sow['duration_days'] !== null && $sow['duration_days'] !== '') ? (int)$sow['duration_days'] : null;
                            $sowDurationOverrun = ($sowMaxDuration !== null && $plannedDays > $sowMaxDuration);
                        ?>
                    <tbody class="planning-scope-group">
                        <tr class="d-none planning-form-row"><td colspan="<?= 5 + count($ganttDays) ?>">
                            <form id="planned-form-<?= (int)$task['id'] ?>" action="<?= BASE_URL ?>/actions/timeline_actions.php" method="POST" data-base-url="<?= BASE_URL ?>" data-redirect-url="<?= htmlspecialchars($planningRedirectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="action" value="update">
                                <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($planningRedirectUrl, ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="task_id" value="<?= (int)$task['id'] ?>">
                                <input type="hidden" name="task_label" value="<?= htmlspecialchars($task['task_label'], ENT_QUOTES, 'UTF-8') ?>">
                                <input type="hidden" name="budget_item_id" value="<?= $sowId ?>">
                                <input type="hidden" name="planned_start_date" id="planned-form-start-<?= (int)$task['id'] ?>" value="<?= $plannedStart ?>">
                                <input type="hidden" name="planned_end_date" id="planned-form-end-<?= (int)$task['id'] ?>" value="<?= $plannedEnd ?>">
                            </form>
                        </td></tr>
                        <tr class="table-light planned-row planning-sow-row">
                            <td class="col-id text-center"><span class="fw-medium"><?= (int)$planningRowNum ?></span></td>
                            <td class="col-desc">
                                <div class="d-flex justify-content-between align-items-center">
                                    <span>
                                        <span class="text-primary fw-semibold"><?= sanitize($sow['item_name']) ?></span>
                                        <span class="badge bg-light text-muted ms-1" style="font-size:0.65rem">SOW</span>
                                    </span>
                                    <?php if ($useSowTasks): ?>
                                    <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline ms-2" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                        <input type="hidden" name="action" value="add_sow_task">
                                        <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                        <input type="hidden" name="budget_item_id" value="<?= $sowId ?>">
                                        <input type="hidden" name="description" value="">
                                        <?= $planningFormRedirectHidden ?? '' ?>
                                        <button type="submit" class="btn btn-sm planning-add-btn" title="Add task under Scope of Work">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                    <?php else: ?>
                                    <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline ms-2" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                        <input type="hidden" name="action" value="add_wbs_item_quick">
                                        <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                        <input type="hidden" name="budget_item_id" value="<?= $sowId ?>">
                                        <?= $planningFormRedirectHidden ?? '' ?>
                                        <input type="hidden" name="scope_index" value="<?= $planningRowNum ?>">
                                        <input type="hidden" name="sow_planned_start" value="<?= $plannedStart ?>">
                                        <input type="hidden" name="sow_planned_end" value="<?= $plannedEnd ?>">
                                        <input type="hidden" name="sow_planned_days" value="<?= $plannedDays ?>">
                                        <button type="submit" class="btn btn-sm planning-add-btn" title="Add breakdown">
                                            <i class="bi bi-plus-lg"></i>
                                        </button>
                                    </form>
                                    <?php endif; ?>
                                </div>
                            </td>
                            <td class="col-duration">
                                <?php if ($sowFromBreakdown): ?>
                                <span class="text-muted"><?= (int)$plannedDays ?></span>
                                <small class="text-muted">working days</small>
                                <?php if ($sowMaxDuration !== null): ?>
                                <small class="text-muted d-block">max: <?= $sowMaxDuration ?> days</small>
                                <?php endif; ?>
                                <?php if ($sowDurationOverrun): ?>
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark" title="Activities exceed Scope of Work planned duration">Overrun</span>
                                    <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST" class="d-inline ms-1" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                        <input type="hidden" name="action" value="update_duration">
                                        <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                        <input type="hidden" name="item_id" value="<?= $sowId ?>">
                                        <input type="hidden" name="duration_days" value="<?= (int)$plannedDays ?>">
                                        <input type="hidden" name="redirect_tab" value="budget">
                                        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Update SOW to <?= (int)$plannedDays ?> days</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                <?php else: ?>
                                <input type="number" min="1" max="365" value="<?= $plannedDays ?>" class="form-control form-control-sm planned-duration-input text-center" data-start-id="planned-form-start-<?= (int)$task['id'] ?>" data-end-id="planned-form-end-<?= (int)$task['id'] ?>" data-form-id="planned-form-<?= (int)$task['id'] ?>" data-task-id="<?= (int)$task['id'] ?>" data-project-id="<?= (int)$id ?>" title="Working days. Saves automatically.<?= $sowMaxDuration !== null ? ' Max ' . $sowMaxDuration . ' days from Scope of Work; use Update SOW if you need more.' : '' ?>" style="max-width:5rem">
                                <small class="text-muted">working days</small>
                                <?php if ($sowMaxDuration !== null): ?>
                                <small class="text-muted d-block">max: <?= $sowMaxDuration ?> days</small>
                                <?php endif; ?>
                                <?php if ($sowDurationOverrun): ?>
                                <div class="mt-1">
                                    <span class="badge bg-warning text-dark">Overrun</span>
                                    <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST" class="d-inline ms-1" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                        <input type="hidden" name="action" value="update_duration">
                                        <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                        <input type="hidden" name="item_id" value="<?= $sowId ?>">
                                        <input type="hidden" name="duration_days" value="<?= (int)$plannedDays ?>">
                                        <input type="hidden" name="redirect_tab" value="budget">
                                        <button type="submit" class="btn btn-link btn-sm p-0 align-baseline">Update SOW to <?= (int)$plannedDays ?> days</button>
                                    </form>
                                </div>
                                <?php endif; ?>
                                <?php endif; ?>
                            </td>
                            <td class="col-start">
                                <?php if ($sowFromBreakdown): ?>
                                <span class="text-muted">Day <?= (int)$plannedStartDay ?></span>
                                <?php else: ?>
                                <input type="number" min="1" max="<?= $planningTotalDays ?>" value="<?= (int)$plannedStartDay ?>" class="form-control form-control-sm planned-day-start-input text-center" style="max-width:4.5rem" data-date-id="planned-form-start-<?= (int)$task['id'] ?>" data-form-id="planned-form-<?= (int)$task['id'] ?>" data-baseline="<?= htmlspecialchars($planningBaseline, ENT_QUOTES, 'UTF-8') ?>" title="Start day">
                                <?php endif; ?>
                            </td>
                            <td class="col-end">
                                <?php if ($sowFromBreakdown): ?>
                                <span class="text-muted">Day <?= (int)$plannedEndDay ?></span>
                                <?php else: ?>
                                <span id="planned-end-day-<?= (int)$task['id'] ?>" class="planned-end-day-display text-muted">Day <?= (int)$plannedEndDay ?></span>
                                <?php endif; ?>
                            </td>
                            <?php
                            // SOW bar: only show bars on days that have at least one child activity (no bar on gap days)
                            $sowActiveDays = [];
                            if ($sowFromBreakdown && $planningBaselineTs !== null) {
                                if ($useSowTasks && !empty($sowChildren)) {
                                    $allSowTasks = [];
                                    $collectSowTasks = function($list) use (&$collectSowTasks, $sowTasksByParent, &$allSowTasks) {
                                        foreach ($list as $t) {
                                            $allSowTasks[] = $t;
                                            $sub = $sowTasksByParent[(int)$t['id']] ?? [];
                                            if (!empty($sub)) $collectSowTasks($sub);
                                        }
                                    };
                                    $collectSowTasks($sowChildren);
                                    foreach ($allSowTasks as $t) {
                                        $s = $t['planned_start_date'] ?? null;
                                        $e = $t['planned_end_date'] ?? null;
                                        if ($s && $e) {
                                            $ds = planningDateToDay(date('Y-m-d', strtotime($s)), $planningBaselineTs);
                                            $de = planningDateToDay(date('Y-m-d', strtotime($e)), $planningBaselineTs);
                                            if ($ds !== null && $de !== null) {
                                                for ($d = $ds; $d <= $de; $d++) $sowActiveDays[$d] = true;
                                            }
                                        }
                                    }
                                } elseif (!empty($children)) {
                                    foreach ($children as $c) {
                                        $s = $c['planned_start_date'] ?? null;
                                        $e = $c['planned_end_date'] ?? null;
                                        if ($s && $e) {
                                            $ds = planningDateToDay(date('Y-m-d', strtotime($s)), $planningBaselineTs);
                                            $de = planningDateToDay(date('Y-m-d', strtotime($e)), $planningBaselineTs);
                                            if ($ds !== null && $de !== null) {
                                                for ($d = $ds; $d <= $de; $d++) $sowActiveDays[$d] = true;
                                            }
                                        }
                                    }
                                }
                            }
                            foreach ($ganttDays as $gd):
                                $dayNum = $gd['day'];
                                if (!empty($sowActiveDays)) {
                                    $inPlanned = isset($sowActiveDays[$dayNum]);
                                    $firstActive = min(array_keys($sowActiveDays));
                                    $lastActive = max(array_keys($sowActiveDays));
                                    $isFirst = $inPlanned && ($dayNum === $firstActive);
                                    $isLast = $inPlanned && ($dayNum === $lastActive);
                                } else {
                                    $inPlanned = ($plannedStartDay !== null && $plannedEndDay !== null && $dayNum >= $plannedStartDay && $dayNum <= $plannedEndDay);
                                    $isFirst = $inPlanned && ($dayNum === $plannedStartDay);
                                    $isLast = $inPlanned && ($dayNum === $plannedEndDay);
                                }
                            ?>
                            <td class="gantt-day-cell planned-day-cell <?= $inPlanned ? 'bar-planned bar-sow' : '' ?> <?= $isFirst ? 'bar-start' : '' ?> <?= $isLast ? 'bar-end' : '' ?>" title="<?= $inPlanned ? 'Planned: Day ' . $plannedStartDay . ' – Day ' . $plannedEndDay : '' ?>"></td>
                            <?php endforeach; ?>
                        </tr>
                        <?php
                        if ($useSowTasks && !empty($sowChildren)) {
                            $planningSowIndex = $planningRowNum;
                            $sowTaskChildren = $sowChildren;
                            require __DIR__ . '/_planning_sow_task_rows.php';
                        } elseif (!empty($children)) {
                            $planningSowIndex = $planningRowNum;
                            require __DIR__ . '/_planning_wbs_rows.php';
                        }
                        ?>
                    </tbody>
                        <?php endforeach; ?>
                </table>
            </div>
        </div>

        <?php if ($hasPlanningTaskResourcesTable): ?>
        <!-- Comprehensive planning: resources per activity + resource plan by day -->
        <div class="card shadow-sm mt-4" id="planning-comprehensive">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Comprehensive planning</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-3">
                    Plan <strong>materials, equipment, and people</strong> per activity and see the result by day. Use <strong>Resources (X)</strong> on each activity row above to add or edit resources (quantity, unit, and when to use: start date, end date, or spread over duration). The grid below shows the derived plan per day using the same baseline as the Gantt.
                </p>
                <?php if (!empty($resourcePlanResourceLabels)): ?>
                <h6 class="mb-2">Resource plan by day</h6>
                <div class="timeline-gantt-scroll-wrapper">
                    <div class="timeline-gantt-scroll">
                        <table class="table table-bordered table-sm align-middle mb-0 planning-resource-by-day-table" style="min-width: 320px;">
                            <thead>
                                <tr class="table-light">
                                    <th class="col-resource" style="min-width:180px">Resource</th>
                                    <?php foreach ($resourcePlanDates as $d): ?>
                                    <th class="text-center gantt-day-header" style="width:<?= $planningDayWidth ?>px; min-width:<?= $planningDayWidth ?>px; font-size:0.65rem" title="<?= htmlspecialchars(formatDate($d), ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars(formatDate($d), ENT_QUOTES, 'UTF-8') ?></th>
                                    <?php endforeach; ?>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($resourcePlanResourceLabels as $rk => $label): ?>
                                <tr>
                                    <td class="fw-medium"><?= htmlspecialchars($label, ENT_QUOTES, 'UTF-8') ?></td>
                                    <?php foreach ($resourcePlanDates as $dateYmd): ?>
                                    <td class="text-center text-muted" style="font-size:0.75rem">
                                        <?php
                                        $val = isset($resourcePlanByDay[$dateYmd][$rk]) ? $resourcePlanByDay[$dateYmd][$rk] : 0;
                                        echo $val > 0 ? number_format($val, 2) : '—';
                                        ?>
                                    </td>
                                    <?php endforeach; ?>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
                <?php else: ?>
                <p class="text-muted small mb-0">Add resources to activities using <strong>Resources (0)</strong> on each activity row above; the resource plan by day will appear here.</p>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Saved notification (no reload on save) -->
<div id="planning-saved-toast" class="position-fixed bottom-0 end-0 p-3" style="z-index: 1100; pointer-events: none; display: none;">
    <div class="toast show align-items-center text-bg-success border-0" role="status">
        <div class="toast-body"><i class="bi bi-check-circle-fill me-2"></i>Saved</div>
    </div>
</div>

<script>
(function() {
    function showSaved() {
        var el = document.getElementById('planning-saved-toast');
        if (!el) return;
        el.style.display = 'block';
        clearTimeout(showSaved._t);
        showSaved._t = setTimeout(function() { el.style.display = 'none'; }, 2200);
    }
    function addDays(dateStr, days) {
        var d = new Date(dateStr + 'T12:00:00');
        d.setDate(d.getDate() + days);
        return d.toISOString().slice(0, 10);
    }
    function dayToDate(dayNum, baselineYmd) {
        if (!baselineYmd || dayNum < 1) return baselineYmd || '';
        return addDays(baselineYmd, (parseInt(dayNum, 10) || 1) - 1);
    }
    var DEBOUNCE_MS = 350;

    function submitSowForm(formEl) {
        if (formEl.getAttribute('data-submitting') === '1') return;
        var scrollY = window.scrollY || document.documentElement.scrollTop;
        formEl.setAttribute('data-submitting', '1');
        var formData = new FormData(formEl);
        var actionUrl = formEl.getAttribute('action');
        var base = formEl.getAttribute('data-base-url');
        if (base) actionUrl = (base.replace(/\/$/, '') + '/actions/timeline_actions.php');
        fetch(actionUrl, { method: 'POST', body: formData, redirect: 'follow', credentials: 'same-origin' })
            .then(function() {
                formEl.removeAttribute('data-submitting');
                window.scrollTo(0, scrollY);
                showSaved();
            })
            .catch(function() { formEl.removeAttribute('data-submitting'); window.scrollTo(0, scrollY); });
    }

    document.querySelectorAll('.planned-row').forEach(function(row) {
        var durInput = row.querySelector('.planned-duration-input');
        var startDayInp = row.querySelector('.planned-day-start-input');
        var endDayDisplay = row.querySelector('.planned-end-day-display');
        if (!durInput || !startDayInp || !endDayDisplay) return;
        var startEl = document.getElementById(durInput.dataset.startId);
        var endEl = document.getElementById(durInput.dataset.endId);
        var formEl = document.getElementById(durInput.dataset.formId);
        var baseline = startDayInp.getAttribute('data-baseline') || '';
        if (!startEl || !endEl || !formEl || !baseline) return;
        var submitTimer = null;
        function syncEndFromStartAndDuration() {
            var durationVal = parseInt(durInput.value, 10) || 1;
            durationVal = Math.max(1, Math.min(365, durationVal));
            durInput.value = durationVal;
            var startDay = parseInt(startDayInp.value, 10) || 1;
            var endDay = startDay + durationVal - 1;
            endDayDisplay.textContent = 'Day ' + endDay;
            startEl.value = dayToDate(startDay, baseline);
            endEl.value = dayToDate(endDay, baseline);
        }
        function doSubmit() {
            submitTimer = null;
            syncEndFromStartAndDuration();
            var startVal = (startEl.value || '').trim();
            if (!startVal) return;
            var scrollY = window.scrollY || document.documentElement.scrollTop;
            formEl.setAttribute('data-submitting', '1');
            var formData = new FormData(formEl);
            formData.set('planned_start_date', startVal);
            formData.set('planned_end_date', endEl.value);
            formData.set('planned_duration_working_days', String(durInput.value));
            var actionUrl = formEl.getAttribute('action');
            var base = formEl.getAttribute('data-base-url');
            if (base) actionUrl = base.replace(/\/$/, '') + '/actions/timeline_actions.php';
            fetch(actionUrl, { method: 'POST', body: formData, redirect: 'follow', credentials: 'same-origin' })
                .then(function() {
                    formEl.removeAttribute('data-submitting');
                    window.scrollTo(0, scrollY);
                    showSaved();
                })
                .catch(function() { formEl.removeAttribute('data-submitting'); window.scrollTo(0, scrollY); });
        }
        function scheduleSubmit() {
            if (submitTimer) clearTimeout(submitTimer);
            submitTimer = setTimeout(doSubmit, DEBOUNCE_MS);
        }
        durInput.addEventListener('change', function() {
            syncEndFromStartAndDuration();
            scheduleSubmit();
        });
        startDayInp.addEventListener('change', function() {
            syncEndFromStartAndDuration();
            scheduleSubmit();
        });
    });

    document.querySelectorAll('.wbs-row').forEach(function(row) {
        var durInput = row.querySelector('.wbs-duration-input');
        var startDayInp = row.querySelector('.wbs-day-start-input');
        var endDayDisplay = row.querySelector('.wbs-end-day-display');
        var formEl = row.querySelector('form[id^="wbs-form-"]');
        if (!durInput || !startDayInp || !endDayDisplay || !formEl) return;
        var baseline = startDayInp.getAttribute('data-baseline') || '';
        var startDateEl = document.getElementById(startDayInp.getAttribute('data-date-id'));
        var endDateEl = document.getElementById('wbs-form-end-' + formEl.id.replace('wbs-form-', ''));
        if (!startDateEl || !endDateEl || !baseline) return;
        var submitTimer = null;
        function syncEndFromStartAndDuration() {
            var durationVal = parseInt(durInput.value, 10) || 1;
            durationVal = Math.max(1, Math.min(365, durationVal));
            durInput.value = durationVal;
            var startDay = parseInt(startDayInp.value, 10) || 1;
            var endDay = startDay + durationVal - 1;
            endDayDisplay.textContent = 'Day ' + endDay;
            startDateEl.value = dayToDate(startDay, baseline);
            endDateEl.value = dayToDate(endDay, baseline);
        }
        function doSubmit() {
            submitTimer = null;
            if (formEl.getAttribute('data-submitting') === '1') return;
            var scrollY = window.scrollY || document.documentElement.scrollTop;
            formEl.setAttribute('data-submitting', '1');
            var fd = new FormData(formEl);
            fetch(formEl.getAttribute('action'), { method: 'POST', body: fd, redirect: 'follow', credentials: 'same-origin' })
                .then(function() { formEl.removeAttribute('data-submitting'); window.scrollTo(0, scrollY); showSaved(); })
                .catch(function() { formEl.removeAttribute('data-submitting'); window.scrollTo(0, scrollY); });
        }
        function scheduleSubmit() {
            if (submitTimer) clearTimeout(submitTimer);
            submitTimer = setTimeout(doSubmit, 400);
        }
        durInput.addEventListener('change', function() {
            syncEndFromStartAndDuration();
            scheduleSubmit();
        });
        startDayInp.addEventListener('change', function() {
            syncEndFromStartAndDuration();
            scheduleSubmit();
        });
    });

    document.querySelectorAll('.wbs-save-all-btn').forEach(function(btn) {
        var nameFormId = btn.getAttribute('data-name-form-id');
        var datesFormId = btn.getAttribute('data-dates-form-id');
        var nameForm = nameFormId ? document.getElementById(nameFormId) : null;
        var datesForm = datesFormId ? document.getElementById(datesFormId) : null;
        if (!nameForm || !datesForm) return;
        btn.addEventListener('click', function() {
            if (btn.getAttribute('data-submitting') === '1') return;
            var scrollY = window.scrollY || document.documentElement.scrollTop;
            btn.setAttribute('data-submitting', '1');
            var actionUrl = nameForm.getAttribute('action');
            var p1 = fetch(actionUrl, { method: 'POST', body: new FormData(nameForm), redirect: 'follow', credentials: 'same-origin' });
            var p2 = fetch(datesForm.getAttribute('action'), { method: 'POST', body: new FormData(datesForm), redirect: 'follow', credentials: 'same-origin' });
            Promise.all([p1, p2]).then(function() {
                btn.removeAttribute('data-submitting');
                window.scrollTo(0, scrollY);
                showSaved();
            }).catch(function() {
                btn.removeAttribute('data-submitting');
                window.scrollTo(0, scrollY);
            });
        });
    });

    var addModal = document.getElementById('addWbsModal');
    if (addModal) {
        addModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var budgetId = (btn && btn.getAttribute('data-budget-item-id')) ? btn.getAttribute('data-budget-item-id') : '';
            var parentId = (btn && btn.getAttribute('data-parent-id')) ? btn.getAttribute('data-parent-id') : '';
            var label = (btn && btn.getAttribute('data-parent-label')) ? btn.getAttribute('data-parent-label') : '';
            var bidEl = document.getElementById('addWbsBudgetItemId');
            var pidEl = document.getElementById('addWbsParentId');
            var titleEl = document.getElementById('addWbsModalTitle');
            var underEl = document.getElementById('addWbsUnderLabel');
            if (bidEl) bidEl.value = budgetId;
            if (pidEl) pidEl.value = parentId;
            if (label) {
                if (titleEl) titleEl.textContent = 'Add work breakdown';
                if (underEl) { underEl.textContent = 'Under: ' + label; underEl.style.display = 'block'; }
            } else {
                if (titleEl) titleEl.textContent = 'Add work breakdown';
                if (underEl) { underEl.style.display = 'none'; }
            }
        });
    }

    document.querySelectorAll('.planning-resources-toggle').forEach(function(btn) {
        var targetId = btn.getAttribute('data-target-id');
        if (!targetId) return;
        btn.addEventListener('click', function() {
            var row = document.getElementById(targetId);
            if (!row) return;
            row.style.display = row.style.display === 'none' ? '' : 'none';
        });
    });

    document.addEventListener('click', function(e) {
        var card = document.getElementById('planning-tab-card');
        if (!card || !card.contains(e.target)) return;
        if (e.target.closest('.planning-resource-edit-btn')) {
            var btn = e.target.closest('.planning-resource-edit-btn');
            var li = btn.closest('.planning-resource-item');
            if (!li) return;
            var display = li.querySelector('.planning-resource-display');
            var formWrap = li.querySelector('.planning-resource-edit-form');
            if (display) display.classList.add('d-none');
            if (formWrap) {
                formWrap.classList.remove('d-none');
                var qty = formWrap.querySelector('input[name=quantity]');
                var unit = formWrap.querySelector('input[name=unit]');
                var rule = formWrap.querySelector('select[name=use_rule]');
                if (qty) qty.value = btn.getAttribute('data-quantity') || qty.value;
                if (unit) unit.value = btn.getAttribute('data-unit') || unit.value;
                if (rule) rule.value = btn.getAttribute('data-use-rule') || rule.value;
            }
        } else if (e.target.closest('.planning-resource-edit-cancel')) {
            var cancel = e.target.closest('.planning-resource-edit-cancel');
            var li = cancel.closest('.planning-resource-item');
            if (!li) return;
            var display = li.querySelector('.planning-resource-display');
            var formWrap = li.querySelector('.planning-resource-edit-form');
            if (display) display.classList.remove('d-none');
            if (formWrap) formWrap.classList.add('d-none');
        }
    });

    var planningCard = document.getElementById('planning-tab-card');
    if (planningCard) {
        planningCard.addEventListener('change', function(e) {
            if (e.target.matches('select[name=dupa_item_id]')) {
                var opt = e.target.options[e.target.selectedIndex];
                var unit = opt && opt.getAttribute('data-unit');
                var form = e.target.closest('form');
                if (form) {
                    var unitInput = form.querySelector('input[name=unit]');
                    if (unitInput) unitInput.value = unit || '';
                }
            }
        });
    }
})();
</script>

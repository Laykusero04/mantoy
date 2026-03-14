<?php
// Tab: Monitoring — schedule vs actual, % accomplishment, cash flow, manpower.
// Expects $pdo, $project, $id from view.php.

$rangeStart = ($project['notice_to_proceed'] ?? $project['start_date']) ?: date('Y-m-d');
$rangeEnd = $project['target_end_date'] ?: date('Y-m-d', strtotime('+1 month'));
$plannedDays = max(1, (strtotime($rangeEnd) - strtotime($rangeStart)) / 86400 + 1);
$budget = (float)($project['budget_allocated'] ?? 0);
$plannedDailyCash = $plannedDays > 0 ? $budget / $plannedDays : 0;

// Total planned quantity (SOW) for accomplishment %
$totalPlannedQty = 0;
try {
    $q = $pdo->prepare("SELECT COALESCE(SUM(quantity), 0) FROM budget_items WHERE project_id = ?");
    $q->execute([$id]);
    $totalPlannedQty = (float)$q->fetchColumn();
} catch (Exception $e) { }

// Daily aggregates from action_logs (amount, quantity_delivered, quantity, manpower)
$dailyRows = [];
$hasManpower = false;
try {
    $sql = "SELECT DATE(log_date) as d, SUM(COALESCE(amount,0)) as amt, SUM(COALESCE(quantity_delivered,0)) as qty_del, SUM(COALESCE(quantity,0)) as qty, SUM(manpower) as man FROM action_logs WHERE project_id = ? AND log_date IS NOT NULL GROUP BY d ORDER BY d";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$id]);
    $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasManpower = true;
} catch (Exception $e) {
    try {
        $sql = "SELECT DATE(log_date) as d, SUM(COALESCE(amount,0)) as amt, SUM(COALESCE(quantity_delivered,0)) as qty_del, SUM(COALESCE(quantity,0)) as qty FROM action_logs WHERE project_id = ? AND log_date IS NOT NULL GROUP BY d ORDER BY d";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dailyRows as &$r) { $r['man'] = null; }
        unset($r);
    } catch (Exception $e2) {
        $sql = "SELECT DATE(log_date) as d FROM action_logs WHERE project_id = ? AND log_date IS NOT NULL GROUP BY d ORDER BY d";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $dailyRows = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($dailyRows as &$r) {
            $r['amt'] = 0;
            $r['qty_del'] = 0;
            $r['qty'] = 0;
            $r['man'] = null;
        }
        unset($r);
    }
}

// Build date range (from project + actual log dates)
$allDates = [];
$startTs = strtotime($rangeStart);
$endTs = strtotime($rangeEnd);
foreach ($dailyRows as $row) {
    $t = strtotime($row['d']);
    if ($t < $startTs) $startTs = $t;
    if ($t > $endTs) $endTs = $t;
}
for ($d = $startTs; $d <= $endTs; $d += 86400) {
    $allDates[] = date('Y-m-d', $d);
}
$monitoringAllDates = $allDates; // keep for progress table after timeline/materials overwrite $allDates
$dailyByDate = [];
foreach ($dailyRows as $row) {
    $dailyByDate[$row['d']] = [
        'amt' => (float)($row['amt'] ?? 0),
        'qty_del' => (float)($row['qty_del'] ?? 0),
        'qty' => (float)($row['qty'] ?? 0),
        'man' => isset($row['man']) && $row['man'] !== null ? (float)$row['man'] : null,
    ];
}

// Planned manpower (timeline tasks)
$plannedManpowerTotal = 0;
try {
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(planned_manpower), 0) FROM project_timeline_tasks WHERE project_id = ?");
    $stmt->execute([$id]);
    $plannedManpowerTotal = (float)$stmt->fetchColumn();
} catch (Exception $e) { }

// Planned manpower from Planning task resources (derived from planning_task_resources + DUPA manpower)
$plannedManpowerFromResourcePlan = null;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'planning_task_resources'");
    if ($chk && $chk->rowCount() > 0) {
        $stmt = $pdo->prepare("
            SELECT COALESCE(SUM(ptr.quantity), 0) AS total
            FROM planning_task_resources ptr
            INNER JOIN project_dupa_items d ON d.id = ptr.dupa_item_id AND d.project_id = ptr.project_id AND d.category = 'manpower'
            WHERE ptr.project_id = ?
        ");
        $stmt->execute([$id]);
        $plannedManpowerFromResourcePlan = (float)$stmt->fetchColumn();
    }
} catch (Exception $e) { }

// Schedule vs actual summary (from timeline)
$scheduleSummary = ['planned_start' => $project['notice_to_proceed'] ?? $project['start_date'], 'planned_end' => $project['target_end_date'], 'actual_start' => null, 'actual_end' => null];
try {
    $stmt = $pdo->prepare("
        SELECT t.id, t.planned_start_date, t.planned_end_date, t.budget_item_id
        FROM project_timeline_tasks t
        WHERE t.project_id = ?
        ORDER BY t.sort_order, t.id
    ");
    $stmt->execute([$id]);
    $tasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $firstActual = null;
    $lastActual = null;
    foreach ($tasks as $t) {
        if (empty($t['budget_item_id'])) continue;
        $dStmt = $pdo->prepare("SELECT MIN(DATE(log_date)) as mn, MAX(DATE(log_date)) as mx FROM action_logs WHERE project_id = ? AND budget_item_id = ?");
        $dStmt->execute([$id, $t['budget_item_id']]);
        $dr = $dStmt->fetch(PDO::FETCH_ASSOC);
        if ($dr && $dr['mn']) {
            if ($firstActual === null || strtotime($dr['mn']) < strtotime($firstActual)) $firstActual = $dr['mn'];
            if ($lastActual === null || strtotime($dr['mx']) > strtotime($lastActual)) $lastActual = $dr['mx'];
        }
    }
    $scheduleSummary['actual_start'] = $firstActual;
    $scheduleSummary['actual_end'] = $lastActual;
} catch (Exception $e) { }

$cumAmt = 0;
$cumQtyDel = 0;

// ─── Timeline (merged from timeline tab) ─────────────────────────────────────
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

$timelineTasks = [];
$timelineTableMissing = !$tableExists;
if ($tableExists) {
    $budgetList = $pdo->prepare("SELECT id, item_name FROM budget_items WHERE project_id = ? ORDER BY id");
    $budgetList->execute([$id]);
    $budgetItemsForSync = $budgetList->fetchAll(PDO::FETCH_ASSOC);
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
    foreach ($budgetItemsForSync as $bi) {
        if (isset($existingBudgetIds[(int)$bi['id']])) continue;
        $ins->execute([$id, $bi['id'], $bi['item_name'], $defaultStart, $defaultEnd, $nextOrder++]);
    }
    $updLabel = $pdo->prepare("UPDATE project_timeline_tasks t INNER JOIN budget_items b ON b.id = t.budget_item_id AND b.project_id = t.project_id SET t.task_label = b.item_name WHERE t.project_id = ?");
    $updLabel->execute([$id]);
    try {
        $stmt = $pdo->prepare("
            SELECT t.id, t.project_id, t.budget_item_id, t.task_label, t.planned_start_date, t.planned_end_date, t.sort_order,
                   t.planned_manpower, b.item_name as sow_item_name
            FROM project_timeline_tasks t
            LEFT JOIN budget_items b ON b.id = t.budget_item_id AND b.project_id = t.project_id
            WHERE t.project_id = ?
            ORDER BY t.sort_order, t.id
        ");
        $stmt->execute([$id]);
        $timelineTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $stmt = $pdo->prepare("
            SELECT t.id, t.project_id, t.budget_item_id, t.task_label, t.planned_start_date, t.planned_end_date, t.sort_order,
                   b.item_name as sow_item_name
            FROM project_timeline_tasks t
            LEFT JOIN budget_items b ON b.id = t.budget_item_id AND b.project_id = t.project_id
            WHERE t.project_id = ?
            ORDER BY t.sort_order, t.id
        ");
        $stmt->execute([$id]);
        $timelineTasks = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach ($timelineTasks as &$t) { $t['planned_manpower'] = null; }
        unset($t);
    }
}

$actualSegments = [];
$actualsSummary = [];
foreach ($timelineTasks as $task) {
    $actualSegments[$task['id']] = [];
    $actualsSummary[$task['id']] = null;
    if (!empty($task['budget_item_id'])) {
        try {
            $daysStmt = $pdo->prepare("
                SELECT DISTINCT DATE(log_date) as d
                FROM action_logs
                WHERE project_id = ? AND budget_item_id = ? AND is_scope_of_work = 1
                ORDER BY d
            ");
            $daysStmt->execute([$id, $task['budget_item_id']]);
            $dates = array_column($daysStmt->fetchAll(PDO::FETCH_ASSOC), 'd');
            if (!empty($dates)) {
                $segments = [];
                $segStart = $dates[0];
                $segEnd = $dates[0];
                foreach ($dates as $i => $d) {
                    if ($i === 0) continue;
                    $prevTs = strtotime($segEnd);
                    $thisTs = strtotime($d);
                    if ($thisTs - $prevTs <= 86400) {
                        $segEnd = $d;
                    } else {
                        $segments[] = ['start' => $segStart, 'end' => $segEnd];
                        $segStart = $d;
                        $segEnd = $d;
                    }
                }
                $segments[] = ['start' => $segStart, 'end' => $segEnd];
                foreach ($segments as $seg) {
                    $actualSegments[$task['id']][] = [
                        'start' => $seg['start'],
                        'end' => $seg['end'],
                        'days' => max(1, (int)((strtotime($seg['end']) - strtotime($seg['start'])) / 86400) + 1)
                    ];
                }
                $actualsSummary[$task['id']] = [
                    'start' => $segments[0]['start'],
                    'end' => $segments[count($segments) - 1]['end'],
                    'days' => array_sum(array_column($actualSegments[$task['id']], 'days'))
                ];
            }
        } catch (Exception $e) { }
    }
}

$timelineRangeStart = $project['start_date'] ?: null;
$timelineRangeEnd = $project['target_end_date'] ?: null;
foreach ($timelineTasks as $t) {
    if ($t['planned_start_date']) {
        if ($timelineRangeStart === null || strtotime($t['planned_start_date']) < strtotime($timelineRangeStart)) $timelineRangeStart = $t['planned_start_date'];
    }
    if ($t['planned_end_date']) {
        if ($timelineRangeEnd === null || strtotime($t['planned_end_date']) > strtotime($timelineRangeEnd)) $timelineRangeEnd = $t['planned_end_date'];
    }
    foreach ($actualSegments[$t['id']] ?? [] as $seg) {
        if (strtotime($seg['start']) < strtotime($timelineRangeStart)) $timelineRangeStart = $seg['start'];
        if (strtotime($seg['end']) > strtotime($timelineRangeEnd)) $timelineRangeEnd = $seg['end'];
    }
}
if (!$timelineRangeStart) $timelineRangeStart = date('Y-m-d');
if (!$timelineRangeEnd) $timelineRangeEnd = date('Y-m-d', strtotime('+1 month'));

$timelineRangeStartTs = strtotime($timelineRangeStart);
$timelineRangeEndTs = strtotime($timelineRangeEnd);
$totalDays = max(1, (int)(($timelineRangeEndTs - $timelineRangeStartTs) / 86400) + 1);

$holidayDates = [];
try {
    $holidayRows = $pdo->query("SELECT holiday_date FROM holidays ORDER BY holiday_date")->fetchAll(PDO::FETCH_COLUMN);
    $holidayDates = array_map(function($d) { return date('Y-m-d', strtotime($d)); }, $holidayRows);
} catch (Exception $e) { }

$satWorking = !empty($project['saturday_working']);
$sunWorking = !empty($project['sunday_working']);

$ganttDates = [];
for ($d = $timelineRangeStartTs; $d <= $timelineRangeEndTs; $d += 86400) {
    $dateYmd = date('Y-m-d', $d);
    $dayOfWeek = (int)date('N', $d);
    $isSat = ($dayOfWeek === 6);
    $isSun = ($dayOfWeek === 7);
    $isWeekend = $isSat || $isSun;
    $isHoliday = in_array($dateYmd, $holidayDates, true);
    $noWork = $isHoliday || ($isWeekend && (($isSat && !$satWorking) || ($isSun && !$sunWorking)));
    $ganttDates[] = [
        'date' => $dateYmd,
        'ts' => $d,
        'weekend' => $isWeekend,
        'holiday' => $isHoliday,
        'no_work' => $noWork,
    ];
}

$isNoWorkDate = function($dateYmd) use ($holidayDates, $satWorking, $sunWorking) {
    $ts = strtotime($dateYmd);
    $dow = (int)date('N', $ts);
    $isSat = ($dow === 6);
    $isSun = ($dow === 7);
    $isWeekend = $isSat || $isSun;
    $isHoliday = in_array($dateYmd, $holidayDates, true);
    return $isHoliday || ($isWeekend && (($isSat && !$satWorking) || ($isSun && !$sunWorking)));
};
$workingDaysInRange = function($startYmd, $endYmd) use ($isNoWorkDate) {
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

$budgetStmt = $pdo->prepare("SELECT id, item_name FROM budget_items WHERE project_id = ? ORDER BY item_name");
$budgetStmt->execute([$id]);
$budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

// ─── Materials Monitoring (merged from materials_monitoring tab) ─────────────
$materials = [];
$withdrawalsByKey = [];
$matAllDates = [];
$withdrawalsTableExists = false;
$hasDupaRemarks = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_material_withdrawals'");
    $withdrawalsTableExists = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    $withdrawalsTableExists = false;
}
try {
    $colsDupa = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'remarks'");
    $hasDupaRemarks = $colsDupa && $colsDupa->rowCount() > 0;
} catch (Exception $e) {
    $hasDupaRemarks = false;
}
if ($withdrawalsTableExists) {
    $stmt = $pdo->prepare("SELECT * FROM project_dupa_items WHERE project_id = ? AND category = 'materials' ORDER BY sort_order, id");
    $stmt->execute([$id]);
    $materials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $stmt = $pdo->prepare("SELECT * FROM project_material_withdrawals WHERE project_id = ?");
    $stmt->execute([$id]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);
    $hasQuantityRequested = false;
    try {
        $colReq = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
        $hasQuantityRequested = $colReq && $colReq->rowCount() > 0;
    } catch (Exception $e) {}
    foreach ($withdrawals as $w) {
        $did = (int)$w['dupa_item_id'];
        $d = $w['withdrawal_date'];
        if (!isset($withdrawalsByKey[$did])) $withdrawalsByKey[$did] = [];
        $withdrawalsByKey[$did][$d] = [
            'quantity'  => (float)$w['quantity'],
            'quantity_requested' => $hasQuantityRequested && isset($w['quantity_requested']) && $w['quantity_requested'] !== null ? (float)$w['quantity_requested'] : null,
            'unit_cost' => $w['unit_cost'] !== null ? (float)$w['unit_cost'] : null,
            'remarks'   => isset($w['remarks']) ? (string)$w['remarks'] : '',
            'id'        => (int)$w['id'],
        ];
    }
    $matRangeStart = $project['notice_to_proceed'] ?? $project['start_date'];
    $matRangeEnd   = $project['target_end_date'];
    if (!$matRangeStart) $matRangeStart = date('Y-m-d', strtotime('-1 month'));
    if (!$matRangeEnd)   $matRangeEnd   = date('Y-m-d', strtotime('+3 months'));
    $matStartTs = strtotime($matRangeStart);
    $matEndTs   = strtotime($matRangeEnd);
    foreach ($withdrawals as $w) {
        $t = strtotime($w['withdrawal_date']);
        if ($t < $matStartTs) $matStartTs = $t;
        if ($t > $matEndTs)   $matEndTs   = $t;
    }
    for ($d = $matStartTs; $d <= $matEndTs; $d += 86400) {
        $matAllDates[] = date('Y-m-d', $d);
    }
}
$projectName = $project['title'] ?? '';
$materialSupplier = $project['material_supplier'] ?? '';
$materialPoNumber = $project['material_po_number'] ?? '';
$materialTermDays  = $project['material_term_days'] ?? '';
$materialAllotedAmount = $project['material_alloted_amount'] ?? '';
?>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Schedule vs Actual</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-3">
                <label class="text-muted small">Planned start</label>
                <div class="fw-medium"><?= formatDate($scheduleSummary['planned_start']) ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Planned end</label>
                <div class="fw-medium"><?= formatDate($scheduleSummary['planned_end']) ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Actual start</label>
                <div class="fw-medium"><?= formatDate($scheduleSummary['actual_start']) ?></div>
            </div>
            <div class="col-md-3">
                <label class="text-muted small">Actual end</label>
                <div class="fw-medium"><?= formatDate($scheduleSummary['actual_end']) ?></div>
            </div>
        </div>
        <p class="text-muted small mt-2 mb-0"><a href="?id=<?= $id ?>&tab=monitoring#timeline">View Timeline</a> below for task-level planned vs actual bars.</p>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
        <h6 class="mb-0 section-title">Progress &amp; Cash Flow (by date)</h6>
        <span class="text-muted small">Total planned quantity: <?= number_format($totalPlannedQty, 2) ?> &nbsp;|&nbsp; Budget: <?= formatCurrency($budget) ?></span>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Date</th>
                        <th class="text-end">% Daily accomplishment</th>
                        <th class="text-end">% Cumulative accomplishment</th>
                        <th class="text-end">Daily cash (planned)</th>
                        <th class="text-end">Daily cash flow (actual)</th>
                        <th class="text-end">Cumulative cash flow</th>
                        <?php if ($hasManpower): ?><th class="text-end">Manpower (actual)</th><?php endif; ?>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    $cumAmt = 0;
                    $cumQtyDel = 0;
                    foreach ($monitoringAllDates as $d):
                        $row = $dailyByDate[$d] ?? ['amt' => 0, 'qty_del' => 0, 'qty' => 0, 'man' => null];
                        $cumQtyDel += $row['qty_del'];
                        $cumAmt += $row['amt'];
                        $pctDaily = $totalPlannedQty > 0 ? ($row['qty_del'] / $totalPlannedQty) * 100 : 0;
                        $pctCum = $totalPlannedQty > 0 ? ($cumQtyDel / $totalPlannedQty) * 100 : 0;
                        $plannedDaily = $plannedDailyCash;
                    ?>
                    <tr>
                        <td><?= date('M j, Y', strtotime($d)) ?></td>
                        <td class="text-end"><?= number_format($pctDaily, 2) ?>%</td>
                        <td class="text-end"><?= number_format($pctCum, 2) ?>%</td>
                        <td class="text-end text-muted"><?= formatCurrency($plannedDaily) ?></td>
                        <td class="text-end"><?= formatCurrency($row['amt']) ?></td>
                        <td class="text-end"><?= formatCurrency($cumAmt) ?></td>
                        <?php if ($hasManpower): ?><td class="text-end"><?= isset($row['man']) && $row['man'] !== null ? number_format($row['man'], 1) : '—' ?></td><?php endif; ?>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php if (empty($monitoringAllDates)): ?>
        <div class="text-center text-muted py-4">No date range or logs yet. Set project start/end and add daily logs.</div>
        <?php endif; ?>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Planned vs actual (summary)</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="text-muted small">Planned daily cash flow</label>
                <div class="fw-medium"><?= formatCurrency($plannedDailyCash) ?> <span class="text-muted">/ day</span></div>
                <small class="text-muted">Budget <?= formatCurrency($budget) ?> over <?= (int)$plannedDays ?> days</small>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Actual total cash flow to date</label>
                <div class="fw-medium"><?= formatCurrency($cumAmt) ?></div>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Cumulative accomplishment</label>
                <div class="fw-medium"><?= number_format($totalPlannedQty > 0 ? ($cumQtyDel / $totalPlannedQty) * 100 : 0, 1) ?>%</div>
                <small class="text-muted"><?= number_format($cumQtyDel, 2) ?> / <?= number_format($totalPlannedQty, 2) ?> planned</small>
            </div>
        </div>
    </div>
</div>

<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Manpower: planned vs actual</h6>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <div class="col-md-4">
                <label class="text-muted small">Planned manpower (total)</label>
                <div class="fw-medium"><?= $plannedManpowerTotal > 0 ? number_format($plannedManpowerTotal, 1) : '—' ?></div>
                <small class="text-muted">From timeline tasks (planned_manpower). Edit timeline below to set.</small>
                <?php if ($plannedManpowerFromResourcePlan !== null && $plannedManpowerFromResourcePlan > 0): ?>
                <div class="mt-1 small">
                    <span class="text-muted">From Planning task resources:</span> <strong><?= number_format($plannedManpowerFromResourcePlan, 1) ?></strong>
                    <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=planning" class="ms-1">Edit</a>
                </div>
                <?php endif; ?>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Actual manpower (total to date)</label>
                <?php
                $actualManTotal = 0;
                foreach ($dailyRows as $r) {
                    if (isset($r['man']) && $r['man'] !== null) $actualManTotal += (float)$r['man'];
                }
                ?>
                <div class="fw-medium"><?= $actualManTotal > 0 ? number_format($actualManTotal, 1) : '—' ?></div>
                <small class="text-muted">From daily logs (manpower). Add in calendar/SOW entries.</small>
            </div>
            <div class="col-md-4">
                <label class="text-muted small">Comparison</label>
                <div class="fw-medium">
                    <?php
                    $plannedForCompare = $plannedManpowerFromResourcePlan > 0 ? $plannedManpowerFromResourcePlan : $plannedManpowerTotal;
                    if ($plannedForCompare > 0 && $actualManTotal > 0) {
                        $pct = ($actualManTotal / $plannedForCompare) * 100;
                        echo number_format($pct, 0) . '% of planned used';
                        if ($pct > 100) echo ' <span class="text-warning">(over)</span>';
                        elseif ($pct < 100) echo ' <span class="text-info">(under)</span>';
                    } else {
                        echo '—';
                    }
                    ?>
                </div>
            </div>
        </div>
        <p class="text-muted small mt-2 mb-0">Run the migration <code>migration_holidays_monitoring.sql</code> to add <code>manpower</code> to action logs and <code>planned_manpower</code> to timeline tasks. Add materials/equipment/people to tasks in <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=planning">Planning</a> to compare planned vs actual resources here.</p>
    </div>
</div>

<!-- Timeline (merged from timeline tab) -->
<div id="timeline" class="timeline-wrap">
    <div class="card shadow-sm mb-4 timeline-printable">
        <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2 no-print">
            <div class="d-flex align-items-center flex-wrap gap-3">
                <h6 class="mb-0 section-title">Scope of Work – Work breakdown (Timeline)</h6>
                <div class="timeline-legend">
                    <span><span class="legend-bar planned"></span> Planned</span>
                    <span><span class="legend-bar actual"></span> Actual</span>
                    <span><span class="legend-bar actual-alt"></span> Actual (2nd segment)</span>
                    <span class="ms-2 border-start ps-2"><span class="legend-today"></span> Today</span>
                    <span class="ms-2 border-start ps-2"><span class="legend-no-work"></span> No work (holiday/weekend)</span>
                </div>
            </div>
            <div class="d-flex gap-2 no-print">
                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="window.print();" title="Print or save as PDF">
                    <i class="bi bi-printer me-1"></i> Print / PDF
                </button>
                <form action="<?= BASE_URL ?>/actions/timeline_actions.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="sync_sow">
                    <input type="hidden" name="project_id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-outline-secondary btn-sm" title="Add any new Scope of Work items to the timeline">
                        <i class="bi bi-arrow-repeat me-1"></i> Refresh from SOW
                    </button>
                </form>
                <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTimelineTaskModal">
                    <i class="bi bi-plus-lg me-1"></i> Add Task
                </button>
            </div>
        </div>
        <div class="card-body">
            <p class="print-only-title mb-0">SCOPE OF WORK – Work breakdown</p>
            <p class="text-muted small mb-2 no-print">
                Planned dates and duration are set in <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=planning">Planning / Design</a>. Actual dates come from daily logs (this Monitoring tab).
            </p>
            <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 mb-3 no-print">
                <span class="text-muted small">Customize no-work days:</span>
                <a href="<?= BASE_URL ?>/pages/settings/holidays.php" class="btn btn-outline-secondary btn-sm" title="Add or remove holidays">
                    <i class="bi bi-calendar-x me-1"></i> Holidays
                </a>
                <a href="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= (int)$id ?>#working-days" class="btn btn-outline-secondary btn-sm" title="Set Saturday/Sunday as working or no-work for this project">
                    <i class="bi bi-calendar-week me-1"></i> Weekend (Sat/Sun)
                </a>
            </div>

            <?php if ($timelineTableMissing): ?>
            <div class="alert alert-warning mb-3">
                <strong>Timeline table not found.</strong> Run <code>database/migration_timeline.sql</code> or create <code>project_timeline_tasks</code> table.
            </div>
            <?php endif; ?>

            <?php if (empty($timelineTasks)): ?>
                <div class="text-center text-muted py-5 rounded-3" style="background: #f8fafc;">
                    <i class="bi bi-bar-chart-steps fs-1 d-block mb-2 opacity-75"></i>
                    <p class="mb-2">No timeline tasks yet.</p>
                    <p class="small mb-3">Add items under <strong>Scope of Work</strong> first; they will appear here automatically. Or add a custom task below.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTimelineTaskModal">
                        <i class="bi bi-plus-lg me-1"></i> Add custom task
                    </button>
                </div>
            <?php else: ?>
                <div class="timeline-project-title-bar mb-3">
                    <span class="timeline-project-title-label">Project</span>
                    <h5 class="timeline-project-title-text mb-0"><?= sanitize($project['title']) ?></h5>
                </div>
                <div class="timeline-summary-cards mb-3">
                    <div class="card border-primary border-2">
                        <div class="card-body py-2 px-3">
                            <div class="small text-uppercase text-primary" style="font-size:0.65rem; font-weight:600">Duration</div>
                            <div class="fw-bold fs-5 text-primary"><?= $totalDays ?> days</div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body py-2 px-3">
                            <div class="small text-muted text-uppercase" style="font-size:0.65rem; font-weight:600">Start</div>
                            <div class="fw-bold"><?= formatDate($timelineRangeStart) ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body py-2 px-3">
                            <div class="small text-muted text-uppercase" style="font-size:0.65rem; font-weight:600">End</div>
                            <div class="fw-bold"><?= formatDate($timelineRangeEnd) ?></div>
                        </div>
                    </div>
                </div>

                <?php
                $timelineLeftColsWidth = 52 + 320 + 80 + 100 + 172 + 172;
                $timelineDayWidth = 52;
                $timelineDayWidthNoWork = 64;
                $timelineTableMinWidth = $timelineLeftColsWidth;
                foreach ($ganttDates as $gd) {
                    $timelineTableMinWidth += !empty($gd['no_work']) ? $timelineDayWidthNoWork : $timelineDayWidth;
                }
                ?>
                <p class="timeline-gantt-scroll-hint no-print">
                    <i class="bi bi-arrow-left-right"></i>
                    <span>Scroll horizontally to view all dates</span>
                </p>
                <div class="timeline-gantt-scroll-wrapper">
                    <div class="timeline-gantt-scroll">
                        <table class="table table-bordered table-sm timeline-spreadsheet timeline-gantt-table mb-0" style="min-width: <?= $timelineTableMinWidth ?>px; width: <?= $timelineTableMinWidth ?>px;">
                        <thead>
                            <tr class="table-light timeline-thead-row">
                                <th class="col-id gantt-task-cell">ID</th>
                                <th class="col-desc gantt-task-cell">Task Description</th>
                                <th class="col-status">STATUS</th>
                                <th class="col-duration">Duration</th>
                                <th class="col-start">START</th>
                                <th class="col-end">END</th>
                                <?php foreach ($ganttDates as $gd): ?>
                                <th class="date-header-cell <?= $gd['weekend'] ? 'weekend' : '' ?> <?= $gd['no_work'] ? 'no-work' : '' ?>" title="<?= date('l, j F Y', $gd['ts']) ?><?= $gd['holiday'] ? ' (Holiday)' : ($gd['no_work'] ? ' (No work)' : '') ?>"><span class="d-block" style="font-size:0.65rem"><?= date('D', $gd['ts']) ?></span><?= date('j', $gd['ts']) ?><br><span class="opacity-75" style="font-size:0.6rem"><?= date('M', $gd['ts']) ?></span><?= $gd['no_work'] ? '<br><span class="badge bg-secondary" style="font-size:0.5rem">No work</span>' : '' ?></th>
                                <?php endforeach; ?>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($timelineTasks as $idx => $task):
                                $taskNum = $idx + 1;
                                $plannedStart = $task['planned_start_date'];
                                $plannedEnd = $task['planned_end_date'];
                                $plannedStart = date('Y-m-d', strtotime($plannedStart));
                                $plannedEnd = date('Y-m-d', strtotime($plannedEnd));
                                $plannedRange = $workingDaysInRange($plannedStart, $plannedEnd);
                                $plannedDays = max(1, $plannedRange['count']);
                                $plannedFirstWork = $plannedRange['first'];
                                $plannedLastWork = $plannedRange['last'];
                                $segments = $actualSegments[$task['id']] ?? [];
                                $summary = $actualsSummary[$task['id']] ?? null;
                                $segmentFirstLast = [];
                                foreach ($segments as $si => $seg) {
                                    $segStart = date('Y-m-d', strtotime($seg['start']));
                                    $segEnd = date('Y-m-d', strtotime($seg['end']));
                                    $segmentFirstLast[$si] = $workingDaysInRange($segStart, $segEnd);
                                }
                                $actualWorkingDays = 0;
                                foreach ($segmentFirstLast as $fl) {
                                    $actualWorkingDays += $fl['count'];
                                }
                                $todayYmd = date('Y-m-d');
                            ?>
                            <tr class="planned-row">
                                <td class="col-id text-center task-desc-cell" rowspan="2"><?= $taskNum ?></td>
                                <td class="col-desc task-desc-cell timeline-task-desc-cell" rowspan="2" title="<?= htmlspecialchars($task['task_label']) ?>">
                                    <strong><?= sanitize($task['task_label']) ?></strong>
                                </td>
                                <td class="col-status text-muted">Planned</td>
                                <td class="col-duration text-center align-middle"><?= $plannedDays ?> working days</td>
                                <td class="col-start align-middle"><?= formatDate($plannedStart) ?></td>
                                <td class="col-end align-middle"><?= formatDate($plannedEnd) ?></td>
                                <?php foreach ($ganttDates as $gd):
                                    $dayYmd = $gd['date'];
                                    $inPlanned = ($dayYmd >= $plannedStart && $dayYmd <= $plannedEnd) && !$gd['no_work'];
                                    $isFirst = $inPlanned && $plannedFirstWork !== null && ($dayYmd === $plannedFirstWork);
                                    $isLast = $inPlanned && $plannedLastWork !== null && ($dayYmd === $plannedLastWork);
                                    $isToday = ($dayYmd === $todayYmd);
                                    $barStyle = $inPlanned ? ' background:#1e3a5f !important; border-color:#0f172a;' : '';
                                ?>
                                <td class="gantt-day-cell planned-day-cell <?= $gd['weekend'] ? 'weekend' : '' ?> <?= $gd['no_work'] ? 'no-work' : '' ?> <?= $inPlanned ? 'bar-planned' : '' ?> <?= $isFirst ? 'bar-start' : '' ?> <?= $isLast ? 'bar-end' : '' ?> <?= $isToday ? 'today' : '' ?>" style="<?= $barStyle ?>" title="<?= $inPlanned ? 'Planned (' . $plannedDays . ' working days): ' . formatDate($plannedStart) . ' – ' . formatDate($plannedEnd) : ($gd['no_work'] ? 'No work' : '') ?>"></td>
                                <?php endforeach; ?>
                            </tr>
                            <tr class="actual-row">
                                <td class="col-status text-primary">Actual</td>
                                <td class="col-duration text-center"><?= $summary ? $actualWorkingDays . ' working days' : (empty($task['budget_item_id']) ? '—<br><small class="text-muted no-print-inline">Link to SOW for actual from calendar</small>' : '—') ?></td>
                                <td class="col-start"><?= $summary ? formatDate($summary['start']) : '—' ?></td>
                                <td class="col-end"><?= $summary ? formatDate($summary['end']) : '—' ?></td>
                                <?php
                                foreach ($ganttDates as $gd) {
                                    $dayYmd = $gd['date'];
                                    $segmentIndex = null;
                                    foreach ($segments as $si => $seg) {
                                        $segStart = date('Y-m-d', strtotime($seg['start']));
                                        $segEnd = date('Y-m-d', strtotime($seg['end']));
                                        if ($dayYmd >= $segStart && $dayYmd <= $segEnd) {
                                            $segmentIndex = $si;
                                            break;
                                        }
                                    }
                                    $inActual = ($segmentIndex !== null) && !$gd['no_work'];
                                    $seg = ($segmentIndex !== null) ? $segments[$segmentIndex] : null;
                                    $fl = $segmentFirstLast[$segmentIndex] ?? null;
                                    $isFirst = $inActual && $fl && $fl['first'] !== null && ($dayYmd === $fl['first']);
                                    $isLast = $inActual && $fl && $fl['last'] !== null && ($dayYmd === $fl['last']);
                                    $isToday = ($dayYmd === $todayYmd);
                                    $barClass = ($segmentIndex !== null && $segmentIndex % 2 === 1) ? 'bar-actual-alt' : 'bar-actual';
                                    $barStyle = '';
                                    if ($inActual) {
                                        $barStyle = ($segmentIndex % 2 === 1) ? ' background:#0369a1 !important; border-color:#075985;' : ' background:#047857 !important; border-color:#065f46;';
                                    }
                                ?>
                                <td class="gantt-day-cell actual-day-cell <?= $gd['weekend'] ? 'weekend' : '' ?> <?= $gd['no_work'] ? 'no-work' : '' ?> <?= $inActual ? $barClass : '' ?> <?= $isFirst ? 'bar-start' : '' ?> <?= $isLast ? 'bar-end' : '' ?> <?= $isToday ? 'today' : '' ?>" style="<?= $barStyle ?>" title="<?= $inActual ? 'Actual: ' . formatDate($seg['start']) . ' – ' . formatDate($seg['end']) : ($gd['no_work'] ? 'No work' : '') ?>"></td>
                                <?php } ?>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                    </div>
                </div>
            <?php endif; ?>
        </div>
    </div>
</div>

<!-- Add Timeline Task Modal -->
<div class="modal fade" id="addTimelineTaskModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/timeline_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Add Timeline Task</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Task description <span class="text-danger">*</span></label>
                        <input type="text" name="task_label" class="form-control" required placeholder="e.g. CONCRETE WORKS">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Link to Scope of Work (optional)</label>
                        <select name="budget_item_id" class="form-select">
                            <option value="">— Custom task (actual not from logs) —</option>
                            <?php foreach ($budgetItems as $bi): ?>
                            <option value="<?= (int)$bi['id'] ?>"><?= sanitize($bi['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text">When linked, actual dates come from daily logs (calendar) for this SOW item.</div>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Planned start <span class="text-danger">*</span></label>
                            <input type="date" name="planned_start_date" class="form-control" required>
                        </div>
                        <div class="col-6">
                            <label class="form-label">Planned end <span class="text-danger">*</span></label>
                            <input type="date" name="planned_end_date" class="form-control" required>
                        </div>
                    </div>
                    <div class="mb-3 mt-2">
                        <label class="form-label">Planned manpower (person-days)</label>
                        <input type="number" name="planned_manpower" class="form-control" min="0" step="0.5" placeholder="e.g. 10">
                        <div class="form-text">Used in Monitoring for planned vs actual comparison.</div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add Task</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
(function() {
    var timelineProjectId = <?= (int)$id ?>;
    var scrollStorageKey = 'timelineScroll:project_' + timelineProjectId;
    function saveTimelineScrollPosition() {
        try {
            var wrapper = document.querySelector('.timeline-gantt-scroll-wrapper');
            var inner = document.querySelector('.timeline-gantt-scroll');
            var payload = {
                winX: window.scrollX || window.pageXOffset || 0,
                winY: window.scrollY || window.pageYOffset || 0,
                wrapX: wrapper ? wrapper.scrollLeft : 0,
                wrapY: wrapper ? wrapper.scrollTop : 0,
                innerX: inner ? inner.scrollLeft : 0,
                innerY: inner ? inner.scrollTop : 0,
                ts: Date.now()
            };
            if (window.sessionStorage) window.sessionStorage.setItem(scrollStorageKey, JSON.stringify(payload));
        } catch (e) { }
    }
    function restoreTimelineScrollPosition() {
        try {
            if (!window.sessionStorage) return;
            var raw = window.sessionStorage.getItem(scrollStorageKey);
            if (!raw) return;
            var data = null;
            try { data = JSON.parse(raw); } catch (e) { data = null; }
            window.sessionStorage.removeItem(scrollStorageKey);
            if (!data) return;
            var wrapper = document.querySelector('.timeline-gantt-scroll-wrapper');
            var inner = document.querySelector('.timeline-gantt-scroll');
            if (typeof data.winX === 'number' || typeof data.winY === 'number') {
                window.scrollTo(typeof data.winX === 'number' ? data.winX : 0, typeof data.winY === 'number' ? data.winY : 0);
            }
            if (wrapper) { if (typeof data.wrapX === 'number') wrapper.scrollLeft = data.wrapX; if (typeof data.wrapY === 'number') wrapper.scrollTop = data.wrapY; }
            if (inner) { if (typeof data.innerX === 'number') inner.scrollLeft = data.innerX; if (typeof data.innerY === 'number') inner.scrollTop = data.innerY; }
        } catch (e) { }
    }
    window.addEventListener('beforeunload', saveTimelineScrollPosition);
    if (document.readyState === 'complete') restoreTimelineScrollPosition();
    else window.addEventListener('load', restoreTimelineScrollPosition);
})();
</script>

<!-- Materials Monitoring (merged from materials_monitoring tab) -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Materials monitoring</h6>
        <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#materialLogHeaderModal">
            <i class="bi bi-pencil me-1"></i> Edit header
        </button>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-6"><label class="text-muted small">Project name</label><div class="fw-medium"><?= sanitize($projectName) ?></div></div>
            <div class="col-md-6"><label class="text-muted small">Supplier</label><div class="fw-medium"><?= $materialSupplier !== '' ? sanitize($materialSupplier) : '—' ?></div></div>
            <div class="col-md-6"><label class="text-muted small">Alloted amount</label><div class="fw-medium"><?= $materialAllotedAmount !== '' && $materialAllotedAmount !== null ? formatCurrency($materialAllotedAmount) : '—' ?></div></div>
            <div class="col-md-6"><label class="text-muted small">P.O number</label><div class="fw-medium"><?= $materialPoNumber !== '' ? sanitize($materialPoNumber) : '—' ?></div></div>
            <div class="col-md-6"><label class="text-muted small">Term days</label><div class="fw-medium"><?= $materialTermDays !== '' && $materialTermDays !== null ? (int)$materialTermDays : '—' ?></div></div>
        </div>
        <?php if (!$withdrawalsTableExists): ?>
            <div class="alert alert-warning mb-0">Run <code>database/migration_material_withdrawals.sql</code> to enable materials withdrawal tracking.</div>
        <?php elseif (empty($materials)): ?>
            <div class="alert alert-info mb-0">No materials defined. Add materials in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=dupa">DUPA</a> tab (Materials) first.</div>
        <?php else: ?>
            <div class="table-responsive">
                <table class="table table-bordered table-hover align-middle mb-0 materials-monitoring-table">
                    <thead class="table-light">
                        <tr>
                            <th style="width: 3%;">No.</th>
                            <th style="min-width: 180px;">Description</th>
                            <th class="text-end" style="width: 6%;">Qty</th>
                            <th style="width: 8%;">Units</th>
                            <th class="text-end" style="width: 8%;">Unit cost</th>
                            <th class="text-end" style="width: 9%;">Amount</th>
                            <?php foreach ($matAllDates as $d): ?>
                                <th class="text-end delivery-col" style="min-width: 90px;" title="<?= formatDate($d) ?> — Req / Del"><?= date('d-M-y', strtotime($d)) ?><br><span class="small fw-normal text-muted">Req / Del</span></th>
                            <?php endforeach; ?>
                            <th class="text-end" style="width: 8%;">Balance</th>
                            <?php if ($hasDupaRemarks): ?><th style="min-width: 120px;">Remarks</th><?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($materials as $idx => $mat):
                            $dupaId   = (int)$mat['id'];
                            $qty      = (float)$mat['quantity'];
                            $rate     = (float)($mat['rate'] ?? 0);
                            $amt      = (float)($mat['amount'] ?? $qty * $rate);
                            $unit     = $mat['unit'] ?? 'pcs';
                            $withdrawn = 0;
                            if (isset($withdrawalsByKey[$dupaId])) {
                                foreach ($withdrawalsByKey[$dupaId] as $w) { $withdrawn += (float)$w['quantity']; }
                            }
                            $balance = $qty - $withdrawn;
                            $rowRemarks = $hasDupaRemarks && isset($mat['remarks']) ? $mat['remarks'] : '';
                        ?>
                            <tr>
                                <td class="text-muted"><?= $idx + 1 ?></td>
                                <td class="fw-medium"><?= sanitize($mat['description']) ?></td>
                                <td class="text-end"><?= number_format($qty, 2) ?></td>
                                <td><?= sanitize($unit) ?></td>
                                <td class="text-end"><?= formatCurrency($rate) ?></td>
                                <td class="text-end"><?= formatCurrency($amt) ?></td>
                                <?php foreach ($matAllDates as $d): ?>
                                    <?php
                                    $cell = isset($withdrawalsByKey[$dupaId][$d]) ? $withdrawalsByKey[$dupaId][$d] : null;
                                    $cellQty = $cell ? $cell['quantity'] : '';
                                    $cellReq = $cell && isset($cell['quantity_requested']) && $cell['quantity_requested'] !== null ? $cell['quantity_requested'] : '';
                                    $cellId  = $cell ? $cell['id'] : '';
                                    $cellUnitCost = $cell && $cell['unit_cost'] !== null ? $cell['unit_cost'] : '';
                                    $cellRemarks = $cell && !empty($cell['remarks']) ? $cell['remarks'] : '';
                                    $hasCell = $cell && ((string)$cellQty !== '' && $cellQty != 0 || (string)$cellReq !== '' && $cellReq != 0);
                                    ?>
                                    <td class="text-end delivery-cell">
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none material-withdrawal-btn <?= $hasCell ? '' : 'text-muted' ?>" data-bs-toggle="modal" data-bs-target="#withdrawalModal" data-dupa-id="<?= $dupaId ?>" data-date="<?= $d ?>" data-quantity="<?= $cellQty !== '' ? $cellQty : '' ?>" data-quantity-requested="<?= $cellReq !== '' ? $cellReq : '' ?>" data-unit-cost="<?= $cellUnitCost ?>" data-remarks="<?= sanitize($cellRemarks) ?>" data-withdrawal-id="<?= $cellId ?>" data-description="<?= sanitize($mat['description']) ?>" title="<?= $cellRemarks !== '' ? sanitize($cellRemarks) : 'Requested / Delivered' ?>">
                                            <?php if ($hasCell): ?>
                                                <?php if ($cellReq !== '' && $cellReq != 0 && ($cellQty === '' || $cellQty == 0)): ?>
                                                    <span class="text-primary"><?= number_format($cellReq, 2) ?></span> <span class="text-muted">/ —</span>
                                                <?php elseif ($cellReq !== '' && $cellReq != 0): ?>
                                                    <span class="text-primary"><?= number_format($cellReq, 2) ?></span> / <?= number_format($cellQty, 2) ?>
                                                <?php else: ?>
                                                    — / <?= number_format($cellQty, 2) ?>
                                                <?php endif; ?>
                                            <?php else: ?>
                                                —
                                            <?php endif; ?>
                                        </button>
                                    </td>
                                <?php endforeach; ?>
                                <td class="text-end fw-medium"><?= number_format($balance, 2) ?></td>
                                <?php if ($hasDupaRemarks): ?>
                                    <td>
                                        <form action="<?= BASE_URL ?>/actions/material_actions.php" method="POST" class="d-inline-block material-remarks-form">
                                            <input type="hidden" name="action" value="update_material_remarks">
                                            <input type="hidden" name="project_id" value="<?= $id ?>">
                                            <input type="hidden" name="dupa_item_id" value="<?= $dupaId ?>">
                                            <div class="input-group input-group-sm">
                                                <input type="text" class="form-control form-control-sm" name="remarks" value="<?= sanitize($rowRemarks) ?>" placeholder="Remarks" style="min-width: 100px;">
                                                <button type="submit" class="btn btn-outline-secondary btn-sm" title="Save">✓</button>
                                            </div>
                                        </form>
                                    </td>
                                <?php endif; ?>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Material log header modal -->
<div class="modal fade" id="materialLogHeaderModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/material_actions.php" method="POST">
                <input type="hidden" name="action" value="update_log_header">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Edit log header</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Supplier</label>
                        <input type="text" class="form-control" name="material_supplier" value="<?= sanitize($materialSupplier) ?>" placeholder="Supplier name">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Alloted amount (₱)</label>
                        <input type="number" class="form-control" name="material_alloted_amount" step="0.01" min="0" value="<?= $materialAllotedAmount !== '' && $materialAllotedAmount !== null ? (float)$materialAllotedAmount : '' ?>" placeholder="0.00">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">P.O number</label>
                        <input type="text" class="form-control" name="material_po_number" value="<?= sanitize($materialPoNumber) ?>" placeholder="P.O number">
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Term days</label>
                        <input type="number" class="form-control" name="material_term_days" min="0" value="<?= $materialTermDays !== '' && $materialTermDays !== null ? (int)$materialTermDays : '' ?>" placeholder="Days">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Withdrawal modal -->
<div class="modal fade" id="withdrawalModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/material_actions.php" method="POST">
                <input type="hidden" name="action" value="save_withdrawal">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="dupa_item_id" id="withdrawalDupaId" value="">
                <div class="modal-header">
                    <h6 class="modal-title" id="withdrawalModalTitle">Withdrawal</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <p class="small text-muted mb-2" id="withdrawalMaterialDesc"></p>
                    <div class="mb-2">
                        <label class="form-label small">Date</label>
                        <input type="date" class="form-control form-control-sm" id="withdrawalDateInput" name="withdrawal_date" required>
                    </div>
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label class="form-label small">Requested</label>
                            <input type="number" class="form-control form-control-sm" name="quantity_requested" id="withdrawalQuantityRequested" step="0.01" min="0" value="" placeholder="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label small">Delivered</label>
                            <input type="number" class="form-control form-control-sm" name="quantity" id="withdrawalQuantity" step="0.01" min="0" value="0" placeholder="0">
                        </div>
                    </div>
                    <div class="mb-2">
                        <label class="form-label small">Unit cost (₱) <span class="text-muted">optional</span></label>
                        <input type="number" class="form-control form-control-sm" name="unit_cost" id="withdrawalUnitCost" step="0.01" min="0" value="" placeholder="Leave blank to use material rate">
                    </div>
                    <div class="mb-0">
                        <label class="form-label small">Notes / Remarks</label>
                        <input type="text" class="form-control form-control-sm" name="remarks" id="withdrawalRemarks" placeholder="Optional">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var modal = document.getElementById('withdrawalModal');
    if (modal) {
        modal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            if (!btn || !btn.classList.contains('material-withdrawal-btn')) return;
            var dupaId = btn.getAttribute('data-dupa-id') || '';
            var date = btn.getAttribute('data-date') || '';
            var qty = btn.getAttribute('data-quantity') || '0';
            var qtyReq = btn.getAttribute('data-quantity-requested') || '';
            var unitCost = btn.getAttribute('data-unit-cost') || '';
            var remarks = btn.getAttribute('data-remarks') || '';
            var desc = btn.getAttribute('data-description') || '';
            document.getElementById('withdrawalDupaId').value = dupaId;
            document.getElementById('withdrawalDateInput').value = date;
            document.getElementById('withdrawalQuantity').value = qty;
            document.getElementById('withdrawalQuantityRequested').value = qtyReq;
            document.getElementById('withdrawalUnitCost').value = unitCost;
            document.getElementById('withdrawalRemarks').value = remarks;
            document.getElementById('withdrawalMaterialDesc').textContent = desc;
            document.getElementById('withdrawalModalTitle').textContent = 'Materials — Requested / Delivered — ' + (date || '');
        });
    }
});
</script>

<?php
// Tab: Timeline — Scope of Work is automatically encoded (task list and names from SOW).
// Planned = manual; Actual = from daily logs (calendar/action_logs).

// Ensure timeline table exists (create if missing, e.g. migration not run yet)
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
    } catch (Exception $e) {
        // If CREATE fails (e.g. no FK permission), show message below
    }
    $chk = $pdo->query("SHOW TABLES LIKE 'project_timeline_tasks'");
    $tableExists = $chk && $chk->rowCount() > 0;
}

$timelineTasks = [];
$timelineTableMissing = !$tableExists;
if ($tableExists) {
    // Automatically encode Scope of Work: ensure every budget item has a timeline task (data from SOW)
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
    // Keep task_label in sync with SOW item name for existing linked tasks
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

// Actual data from daily logs (action_logs): one or more segments per task when linked to SOW
$actualSegments = []; // task_id => [ ['start'=>'', 'end'=>'', 'days'=>n], ... ]
$actualsSummary = []; // task_id => ['start'=>'', 'end'=>'', 'days'=>n] for display (first to last)
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
        } catch (Exception $e) {
            // columns may not exist if migrations not run
        }
    }
}

$rangeStart = $project['start_date'] ?: null;
$rangeEnd = $project['target_end_date'] ?: null;
foreach ($timelineTasks as $t) {
    if ($t['planned_start_date']) {
        if ($rangeStart === null || strtotime($t['planned_start_date']) < strtotime($rangeStart)) $rangeStart = $t['planned_start_date'];
    }
    if ($t['planned_end_date']) {
        if ($rangeEnd === null || strtotime($t['planned_end_date']) > strtotime($rangeEnd)) $rangeEnd = $t['planned_end_date'];
    }
    foreach ($actualSegments[$t['id']] ?? [] as $seg) {
        if (strtotime($seg['start']) < strtotime($rangeStart)) $rangeStart = $seg['start'];
        if (strtotime($seg['end']) > strtotime($rangeEnd)) $rangeEnd = $seg['end'];
    }
}
if (!$rangeStart) $rangeStart = date('Y-m-d');
if (!$rangeEnd) $rangeEnd = date('Y-m-d', strtotime('+1 month'));

$rangeStartTs = strtotime($rangeStart);
$rangeEndTs = strtotime($rangeEnd);
$totalDays = max(1, (int)(($rangeEndTs - $rangeStartTs) / 86400) + 1);

$holidayDates = [];
try {
    $holidayRows = $pdo->query("SELECT holiday_date FROM holidays ORDER BY holiday_date")->fetchAll(PDO::FETCH_COLUMN);
    $holidayDates = array_map(function($d) { return date('Y-m-d', strtotime($d)); }, $holidayRows);
} catch (Exception $e) { }

$satWorking = !empty($project['saturday_working']);
$sunWorking = !empty($project['sunday_working']);

$ganttDates = [];
for ($d = $rangeStartTs; $d <= $rangeEndTs; $d += 86400) {
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

// Helper: is this date a no-work day (holiday or non-working weekend)?
$isNoWorkDate = function($dateYmd) use ($holidayDates, $satWorking, $sunWorking) {
    $ts = strtotime($dateYmd);
    $dow = (int)date('N', $ts);
    $isSat = ($dow === 6);
    $isSun = ($dow === 7);
    $isWeekend = $isSat || $isSun;
    $isHoliday = in_array($dateYmd, $holidayDates, true);
    return $isHoliday || ($isWeekend && (($isSat && !$satWorking) || ($isSun && !$sunWorking)));
};
// Count working days and first/last working day in a date range
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
?>

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
                Planned dates and duration are set in <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=planning">Planning / Design</a>. Actual dates come from daily logs (Monitoring).
            </p>
            <div class="d-flex flex-wrap align-items-center gap-2 gap-md-3 mb-3 no-print">
                <span class="text-muted small">Customize no-work days:</span>
                <a href="<?= BASE_URL ?>/pages/settings/holidays.php" class="btn btn-outline-secondary btn-sm" title="Add or remove holidays (no-work dates for all projects)">
                    <i class="bi bi-calendar-x me-1"></i> Holidays
                </a>
                <a href="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= (int)$id ?>#working-days" class="btn btn-outline-secondary btn-sm" title="Set Saturday/Sunday as working or no-work for this project">
                    <i class="bi bi-calendar-week me-1"></i> Weekend (Sat/Sun)
                </a>
            </div>

            <?php if ($timelineTableMissing): ?>
            <div class="alert alert-warning mb-3">
                <strong>Timeline table not found.</strong> Run this SQL in your database to create it:
                <pre class="mb-0 mt-2 small bg-light p-2 rounded"><code>CREATE TABLE IF NOT EXISTS project_timeline_tasks (
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
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;</code></pre>
                Or run the file <code>database/migration_timeline.sql</code> from phpMyAdmin or MySQL.
            </div>
            <?php endif; ?>

            <?php if (empty($timelineTasks)): ?>
                <div class="text-center text-muted py-5 rounded-3" style="background: #f8fafc;">
                    <i class="bi bi-bar-chart-steps fs-1 d-block mb-2 opacity-75"></i>
                    <p class="mb-2">No timeline tasks yet.</p>
                    <p class="small mb-3">Add items under <strong>Scope of Work</strong> first; they will appear here automatically when you open the Timeline. Or add a custom task below.</p>
                    <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addTimelineTaskModal">
                        <i class="bi bi-plus-lg me-1"></i> Add custom task
                    </button>
                </div>
            <?php else: ?>

                <!-- Project title: full-width block so long names display properly -->
                <div class="timeline-project-title-bar mb-3">
                    <span class="timeline-project-title-label">Project</span>
                    <h5 class="timeline-project-title-text mb-0"><?= sanitize($project['title']) ?></h5>
                </div>

                <!-- Summary strip -->
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
                            <div class="fw-bold"><?= formatDate($rangeStart) ?></div>
                        </div>
                    </div>
                    <div class="card">
                        <div class="card-body py-2 px-3">
                            <div class="small text-muted text-uppercase" style="font-size:0.65rem; font-weight:600">End</div>
                            <div class="fw-bold"><?= formatDate($rangeEnd) ?></div>
                        </div>
                    </div>
                </div>

                <!-- Gantt chart: table starts with column headers only (no project row) -->
                <?php
                $timelineLeftColsWidth = 52 + 320 + 80 + 100 + 172 + 172; /* id, desc, status, duration, start, end */
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
                                <td class="col-duration text-center align-middle">
                                    <?= $plannedDays ?> working days
                                </td>
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

<!-- Add Task Modal -->
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
                        <div class="form-text">Used in Monitoring tab for planned vs actual comparison.</div>
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
    // Scroll preservation helpers: keep timeline scroll position after save/redirect/refresh
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
            if (window.sessionStorage) {
                window.sessionStorage.setItem(scrollStorageKey, JSON.stringify(payload));
            }
        } catch (e) {
            // Ignore storage errors; normal behavior will just scroll to top
        }
    }

    function restoreTimelineScrollPosition() {
        try {
            if (!window.sessionStorage) return;
            var raw = window.sessionStorage.getItem(scrollStorageKey);
            if (!raw) return;
            var data = null;
            try {
                data = JSON.parse(raw);
            } catch (e) {
                data = null;
            }
            window.sessionStorage.removeItem(scrollStorageKey);
            if (!data) return;

            var wrapper = document.querySelector('.timeline-gantt-scroll-wrapper');
            var inner = document.querySelector('.timeline-gantt-scroll');

            // Restore window scroll (vertical page position)
            if (typeof data.winX === 'number' || typeof data.winY === 'number') {
                window.scrollTo(
                    typeof data.winX === 'number' ? data.winX : 0,
                    typeof data.winY === 'number' ? data.winY : 0
                );
            }
            // Restore horizontal/vertical scroll inside the timeline wrapper if it exists
            if (wrapper) {
                if (typeof data.wrapX === 'number') wrapper.scrollLeft = data.wrapX;
                if (typeof data.wrapY === 'number') wrapper.scrollTop = data.wrapY;
            }
            if (inner) {
                if (typeof data.innerX === 'number') inner.scrollLeft = data.innerX;
                if (typeof data.innerY === 'number') inner.scrollTop = data.innerY;
            }
        } catch (e) {
            // Fail silently if anything goes wrong with reading/setting scroll
        }
    }

    // Save scroll for any kind of navigation/refresh from this page
    window.addEventListener('beforeunload', saveTimelineScrollPosition);

    // Restore after full load so layout is stable
    if (document.readyState === 'complete') {
        restoreTimelineScrollPosition();
    } else {
        window.addEventListener('load', restoreTimelineScrollPosition);
    }
})();
</script>

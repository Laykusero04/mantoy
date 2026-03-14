<?php
// Renders sow_tasks rows under a SOW. Expects: $sowTaskChildren, $sowTasksByParent, $sowTaskComputedDuration, $id, $ganttDays, $planningSowIndex, $planningBaselineTs, $planningTotalDays, $planningBaseline.
// Optional: $planningTaskResourcesBySowTask, $dupaItemsForPlanning, $hasPlanningTaskResourcesTable for Resources section.
if (!function_exists('renderPlanningSowTaskRows')) {
function renderPlanningSowTaskRows(array $children, array $sowTasksByParent, array $computedDuration, int $projectId, array $ganttDays, int $depth = 1, $sowIndex = null, $baselineTs = null, $totalDays = 1, $baselineYmd = '', array $resourcesBySowTask = [], array $dupaItems = [], $colspan = 0, $redirectHidden = '', $baseUrl = '', array $durationFromResources = []): void {
    $pad = min($depth * 24, 120);
    $rowIndex = 0;
    $baseUrl = $baseUrl ?: (defined('BASE_URL') ? BASE_URL : '');
    foreach ($children as $w) {
        $rowIndex++;
        $taskId = (int)$w['id'];
        $displayCode = ($sowIndex !== null && $depth === 1) ? ($sowIndex . '.' . $rowIndex) : ($w['code'] ?? '');
        $hasChildren = !empty($sowTasksByParent[$taskId]);
        $storedDur = isset($w['planned_duration_days']) && $w['planned_duration_days'] !== null ? (int)$w['planned_duration_days'] : 0;
        $resourceDur = (int)($durationFromResources[$taskId] ?? 0);
        $effectiveDur = $hasChildren ? ($computedDuration[$taskId] ?? '') : ($resourceDur > 0 ? $resourceDur : ($storedDur > 0 ? $storedDur : ''));
        $plannedStart = !empty($w['planned_start_date']) ? date('Y-m-d', strtotime($w['planned_start_date'])) : '';
        $plannedEnd = !empty($w['planned_end_date']) ? date('Y-m-d', strtotime($w['planned_end_date'])) : '';
        $plannedStartDay = ($baselineTs !== null && $plannedStart) ? planningDateToDay($plannedStart, $baselineTs) : null;
        $plannedEndDay = ($baselineTs !== null && $plannedEnd) ? planningDateToDay($plannedEnd, $baselineTs) : null;
        $formId = 'sow-form-' . $taskId;
        $taskResources = $resourcesBySowTask[$taskId] ?? [];
        $resourceCount = count($taskResources);
        $showResourcesSection = !$hasChildren && $colspan > 0;
        ?>
        <tr class="sow-task-row planned-row" data-sow-task-id="<?= $taskId ?>">
            <td class="col-id text-center"></td>
            <td class="col-desc planning-wbs-desc-cell" style="padding-left:<?= $pad ?>px">
                <div class="planning-activity-cell">
                    <div class="d-flex align-items-center planning-activity-row">
                        <form id="sow-edit-form-<?= $taskId ?>" action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="planning-wbs-name-form d-flex align-items-center flex-grow-1 min-w-0">
                            <input type="hidden" name="action" value="update_sow_task">
                            <input type="hidden" name="project_id" value="<?= $projectId ?>">
                            <input type="hidden" name="task_id" value="<?= $taskId ?>">
                            <?= $redirectHidden ?>
                            <input type="text" name="code" class="form-control form-control-sm wbs-code-input planning-code-input" value="<?= htmlspecialchars($displayCode, ENT_QUOTES, 'UTF-8') ?>" placeholder="<?= $sowIndex !== null && $depth === 1 ? $sowIndex . '.1' : '1.1' ?>" title="Code">
                            <input type="text" name="description" class="form-control form-control-sm wbs-desc-input" value="<?= htmlspecialchars($w['description'] ?? '', ENT_QUOTES, 'UTF-8') ?>" placeholder="Description" title="Description">
                        </form>
                        <span class="planning-row-actions">
                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 me-1 wbs-save-all-btn" title="Save" data-name-form-id="sow-edit-form-<?= $taskId ?>" data-dates-form-id="<?= $formId ?>">Save</button>
                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-inline" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll(); return confirm('Delete this task?');">
                                <input type="hidden" name="action" value="delete_sow_task">
                                <input type="hidden" name="project_id" value="<?= $projectId ?>">
                                <input type="hidden" name="task_id" value="<?= $taskId ?>">
                                <?= $redirectHidden ?>
                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                            </form>
                        </span>
                    </div>
                    <?php if ($showResourcesSection): ?>
                    <div class="planning-resources-row-wrap d-flex align-items-center gap-2">
                        <span class="planning-resources-code-spacer"></span>
                        <button type="button" class="btn btn-outline-primary btn-sm planning-resources-toggle flex-grow-1 text-start" data-target-id="planning-resources-<?= $taskId ?>" title="Toggle resources">
                            <i class="bi bi-boxes me-1"></i>Resources (<?= $resourceCount ?>)
                        </button>
                        <span class="planning-resources-actions-spacer"></span>
                    </div>
                    <?php endif; ?>
                </div>
            </td>
            <td class="col-duration">
                <?php if ($hasChildren): ?>
                <span class="text-muted" title="Sum of children"><?= (int)($computedDuration[$taskId] ?? 0) ?></span> days
                <?php else: ?>
                <form id="<?= $formId ?>" action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-none">
                    <input type="hidden" name="action" value="update_sow_task_dates">
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <input type="hidden" name="task_id" value="<?= $taskId ?>">
                    <?= isset($planningFormRedirectHidden) ? $planningFormRedirectHidden : '' ?>
                    <input type="hidden" name="project_id" value="<?= $projectId ?>">
                    <input type="hidden" name="task_id" value="<?= $taskId ?>">
                    <input type="hidden" name="planned_start_date" id="sow-form-start-<?= $taskId ?>" value="<?= htmlspecialchars($plannedStart, ENT_QUOTES, 'UTF-8') ?>">
                    <input type="hidden" name="planned_end_date" id="sow-form-end-<?= $taskId ?>" value="<?= htmlspecialchars($plannedEnd, ENT_QUOTES, 'UTF-8') ?>">
                </form>
                <input type="number" form="<?= $formId ?>" name="planned_duration_days" class="form-control form-control-sm planned-duration-input text-center" min="1" max="365" value="<?= $effectiveDur !== '' ? $effectiveDur : '' ?>" placeholder="—" style="max-width:5rem" data-start-id="sow-form-start-<?= $taskId ?>" data-end-id="sow-form-end-<?= $taskId ?>" data-form-id="<?= $formId ?>">
                <?php endif; ?>
            </td>
            <td class="col-start">
                <?php if ($hasChildren): ?>
                <span class="text-muted">—</span>
                <?php else: ?>
                <input type="number" class="form-control form-control-sm wbs-day-start-input text-center" min="1" max="<?= (int)$totalDays ?>" value="<?= $plannedStartDay !== null ? (int)$plannedStartDay : '' ?>" placeholder="—" style="max-width:4.5rem" data-date-id="sow-form-start-<?= $taskId ?>" data-form-id="<?= $formId ?>" data-baseline="<?= htmlspecialchars($baselineYmd, ENT_QUOTES, 'UTF-8') ?>" title="Start day">
                <?php endif; ?>
            </td>
            <td class="col-end">
                <span class="planned-end-day-display sow-end-day-display text-muted">Day <?= $plannedEndDay !== null ? (int)$plannedEndDay : '—' ?></span>
            </td>
            <?php
            foreach ($ganttDays as $gd) {
                $dayNum = $gd['day'];
                $inPlanned = ($plannedStartDay !== null && $plannedEndDay !== null && $dayNum >= $plannedStartDay && $dayNum <= $plannedEndDay);
                $isFirst = $inPlanned && ($dayNum === $plannedStartDay);
                $isLast = $inPlanned && ($dayNum === $plannedEndDay);
                ?>
                <td class="gantt-day-cell planned-day-cell <?= $inPlanned ? 'bar-planned' : '' ?> <?= $isFirst ? 'bar-start' : '' ?> <?= $isLast ? 'bar-end' : '' ?>" title="<?= $inPlanned ? 'Planned: Day ' . $plannedStartDay . ' – Day ' . $plannedEndDay : '' ?>"></td>
            <?php } ?>
        </tr>
        <?php if ($showResourcesSection): ?>
        <?php
        $sowBudgetItemId = isset($w['budget_item_id']) && $w['budget_item_id'] !== '' && $w['budget_item_id'] !== null ? (int)$w['budget_item_id'] : null;
        $dupaForThisSow = [];
        foreach (['manpower' => 'Manpower', 'equipment' => 'Equipment', 'materials' => 'Materials'] as $cat => $label) {
            $dupaForThisSow[$cat] = [];
            foreach ($dupaItems[$cat] ?? [] as $d) {
                $dBid = isset($d['budget_item_id']) && $d['budget_item_id'] !== '' && $d['budget_item_id'] !== null ? (int)$d['budget_item_id'] : null;
                if ($dBid === $sowBudgetItemId) {
                    $dupaForThisSow[$cat][] = $d;
                }
            }
        }
        $resourcesByCat = [ 'manpower' => [], 'equipment' => [], 'materials' => [] ];
        foreach ($taskResources as $res) {
            $c = $res['dupa_category'] ?? 'materials';
            if (isset($resourcesByCat[$c])) $resourcesByCat[$c][] = $res; else $resourcesByCat['materials'][] = $res;
        }
        ?>
        <tr id="planning-resources-<?= $taskId ?>" class="planning-resources-row table-light" style="display:none" data-planning-pad="<?= (int)$pad ?>">
            <td colspan="5" class="py-3 align-top planning-resources-td" style="padding-left: calc(3rem + <?= (int)$pad ?>px);">
                <div class="planning-resources-vertical d-flex flex-column gap-2">
                    <div class="border rounded p-2 bg-white">
                        <h6 class="small text-uppercase text-muted mb-2"><i class="bi bi-people me-1"></i>Manpower</h6>
                            <ul class="list-unstyled mb-2 small">
                                <?php foreach ($resourcesByCat['manpower'] as $res):
                                    $resId = (int)$res['id'];
                                    $resQty = (float)($res['quantity'] ?? 0);
                                    $resUnit = $res['unit'] ?? $res['dupa_unit'] ?? '';
                                    $resRule = $res['use_rule'] ?? 'spread';
                                ?>
                                <li class="planning-resource-item border-bottom border-light py-1">
                                    <span class="planning-resource-display d-flex align-items-center gap-2 flex-wrap">
                                        <span><?= htmlspecialchars($res['dupa_description'] ?? $res['resource_description'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted"><?= $resQty ?> <?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="ms-auto d-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 planning-resource-edit-btn" title="Edit" data-resource-id="<?= $resId ?>" data-quantity="<?= $resQty ?>" data-unit="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" data-use-rule="<?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
                                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-inline" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll(); return confirm('Remove?');">
                                                <input type="hidden" name="action" value="delete_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </span>
                                    </span>
                                    <span class="planning-resource-edit-form d-none d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-center gap-1" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                            <input type="hidden" name="action" value="update_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                            <?php if (!empty($res['dupa_item_id'])): ?><input type="hidden" name="dupa_item_id" value="<?= (int)$res['dupa_item_id'] ?>"><?php endif; ?>
                                            <?php if (!empty($res['resource_description'])): ?><input type="hidden" name="resource_description" value="<?= htmlspecialchars($res['resource_description'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                            <input type="number" name="quantity" class="form-control form-control-sm" style="width:55px" min="0.001" step="any" value="<?= $resQty ?>">
                                            <input type="text" name="unit" class="form-control form-control-sm" style="width:50px" value="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            <select name="use_rule" class="form-select form-select-sm planning-use-rule-select"><option value="spread"<?= $resRule === 'spread' ? ' selected' : '' ?>>Spread</option><option value="start"<?= $resRule === 'start' ? ' selected' : '' ?>>Start</option><option value="end"<?= $resRule === 'end' ? ' selected' : '' ?>>End</option></select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                            <button type="button" class="btn btn-secondary btn-sm planning-resource-edit-cancel">Cancel</button>
                                        </form>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($resourcesByCat['manpower'])): ?><li class="text-muted">None</li><?php endif; ?>
                            </ul>
                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-end gap-1 small" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                <input type="hidden" name="action" value="add_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="task_type" value="sow_task"><input type="hidden" name="sow_task_id" value="<?= $taskId ?>"><?= $redirectHidden ?>
                                <select name="dupa_item_id" class="form-select form-select-sm" style="min-width:140px"><option value="">— select —</option><?php foreach ($dupaForThisSow['manpower'] ?? [] as $d): ?><option value="<?= (int)$d['id'] ?>" data-unit="<?= htmlspecialchars($d['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['description'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="0.001" step="any" style="width:55px" placeholder="Qty">
                                <input type="text" name="unit" class="form-control form-control-sm planning-resource-unit-input" placeholder="Unit (from DUPA)" style="width:50px" readonly>
                                <select name="use_rule" class="form-select form-select-sm planning-use-rule-select" title="When"><option value="spread">Spread</option><option value="start">Start</option><option value="end">End</option></select>
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
                    </div>
                    <div class="border rounded p-2 bg-white">
                        <h6 class="small text-uppercase text-muted mb-2"><i class="bi bi-truck me-1"></i>Equipment</h6>
                            <ul class="list-unstyled mb-2 small">
                                <?php foreach ($resourcesByCat['equipment'] as $res):
                                    $resId = (int)$res['id'];
                                    $resQty = (float)($res['quantity'] ?? 0);
                                    $resUnit = $res['unit'] ?? $res['dupa_unit'] ?? '';
                                    $resRule = $res['use_rule'] ?? 'spread';
                                ?>
                                <li class="planning-resource-item border-bottom border-light py-1">
                                    <span class="planning-resource-display d-flex align-items-center gap-2 flex-wrap">
                                        <span><?= htmlspecialchars($res['dupa_description'] ?? $res['resource_description'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted"><?= $resQty ?> <?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="ms-auto d-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 planning-resource-edit-btn" title="Edit" data-resource-id="<?= $resId ?>" data-quantity="<?= $resQty ?>" data-unit="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" data-use-rule="<?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
                                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-inline" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll(); return confirm('Remove?');">
                                                <input type="hidden" name="action" value="delete_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </span>
                                    </span>
                                    <span class="planning-resource-edit-form d-none d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-center gap-1" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                            <input type="hidden" name="action" value="update_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                            <?php if (!empty($res['dupa_item_id'])): ?><input type="hidden" name="dupa_item_id" value="<?= (int)$res['dupa_item_id'] ?>"><?php endif; ?>
                                            <?php if (!empty($res['resource_description'])): ?><input type="hidden" name="resource_description" value="<?= htmlspecialchars($res['resource_description'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                            <input type="number" name="quantity" class="form-control form-control-sm" style="width:55px" min="0.001" step="any" value="<?= $resQty ?>">
                                            <input type="text" name="unit" class="form-control form-control-sm" style="width:50px" value="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            <select name="use_rule" class="form-select form-select-sm planning-use-rule-select"><option value="spread"<?= $resRule === 'spread' ? ' selected' : '' ?>>Spread</option><option value="start"<?= $resRule === 'start' ? ' selected' : '' ?>>Start</option><option value="end"<?= $resRule === 'end' ? ' selected' : '' ?>>End</option></select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                            <button type="button" class="btn btn-secondary btn-sm planning-resource-edit-cancel">Cancel</button>
                                        </form>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($resourcesByCat['equipment'])): ?><li class="text-muted">None</li><?php endif; ?>
                            </ul>
                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-end gap-1 small" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                <input type="hidden" name="action" value="add_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="task_type" value="sow_task"><input type="hidden" name="sow_task_id" value="<?= $taskId ?>"><?= $redirectHidden ?>
                                <select name="dupa_item_id" class="form-select form-select-sm" style="min-width:140px"><option value="">— select —</option><?php foreach ($dupaForThisSow['equipment'] ?? [] as $d): ?><option value="<?= (int)$d['id'] ?>" data-unit="<?= htmlspecialchars($d['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['description'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="0.001" step="any" style="width:55px" placeholder="Qty">
                                <input type="text" name="unit" class="form-control form-control-sm planning-resource-unit-input" placeholder="Unit (from DUPA)" style="width:50px" readonly>
                                <select name="use_rule" class="form-select form-select-sm planning-use-rule-select"><option value="spread">Spread</option><option value="start">Start</option><option value="end">End</option></select>
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
                    </div>
                    <div class="border rounded p-2 bg-white">
                        <h6 class="small text-uppercase text-muted mb-2"><i class="bi bi-box-seam me-1"></i>Materials</h6>
                            <ul class="list-unstyled mb-2 small">
                                <?php foreach ($resourcesByCat['materials'] as $res):
                                    $resId = (int)$res['id'];
                                    $resQty = (float)($res['quantity'] ?? 0);
                                    $resUnit = $res['unit'] ?? $res['dupa_unit'] ?? '';
                                    $resRule = $res['use_rule'] ?? 'spread';
                                ?>
                                <li class="planning-resource-item border-bottom border-light py-1">
                                    <span class="planning-resource-display d-flex align-items-center gap-2 flex-wrap">
                                        <span><?= htmlspecialchars($res['dupa_description'] ?? $res['resource_description'] ?? '—', ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="text-muted"><?= $resQty ?> <?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="badge bg-secondary"><?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?></span>
                                        <span class="ms-auto d-flex align-items-center gap-1">
                                            <button type="button" class="btn btn-outline-secondary btn-sm py-0 px-1 planning-resource-edit-btn" title="Edit" data-resource-id="<?= $resId ?>" data-quantity="<?= $resQty ?>" data-unit="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" data-use-rule="<?= htmlspecialchars($resRule, ENT_QUOTES, 'UTF-8') ?>"><i class="bi bi-pencil"></i></button>
                                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-inline" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll(); return confirm('Remove?');">
                                                <input type="hidden" name="action" value="delete_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1"><i class="bi bi-trash"></i></button>
                                            </form>
                                        </span>
                                    </span>
                                    <span class="planning-resource-edit-form d-none d-flex flex-wrap align-items-center gap-1 mt-1">
                                        <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-center gap-1" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                            <input type="hidden" name="action" value="update_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="resource_id" value="<?= $resId ?>"><?= $redirectHidden ?>
                                            <?php if (!empty($res['dupa_item_id'])): ?><input type="hidden" name="dupa_item_id" value="<?= (int)$res['dupa_item_id'] ?>"><?php endif; ?>
                                            <?php if (!empty($res['resource_description'])): ?><input type="hidden" name="resource_description" value="<?= htmlspecialchars($res['resource_description'], ENT_QUOTES, 'UTF-8') ?>"><?php endif; ?>
                                            <input type="number" name="quantity" class="form-control form-control-sm" style="width:55px" min="0.001" step="any" value="<?= $resQty ?>">
                                            <input type="text" name="unit" class="form-control form-control-sm" style="width:50px" value="<?= htmlspecialchars($resUnit, ENT_QUOTES, 'UTF-8') ?>" readonly>
                                            <select name="use_rule" class="form-select form-select-sm planning-use-rule-select"><option value="spread"<?= $resRule === 'spread' ? ' selected' : '' ?>>Spread</option><option value="start"<?= $resRule === 'start' ? ' selected' : '' ?>>Start</option><option value="end"<?= $resRule === 'end' ? ' selected' : '' ?>>End</option></select>
                                            <button type="submit" class="btn btn-primary btn-sm">Save</button>
                                            <button type="button" class="btn btn-secondary btn-sm planning-resource-edit-cancel">Cancel</button>
                                        </form>
                                    </span>
                                </li>
                                <?php endforeach; ?>
                                <?php if (empty($resourcesByCat['materials'])): ?><li class="text-muted">None</li><?php endif; ?>
                            </ul>
                            <form action="<?= $baseUrl ?>/actions/project_actions.php" method="POST" class="d-flex flex-wrap align-items-end gap-1 small" onsubmit="if(typeof window.saveViewScroll==='function') window.saveViewScroll();">
                                <input type="hidden" name="action" value="add_planning_task_resource"><input type="hidden" name="project_id" value="<?= $projectId ?>"><input type="hidden" name="task_type" value="sow_task"><input type="hidden" name="sow_task_id" value="<?= $taskId ?>"><?= $redirectHidden ?>
                                <select name="dupa_item_id" class="form-select form-select-sm" style="min-width:140px"><option value="">— select —</option><?php foreach ($dupaForThisSow['materials'] ?? [] as $d): ?><option value="<?= (int)$d['id'] ?>" data-unit="<?= htmlspecialchars($d['unit'] ?? '', ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($d['description'], ENT_QUOTES, 'UTF-8') ?></option><?php endforeach; ?></select>
                                <input type="number" name="quantity" class="form-control form-control-sm" value="1" min="0.001" step="any" style="width:55px" placeholder="Qty">
                                <input type="text" name="unit" class="form-control form-control-sm planning-resource-unit-input" placeholder="Unit (from DUPA)" style="width:50px" readonly>
                                <select name="use_rule" class="form-select form-select-sm planning-use-rule-select"><option value="spread">Spread</option><option value="start">Start</option><option value="end">End</option></select>
                                <button type="submit" class="btn btn-primary btn-sm">Add</button>
                            </form>
                    </div>
                </div>
                <small class="text-muted d-block mt-2">DUPA items shown are under this Scope of Work only. Add/link them in the <a href="<?= $baseUrl ?>/pages/projects/view.php?id=<?= $projectId ?>&tab=dupa">DUPA</a> tab.</small>
            </td>
            <?php for ($di = 0; $di < count($ganttDays); $di++): ?>
            <td class="gantt-day-cell table-light"></td>
            <?php endfor; ?>
        </tr>
        <?php endif; ?>
        <?php
        $sub = $sowTasksByParent[$taskId] ?? [];
        if (!empty($sub)) {
            renderPlanningSowTaskRows($sub, $sowTasksByParent, $computedDuration, $projectId, $ganttDays, $depth + 1, null, $baselineTs, $totalDays, $baselineYmd, $resourcesBySowTask, $dupaItems, $colspan, $redirectHidden, $baseUrl);
        }
    }
}
}
if (!empty($sowTaskChildren)) {
    $sowIndex = isset($planningSowIndex) ? (int)$planningSowIndex : 1;
    $baselineTs = isset($planningBaselineTs) ? $planningBaselineTs : null;
    $totalDays = isset($planningTotalDays) ? (int)$planningTotalDays : 1;
    $baselineYmd = isset($planningBaseline) ? $planningBaseline : '';
    $colspan = 5 + (isset($ganttDays) ? count($ganttDays) : 0);
    $redirectHidden = isset($planningFormRedirectHidden) ? $planningFormRedirectHidden : '';
    renderPlanningSowTaskRows($sowTaskChildren, $sowTasksByParent, $sowTaskComputedDuration, (int)$id, $ganttDays, 1, $sowIndex, $baselineTs, $totalDays, $baselineYmd, isset($planningTaskResourcesBySowTask) ? $planningTaskResourcesBySowTask : [], isset($dupaItemsForPlanning) ? $dupaItemsForPlanning : [], $colspan, $redirectHidden, defined('BASE_URL') ? BASE_URL : '', isset($planningDurationFromResourcesBySowTask) ? $planningDurationFromResourcesBySowTask : []);
}

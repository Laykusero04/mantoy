<?php
// Tab: DUPA — Manpower, Equipment, Materials cost breakdown by Scope of Work. Receives $pdo, $project, $id, $activeTab from view.php

$dupaTableExists = false;
$budgetItems = [];
$bySow = [];
$grandTotalManpower = 0;
$grandTotalEquipment = 0;
$grandTotalMaterials = 0;
$hasUnassigned = false;

try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_dupa_items'");
    $dupaTableExists = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    $dupaTableExists = false;
}

if ($dupaTableExists) {
    $durationCol = $pdo->query("SHOW COLUMNS FROM budget_items LIKE 'duration_days'");
    $hasDuration = $durationCol && $durationCol->rowCount() > 0;
    $budgetSelect = "SELECT id, item_name" . ($hasDuration ? ", duration_days" : "") . " FROM budget_items WHERE project_id = ? ORDER BY id";
    $budgetStmt = $pdo->prepare($budgetSelect);
    $budgetStmt->execute([$id]);
    $budgetItems = $budgetStmt->fetchAll(PDO::FETCH_ASSOC);

    $cols = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
    $hasBudgetItemId = $cols && $cols->rowCount() > 0;

    $stmt = $pdo->prepare("SELECT * FROM project_dupa_items WHERE project_id = ? ORDER BY " . ($hasBudgetItemId ? "COALESCE(budget_item_id, 0), " : "") . "category, sort_order, id");
    $stmt->execute([$id]);
    $all = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($budgetItems as $bi) {
        $bid = (int)$bi['id'];
        $bySow[$bid] = [
            'label' => $bi['item_name'],
            'duration_days' => isset($bi['duration_days']) ? (int)$bi['duration_days'] : null,
            'budget_item_id' => $bid,
            'manpower' => [],
            'equipment' => [],
            'materials' => [],
            'manpower_total' => 0,
            'equipment_total' => 0,
            'materials_total' => 0,
            'row_total' => 0,
        ];
    }

    $bySow['_unassigned'] = [
        'label' => '— Unassigned',
        'duration_days' => null,
        'budget_item_id' => null,
        'manpower' => [],
        'equipment' => [],
        'materials' => [],
        'manpower_total' => 0,
        'equipment_total' => 0,
        'materials_total' => 0,
        'row_total' => 0,
    ];

    $budgetIds = array_map(function ($bi) { return (int)$bi['id']; }, $budgetItems);
    foreach ($all as $row) {
        $amt = (float)($row['amount'] ?? (float)$row['quantity'] * (float)$row['rate']);
        $bid = isset($row['budget_item_id']) && $row['budget_item_id'] !== null && $row['budget_item_id'] !== '' ? (int)$row['budget_item_id'] : null;
        // Only place under SOW row if that budget item still exists; otherwise show under Unassigned
        $key = ($bid !== null && in_array($bid, $budgetIds, true)) ? $bid : '_unassigned';
        if ($key === '_unassigned') {
            $hasUnassigned = true;
        }
        if (!isset($bySow[$key])) {
            $bySow[$key] = [
                'label' => $key === '_unassigned' ? '— Unassigned' : '',
                'duration_days' => null,
                'budget_item_id' => $key === '_unassigned' ? null : $key,
                'manpower' => [],
                'equipment' => [],
                'materials' => [],
                'manpower_total' => 0,
                'equipment_total' => 0,
                'materials_total' => 0,
                'row_total' => 0,
            ];
            if ($key !== '_unassigned') {
                foreach ($budgetItems as $bi) {
                    if ((int)$bi['id'] === $key) {
                        $bySow[$key]['label'] = $bi['item_name'];
                        if (isset($bi['duration_days'])) $bySow[$key]['duration_days'] = (int)$bi['duration_days'];
                        break;
                    }
                }
            }
        }
        $cat = $row['category'];
        $bySow[$key][$cat][] = $row;
        $bySow[$key][$cat . '_total'] += $amt;
        $bySow[$key]['row_total'] += $amt;
        if ($cat === 'manpower') {
            $grandTotalManpower += $amt;
        } elseif ($cat === 'equipment') {
            $grandTotalEquipment += $amt;
        } else {
            $grandTotalMaterials += $amt;
        }
    }

    $grandTotal = $grandTotalManpower + $grandTotalEquipment + $grandTotalMaterials;

    $rowOrder = array_map(function ($bi) { return (int)$bi['id']; }, $budgetItems);
    if ($hasUnassigned) {
        $rowOrder[] = '_unassigned';
    }
} else {
    $rowOrder = [];
}

function renderDupaCell($items, $category, $sowKey, $id, $defaultUnit) {
    $categoryLabel = ucfirst($category);
    ?>
    <td class="align-top">
        <div class="dupa-cell-items">
            <?php foreach ($items as $item):
                $amt = (float)($item['amount'] ?? (float)$item['quantity'] * (float)$item['rate']);
            ?>
                <div class="d-flex align-items-center justify-content-between gap-1 small border-bottom border-light pb-1 mb-1">
                    <span class="text-truncate" title="<?= sanitize($item['description']) ?>"><?= sanitize($item['description']) ?></span>
                    <span class="text-nowrap fw-medium"><?= formatCurrency($amt) ?></span>
                    <div class="btn-group btn-group-sm">
                        <button type="button" class="btn btn-outline-secondary btn-edit-dupa p-0 px-1"
                                data-bs-toggle="modal" data-bs-target="#dupaEditModal"
                                data-id="<?= $item['id'] ?>"
                                data-budget-item-id="<?= (isset($item['budget_item_id']) && $item['budget_item_id'] !== null && $item['budget_item_id'] !== '' && (int)$item['budget_item_id'] > 0) ? (int)$item['budget_item_id'] : '' ?>"
                                data-category="<?= $category ?>"
                                data-description="<?= sanitize($item['description']) ?>"
                                data-quantity="<?= $item['quantity'] ?>"
                                data-unit="<?= sanitize($item['unit'] ?? '') ?>"
                                data-rate="<?= $item['rate'] ?>">
                            <i class="bi bi-pencil"></i>
                        </button>
                        <form action="<?= BASE_URL ?>/actions/dupa_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this item?');">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="project_id" value="<?= $id ?>">
                            <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm p-0 px-1"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        <button type="button" class="btn btn-outline-primary btn-sm mt-1" data-bs-toggle="modal" data-bs-target="#dupaAddModal"
                data-category="<?= $category ?>"
                data-default-unit="<?= $defaultUnit ?>"
                data-budget-item-id="<?= $sowKey === '_unassigned' ? '' : $sowKey ?>">
            <i class="bi bi-plus-lg me-1"></i> Add
        </button>
    </td>
    <?php
}
?>

<div class="dupa-tab">
    <?php if (!$dupaTableExists): ?>
        <div class="alert alert-warning">
            <strong>DUPA table not found.</strong> Run <code>database/migration_dupa.sql</code> in your database (e.g. via phpMyAdmin) to create the cost breakdown tables.
        </div>
    <?php else: ?>

        <p class="text-muted small mb-3">Cost breakdown by Scope of Work: Manpower, Equipment, and Materials. Each row is a Scope of Work; add line items in each cell.</p>

        <?php if (empty($budgetItems) && !$hasUnassigned): ?>
            <div class="alert alert-info">
                No Scope of Work yet. Add items in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=budget">Scope of Work (Budget)</a> tab, then return here to add DUPA line items per SOW.
            </div>
            <div class="card shadow-sm">
                <div class="card-body">
                    <p class="mb-2">You can still add unassigned DUPA items:</p>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dupaAddModal" data-category="manpower" data-default-unit="hrs" data-budget-item-id="">
                        <i class="bi bi-plus-lg me-1"></i> Add Manpower
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dupaAddModal" data-category="equipment" data-default-unit="days" data-budget-item-id="">
                        <i class="bi bi-plus-lg me-1"></i> Add Equipment
                    </button>
                    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#dupaAddModal" data-category="materials" data-default-unit="pcs" data-budget-item-id="">
                        <i class="bi bi-plus-lg me-1"></i> Add Materials
                    </button>
                </div>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-bordered table-hover align-middle mb-0 dupa-sow-table">
                <thead class="table-light">
                    <tr>
                        <th>Scope of Work</th>
                        <th class="text-center">Duration <span class="fw-normal text-muted small">(days)</span></th>
                        <th class="text-end">Manpower</th>
                        <th class="text-end">Equipment</th>
                        <th class="text-end">Materials</th>
                        <th class="text-end fw-bold">Row total</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    foreach ($rowOrder as $sowKey):
                        if (!isset($bySow[$sowKey])) continue;
                        $row = $bySow[$sowKey];
                        $defaultUnits = ['manpower' => 'hrs', 'equipment' => 'days', 'materials' => 'pcs'];
                        $bid = isset($row['budget_item_id']) ? (int)$row['budget_item_id'] : null;
                        $dur = isset($row['duration_days']) ? (int)$row['duration_days'] : null;
                    ?>
                    <tr>
                        <td class="fw-medium"><?= sanitize($row['label']) ?></td>
                        <td class="align-top pt-2">
                            <?php if ($bid !== null && $hasDuration): ?>
                            <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST" class="d-inline-block">
                                <input type="hidden" name="action" value="update_duration">
                                <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                <input type="hidden" name="item_id" value="<?= $bid ?>">
                                <input type="number" name="duration_days" class="form-control form-control-sm d-inline-block text-center" style="width: 5rem;" min="0" step="1" value="<?= $dur !== null && $dur > 0 ? $dur : '' ?>" placeholder="—">
                                <button type="submit" class="btn btn-outline-secondary btn-sm ms-1 py-0" title="Save duration">✓</button>
                            </form>
                            <?php elseif ($hasDuration && $dur !== null && $dur > 0): ?>
                                <?= (int)$dur ?> days
                            <?php else: ?>
                                <span class="text-muted">—</span>
                            <?php endif; ?>
                        </td>
                        <?php renderDupaCell($row['manpower'], 'manpower', $sowKey, $id, $defaultUnits['manpower']); ?>
                        <?php renderDupaCell($row['equipment'], 'equipment', $sowKey, $id, $defaultUnits['equipment']); ?>
                        <?php renderDupaCell($row['materials'], 'materials', $sowKey, $id, $defaultUnits['materials']); ?>
                        <td class="text-end fw-bold align-top pt-2"><?= formatCurrency($row['row_total']) ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot class="table-light">
                    <tr>
                        <td class="fw-bold">Grand total</td>
                        <td></td>
                        <td class="text-end fw-bold"><?= formatCurrency($grandTotalManpower) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($grandTotalEquipment) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($grandTotalMaterials) ?></td>
                        <td class="text-end fw-bold text-primary"><?= formatCurrency($grandTotal) ?></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    <?php endif; ?>
</div>

<!-- Add DUPA item modal -->
<div class="modal fade" id="dupaAddModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/dupa_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="category" id="dupaAddCategory" value="manpower">
                <div class="modal-header">
                    <h6 class="modal-title" id="dupaAddModalTitle">Add Manpower item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Scope of Work</label>
                        <select class="form-select" id="dupaAddSowSelect" name="budget_item_id">
                            <option value="">— Unassigned</option>
                            <?php foreach ($budgetItems as $bi): ?>
                                <option value="<?= (int)$bi['id'] ?>"><?= sanitize($bi['item_name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <p class="form-text text-muted small mb-0" id="dupaAddSowNote" style="display: none;">Materials must be assigned to a Scope of Work.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="description" required placeholder="e.g. Skilled labor, Foreman">
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" step="0.01" min="0" value="1">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="dupaAddUnit" placeholder="e.g. hrs, days, pcs">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label" id="dupaAddRateLabel">Hourly rate (₱)</label>
                        <input type="number" class="form-control" name="rate" step="0.01" min="0" value="0" placeholder="0.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit DUPA item modal -->
<div class="modal fade" id="dupaEditModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/dupa_actions.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="item_id" id="dupaEditId" value="">
                <input type="hidden" name="category" id="dupaEditCategory" value="manpower">
                <div class="modal-header">
                    <h6 class="modal-title">Edit item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Scope of Work</label>
                        <select class="form-select" id="dupaEditSowSelect" name="budget_item_id">
                            <option value="">— Unassigned</option>
                            <?php if (isset($budgetItems)): foreach ($budgetItems as $bi): ?>
                                <option value="<?= (int)$bi['id'] ?>"><?= sanitize($bi['item_name']) ?></option>
                            <?php endforeach; endif; ?>
                        </select>
                        <p class="form-text text-muted small mb-0" id="dupaEditSowNote" style="display: none;">Materials must be assigned to a Scope of Work.</p>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="description" id="dupaEditDescription" required>
                    </div>
                    <div class="row g-2">
                        <div class="col-6">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="dupaEditQuantity" step="0.01" min="0">
                        </div>
                        <div class="col-6">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="dupaEditUnit">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Rate / Unit cost (₱)</label>
                        <input type="number" class="form-control" name="rate" id="dupaEditRate" step="0.01" min="0">
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
    var addModal = document.getElementById('dupaAddModal');
    if (addModal) {
        addModal.addEventListener('show.bs.modal', function(e) {
            var btn = e.relatedTarget;
            var cat = (btn && btn.dataset.category) ? btn.dataset.category : 'manpower';
            var defaultUnit = (btn && btn.dataset.defaultUnit) ? btn.dataset.defaultUnit : 'hrs';
            var budgetItemId = (btn && btn.dataset.budgetItemId !== undefined) ? btn.dataset.budgetItemId : '';
            document.getElementById('dupaAddCategory').value = cat;
            var unitEl = document.getElementById('dupaAddUnit');
            if (unitEl) unitEl.placeholder = defaultUnit;
            var sowSelect = document.getElementById('dupaAddSowSelect');
            var sowNote = document.getElementById('dupaAddSowNote');
            if (sowSelect) {
                var unassigned = sowSelect.querySelector('option[value=""]');
                var addingFromSpecificSow = (budgetItemId !== undefined && budgetItemId !== '');
                if (cat === 'materials') {
                    sowSelect.required = true;
                    if (sowNote) sowNote.style.display = '';
                    if (unassigned) unassigned.remove();
                    sowSelect.value = budgetItemId || (sowSelect.options.length ? sowSelect.options[0].value : '');
                } else {
                    sowSelect.required = false;
                    if (sowNote) sowNote.style.display = 'none';
                    if (addingFromSpecificSow && unassigned) unassigned.remove();
                    if (!addingFromSpecificSow && !sowSelect.querySelector('option[value=""]')) {
                        var opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '— Unassigned';
                        sowSelect.insertBefore(opt, sowSelect.firstChild);
                    }
                    sowSelect.value = budgetItemId || '';
                }
            }
            var title = document.getElementById('dupaAddModalTitle');
            var rateLabel = document.getElementById('dupaAddRateLabel');
            if (cat === 'manpower') {
                if (title) title.textContent = 'Add Manpower item';
                if (rateLabel) rateLabel.textContent = 'Hourly rate (₱)';
            } else if (cat === 'equipment') {
                if (title) title.textContent = 'Add Equipment item';
                if (rateLabel) rateLabel.textContent = 'Rate (₱)';
            } else {
                if (title) title.textContent = 'Add Materials item';
                if (rateLabel) rateLabel.textContent = 'Unit cost (₱)';
            }
        });
    }
    document.querySelectorAll('.btn-edit-dupa').forEach(function(btn) {
        btn.addEventListener('click', function() {
            var cat = btn.dataset.category || 'manpower';
            document.getElementById('dupaEditId').value = btn.dataset.id || '';
            document.getElementById('dupaEditCategory').value = cat;
            var editSow = document.getElementById('dupaEditSowSelect');
            var editSowNote = document.getElementById('dupaEditSowNote');
            if (editSow) {
                var unassigned = editSow.querySelector('option[value=""]');
                if (cat === 'materials') {
                    editSow.required = true;
                    if (editSowNote) editSowNote.style.display = '';
                    if (unassigned) unassigned.remove();
                    // For materials, ensure a valid SOW is selected (so save moves item to that scope, not Unassigned)
                    var currentBudgetId = (btn.dataset.budgetItemId !== undefined && btn.dataset.budgetItemId !== '') ? btn.dataset.budgetItemId : '';
                    editSow.value = currentBudgetId || (editSow.options.length ? editSow.options[0].value : '');
                } else {
                    editSow.required = false;
                    if (editSowNote) editSowNote.style.display = 'none';
                    if (!editSow.querySelector('option[value=""]')) {
                        var opt = document.createElement('option');
                        opt.value = '';
                        opt.textContent = '— Unassigned';
                        editSow.insertBefore(opt, editSow.firstChild);
                    }
                    editSow.value = btn.dataset.budgetItemId || '';
                }
            }
            document.getElementById('dupaEditDescription').value = btn.dataset.description || '';
            document.getElementById('dupaEditQuantity').value = btn.dataset.quantity || '';
            document.getElementById('dupaEditUnit').value = btn.dataset.unit || '';
            document.getElementById('dupaEditRate').value = btn.dataset.rate || '';
        });
    });
    var editForm = document.querySelector('#dupaEditModal form');
    if (editForm) {
        editForm.addEventListener('submit', function() {
            var cat = document.getElementById('dupaEditCategory');
            var sowSelect = document.getElementById('dupaEditSowSelect');
            if (cat && cat.value === 'materials' && sowSelect && !sowSelect.value && sowSelect.options.length) {
                sowSelect.value = sowSelect.options[0].value;
            }
        });
    }
});
</script>

<?php
// Tab: Materials Monitoring — actual materials + daily withdrawal with quantity/price, balance, remarks.
// Expects $pdo, $project, $id from view.php.

$materials = [];
$withdrawalsByKey = []; // [dupa_item_id][withdrawal_date] => ['quantity' => x, 'unit_cost' => y, 'id' => z]
$allDates = [];
$withdrawalsTableExists = false;
$hasDupaRemarks = false;

try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_material_withdrawals'");
    $withdrawalsTableExists = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    $withdrawalsTableExists = false;
}

$colsDupa = null;
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

    $hasQuantityRequested = false;
    try {
        $colReq = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
        $hasQuantityRequested = $colReq && $colReq->rowCount() > 0;
    } catch (Exception $e) {}
    $stmt = $pdo->prepare("SELECT * FROM project_material_withdrawals WHERE project_id = ?");
    $stmt->execute([$id]);
    $withdrawals = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($withdrawals as $w) {
        $did = (int)$w['dupa_item_id'];
        $d = $w['withdrawal_date'];
        if (!isset($withdrawalsByKey[$did])) {
            $withdrawalsByKey[$did] = [];
        }
        $withdrawalsByKey[$did][$d] = [
            'quantity'  => (float)$w['quantity'],
            'quantity_requested' => $hasQuantityRequested && isset($w['quantity_requested']) && $w['quantity_requested'] !== null ? (float)$w['quantity_requested'] : null,
            'unit_cost' => $w['unit_cost'] !== null ? (float)$w['unit_cost'] : null,
            'remarks'   => isset($w['remarks']) ? (string)$w['remarks'] : '',
            'id'        => (int)$w['id'],
        ];
    }

    $rangeStart = $project['notice_to_proceed'] ?? $project['start_date'];
    $rangeEnd   = $project['target_end_date'];
    if (!$rangeStart) $rangeStart = date('Y-m-d', strtotime('-1 month'));
    if (!$rangeEnd)   $rangeEnd   = date('Y-m-d', strtotime('+3 months'));
    $startTs = strtotime($rangeStart);
    $endTs   = strtotime($rangeEnd);
    foreach ($withdrawals as $w) {
        $t = strtotime($w['withdrawal_date']);
        if ($t < $startTs) $startTs = $t;
        if ($t > $endTs)   $endTs   = $t;
    }
    for ($d = $startTs; $d <= $endTs; $d += 86400) {
        $allDates[] = date('Y-m-d', $d);
    }
}

$projectName = $project['title'] ?? '';
$materialSupplier = $project['material_supplier'] ?? '';
$materialPoNumber = $project['material_po_number'] ?? '';
$materialTermDays  = $project['material_term_days'] ?? '';
$materialAllotedAmount = $project['material_alloted_amount'] ?? '';
?>

<div class="materials-monitoring-tab">
    <?php if (!$withdrawalsTableExists): ?>
        <div class="alert alert-warning">
            <strong>Materials monitoring is not set up.</strong> Run <code>database/migration_material_withdrawals.sql</code> in your database (e.g. via phpMyAdmin) first.
        </div>
    <?php else: ?>

        <!-- Log header (like Excel) -->
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white d-flex justify-content-between align-items-center">
                <h6 class="mb-0 section-title">Material log header</h6>
                <button type="button" class="btn btn-outline-secondary btn-sm" data-bs-toggle="modal" data-bs-target="#materialLogHeaderModal">
                    <i class="bi bi-pencil me-1"></i> Edit
                </button>
            </div>
            <div class="card-body">
                <div class="row g-3">
                    <div class="col-md-6">
                        <label class="text-muted small">Project name</label>
                        <div class="fw-medium"><?= sanitize($projectName) ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Supplier</label>
                        <div class="fw-medium"><?= $materialSupplier !== '' ? sanitize($materialSupplier) : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Alloted amount</label>
                        <div class="fw-medium"><?= $materialAllotedAmount !== '' && $materialAllotedAmount !== null ? formatCurrency($materialAllotedAmount) : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">P.O number</label>
                        <div class="fw-medium"><?= $materialPoNumber !== '' ? sanitize($materialPoNumber) : '—' ?></div>
                    </div>
                    <div class="col-md-6">
                        <label class="text-muted small">Term days</label>
                        <div class="fw-medium"><?= $materialTermDays !== '' && $materialTermDays !== null ? (int)$materialTermDays : '—' ?></div>
                    </div>
                </div>
            </div>
        </div>

        <?php if (empty($materials)): ?>
            <div class="alert alert-info">
                No materials defined. Add materials in the <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=dupa">DUPA</a> tab (Materials section) first, then return here to record daily withdrawals.
            </div>
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
                            <?php foreach ($allDates as $d): ?>
                                <th class="text-end delivery-col" style="min-width: 90px;" title="<?= formatDate($d) ?> — Req / Del"><?= date('d-M-y', strtotime($d)) ?><br><span class="small fw-normal text-muted">Req / Del</span></th>
                            <?php endforeach; ?>
                            <th class="text-end" style="width: 8%;">Balance</th>
                            <?php if ($hasDupaRemarks): ?>
                                <th style="min-width: 120px;">Remarks</th>
                            <?php endif; ?>
                        </tr>
                    </thead>
                    <tbody>
                        <?php
                        foreach ($materials as $idx => $mat):
                            $dupaId   = (int)$mat['id'];
                            $qty      = (float)$mat['quantity'];
                            $rate     = (float)($mat['rate'] ?? 0);
                            $amt      = (float)($mat['amount'] ?? $qty * $rate);
                            $unit     = $mat['unit'] ?? 'pcs';
                            $withdrawn = 0;
                            if (isset($withdrawalsByKey[$dupaId])) {
                                foreach ($withdrawalsByKey[$dupaId] as $w) {
                                    $withdrawn += (float)$w['quantity'];
                                }
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
                                <?php foreach ($allDates as $d): ?>
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
                                        <button type="button" class="btn btn-link btn-sm p-0 text-decoration-none material-withdrawal-btn <?= $hasCell ? '' : 'text-muted' ?>"
                                                data-bs-toggle="modal" data-bs-target="#withdrawalModal"
                                                data-dupa-id="<?= $dupaId ?>"
                                                data-date="<?= $d ?>"
                                                data-quantity="<?= $cellQty !== '' ? $cellQty : '' ?>"
                                                data-quantity-requested="<?= $cellReq !== '' ? $cellReq : '' ?>"
                                                data-unit-cost="<?= $cellUnitCost ?>"
                                                data-remarks="<?= sanitize($cellRemarks) ?>"
                                                data-withdrawal-id="<?= $cellId ?>"
                                                data-description="<?= sanitize($mat['description']) ?>"
                                                title="<?= $cellRemarks !== '' ? sanitize($cellRemarks) : 'Requested / Delivered' ?>">
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
    <?php endif; ?>
</div>

<!-- Log header edit modal -->
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

<!-- Withdrawal (daily delivery) modal -->
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

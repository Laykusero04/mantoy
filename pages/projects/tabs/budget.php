<?php
// Tab: Budget — receives $pdo, $project, $id, $activeTab from view.php

// Fetch budget data
$budgetStmt = $pdo->prepare("SELECT * FROM budget_items WHERE project_id = ? ORDER BY id");
$budgetStmt->execute([$id]);
$budgetItems = $budgetStmt->fetchAll();
$estimatedTotal = array_sum(array_column($budgetItems, 'total_cost'));
?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Budget Breakdown</h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addBudgetModal">
            <i class="bi bi-plus-lg me-1"></i> Add Item
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($budgetItems)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-wallet2 fs-1 d-block mb-2"></i>
                <p>No budget items yet.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th>Item Name</th>
                        <th>Category</th>
                        <th class="text-end">Qty</th>
                        <th>Unit</th>
                        <th class="text-end">Unit Cost</th>
                        <th class="text-end">Total</th>
                        <th style="width:10%"></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($budgetItems as $i => $item): ?>
                    <tr>
                        <td class="text-muted"><?= $i + 1 ?></td>
                        <td class="fw-medium"><?= sanitize($item['item_name']) ?></td>
                        <td><span class="badge bg-secondary"><?= sanitize($item['budget_category']) ?></span></td>
                        <td class="text-end"><?= number_format($item['quantity'], 2) ?></td>
                        <td><?= sanitize($item['unit']) ?></td>
                        <td class="text-end"><?= formatCurrency($item['unit_cost']) ?></td>
                        <td class="text-end fw-bold"><?= formatCurrency($item['total_cost']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary btn-edit-budget"
                                        data-bs-toggle="modal" data-bs-target="#editBudgetModal"
                                        data-id="<?= $item['id'] ?>"
                                        data-name="<?= sanitize($item['item_name']) ?>"
                                        data-category="<?= sanitize($item['budget_category']) ?>"
                                        data-quantity="<?= $item['quantity'] ?>"
                                        data-unit="<?= sanitize($item['unit']) ?>"
                                        data-cost="<?= $item['unit_cost'] ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this item?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="project_id" value="<?= $id ?>">
                                    <input type="hidden" name="item_id" value="<?= $item['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="table-light">
                        <td colspan="6" class="text-end fw-bold">TOTAL:</td>
                        <td class="text-end fw-bold"><?= formatCurrency($estimatedTotal) ?></td>
                        <td></td>
                    </tr>
                </tfoot>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Budget Summary Cards -->
<div class="row g-3 mt-2">
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-primary border-3">
            <div class="card-body text-center">
                <div class="small text-muted">Allocated</div>
                <div class="fs-5 fw-bold"><?= formatCurrency($project['budget_allocated']) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <div class="card shadow-sm border-start border-info border-3">
            <div class="card-body text-center">
                <div class="small text-muted">Estimated (Line Items)</div>
                <div class="fs-5 fw-bold"><?= formatCurrency($estimatedTotal) ?></div>
            </div>
        </div>
    </div>
    <div class="col-md-4">
        <?php
        $variance = $project['budget_allocated'] - $estimatedTotal;
        $varClass = $variance >= 0 ? 'success' : 'danger';
        $varLabel = $variance >= 0 ? 'Under Budget' : 'Over Budget';
        ?>
        <div class="card shadow-sm border-start border-<?= $varClass ?> border-3">
            <div class="card-body text-center">
                <div class="small text-muted">Variance</div>
                <div class="fs-5 fw-bold text-<?= $varClass ?>"><?= formatCurrency(abs($variance)) ?></div>
                <div class="small text-<?= $varClass ?>">(<?= $varLabel ?>)</div>
            </div>
        </div>
    </div>
</div>

<!-- Add Budget Modal -->
<div class="modal fade" id="addBudgetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Add Budget Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="budget_category">
                                <option value="MOOE">MOOE</option>
                                <option value="Capital Outlay">Capital Outlay</option>
                                <option value="Personnel Services">Personnel Services</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" step="0.01" min="0" value="1">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" value="pcs">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Unit Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" class="form-control" name="unit_cost" step="0.01" min="0" value="0.00">
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Budget Modal -->
<div class="modal fade" id="editBudgetModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/budget_actions.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="item_id" id="editBudgetId" value="">
                <div class="modal-header">
                    <h6 class="modal-title">Edit Budget Item</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Item Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="item_name" id="editBudgetName" required>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Category</label>
                            <select class="form-select" name="budget_category" id="editBudgetCategory">
                                <option value="MOOE">MOOE</option>
                                <option value="Capital Outlay">Capital Outlay</option>
                                <option value="Personnel Services">Personnel Services</option>
                            </select>
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Quantity</label>
                            <input type="number" class="form-control" name="quantity" id="editBudgetQty" step="0.01" min="0">
                        </div>
                        <div class="col-md-3">
                            <label class="form-label">Unit</label>
                            <input type="text" class="form-control" name="unit" id="editBudgetUnit">
                        </div>
                    </div>
                    <div class="mt-3">
                        <label class="form-label">Unit Cost</label>
                        <div class="input-group">
                            <span class="input-group-text">&#8369;</span>
                            <input type="number" class="form-control" name="unit_cost" id="editBudgetCost" step="0.01" min="0">
                        </div>
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

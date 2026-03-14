<?php
// Tab: Action Log — receives $pdo, $project, $id, $activeTab from view.php

// Fetch log data
$logStmt = $pdo->prepare("SELECT * FROM action_logs WHERE project_id = ? ORDER BY log_date DESC, id DESC");
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll();

// Group by date (one date, scope of works inside)
$byDate = [];
foreach ($logs as $log) {
    $d = date('Y-m-d', strtotime($log['log_date']));
    if (!isset($byDate[$d])) $byDate[$d] = [];
    $byDate[$d][] = $log;
}
?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Action Log</h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addLogModal">
            <i class="bi bi-plus-lg me-1"></i> Add Entry
        </button>
    </div>
    <div class="card-body">
        <?php if (empty($logs)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-journal-text fs-1 d-block mb-2"></i>
                <p>No log entries yet.</p>
            </div>
        <?php else: ?>
            <?php foreach ($byDate as $dateStr => $entries): ?>
                <div class="mb-4">
                    <div class="fw-bold small text-muted border-bottom pb-1 mb-3"><?= formatDate($dateStr) ?></div>
                    <?php foreach ($entries as $log):
                        $isSow = !empty($log['is_scope_of_work']);
                        $qty = isset($log['quantity']) ? (float)$log['quantity'] : null;
                        $qtyDel = isset($log['quantity_delivered']) ? (float)$log['quantity_delivered'] : null;
                        $remaining = ($qty !== null && $qtyDel !== null) ? max(0, $qty - $qtyDel) : null;
                    ?>
                    <div class="border rounded mb-2 <?= $isSow ? 'border-success' : '' ?>" style="background:#fff;">
                        <?php if ($isSow): ?>
                            <div class="p-2 px-3 border-bottom bg-light">
                                <div class="fw-bold text-dark"><?= sanitize($log['action_taken']) ?></div>
                            </div>
                            <div class="p-3">
                                <?php if (!empty($log['notes'])): ?>
                                    <div class="text-muted small mb-2"><?= nl2br(sanitize($log['notes'])) ?></div>
                                <?php endif; ?>
                                <?php if ($qty !== null || isset($log['unit']) || isset($log['unit_cost']) || isset($log['amount'])): ?>
                                    <div class="small mb-2">
                                        <span class="text-muted">Quantity:</span> <?= $qty !== null ? number_format($qty, 2) : '—' ?>
                                        <span class="text-muted ms-2">Unit:</span> <?= isset($log['unit']) ? sanitize($log['unit']) : '—' ?>
                                        <span class="text-muted ms-2">Unit cost:</span> <?= isset($log['unit_cost']) ? formatCurrency($log['unit_cost']) : '—' ?>
                                        <span class="text-muted ms-2">Amount:</span> <?= isset($log['amount']) ? formatCurrency($log['amount']) : '—' ?>
                                    </div>
                                    <div class="d-flex align-items-center flex-wrap gap-2">
                                        <form action="<?= BASE_URL ?>/actions/action_log_actions.php" method="POST" class="d-inline-flex align-items-center gap-2">
                                            <input type="hidden" name="action" value="update_delivered">
                                            <input type="hidden" name="project_id" value="<?= $id ?>">
                                            <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                            <label class="small text-muted mb-0">Quantity delivered:</label>
                                            <input type="number" name="quantity_delivered" class="form-control form-control-sm" style="width:100px" step="0.01" min="0" value="<?= $qtyDel !== null ? (string)$qtyDel : '' ?>" placeholder="0">
                                            <button type="submit" class="btn btn-sm btn-outline-primary">Save</button>
                                        </form>
                                        <?php if ($qtyDel !== null && $qty !== null): ?>
                                            <span class="small text-muted">→ <strong><?= number_format($qtyDel, 2) ?> of <?= number_format($qty, 2) ?> delivered</strong><?= $remaining !== null && $remaining > 0 ? ' · Remaining: ' . number_format($remaining, 2) : '' ?></span>
                                        <?php endif; ?>
                                    </div>
                                <?php endif; ?>
                                <div class="d-flex justify-content-end mt-2">
                                    <form action="<?= BASE_URL ?>/actions/action_log_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this entry?')">
                                        <input type="hidden" name="action" value="delete">
                                        <input type="hidden" name="project_id" value="<?= $id ?>">
                                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete</button>
                                    </form>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="p-3 d-flex justify-content-between align-items-start">
                                <div class="flex-grow-1">
                                    <div class="fw-medium"><?= sanitize($log['action_taken']) ?></div>
                                    <?php if (!empty($log['notes'])): ?>
                                        <div class="text-muted small mt-1"><?= nl2br(sanitize($log['notes'])) ?></div>
                                    <?php endif; ?>
                                    <?php if (!empty($log['logged_by'])): ?>
                                        <div class="text-muted small mt-1"><?= sanitize($log['logged_by']) ?></div>
                                    <?php endif; ?>
                                </div>
                                <form action="<?= BASE_URL ?>/actions/action_log_actions.php" method="POST" class="d-inline ms-2" onsubmit="return confirm('Delete this entry?')">
                                    <input type="hidden" name="action" value="delete">
                                    <input type="hidden" name="project_id" value="<?= $id ?>">
                                    <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Add Log Modal -->
<div class="modal fade" id="addLogModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/action_log_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Log Progress Update</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Action Taken <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="action_taken" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="3"></textarea>
                    </div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="form-label">Logged By</label>
                            <input type="text" class="form-control" name="logged_by">
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="datetime-local" class="form-control" name="log_date"
                                   value="<?= date('Y-m-d\TH:i') ?>" required>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save Entry</button>
                </div>
            </form>
        </div>
    </div>
</div>

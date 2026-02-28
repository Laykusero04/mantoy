<?php
// Tab: Action Log — receives $pdo, $project, $id, $activeTab from view.php

// Fetch log data
$logStmt = $pdo->prepare("SELECT * FROM action_logs WHERE project_id = ? ORDER BY log_date DESC, id DESC");
$logStmt->execute([$id]);
$logs = $logStmt->fetchAll();
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
            <?php foreach ($logs as $log): ?>
            <div class="border-start border-3 border-primary ps-3 mb-4">
                <div class="d-flex justify-content-between align-items-start">
                    <div>
                        <div class="fw-bold small text-muted">
                            <?= formatDate($log['log_date']) ?>
                            <?php if ($log['logged_by']): ?>
                                &mdash; <?= sanitize($log['logged_by']) ?>
                            <?php endif; ?>
                        </div>
                        <div class="fw-medium mt-1"><?= sanitize($log['action_taken']) ?></div>
                        <?php if ($log['notes']): ?>
                            <div class="text-muted small mt-1"><?= nl2br(sanitize($log['notes'])) ?></div>
                        <?php endif; ?>
                    </div>
                    <form action="<?= BASE_URL ?>/actions/action_log_actions.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this entry?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= $id ?>">
                        <input type="hidden" name="log_id" value="<?= $log['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
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

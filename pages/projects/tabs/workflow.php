<?php
// Tab: Workflow — receives $pdo, $project, $id, $activeTab from view.php

// Fetch workflow data
$wfStmt = $pdo->prepare("SELECT * FROM workflow_stages WHERE project_id = ? ORDER BY stage_order");
$wfStmt->execute([$id]);
$workflowStages = $wfStmt->fetchAll();

// If no stages exist (legacy project), create them
if (empty($workflowStages)) {
    initWorkflowStages($pdo, $id);
    $wfStmt->execute([$id]);
    $workflowStages = $wfStmt->fetchAll();
}

// Fetch attachments for all stages
$wfAttStmt = $pdo->prepare("SELECT * FROM workflow_attachments WHERE project_id = ? ORDER BY uploaded_at DESC");
$wfAttStmt->execute([$id]);
$allWfAttachments = $wfAttStmt->fetchAll();

// Group attachments by stage_id
$wfAttByStage = [];
foreach ($allWfAttachments as $att) {
    $wfAttByStage[$att['stage_id']][] = $att;
}

$wfProgress = getWorkflowProgress($workflowStages);
?>

<!-- Progress Bar -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="fw-medium">Workflow Progress</span>
            <span class="fw-bold"><?= $wfProgress ?>%</span>
        </div>
        <div class="progress" style="height: 10px;">
            <div class="progress-bar bg-<?= $wfProgress >= 100 ? 'success' : ($wfProgress > 0 ? 'info' : 'secondary') ?>"
                 style="width: <?= $wfProgress ?>%"></div>
        </div>
        <small class="text-muted mt-1 d-block">
            <?= count(array_filter($workflowStages, fn($s) => $s['status'] === 'Completed')) ?> of <?= count($workflowStages) ?> stages completed
        </small>
    </div>
</div>

<!-- Workflow Stepper -->
<div class="workflow-stepper">
<?php foreach ($workflowStages as $i => $stage):
    $stageAtts = $wfAttByStage[$stage['id']] ?? [];
    $isStatusStage = ($stage['stage_type'] === 'Status');
    $statusClass = match($stage['status']) {
        'Completed' => 'border-success',
        'In Progress' => 'border-info',
        default => 'border-secondary',
    };
    $stepIcon = match($stage['status']) {
        'Completed' => '<i class="bi bi-check-circle-fill text-success"></i>',
        'In Progress' => '<i class="bi bi-circle-fill text-info"></i>',
        default => '<i class="bi bi-circle text-secondary"></i>',
    };
?>
    <div class="card shadow-sm mb-3 border-start border-4 <?= $statusClass ?>">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <div class="d-flex align-items-center gap-2">
                <?= $stepIcon ?>
                <h6 class="mb-0">Step <?= $stage['stage_order'] ?>: <?= sanitize($stage['stage_type']) ?></h6>
            </div>
            <span class="badge bg-<?= match($stage['status']) { 'Completed' => 'success', 'In Progress' => 'info', default => 'secondary' } ?>">
                <?= sanitize($stage['status']) ?>
            </span>
        </div>
        <div class="card-body">
            <!-- Update Stage Form -->
            <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" class="mb-3">
                <input type="hidden" name="action" value="update_stage">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">

                <div class="row g-3">
                    <div class="col-md-3">
                        <label class="form-label small">Status</label>
                        <select name="stage_status" class="form-select form-select-sm">
                            <?php foreach (['Pending', 'In Progress', 'Completed'] as $ss): ?>
                                <option value="<?= $ss ?>" <?= $stage['status'] === $ss ? 'selected' : '' ?>><?= $ss ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="col-md-7">
                        <label class="form-label small">Notes</label>
                        <textarea name="notes" class="form-control form-control-sm" rows="2"><?= sanitize($stage['notes'] ?? '') ?></textarea>
                    </div>
                    <div class="col-md-2 d-flex align-items-end">
                        <button type="submit" class="btn btn-primary btn-sm w-100">
                            <i class="bi bi-check-lg me-1"></i> Save
                        </button>
                    </div>
                </div>
                <?php if ($stage['completed_at']): ?>
                    <small class="text-muted mt-1 d-block">Completed: <?= formatDate($stage['completed_at']) ?></small>
                <?php endif; ?>
            </form>

            <?php if (!$isStatusStage): ?>
            <!-- Files Section (not for Status stage) -->
            <hr class="my-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 small fw-bold text-muted">
                    <i class="bi bi-paperclip me-1"></i> Attachments (<?= count($stageAtts) ?>)
                </h6>
                <button type="button" class="btn btn-outline-primary btn-sm" data-bs-toggle="modal"
                        data-bs-target="#wfUploadModal" data-stage-id="<?= $stage['id'] ?>">
                    <i class="bi bi-upload me-1"></i> Upload
                </button>
            </div>

            <?php if (!empty($stageAtts)): ?>
            <div class="table-responsive">
                <table class="table table-sm table-hover mb-0">
                    <thead><tr><th>File</th><th>Size</th><th>Uploaded</th><th></th></tr></thead>
                    <tbody>
                    <?php foreach ($stageAtts as $att):
                        $slug = stageSlug($stage['stage_type']);
                        $fileUrl = BASE_URL . '/uploads/' . $id . '/workflow/' . $slug . '/' . $att['file_name'];
                    ?>
                        <tr>
                            <td>
                                <a href="<?= $fileUrl ?>" target="_blank" class="text-decoration-none">
                                    <i class="bi bi-file-earmark me-1"></i><?= sanitize($att['file_original_name']) ?>
                                </a>
                            </td>
                            <td class="small text-muted"><?= number_format($att['file_size'] / 1024, 1) ?> KB</td>
                            <td class="small text-muted"><?= formatDate($att['uploaded_at']) ?></td>
                            <td>
                                <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this file?')">
                                    <input type="hidden" name="action" value="delete_file">
                                    <input type="hidden" name="project_id" value="<?= $id ?>">
                                    <input type="hidden" name="file_id" value="<?= $att['id'] ?>">
                                    <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1">
                                        <i class="bi bi-trash"></i>
                                    </button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
            <?php else: ?>
                <p class="text-muted small mb-0">No files attached yet.</p>
            <?php endif; ?>
            <?php endif; ?>
        </div>
    </div>
<?php endforeach; ?>
</div>

<!-- Workflow File Upload Modal -->
<div class="modal fade" id="wfUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="stage_id" id="wfUploadStageId" value="">
                <div class="modal-header">
                    <h6 class="modal-title">Upload File</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Files <span class="text-danger">*</span></label>
                        <input type="file" name="files[]" class="form-control" multiple required
                               accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                        <div class="form-text">Max 50MB per file. Select multiple files. Allowed: <?= strtoupper(implode(', ', ALLOWED_EXTENSIONS)) ?></div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description</label>
                        <input type="text" name="description" class="form-control" placeholder="Optional description">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Upload</button>
                </div>
            </form>
        </div>
    </div>
</div>

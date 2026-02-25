<?php
$pageTitle = 'Project Details';
require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('danger', 'Invalid project ID.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Fetch project with category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM projects p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.is_deleted = 0
");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    flashMessage('danger', 'Project not found.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Budget utilization
$utilization = $project['budget_allocated'] > 0
    ? round(($project['actual_cost'] / $project['budget_allocated']) * 100, 1)
    : 0;

// Active tab
$activeTab = $_GET['tab'] ?? 'overview';

// Status badge
function viewStatusBadge($status) {
    $map = [
        'Not Started' => 'bg-secondary',
        'In Progress' => 'bg-info',
        'On Hold'     => 'bg-warning text-dark',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
    ];
    return $map[$status] ?? 'bg-secondary';
}

function viewPriorityBadge($priority) {
    $map = ['High' => 'badge-high', 'Medium' => 'badge-medium', 'Low' => 'badge-low'];
    return $map[$priority] ?? 'bg-secondary';
}

// ── Fetch tab-specific data ────────────────────────────────
if ($activeTab === 'workflow') {
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
}

if ($activeTab === 'budget') {
    $budgetStmt = $pdo->prepare("SELECT * FROM budget_items WHERE project_id = ? ORDER BY id");
    $budgetStmt->execute([$id]);
    $budgetItems = $budgetStmt->fetchAll();
    $estimatedTotal = array_sum(array_column($budgetItems, 'total_cost'));
}

if ($activeTab === 'milestones') {
    $msStmt = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY sort_order, id");
    $msStmt->execute([$id]);
    $milestones = $msStmt->fetchAll();
    $totalMilestones = count($milestones);
    $doneMilestones  = count(array_filter($milestones, fn($m) => $m['status'] === 'Done'));
    $milestonePercent = $totalMilestones > 0 ? round(($doneMilestones / $totalMilestones) * 100) : 0;
}

if ($activeTab === 'log') {
    $logStmt = $pdo->prepare("SELECT * FROM action_logs WHERE project_id = ? ORDER BY log_date DESC, id DESC");
    $logStmt->execute([$id]);
    $logs = $logStmt->fetchAll();
}

if ($activeTab === 'files') {
    $fileStmt = $pdo->prepare("SELECT * FROM attachments WHERE project_id = ? ORDER BY uploaded_at DESC");
    $fileStmt->execute([$id]);
    $attachments = $fileStmt->fetchAll();
    $totalFileSize = array_sum(array_column($attachments, 'file_size'));
}
?>

<!-- Header -->
<div class="mb-4">
    <a href="<?= BASE_URL ?>/pages/projects/list.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Projects
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-2"><?= sanitize($project['title']) ?></h4>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge <?= getClassificationBadgeClass($project) ?>"><?= sanitize(getClassificationLabel($project)) ?></span>
                <span class="badge <?= viewStatusBadge($project['status']) ?>"><?= sanitize($project['status']) ?></span>
                <span class="badge <?= viewPriorityBadge($project['priority']) ?>"><?= sanitize($project['priority']) ?> Priority</span>
                <?php if (isOverdue($project)): ?>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Overdue</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="duplicate">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-copy me-1"></i> Duplicate
                </button>
            </form>
            <?php if (!$project['is_archived']): ?>
            <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning"
                        onclick="return confirm('Archive this project?')">
                    <i class="bi bi-archive me-1"></i> Archive
                </button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Tabs -->
<ul class="nav nav-tabs mb-4">
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'overview' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=overview">Overview</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'workflow' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=workflow">Workflow</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'budget' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=budget">Budget</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'milestones' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=milestones">Milestones</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'log' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=log">Action Log</a>
    </li>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === 'files' ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=files">Files</a>
    </li>
</ul>

<!-- ════════════════════════════════════════════════════════════
     TAB: OVERVIEW
     ════════════════════════════════════════════════════════════ -->
<?php if ($activeTab === 'overview'): ?>

    <div class="row g-4">
        <div class="col-lg-8">
            <div class="card shadow-sm">
                <div class="card-header bg-white">
                    <h6 class="mb-0 section-title">Project Details</h6>
                </div>
                <div class="card-body">
                    <table class="table table-borderless mb-0">
                        <tbody>
                            <tr><td class="text-muted" style="width:35%">Visibility</td><td><span class="badge <?= $project['visibility'] === 'Private' ? 'bg-dark' : 'bg-success' ?>"><?= sanitize($project['visibility']) ?></span></td></tr>
                            <?php if ($project['visibility'] === 'Public' && !empty($project['department'])): ?>
                            <tr><td class="text-muted">Department</td><td><?= sanitize($project['department']) ?></td></tr>
                            <tr><td class="text-muted">Sub-type</td><td><?= sanitize($project['project_subtype'] ?? '—') ?></td></tr>
                            <?php endif; ?>
                            <tr><td class="text-muted">Category</td><td><?= sanitize($project['category_name'] ?? '—') ?></td></tr>
                            <tr><td class="text-muted">Funding Source</td><td><?= sanitize($project['funding_source'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted">Resolution No.</td><td><?= sanitize($project['resolution_no'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted">Location</td><td><?= sanitize($project['location'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted">Contact Person</td><td><?= sanitize($project['contact_person'] ?: '—') ?></td></tr>
                            <tr><td class="text-muted">Start Date</td><td><?= formatDate($project['start_date']) ?></td></tr>
                            <tr>
                                <td class="text-muted">Target End Date</td>
                                <td>
                                    <?= formatDate($project['target_end_date']) ?>
                                    <?php if (isOverdue($project)): ?>
                                        <span class="text-danger small ms-1">(Overdue)</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php if ($project['actual_end_date']): ?>
                            <tr><td class="text-muted">Actual End Date</td><td><?= formatDate($project['actual_end_date']) ?></td></tr>
                            <?php endif; ?>
                            <tr><td class="text-muted">Fiscal Year</td><td><?= sanitize($project['fiscal_year']) ?></td></tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <?php if (!empty($project['description'])): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0 section-title">Description</h6></div>
                <div class="card-body"><p class="mb-0"><?= nl2br(sanitize($project['description'])) ?></p></div>
            </div>
            <?php endif; ?>

            <?php if (!empty($project['remarks'])): ?>
            <div class="card shadow-sm mt-3">
                <div class="card-header bg-white"><h6 class="mb-0 section-title">Remarks</h6></div>
                <div class="card-body"><p class="mb-0"><?= nl2br(sanitize($project['remarks'])) ?></p></div>
            </div>
            <?php endif; ?>
        </div>

        <div class="col-lg-4">
            <div class="card shadow-sm">
                <div class="card-header bg-white"><h6 class="mb-0 section-title">Budget Overview</h6></div>
                <div class="card-body">
                    <div class="mb-3">
                        <div class="small text-muted">Budget Allocated</div>
                        <div class="fs-5 fw-bold"><?= formatCurrency($project['budget_allocated']) ?></div>
                    </div>
                    <div class="mb-3">
                        <div class="small text-muted">Actual Cost</div>
                        <div class="fs-5 fw-bold"><?= formatCurrency($project['actual_cost']) ?></div>
                    </div>
                    <div>
                        <div class="d-flex justify-content-between small mb-1">
                            <span class="text-muted">Utilization</span>
                            <span class="fw-bold"><?= $utilization ?>%</span>
                        </div>
                        <div class="progress" style="height: 8px;">
                            <div class="progress-bar bg-<?= $utilization > 90 ? 'danger' : ($utilization > 70 ? 'warning' : 'success') ?>"
                                 style="width: <?= min($utilization, 100) ?>%"></div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="card shadow-sm mt-3">
                <div class="card-body small text-muted">
                    <div class="d-flex justify-content-between mb-1">
                        <span>Created</span><span><?= formatDate($project['created_at']) ?></span>
                    </div>
                    <div class="d-flex justify-content-between">
                        <span>Last Updated</span><span><?= formatDate($project['updated_at']) ?></span>
                    </div>
                </div>
            </div>
        </div>
    </div>

<!-- ════════════════════════════════════════════════════════════
     TAB: WORKFLOW
     ════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'workflow'): ?>

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
                            <label class="form-label">File <span class="text-danger">*</span></label>
                            <input type="file" name="file" class="form-control" required>
                            <div class="form-text">Max 10MB. Allowed: <?= implode(', ', ALLOWED_EXTENSIONS) ?></div>
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

<!-- ════════════════════════════════════════════════════════════
     TAB: BUDGET
     ════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'budget'): ?>

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
                                <span class="input-group-text">₱</span>
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
                                <span class="input-group-text">₱</span>
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

<!-- ════════════════════════════════════════════════════════════
     TAB: MILESTONES
     ════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'milestones'): ?>

    <!-- Progress Bar -->
    <div class="card shadow-sm mb-3">
        <div class="card-body">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <span class="section-title">Progress: <?= $doneMilestones ?>/<?= $totalMilestones ?> milestones</span>
                <span class="fw-bold"><?= $milestonePercent ?>%</span>
            </div>
            <div class="progress" style="height: 10px;">
                <div class="progress-bar bg-success" style="width: <?= $milestonePercent ?>%"></div>
            </div>
        </div>
    </div>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 section-title">Milestones</h6>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
                <i class="bi bi-plus-lg me-1"></i> Add Milestone
            </button>
        </div>
        <div class="card-body p-0">
            <?php if (empty($milestones)): ?>
                <div class="text-center text-muted py-5">
                    <i class="bi bi-flag fs-1 d-block mb-2"></i>
                    <p>No milestones yet.</p>
                </div>
            <?php else: ?>
            <ul class="list-group list-group-flush">
                <?php foreach ($milestones as $ms): ?>
                <li class="list-group-item d-flex justify-content-between align-items-center">
                    <div class="d-flex align-items-center gap-3">
                        <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST" class="d-inline">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="project_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="Toggle status">
                                <?php if ($ms['status'] === 'Done'): ?>
                                    <i class="bi bi-check-circle-fill text-success fs-5"></i>
                                <?php else: ?>
                                    <i class="bi bi-circle text-muted fs-5"></i>
                                <?php endif; ?>
                            </button>
                        </form>
                        <div>
                            <div class="fw-medium <?= $ms['status'] === 'Done' ? 'text-decoration-line-through text-muted' : '' ?>">
                                <?= sanitize($ms['title']) ?>
                            </div>
                            <div class="small text-muted">
                                <?php if ($ms['target_date']): ?>
                                    Target: <?= formatDate($ms['target_date']) ?>
                                <?php endif; ?>
                                <?php if ($ms['completed_date']): ?>
                                    &middot; Completed: <?= formatDate($ms['completed_date']) ?>
                                <?php endif; ?>
                                <?php if ($ms['notes']): ?>
                                    &middot; <?= sanitize($ms['notes']) ?>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <div class="d-flex gap-1 align-items-center">
                        <span class="badge <?= $ms['status'] === 'Done' ? 'bg-success' : 'bg-secondary' ?> me-2"><?= $ms['status'] ?></span>
                        <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST" class="d-inline"
                              onsubmit="return confirm('Delete this milestone?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="project_id" value="<?= $id ?>">
                            <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                        </form>
                    </div>
                </li>
                <?php endforeach; ?>
            </ul>
            <?php endif; ?>
        </div>
    </div>

    <!-- Add Milestone Modal -->
    <div class="modal fade" id="addMilestoneModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <input type="hidden" name="project_id" value="<?= $id ?>">
                    <div class="modal-header">
                        <h6 class="modal-title">Add Milestone</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Title <span class="text-danger">*</span></label>
                            <input type="text" class="form-control" name="title" required>
                        </div>
                        <div class="mb-3">
                            <label class="form-label">Target Date</label>
                            <input type="date" class="form-control" name="target_date">
                        </div>
                        <div>
                            <label class="form-label">Notes</label>
                            <textarea class="form-control" name="notes" rows="2"></textarea>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-primary btn-sm">Add Milestone</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

<!-- ════════════════════════════════════════════════════════════
     TAB: ACTION LOG
     ════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'log'): ?>

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

<!-- ════════════════════════════════════════════════════════════
     TAB: FILES
     ════════════════════════════════════════════════════════════ -->
<?php elseif ($activeTab === 'files'): ?>

    <div class="card shadow-sm">
        <div class="card-header bg-white d-flex justify-content-between align-items-center">
            <h6 class="mb-0 section-title">
                Attachments
                <?php if (!empty($attachments)): ?>
                    <span class="badge bg-secondary ms-1"><?= count($attachments) ?></span>
                <?php endif; ?>
            </h6>
            <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
                <i class="bi bi-upload me-1"></i> Upload File
            </button>
        </div>
        <div class="card-body">
            <?php if (empty($attachments)): ?>
                <div class="text-center text-muted py-4">
                    <i class="bi bi-paperclip fs-1 d-block mb-2"></i>
                    <p>No files attached.</p>
                </div>
            <?php else: ?>
                <div class="row g-3">
                    <?php foreach ($attachments as $att):
                        $ext = strtolower(pathinfo($att['file_original_name'], PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
                        $fileUrl = BASE_URL . '/uploads/' . $id . '/' . $att['file_name'];
                        $sizeKB = round($att['file_size'] / 1024, 1);
                        $sizeLabel = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';

                        $icon = 'bi-file-earmark';
                        if ($isImage) $icon = 'bi-file-earmark-image';
                        elseif ($ext === 'pdf') $icon = 'bi-file-earmark-pdf';
                        elseif (in_array($ext, ['doc', 'docx'])) $icon = 'bi-file-earmark-word';
                        elseif (in_array($ext, ['xls', 'xlsx'])) $icon = 'bi-file-earmark-excel';
                    ?>
                    <div class="col-md-4 col-lg-3">
                        <div class="card h-100 border">
                            <?php if ($isImage): ?>
                                <a href="<?= $fileUrl ?>" target="_blank">
                                    <img src="<?= $fileUrl ?>" class="card-img-top" alt="<?= sanitize($att['file_original_name']) ?>"
                                         style="height: 140px; object-fit: cover;">
                                </a>
                            <?php else: ?>
                                <a href="<?= $fileUrl ?>" target="_blank" class="d-flex align-items-center justify-content-center bg-light" style="height: 140px;">
                                    <i class="bi <?= $icon ?> fs-1 text-muted"></i>
                                </a>
                            <?php endif; ?>
                            <div class="card-body p-2">
                                <div class="small fw-medium text-truncate" title="<?= sanitize($att['file_original_name']) ?>">
                                    <?= sanitize($att['file_original_name']) ?>
                                </div>
                                <div class="d-flex justify-content-between align-items-center mt-1">
                                    <span class="text-muted" style="font-size:0.75rem"><?= $sizeLabel ?></span>
                                    <div class="btn-group btn-group-sm">
                                        <a href="<?= $fileUrl ?>" class="btn btn-outline-primary btn-sm" target="_blank" title="Open">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                        <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this file?')">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="project_id" value="<?= $id ?>">
                                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </div>
                                </div>
                                <?php if ($att['description']): ?>
                                    <div class="text-muted mt-1" style="font-size:0.75rem"><?= sanitize($att['description']) ?></div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                </div>
                <div class="text-muted small mt-3">
                    Total: <?= count($attachments) ?> file(s)
                    (<?= $totalFileSize >= 1048576 ? round($totalFileSize / 1048576, 1) . ' MB' : round($totalFileSize / 1024, 1) . ' KB' ?>)
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Upload Modal -->
    <div class="modal fade" id="uploadModal" tabindex="-1">
        <div class="modal-dialog modal-dialog-centered">
            <div class="modal-content">
                <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" enctype="multipart/form-data">
                    <input type="hidden" name="action" value="upload">
                    <input type="hidden" name="project_id" value="<?= $id ?>">
                    <div class="modal-header">
                        <h6 class="modal-title">Upload File</h6>
                        <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                    </div>
                    <div class="modal-body">
                        <div class="mb-3">
                            <label class="form-label">Choose File <span class="text-danger">*</span></label>
                            <input type="file" class="form-control" name="file" required
                                   accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx">
                            <div class="form-text">Max 10MB. Allowed: JPG, PNG, GIF, PDF, DOC, DOCX, XLS, XLSX</div>
                        </div>
                        <div>
                            <label class="form-label">Description</label>
                            <input type="text" class="form-control" name="description" placeholder="Optional label for this file">
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

<?php endif; ?>

<!-- Delete Project Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirm Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong><?= sanitize($project['title']) ?></strong>?
                <p class="text-muted small mt-2 mb-0">This can be undone from the archive.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
// Workflow upload modal - pass stage_id
document.getElementById("wfUploadModal")?.addEventListener("show.bs.modal", function(e) {
    var btn = e.relatedTarget;
    document.getElementById("wfUploadStageId").value = btn.dataset.stageId;
});

document.getElementById("editBudgetModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("editBudgetId").value = btn.dataset.id;
    document.getElementById("editBudgetName").value = btn.dataset.name;
    document.getElementById("editBudgetCategory").value = btn.dataset.category;
    document.getElementById("editBudgetQty").value = btn.dataset.quantity;
    document.getElementById("editBudgetUnit").value = btn.dataset.unit;
    document.getElementById("editBudgetCost").value = btn.dataset.cost;
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

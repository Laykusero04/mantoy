<?php
$pageTitle = 'Edit Project';
require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('danger', 'Invalid project ID.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Fetch project
$stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND is_deleted = 0");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    flashMessage('danger', 'Project not found.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Fetch categories
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Fetch workflow stages
$wfStmt = $pdo->prepare("SELECT * FROM workflow_stages WHERE project_id = ? ORDER BY stage_order");
$wfStmt->execute([$id]);
$workflowStages = $wfStmt->fetchAll();

// If no stages exist (legacy project), create them
if (empty($workflowStages)) {
    initWorkflowStages($pdo, $id);
    $wfStmt->execute([$id]);
    $workflowStages = $wfStmt->fetchAll();
}

// Fetch workflow attachments grouped by stage
$wfAttStmt = $pdo->prepare("SELECT * FROM workflow_attachments WHERE project_id = ? ORDER BY uploaded_at DESC");
$wfAttStmt->execute([$id]);
$allWfAttachments = $wfAttStmt->fetchAll();
$wfAttByStage = [];
foreach ($allWfAttachments as $att) {
    $wfAttByStage[$att['stage_id']][] = $att;
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Edit Project</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/projects/list.php">Projects</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>"><?= sanitize($project['title']) ?></a></li>
                <li class="breadcrumb-item active">Edit</li>
            </ol>
        </nav>
    </div>
</div>

<form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST">
    <input type="hidden" name="action" value="update">
    <input type="hidden" name="id" value="<?= $id ?>">

    <!-- Project Information -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title">Project Information</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label for="title" class="form-label">Project Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title"
                           value="<?= sanitize($project['title']) ?>" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visibility <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="vis_public" value="Public"
                                   <?= ($project['visibility'] ?? 'Public') === 'Public' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="vis_public">Public</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="Private"
                                   <?= ($project['visibility'] ?? '') === 'Private' ? 'checked' : '' ?>>
                            <label class="form-check-label" for="vis_private">Private</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" id="deptGroup" <?= ($project['visibility'] ?? 'Public') === 'Private' ? 'style="display:none;"' : '' ?>>
                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department">
                        <option value="">Select department...</option>
                        <option value="CEO" <?= ($project['department'] ?? '') === 'CEO' ? 'selected' : '' ?>>CEO</option>
                        <option value="Mayors" <?= ($project['department'] ?? '') === 'Mayors' ? 'selected' : '' ?>>Mayors</option>
                    </select>
                </div>
                <div class="col-md-4" id="subtypeGroup" <?= empty($project['department']) ? 'style="display:none;"' : '' ?>>
                    <label for="project_subtype" class="form-label">Sub-type <span class="text-danger">*</span></label>
                    <select class="form-select" id="project_subtype" name="project_subtype">
                        <option value="">Select sub-type...</option>
                        <?php
                        $allSubtypes = getDepartmentSubtypes();
                        $currentDept = $project['department'] ?? '';
                        if ($currentDept && isset($allSubtypes[$currentDept])):
                            foreach ($allSubtypes[$currentDept] as $st): ?>
                                <option value="<?= $st ?>" <?= ($project['project_subtype'] ?? '') === $st ? 'selected' : '' ?>><?= $st ?></option>
                        <?php endforeach;
                        endif; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>" <?= $project['category_id'] == $cat['id'] ? 'selected' : '' ?>><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="priority" class="form-label">Priority</label>
                    <select class="form-select" id="priority" name="priority">
                        <?php foreach (['High', 'Medium', 'Low'] as $p): ?>
                            <option value="<?= $p ?>" <?= $project['priority'] === $p ? 'selected' : '' ?>><?= $p ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="status" class="form-label">Status</label>
                    <select class="form-select" id="status" name="status">
                        <?php foreach (['Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled'] as $s): ?>
                            <option value="<?= $s ?>" <?= $project['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= sanitize($project['description']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Update Project
        </button>
    </div>
</form>

<!-- ═══════════════════════════════════════════════════════════
     WORKFLOW DOCUMENTATION
     ═══════════════════════════════════════════════════════════ -->
<div class="mb-4">
    <h5 class="fw-bold mb-3"><i class="bi bi-diagram-3 me-2"></i>Workflow Documentation</h5>
    <p class="text-muted small">Upload required documents for each workflow stage. Each stage tracks the project's progress through the communication pipeline.</p>

    <?php foreach ($workflowStages as $stage):
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
            <!-- Update Stage Status & Notes -->
            <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" class="mb-3">
                <input type="hidden" name="action" value="update_stage">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="stage_id" value="<?= $stage['id'] ?>">
                <input type="hidden" name="redirect_to" value="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $id ?>">
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
            <!-- File Upload & List -->
            <hr class="my-3">
            <div class="d-flex justify-content-between align-items-center mb-2">
                <h6 class="mb-0 small fw-bold text-muted">
                    <i class="bi bi-paperclip me-1"></i> Attachments (<?= count($stageAtts) ?>)
                </h6>
                <button type="button" class="btn btn-outline-primary btn-sm btn-wf-upload"
                        data-stage-id="<?= $stage['id'] ?>" data-stage-name="<?= sanitize($stage['stage_type']) ?>">
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
                                <?php if ($att['description']): ?>
                                    <span class="text-muted small ms-1">&mdash; <?= sanitize($att['description']) ?></span>
                                <?php endif; ?>
                            </td>
                            <td class="small text-muted"><?= number_format($att['file_size'] / 1024, 1) ?> KB</td>
                            <td class="small text-muted"><?= formatDate($att['uploaded_at']) ?></td>
                            <td>
                                <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this file?')">
                                    <input type="hidden" name="action" value="delete_file">
                                    <input type="hidden" name="project_id" value="<?= $id ?>">
                                    <input type="hidden" name="file_id" value="<?= $att['id'] ?>">
                                    <input type="hidden" name="redirect_to" value="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $id ?>">
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
<div class="modal fade" id="wfEditUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/workflow_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload_file">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="stage_id" id="wfEditUploadStageId" value="">
                <input type="hidden" name="redirect_to" value="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Upload File — <span id="wfEditUploadStageName"></span></h6>
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

<?php
$pageScripts = '<script>
const subtypes = {
    "CEO": ["Planning and Programming", "Construction"],
    "Mayors": ["BDP", "SEF", "Special Projects"]
};

function updateClassification() {
    const vis = document.querySelector("input[name=visibility]:checked").value;
    const deptGroup = document.getElementById("deptGroup");
    const subtypeGroup = document.getElementById("subtypeGroup");
    const deptSelect = document.getElementById("department");
    const subtypeSelect = document.getElementById("project_subtype");

    if (vis === "Private") {
        deptGroup.style.display = "none";
        subtypeGroup.style.display = "none";
        deptSelect.value = "";
        subtypeSelect.innerHTML = "<option value=\"\">Select sub-type...</option>";
    } else {
        deptGroup.style.display = "";
        if (!deptSelect.value) {
            subtypeGroup.style.display = "none";
        }
    }
}

function updateSubtypes() {
    const dept = document.getElementById("department").value;
    const subtypeGroup = document.getElementById("subtypeGroup");
    const subtypeSelect = document.getElementById("project_subtype");
    const currentVal = subtypeSelect.value;

    subtypeSelect.innerHTML = "<option value=\"\">Select sub-type...</option>";
    if (dept && subtypes[dept]) {
        subtypeGroup.style.display = "";
        subtypes[dept].forEach(function(st) {
            const opt = document.createElement("option");
            opt.value = st;
            opt.textContent = st;
            if (st === currentVal) opt.selected = true;
            subtypeSelect.appendChild(opt);
        });
    } else {
        subtypeGroup.style.display = "none";
    }
}

document.querySelectorAll("input[name=visibility]").forEach(function(r) {
    r.addEventListener("change", updateClassification);
});
document.getElementById("department").addEventListener("change", function() {
    document.getElementById("project_subtype").value = "";
    updateSubtypes();
});

// Workflow upload modal
document.querySelectorAll(".btn-wf-upload").forEach(function(btn) {
    btn.addEventListener("click", function() {
        document.getElementById("wfEditUploadStageId").value = this.dataset.stageId;
        document.getElementById("wfEditUploadStageName").textContent = this.dataset.stageName;
        new bootstrap.Modal(document.getElementById("wfEditUploadModal")).show();
    });
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

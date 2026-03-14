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
                        <?php foreach (getDepartments($pdo) as $dept): ?>
                            <option value="<?= sanitize($dept) ?>" <?= ($project['department'] ?? '') === $dept ? 'selected' : '' ?>><?= sanitize($dept) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-4" id="subtypeGroup" <?= empty($project['department']) ? 'style="display:none;"' : '' ?>>
                    <label for="project_subtype" class="form-label">Sub-type <span class="text-danger">*</span></label>
                    <select class="form-select" id="project_subtype" name="project_subtype">
                        <option value="">Select sub-type...</option>
                        <?php
                        $allSubtypes = getDepartmentSubtypes($pdo);
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
                        <?php foreach (STATUS_OPTIONS as $s): ?>
                            <option value="<?= $s ?>" <?= $project['status'] === $s ? 'selected' : '' ?>><?= $s ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-6">
                    <label for="location" class="form-label">Location</label>
                    <input type="text" class="form-control" id="location" name="location" value="<?= sanitize($project['location'] ?? '') ?>" placeholder="e.g. Barangay, City, or address">
                </div>
                <div class="col-md-6">
                    <label for="contact_person" class="form-label">Contact Person</label>
                    <input type="text" class="form-control" id="contact_person" name="contact_person" value="<?= sanitize($project['contact_person'] ?? '') ?>">
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description</label>
                    <textarea class="form-control" id="description" name="description" rows="3"><?= sanitize($project['description']) ?></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Budget -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title"><i class="bi bi-wallet2 me-1"></i> Budget</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-6">
                    <label for="budget_allocated" class="form-label">Budget Allocated</label>
                    <div class="input-group">
                        <span class="input-group-text">&#8369;</span>
                        <input type="number" class="form-control" id="budget_allocated" name="budget_allocated"
                               step="0.01" min="0" value="<?= (float)($project['budget_allocated'] ?? 0) ?>">
                    </div>
                </div>
                <div class="col-md-6">
                    <label for="actual_cost" class="form-label">Actual Cost</label>
                    <div class="input-group">
                        <span class="input-group-text">&#8369;</span>
                        <input type="number" class="form-control" id="actual_cost" name="actual_cost"
                               step="0.01" min="0" value="<?= (float)($project['actual_cost'] ?? 0) ?>">
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Working days (weekends): some projects work Sat/Sun -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white" id="working-days">
            <h6 class="mb-0 section-title">Working days (weekends)</h6>
        </div>
        <div class="card-body">
            <div class="mb-3">
                <label for="notice_to_proceed" class="form-label">Notice to Proceed (NTP)</label>
                <input type="date" class="form-control" id="notice_to_proceed" name="notice_to_proceed" value="<?= !empty($project['notice_to_proceed']) ? sanitize($project['notice_to_proceed']) : '' ?>">
                <div class="form-text">Official project start date; used for timeline and planning baseline.</div>
            </div>
            <p class="text-muted small mb-2">By default Saturday and Sunday are no-work. Check if this project works on:</p>
            <div class="d-flex gap-4">
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="saturday_working" id="saturday_working" value="1" <?= !empty($project['saturday_working']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="saturday_working">Saturday is working day</label>
                </div>
                <div class="form-check">
                    <input class="form-check-input" type="checkbox" name="sunday_working" id="sunday_working" value="1" <?= !empty($project['sunday_working']) ? 'checked' : '' ?>>
                    <label class="form-check-label" for="sunday_working">Sunday is working day</label>
                </div>
            </div>
        </div>
    </div>

    <?php
    // Preserve fields not shown in this form so update doesn't clear them
    foreach (['funding_source', 'resolution_no', 'start_date', 'target_end_date', 'actual_end_date', 'fiscal_year', 'remarks'] as $f):
        $v = $project[$f] ?? '';
        ?>
        <input type="hidden" name="<?= $f ?>" value="<?= sanitize((string)$v) ?>">
    <?php endforeach; ?>

    <!-- Form Actions -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Update Project
        </button>
    </div>
</form>

<?php
$pageScripts = '<script>
const subtypes = ' . json_encode(getDepartmentSubtypes($pdo)) . ';

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
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

<?php
$pageTitle = 'Incoming Project';
require_once __DIR__ . '/../../includes/header.php';

// Fetch categories for dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Incoming Project</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/projects/list.php">Projects</a></li>
                <li class="breadcrumb-item active">New</li>
            </ol>
        </nav>
    </div>
</div>

<form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" enctype="multipart/form-data">
    <input type="hidden" name="action" value="create">
    <input type="hidden" name="fiscal_year" value="<?= sanitize($currentFiscalYear) ?>">

    <!-- Project Information -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title">Project Information</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-12">
                    <label for="title" class="form-label">Project Title <span class="text-danger">*</span></label>
                    <input type="text" class="form-control" id="title" name="title" required>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Visibility <span class="text-danger">*</span></label>
                    <div class="d-flex gap-3 mt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="vis_public" value="Public" checked>
                            <label class="form-check-label" for="vis_public">Public</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="visibility" id="vis_private" value="Private">
                            <label class="form-check-label" for="vis_private">Private</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4" id="deptGroup">
                    <label for="department" class="form-label">Department <span class="text-danger">*</span></label>
                    <select class="form-select" id="department" name="department">
                        <option value="">Select department...</option>
                        <option value="CEO">CEO</option>
                        <option value="Mayors">Mayors</option>
                    </select>
                </div>
                <div class="col-md-4" id="subtypeGroup" style="display:none;">
                    <label for="project_subtype" class="form-label">Sub-type <span class="text-danger">*</span></label>
                    <select class="form-select" id="project_subtype" name="project_subtype">
                        <option value="">Select sub-type...</option>
                    </select>
                </div>
                <div class="col-md-4">
                    <label for="category_id" class="form-label">Category</label>
                    <select class="form-select" id="category_id" name="category_id">
                        <option value="">Select category...</option>
                        <?php foreach ($categories as $cat): ?>
                            <option value="<?= $cat['id'] ?>"><?= sanitize($cat['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-12">
                    <label for="description" class="form-label">Description / Remarks</label>
                    <textarea class="form-control" id="description" name="description" rows="3" placeholder="Describe the incoming communication or project details..."></textarea>
                </div>
            </div>
        </div>
    </div>

    <!-- Incoming Communication Attachments -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title"><i class="bi bi-paperclip me-1"></i> Attachments</h6>
        </div>
        <div class="card-body">
            <p class="text-muted small mb-3">Attach files for the Incoming Communication (letters, documents, images, etc.)</p>
            <div id="fileInputs">
                <div class="row g-2 mb-2 file-row">
                    <div class="col-md-7">
                        <input type="file" name="files[]" class="form-control form-control-sm">
                    </div>
                    <div class="col-md-5">
                        <input type="text" name="file_descriptions[]" class="form-control form-control-sm" placeholder="Description (optional)">
                    </div>
                </div>
            </div>
            <button type="button" class="btn btn-outline-secondary btn-sm mt-2" id="addFileBtn">
                <i class="bi bi-plus-lg me-1"></i> Add Another File
            </button>
            <div class="form-text mt-2">Max 10MB per file. Allowed: <?= implode(', ', ALLOWED_EXTENSIONS) ?></div>
        </div>
    </div>

    <!-- Form Actions -->
    <div class="d-flex justify-content-end gap-2 mb-4">
        <a href="<?= BASE_URL ?>/pages/projects/list.php" class="btn btn-secondary">Cancel</a>
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Save Project
        </button>
    </div>
</form>

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
        updateSubtypes();
    }
}

function updateSubtypes() {
    const dept = document.getElementById("department").value;
    const subtypeGroup = document.getElementById("subtypeGroup");
    const subtypeSelect = document.getElementById("project_subtype");

    subtypeSelect.innerHTML = "<option value=\"\">Select sub-type...</option>";
    if (dept && subtypes[dept]) {
        subtypeGroup.style.display = "";
        subtypes[dept].forEach(function(st) {
            subtypeSelect.innerHTML += "<option value=\"" + st + "\">" + st + "</option>";
        });
    } else {
        subtypeGroup.style.display = "none";
    }
}

document.querySelectorAll("input[name=visibility]").forEach(function(r) {
    r.addEventListener("change", updateClassification);
});
document.getElementById("department").addEventListener("change", updateSubtypes);

// Add more file inputs
document.getElementById("addFileBtn").addEventListener("click", function() {
    var row = document.createElement("div");
    row.className = "row g-2 mb-2 file-row";
    row.innerHTML = \'<div class="col-md-7"><input type="file" name="files[]" class="form-control form-control-sm"></div>\' +
        \'<div class="col-md-4"><input type="text" name="file_descriptions[]" class="form-control form-control-sm" placeholder="Description (optional)"></div>\' +
        \'<div class="col-md-1"><button type="button" class="btn btn-outline-danger btn-sm w-100" onclick="this.closest(\\\'.file-row\\\').remove()"><i class="bi bi-x-lg"></i></button></div>\';
    document.getElementById("fileInputs").appendChild(row);
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

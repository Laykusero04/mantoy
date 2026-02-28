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
                        <?php foreach (getDepartments($pdo) as $dept): ?>
                            <option value="<?= sanitize($dept) ?>"><?= sanitize($dept) ?></option>
                        <?php endforeach; ?>
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
            <div id="createDropZone" class="border border-2 border-dashed rounded-3 p-4 text-center mb-3"
                 style="cursor:pointer; border-color:#adb5bd; transition: all 0.2s;">
                <i class="bi bi-cloud-arrow-up fs-1 text-muted d-block mb-2"></i>
                <p class="mb-1 fw-medium">Drag & drop files here</p>
                <p class="text-muted small mb-2">or click to browse — select multiple files at once</p>
                <button type="button" class="btn btn-outline-primary btn-sm" id="createBtnBrowse">
                    <i class="bi bi-folder2-open me-1"></i> Browse Files
                </button>
                <input type="file" id="createFileInput" class="d-none" multiple
                       accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
            </div>
            <div id="createFileList" class="d-none mb-3">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <label class="form-label mb-0 fw-medium">Selected Files <span id="createFileCount" class="badge bg-primary ms-1">0</span></label>
                    <button type="button" class="btn btn-outline-danger btn-sm" id="createBtnClear">
                        <i class="bi bi-x-lg me-1"></i> Clear All
                    </button>
                </div>
                <div id="createFileItems" style="max-height: 200px; overflow-y: auto;"></div>
                <div class="text-muted small mt-1">Total size: <span id="createTotalSize">0 KB</span></div>
            </div>
            <div>
                <label class="form-label">Description</label>
                <input type="text" name="file_descriptions[]" class="form-control form-control-sm" placeholder="Optional label for all files">
            </div>
            <div class="form-text mt-2">Max 50MB per file. Allowed: <?= strtoupper(implode(', ', ALLOWED_EXTENSIONS)) ?></div>
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

// Multi-file drag & drop for create page
(function() {
    const dropZone = document.getElementById("createDropZone");
    const fileInput = document.getElementById("createFileInput");
    const btnBrowse = document.getElementById("createBtnBrowse");
    const fileList = document.getElementById("createFileList");
    const fileItems = document.getElementById("createFileItems");
    const fileCount = document.getElementById("createFileCount");
    const totalSize = document.getElementById("createTotalSize");
    const btnClear = document.getElementById("createBtnClear");

    if (!dropZone) return;

    let selectedFiles = [];

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + " MB";
        return (bytes / 1024).toFixed(1) + " KB";
    }

    function getIcon(ext) {
        ext = ext.toLowerCase();
        if (["jpg","jpeg","png","gif"].includes(ext)) return "bi-file-earmark-image text-success";
        if (ext === "pdf") return "bi-file-earmark-pdf text-danger";
        if (["doc","docx"].includes(ext)) return "bi-file-earmark-word text-primary";
        if (["xls","xlsx"].includes(ext)) return "bi-file-earmark-excel text-success";
        if (["mp4","avi","mov"].includes(ext)) return "bi-camera-video text-info";
        if (["dwg","dxf","skp","rvt"].includes(ext)) return "bi-rulers text-secondary";
        return "bi-file-earmark text-muted";
    }

    function renderList() {
        fileItems.innerHTML = "";
        if (selectedFiles.length === 0) {
            fileList.classList.add("d-none");
            return;
        }
        fileList.classList.remove("d-none");
        fileCount.textContent = selectedFiles.length;
        let total = 0;
        selectedFiles.forEach(function(file, idx) {
            total += file.size;
            const ext = file.name.split(".").pop();
            const isImg = ["jpg","jpeg","png","gif"].includes(ext.toLowerCase());
            const row = document.createElement("div");
            row.className = "d-flex align-items-center justify-content-between py-2 px-2 border-bottom";
            row.innerHTML =
                "<div class=\\"d-flex align-items-center gap-2 overflow-hidden\\">" +
                    (isImg ? "<img src=\\"" + URL.createObjectURL(file) + "\\" style=\\"width:28px;height:28px;object-fit:cover;border-radius:4px;\\">" :
                    "<i class=\\"bi " + getIcon(ext) + " fs-5\\"></i>") +
                    "<div class=\\"overflow-hidden\\"><div class=\\"small fw-medium text-truncate\\" style=\\"max-width:400px;\\">" + file.name + "</div>" +
                    "<div class=\\"text-muted\\" style=\\"font-size:0.7rem;\\">" + formatSize(file.size) + "</div></div>" +
                "</div>" +
                "<button type=\\"button\\" class=\\"btn btn-outline-danger btn-sm py-0 px-1\\" data-rm=\\"" + idx + "\\"><i class=\\"bi bi-x\\"></i></button>";
            fileItems.appendChild(row);
        });
        totalSize.textContent = formatSize(total);
        fileItems.querySelectorAll("[data-rm]").forEach(function(btn) {
            btn.addEventListener("click", function() {
                selectedFiles.splice(parseInt(this.dataset.rm), 1);
                syncFiles(); renderList();
            });
        });
    }

    function syncFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function(f) { dt.items.add(f); });
        fileInput.files = dt.files;
    }

    function addFiles(newFiles) {
        for (let i = 0; i < newFiles.length; i++) {
            const f = newFiles[i];
            if (f.size > 52428800) continue;
            const dup = selectedFiles.some(function(sf) { return sf.name === f.name && sf.size === f.size; });
            if (!dup) selectedFiles.push(f);
        }
        syncFiles(); renderList();
    }

    btnBrowse.addEventListener("click", function(e) { e.stopPropagation(); fileInput.click(); });
    dropZone.addEventListener("click", function() { fileInput.click(); });
    fileInput.addEventListener("change", function() { if (this.files.length > 0) addFiles(this.files); });

    dropZone.addEventListener("dragover", function(e) { e.preventDefault(); this.style.borderColor = "#1a56db"; this.style.backgroundColor = "#f0f4ff"; });
    dropZone.addEventListener("dragleave", function(e) { e.preventDefault(); this.style.borderColor = "#adb5bd"; this.style.backgroundColor = ""; });
    dropZone.addEventListener("drop", function(e) {
        e.preventDefault(); this.style.borderColor = "#adb5bd"; this.style.backgroundColor = "";
        if (e.dataTransfer.files.length > 0) addFiles(e.dataTransfer.files);
    });

    btnClear.addEventListener("click", function() { selectedFiles = []; fileInput.value = ""; renderList(); });

    // On form submit, set the input name
    document.querySelector("form").addEventListener("submit", function() {
        fileInput.name = "files[]";
    });
})();
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

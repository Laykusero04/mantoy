<?php
// Tab: Files — receives $pdo, $project, $id, $activeTab from view.php

// Fetch files data
$fileStmt = $pdo->prepare("SELECT * FROM attachments WHERE project_id = ? ORDER BY uploaded_at DESC");
$fileStmt->execute([$id]);
$attachments = $fileStmt->fetchAll();
$totalFileSize = array_sum(array_column($attachments, 'file_size'));

?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">
            Attachments
            <?php if (!empty($attachments)): ?>
                <span class="badge bg-secondary ms-1"><?= count($attachments) ?></span>
            <?php endif; ?>
        </h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload me-1"></i> Upload Files
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
                    $isVideo = in_array($ext, ['mp4', 'avi', 'mov']);
                    $isAudio = ($ext === 'mp3');
                    $fileUrl = BASE_URL . '/uploads/' . $id . '/' . $att['file_name'];
                    $sizeKB = round($att['file_size'] / 1024, 1);
                    $sizeLabel = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';
                    $icon = getFileTypeIcon($ext);
                ?>
                <div class="col-md-4 col-lg-3">
                    <div class="card h-100 border">
                        <?php if ($isImage): ?>
                            <a href="<?= $fileUrl ?>" target="_blank">
                                <img src="<?= $fileUrl ?>" class="card-img-top" alt="<?= sanitize($att['file_original_name']) ?>"
                                     style="height: 140px; object-fit: cover;">
                            </a>
                        <?php elseif ($isVideo): ?>
                            <div class="bg-light" style="height: 140px;">
                                <video src="<?= $fileUrl ?>" class="w-100 h-100" style="object-fit: cover;" muted preload="metadata"></video>
                            </div>
                        <?php else: ?>
                            <a href="<?= $fileUrl ?>" target="_blank" class="d-flex align-items-center justify-content-center bg-light" style="height: 140px;">
                                <i class="bi <?= $icon ?> fs-1"></i>
                            </a>
                        <?php endif; ?>
                        <div class="card-body p-2">
                            <div class="small fw-medium text-truncate" title="<?= sanitize($att['file_original_name']) ?>">
                                <?= sanitize($att['file_original_name']) ?>
                            </div>
                            <div class="d-flex justify-content-between align-items-center mt-1">
                                <span class="text-muted" style="font-size:0.75rem">
                                    <?= $sizeLabel ?>
                                    <span class="badge bg-light text-dark ms-1" style="font-size:0.65rem">.<?= $ext ?></span>
                                </span>
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
                            <?php if (!empty($att['description'])): ?>
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

<!-- Upload Modal (Enhanced Multi-File with Drag & Drop) -->
<div class="modal fade" id="uploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" enctype="multipart/form-data" id="uploadForm">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Upload Files</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <!-- Drop Zone -->
                    <div id="dropZone" class="border border-2 border-dashed rounded-3 p-4 text-center mb-3"
                         style="cursor:pointer; border-color:#adb5bd; transition: all 0.2s;">
                        <i class="bi bi-cloud-arrow-up fs-1 text-muted d-block mb-2"></i>
                        <p class="mb-1 fw-medium">Drag & drop files here</p>
                        <p class="text-muted small mb-2">or click to browse</p>
                        <button type="button" class="btn btn-outline-primary btn-sm" id="btnBrowse">
                            <i class="bi bi-folder2-open me-1"></i> Browse Files
                        </button>
                        <input type="file" id="fileInput" class="d-none" multiple
                               accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.dwg,.dxf,.mp4,.avi,.mov,.skp,.rvt,.mp3,.pptx,.ppt,.zip,.rar">
                        <div class="form-text mt-2">Max 50MB per file. Allowed: <?= strtoupper(implode(', ', ALLOWED_EXTENSIONS)) ?></div>
                    </div>

                    <!-- File List Preview -->
                    <div id="fileList" class="d-none mb-3">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label mb-0 fw-medium">Selected Files <span id="fileCount" class="badge bg-primary ms-1">0</span></label>
                            <button type="button" class="btn btn-outline-danger btn-sm" id="btnClearAll">
                                <i class="bi bi-x-lg me-1"></i> Clear All
                            </button>
                        </div>
                        <div id="fileItems" style="max-height: 250px; overflow-y: auto;"></div>
                        <div class="text-muted small mt-1">Total size: <span id="totalSize">0 KB</span></div>
                    </div>

                    <div>
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="Optional label for these files">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm" id="btnUpload" disabled>
                        <i class="bi bi-upload me-1"></i> Upload <span id="btnUploadCount"></span>
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<?php
$fileUploadScript = '<script>
document.addEventListener("DOMContentLoaded", function() {
    const dropZone = document.getElementById("dropZone");
    const fileInput = document.getElementById("fileInput");
    const btnBrowse = document.getElementById("btnBrowse");
    const fileList = document.getElementById("fileList");
    const fileItems = document.getElementById("fileItems");
    const fileCount = document.getElementById("fileCount");
    const totalSize = document.getElementById("totalSize");
    const btnClearAll = document.getElementById("btnClearAll");
    const btnUpload = document.getElementById("btnUpload");
    const btnUploadCount = document.getElementById("btnUploadCount");
    const uploadForm = document.getElementById("uploadForm");

    if (!dropZone) return;

    let selectedFiles = [];

    function formatSize(bytes) {
        if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + " MB";
        return (bytes / 1024).toFixed(1) + " KB";
    }

    function getFileIcon(ext) {
        ext = ext.toLowerCase();
        if (["jpg","jpeg","png","gif"].includes(ext)) return "bi-file-earmark-image text-success";
        if (ext === "pdf") return "bi-file-earmark-pdf text-danger";
        if (["doc","docx"].includes(ext)) return "bi-file-earmark-word text-primary";
        if (["xls","xlsx"].includes(ext)) return "bi-file-earmark-excel text-success";
        if (["ppt","pptx"].includes(ext)) return "bi-file-earmark-ppt text-warning";
        if (["mp4","avi","mov"].includes(ext)) return "bi-camera-video text-info";
        if (ext === "mp3") return "bi-music-note-beamed text-purple";
        if (["dwg","dxf"].includes(ext)) return "bi-rulers text-secondary";
        if (["skp","rvt"].includes(ext)) return "bi-building text-secondary";
        if (["zip","rar"].includes(ext)) return "bi-file-earmark-zip text-warning";
        return "bi-file-earmark text-muted";
    }

    function renderFileList() {
        fileItems.innerHTML = "";
        if (selectedFiles.length === 0) {
            fileList.classList.add("d-none");
            btnUpload.disabled = true;
            btnUploadCount.textContent = "";
            return;
        }
        fileList.classList.remove("d-none");
        btnUpload.disabled = false;
        fileCount.textContent = selectedFiles.length;
        btnUploadCount.textContent = "(" + selectedFiles.length + " file" + (selectedFiles.length > 1 ? "s" : "") + ")";

        let total = 0;
        selectedFiles.forEach(function(file, idx) {
            total += file.size;
            const ext = file.name.split(".").pop();
            const icon = getFileIcon(ext);
            const isImage = ["jpg","jpeg","png","gif"].includes(ext.toLowerCase());
            const row = document.createElement("div");
            row.className = "d-flex align-items-center justify-content-between py-2 px-2 border-bottom";
            row.innerHTML =
                "<div class=\"d-flex align-items-center gap-2 overflow-hidden\">" +
                    (isImage ? "<img src=\"" + URL.createObjectURL(file) + "\" style=\"width:32px;height:32px;object-fit:cover;border-radius:4px;\">" :
                    "<i class=\"bi " + icon + " fs-5\"></i>") +
                    "<div class=\"overflow-hidden\">" +
                        "<div class=\"small fw-medium text-truncate\" style=\"max-width:350px;\">" + file.name + "</div>" +
                        "<div class=\"text-muted\" style=\"font-size:0.7rem;\">" + formatSize(file.size) + " &middot; ." + ext.toUpperCase() + "</div>" +
                    "</div>" +
                "</div>" +
                "<button type=\"button\" class=\"btn btn-outline-danger btn-sm py-0 px-1 flex-shrink-0\" data-remove=\"" + idx + "\">" +
                    "<i class=\"bi bi-x\"></i>" +
                "</button>";
            fileItems.appendChild(row);
        });
        totalSize.textContent = formatSize(total);

        fileItems.querySelectorAll("[data-remove]").forEach(function(btn) {
            btn.addEventListener("click", function() {
                selectedFiles.splice(parseInt(this.dataset.remove), 1);
                syncInputFiles();
                renderFileList();
            });
        });
    }

    function syncInputFiles() {
        const dt = new DataTransfer();
        selectedFiles.forEach(function(f) { dt.items.add(f); });
        fileInput.files = dt.files;
    }

    function addFiles(newFiles) {
        const allowed = <?= json_encode(ALLOWED_EXTENSIONS) ?>;
        for (let i = 0; i < newFiles.length; i++) {
            const f = newFiles[i];
            const ext = f.name.split(".").pop().toLowerCase();
            if (!allowed.includes(ext)) continue;
            if (f.size > <?= MAX_FILE_SIZE ?>) continue;
            // Skip duplicate by name+size
            const dup = selectedFiles.some(function(sf) { return sf.name === f.name && sf.size === f.size; });
            if (!dup) selectedFiles.push(f);
        }
        syncInputFiles();
        renderFileList();
    }

    // Browse button
    btnBrowse.addEventListener("click", function(e) { e.stopPropagation(); fileInput.click(); });
    dropZone.addEventListener("click", function() { fileInput.click(); });

    // File input change (accumulate, dont replace)
    fileInput.addEventListener("change", function() {
        if (this.files.length > 0) addFiles(this.files);
    });

    // Drag & Drop
    dropZone.addEventListener("dragover", function(e) {
        e.preventDefault();
        this.style.borderColor = "#1a56db";
        this.style.backgroundColor = "#f0f4ff";
    });
    dropZone.addEventListener("dragleave", function(e) {
        e.preventDefault();
        this.style.borderColor = "#adb5bd";
        this.style.backgroundColor = "";
    });
    dropZone.addEventListener("drop", function(e) {
        e.preventDefault();
        this.style.borderColor = "#adb5bd";
        this.style.backgroundColor = "";
        if (e.dataTransfer.files.length > 0) addFiles(e.dataTransfer.files);
    });

    // Clear all
    btnClearAll.addEventListener("click", function() {
        selectedFiles = [];
        fileInput.value = "";
        renderFileList();
    });

    // Form submit: inject accumulated files
    uploadForm.addEventListener("submit", function(e) {
        if (selectedFiles.length === 0) {
            e.preventDefault();
            return;
        }
        // Files are already synced to input via DataTransfer
        // Change input name to files[] for backend
        fileInput.name = "files[]";
    });

    // Reset on modal close
    document.getElementById("uploadModal").addEventListener("hidden.bs.modal", function() {
        selectedFiles = [];
        fileInput.value = "";
        renderFileList();
    });
});
</script>';

if (isset($pageScripts)) {
    $pageScripts .= $fileUploadScript;
} else {
    $pageScripts = $fileUploadScript;
}
?>

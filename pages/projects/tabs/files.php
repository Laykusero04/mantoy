<?php
// Tab: Files — receives $pdo, $project, $id, $activeTab from view.php

// Fetch files data (exclude pre-construction documents, which live in their own tab)
$fileStmt = $pdo->prepare("
    SELECT *
    FROM attachments
    WHERE project_id = ?
      AND (module_type IS NULL OR module_type <> 'planning')
    ORDER BY uploaded_at DESC
");
$fileStmt->execute([$id]);
$attachments = $fileStmt->fetchAll();
$totalFileSize = array_sum(array_column($attachments, 'file_size'));

// Group all documents by upload date (encoded/upload date)
$attachmentsByDate = [];
foreach ($attachments as $a) {
    $d = date('Y-m-d', strtotime($a['uploaded_at'] ?? 'now'));
    if (!isset($attachmentsByDate[$d])) $attachmentsByDate[$d] = [];
    $attachmentsByDate[$d][] = $a;
}

$imageAttachments = array_filter($attachments, function ($a) {
    $ext = strtolower(pathinfo($a['file_original_name'], PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif']);
});
$imageAttachments = array_values($imageAttachments);
?>

<?php if (!empty($imageAttachments)): ?>
<div class="card shadow-sm mb-3" id="pictureViewerCard" data-images="<?= htmlspecialchars(json_encode(array_map(function ($a) use ($id) {
    $up = $a['uploaded_at'] ?? null;
    $dateDisplay = $up ? (formatDate($up) . ' ' . date('g:i A', strtotime($up))) : '—';
    return ['id' => (int)$a['id'], 'file_name' => $a['file_name'], 'file_original_name' => $a['file_original_name'], 'url' => BASE_URL . '/uploads/' . $id . '/' . $a['file_name'], 'date_display' => $dateDisplay];
}, $imageAttachments)), ENT_QUOTES, 'UTF-8') ?>">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Pictures</h6>
    </div>
    <div class="card-body">
        <div class="row align-items-center">
            <div class="col-auto">
                <button type="button" class="btn btn-outline-secondary btn-lg" id="btnPicPrev" title="Previous">
                    <i class="bi bi-chevron-left"></i>
                </button>
            </div>
            <div class="col text-center">
                <div id="pictureViewerContainer" class="d-inline-block position-relative">
                    <img id="pictureViewerImg" src="<?= BASE_URL ?>/uploads/<?= $id ?>/<?= sanitize($imageAttachments[0]['file_name']) ?>" alt="" class="img-fluid border rounded" style="max-height: 400px; object-fit: contain;">
                    <?php
                    $firstDate = !empty($imageAttachments[0]['uploaded_at']) ? (formatDate($imageAttachments[0]['uploaded_at']) . ' ' . date('g:i A', strtotime($imageAttachments[0]['uploaded_at']))) : '—';
                ?>
                    <div class="mt-2 small text-muted" id="pictureViewerCaption">1 of <?= count($imageAttachments) ?> — <?= sanitize($imageAttachments[0]['file_original_name']) ?> <span class="text-muted">· <?= $firstDate ?></span></div>
                    <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" class="mt-2 d-inline" id="pictureDeleteForm" onsubmit="return confirm('Delete this picture?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= $id ?>">
                        <input type="hidden" name="attachment_id" id="pictureDeleteId" value="<?= (int)$imageAttachments[0]['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i>Delete this picture</button>
                    </form>
                </div>
            </div>
            <div class="col-auto">
                <button type="button" class="btn btn-outline-secondary btn-lg" id="btnPicNext" title="Next">
                    <i class="bi bi-chevron-right"></i>
                </button>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">
            Documents
            <?php if (!empty($attachments)): ?>
                <span class="badge bg-secondary ms-1"><?= count($attachments) ?> file(s)</span>
            <?php endif; ?>
        </h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#uploadModal">
            <i class="bi bi-upload me-1"></i> Upload Files
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($attachments)): ?>
            <div class="text-center text-muted py-4 px-3">
                <i class="bi bi-folder2-open fs-1 d-block mb-2"></i>
                <p class="mb-0">No documents yet. Upload files to group them by date.</p>
            </div>
        <?php else: ?>
            <?php foreach ($attachmentsByDate as $dateStr => $dayFiles): ?>
                <div class="border-bottom pb-3 mb-3 px-3">
                    <div class="fw-semibold small text-muted py-2 border-bottom mb-2">
                        <i class="bi bi-calendar3 me-1"></i><?= formatDate($dateStr) ?>
                        <span class="badge bg-light text-dark ms-2"><?= count($dayFiles) ?> file(s)</span>
                    </div>
                    <div class="table-responsive">
                        <table class="table table-sm table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width:2rem"></th>
                                    <th>Document</th>
                                    <th class="text-end">Size</th>
                                    <th>Description</th>
                                    <th class="text-nowrap">Time</th>
                                    <th style="width:8rem" class="text-end">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($dayFiles as $att):
                                    $ext = strtolower(pathinfo($att['file_original_name'], PATHINFO_EXTENSION));
                                    $fileUrl = BASE_URL . '/uploads/' . $id . '/' . $att['file_name'];
                                    $sizeKB = round($att['file_size'] / 1024, 1);
                                    $sizeLabel = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';
                                    $icon = getFileTypeIcon($ext);
                                    $uploadTime = !empty($att['uploaded_at']) ? date('g:i A', strtotime($att['uploaded_at'])) : '—';
                                ?>
                                <tr>
                                    <td><i class="bi <?= $icon ?>"></i></td>
                                    <td>
                                        <a href="<?= $fileUrl ?>" target="_blank" class="text-decoration-none fw-medium text-truncate d-inline-block" style="max-width:220px" title="<?= sanitize($att['file_original_name']) ?>">
                                            <?= sanitize($att['file_original_name']) ?>
                                        </a>
                                        <span class="badge bg-light text-dark ms-1" style="font-size:0.7rem">.<?= $ext ?></span>
                                    </td>
                                    <td class="text-end text-muted small"><?= $sizeLabel ?></td>
                                    <td class="text-muted small"><?= !empty($att['description']) ? sanitize($att['description']) : '—' ?></td>
                                    <td class="text-muted small text-nowrap"><?= $uploadTime ?></td>
                                    <td class="text-end">
                                        <a href="<?= $fileUrl ?>" class="btn btn-outline-primary btn-sm py-0 px-1" target="_blank" title="Open"><i class="bi bi-box-arrow-up-right"></i></a>
                                        <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Delete this file?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="project_id" value="<?= $id ?>">
                                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                </div>
            <?php endforeach; ?>
            <div class="text-muted small px-3 pb-3 pt-0">
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

$pictureViewerScript = '';
if (!empty($imageAttachments)) {
    $pictureViewerScript = '<script>
document.addEventListener("DOMContentLoaded", function() {
    var card = document.getElementById("pictureViewerCard");
    var img = document.getElementById("pictureViewerImg");
    var caption = document.getElementById("pictureViewerCaption");
    var deleteId = document.getElementById("pictureDeleteId");
    var btnPrev = document.getElementById("btnPicPrev");
    var btnNext = document.getElementById("btnPicNext");
    if (!card || !img) return;
    var images = [];
    try { images = JSON.parse(card.dataset.images || "[]"); } catch (e) {}
    var idx = 0;
    function showPic(i) {
        if (images.length === 0) return;
        idx = (i + images.length) % images.length;
        var o = images[idx];
        img.src = o.url;
        img.alt = o.file_original_name || "";
        caption.textContent = (idx + 1) + " of " + images.length + " — " + (o.file_original_name || "") + " · " + (o.date_display || "—");
        if (deleteId) deleteId.value = o.id;
    }
    if (btnPrev) btnPrev.addEventListener("click", function() { showPic(idx - 1); });
    if (btnNext) btnNext.addEventListener("click", function() { showPic(idx + 1); });
});
</script>';
}

if (isset($pageScripts)) {
    $pageScripts .= $fileUploadScript . $pictureViewerScript;
} else {
    $pageScripts = $fileUploadScript . $pictureViewerScript;
}
?>

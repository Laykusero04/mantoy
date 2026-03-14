<?php
// Tab: Pre-Construction — important files (permits, drawings, safety, etc.)
// Receives $pdo, $project, $id, $activeTab from view.php

// Fetch only attachments tagged to the pre-construction module
$stmt = $pdo->prepare("
    SELECT *
    FROM attachments
    WHERE project_id = ? AND module_type = 'planning'
    ORDER BY uploaded_at DESC, id DESC
");
$stmt->execute([$id]);
$attachments = $stmt->fetchAll();

// Helper: parse document type from description prefix like "[Permits] ..."
function preconDocTypeAndDescription(?string $raw): array {
    $raw = (string)$raw;
    if (preg_match('/^\[([^\]]+)\]\s*(.*)$/u', $raw, $m)) {
        $type = trim($m[1]);
        $desc = trim($m[2]);
        return [$type, $desc !== '' ? $desc : null];
    }
    return [null, $raw !== '' ? $raw : null];
}

// Define logical "folders" for pre-construction
$preconFolders = [
    'Permits'                 => 'bi-file-earmark-lock',
    'Approved drawings'       => 'bi-file-earmark-richtext',
    'Shop drawings'           => 'bi-file-earmark-ruled',
    'Construction methodology'=> 'bi-diagram-3',
    'Safety plan'             => 'bi-shield-check',
    'Contractor agreements'   => 'bi-file-earmark-text',
    'Other'                   => 'bi-folder2',
];

// Group attachments into folders based on parsed doc type
$folderBuckets = [];
foreach ($attachments as $att) {
    [$docType, $docDesc] = preconDocTypeAndDescription($att['description'] ?? '');
    $folderKey = $docType && isset($preconFolders[$docType]) ? $docType : 'Other';
    if (!isset($folderBuckets[$folderKey])) {
        $folderBuckets[$folderKey] = [];
    }
    $att['_parsed_doc_type'] = $docType;
    $att['_parsed_doc_desc'] = $docDesc;
    $folderBuckets[$folderKey][] = $att;
}
?>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <div>
            <h6 class="mb-0 section-title">Pre-Construction – Important Folders</h6>
            <div class="text-muted small">
                Each folder groups related pre-construction documents (permits, drawings, safety plans, contracts, etc.).
            </div>
        </div>
    </div>
    <div class="card-body">
        <div class="row g-3">
            <?php foreach ($preconFolders as $folderName => $iconClass):
                $items = $folderBuckets[$folderName] ?? [];
            ?>
            <div class="col-lg-6">
                <div class="border rounded-3 h-100 d-flex flex-column">
                    <div class="px-3 py-2 border-bottom bg-light d-flex justify-content-between align-items-center">
                        <div class="d-flex align-items-center gap-2">
                            <i class="bi <?= $iconClass ?>"></i>
                            <span class="fw-semibold"><?= htmlspecialchars($folderName, ENT_QUOTES, 'UTF-8') ?></span>
                        </div>
                        <div class="d-flex align-items-center gap-2">
                            <span class="badge <?= empty($items) ? 'bg-light text-muted' : 'bg-primary' ?>">
                                <?= count($items) ?> file(s)
                            </span>
                            <button type="button"
                                    class="btn btn-outline-primary btn-sm py-0 px-2 precon-upload-btn"
                                    data-bs-toggle="modal"
                                    data-bs-target="#preconUploadModal"
                                    data-doc-type="<?= htmlspecialchars($folderName, ENT_QUOTES, 'UTF-8') ?>">
                                <i class="bi bi-upload me-1"></i> Upload
                            </button>
                        </div>
                    </div>
                    <div class="px-3 py-2 flex-grow-1">
                        <?php if (empty($items)): ?>
                            <div class="text-muted small fst-italic">No documents in this folder yet.</div>
                        <?php else: ?>
                            <div class="list-group list-group-flush small">
                                <?php foreach ($items as $att):
                                    $ext = strtolower(pathinfo($att['file_original_name'], PATHINFO_EXTENSION));
                                    $fileUrl = BASE_URL . '/uploads/' . $id . '/' . $att['file_name'];
                                    $sizeKB = round($att['file_size'] / 1024, 1);
                                    $sizeLabel = $sizeKB >= 1024 ? round($sizeKB / 1024, 1) . ' MB' : $sizeKB . ' KB';
                                    $fileIcon = getFileTypeIcon($ext);
                                    $uploadedAt = !empty($att['uploaded_at'])
                                        ? formatDate($att['uploaded_at']) . ' ' . date('g:i A', strtotime($att['uploaded_at']))
                                        : '—';
                                    $docDesc = $att['_parsed_doc_desc'];
                                ?>
                                <div class="list-group-item px-0 py-2 d-flex align-items-center justify-content-between">
                                    <div class="d-flex align-items-center gap-2 flex-grow-1 overflow-hidden">
                                        <i class="bi <?= $fileIcon ?>"></i>
                                        <div class="overflow-hidden">
                                            <a href="<?= $fileUrl ?>" target="_blank"
                                               class="text-decoration-none fw-medium text-truncate d-block"
                                               style="max-width:260px"
                                               title="<?= sanitize($att['file_original_name']) ?>">
                                                <?= sanitize($att['file_original_name']) ?>
                                            </a>
                                            <div class="text-muted" style="font-size:0.7rem;">
                                                <?= $docDesc ? sanitize($docDesc) . ' · ' : '' ?><?= $sizeLabel ?> · <?= $uploadedAt ?>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 ms-2">
                                        <a href="<?= $fileUrl ?>" class="btn btn-outline-primary btn-sm py-0 px-1" target="_blank" title="Open">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                        <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this file?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="project_id" value="<?= $id ?>">
                                            <input type="hidden" name="attachment_id" value="<?= $att['id'] ?>">
                                            <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>&tab=preconstruction">
                                            <button type="submit" class="btn btn-outline-danger btn-sm py-0 px-1" title="Delete">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</div>

<!-- Upload Modal for Pre-Construction Documents -->
<div class="modal fade" id="preconUploadModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/attachment_actions.php" method="POST" enctype="multipart/form-data">
                <input type="hidden" name="action" value="upload">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>&tab=preconstruction">
                <input type="hidden" name="module_type" value="planning">
                <input type="hidden" name="doc_type" id="preconDocTypeInput" value="Other">
                <div class="modal-header">
                    <h6 class="modal-title" id="preconUploadTitle">Upload Pre-Construction File</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Document type</label>
                        <input type="text" class="form-control" id="preconDocTypeLabel" value="Other" readonly>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">File</label>
                        <input type="file" class="form-control" name="file" required>
                        <div class="form-text">
                            Max <?= round(MAX_FILE_SIZE / 1024 / 1024, 0) ?>MB.
                            Allowed: <?= strtoupper(implode(', ', ALLOWED_EXTENSIONS)) ?>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Description</label>
                        <input type="text" class="form-control" name="description" placeholder="Optional description (e.g. Lot 1, Phase 2)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">
                        <i class="bi bi-upload me-1"></i> Upload
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
    var buttons = document.querySelectorAll('.precon-upload-btn');
    var docTypeInput = document.getElementById('preconDocTypeInput');
    var docTypeLabel = document.getElementById('preconDocTypeLabel');
    var titleEl = document.getElementById('preconUploadTitle');

    if (!buttons.length || !docTypeInput || !docTypeLabel || !titleEl) return;

    buttons.forEach(function (btn) {
        btn.addEventListener('click', function () {
            var type = this.getAttribute('data-doc-type') || 'Other';
            docTypeInput.value = type;
            docTypeLabel.value = type;
            titleEl.textContent = 'Upload ' + type;
        });
    });
});
</script>


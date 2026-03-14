<?php
// Tab: Closeout — inspection requests, results, and attachments.
// Receives $pdo, $project, $id, $activeTab from view.php

$statusFilter = isset($_GET['inspection_status']) ? strtolower(trim($_GET['inspection_status'])) : '';
$typeFilter   = isset($_GET['inspection_type']) ? trim($_GET['inspection_type']) : '';
$editId       = isset($_GET['edit']) ? (int)$_GET['edit'] : 0;

// Ensure inspections table exists
$inspectionsTableExists = false;
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_inspections'");
    $inspectionsTableExists = $chk && $chk->rowCount() > 0;
} catch (Exception $e) {
    $inspectionsTableExists = false;
}

$editInspection = null;
if ($inspectionsTableExists && $editId > 0) {
    $stmt = $pdo->prepare("SELECT * FROM project_inspections WHERE id = ? AND project_id = ?");
    $stmt->execute([$editId, $id]);
    $editInspection = $stmt->fetch(PDO::FETCH_ASSOC) ?: null;
}

$inspections = [];
$attachmentsByInspection = [];

if ($inspectionsTableExists) {
    $where = ['project_id = :pid'];
    $params = ['pid' => $id];

    if ($statusFilter !== '' && in_array($statusFilter, ['pending', 'approved', 'rejected'], true)) {
        $where[] = 'status = :status';
        $params['status'] = $statusFilter;
    }
    if ($typeFilter !== '') {
        $where[] = 'inspection_type = :itype';
        $params['itype'] = $typeFilter;
    }

    $sql = "
        SELECT i.*
        FROM project_inspections i
        WHERE " . implode(' AND ', $where) . "
        ORDER BY i.date_requested DESC, i.id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $inspections = $stmt->fetchAll(PDO::FETCH_ASSOC);

    if (!empty($inspections)) {
        $ids = array_column($inspections, 'id');
        $placeholders = implode(',', array_fill(0, count($ids), '?'));
        $attStmt = $pdo->prepare("
            SELECT id, module_record_id, file_name, file_original_name, file_size
            FROM attachments
            WHERE project_id = ? AND module_type = 'inspection' AND module_record_id IN ($placeholders)
            ORDER BY id DESC
        ");
        $attStmt->execute(array_merge([$id], $ids));
        while ($row = $attStmt->fetch(PDO::FETCH_ASSOC)) {
            $mid = (int)$row['module_record_id'];
            if (!isset($attachmentsByInspection[$mid])) {
                $attachmentsByInspection[$mid] = [];
            }
            $attachmentsByInspection[$mid][] = $row;
        }
    }
}

function inspectionStatusBadgeClass(string $status): string {
    $status = strtolower($status);
    if ($status === 'approved') return 'badge-completed';
    if ($status === 'rejected') return 'badge-cancelled';
    return 'badge-in-progress';
}
?>

<div class="row g-4">
    <div class="col-lg-4">
        <div class="card shadow-sm mb-4">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title"><?= $editInspection ? 'Edit Inspection' : 'New Inspection' ?></h6>
            </div>
            <div class="card-body">
                <?php if (!$inspectionsTableExists): ?>
                    <div class="alert alert-warning mb-0">
                        <strong>Inspections are not set up.</strong><br>
                        Run <code>database/migration_inspections.sql</code> in your database (e.g. via phpMyAdmin) first.
                    </div>
                <?php else: ?>
                    <?php
                    $formAction = BASE_URL . '/actions/inspection_actions.php';
                    $isEdit = (bool)$editInspection;
                    $current = $editInspection ?: [
                        'inspection_type' => '',
                        'task_id'         => null,
                        'date_requested'  => $project['target_end_date'] ?: date('Y-m-d'),
                        'date_inspected'  => null,
                        'status'          => 'pending',
                        'remarks'         => '',
                    ];
                    ?>
                    <form action="<?= $formAction ?>" method="POST" enctype="multipart/form-data">
                        <input type="hidden" name="action" value="<?= $isEdit ? 'update' : 'create' ?>">
                        <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                        <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=closeout">
                        <?php if ($isEdit): ?>
                            <input type="hidden" name="inspection_id" value="<?= (int)$current['id'] ?>">
                        <?php endif; ?>

                        <div class="mb-3">
                            <label class="form-label">Inspection type <span class="text-danger">*</span></label>
                            <input type="text" name="inspection_type" class="form-control"
                                   value="<?= sanitize($current['inspection_type'] ?? '') ?>"
                                   placeholder="e.g. Pre-final, Punchlist, Turnover" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Related task / activity (optional)</label>
                            <input type="text" class="form-control" name="task_label" value=""
                                   placeholder="Describe task (for now this is for reference only)">
                            <input type="hidden" name="task_id" value="<?= isset($current['task_id']) ? (int)$current['task_id'] : '' ?>">
                            <div class="form-text small">
                                Task linkage can be wired to timeline or DUPA in a later enhancement.
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Requested date <span class="text-danger">*</span></label>
                            <input type="date" name="date_requested" class="form-control"
                                   value="<?= sanitize($current['date_requested'] ?? '') ?>" required>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Inspection date (result)</label>
                            <input type="date" name="date_inspected" class="form-control"
                                   value="<?= sanitize($current['date_inspected'] ?? '') ?>">
                            <div class="form-text small">
                                When set, a separate calendar event is created for this date (result / after).
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Status</label>
                            <?php $statusVal = strtolower($current['status'] ?? 'pending'); ?>
                            <select name="status" class="form-select">
                                <option value="pending"  <?= $statusVal === 'pending' ? 'selected' : '' ?>>Pending</option>
                                <option value="approved" <?= $statusVal === 'approved' ? 'selected' : '' ?>>Approved</option>
                                <option value="rejected" <?= $statusVal === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                            </select>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Remarks</label>
                            <textarea name="remarks" class="form-control" rows="3"
                                      placeholder="Notes, punchlist items, rectification summary"><?= sanitize($current['remarks'] ?? '') ?></textarea>
                        </div>

                        <div class="mb-3">
                            <label class="form-label">Attachments (optional)</label>
                            <input type="file" class="form-control" name="attachments[]" multiple
                                   accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                            <input type="text" class="form-control mt-1" name="attachment_description"
                                   placeholder="Attachment description (optional)">
                            <div class="form-text small">
                                Files are stored under this project's folder and linked to this inspection.
                            </div>
                        </div>

                        <div class="d-flex justify-content-between">
                            <?php if ($isEdit): ?>
                                <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=closeout"
                                   class="btn btn-outline-secondary btn-sm">Cancel edit</a>
                            <?php else: ?>
                                <span></span>
                            <?php endif; ?>
                            <button type="submit" class="btn btn-primary btn-sm">
                                <?= $isEdit ? 'Update Inspection' : 'Create Inspection' ?>
                            </button>
                        </div>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <div>
                    <h6 class="mb-0 section-title">Inspection log</h6>
                    <div class="text-muted small">
                        Tracks requested, during (rectification), and after (result) dates via calendar events.
                    </div>
                </div>
                <form method="GET" class="d-flex flex-wrap gap-2 align-items-center">
                    <input type="hidden" name="id" value="<?= (int)$id ?>">
                    <input type="hidden" name="tab" value="closeout">
                    <select name="inspection_status" class="form-select form-select-sm" style="min-width: 130px;">
                        <option value="">All statuses</option>
                        <option value="pending"  <?= $statusFilter === 'pending' ? 'selected' : '' ?>>Pending</option>
                        <option value="approved" <?= $statusFilter === 'approved' ? 'selected' : '' ?>>Approved</option>
                        <option value="rejected" <?= $statusFilter === 'rejected' ? 'selected' : '' ?>>Rejected</option>
                    </select>
                    <input type="text" name="inspection_type" class="form-control form-control-sm"
                           value="<?= sanitize($typeFilter) ?>" placeholder="Filter by type">
                    <button type="submit" class="btn btn-outline-secondary btn-sm">Filter</button>
                </form>
            </div>
            <div class="card-body p-0">
                <?php if (!$inspectionsTableExists): ?>
                    <div class="p-4 text-center text-muted">
                        Inspections table not found. Run <code>database/migration_inspections.sql</code> to enable this tab.
                    </div>
                <?php elseif (empty($inspections)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-clipboard-check fs-1 d-block mb-2 opacity-50"></i>
                        <p class="mb-1">No inspections recorded yet.</p>
                        <p class="small mb-0">Use the form on the left to create the first inspection request.</p>
                    </div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th style="width: 4%;">#</th>
                                    <th>Type</th>
                                    <th style="width: 13%;">Requested</th>
                                    <th style="width: 13%;">Inspected</th>
                                    <th style="width: 10%;">Status</th>
                                    <th>Remarks</th>
                                    <th style="width: 10%;">Attachments</th>
                                    <th style="width: 12%;"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($inspections as $idx => $ins):
                                    $istatus = strtolower($ins['status'] ?? 'pending');
                                    $badgeClass = inspectionStatusBadgeClass($istatus);
                                    $attList = $attachmentsByInspection[(int)$ins['id']] ?? [];
                                    $attCount = count($attList);
                                ?>
                                <tr>
                                    <td class="text-muted"><?= $idx + 1 ?></td>
                                    <td class="fw-medium"><?= sanitize($ins['inspection_type']) ?></td>
                                    <td><?= formatDate($ins['date_requested']) ?></td>
                                    <td><?= formatDate($ins['date_inspected']) ?></td>
                                    <td>
                                        <span class="badge <?= $badgeClass ?> text-uppercase small">
                                            <?= strtoupper($istatus) ?>
                                        </span>
                                    </td>
                                    <td>
                                        <?php if (!empty($ins['remarks'])): ?>
                                            <span title="<?= sanitize($ins['remarks']) ?>">
                                                <?= nl2br(sanitize(mb_strimwidth($ins['remarks'], 0, 80, '…'))) ?>
                                            </span>
                                        <?php else: ?>
                                            <span class="text-muted">—</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php if ($attCount === 0): ?>
                                            <span class="text-muted small">None</span>
                                        <?php else: ?>
                                            <span class="small"><?= $attCount ?> file(s)</span>
                                        <?php endif; ?>
                                    </td>
                                    <td class="text-end">
                                        <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=closeout&edit=<?= (int)$ins['id'] ?>"
                                           class="btn btn-outline-primary btn-sm">
                                            Edit
                                        </a>
                                        <form action="<?= BASE_URL ?>/actions/inspection_actions.php" method="POST" class="d-inline"
                                              onsubmit="return confirm('Delete this inspection and its calendar events?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="project_id" value="<?= (int)$id ?>">
                                            <input type="hidden" name="inspection_id" value="<?= (int)$ins['id'] ?>">
                                            <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= (int)$id ?>&tab=closeout">
                                            <button type="submit" class="btn btn-outline-danger btn-sm ms-1">
                                                <i class="bi bi-trash"></i>
                                            </button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>


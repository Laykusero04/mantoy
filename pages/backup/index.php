<?php
$pageTitle = 'Backup & Restore';
require_once __DIR__ . '/../../includes/header.php';

// Get backup files
$backupFiles = [];
if (is_dir(BACKUP_DIR)) {
    $files = glob(BACKUP_DIR . '*.{sql,zip}', GLOB_BRACE);
    foreach ($files as $file) {
        $backupFiles[] = [
            'name' => basename($file),
            'size' => filesize($file),
            'date' => filemtime($file),
        ];
    }
    usort($backupFiles, fn($a, $b) => $b['date'] - $a['date']);
}

// Last backup date
$lastBackup = !empty($backupFiles) ? $backupFiles[0]['date'] : null;
$daysSinceBackup = $lastBackup ? floor((time() - $lastBackup) / 86400) : null;
?>

<div class="mb-4">
    <h4 class="mb-1 fw-bold">Backup & Restore</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
            <li class="breadcrumb-item active">Backup & Restore</li>
        </ol>
    </nav>
</div>

<!-- Backup Warning -->
<?php if ($daysSinceBackup === null || $daysSinceBackup >= 7): ?>
<div class="alert alert-warning d-flex align-items-center mb-4">
    <i class="bi bi-exclamation-triangle-fill me-2 fs-5"></i>
    <div>
        <?php if ($daysSinceBackup === null): ?>
            <strong>No backups found.</strong> Create your first backup now to protect your data.
        <?php else: ?>
            <strong>Last backup was <?= $daysSinceBackup ?> days ago.</strong> Consider creating a new backup.
        <?php endif; ?>
    </div>
</div>
<?php endif; ?>

<!-- Backup Section -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Backup</h6>
    </div>
    <div class="card-body">
        <?php if ($lastBackup): ?>
            <p class="text-muted mb-3">
                Last backup: <strong><?= date('M d, Y h:i A', $lastBackup) ?></strong>
                (<?= $daysSinceBackup == 0 ? 'today' : $daysSinceBackup . ' day(s) ago' ?>)
            </p>
        <?php endif; ?>
        <div class="d-flex gap-2 flex-wrap">
            <form action="<?= BASE_URL ?>/actions/backup_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="export_db">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-database-down me-1"></i> Export Database (SQL)
                </button>
            </form>
            <form action="<?= BASE_URL ?>/actions/backup_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="export_files">
                <button type="submit" class="btn btn-outline-primary">
                    <i class="bi bi-file-earmark-zip me-1"></i> Export Files (ZIP)
                </button>
            </form>
        </div>
    </div>
</div>

<!-- Restore Section -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Restore</h6>
    </div>
    <div class="card-body">
        <div class="alert alert-danger py-2 mb-3">
            <i class="bi bi-exclamation-triangle me-1"></i>
            <strong>Warning:</strong> Restoring will overwrite all current data. Make sure to backup first.
        </div>
        <form action="<?= BASE_URL ?>/actions/backup_actions.php" method="POST" enctype="multipart/form-data"
              onsubmit="return confirm('This will overwrite ALL current data. Are you sure?')">
            <input type="hidden" name="action" value="restore_db">
            <div class="row g-2 align-items-end">
                <div class="col-md-6">
                    <label class="form-label">Upload SQL File</label>
                    <input type="file" class="form-control" name="sql_file" accept=".sql" required>
                </div>
                <div class="col-md-3">
                    <button type="submit" class="btn btn-danger">
                        <i class="bi bi-arrow-counterclockwise me-1"></i> Restore
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>

<!-- Backup History -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Backup History</h6>
    </div>
    <div class="card-body p-0">
        <?php if (empty($backupFiles)): ?>
            <div class="text-center text-muted py-4">
                <i class="bi bi-archive fs-1 d-block mb-2"></i>
                <p>No backup files found.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>File Name</th>
                        <th>Size</th>
                        <th>Date</th>
                        <th style="width:15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($backupFiles as $bf): ?>
                    <tr>
                        <td>
                            <i class="bi bi-<?= str_ends_with($bf['name'], '.zip') ? 'file-earmark-zip' : 'file-earmark-code' ?> me-1 text-muted"></i>
                            <?= sanitize($bf['name']) ?>
                        </td>
                        <td>
                            <?php
                            $size = $bf['size'];
                            echo $size >= 1048576 ? round($size / 1048576, 1) . ' MB' : round($size / 1024, 1) . ' KB';
                            ?>
                        </td>
                        <td><?= date('M d, Y h:i A', $bf['date']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/backups/<?= sanitize($bf['name']) ?>" class="btn btn-outline-primary" title="Download" download>
                                    <i class="bi bi-download"></i>
                                </a>
                                <form action="<?= BASE_URL ?>/actions/backup_actions.php" method="POST" class="d-inline"
                                      onsubmit="return confirm('Delete this backup file?')">
                                    <input type="hidden" name="action" value="delete_backup">
                                    <input type="hidden" name="filename" value="<?= sanitize($bf['name']) ?>">
                                    <button type="submit" class="btn btn-outline-danger"><i class="bi bi-trash"></i></button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

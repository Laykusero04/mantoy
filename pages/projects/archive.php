<?php
$pageTitle = 'Archived Projects';
require_once __DIR__ . '/../../includes/header.php';

$page    = max(1, (int)($_GET['page'] ?? 1));
$perPage = ITEMS_PER_PAGE;
$offset  = ($page - 1) * $perPage;

// Count
$totalRows = $pdo->query("SELECT COUNT(*) FROM projects WHERE is_deleted = 0 AND is_archived = 1")->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM projects p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.is_deleted = 0 AND p.is_archived = 1
    ORDER BY p.updated_at DESC
    LIMIT ? OFFSET ?
");
$stmt->bindValue(1, $perPage, PDO::PARAM_INT);
$stmt->bindValue(2, $offset, PDO::PARAM_INT);
$stmt->execute();
$projects = $stmt->fetchAll();

function archiveStatusBadge($status) {
    $map = [
        'Not Started' => 'bg-secondary',
        'In Progress' => 'bg-info',
        'On Hold'     => 'bg-warning text-dark',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'bg-secondary') . '">' . sanitize($status) . '</span>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Archived Projects</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/projects/list.php">Projects</a></li>
                <li class="breadcrumb-item active">Archived</li>
            </ol>
        </nav>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($projects)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-archive fs-1 d-block mb-2"></i>
                <p>No archived projects.</p>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>#</th>
                        <th>Title</th>
                        <th>Classification</th>
                        <th>Status</th>
                        <th>Fiscal Year</th>
                        <th class="text-end">Budget</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $i => $proj): ?>
                    <tr>
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td class="fw-medium"><?= sanitize($proj['title']) ?></td>
                        <td><span class="badge <?= getClassificationBadgeClass($proj) ?>"><?= sanitize(getClassificationLabel($proj)) ?></span></td>
                        <td><?= archiveStatusBadge($proj['status']) ?></td>
                        <td><?= sanitize($proj['fiscal_year']) ?></td>
                        <td class="text-end"><?= formatCurrency($proj['budget_allocated']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                                    <input type="hidden" name="action" value="unarchive">
                                    <input type="hidden" name="id" value="<?= $proj['id'] ?>">
                                    <button type="submit" class="btn btn-outline-success" title="Unarchive">
                                        <i class="bi bi-box-arrow-up"></i>
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
            <small class="text-muted">Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?></small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page - 1 ?>">Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?page=<?= $p ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?page=<?= $page + 1 ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

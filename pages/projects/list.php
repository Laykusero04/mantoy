<?php
$pageTitle = 'All Projects';
require_once __DIR__ . '/../../includes/header.php';

// Fetch categories for filter dropdown
$categories = $pdo->query("SELECT id, name FROM categories ORDER BY name")->fetchAll();

// Collect filter values
$filterVisibility = $_GET['visibility'] ?? '';
$filterDepartment = $_GET['department'] ?? '';
$filterSubtype  = $_GET['subtype'] ?? '';
$filterStatus   = $_GET['status'] ?? '';
$filterYear     = $_GET['year'] ?? '';
$filterCategory = $_GET['category'] ?? '';
$filterPriority = $_GET['priority'] ?? '';
$searchQuery    = trim($_GET['q'] ?? '');
$page           = max(1, (int)($_GET['page'] ?? 1));
$perPage        = ITEMS_PER_PAGE;
$offset         = ($page - 1) * $perPage;

// Build WHERE clause
$where = ["p.is_deleted = 0", "p.is_archived = 0"];
$params = [];

if ($filterVisibility !== '') {
    $where[] = "p.visibility = ?";
    $params[] = $filterVisibility;
}
if ($filterDepartment !== '') {
    $where[] = "p.department = ?";
    $params[] = $filterDepartment;
}
if ($filterSubtype !== '') {
    $where[] = "p.project_subtype = ?";
    $params[] = $filterSubtype;
}
if ($filterStatus !== '') {
    $where[] = "p.status = ?";
    $params[] = $filterStatus;
}
if ($filterYear !== '') {
    $where[] = "p.fiscal_year = ?";
    $params[] = (int)$filterYear;
}
if ($filterCategory !== '') {
    $where[] = "p.category_id = ?";
    $params[] = (int)$filterCategory;
}
if ($filterPriority !== '') {
    $where[] = "p.priority = ?";
    $params[] = $filterPriority;
}
if ($searchQuery !== '') {
    $where[] = "(p.title LIKE ? OR p.description LIKE ? OR p.remarks LIKE ?)";
    $like = "%$searchQuery%";
    $params[] = $like;
    $params[] = $like;
    $params[] = $like;
}

$whereSql = implode(' AND ', $where);

// Count total
$countStmt = $pdo->prepare("SELECT COUNT(*) FROM projects p WHERE $whereSql");
$countStmt->execute($params);
$totalRows = $countStmt->fetchColumn();
$totalPages = max(1, ceil($totalRows / $perPage));

// Fetch projects
$sql = "SELECT p.*, c.name as category_name
        FROM projects p
        LEFT JOIN categories c ON p.category_id = c.id
        WHERE $whereSql
        ORDER BY p.updated_at DESC
        LIMIT $perPage OFFSET $offset";
$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$projects = $stmt->fetchAll();

// Available fiscal years
$years = $pdo->query("SELECT DISTINCT fiscal_year FROM projects WHERE is_deleted = 0 ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);

// Status badge helper
function listStatusBadge($status) {
    $map = [
        'Not Started' => 'bg-secondary',
        'In Progress' => 'bg-info',
        'On Hold'     => 'bg-warning text-dark',
        'Completed'   => 'bg-success',
        'Cancelled'   => 'bg-danger',
    ];
    return '<span class="badge ' . ($map[$status] ?? 'bg-secondary') . '">' . sanitize($status) . '</span>';
}

function priorityBadge($priority) {
    $map = [
        'High'   => 'badge-high',
        'Medium' => 'badge-medium',
        'Low'    => 'badge-low',
    ];
    return '<span class="badge ' . ($map[$priority] ?? 'bg-secondary') . '">' . sanitize($priority) . '</span>';
}

// Build filter query string helper (preserves other filters)
function filterUrl($overrides = []) {
    $params = array_merge($_GET, $overrides);
    unset($params['page']); // reset page on filter change
    $params = array_filter($params, fn($v) => $v !== '');
    return '?' . http_build_query($params);
}
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">All Projects</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item active">Projects</li>
            </ol>
        </nav>
    </div>
    <a href="<?= BASE_URL ?>/pages/projects/create.php" class="btn btn-primary">
        <i class="bi bi-plus-lg me-1"></i> New Project
    </a>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-4">
    <div class="card-body">
        <form method="GET" class="row g-2 align-items-end">
            <div class="col-md-2">
                <label class="form-label small mb-1">Visibility</label>
                <select name="visibility" class="form-select form-select-sm">
                    <option value="">All</option>
                    <option value="Public" <?= $filterVisibility === 'Public' ? 'selected' : '' ?>>Public</option>
                    <option value="Private" <?= $filterVisibility === 'Private' ? 'selected' : '' ?>>Private</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Department</label>
                <select name="department" class="form-select form-select-sm">
                    <option value="">All Depts</option>
                    <option value="CEO" <?= $filterDepartment === 'CEO' ? 'selected' : '' ?>>CEO</option>
                    <option value="Mayors" <?= $filterDepartment === 'Mayors' ? 'selected' : '' ?>>Mayors</option>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Status</label>
                <select name="status" class="form-select form-select-sm">
                    <option value="">All Status</option>
                    <?php foreach (['Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled'] as $s): ?>
                        <option value="<?= $s ?>" <?= $filterStatus === $s ? 'selected' : '' ?>><?= $s ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-1">
                <label class="form-label small mb-1">Year</label>
                <select name="year" class="form-select form-select-sm">
                    <option value="">All</option>
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $filterYear == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Priority</label>
                <select name="priority" class="form-select form-select-sm">
                    <option value="">All Priorities</option>
                    <?php foreach (['High', 'Medium', 'Low'] as $p): ?>
                        <option value="<?= $p ?>" <?= $filterPriority === $p ? 'selected' : '' ?>><?= $p ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2">
                <label class="form-label small mb-1">Search</label>
                <input type="text" name="q" class="form-control form-control-sm"
                       placeholder="Keyword..." value="<?= sanitize($searchQuery) ?>">
            </div>
            <div class="col-md-1 d-flex gap-1">
                <button type="submit" class="btn btn-primary btn-sm flex-fill">
                    <i class="bi bi-funnel"></i>
                </button>
                <a href="<?= BASE_URL ?>/pages/projects/list.php" class="btn btn-outline-secondary btn-sm" title="Clear">
                    <i class="bi bi-x-lg"></i>
                </a>
            </div>
        </form>
    </div>
</div>

<!-- Projects Table -->
<div class="card shadow-sm">
    <div class="card-body p-0">
        <?php if (empty($projects)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                <p>No projects found.</p>
                <a href="<?= BASE_URL ?>/pages/projects/create.php" class="btn btn-primary btn-sm">Create your first project</a>
            </div>
        <?php else: ?>
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th style="width:5%">#</th>
                        <th style="width:22%">Title</th>
                        <th>Classification</th>
                        <th>Priority</th>
                        <th>Status</th>
                        <th class="text-end">Budget</th>
                        <th>Start Date</th>
                        <th style="width:12%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($projects as $i => $proj): ?>
                    <tr>
                        <td class="text-muted"><?= $offset + $i + 1 ?></td>
                        <td>
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="fw-medium text-decoration-none">
                                <?= sanitize($proj['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge <?= getClassificationBadgeClass($proj) ?>"><?= sanitize(getClassificationLabel($proj)) ?></span></td>
                        <td><?= priorityBadge($proj['priority']) ?></td>
                        <td><?= listStatusBadge($proj['status']) ?></td>
                        <td class="text-end"><?= formatCurrency($proj['budget_allocated']) ?></td>
                        <td class="small"><?= formatDate($proj['start_date']) ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-outline-primary" title="View">
                                    <i class="bi bi-eye"></i>
                                </a>
                                <a href="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $proj['id'] ?>" class="btn btn-outline-secondary" title="Edit">
                                    <i class="bi bi-pencil"></i>
                                </a>
                                <button type="button" class="btn btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-id="<?= $proj['id'] ?>" data-title="<?= sanitize($proj['title']) ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Pagination -->
        <?php if ($totalPages > 1): ?>
        <div class="d-flex justify-content-between align-items-center px-3 py-3 border-top">
            <small class="text-muted">Showing <?= $offset + 1 ?>-<?= min($offset + $perPage, $totalRows) ?> of <?= $totalRows ?> projects</small>
            <nav>
                <ul class="pagination pagination-sm mb-0">
                    <li class="page-item <?= $page <= 1 ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page - 1])) ?>">Prev</a>
                    </li>
                    <?php for ($p = 1; $p <= $totalPages; $p++): ?>
                        <li class="page-item <?= $p === $page ? 'active' : '' ?>">
                            <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $p])) ?>"><?= $p ?></a>
                        </li>
                    <?php endfor; ?>
                    <li class="page-item <?= $page >= $totalPages ? 'disabled' : '' ?>">
                        <a class="page-link" href="?<?= http_build_query(array_merge($_GET, ['page' => $page + 1])) ?>">Next</a>
                    </li>
                </ul>
            </nav>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirm Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong id="deleteProjectTitle"></strong>?
                <p class="text-muted small mt-2 mb-0">This can be undone from the archive.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteProjectId" value="">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.getElementById("deleteModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("deleteProjectId").value = btn.dataset.id;
    document.getElementById("deleteProjectTitle").textContent = btn.dataset.title;
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

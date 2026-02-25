<?php
$pageTitle = 'Manage Categories';
require_once __DIR__ . '/../../includes/header.php';

// Fetch all categories with project count
$categories = $pdo->query("
    SELECT c.*, COUNT(p.id) as project_count
    FROM categories c
    LEFT JOIN projects p ON c.id = p.category_id AND p.is_deleted = 0
    GROUP BY c.id
    ORDER BY c.is_default DESC, c.name ASC
")->fetchAll();
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Manage Categories</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item">Settings</li>
                <li class="breadcrumb-item active">Categories</li>
            </ol>
        </nav>
    </div>
    <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addModal">
        <i class="bi bi-plus-lg me-1"></i> Add Category
    </button>
</div>

<div class="card shadow-sm">
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover align-middle mb-0">
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Type</th>
                        <th>Projects</th>
                        <th style="width: 15%">Actions</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($categories as $cat): ?>
                    <tr>
                        <td class="fw-medium"><?= sanitize($cat['name']) ?></td>
                        <td>
                            <?php if ($cat['is_default']): ?>
                                <span class="badge bg-secondary">Default</span>
                            <?php else: ?>
                                <span class="badge bg-info">Custom</span>
                            <?php endif; ?>
                        </td>
                        <td><?= $cat['project_count'] ?></td>
                        <td>
                            <div class="btn-group btn-group-sm">
                                <button type="button" class="btn btn-outline-secondary" title="Edit"
                                        data-bs-toggle="modal" data-bs-target="#editModal"
                                        data-id="<?= $cat['id'] ?>" data-name="<?= sanitize($cat['name']) ?>">
                                    <i class="bi bi-pencil"></i>
                                </button>
                                <?php if (!$cat['is_default']): ?>
                                <button type="button" class="btn btn-outline-danger" title="Delete"
                                        data-bs-toggle="modal" data-bs-target="#deleteModal"
                                        data-id="<?= $cat['id'] ?>" data-name="<?= sanitize($cat['name']) ?>"
                                        data-count="<?= $cat['project_count'] ?>">
                                    <i class="bi bi-trash"></i>
                                </button>
                                <?php endif; ?>
                            </div>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<!-- Add Modal -->
<div class="modal fade" id="addModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/category_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <div class="modal-header">
                    <h6 class="modal-title">Add Category</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="addName" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="addName" name="name" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Edit Modal -->
<div class="modal fade" id="editModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/category_actions.php" method="POST">
                <input type="hidden" name="action" value="update">
                <input type="hidden" name="id" id="editId" value="">
                <div class="modal-header">
                    <h6 class="modal-title">Edit Category</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <label for="editName" class="form-label">Category Name</label>
                    <input type="text" class="form-control" id="editName" name="name" required>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Delete Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirm Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Delete category <strong id="deleteName"></strong>?
                <p class="text-muted small mt-2 mb-0" id="deleteWarning"></p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="<?= BASE_URL ?>/actions/category_actions.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" id="deleteId" value="">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
document.getElementById("editModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("editId").value = btn.dataset.id;
    document.getElementById("editName").value = btn.dataset.name;
});
document.getElementById("deleteModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("deleteId").value = btn.dataset.id;
    document.getElementById("deleteName").textContent = btn.dataset.name;
    const count = parseInt(btn.dataset.count);
    document.getElementById("deleteWarning").textContent = count > 0
        ? count + " project(s) will have their category unset."
        : "No projects use this category.";
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

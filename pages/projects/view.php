<?php
$pageTitle = 'Project Details';
require_once __DIR__ . '/../../includes/header.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    flashMessage('danger', 'Invalid project ID.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Fetch project with category
$stmt = $pdo->prepare("
    SELECT p.*, c.name as category_name
    FROM projects p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.id = ? AND p.is_deleted = 0
");
$stmt->execute([$id]);
$project = $stmt->fetch();

if (!$project) {
    flashMessage('danger', 'Project not found.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

// Budget utilization
$utilization = $project['budget_allocated'] > 0
    ? round(($project['actual_cost'] / $project['budget_allocated']) * 100, 1)
    : 0;

// Active tab
$activeTab = $_GET['tab'] ?? 'overview';

// Available tabs (enabled: false hides tabs not yet implemented)
$tabs = [
    'overview'       => ['label' => 'Overview',       'icon' => 'bi-house',             'enabled' => true],
    'pow'            => ['label' => 'POW',            'icon' => 'bi-list-check',        'enabled' => false],
    'daily_logs'     => ['label' => 'Daily Logs',     'icon' => 'bi-calendar-day',      'enabled' => false],
    'accomplishment' => ['label' => 'Accomplishment', 'icon' => 'bi-graph-up',          'enabled' => false],
    'inspections'    => ['label' => 'Inspections',    'icon' => 'bi-clipboard-check',   'enabled' => false],
    'photos'         => ['label' => 'Photos',         'icon' => 'bi-camera',            'enabled' => false],
    'calendar'       => ['label' => 'Calendar',       'icon' => 'bi-calendar-event',    'enabled' => true],
    'letters'        => ['label' => 'Letters',        'icon' => 'bi-envelope',          'enabled' => false],
    'workflow'       => ['label' => 'Workflow',       'icon' => 'bi-diagram-3',         'enabled' => true],
    'budget'         => ['label' => 'Budget',         'icon' => 'bi-wallet2',           'enabled' => true],
    'milestones'     => ['label' => 'Milestones',     'icon' => 'bi-flag',              'enabled' => true],
    'log'            => ['label' => 'Action Log',     'icon' => 'bi-journal-text',      'enabled' => true],
    'files'          => ['label' => 'Files',          'icon' => 'bi-paperclip',         'enabled' => true],
    'turnover'       => ['label' => 'Turnover',       'icon' => 'bi-box-arrow-right',   'enabled' => false],
];

// Fall back to overview if the requested tab is disabled
if (!isset($tabs[$activeTab]) || !$tabs[$activeTab]['enabled']) {
    $activeTab = 'overview';
}
?>

<!-- Header -->
<div class="mb-4">
    <a href="<?= BASE_URL ?>/pages/projects/list.php" class="btn btn-sm btn-outline-secondary mb-3">
        <i class="bi bi-arrow-left me-1"></i> Back to Projects
    </a>
    <div class="d-flex justify-content-between align-items-start flex-wrap gap-2">
        <div>
            <h4 class="fw-bold mb-2"><?= sanitize($project['title']) ?></h4>
            <div class="d-flex gap-2 flex-wrap">
                <span class="badge <?= getClassificationBadgeClass($project) ?>"><?= sanitize(getClassificationLabel($project)) ?></span>
                <span class="badge <?= statusBadgeClass($project['status']) ?>"><?= sanitize($project['status']) ?></span>
                <span class="badge <?= priorityBadgeClass($project['priority']) ?>"><?= sanitize($project['priority']) ?> Priority</span>
                <?php if (isOverdue($project)): ?>
                    <span class="badge bg-danger"><i class="bi bi-exclamation-triangle me-1"></i>Overdue</span>
                <?php endif; ?>
            </div>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <a href="<?= BASE_URL ?>/pages/projects/edit.php?id=<?= $id ?>" class="btn btn-sm btn-outline-primary">
                <i class="bi bi-pencil me-1"></i> Edit
            </a>
            <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="duplicate">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm btn-outline-secondary">
                    <i class="bi bi-copy me-1"></i> Duplicate
                </button>
            </form>
            <?php if (!$project['is_archived']): ?>
            <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                <input type="hidden" name="action" value="archive">
                <input type="hidden" name="id" value="<?= $id ?>">
                <button type="submit" class="btn btn-sm btn-outline-warning"
                        onclick="return confirm('Archive this project?')">
                    <i class="bi bi-archive me-1"></i> Archive
                </button>
            </form>
            <?php endif; ?>
            <button type="button" class="btn btn-sm btn-outline-danger"
                    data-bs-toggle="modal" data-bs-target="#deleteModal">
                <i class="bi bi-trash me-1"></i> Delete
            </button>
        </div>
    </div>
</div>

<!-- Tabs (scrollable) -->
<ul class="nav nav-tabs nav-tabs-scrollable mb-4">
    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
        <?php if (!$tabInfo['enabled']) continue; ?>
    <li class="nav-item">
        <a class="nav-link <?= $activeTab === $tabKey ? 'active' : '' ?>"
           href="?id=<?= $id ?>&tab=<?= $tabKey ?>">
            <i class="bi <?= $tabInfo['icon'] ?> me-1"></i><?= $tabInfo['label'] ?>
        </a>
    </li>
    <?php endforeach; ?>
</ul>

<!-- Tab Content (dispatched to include files) -->
<?php
$tabFile = __DIR__ . '/tabs/' . $activeTab . '.php';
if (file_exists($tabFile)) {
    include $tabFile;
} else {
    echo '<div class="text-center text-muted py-5">
        <i class="bi bi-tools fs-1 d-block mb-2"></i>
        <p>This module is coming soon.</p>
    </div>';
}
?>

<!-- Delete Project Modal -->
<div class="modal fade" id="deleteModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-sm">
        <div class="modal-content">
            <div class="modal-header">
                <h6 class="modal-title">Confirm Delete</h6>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>
            <div class="modal-body">
                Are you sure you want to delete <strong><?= sanitize($project['title']) ?></strong>?
                <p class="text-muted small mt-2 mb-0">This can be undone from the archive.</p>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                <form action="<?= BASE_URL ?>/actions/project_actions.php" method="POST" class="d-inline">
                    <input type="hidden" name="action" value="delete">
                    <input type="hidden" name="id" value="<?= $id ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                </form>
            </div>
        </div>
    </div>
</div>

<?php
$extraScripts = '<script>
// Workflow upload modal - pass stage_id
document.getElementById("wfUploadModal")?.addEventListener("show.bs.modal", function(e) {
    var btn = e.relatedTarget;
    document.getElementById("wfUploadStageId").value = btn.dataset.stageId;
});

document.getElementById("editBudgetModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    document.getElementById("editBudgetId").value = btn.dataset.id;
    document.getElementById("editBudgetName").value = btn.dataset.name;
    document.getElementById("editBudgetCategory").value = btn.dataset.category;
    document.getElementById("editBudgetQty").value = btn.dataset.quantity;
    document.getElementById("editBudgetUnit").value = btn.dataset.unit;
    document.getElementById("editBudgetCost").value = btn.dataset.cost;
});
</script>';

if (isset($pageScripts)) {
    $pageScripts .= $extraScripts;
} else {
    $pageScripts = $extraScripts;
}

require_once __DIR__ . '/../../includes/footer.php';
?>

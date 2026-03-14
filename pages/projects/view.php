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
    'overview'        => ['label' => 'Overview',           'icon' => 'bi-house',           'enabled' => true],
    'pow'             => ['label' => 'POW',                'icon' => 'bi-list-check',      'enabled' => false],
    'daily_logs'      => ['label' => 'Daily Logs',         'icon' => 'bi-calendar-day',    'enabled' => false],
    'accomplishment'  => ['label' => 'Accomplishment',     'icon' => 'bi-graph-up',        'enabled' => false],
    'inspections'     => ['label' => 'Inspections',        'icon' => 'bi-clipboard-check', 'enabled' => false],
    'photos'          => ['label' => 'Photos',             'icon' => 'bi-camera',          'enabled' => false],
    'calendar'        => ['label' => 'Calendar',           'icon' => 'bi-calendar-event',  'enabled' => true],
    'letters'         => ['label' => 'Letters',            'icon' => 'bi-envelope',        'enabled' => false],
    'budget'          => ['label' => 'Scope of Work',      'icon' => 'bi-wallet2',         'enabled' => true],
    'dupa'            => ['label' => 'DUPA',               'icon' => 'bi-cash-stack',      'enabled' => true],
    'milestones'      => ['label' => 'Milestones',         'icon' => 'bi-flag',            'enabled' => true],
    'monitoring'      => ['label' => 'Monitoring',         'icon' => 'bi-graph-up-arrow',  'enabled' => true],
    'log'             => ['label' => 'Action Log',         'icon' => 'bi-journal-text',    'enabled' => true],
    'preconstruction' => ['label' => 'Pre-Construction',   'icon' => 'bi-folder2-open',    'enabled' => true],
    'files'           => ['label' => 'Files',              'icon' => 'bi-paperclip',       'enabled' => true],
    'closeout'        => ['label' => 'Closeout',           'icon' => 'bi-clipboard-check', 'enabled' => true],
    'turnover'        => ['label' => 'Turnover',           'icon' => 'bi-box-arrow-right', 'enabled' => false],
];

// Planning content lives under Scope of Work (Budget tab includes planning.php)
// Allow tab=planning so links to Planning open Budget tab which embeds the Planning Gantt
if ($activeTab === 'planning') {
    $activeTab = 'budget';
}
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
<ul class="nav nav-tabs nav-tabs-scrollable mb-4" id="projectTabsNav" data-project-id="<?= $id ?>">
    <?php foreach ($tabs as $tabKey => $tabInfo): ?>
        <?php if (!$tabInfo['enabled']) continue; ?>
    <li class="nav-item project-tab-item" data-tab-key="<?= $tabKey ?>" draggable="true">
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
<script>
(function() {
    var id = '<?= (int)$id ?>';
    var tab = '<?= htmlspecialchars($activeTab ?? '', ENT_QUOTES, 'UTF-8') ?>';
    if (!id) return;
    var key = 'scroll_view_' + id + '_' + tab;
    var scrollOpt = { behavior: 'auto' };
    try {
        var saved = sessionStorage.getItem(key);
        if (saved !== null) {
            sessionStorage.removeItem(key);
            var y = Math.max(0, parseInt(saved, 10) || 0);
            if (typeof history !== 'undefined' && history.scrollRestoration) history.scrollRestoration = 'manual';
            function applyScroll() { window.scrollTo(0, y, scrollOpt); }
            var guardEnd = 0;
            function runRestore() {
                applyScroll();
                if (y > 0) guardEnd = Date.now() + 1200;
            }
            runRestore();
            requestAnimationFrame(runRestore);
            window.addEventListener('load', function() {
                runRestore();
                setTimeout(runRestore, 0);
                setTimeout(runRestore, 50);
                setTimeout(runRestore, 150);
                setTimeout(runRestore, 350);
                setTimeout(runRestore, 600);
            });
            (function scrollGuard() {
                if (guardEnd && Date.now() < guardEnd) {
                    if (window.scrollY === 0 && y > 0) window.scrollTo(0, y, scrollOpt);
                    requestAnimationFrame(scrollGuard);
                }
            })();
        }
    } catch (e) {}
    window.saveViewScroll = function() {
        try { sessionStorage.setItem(key, String(window.scrollY || document.documentElement.scrollTop)); } catch (e) {}
    };
})();
</script>

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
document.getElementById("editBudgetModal")?.addEventListener("show.bs.modal", function(e) {
    const btn = e.relatedTarget;
    if (!btn || !btn.dataset) return;
    document.getElementById("editBudgetId").value = btn.dataset.id || "";
    document.getElementById("editBudgetName").value = btn.dataset.name || "";
    document.getElementById("editBudgetQty").value = btn.dataset.quantity || "";
    document.getElementById("editBudgetUnit").value = btn.dataset.unit || "";
    document.getElementById("editBudgetCost").value = btn.dataset.cost || "";
    var durEl = document.getElementById("editBudgetDuration");
    if (durEl) durEl.value = (btn.dataset.durationDays !== undefined && btn.dataset.durationDays !== "") ? btn.dataset.durationDays : "";
});

// Enable drag-and-drop reordering of project tabs (per-browser, using localStorage)
document.addEventListener("DOMContentLoaded", function () {
    var nav = document.getElementById("projectTabsNav");
    if (!nav) return;

    var projectId = nav.dataset.projectId || "global";
    var storageKey = "projectTabsOrder:" + projectId;

    function getItems() {
        return Array.prototype.slice.call(nav.querySelectorAll(".project-tab-item"));
    }

    // Apply saved order from localStorage, if any
    try {
        var saved = window.localStorage ? window.localStorage.getItem(storageKey) : null;
        if (saved) {
            var order = JSON.parse(saved);
            if (Array.isArray(order) && order.length) {
                var currentItems = {};
                getItems().forEach(function (li) {
                    currentItems[li.dataset.tabKey] = li;
                });
                order.forEach(function (key) {
                    if (currentItems[key]) {
                        nav.appendChild(currentItems[key]);
                    }
                });
            }
        }
    } catch (e) {
        // ignore storage errors
    }

    var dragSrcEl = null;

    function handleDragStart(e) {
        dragSrcEl = this;
        this.classList.add("dragging");
        e.dataTransfer.effectAllowed = "move";
        try {
            e.dataTransfer.setData("text/plain", this.dataset.tabKey || "");
        } catch (err) {}
    }

    function handleDragOver(e) {
        if (!dragSrcEl) return;
        e.preventDefault();
        e.dataTransfer.dropEffect = "move";
        var target = this;
        if (target === dragSrcEl || !target.classList.contains("project-tab-item")) return;
        var rect = target.getBoundingClientRect();
        var offset = (e.clientX - rect.left) / rect.width;
        if (offset > 0.5) {
            if (target.nextSibling !== dragSrcEl) {
                nav.insertBefore(dragSrcEl, target.nextSibling);
            }
        } else {
            if (target !== dragSrcEl.nextSibling) {
                nav.insertBefore(dragSrcEl, target);
            }
        }
    }

    function handleDrop(e) {
        if (!dragSrcEl) return;
        e.preventDefault();
    }

    function handleDragEnd() {
        if (dragSrcEl) {
            dragSrcEl.classList.remove("dragging");
            dragSrcEl = null;
        }
        // Save new order
        try {
            if (!window.localStorage) return;
            var order = getItems().map(function (li) { return li.dataset.tabKey; });
            window.localStorage.setItem(storageKey, JSON.stringify(order));
        } catch (e) {
            // ignore storage errors
        }
    }

    getItems().forEach(function (li) {
        li.addEventListener("dragstart", handleDragStart);
        li.addEventListener("dragover", handleDragOver);
        li.addEventListener("drop", handleDrop);
        li.addEventListener("dragend", handleDragEnd);
    });
});
</script>';

if (isset($pageScripts)) {
    $pageScripts .= $extraScripts;
} else {
    $pageScripts = $extraScripts;
}

require_once __DIR__ . '/../../includes/footer.php';
?>

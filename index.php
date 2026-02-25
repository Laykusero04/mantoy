<?php
$pageTitle = 'Dashboard';
require_once __DIR__ . '/includes/header.php';

// Get fiscal year from query param or settings
$fiscalYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)$currentFiscalYear;

// Dashboard stats
$counts = getProjectCounts($pdo, $fiscalYear);

// Overdue projects
$stmtOverdue = $pdo->prepare("
    SELECT id, title, project_type, visibility, department, project_subtype, target_end_date, status
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date IS NOT NULL AND target_end_date < CURDATE()
    ORDER BY target_end_date ASC
    LIMIT 5
");
$stmtOverdue->execute([$fiscalYear]);
$overdueProjects = $stmtOverdue->fetchAll();

// Recent projects (last 5 updated)
$stmtRecent = $pdo->prepare("
    SELECT id, title, project_type, visibility, department, project_subtype, status, updated_at
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?
    ORDER BY updated_at DESC
    LIMIT 5
");
$stmtRecent->execute([$fiscalYear]);
$recentProjects = $stmtRecent->fetchAll();

// Upcoming deadlines (next 30 days)
$stmtUpcoming = $pdo->prepare("
    SELECT id, title, project_type, visibility, department, project_subtype, target_end_date, status
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY target_end_date ASC
    LIMIT 5
");
$stmtUpcoming->execute([$fiscalYear]);
$upcomingProjects = $stmtUpcoming->fetchAll();

// Available fiscal years for dropdown
$stmtYears = $pdo->query("SELECT DISTINCT fiscal_year FROM projects WHERE is_deleted = 0 ORDER BY fiscal_year DESC");
$availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($fiscalYear, $availableYears)) {
    $availableYears[] = $fiscalYear;
    rsort($availableYears);
}

// Status badge helper
function statusBadgeClass($status) {
    $map = [
        'Not Started' => 'badge-not-started',
        'In Progress' => 'badge-in-progress',
        'On Hold'     => 'badge-on-hold',
        'Completed'   => 'badge-completed',
        'Cancelled'   => 'badge-cancelled',
    ];
    return $map[$status] ?? 'bg-secondary';
}
?>

<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <h4 class="mb-0 fw-bold">Dashboard</h4>
    <form method="GET" class="d-flex align-items-center">
        <label class="me-2 text-muted small">FY:</label>
        <select name="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
            <?php foreach ($availableYears as $yr): ?>
                <option value="<?= $yr ?>" <?= $yr == $fiscalYear ? 'selected' : '' ?>><?= $yr ?></option>
            <?php endforeach; ?>
        </select>
    </form>
</div>

<!-- Summary Cards Row 1 -->
<div class="row g-3 mb-4">
    <!-- Total Projects -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-folder"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $counts['total'] ?></div>
                    <div class="stat-label">Total Projects</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Not Started -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-secondary bg-opacity-10 text-secondary me-3">
                    <i class="bi bi-clock"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $counts['not_started'] ?></div>
                    <div class="stat-label">Not Started</div>
                </div>
            </div>
        </div>
    </div>
    <!-- In Progress -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-info bg-opacity-10 text-info me-3">
                    <i class="bi bi-arrow-repeat"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $counts['in_progress'] ?></div>
                    <div class="stat-label">In Progress</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Completed -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-success bg-opacity-10 text-success me-3">
                    <i class="bi bi-check-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $counts['completed'] ?></div>
                    <div class="stat-label">Completed</div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Summary Cards Row 2 -->
<div class="row g-3 mb-4">
    <!-- On Hold -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-warning bg-opacity-10 text-warning me-3">
                    <i class="bi bi-pause-circle"></i>
                </div>
                <div>
                    <div class="stat-value"><?= $counts['on_hold'] ?></div>
                    <div class="stat-label">On Hold</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Total Budget -->
    <div class="col-xl-3 col-md-6">
        <div class="card stat-card shadow-sm">
            <div class="card-body d-flex align-items-center">
                <div class="stat-icon bg-primary bg-opacity-10 text-primary me-3">
                    <i class="bi bi-wallet2"></i>
                </div>
                <div>
                    <div class="stat-value"><?= formatCurrency($counts['total_budget']) ?></div>
                    <div class="stat-label">Total Budget</div>
                </div>
            </div>
        </div>
    </div>
    <!-- Utilization -->
    <div class="col-xl-6 col-md-12">
        <div class="card stat-card shadow-sm">
            <div class="card-body">
                <div class="d-flex justify-content-between align-items-center mb-2">
                    <span class="stat-label">Budget Utilization</span>
                    <span class="fw-bold"><?= $counts['utilization'] ?>%</span>
                </div>
                <div class="progress" style="height: 10px;">
                    <div class="progress-bar bg-<?= $counts['utilization'] > 90 ? 'danger' : ($counts['utilization'] > 70 ? 'warning' : 'success') ?>"
                         role="progressbar"
                         style="width: <?= min($counts['utilization'], 100) ?>%">
                    </div>
                </div>
                <div class="d-flex justify-content-between mt-2">
                    <small class="text-muted">Actual: <?= formatCurrency($counts['total_actual']) ?></small>
                    <small class="text-muted">Budget: <?= formatCurrency($counts['total_budget']) ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <!-- Pie Chart: Projects by Status -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Projects by Status</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="statusChart"></canvas>
                </div>
            </div>
        </div>
    </div>
    <!-- Bar Chart: Budget by Department -->
    <div class="col-lg-6">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Budget by Department</h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Overdue Projects -->
<?php if (!empty($overdueProjects)): ?>
<div class="row g-3 mb-4">
    <div class="col-12">
        <div class="card shadow-sm border-danger border-start border-4">
            <div class="card-header bg-white d-flex align-items-center">
                <i class="bi bi-exclamation-triangle text-danger me-2"></i>
                <h6 class="mb-0 section-title">Overdue Projects</h6>
            </div>
            <div class="card-body p-0">
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Classification</th>
                                <th>Due Date</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($overdueProjects as $proj): ?>
                            <tr>
                                <td class="fw-medium"><?= sanitize($proj['title']) ?></td>
                                <td><span class="badge <?= getClassificationBadgeClass($proj) ?>"><?= sanitize(getClassificationLabel($proj)) ?></span></td>
                                <td class="text-danger"><?= formatDate($proj['target_end_date']) ?></td>
                                <td><span class="badge <?= statusBadgeClass($proj['status']) ?>"><?= sanitize($proj['status']) ?></span></td>
                                <td><a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Recent Projects & Upcoming Deadlines -->
<div class="row g-3 mb-4">
    <!-- Recent Projects -->
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Recent Projects</h6>
            </div>
            <div class="card-body p-0">
                <?php if (empty($recentProjects)): ?>
                    <div class="p-4 text-center text-muted">
                        <i class="bi bi-inbox fs-1 d-block mb-2"></i>
                        No projects yet. <a href="<?= BASE_URL ?>/pages/projects/create.php">Create one</a>.
                    </div>
                <?php else: ?>
                <div class="table-responsive">
                    <table class="table table-hover mb-0">
                        <thead>
                            <tr>
                                <th>Title</th>
                                <th>Classification</th>
                                <th>Status</th>
                                <th></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($recentProjects as $proj): ?>
                            <tr>
                                <td class="fw-medium"><?= sanitize($proj['title']) ?></td>
                                <td><span class="badge <?= getClassificationBadgeClass($proj) ?>"><?= sanitize(getClassificationLabel($proj)) ?></span></td>
                                <td><span class="badge <?= statusBadgeClass($proj['status']) ?>"><?= sanitize($proj['status']) ?></span></td>
                                <td><a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">View</a></td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Upcoming Deadlines -->
    <div class="col-lg-5">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Upcoming Deadlines <small class="text-muted">(next 30 days)</small></h6>
            </div>
            <div class="card-body">
                <?php if (empty($upcomingProjects)): ?>
                    <div class="text-center text-muted py-3">
                        <i class="bi bi-calendar-check fs-1 d-block mb-2"></i>
                        No upcoming deadlines.
                    </div>
                <?php else: ?>
                    <ul class="list-group list-group-flush">
                        <?php foreach ($upcomingProjects as $proj): ?>
                        <li class="list-group-item d-flex justify-content-between align-items-center px-0">
                            <div>
                                <div class="fw-medium"><?= sanitize($proj['title']) ?></div>
                                <small class="text-muted"><?= formatDate($proj['target_end_date']) ?></small>
                            </div>
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary">View</a>
                        </li>
                        <?php endforeach; ?>
                    </ul>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php
// Chart data passed to JS
$chartData = [
    'status' => [
        'labels' => ['Not Started', 'In Progress', 'On Hold', 'Completed', 'Cancelled'],
        'data' => [
            $counts['not_started'],
            $counts['in_progress'],
            $counts['on_hold'],
            $counts['completed'],
            $counts['cancelled'],
        ],
        'colors' => ['#6b7280', '#0891b2', '#d97706', '#059669', '#dc2626'],
    ],
    'budget' => [
        'labels' => ['CEO', 'Mayors', 'Private'],
        'data' => [
            (float)$counts['ceo_budget'],
            (float)$counts['mayors_budget'],
            (float)$counts['private_budget'],
        ],
        'colors' => ['#0891b2', '#1a56db', '#374151'],
    ],
];

$pageScripts = '<script>
const chartData = ' . json_encode($chartData) . ';

// Status Pie Chart
new Chart(document.getElementById("statusChart"), {
    type: "doughnut",
    data: {
        labels: chartData.status.labels,
        datasets: [{
            data: chartData.status.data,
            backgroundColor: chartData.status.colors,
            borderWidth: 2,
            borderColor: "#fff"
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { position: "bottom", labels: { padding: 15, usePointStyle: true } }
        }
    }
});

// Budget Bar Chart
new Chart(document.getElementById("budgetChart"), {
    type: "bar",
    data: {
        labels: chartData.budget.labels,
        datasets: [{
            label: "Budget Allocated",
            data: chartData.budget.data,
            backgroundColor: chartData.budget.colors,
            borderRadius: 4,
            barThickness: 50
        }]
    },
    options: {
        responsive: true,
        maintainAspectRatio: false,
        plugins: {
            legend: { display: false }
        },
        scales: {
            y: {
                beginAtZero: true,
                ticks: {
                    callback: function(value) {
                        if (value >= 1000000) return "\u20B1" + (value / 1000000).toFixed(1) + "M";
                        if (value >= 1000) return "\u20B1" + (value / 1000).toFixed(0) + "K";
                        return "\u20B1" + value;
                    }
                }
            }
        }
    }
});
</script>';

require_once __DIR__ . '/includes/footer.php';
?>

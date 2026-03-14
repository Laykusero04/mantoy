<?php
// Shared dashboard view. Expects: $pageTitle, $fiscalYear, $availableYears, $counts, $completionRate,
// $totalOverdueCount, $overdueProjects, $atRiskProjects, $hasAttentionItems, $overdueMilestoneCount,
// $upcomingMilestones, $recentLogs, $upcomingDeadlines, $recentProjects, $chartData.
// Optional: $listVisibility ('Private' or 'Public') for "View All" link filter.
$listUrl = BASE_URL . '/pages/projects/list.php';
if (!empty($listVisibility)) {
    $listUrl .= '?visibility=' . urlencode($listVisibility) . '&year=' . (int)$fiscalYear;
} else {
    $listUrl .= '?year=' . (int)$fiscalYear;
}
$budgetChartTitle = $chartData['budget']['labels'][0] === 'Private' && count($chartData['budget']['labels']) === 1 ? 'Budget' : 'Budget by Department';
?>
<!-- Page Header -->
<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-0 fw-bold"><?= sanitize($pageTitle) ?></h4>
        <small class="text-muted">FY <?= $fiscalYear ?> Overview</small>
    </div>
    <div class="d-flex align-items-center gap-2">
        <form method="GET" class="d-flex align-items-center">
            <label class="me-2 text-muted small">FY:</label>
            <select name="year" class="form-select form-select-sm" style="width: auto;" onchange="this.form.submit()">
                <?php foreach ($availableYears as $yr): ?>
                    <option value="<?= $yr ?>" <?= $yr == $fiscalYear ? 'selected' : '' ?>><?= $yr ?></option>
                <?php endforeach; ?>
            </select>
        </form>
        <a href="<?= BASE_URL ?>/pages/projects/create.php" class="btn btn-primary btn-sm">
            <i class="bi bi-plus-lg me-1"></i>New Project
        </a>
    </div>
</div>

<!-- Stat Cards -->
<div class="row g-3 mb-4">
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body text-center py-3">
                <div class="stat-value"><?= $counts['total'] ?></div>
                <div class="stat-label">Total Projects</div>
                <?php if ($counts['on_hold'] > 0 || $counts['cancelled'] > 0): ?>
                    <small class="text-muted">
                        <?= $counts['on_hold'] ?> on hold<?= $counts['cancelled'] > 0 ? ' · ' . $counts['cancelled'] . ' cancelled' : '' ?>
                    </small>
                <?php else: ?>
                    <small class="text-muted"><?= $counts['not_started'] ?> not started</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card shadow-sm h-100 border-start border-3 border-info">
            <div class="card-body text-center py-3">
                <div class="stat-value text-info"><?= $counts['in_progress'] ?></div>
                <div class="stat-label">In Progress</div>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card shadow-sm h-100 border-start border-3 border-success">
            <div class="card-body text-center py-3">
                <div class="stat-value text-success"><?= $counts['completed'] ?></div>
                <div class="stat-label">Completed</div>
                <small class="text-muted"><?= $completionRate ?>% rate</small>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-4 col-6">
        <div class="card stat-card shadow-sm h-100 <?= $totalOverdueCount > 0 ? 'border-start border-3 border-danger stat-card-alert' : '' ?>">
            <div class="card-body text-center py-3">
                <div class="stat-value <?= $totalOverdueCount > 0 ? 'text-danger' : '' ?>"><?= $totalOverdueCount ?></div>
                <div class="stat-label">Overdue</div>
                <?php if ($totalOverdueCount > 0): ?>
                    <small class="text-danger"><i class="bi bi-exclamation-circle"></i> Needs attention</small>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-xl col-md-8 col-12">
        <div class="card stat-card shadow-sm h-100">
            <div class="card-body py-3">
                <div class="d-flex justify-content-between align-items-center mb-1">
                    <div class="stat-label mb-0">Budget Utilization</div>
                    <span class="fw-bold"><?= $counts['utilization'] ?>%</span>
                </div>
                <div class="progress mb-2" style="height: 8px;">
                    <div class="progress-bar bg-<?= $counts['utilization'] > 90 ? 'danger' : ($counts['utilization'] > 70 ? 'warning' : 'primary') ?>"
                         style="width: <?= min($counts['utilization'], 100) ?>%"></div>
                </div>
                <div class="d-flex justify-content-between">
                    <small class="text-muted">Spent: <?= formatCurrency($counts['total_actual']) ?></small>
                    <small class="text-muted">Budget: <?= formatCurrency($counts['total_budget']) ?></small>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Attention Needed -->
<?php if ($hasAttentionItems): ?>
<div class="card shadow-sm border-start border-4 border-danger mb-4">
    <div class="card-header bg-danger bg-opacity-10 d-flex align-items-center justify-content-between py-2">
        <div>
            <i class="bi bi-exclamation-triangle-fill text-danger me-2"></i>
            <span class="fw-semibold">Attention Needed</span>
        </div>
        <div class="d-flex gap-2 flex-wrap">
            <?php if ($totalOverdueCount > 0): ?>
                <span class="badge bg-danger"><?= $totalOverdueCount ?> overdue project<?= $totalOverdueCount > 1 ? 's' : '' ?></span>
            <?php endif; ?>
            <?php if (count($atRiskProjects) > 0): ?>
                <span class="badge bg-warning text-dark"><?= count($atRiskProjects) ?> due this week</span>
            <?php endif; ?>
            <?php if ($overdueMilestoneCount > 0): ?>
                <span class="badge bg-secondary"><?= $overdueMilestoneCount ?> milestone<?= $overdueMilestoneCount > 1 ? 's' : '' ?> overdue</span>
            <?php endif; ?>
        </div>
    </div>
    <div class="card-body p-0">
        <div class="table-responsive">
            <table class="table table-hover mb-0 small">
                <tbody>
                    <?php foreach ($overdueProjects as $proj): ?>
                    <tr>
                        <td style="width: 20px;" class="ps-3">
                            <span class="d-inline-block rounded-circle bg-danger" style="width:8px;height:8px;"></span>
                        </td>
                        <td class="fw-medium">
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="text-decoration-none text-dark">
                                <?= sanitize($proj['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge <?= statusBadgeClass($proj['status']) ?>"><?= sanitize($proj['status']) ?></span></td>
                        <td class="text-danger fw-medium"><?= $proj['days_overdue'] ?> day<?= $proj['days_overdue'] != 1 ? 's' : '' ?> overdue</td>
                        <td class="text-end pe-3">
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-danger py-0">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    <?php foreach ($atRiskProjects as $proj): ?>
                    <tr>
                        <td style="width: 20px;" class="ps-3">
                            <span class="d-inline-block rounded-circle bg-warning" style="width:8px;height:8px;"></span>
                        </td>
                        <td class="fw-medium">
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="text-decoration-none text-dark">
                                <?= sanitize($proj['title']) ?>
                            </a>
                        </td>
                        <td><span class="badge <?= statusBadgeClass($proj['status']) ?>"><?= sanitize($proj['status']) ?></span></td>
                        <td class="text-warning fw-medium">Due in <?= $proj['days_left'] ?> day<?= $proj['days_left'] != 1 ? 's' : '' ?></td>
                        <td class="text-end pe-3">
                            <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-warning py-0">View</a>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- Charts Row -->
<div class="row g-3 mb-4">
    <div class="col-lg-5">
        <div class="card shadow-sm h-100">
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
    <div class="col-lg-7">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title"><?= sanitize($budgetChartTitle) ?></h6>
            </div>
            <div class="card-body">
                <div class="chart-container">
                    <canvas id="budgetChart"></canvas>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Three-Column Info Panels -->
<div class="row g-3 mb-4">
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 section-title"><i class="bi bi-flag me-1 text-primary"></i>Milestones</h6>
                <small class="text-muted">next 14 days</small>
            </div>
            <div class="card-body py-2">
                <?php if (empty($upcomingMilestones)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-flag fs-3 d-block mb-2 opacity-50"></i>
                        No upcoming milestones
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($upcomingMilestones as $ms): ?>
                        <div class="d-flex align-items-start gap-2 p-2 rounded dash-list-item">
                            <i class="bi bi-flag-fill text-primary small mt-1"></i>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="fw-medium small text-truncate"><?= sanitize($ms['title']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $ms['project_id'] ?>&tab=milestones" class="text-decoration-none">
                                        <?= sanitize($ms['project_title']) ?>
                                    </a>
                                    &middot; <?= formatDate($ms['target_date']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 section-title"><i class="bi bi-activity me-1 text-info"></i>Activity</h6>
                <small class="text-muted">recent</small>
            </div>
            <div class="card-body py-2">
                <?php if (empty($recentLogs)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-journal-text fs-3 d-block mb-2 opacity-50"></i>
                        No recent activity
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($recentLogs as $log): ?>
                        <div class="d-flex align-items-start gap-2 p-2 rounded dash-list-item">
                            <i class="bi bi-circle-fill text-muted mt-2" style="font-size: 0.35rem;"></i>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="small text-truncate"><?= sanitize($log['action_taken']) ?></div>
                                <div class="text-muted" style="font-size: 0.75rem;">
                                    <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $log['project_id'] ?>&tab=log" class="text-decoration-none">
                                        <?= sanitize($log['project_title']) ?>
                                    </a>
                                    <?php if (!empty($log['logged_by'])): ?>
                                        &middot; <?= sanitize($log['logged_by']) ?>
                                    <?php endif; ?>
                                    &middot; <?= formatDate($log['log_date']) ?>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <div class="col-lg-4">
        <div class="card shadow-sm h-100">
            <div class="card-header bg-white d-flex justify-content-between align-items-center py-2">
                <h6 class="mb-0 section-title"><i class="bi bi-calendar-event me-1 text-warning"></i>Deadlines</h6>
                <small class="text-muted">next 30 days</small>
            </div>
            <div class="card-body py-2">
                <?php if (empty($upcomingDeadlines)): ?>
                    <div class="text-center text-muted py-4">
                        <i class="bi bi-calendar-check fs-3 d-block mb-2 opacity-50"></i>
                        No upcoming deadlines
                    </div>
                <?php else: ?>
                    <div class="d-flex flex-column gap-1">
                        <?php foreach ($upcomingDeadlines as $proj): ?>
                        <div class="d-flex align-items-center gap-2 p-2 rounded dash-list-item">
                            <div class="text-center" style="min-width: 36px;">
                                <div class="fw-bold small lh-1 <?= $proj['days_left'] <= 3 ? 'text-danger' : ($proj['days_left'] <= 7 ? 'text-warning' : 'text-muted') ?>">
                                    <?= $proj['days_left'] ?>
                                </div>
                                <div style="font-size: 0.6rem;" class="text-muted text-uppercase">days</div>
                            </div>
                            <div class="flex-grow-1" style="min-width: 0;">
                                <div class="fw-medium small text-truncate">
                                    <a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="text-decoration-none text-dark">
                                        <?= sanitize($proj['title']) ?>
                                    </a>
                                </div>
                                <div style="font-size: 0.75rem;">
                                    <span class="text-muted"><?= formatDate($proj['target_end_date']) ?></span>
                                    &middot;
                                    <span class="badge <?= statusBadgeClass($proj['status']) ?>" style="font-size: 0.6rem;"><?= sanitize($proj['status']) ?></span>
                                </div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<!-- Recent Projects -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Recent Projects</h6>
        <a href="<?= $listUrl ?>" class="btn btn-sm btn-outline-primary">View All</a>
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
                        <th>Priority</th>
                        <th>Status</th>
                        <th>Budget</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($recentProjects as $proj): ?>
                    <tr>
                        <td class="fw-medium"><?= sanitize($proj['title']) ?></td>
                        <td><span class="badge <?= getClassificationBadgeClass($proj) ?>"><?= sanitize(getClassificationLabel($proj)) ?></span></td>
                        <td><span class="badge <?= priorityBadgeClass($proj['priority']) ?>"><?= sanitize($proj['priority']) ?></span></td>
                        <td><span class="badge <?= statusBadgeClass($proj['status']) ?>"><?= sanitize($proj['status']) ?></span></td>
                        <td class="text-muted small"><?= formatCurrency($proj['budget_allocated']) ?></td>
                        <td><a href="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $proj['id'] ?>" class="btn btn-sm btn-outline-primary py-0">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        <?php endif; ?>
    </div>
</div>

<?php
$pageScripts = '<script>
const chartData = ' . json_encode($chartData) . ';

const centerTextPlugin = {
    id: "centerText",
    afterDraw(chart) {
        if (chart.config.type !== "doughnut") return;
        const { ctx, chartArea } = chart;
        const centerX = (chartArea.left + chartArea.right) / 2;
        const centerY = (chartArea.top + chartArea.bottom) / 2;
        const total = chart.data.datasets[0].data.reduce((a, b) => a + b, 0);
        ctx.save();
        ctx.font = "bold 1.5rem system-ui";
        ctx.fillStyle = "#374151";
        ctx.textAlign = "center";
        ctx.textBaseline = "middle";
        ctx.fillText(total, centerX, centerY - 8);
        ctx.font = "0.7rem system-ui";
        ctx.fillStyle = "#6b7280";
        ctx.fillText("TOTAL", centerX, centerY + 12);
        ctx.restore();
    }
};

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
        cutout: "65%",
        plugins: {
            legend: { position: "bottom", labels: { padding: 15, usePointStyle: true, pointStyle: "circle" } }
        }
    },
    plugins: [centerTextPlugin]
});

new Chart(document.getElementById("budgetChart"), {
    type: "bar",
    data: {
        labels: chartData.budget.labels,
        datasets: [{
            label: "Budget Allocated",
            data: chartData.budget.data,
            backgroundColor: chartData.budget.colors,
            borderRadius: 4,
            barThickness: 28
        }]
    },
    options: {
        indexAxis: "y",
        responsive: true,
        maintainAspectRatio: false,
        plugins: { legend: { display: false } },
        scales: {
            x: {
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
?>

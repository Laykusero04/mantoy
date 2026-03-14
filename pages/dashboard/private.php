<?php
$dashboardVisibility = 'Private';
$pageTitle = 'Private Dashboard';
require_once __DIR__ . '/../../includes/header.php';

$fiscalYear = isset($_GET['year']) ? (int)$_GET['year'] : (int)$currentFiscalYear;
$counts = getProjectCounts($pdo, $fiscalYear, $dashboardVisibility);

// Available fiscal years
$stmtYears = $pdo->query("SELECT DISTINCT fiscal_year FROM projects WHERE is_deleted = 0 ORDER BY fiscal_year DESC");
$availableYears = $stmtYears->fetchAll(PDO::FETCH_COLUMN);
if (!in_array($fiscalYear, $availableYears)) {
    $availableYears[] = $fiscalYear;
    rsort($availableYears);
}

$visParam = [$fiscalYear, $dashboardVisibility];

// Total overdue count
$stmtOverdueCount = $pdo->prepare("
    SELECT COUNT(*) FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date IS NOT NULL AND target_end_date < CURDATE()
");
$stmtOverdueCount->execute($visParam);
$totalOverdueCount = (int)$stmtOverdueCount->fetchColumn();

// Overdue projects
$stmtOverdue = $pdo->prepare("
    SELECT id, title, visibility, department, project_subtype, target_end_date, status, priority,
           DATEDIFF(CURDATE(), target_end_date) as days_overdue
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date IS NOT NULL AND target_end_date < CURDATE()
    ORDER BY days_overdue DESC
    LIMIT 5
");
$stmtOverdue->execute($visParam);
$overdueProjects = $stmtOverdue->fetchAll();

// At-risk projects
$stmtAtRisk = $pdo->prepare("
    SELECT id, title, visibility, department, project_subtype, target_end_date, status, priority,
           DATEDIFF(target_end_date, CURDATE()) as days_left
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY)
    ORDER BY target_end_date ASC
    LIMIT 5
");
$stmtAtRisk->execute($visParam);
$atRiskProjects = $stmtAtRisk->fetchAll();

// Upcoming milestones
$stmtMilestones = $pdo->prepare("
    SELECT m.title, m.target_date, p.id as project_id, p.title as project_title
    FROM milestones m
    JOIN projects p ON m.project_id = p.id
    WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? AND p.visibility = ?
      AND m.status = 'Pending' AND m.target_date IS NOT NULL
      AND m.target_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 14 DAY)
    ORDER BY m.target_date ASC
    LIMIT 6
");
$stmtMilestones->execute($visParam);
$upcomingMilestones = $stmtMilestones->fetchAll();

// Overdue milestones count
$stmtOverdueMilestones = $pdo->prepare("
    SELECT COUNT(*) FROM milestones m
    JOIN projects p ON m.project_id = p.id
    WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? AND p.visibility = ?
      AND m.status = 'Pending' AND m.target_date IS NOT NULL AND m.target_date < CURDATE()
");
$stmtOverdueMilestones->execute($visParam);
$overdueMilestoneCount = (int)$stmtOverdueMilestones->fetchColumn();

// Recent activity logs
$stmtLogs = $pdo->prepare("
    SELECT al.action_taken, al.log_date, al.logged_by,
           p.id as project_id, p.title as project_title
    FROM action_logs al
    JOIN projects p ON al.project_id = p.id
    WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? AND p.visibility = ?
    ORDER BY al.created_at DESC
    LIMIT 8
");
$stmtLogs->execute($visParam);
$recentLogs = $stmtLogs->fetchAll();

// Recent projects
$stmtRecent = $pdo->prepare("
    SELECT id, title, visibility, department, project_subtype, status, priority,
           budget_allocated, updated_at
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = ?
    ORDER BY updated_at DESC
    LIMIT 6
");
$stmtRecent->execute($visParam);
$recentProjects = $stmtRecent->fetchAll();

// Upcoming deadlines
$stmtUpcoming = $pdo->prepare("
    SELECT id, title, visibility, department, project_subtype, target_end_date, status,
           DATEDIFF(target_end_date, CURDATE()) as days_left
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = ?
      AND status NOT IN ('Completed', 'Cancelled')
      AND target_end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY target_end_date ASC
    LIMIT 8
");
$stmtUpcoming->execute($visParam);
$upcomingDeadlines = $stmtUpcoming->fetchAll();

$completionRate = $counts['total'] > 0 ? round(($counts['completed'] / $counts['total']) * 100) : 0;
$hasAttentionItems = $totalOverdueCount > 0 || count($atRiskProjects) > 0 || $overdueMilestoneCount > 0;

// Budget chart: Private only (single slice)
$chartData = [
    'status' => [
        'labels' => STATUS_OPTIONS,
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
        'labels' => ['Private'],
        'data' => [(float)$counts['private_budget']],
        'colors' => ['#374151'],
    ],
];
$listVisibility = 'Private';
require __DIR__ . '/dashboard_view.php';
require_once __DIR__ . '/../../includes/footer.php';

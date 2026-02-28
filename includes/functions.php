<?php
session_start();

/**
 * Log a message to logs/app.log
 */
function appLog($level, $message, $context = []) {
    $logDir = dirname(__DIR__) . '/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0755, true);
    }
    $timestamp = date('Y-m-d H:i:s');
    $entry = "[$timestamp] [$level] $message";
    if ($context) {
        $entry .= ' | ' . json_encode($context, JSON_UNESCAPED_SLASHES);
    }
    error_log($entry . PHP_EOL, 3, $logDir . '/app.log');
}

/**
 * Redirect to a URL
 */
function redirect($url) {
    header("Location: $url");
    exit;
}

/**
 * Sanitize output for HTML display
 */
function sanitize($input) {
    return htmlspecialchars($input ?? '', ENT_QUOTES, 'UTF-8');
}

/**
 * Format a number as currency (PHP peso)
 */
function formatCurrency($amount, $pdo = null) {
    $symbol = '₱';
    return $symbol . number_format((float)$amount, 2);
}

/**
 * Format a date for display
 */
function formatDate($date) {
    if (empty($date)) return '—';
    return date('M d, Y', strtotime($date));
}

/**
 * Fetch all settings as key => value array
 */
function getSettings($pdo) {
    $stmt = $pdo->query("SELECT setting_key, setting_value FROM settings");
    $settings = [];
    while ($row = $stmt->fetch()) {
        $settings[$row['setting_key']] = $row['setting_value'];
    }
    return $settings;
}

/**
 * Set a flash message in session
 */
function flashMessage($type, $message) {
    $_SESSION['flash'] = [
        'type' => $type,
        'message' => $message
    ];
}

/**
 * Get and clear the flash message
 */
function getFlashMessage() {
    if (isset($_SESSION['flash'])) {
        $flash = $_SESSION['flash'];
        unset($_SESSION['flash']);
        return $flash;
    }
    return null;
}

/**
 * Get classification label for display
 */
function getClassificationLabel($project) {
    if ($project['visibility'] === 'Private') return 'Private';
    if (!empty($project['department']) && !empty($project['project_subtype'])) {
        return $project['department'] . ' - ' . $project['project_subtype'];
    }
    return $project['visibility'];
}

/**
 * Get classification badge CSS class
 */
function getClassificationBadgeClass($project) {
    if ($project['visibility'] === 'Private') return 'bg-dark';
    if ($project['department'] === 'CEO') return 'bg-info';
    if ($project['department'] === 'Mayors') return 'bg-primary';
    return 'bg-secondary';
}

/**
 * Get active departments from DB.
 */
function getDepartments($pdo) {
    $stmt = $pdo->query("SELECT name FROM departments WHERE is_active = 1 ORDER BY sort_order");
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Get department → subtypes mapping from DB.
 */
function getDepartmentSubtypes($pdo) {
    $stmt = $pdo->query("
        SELECT d.name AS dept, ds.name AS subtype
        FROM department_subtypes ds
        JOIN departments d ON ds.department_id = d.id
        WHERE ds.is_active = 1 AND d.is_active = 1
        ORDER BY d.sort_order, ds.sort_order
    ");
    $map = [];
    foreach ($stmt as $row) {
        $map[$row['dept']][] = $row['subtype'];
    }
    return $map;
}

/**
 * Get dashboard project counts for a fiscal year
 */
function getProjectCounts($pdo, $fiscalYear) {
    $counts = [];

    // Total projects (not deleted, not archived)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?");
    $stmt->execute([$fiscalYear]);
    $counts['total'] = $stmt->fetchColumn();

    // By status
    $statuses = STATUS_OPTIONS;
    foreach ($statuses as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND status = ?");
        $stmt->execute([$fiscalYear, $status]);
        $key = strtolower(str_replace(' ', '_', $status));
        $counts[$key] = $stmt->fetchColumn();
    }

    // Budget totals
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(budget_allocated), 0) as total_budget, COALESCE(SUM(actual_cost), 0) as total_actual FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?");
    $stmt->execute([$fiscalYear]);
    $budget = $stmt->fetch();
    $counts['total_budget'] = $budget['total_budget'];
    $counts['total_actual'] = $budget['total_actual'];
    $counts['utilization'] = $budget['total_budget'] > 0
        ? round(($budget['total_actual'] / $budget['total_budget']) * 100, 1)
        : 0;

    // By department
    $departments = getDepartments($pdo);
    foreach ($departments as $dept) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(budget_allocated), 0) as budget FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND department = ?");
        $stmt->execute([$fiscalYear, $dept]);
        $row = $stmt->fetch();
        $key = strtolower($dept);
        $counts[$key . '_count'] = $row['cnt'];
        $counts[$key . '_budget'] = $row['budget'];
    }

    // By subtype
    $allSubtypes = getDepartmentSubtypes($pdo);
    $subtypes = array_merge(...array_values($allSubtypes));
    foreach ($subtypes as $subtype) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(budget_allocated), 0) as budget FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND project_subtype = ?");
        $stmt->execute([$fiscalYear, $subtype]);
        $row = $stmt->fetch();
        $key = strtolower(str_replace(' ', '_', $subtype));
        $counts[$key . '_count'] = $row['cnt'];
        $counts[$key . '_budget'] = $row['budget'];
    }

    // Private count
    $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(budget_allocated), 0) as budget FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND visibility = 'Private'");
    $stmt->execute([$fiscalYear]);
    $row = $stmt->fetch();
    $counts['private_count'] = $row['cnt'];
    $counts['private_budget'] = $row['budget'];

    return $counts;
}

/**
 * Check if a project is overdue
 */
function isOverdue($project) {
    if (in_array($project['status'], ['Completed', 'Cancelled'])) {
        return false;
    }
    if (empty($project['target_end_date'])) {
        return false;
    }
    return strtotime($project['target_end_date']) < strtotime('today');
}

/**
 * Initialize workflow stages for a project
 */
function initWorkflowStages($pdo, $projectId) {
    $stages = [
        ['Incoming Communication', 1],
        ['Action Taken', 2],
        ['Outgoing Communication', 3],
        ['Status', 4],
        ['Preparation of Plans/Estimates', 5],
    ];
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO workflow_stages (project_id, stage_type, stage_order)
        VALUES (?, ?, ?)
    ");
    foreach ($stages as $s) {
        $stmt->execute([$projectId, $s[0], $s[1]]);
    }
}

/**
 * Get workflow completion percentage
 */
function getWorkflowProgress($stages) {
    $total = count($stages);
    $completed = count(array_filter($stages, fn($s) => $s['status'] === 'Completed'));
    return $total > 0 ? round(($completed / $total) * 100) : 0;
}

/**
 * Convert stage type to URL-safe slug
 */
function stageSlug($stageType) {
    return strtolower(preg_replace('/[^a-z0-9]+/i', '-', $stageType));
}

/**
 * Get the current page name for sidebar active state
 */
function getCurrentPage() {
    $script = $_SERVER['SCRIPT_NAME'] ?? '';
    return basename($script, '.php');
}

/**
 * Get the current directory path for sidebar active state
 */
function getCurrentPath() {
    return $_SERVER['SCRIPT_NAME'] ?? '';
}

/**
 * Get CSS class for a project status badge
 */
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

/**
 * Get CSS class for a priority badge
 */
function priorityBadgeClass($priority) {
    $map = ['High' => 'badge-high', 'Medium' => 'badge-medium', 'Low' => 'badge-low'];
    return $map[$priority] ?? 'bg-secondary';
}

/**
 * Get Bootstrap icon class for a file extension
 */
function getFileTypeIcon($ext) {
    if (in_array($ext, ['jpg', 'jpeg', 'png', 'gif'])) return 'bi-file-earmark-image text-success';
    if ($ext === 'pdf') return 'bi-file-earmark-pdf text-danger';
    if (in_array($ext, ['doc', 'docx'])) return 'bi-file-earmark-word text-primary';
    if (in_array($ext, ['xls', 'xlsx'])) return 'bi-file-earmark-excel text-success';
    if (in_array($ext, ['ppt', 'pptx'])) return 'bi-file-earmark-ppt text-warning';
    if (in_array($ext, ['mp4', 'avi', 'mov'])) return 'bi-camera-video text-info';
    if ($ext === 'mp3') return 'bi-music-note-beamed text-purple';
    if (in_array($ext, ['dwg', 'dxf'])) return 'bi-rulers text-secondary';
    if (in_array($ext, ['skp', 'rvt'])) return 'bi-building text-secondary';
    if (in_array($ext, ['zip', 'rar'])) return 'bi-file-earmark-zip text-warning';
    return 'bi-file-earmark text-muted';
}

/**
 * Generate a safe, unique filename for uploads
 */
function generateSafeFilename($originalName, $index = null) {
    $safe = preg_replace('/[^a-zA-Z0-9._-]/', '_', $originalName);
    $prefix = $index !== null ? time() . '_' . $index . '_' : time() . '_';
    return $prefix . $safe;
}

/**
 * Validate a single uploaded file (size + extension).
 * Returns null on success, or an error message string on failure.
 */
function validateUpload($fileName, $fileSize) {
    if ($fileSize > MAX_FILE_SIZE) {
        return $fileName . ': exceeds 50MB limit.';
    }
    $ext = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    if (!in_array($ext, ALLOWED_EXTENSIONS)) {
        return $fileName . ': file type not allowed.';
    }
    if (in_array($ext, ALLOWED_IMAGE_EXTENSIONS) && $fileSize > MAX_IMAGE_SIZE) {
        return $fileName . ': image exceeds 10MB limit.';
    }
    return null;
}

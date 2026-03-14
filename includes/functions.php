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
 * Recalculate project actual_cost from SUM(calendar_events.daily_cost) for the given project.
 * Call after create/update/delete of calendar events for that project.
 *
 * @param PDO $pdo
 * @param int $projectId
 */
function recalcProjectActualCost($pdo, $projectId) {
    $projectId = (int) $projectId;
    if ($projectId <= 0) return;
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(daily_cost), 0) FROM calendar_events WHERE project_id = ?");
    $stmt->execute([$projectId]);
    $total = (float) $stmt->fetchColumn();
    $upd = $pdo->prepare("UPDATE projects SET actual_cost = ? WHERE id = ?");
    $upd->execute([$total, $projectId]);
}

/**
 * Write a simple activity entry into action_logs for a project.
 * Encodes the originating module in the action_taken field.
 *
 * @param PDO         $pdo
 * @param int         $projectId
 * @param string      $module   Short module label, e.g. 'Inspection', 'Materials', 'Calendar'
 * @param string      $message  What happened, e.g. 'Inspection approved'
 * @param string|null $loggedBy Who did it (fallback 'System' when null)
 * @param string|null $logDate  When it happened (Y-m-d H:i:s, fallback now() when null)
 */
function logProjectActivity($pdo, $projectId, $module, $message, $loggedBy = null, $logDate = null) {
    $projectId = (int)$projectId;
    if ($projectId <= 0) {
        return;
    }
    $module = trim((string)$module);
    $message = trim((string)$message);
    if ($module === '' || $message === '') {
        return;
    }

    $prefix = '[' . $module . '] ';
    $actionTaken = mb_substr($prefix . $message, 0, 500);
    $notes = null;
    $who = $loggedBy !== null && trim($loggedBy) !== '' ? trim($loggedBy) : 'System';

    if ($logDate === null || trim($logDate) === '') {
        $logDate = date('Y-m-d H:i:s');
    }

    try {
        $stmt = $pdo->prepare("
            INSERT INTO action_logs (project_id, action_taken, notes, logged_by, log_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $actionTaken,
            $notes,
            $who,
            $logDate,
        ]);
    } catch (Exception $e) {
        appLog('error', 'Failed to write project activity log', [
            'project_id' => $projectId,
            'module'     => $module,
            'message'    => $message,
            'error'      => $e->getMessage(),
        ]);
    }
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
 * 27 Barangays of Koronadal City (for project location dropdown)
 */
function getKoronadalBarangays() {
    return [
        'Assumption',
        'Avanceña',
        'Buenaflor',
        'Cacub',
        'Caloocan',
        'Carpenter Hill',
        'Concepcion',
        'Esperanza',
        'General Paulino Santos',
        'Mabini',
        'Magsaysay',
        'Maibo',
        'Malandag',
        'Marbel (Poblacion)',
        'Morales',
        'Namnama',
        'New Pangasinan',
        'Paraiso',
        'Rotonda',
        'San Isidro',
        'San Jose',
        'Saravia',
        'Sta. Cruz',
        'Tamban',
        'Topland',
        'Zone 1 (Poblacion)',
        'Zone 2 (Poblacion)',
        'Zone 3 (Poblacion)',
    ];
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
 * Get dashboard project counts for a fiscal year.
 * @param PDO $pdo
 * @param int $fiscalYear
 * @param string|null $visibility 'Private', 'Public', or null for all
 */
function getProjectCounts($pdo, $fiscalYear, $visibility = null) {
    $counts = [];
    $visClause = $visibility !== null ? ' AND visibility = ?' : '';
    $params = [$fiscalYear];
    if ($visibility !== null) {
        $params[] = $visibility;
    }

    // Total projects (not deleted, not archived)
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?" . $visClause);
    $stmt->execute($params);
    $counts['total'] = $stmt->fetchColumn();

    // By status
    $statuses = STATUS_OPTIONS;
    foreach ($statuses as $status) {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND status = ?" . $visClause);
        $stmt->execute(array_merge([$fiscalYear, $status], $visibility !== null ? [$visibility] : []));
        $key = strtolower(str_replace(' ', '_', $status));
        $counts[$key] = $stmt->fetchColumn();
    }

    // Budget totals
    $stmt = $pdo->prepare("SELECT COALESCE(SUM(budget_allocated), 0) as total_budget, COALESCE(SUM(actual_cost), 0) as total_actual FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ?" . $visClause);
    $stmt->execute($params);
    $budget = $stmt->fetch();
    $counts['total_budget'] = $budget['total_budget'];
    $counts['total_actual'] = $budget['total_actual'];
    $counts['utilization'] = $budget['total_budget'] > 0
        ? round(($budget['total_actual'] / $budget['total_budget']) * 100, 1)
        : 0;

    // By department
    $departments = getDepartments($pdo);
    foreach ($departments as $dept) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(budget_allocated), 0) as budget FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND department = ?" . $visClause);
        $stmt->execute(array_merge([$fiscalYear, $dept], $visibility !== null ? [$visibility] : []));
        $row = $stmt->fetch();
        $key = strtolower($dept);
        $counts[$key . '_count'] = $row['cnt'];
        $counts[$key . '_budget'] = $row['budget'];
    }

    // By subtype
    $allSubtypes = getDepartmentSubtypes($pdo);
    $subtypes = array_merge(...array_values($allSubtypes));
    foreach ($subtypes as $subtype) {
        $stmt = $pdo->prepare("SELECT COUNT(*) as cnt, COALESCE(SUM(budget_allocated), 0) as budget FROM projects WHERE is_deleted = 0 AND is_archived = 0 AND fiscal_year = ? AND project_subtype = ?" . $visClause);
        $stmt->execute(array_merge([$fiscalYear, $subtype], $visibility !== null ? [$visibility] : []));
        $row = $stmt->fetch();
        $key = strtolower(str_replace(' ', '_', $subtype));
        $counts[$key . '_count'] = $row['cnt'];
        $counts[$key . '_budget'] = $row['budget'];
    }

    // Private count (when viewing all or private only)
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

<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/projects/list.php');
}

$action    = $_POST['action'] ?? '';
$projectId = (int)($_POST['project_id'] ?? 0);

if ($projectId <= 0) {
    flashMessage('danger', 'Invalid project.');
    redirect(BASE_URL . '/pages/projects/list.php');
}

$redirectUrl = $_POST['redirect_url'] ?? (BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=closeout');

// Ensure inspections table exists
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_inspections'");
    if (!$chk || $chk->rowCount() === 0) {
        flashMessage('warning', 'Inspections are not set up. Run database/migration_inspections.sql first.');
        redirect($redirectUrl);
    }
} catch (Exception $e) {
    appLog('error', 'Error checking project_inspections table', ['error' => $e->getMessage()]);
    flashMessage('danger', 'Database error.');
    redirect($redirectUrl);
}

/**
 * Normalize a date string from form input.
 */
function normalizeInspectionDate(?string $value): ?string
{
    $value = trim($value ?? '');
    if ($value === '' || strtotime($value) === false) {
        return null;
    }
    return date('Y-m-d', strtotime($value));
}

/**
 * Upsert calendar events for an inspection.
 * Creates/updates one event for date_requested and one for date_inspected.
 */
function syncInspectionCalendarEvents(
    PDO $pdo,
    int $projectId,
    int $inspectionId,
    string $projectTitle,
    string $inspectionType,
    ?string $dateRequested,
    ?string $dateInspected,
    string $status
): void {
    // Helper to upsert a single event by description prefix
    $upsert = function (?string $date, string $prefix) use ($pdo, $projectId, $inspectionId, $projectTitle, $inspectionType, $status): void {
        if ($date === null) {
            // Delete existing event of this kind
            $del = $pdo->prepare("
                DELETE FROM calendar_events
                WHERE project_id = ? AND source_table = 'project_inspections' AND source_id = ? AND description LIKE ?
            ");
            $del->execute([$projectId, $inspectionId, $prefix . '%']);
            return;
        }

        $desc = sprintf(
            '%s %s (%s) – Status: %s',
            $prefix,
            $inspectionType,
            $date,
            $status
        );

        // Try to find existing event
        $stmt = $pdo->prepare("
            SELECT id FROM calendar_events
            WHERE project_id = ? AND source_table = 'project_inspections' AND source_id = ? AND description LIKE ?
            LIMIT 1
        ");
        $stmt->execute([$projectId, $inspectionId, $prefix . '%']);
        $existingId = (int)($stmt->fetchColumn() ?: 0);

        if ($existingId > 0) {
            $upd = $pdo->prepare("
                UPDATE calendar_events
                SET
                    title = ?,
                    description = ?,
                    event_type = 'inspection',
                    event_date = ?,
                    event_end_date = ?,
                    event_time = NULL,
                    is_all_day = 1,
                    is_auto_generated = 1,
                    show_on_main = 1
                WHERE id = ?
            ");
            $upd->execute([
                $projectTitle,
                $desc,
                $date,
                $date,
                $existingId,
            ]);
        } else {
            $ins = $pdo->prepare("
                INSERT INTO calendar_events
                    (project_id, title, description, event_type, event_date, event_end_date,
                     event_time, is_all_day, is_auto_generated, source_table, source_id, color,
                     created_by, show_on_main, daily_cost, actual_accomplishment, remaining_work)
                VALUES (?, ?, ?, 'inspection', ?, ?, NULL, 1, 1, 'project_inspections', ?, NULL,
                        NULL, 1, NULL, NULL, NULL)
            ");
            $ins->execute([
                $projectId,
                $projectTitle,
                $desc,
                $date,
                $date,
                $inspectionId,
            ]);
        }
    };

    // Requested date event
    $upsert($dateRequested, '[Inspection Requested]');

    // Inspected/Result date event
    $upsert($dateInspected, '[Inspection Done]');
}

/**
 * Handle inspection attachments (module_type = 'inspection').
 */
function handleInspectionAttachments(PDO $pdo, int $projectId, int $inspectionId): void
{
    if (empty($_FILES['attachments']['name'][0])) {
        return;
    }

    $projectDir = UPLOAD_DIR . $projectId . '/';
    if (!is_dir($projectDir)) {
        mkdir($projectDir, 0755, true);
    }

    $descBase = trim($_POST['attachment_description'] ?? '');

    $fileCount = count($_FILES['attachments']['name']);
    for ($i = 0; $i < $fileCount; $i++) {
        if ($_FILES['attachments']['error'][$i] !== UPLOAD_ERR_OK) {
            continue;
        }

        $name = $_FILES['attachments']['name'][$i];
        $size = $_FILES['attachments']['size'][$i];
        $type = $_FILES['attachments']['type'][$i];
        $tmp  = $_FILES['attachments']['tmp_name'][$i];

        $err = validateUpload($name, $size);
        if ($err) {
            flashMessage('warning', $err);
            continue;
        }

        $storedName = generateSafeFilename($name, $i);
        $destPath   = $projectDir . $storedName;

        if (!move_uploaded_file($tmp, $destPath)) {
            flashMessage('warning', $name . ': failed to save.');
            continue;
        }

        $stmt = $pdo->prepare("
            INSERT INTO attachments
                (project_id, module_type, module_record_id, file_name, file_original_name, file_type, file_size, description)
            VALUES (?, 'inspection', ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $inspectionId,
            $storedName,
            $name,
            $type,
            $size,
            $descBase !== '' ? $descBase : null,
        ]);
    }
}

// Fetch project title once for calendar event titles
$projStmt = $pdo->prepare("SELECT title FROM projects WHERE id = ? AND is_deleted = 0");
$projStmt->execute([$projectId]);
$projectRow = $projStmt->fetch(PDO::FETCH_ASSOC);
if (!$projectRow) {
    flashMessage('danger', 'Project not found.');
    redirect(BASE_URL . '/pages/projects/list.php');
}
$projectTitle = $projectRow['title'];

switch ($action) {
    case 'create':
        $inspectionType = trim($_POST['inspection_type'] ?? '');
        $taskId         = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        $dateRequested  = normalizeInspectionDate($_POST['date_requested'] ?? null);
        $dateInspected  = normalizeInspectionDate($_POST['date_inspected'] ?? null);
        $status         = strtolower(trim($_POST['status'] ?? 'pending'));
        $remarks        = trim($_POST['remarks'] ?? '');

        if ($inspectionType === '' || $dateRequested === null) {
            flashMessage('danger', 'Inspection type and requested date are required.');
            redirect($redirectUrl);
        }

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = 'pending';
        }

        $stmt = $pdo->prepare("
            INSERT INTO project_inspections
                (project_id, task_id, inspection_type, date_requested, date_inspected, status, remarks)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $taskId,
            $inspectionType,
            $dateRequested,
            $dateInspected,
            $status,
            $remarks !== '' ? $remarks : null,
        ]);

        $inspectionId = (int)$pdo->lastInsertId();

        // Attachments (optional)
        handleInspectionAttachments($pdo, $projectId, $inspectionId);

        // Calendar events
        syncInspectionCalendarEvents(
            $pdo,
            $projectId,
            $inspectionId,
            $projectTitle,
            $inspectionType,
            $dateRequested,
            $dateInspected,
            $status
        );

        // Action log: inspection requested (and possibly result date)
        $logDateRequested = $dateRequested ? ($dateRequested . ' 00:00:00') : null;
        logProjectActivity(
            $pdo,
            $projectId,
            'Inspection',
            'Inspection requested: ' . $inspectionType,
            'System',
            $logDateRequested
        );
        if ($dateInspected !== null) {
            $logDateInspected = $dateInspected . ' 00:00:00';
            logProjectActivity(
                $pdo,
                $projectId,
                'Inspection',
                'Inspection recorded (result date set): ' . $inspectionType,
                'System',
                $logDateInspected
            );
        }

        flashMessage('success', 'Inspection created.');
        redirect($redirectUrl);

    case 'update':
        $inspectionId = (int)($_POST['inspection_id'] ?? 0);
        if ($inspectionId <= 0) {
            flashMessage('danger', 'Invalid inspection.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("SELECT * FROM project_inspections WHERE id = ? AND project_id = ?");
        $stmt->execute([$inspectionId, $projectId]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);
        if (!$existing) {
            flashMessage('danger', 'Inspection not found.');
            redirect($redirectUrl);
        }

        $inspectionType = trim($_POST['inspection_type'] ?? $existing['inspection_type']);
        $taskId         = isset($_POST['task_id']) && $_POST['task_id'] !== '' ? (int)$_POST['task_id'] : null;
        $dateRequested  = normalizeInspectionDate($_POST['date_requested'] ?? $existing['date_requested']);
        $dateInspected  = normalizeInspectionDate($_POST['date_inspected'] ?? $existing['date_inspected']);
        $status         = strtolower(trim($_POST['status'] ?? $existing['status']));
        $remarks        = trim($_POST['remarks'] ?? ($existing['remarks'] ?? ''));

        if ($inspectionType === '' || $dateRequested === null) {
            flashMessage('danger', 'Inspection type and requested date are required.');
            redirect($redirectUrl);
        }

        $allowedStatuses = ['pending', 'approved', 'rejected'];
        if (!in_array($status, $allowedStatuses, true)) {
            $status = $existing['status'];
        }

        $upd = $pdo->prepare("
            UPDATE project_inspections
            SET
                task_id = ?,
                inspection_type = ?,
                date_requested = ?,
                date_inspected = ?,
                status = ?,
                remarks = ?
            WHERE id = ? AND project_id = ?
        ");
        $upd->execute([
            $taskId,
            $inspectionType,
            $dateRequested,
            $dateInspected,
            $status,
            $remarks !== '' ? $remarks : null,
            $inspectionId,
            $projectId,
        ]);

        // Optional new attachments (does not remove existing ones)
        handleInspectionAttachments($pdo, $projectId, $inspectionId);

        // Calendar events
        syncInspectionCalendarEvents(
            $pdo,
            $projectId,
            $inspectionId,
            $projectTitle,
            $inspectionType,
            $dateRequested,
            $dateInspected,
            $status
        );

        // Action log: capture key state transitions
        $oldStatus = strtolower($existing['status'] ?? '');
        if ($oldStatus !== $status) {
            if ($status === 'approved') {
                logProjectActivity(
                    $pdo,
                    $projectId,
                    'Inspection',
                    'Inspection approved: ' . $inspectionType,
                    'System',
                    $dateInspected ? ($dateInspected . ' 00:00:00') : null
                );
            } elseif ($status === 'rejected') {
                logProjectActivity(
                    $pdo,
                    $projectId,
                    'Inspection',
                    'Inspection rejected: ' . $inspectionType,
                    'System',
                    $dateInspected ? ($dateInspected . ' 00:00:00') : null
                );
            } else {
                logProjectActivity(
                    $pdo,
                    $projectId,
                    'Inspection',
                    'Inspection status updated to ' . ucfirst($status) . ': ' . $inspectionType
                );
            }
        } else {
            // Generic update entry
            logProjectActivity(
                $pdo,
                $projectId,
                'Inspection',
                'Inspection updated: ' . $inspectionType
            );
        }

        flashMessage('success', 'Inspection updated.');
        redirect($redirectUrl);

    case 'delete':
        $inspectionId = (int)($_POST['inspection_id'] ?? 0);
        if ($inspectionId <= 0) {
            flashMessage('danger', 'Invalid inspection.');
            redirect($redirectUrl);
        }

        // Delete related attachments
        try {
            $attStmt = $pdo->prepare("
                SELECT id, file_name
                FROM attachments
                WHERE project_id = ? AND module_type = 'inspection' AND module_record_id = ?
            ");
            $attStmt->execute([$projectId, $inspectionId]);
            $attachments = $attStmt->fetchAll(PDO::FETCH_ASSOC);
            foreach ($attachments as $att) {
                $path = UPLOAD_DIR . $projectId . '/' . $att['file_name'];
                if (is_file($path)) {
                    @unlink($path);
                }
                $delAtt = $pdo->prepare("DELETE FROM attachments WHERE id = ?");
                $delAtt->execute([$att['id']]);
            }
        } catch (Exception $e) {
            appLog('error', 'Error deleting inspection attachments', ['inspection_id' => $inspectionId, 'error' => $e->getMessage()]);
        }

        // Delete calendar events linked to this inspection
        try {
            $delEv = $pdo->prepare("
                DELETE FROM calendar_events
                WHERE project_id = ? AND source_table = 'project_inspections' AND source_id = ?
            ");
            $delEv->execute([$projectId, $inspectionId]);
        } catch (Exception $e) {
            appLog('error', 'Error deleting inspection calendar events', ['inspection_id' => $inspectionId, 'error' => $e->getMessage()]);
        }

        // Delete inspection record
        $del = $pdo->prepare("DELETE FROM project_inspections WHERE id = ? AND project_id = ?");
        $del->execute([$inspectionId, $projectId]);

        flashMessage('success', 'Inspection deleted.');
        redirect($redirectUrl);

    default:
        redirect($redirectUrl);
}


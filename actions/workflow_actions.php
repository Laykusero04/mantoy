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

// Allow redirect back to edit page when coming from there
$redirectUrl = !empty($_POST['redirect_to']) ? $_POST['redirect_to'] : BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=workflow';

switch ($action) {

    // ── Update Stage Status & Notes ──────────────────────
    case 'update_stage':
        $stageId = (int)($_POST['stage_id'] ?? 0);
        $status  = $_POST['stage_status'] ?? '';
        $notes   = trim($_POST['notes'] ?? '');

        $validStatuses = ['Pending', 'In Progress', 'Completed'];
        if ($stageId <= 0 || !in_array($status, $validStatuses)) {
            flashMessage('danger', 'Invalid stage data.');
            redirect($redirectUrl);
        }

        $completedAt = ($status === 'Completed') ? date('Y-m-d H:i:s') : null;

        // If marking as not-completed, clear completed_at
        $stmt = $pdo->prepare("
            UPDATE workflow_stages SET
                status = ?, notes = ?, completed_at = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([$status, $notes, $completedAt, $stageId, $projectId]);

        flashMessage('success', 'Workflow stage updated.');
        redirect($redirectUrl);
        break;

    // ── Upload File to Stage ─────────────────────────────
    case 'upload_file':
        $stageId = (int)($_POST['stage_id'] ?? 0);

        if ($stageId <= 0) {
            flashMessage('danger', 'Invalid stage.');
            redirect($redirectUrl);
        }

        // Verify stage exists and belongs to project
        $stmtCheck = $pdo->prepare("SELECT stage_type FROM workflow_stages WHERE id = ? AND project_id = ?");
        $stmtCheck->execute([$stageId, $projectId]);
        $stage = $stmtCheck->fetch();

        if (!$stage) {
            flashMessage('danger', 'Stage not found.');
            redirect($redirectUrl);
        }

        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            flashMessage('danger', 'No file selected or upload error.');
            redirect($redirectUrl);
        }

        $file = $_FILES['file'];

        // Validate size
        if ($file['size'] > MAX_FILE_SIZE) {
            flashMessage('danger', 'File exceeds maximum size of 10MB.');
            redirect($redirectUrl);
        }

        // Validate extension
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!in_array($ext, ALLOWED_EXTENSIONS)) {
            flashMessage('danger', 'File type not allowed. Allowed: ' . implode(', ', ALLOWED_EXTENSIONS));
            redirect($redirectUrl);
        }

        // Create workflow upload directory
        $slug = stageSlug($stage['stage_type']);
        $workflowDir = UPLOAD_DIR . $projectId . '/workflow/' . $slug . '/';
        if (!is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        // Generate unique filename
        $storedName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destPath   = $workflowDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            flashMessage('danger', 'Failed to save file.');
            redirect($redirectUrl);
        }

        // Save metadata
        $stmt = $pdo->prepare("
            INSERT INTO workflow_attachments (stage_id, project_id, file_name, file_original_name, file_type, file_size, description)
            VALUES (?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $stageId,
            $projectId,
            $storedName,
            $file['name'],
            $file['type'],
            $file['size'],
            trim($_POST['description'] ?? ''),
        ]);

        flashMessage('success', 'File uploaded to workflow stage.');
        redirect($redirectUrl);
        break;

    // ── Delete Workflow File ─────────────────────────────
    case 'delete_file':
        $fileId = (int)($_POST['file_id'] ?? 0);

        if ($fileId <= 0) {
            flashMessage('danger', 'Invalid file.');
            redirect($redirectUrl);
        }

        // Get file info with stage type
        $stmt = $pdo->prepare("
            SELECT wa.file_name, ws.stage_type
            FROM workflow_attachments wa
            JOIN workflow_stages ws ON wa.stage_id = ws.id
            WHERE wa.id = ? AND wa.project_id = ?
        ");
        $stmt->execute([$fileId, $projectId]);
        $att = $stmt->fetch();

        if ($att) {
            // Delete physical file
            $slug = stageSlug($att['stage_type']);
            $filePath = UPLOAD_DIR . $projectId . '/workflow/' . $slug . '/' . $att['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete DB record
            $stmt = $pdo->prepare("DELETE FROM workflow_attachments WHERE id = ? AND project_id = ?");
            $stmt->execute([$fileId, $projectId]);
            flashMessage('success', 'File deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

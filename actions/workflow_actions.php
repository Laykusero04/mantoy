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

        // Collect files from either multi (files[]) or single (file) input
        $uploadedFiles = [];
        if (!empty($_FILES['files']['name'][0])) {
            $fileCount = count($_FILES['files']['name']);
            for ($i = 0; $i < $fileCount; $i++) {
                if ($_FILES['files']['error'][$i] === UPLOAD_ERR_OK) {
                    $uploadedFiles[] = [
                        'name'     => $_FILES['files']['name'][$i],
                        'tmp_name' => $_FILES['files']['tmp_name'][$i],
                        'size'     => $_FILES['files']['size'][$i],
                        'type'     => $_FILES['files']['type'][$i],
                    ];
                }
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFiles[] = $_FILES['file'];
        }

        if (empty($uploadedFiles)) {
            flashMessage('danger', 'No file selected or upload error.');
            redirect($redirectUrl);
        }

        // Create workflow upload directory
        $slug = stageSlug($stage['stage_type']);
        $workflowDir = UPLOAD_DIR . $projectId . '/workflow/' . $slug . '/';
        if (!is_dir($workflowDir)) {
            mkdir($workflowDir, 0755, true);
        }

        $successCount = 0;
        $errors = [];
        $description = trim($_POST['description'] ?? '');

        foreach ($uploadedFiles as $file) {
            // Validate size + extension
            $uploadError = validateUpload($file['name'], $file['size']);
            if ($uploadError) {
                $errors[] = $uploadError;
                continue;
            }

            // Generate unique filename
            $storedName = generateSafeFilename($file['name']);
            $destPath   = $workflowDir . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = $file['name'] . ': failed to save.';
                continue;
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
                $description,
            ]);
            $successCount++;
        }

        if ($successCount > 0 && empty($errors)) {
            flashMessage('success', $successCount . ' file(s) uploaded to workflow stage.');
        } elseif ($successCount > 0) {
            flashMessage('warning', $successCount . ' file(s) uploaded. Errors: ' . implode(' ', $errors));
        } else {
            flashMessage('danger', 'Upload failed. ' . implode(' ', $errors));
        }
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

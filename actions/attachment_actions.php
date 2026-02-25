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

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=files';

switch ($action) {

    case 'upload':
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

        // Create project upload directory
        $projectDir = UPLOAD_DIR . $projectId . '/';
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        // Generate unique filename
        $storedName = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $file['name']);
        $destPath   = $projectDir . $storedName;

        if (!move_uploaded_file($file['tmp_name'], $destPath)) {
            flashMessage('danger', 'Failed to save file.');
            redirect($redirectUrl);
        }

        // Save metadata
        $stmt = $pdo->prepare("
            INSERT INTO attachments (project_id, file_name, file_original_name, file_type, file_size, description)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $storedName,
            $file['name'],
            $file['type'],
            $file['size'],
            trim($_POST['description'] ?? ''),
        ]);

        flashMessage('success', 'File uploaded.');
        redirect($redirectUrl);
        break;

    case 'delete':
        $attachmentId = (int)($_POST['attachment_id'] ?? 0);
        if ($attachmentId <= 0) {
            flashMessage('danger', 'Invalid attachment.');
            redirect($redirectUrl);
        }

        // Get file info
        $stmt = $pdo->prepare("SELECT file_name FROM attachments WHERE id = ? AND project_id = ?");
        $stmt->execute([$attachmentId, $projectId]);
        $att = $stmt->fetch();

        if ($att) {
            // Delete physical file
            $filePath = UPLOAD_DIR . $projectId . '/' . $att['file_name'];
            if (file_exists($filePath)) {
                unlink($filePath);
            }

            // Delete DB record
            $stmt = $pdo->prepare("DELETE FROM attachments WHERE id = ? AND project_id = ?");
            $stmt->execute([$attachmentId, $projectId]);
            flashMessage('success', 'Attachment deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

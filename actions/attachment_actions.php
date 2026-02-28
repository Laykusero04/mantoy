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

$redirectUrl = $_POST['redirect_url'] ?? BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=files';

switch ($action) {

    case 'upload':
        // Collect files from either multi (files[]) or single (file) input
        $uploadedFiles = [];
        $descriptions  = [];

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
                    $descriptions[] = trim($_POST['descriptions'][$i] ?? $_POST['description'] ?? '');
                }
            }
        } elseif (isset($_FILES['file']) && $_FILES['file']['error'] === UPLOAD_ERR_OK) {
            $uploadedFiles[] = [
                'name'     => $_FILES['file']['name'],
                'tmp_name' => $_FILES['file']['tmp_name'],
                'size'     => $_FILES['file']['size'],
                'type'     => $_FILES['file']['type'],
            ];
            $descriptions[] = trim($_POST['description'] ?? '');
        }

        if (empty($uploadedFiles)) {
            flashMessage('danger', 'No file selected or upload error.');
            redirect($redirectUrl);
        }

        $moduleType     = $_POST['module_type'] ?? 'general';
        $moduleRecordId = !empty($_POST['module_record_id']) ? (int)$_POST['module_record_id'] : null;

        // Create project upload directory
        $projectDir = UPLOAD_DIR . $projectId . '/';
        if (!is_dir($projectDir)) {
            mkdir($projectDir, 0755, true);
        }

        $successCount = 0;
        $errors = [];

        foreach ($uploadedFiles as $idx => $file) {
            // Validate size + extension
            $uploadError = validateUpload($file['name'], $file['size']);
            if ($uploadError) {
                $errors[] = $uploadError;
                continue;
            }

            // Generate unique filename
            $storedName = generateSafeFilename($file['name']);
            $destPath   = $projectDir . $storedName;

            if (!move_uploaded_file($file['tmp_name'], $destPath)) {
                $errors[] = $file['name'] . ': failed to save.';
                continue;
            }

            // Save metadata (requires migration_v2.sql applied)
            $stmt = $pdo->prepare("
                INSERT INTO attachments (project_id, module_type, module_record_id, file_name, file_original_name, file_type, file_size, description)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([
                $projectId,
                $moduleType,
                $moduleRecordId,
                $storedName,
                $file['name'],
                $file['type'],
                $file['size'],
                $descriptions[$idx] ?? '',
            ]);

            $successCount++;
        }

        if ($successCount > 0 && empty($errors)) {
            flashMessage('success', $successCount . ' file(s) uploaded.');
        } elseif ($successCount > 0) {
            flashMessage('warning', $successCount . ' file(s) uploaded. Errors: ' . implode(' ', $errors));
        } else {
            flashMessage('danger', 'Upload failed. ' . implode(' ', $errors));
        }
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

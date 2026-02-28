<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/calendar/index.php');
}

$action = $_POST['action'] ?? '';
$redirectUrl = $_POST['redirect_url'] ?? (BASE_URL . '/pages/calendar/index.php');

function normalizeDate($value) {
    $value = trim($value ?? '');
    return $value !== '' ? $value : null;
}

function normalizeTime($value) {
    $value = trim($value ?? '');
    return $value !== '' ? $value : null;
}

switch ($action) {
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $eventDate = normalizeDate($_POST['event_date'] ?? '');
        $eventEndDate = normalizeDate($_POST['event_end_date'] ?? '');
        $eventTime = normalizeTime($_POST['event_time'] ?? '');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $eventType = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $showOnMain = $projectId ? (isset($_POST['show_on_main']) ? 1 : 0) : 1; // global events always on main

        if ($title === '' || $eventDate === null) {
            flashMessage('danger', 'Title and date are required.');
            redirect($redirectUrl);
        }

        if ($eventEndDate === null) {
            $eventEndDate = $eventDate;
        }

        $stmt = $pdo->prepare("
            INSERT INTO calendar_events
                (project_id, title, description, event_type, event_date, event_end_date,
                 event_time, is_all_day, is_auto_generated, source_table, source_id, color,
                 created_by, show_on_main)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, 0, NULL, NULL, NULL, NULL, ?)
        ");

        $stmt->execute([
            $projectId,
            $title,
            $description,
            $eventType,
            $eventDate,
            $eventEndDate,
            $eventTime,
            $isAllDay,
            $showOnMain,
        ]);

        $newEventId = (int)$pdo->lastInsertId();

        // Optional multi-file attachments linked to this event (project-specific only)
        if ($projectId && !empty($_FILES['attachments']['name'][0])) {
            $projectDir = UPLOAD_DIR . $projectId . '/';
            if (!is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            $desc = trim($_POST['attachment_description'] ?? '');
            $fCount = count($_FILES['attachments']['name']);
            for ($fi = 0; $fi < $fCount; $fi++) {
                if ($_FILES['attachments']['error'][$fi] !== UPLOAD_ERR_OK) continue;
                if (validateUpload($_FILES['attachments']['name'][$fi], $_FILES['attachments']['size'][$fi])) continue;

                $storedName = generateSafeFilename($_FILES['attachments']['name'][$fi], $fi);
                $destPath = $projectDir . $storedName;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$fi], $destPath)) {
                    $attStmt = $pdo->prepare("
                        INSERT INTO attachments
                            (project_id, file_name, file_original_name, file_type, file_size, description)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $attStmt->execute([
                        $projectId, $storedName, $_FILES['attachments']['name'][$fi],
                        $_FILES['attachments']['type'][$fi], $_FILES['attachments']['size'][$fi], $desc,
                    ]);
                }
            }
        }

        flashMessage('success', 'Calendar event created.');
        redirect($redirectUrl);

    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $projectId = !empty($_POST['project_id']) ? (int)$_POST['project_id'] : null;
        $eventDate = normalizeDate($_POST['event_date'] ?? '');
        $eventEndDate = normalizeDate($_POST['event_end_date'] ?? '');
        $eventTime = normalizeTime($_POST['event_time'] ?? '');
        $isAllDay = isset($_POST['is_all_day']) ? 1 : 0;
        $eventType = $_POST['event_type'] ?? 'other';
        $description = trim($_POST['description'] ?? '');
        $showOnMain = $projectId ? (isset($_POST['show_on_main']) ? 1 : 0) : 1;

        if ($id <= 0 || $title === '' || $eventDate === null) {
            flashMessage('danger', 'Invalid event data.');
            redirect($redirectUrl);
        }

        if ($eventEndDate === null) {
            $eventEndDate = $eventDate;
        }

        $stmt = $pdo->prepare("
            UPDATE calendar_events SET
                project_id = ?,
                title = ?,
                description = ?,
                event_type = ?,
                event_date = ?,
                event_end_date = ?,
                event_time = ?,
                is_all_day = ?,
                show_on_main = ?
            WHERE id = ?
        ");

        $stmt->execute([
            $projectId,
            $title,
            $description,
            $eventType,
            $eventDate,
            $eventEndDate,
            $eventTime,
            $isAllDay,
            $showOnMain,
            $id,
        ]);

        // Optional multi-file attachments when updating (does not remove existing ones)
        if ($projectId && !empty($_FILES['attachments']['name'][0])) {
            $projectDir = UPLOAD_DIR . $projectId . '/';
            if (!is_dir($projectDir)) {
                mkdir($projectDir, 0755, true);
            }
            $desc = trim($_POST['attachment_description'] ?? '');
            $fCount = count($_FILES['attachments']['name']);
            for ($fi = 0; $fi < $fCount; $fi++) {
                if ($_FILES['attachments']['error'][$fi] !== UPLOAD_ERR_OK) continue;
                if (validateUpload($_FILES['attachments']['name'][$fi], $_FILES['attachments']['size'][$fi])) continue;

                $storedName = generateSafeFilename($_FILES['attachments']['name'][$fi], $fi);
                $destPath = $projectDir . $storedName;
                if (move_uploaded_file($_FILES['attachments']['tmp_name'][$fi], $destPath)) {
                    $attStmt = $pdo->prepare("
                        INSERT INTO attachments
                            (project_id, file_name, file_original_name, file_type, file_size, description)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $attStmt->execute([
                        $projectId, $storedName, $_FILES['attachments']['name'][$fi],
                        $_FILES['attachments']['type'][$fi], $_FILES['attachments']['size'][$fi], $desc,
                    ]);
                }
            }
        }

        flashMessage('success', 'Calendar event updated.');
        redirect($redirectUrl);

    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("DELETE FROM calendar_events WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Calendar event deleted.');
        }
        redirect($redirectUrl);

    default:
        redirect($redirectUrl);
}

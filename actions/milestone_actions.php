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

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=milestones';

switch ($action) {

    case 'add':
        $title = trim($_POST['title'] ?? '');
        if ($title === '') {
            flashMessage('danger', 'Milestone title is required.');
            redirect($redirectUrl);
        }

        // Get next sort_order
        $stmt = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) + 1 FROM milestones WHERE project_id = ?");
        $stmt->execute([$projectId]);
        $nextOrder = $stmt->fetchColumn();

        $stmt = $pdo->prepare("
            INSERT INTO milestones (project_id, title, target_date, notes, sort_order)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $title,
            !empty($_POST['target_date']) ? $_POST['target_date'] : null,
            trim($_POST['notes'] ?? ''),
            $nextOrder,
        ]);
        flashMessage('success', 'Milestone added.');
        redirect($redirectUrl);
        break;

    case 'update':
        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        if ($milestoneId <= 0 || $title === '') {
            flashMessage('danger', 'Invalid input.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("
            UPDATE milestones
            SET title = ?, target_date = ?, notes = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([
            $title,
            !empty($_POST['target_date']) ? $_POST['target_date'] : null,
            trim($_POST['notes'] ?? ''),
            $milestoneId,
            $projectId,
        ]);
        flashMessage('success', 'Milestone updated.');
        redirect($redirectUrl);
        break;

    case 'toggle':
        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        if ($milestoneId > 0) {
            $stmt = $pdo->prepare("SELECT status FROM milestones WHERE id = ? AND project_id = ?");
            $stmt->execute([$milestoneId, $projectId]);
            $current = $stmt->fetchColumn();

            if ($current === 'Pending') {
                $pdo->prepare("UPDATE milestones SET status = 'Done', completed_date = CURDATE() WHERE id = ?")
                    ->execute([$milestoneId]);
            } else {
                $pdo->prepare("UPDATE milestones SET status = 'Pending', completed_date = NULL WHERE id = ?")
                    ->execute([$milestoneId]);
            }
            flashMessage('success', 'Milestone status updated.');
        }
        redirect($redirectUrl);
        break;

    case 'delete':
        $milestoneId = (int)($_POST['milestone_id'] ?? 0);
        if ($milestoneId > 0) {
            $stmt = $pdo->prepare("DELETE FROM milestones WHERE id = ? AND project_id = ?");
            $stmt->execute([$milestoneId, $projectId]);
            flashMessage('success', 'Milestone deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

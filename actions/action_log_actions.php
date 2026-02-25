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

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=log';

switch ($action) {

    case 'add':
        $actionTaken = trim($_POST['action_taken'] ?? '');
        $logDate     = $_POST['log_date'] ?? '';

        if ($actionTaken === '' || $logDate === '') {
            flashMessage('danger', 'Action taken and date are required.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("
            INSERT INTO action_logs (project_id, action_taken, notes, logged_by, log_date)
            VALUES (?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $actionTaken,
            trim($_POST['notes'] ?? ''),
            trim($_POST['logged_by'] ?? ''),
            $logDate,
        ]);
        flashMessage('success', 'Log entry added.');
        redirect($redirectUrl);
        break;

    case 'delete':
        $logId = (int)($_POST['log_id'] ?? 0);
        if ($logId > 0) {
            $stmt = $pdo->prepare("DELETE FROM action_logs WHERE id = ? AND project_id = ?");
            $stmt->execute([$logId, $projectId]);
            flashMessage('success', 'Log entry deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

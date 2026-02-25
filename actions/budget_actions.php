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

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=budget';

switch ($action) {

    case 'add':
        $itemName = trim($_POST['item_name'] ?? '');
        if ($itemName === '') {
            flashMessage('danger', 'Item name is required.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("
            INSERT INTO budget_items (project_id, item_name, budget_category, quantity, unit, unit_cost)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $projectId,
            $itemName,
            $_POST['budget_category'] ?? 'MOOE',
            max(0, (float)($_POST['quantity'] ?? 1)),
            trim($_POST['unit'] ?? 'pcs'),
            max(0, (float)($_POST['unit_cost'] ?? 0)),
        ]);
        flashMessage('success', 'Budget item added.');
        redirect($redirectUrl);
        break;

    case 'update':
        $itemId   = (int)($_POST['item_id'] ?? 0);
        $itemName = trim($_POST['item_name'] ?? '');
        if ($itemId <= 0 || $itemName === '') {
            flashMessage('danger', 'Invalid input.');
            redirect($redirectUrl);
        }

        $stmt = $pdo->prepare("
            UPDATE budget_items
            SET item_name = ?, budget_category = ?, quantity = ?, unit = ?, unit_cost = ?
            WHERE id = ? AND project_id = ?
        ");
        $stmt->execute([
            $itemName,
            $_POST['budget_category'] ?? 'MOOE',
            max(0, (float)($_POST['quantity'] ?? 1)),
            trim($_POST['unit'] ?? 'pcs'),
            max(0, (float)($_POST['unit_cost'] ?? 0)),
            $itemId,
            $projectId,
        ]);
        flashMessage('success', 'Budget item updated.');
        redirect($redirectUrl);
        break;

    case 'delete':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $pdo->prepare("DELETE FROM budget_items WHERE id = ? AND project_id = ?");
            $stmt->execute([$itemId, $projectId]);
            flashMessage('success', 'Budget item deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

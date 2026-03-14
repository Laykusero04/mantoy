<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/projects/list.php');
}

$action    = $_POST['action'] ?? '';
$projectId = (int)($_POST['project_id'] ?? 0);
$category  = $_POST['category'] ?? '';
$allowedCategories = ['manpower', 'equipment', 'materials'];

if ($projectId <= 0) {
    flashMessage('danger', 'Invalid project.');
    redirect(BASE_URL . '/pages/projects/list.php');
}
if (!in_array($category, $allowedCategories, true)) {
    $category = 'manpower';
}

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=dupa';

function resolveBudgetItemId($pdo, $projectId, $raw) {
    if ($raw === '' || $raw === null) return null;
    $bid = (int)$raw;
    if ($bid <= 0) return null;
    $stmt = $pdo->prepare("SELECT id FROM budget_items WHERE id = ? AND project_id = ?");
    $stmt->execute([$bid, $projectId]);
    return $stmt->fetchColumn() ? $bid : null;
}

try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_dupa_items'");
    if (!$chk || $chk->rowCount() === 0) {
        flashMessage('warning', 'DUPA tables not found. Run database/migration_dupa.sql first.');
        redirect($redirectUrl);
    }
} catch (Exception $e) {
    flashMessage('danger', 'Database error.');
    redirect($redirectUrl);
}

switch ($action) {

    case 'add':
        $description = trim($_POST['description'] ?? '');
        if ($description === '') {
            flashMessage('danger', 'Description is required.');
            redirect($redirectUrl);
        }
        $quantity = max(0, (float)($_POST['quantity'] ?? 1));
        $unit = trim((string)($_POST['unit'] ?? ''));
        if ($unit === '' && $category === 'manpower') $unit = 'hrs';
        if ($unit === '' && $category === 'equipment') $unit = 'days';
        if ($unit === '' && $category === 'materials') $unit = 'pcs';
        $rate = max(0, (float)($_POST['rate'] ?? 0));
        $budgetItemId = resolveBudgetItemId($pdo, $projectId, $_POST['budget_item_id'] ?? '');

        if ($category === 'materials' && $budgetItemId === null) {
            flashMessage('danger', 'Materials must be assigned to a Scope of Work.');
            redirect($redirectUrl);
        }

        $cols = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
        $hasBudgetItemId = $cols && $cols->rowCount() > 0;

        $maxOrder = $pdo->prepare("SELECT COALESCE(MAX(sort_order), 0) FROM project_dupa_items WHERE project_id = ? AND category = ?" . ($hasBudgetItemId ? " AND COALESCE(budget_item_id, 0) = COALESCE(?, 0)" : ""));
        if ($hasBudgetItemId) {
            $maxOrder->execute([$projectId, $category, $budgetItemId]);
        } else {
            $maxOrder->execute([$projectId, $category]);
        }
        $sortOrder = (int)$maxOrder->fetchColumn() + 1;

        if ($hasBudgetItemId) {
            $stmt = $pdo->prepare("
                INSERT INTO project_dupa_items (project_id, budget_item_id, category, description, quantity, unit, rate, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$projectId, $budgetItemId, $category, $description, $quantity, $unit, $rate, $sortOrder]);
        } else {
            $stmt = $pdo->prepare("
                INSERT INTO project_dupa_items (project_id, category, description, quantity, unit, rate, sort_order)
                VALUES (?, ?, ?, ?, ?, ?, ?)
            ");
            $stmt->execute([$projectId, $category, $description, $quantity, $unit, $rate, $sortOrder]);
        }
        flashMessage('success', 'Item added.');
        redirect($redirectUrl);
        break;

    case 'update':
        $itemId = (int)($_POST['item_id'] ?? 0);
        $description = trim($_POST['description'] ?? '');
        if ($itemId <= 0 || $description === '') {
            flashMessage('danger', 'Invalid input.');
            redirect($redirectUrl);
        }
        $quantity = max(0, (float)($_POST['quantity'] ?? 1));
        $unit = trim((string)($_POST['unit'] ?? ''));
        if ($unit === '') $unit = $category === 'manpower' ? 'hrs' : ($category === 'equipment' ? 'days' : 'pcs');
        $rate = max(0, (float)($_POST['rate'] ?? 0));
        $budgetItemId = resolveBudgetItemId($pdo, $projectId, $_POST['budget_item_id'] ?? '');

        if ($category === 'materials' && $budgetItemId === null) {
            flashMessage('danger', 'Materials must be assigned to a Scope of Work.');
            redirect($redirectUrl);
        }

        $cols = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
        $hasBudgetItemId = $cols && $cols->rowCount() > 0;

        if ($hasBudgetItemId) {
            $stmt = $pdo->prepare("
                UPDATE project_dupa_items
                SET description = ?, quantity = ?, unit = ?, rate = ?, budget_item_id = ?
                WHERE id = ? AND project_id = ? AND category = ?
            ");
            $stmt->execute([$description, $quantity, $unit, $rate, $budgetItemId, $itemId, $projectId, $category]);
        } else {
            $stmt = $pdo->prepare("
                UPDATE project_dupa_items
                SET description = ?, quantity = ?, unit = ?, rate = ?
                WHERE id = ? AND project_id = ? AND category = ?
            ");
            $stmt->execute([$description, $quantity, $unit, $rate, $itemId, $projectId, $category]);
        }
        flashMessage('success', 'Item updated.');
        redirect($redirectUrl);
        break;

    case 'delete':
        $itemId = (int)($_POST['item_id'] ?? 0);
        if ($itemId > 0) {
            $stmt = $pdo->prepare("DELETE FROM project_dupa_items WHERE id = ? AND project_id = ?");
            $stmt->execute([$itemId, $projectId]);
            flashMessage('success', 'Item deleted.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

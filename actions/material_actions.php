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

$redirectUrl = BASE_URL . '/pages/projects/view.php?id=' . $projectId . '&tab=monitoring';

// Check withdrawals table exists
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_material_withdrawals'");
    if (!$chk || $chk->rowCount() === 0) {
        flashMessage('warning', 'Materials monitoring not set up. Run database/migration_material_withdrawals.sql first.');
        redirect($redirectUrl);
    }
} catch (Exception $e) {
    flashMessage('danger', 'Database error.');
    redirect($redirectUrl);
}

switch ($action) {

    case 'save_withdrawal':
        $dupaItemId     = (int)($_POST['dupa_item_id'] ?? 0);
        $withdrawalDate = trim($_POST['withdrawal_date'] ?? '');
        $quantity       = isset($_POST['quantity']) ? max(0, (float)$_POST['quantity']) : 0;
        $quantityRequested = isset($_POST['quantity_requested']) && $_POST['quantity_requested'] !== '' ? max(0, (float)$_POST['quantity_requested']) : null;
        $unitCost       = isset($_POST['unit_cost']) && $_POST['unit_cost'] !== '' ? (float)$_POST['unit_cost'] : null;
        $remarks        = trim($_POST['remarks'] ?? '');

        if ($dupaItemId <= 0 || $withdrawalDate === '' || strtotime($withdrawalDate) === false) {
            flashMessage('danger', 'Invalid material or date.');
            redirect($redirectUrl);
        }
        $withdrawalDate = date('Y-m-d', strtotime($withdrawalDate));

        // Ensure dupa_item belongs to project and is materials
        $stmt = $pdo->prepare("SELECT id FROM project_dupa_items WHERE id = ? AND project_id = ? AND category = 'materials'");
        $stmt->execute([$dupaItemId, $projectId]);
        if (!$stmt->fetch()) {
            flashMessage('danger', 'Invalid material for this project.');
            redirect($redirectUrl);
        }

        $hasQuantityRequested = false;
        try {
            $colCheck = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
            $hasQuantityRequested = $colCheck && $colCheck->rowCount() > 0;
        } catch (Exception $e) {}

        $stmt = $pdo->prepare("
            SELECT id FROM project_material_withdrawals
            WHERE project_id = ? AND dupa_item_id = ? AND withdrawal_date = ?
        ");
        $stmt->execute([$projectId, $dupaItemId, $withdrawalDate]);
        $existing = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($quantity <= 0 && $quantityRequested <= 0 && $existing) {
            $del = $pdo->prepare("DELETE FROM project_material_withdrawals WHERE id = ? AND project_id = ?");
            $del->execute([$existing['id'], $projectId]);
            flashMessage('success', 'Withdrawal cleared.');
        } elseif ($quantity > 0 || ($quantityRequested !== null && $quantityRequested > 0)) {
            $deliveredQty = $quantity > 0 ? $quantity : 0;
            if ($existing) {
                if ($hasQuantityRequested) {
                    $stmt = $pdo->prepare("
                        UPDATE project_material_withdrawals
                        SET quantity = ?, quantity_requested = ?, unit_cost = ?, remarks = ?
                        WHERE id = ? AND project_id = ?
                    ");
                    $stmt->execute([$deliveredQty, $quantityRequested, $unitCost, $remarks !== '' ? $remarks : null, $existing['id'], $projectId]);
                } else {
                    $stmt = $pdo->prepare("
                        UPDATE project_material_withdrawals
                        SET quantity = ?, unit_cost = ?, remarks = ?
                        WHERE id = ? AND project_id = ?
                    ");
                    $stmt->execute([$deliveredQty, $unitCost, $remarks !== '' ? $remarks : null, $existing['id'], $projectId]);
                }
            } else {
                if ($hasQuantityRequested) {
                    $stmt = $pdo->prepare("
                        INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity_requested, quantity, unit_cost, remarks)
                        VALUES (?, ?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$projectId, $dupaItemId, $withdrawalDate, $quantityRequested, $deliveredQty, $unitCost, $remarks !== '' ? $remarks : null]);
                } else {
                    $stmt = $pdo->prepare("
                        INSERT INTO project_material_withdrawals (project_id, dupa_item_id, withdrawal_date, quantity, unit_cost, remarks)
                        VALUES (?, ?, ?, ?, ?, ?)
                    ");
                    $stmt->execute([$projectId, $dupaItemId, $withdrawalDate, $deliveredQty, $unitCost, $remarks !== '' ? $remarks : null]);
                }
            }
            flashMessage('success', 'Withdrawal saved.');
        }

        // Action log: Material delivered / withdrawal change
        try {
            $matStmt = $pdo->prepare("SELECT description, unit FROM project_dupa_items WHERE id = ? AND project_id = ?");
            $matStmt->execute([$dupaItemId, $projectId]);
            $mat = $matStmt->fetch(PDO::FETCH_ASSOC);
            if ($mat) {
                $desc = $mat['description'] ?? 'Material';
                $unit = $mat['unit'] ?? 'unit';
                $baseMsg = $quantity > 0
                    ? sprintf('Material delivered: %s (%.2f %s)', $desc, $quantity, $unit)
                    : sprintf('Material delivery cleared: %s', $desc);
                $logDate = $withdrawalDate . ' 00:00:00';
                logProjectActivity($pdo, $projectId, 'Materials', $baseMsg, 'System', $logDate);
            }
        } catch (Exception $e) {
            appLog('error', 'Failed to log material withdrawal', [
                'project_id' => $projectId,
                'dupa_item_id' => $dupaItemId,
                'error' => $e->getMessage(),
            ]);
        }

        redirect($redirectUrl);
        break;

    case 'delete_withdrawal':
        $withdrawalId = (int)($_POST['withdrawal_id'] ?? 0);
        if ($withdrawalId <= 0) {
            flashMessage('danger', 'Invalid withdrawal.');
            redirect($redirectUrl);
        }
        $stmt = $pdo->prepare("DELETE FROM project_material_withdrawals WHERE id = ? AND project_id = ?");
        $stmt->execute([$withdrawalId, $projectId]);
        flashMessage('success', 'Withdrawal removed.');
        redirect($redirectUrl);
        break;

    case 'update_log_header':
        $supplier   = trim($_POST['material_supplier'] ?? '');
        $poNumber   = trim($_POST['material_po_number'] ?? '');
        $termDays    = isset($_POST['material_term_days']) && $_POST['material_term_days'] !== '' ? (int)$_POST['material_term_days'] : null;
        $allotedAmt  = isset($_POST['material_alloted_amount']) && $_POST['material_alloted_amount'] !== '' ? max(0, (float)$_POST['material_alloted_amount']) : null;

        $cols = $pdo->query("SHOW COLUMNS FROM projects LIKE 'material_supplier'");
        if ($cols && $cols->rowCount() > 0) {
            $stmt = $pdo->prepare("
                UPDATE projects SET
                    material_supplier = ?,
                    material_po_number = ?,
                    material_term_days = ?,
                    material_alloted_amount = ?
                WHERE id = ? AND is_deleted = 0
            ");
            $stmt->execute([$supplier !== '' ? $supplier : null, $poNumber !== '' ? $poNumber : null, $termDays, $allotedAmt, $projectId]);
            flashMessage('success', 'Log header updated.');
        }
        redirect($redirectUrl);
        break;

    case 'update_material_remarks':
        $dupaItemId = (int)($_POST['dupa_item_id'] ?? 0);
        $remarks   = trim($_POST['remarks'] ?? '');

        if ($dupaItemId <= 0) {
            flashMessage('danger', 'Invalid material.');
            redirect($redirectUrl);
        }
        $cols = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'remarks'");
        if ($cols && $cols->rowCount() > 0) {
            $stmt = $pdo->prepare("
                UPDATE project_dupa_items
                SET remarks = ?
                WHERE id = ? AND project_id = ? AND category = 'materials'
            ");
            $stmt->execute([$remarks !== '' ? $remarks : null, $dupaItemId, $projectId]);
            flashMessage('success', 'Remarks updated.');
        }
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

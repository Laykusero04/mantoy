<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/projects/list.php');
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Create ──────────────────────────────────────────────
    case 'create':
        $title = trim($_POST['title'] ?? '');
        $visibility = $_POST['visibility'] ?? 'Public';
        $department = trim($_POST['department'] ?? '');
        $projectSubtype = trim($_POST['project_subtype'] ?? '');
        $fiscalYear = $_POST['fiscal_year'] ?? date('Y');

        if ($title === '') {
            flashMessage('danger', 'Title is required.');
            redirect(BASE_URL . '/pages/projects/create.php');
        }

        // Validate classification
        if ($visibility === 'Private') {
            $department = null;
            $projectSubtype = null;
        } else {
            if ($department === '') {
                flashMessage('danger', 'Department is required for public projects.');
                redirect(BASE_URL . '/pages/projects/create.php');
            }
            $validSubtypes = getDepartmentSubtypes();
            if (!isset($validSubtypes[$department]) || !in_array($projectSubtype, $validSubtypes[$department])) {
                flashMessage('danger', 'Invalid sub-type for the selected department.');
                redirect(BASE_URL . '/pages/projects/create.php');
            }
        }

        // Map to legacy project_type for backward compatibility
        $projectTypeMap = [
            'BDP' => 'Root Project',
            'SEF' => 'SEF Project',
            'Special Projects' => 'Special Project',
            'Planning and Programming' => 'Root Project',
            'Construction' => 'Root Project',
        ];
        $projectType = $projectTypeMap[$projectSubtype] ?? 'Root Project';

        $stmt = $pdo->prepare("
            INSERT INTO projects
                (title, project_type, visibility, department, project_subtype,
                 category_id, description, fiscal_year)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
        ");
        $stmt->execute([
            $title,
            $projectType,
            $visibility,
            $department,
            $projectSubtype,
            !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            trim($_POST['description'] ?? ''),
            $fiscalYear,
        ]);

        $newId = $pdo->lastInsertId();
        initWorkflowStages($pdo, $newId);

        // Handle file uploads → attach to "Incoming Communication" stage
        if (!empty($_FILES['files']['name'][0])) {
            // Get the Incoming Communication stage
            $stageStmt = $pdo->prepare("SELECT id FROM workflow_stages WHERE project_id = ? AND stage_type = 'Incoming Communication'");
            $stageStmt->execute([$newId]);
            $incomingStage = $stageStmt->fetch();

            if ($incomingStage) {
                $stageId = $incomingStage['id'];
                $slug = stageSlug('Incoming Communication');
                $workflowDir = UPLOAD_DIR . $newId . '/workflow/' . $slug . '/';

                if (!is_dir($workflowDir)) {
                    mkdir($workflowDir, 0755, true);
                }

                $descriptions = $_POST['file_descriptions'] ?? [];
                $uploadCount = 0;

                for ($i = 0; $i < count($_FILES['files']['name']); $i++) {
                    if ($_FILES['files']['error'][$i] !== UPLOAD_ERR_OK) continue;
                    if ($_FILES['files']['size'][$i] > MAX_FILE_SIZE) continue;

                    $ext = strtolower(pathinfo($_FILES['files']['name'][$i], PATHINFO_EXTENSION));
                    if (!in_array($ext, ALLOWED_EXTENSIONS)) continue;

                    $storedName = time() . '_' . $i . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '_', $_FILES['files']['name'][$i]);
                    $destPath = $workflowDir . $storedName;

                    if (move_uploaded_file($_FILES['files']['tmp_name'][$i], $destPath)) {
                        $fileStmt = $pdo->prepare("
                            INSERT INTO workflow_attachments (stage_id, project_id, file_name, file_original_name, file_type, file_size, description)
                            VALUES (?, ?, ?, ?, ?, ?, ?)
                        ");
                        $fileStmt->execute([
                            $stageId,
                            $newId,
                            $storedName,
                            $_FILES['files']['name'][$i],
                            $_FILES['files']['type'][$i],
                            $_FILES['files']['size'][$i],
                            trim($descriptions[$i] ?? ''),
                        ]);
                        $uploadCount++;
                    }
                }

                // Mark incoming stage as In Progress if files were uploaded
                if ($uploadCount > 0) {
                    $pdo->prepare("UPDATE workflow_stages SET status = 'In Progress' WHERE id = ?")->execute([$stageId]);
                }
            }
        }

        flashMessage('success', 'Project created successfully.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $newId);
        break;

    // ── Update ──────────────────────────────────────────────
    case 'update':
        $id = (int)($_POST['id'] ?? 0);
        $title = trim($_POST['title'] ?? '');
        $visibility = $_POST['visibility'] ?? 'Public';
        $department = trim($_POST['department'] ?? '');
        $projectSubtype = trim($_POST['project_subtype'] ?? '');
        $fiscalYear = $_POST['fiscal_year'] ?? date('Y');

        if ($id <= 0 || $title === '') {
            flashMessage('danger', 'Title is required.');
            redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
        }

        // Validate classification
        if ($visibility === 'Private') {
            $department = null;
            $projectSubtype = null;
        } else {
            if ($department === '') {
                flashMessage('danger', 'Department is required for public projects.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
            }
            $validSubtypes = getDepartmentSubtypes();
            if (!isset($validSubtypes[$department]) || !in_array($projectSubtype, $validSubtypes[$department])) {
                flashMessage('danger', 'Invalid sub-type for the selected department.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $id);
            }
        }

        // Map to legacy project_type
        $projectTypeMap = [
            'BDP' => 'Root Project',
            'SEF' => 'SEF Project',
            'Special Projects' => 'Special Project',
            'Planning and Programming' => 'Root Project',
            'Construction' => 'Root Project',
        ];
        $projectType = $projectTypeMap[$projectSubtype] ?? 'Root Project';

        $stmt = $pdo->prepare("
            UPDATE projects SET
                title = ?, project_type = ?, visibility = ?, department = ?,
                project_subtype = ?, category_id = ?, description = ?,
                priority = ?, status = ?, funding_source = ?, resolution_no = ?,
                budget_allocated = ?, actual_cost = ?, start_date = ?,
                target_end_date = ?, actual_end_date = ?, fiscal_year = ?,
                location = ?, contact_person = ?, remarks = ?
            WHERE id = ? AND is_deleted = 0
        ");
        $stmt->execute([
            $title,
            $projectType,
            $visibility,
            $department,
            $projectSubtype,
            !empty($_POST['category_id']) ? (int)$_POST['category_id'] : null,
            trim($_POST['description'] ?? ''),
            $_POST['priority'] ?? 'Medium',
            $_POST['status'] ?? 'Not Started',
            trim($_POST['funding_source'] ?? ''),
            trim($_POST['resolution_no'] ?? ''),
            !empty($_POST['budget_allocated']) ? (float)$_POST['budget_allocated'] : 0,
            !empty($_POST['actual_cost']) ? (float)$_POST['actual_cost'] : 0,
            !empty($_POST['start_date']) ? $_POST['start_date'] : null,
            !empty($_POST['target_end_date']) ? $_POST['target_end_date'] : null,
            !empty($_POST['actual_end_date']) ? $_POST['actual_end_date'] : null,
            $fiscalYear,
            trim($_POST['location'] ?? ''),
            trim($_POST['contact_person'] ?? ''),
            trim($_POST['remarks'] ?? ''),
            $id,
        ]);

        flashMessage('success', 'Project updated successfully.');
        redirect(BASE_URL . '/pages/projects/view.php?id=' . $id);
        break;

    // ── Soft Delete ─────────────────────────────────────────
    case 'delete':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_deleted = 1 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project deleted.');
        }
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    // ── Archive ─────────────────────────────────────────────
    case 'archive':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_archived = 1 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project archived.');
        }
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    // ── Unarchive ───────────────────────────────────────────
    case 'unarchive':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("UPDATE projects SET is_archived = 0 WHERE id = ?");
            $stmt->execute([$id]);
            flashMessage('success', 'Project unarchived.');
        }
        redirect(BASE_URL . '/pages/projects/archive.php');
        break;

    // ── Duplicate ───────────────────────────────────────────
    case 'duplicate':
        $id = (int)($_POST['id'] ?? 0);
        if ($id > 0) {
            $stmt = $pdo->prepare("SELECT * FROM projects WHERE id = ? AND is_deleted = 0");
            $stmt->execute([$id]);
            $orig = $stmt->fetch();

            if ($orig) {
                $insert = $pdo->prepare("
                    INSERT INTO projects
                        (title, project_type, visibility, department, project_subtype,
                         category_id, description, priority, status,
                         funding_source, resolution_no, budget_allocated, start_date,
                         target_end_date, fiscal_year, location, contact_person, remarks)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Not Started', ?, ?, ?, ?, ?, ?, ?, ?, ?)
                ");
                $insert->execute([
                    $orig['title'] . ' (Copy)',
                    $orig['project_type'],
                    $orig['visibility'],
                    $orig['department'],
                    $orig['project_subtype'],
                    $orig['category_id'],
                    $orig['description'],
                    $orig['priority'],
                    $orig['funding_source'],
                    $orig['resolution_no'],
                    $orig['budget_allocated'],
                    $orig['start_date'],
                    $orig['target_end_date'],
                    $orig['fiscal_year'],
                    $orig['location'],
                    $orig['contact_person'],
                    $orig['remarks'],
                ]);
                $newId = $pdo->lastInsertId();
                initWorkflowStages($pdo, $newId);
                flashMessage('success', 'Project duplicated.');
                redirect(BASE_URL . '/pages/projects/edit.php?id=' . $newId);
            }
        }
        flashMessage('danger', 'Project not found.');
        redirect(BASE_URL . '/pages/projects/list.php');
        break;

    default:
        redirect(BASE_URL . '/pages/projects/list.php');
}

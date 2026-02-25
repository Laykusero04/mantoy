<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/backup/index.php');
}

$action = $_POST['action'] ?? '';

switch ($action) {

    // ── Export Database ──────────────────────────────────────
    case 'export_db':
        $timestamp = date('Y-m-d_His');
        $filename = "bdpt_backup_{$timestamp}.sql";

        // Get all tables
        $tables = $pdo->query("SHOW TABLES")->fetchAll(PDO::FETCH_COLUMN);

        $sql = "-- BDPT Database Backup\n";
        $sql .= "-- Generated: " . date('Y-m-d H:i:s') . "\n";
        $sql .= "-- " . APP_NAME . " v" . APP_VERSION . "\n\n";
        $sql .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";

        foreach ($tables as $table) {
            // DROP + CREATE
            $createStmt = $pdo->query("SHOW CREATE TABLE `$table`")->fetch();
            $sql .= "DROP TABLE IF EXISTS `$table`;\n";
            $sql .= $createStmt['Create Table'] . ";\n\n";

            // INSERT data
            $rows = $pdo->query("SELECT * FROM `$table`")->fetchAll(PDO::FETCH_ASSOC);
            if (!empty($rows)) {
                $cols = array_keys($rows[0]);
                // Skip generated columns for budget_items
                if ($table === 'budget_items') {
                    $cols = array_filter($cols, fn($c) => $c !== 'total_cost');
                }
                $colList = implode('`, `', $cols);

                foreach ($rows as $row) {
                    $values = [];
                    foreach ($cols as $col) {
                        if ($row[$col] === null) {
                            $values[] = 'NULL';
                        } else {
                            $values[] = $pdo->quote($row[$col]);
                        }
                    }
                    $sql .= "INSERT INTO `$table` (`$colList`) VALUES (" . implode(', ', $values) . ");\n";
                }
                $sql .= "\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS = 1;\n";

        // Save to backups dir
        if (!is_dir(BACKUP_DIR)) {
            mkdir(BACKUP_DIR, 0755, true);
        }
        file_put_contents(BACKUP_DIR . $filename, $sql);

        // Download
        header('Content-Type: application/sql');
        header('Content-Disposition: attachment; filename="' . $filename . '"');
        header('Content-Length: ' . strlen($sql));
        echo $sql;
        exit;

    // ── Export Files (ZIP) ───────────────────────────────────
    case 'export_files':
        $uploadDir = UPLOAD_DIR;
        if (!is_dir($uploadDir)) {
            flashMessage('warning', 'No uploaded files to export.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        $timestamp = date('Y-m-d_His');
        $zipFilename = "bdpt_files_{$timestamp}.zip";
        $zipPath = BACKUP_DIR . $zipFilename;

        if (!is_dir(BACKUP_DIR)) {
            mkdir(BACKUP_DIR, 0755, true);
        }

        $zip = new ZipArchive();
        if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
            flashMessage('danger', 'Failed to create ZIP file.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($uploadDir, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::LEAVES_ONLY
        );

        $fileCount = 0;
        foreach ($iterator as $file) {
            if (!$file->isDir()) {
                $relativePath = 'uploads/' . substr($file->getPathname(), strlen($uploadDir));
                $relativePath = str_replace('\\', '/', $relativePath);
                $zip->addFile($file->getPathname(), $relativePath);
                $fileCount++;
            }
        }

        $zip->close();

        if ($fileCount === 0) {
            @unlink($zipPath);
            flashMessage('warning', 'No files to export.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        header('Content-Type: application/zip');
        header('Content-Disposition: attachment; filename="' . $zipFilename . '"');
        header('Content-Length: ' . filesize($zipPath));
        readfile($zipPath);
        exit;

    // ── Restore Database ────────────────────────────────────
    case 'restore_db':
        if (!isset($_FILES['sql_file']) || $_FILES['sql_file']['error'] !== UPLOAD_ERR_OK) {
            flashMessage('danger', 'No file uploaded or upload error.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        $file = $_FILES['sql_file'];
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

        if ($ext !== 'sql') {
            flashMessage('danger', 'Only .sql files are accepted.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        $sql = file_get_contents($file['tmp_name']);
        if (empty($sql)) {
            flashMessage('danger', 'SQL file is empty.');
            redirect(BASE_URL . '/pages/backup/index.php');
        }

        try {
            $pdo->exec($sql);
            flashMessage('success', 'Database restored successfully from ' . sanitize($file['name']));
        } catch (PDOException $e) {
            flashMessage('danger', 'Restore failed: ' . $e->getMessage());
        }

        redirect(BASE_URL . '/pages/backup/index.php');
        break;

    // ── Delete backup file ──────────────────────────────────
    case 'delete_backup':
        $filename = basename($_POST['filename'] ?? '');
        $filepath = BACKUP_DIR . $filename;

        if ($filename && file_exists($filepath) && strpos(realpath($filepath), realpath(BACKUP_DIR)) === 0) {
            unlink($filepath);
            flashMessage('success', 'Backup file deleted.');
        } else {
            flashMessage('danger', 'File not found.');
        }
        redirect(BASE_URL . '/pages/backup/index.php');
        break;

    default:
        redirect(BASE_URL . '/pages/backup/index.php');
}

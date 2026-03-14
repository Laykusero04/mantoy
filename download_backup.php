<?php
/**
 * Secure backup file download.
 * Serves files from BACKUP_DIR only; prevents path traversal.
 */
require_once __DIR__ . '/config/constants.php';

$filename = $_GET['file'] ?? '';
$filename = basename($filename);

if ($filename === '') {
    header('HTTP/1.1 400 Bad Request');
    exit('No file specified.');
}

$ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
if (!in_array($ext, ['sql', 'zip'], true)) {
    header('HTTP/1.1 400 Bad Request');
    exit('Invalid file type.');
}

$backupDir = rtrim(BACKUP_DIR, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR;
$filepath = $backupDir . $filename;

if (!file_exists($filepath) || !is_file($filepath)) {
    header('HTTP/1.1 404 Not Found');
    exit('File not found.');
}

$realPath = realpath($filepath);
$realBackup = realpath($backupDir);
if ($realPath === false || $realBackup === false || strpos($realPath, $realBackup) !== 0) {
    header('HTTP/1.1 403 Forbidden');
    exit('Access denied.');
}

$mime = $ext === 'zip' ? 'application/zip' : 'application/sql';
header('Content-Type: ' . $mime);
header('Content-Disposition: attachment; filename="' . basename($filename) . '"');
header('Content-Length: ' . filesize($filepath));
header('Cache-Control: no-store');
readfile($filepath);
exit;

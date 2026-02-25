<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/settings/index.php');
}

$action = $_POST['action'] ?? '';

switch ($action) {
    case 'save':
        $fields = ['barangay_name', 'municipality', 'province', 'prepared_by', 'designation', 'fiscal_year'];

        $stmt = $pdo->prepare("UPDATE settings SET setting_value = ? WHERE setting_key = ?");

        foreach ($fields as $key) {
            $value = trim($_POST[$key] ?? '');
            $stmt->execute([$value, $key]);
        }

        flashMessage('success', 'Settings saved successfully.');
        redirect(BASE_URL . '/pages/settings/index.php');
        break;

    default:
        redirect(BASE_URL . '/pages/settings/index.php');
}

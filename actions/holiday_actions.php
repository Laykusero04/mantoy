<?php
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../includes/functions.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect(BASE_URL . '/pages/settings/holidays.php');
}

$action = $_POST['action'] ?? '';
$redirectUrl = BASE_URL . '/pages/settings/holidays.php';

// Ensure holidays table exists
try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS holidays (
            id INT AUTO_INCREMENT PRIMARY KEY,
            holiday_date DATE NOT NULL UNIQUE,
            description VARCHAR(255) NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_holidays_date (holiday_date)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
} catch (Exception $e) {
    flashMessage('danger', 'Could not create holidays table.');
    redirect($redirectUrl);
}

switch ($action) {
    case 'add':
        $date = trim($_POST['holiday_date'] ?? '');
        $desc = trim($_POST['description'] ?? '');
        if ($date === '') {
            flashMessage('danger', 'Date is required.');
            redirect($redirectUrl);
        }
        try {
            $ins = $pdo->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
            $ins->execute([$date, $desc ?: null]);
            flashMessage('success', 'Holiday added (no-work day).');
        } catch (PDOException $e) {
            if ($e->getCode() == 23000) flashMessage('danger', 'That date is already a holiday.');
            else flashMessage('danger', 'Could not add holiday.');
        }
        redirect($redirectUrl);
        break;

    case 'add_range':
        $from = trim($_POST['range_from'] ?? '');
        $to = trim($_POST['range_to'] ?? '');
        $desc = trim($_POST['range_description'] ?? '');
        if ($from === '' || $to === '') {
            flashMessage('danger', 'From and To dates are required.');
            redirect($redirectUrl);
        }
        $fromTs = strtotime($from);
        $toTs = strtotime($to);
        if ($fromTs === false || $toTs === false || $toTs < $fromTs) {
            flashMessage('danger', 'Invalid date range.');
            redirect($redirectUrl);
        }
        $limit = 366 * 3; // max 3 years of days
        $added = 0;
        $skipped = 0;
        $ins = $pdo->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
        for ($n = 0; $n <= $limit; $n++) {
            $d = date('Y-m-d', $fromTs + $n * 86400);
            if ($d > date('Y-m-d', $toTs)) break;
            try {
                $ins->execute([$d, $desc ?: null]);
                $added++;
            } catch (PDOException $e) {
                if ($e->getCode() == 23000) $skipped++;
            }
        }
        flashMessage('success', $added . ' no-work day(s) added.' . ($skipped ? ' ' . $skipped . ' already existed.' : ''));
        redirect($redirectUrl);
        break;

    case 'add_weekends':
        $year = (int)($_POST['weekends_year'] ?? date('Y'));
        $desc = trim($_POST['weekends_description'] ?? '') ?: 'Weekend';
        if ($year < 2000 || $year > 2100) {
            flashMessage('danger', 'Invalid year.');
            redirect($redirectUrl);
        }
        $added = 0;
        $skipped = 0;
        $ins = $pdo->prepare("INSERT INTO holidays (holiday_date, description) VALUES (?, ?)");
        for ($month = 1; $month <= 12; $month++) {
            $days = (int)date('t', strtotime($year . '-' . $month . '-01'));
            for ($day = 1; $day <= $days; $day++) {
                $ts = strtotime("$year-$month-$day");
                $dow = (int)date('N', $ts); // 1=Mon .. 7=Sun
                if ($dow === 6 || $dow === 7) {
                    $d = date('Y-m-d', $ts);
                    try {
                        $ins->execute([$d, $desc]);
                        $added++;
                    } catch (PDOException $e) {
                        if ($e->getCode() == 23000) $skipped++;
                    }
                }
            }
        }
        flashMessage('success', $added . ' weekend day(s) added for ' . $year . '.' . ($skipped ? ' ' . $skipped . ' already existed.' : ''));
        redirect($redirectUrl);
        break;

    case 'delete':
        $hid = (int)($_POST['holiday_id'] ?? 0);
        if ($hid > 0) {
            $pdo->prepare("DELETE FROM holidays WHERE id = ?")->execute([$hid]);
            flashMessage('success', 'Holiday removed.');
        }
        redirect($redirectUrl);
        break;

    case 'delete_range':
        $from = trim($_POST['range_from'] ?? '');
        $to = trim($_POST['range_to'] ?? '');
        if ($from === '' || $to === '') {
            flashMessage('danger', 'From and To dates are required.');
            redirect($redirectUrl);
        }
        $stmt = $pdo->prepare("DELETE FROM holidays WHERE holiday_date BETWEEN ? AND ?");
        $stmt->execute([$from, $to]);
        $n = $stmt->rowCount();
        flashMessage('success', $n . ' no-work day(s) removed.');
        redirect($redirectUrl);
        break;

    case 'delete_all':
        $pdo->exec("DELETE FROM holidays");
        flashMessage('success', 'All holidays (no-work days) removed.');
        redirect($redirectUrl);
        break;

    default:
        redirect($redirectUrl);
}

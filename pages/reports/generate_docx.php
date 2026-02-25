<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

use PhpOffice\PhpWord\PhpWord;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\SimpleType\Jc;
use PhpOffice\PhpWord\Style\Font;

$settings = getSettings($pdo);
$barangay  = $settings['barangay_name'] ?: 'Barangay';
$municipal = $settings['municipality'] ?: '';
$province  = $settings['province'] ?: '';
$preparedBy = $settings['prepared_by'] ?: '';
$designation = $settings['designation'] ?: '';

$projectId = (int)($_GET['id'] ?? 0);
$sections = $_GET['sections'] ?? ['details', 'budget', 'milestones', 'log'];

if ($projectId <= 0) {
    flashMessage('danger', 'Invalid project.');
    redirect(BASE_URL . '/pages/reports/index.php');
}

$stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_deleted = 0");
$stmt->execute([$projectId]);
$project = $stmt->fetch();

if (!$project) {
    flashMessage('danger', 'Project not found.');
    redirect(BASE_URL . '/pages/reports/index.php');
}

// ── Create Word Document ────────────────────────────────────
$phpWord = new PhpWord();
$phpWord->setDefaultFontName('Arial');
$phpWord->setDefaultFontSize(10);

// Styles
$phpWord->addTitleStyle(1, ['bold' => true, 'size' => 14], ['alignment' => Jc::CENTER]);
$phpWord->addTitleStyle(2, ['bold' => true, 'size' => 12, 'color' => '1a56db']);

$headerStyle = ['bold' => true, 'size' => 14];
$subHeaderStyle = ['size' => 10, 'color' => '666666'];
$sectionHeaderStyle = ['bold' => true, 'size' => 11, 'color' => 'FFFFFF'];
$labelStyle = ['bold' => true, 'size' => 9];
$valueStyle = ['size' => 9];
$tableHeaderFont = ['bold' => true, 'size' => 8, 'color' => 'FFFFFF'];
$tableCellFont = ['size' => 8];

$tableStyle = [
    'borderSize' => 1,
    'borderColor' => 'CCCCCC',
    'cellMargin' => 50,
];
$phpWord->addTableStyle('projectTable', $tableStyle);

$section = $phpWord->addSection(['marginTop' => 600, 'marginBottom' => 600]);

// Header
$section->addText($barangay, $headerStyle, ['alignment' => Jc::CENTER]);
if ($municipal || $province) {
    $section->addText(trim("$municipal, $province", ', '), $subHeaderStyle, ['alignment' => Jc::CENTER]);
}
$section->addText('PROJECT REPORT', ['bold' => true, 'size' => 12], ['alignment' => Jc::CENTER, 'spaceBefore' => 100]);
$section->addLine(['weight' => 2, 'width' => 450, 'height' => 0, 'color' => '1a56db']);

// Project Title
$section->addTextBreak();
$section->addText($project['title'], ['bold' => true, 'size' => 13]);
$section->addText(
    $project['project_type'] . ' | ' . $project['status'] . ' | ' . $project['priority'] . ' Priority',
    ['size' => 9, 'color' => '888888']
);

// Details
if (in_array('details', $sections)) {
    $section->addTextBreak();
    $run = $section->addTextRun(['spacing' => 0]);
    $run->addText(' Project Details', $sectionHeaderStyle);
    // Use a table for details
    $table = $section->addTable('projectTable');
    $details = [
        ['Category', $project['category_name'] ?? 'N/A'],
        ['Fiscal Year', $project['fiscal_year']],
        ['Funding Source', $project['funding_source'] ?: 'N/A'],
        ['Resolution No.', $project['resolution_no'] ?: 'N/A'],
        ['Location', $project['location'] ?: 'N/A'],
        ['Contact Person', $project['contact_person'] ?: 'N/A'],
        ['Start Date', $project['start_date'] ? formatDate($project['start_date']) : 'N/A'],
        ['Target End Date', $project['target_end_date'] ? formatDate($project['target_end_date']) : 'N/A'],
        ['Budget Allocated', formatCurrency($project['budget_allocated'])],
        ['Actual Cost', formatCurrency($project['actual_cost'])],
    ];
    foreach ($details as $row) {
        $tableRow = $table->addRow();
        $tableRow->addCell(3000)->addText($row[0], $labelStyle);
        $tableRow->addCell(6000)->addText($row[1], $valueStyle);
    }

    if ($project['description']) {
        $section->addTextBreak();
        $section->addText('Description:', $labelStyle);
        $section->addText($project['description'], $valueStyle);
    }
    if ($project['remarks']) {
        $section->addTextBreak();
        $section->addText('Remarks:', $labelStyle);
        $section->addText($project['remarks'], $valueStyle);
    }
}

// Budget
if (in_array('budget', $sections)) {
    $budgetItems = $pdo->prepare("SELECT * FROM budget_items WHERE project_id = ? ORDER BY id");
    $budgetItems->execute([$projectId]);
    $budgetItems = $budgetItems->fetchAll();

    if (!empty($budgetItems)) {
        $section->addTextBreak();
        $section->addText('Budget Breakdown', ['bold' => true, 'size' => 11, 'color' => '1a56db']);

        $table = $section->addTable('projectTable');
        $headerRow = $table->addRow();
        foreach (['#', 'Item', 'Category', 'Qty', 'Unit', 'Unit Cost', 'Total'] as $h) {
            $headerRow->addCell($h === 'Item' ? 3000 : 1200, ['bgColor' => '1a56db'])->addText($h, $tableHeaderFont);
        }

        $total = 0;
        foreach ($budgetItems as $i => $item) {
            $row = $table->addRow();
            $row->addCell(1200)->addText($i + 1, $tableCellFont);
            $row->addCell(3000)->addText($item['item_name'], $tableCellFont);
            $row->addCell(1200)->addText($item['budget_category'], $tableCellFont);
            $row->addCell(1200)->addText(number_format($item['quantity'], 2), $tableCellFont);
            $row->addCell(1200)->addText($item['unit'], $tableCellFont);
            $row->addCell(1200)->addText(formatCurrency($item['unit_cost']), $tableCellFont);
            $row->addCell(1200)->addText(formatCurrency($item['total_cost']), $tableCellFont);
            $total += $item['total_cost'];
        }
        $totalRow = $table->addRow();
        $totalRow->addCell(8400, ['gridSpan' => 6])->addText('TOTAL:', ['bold' => true, 'size' => 8]);
        $totalRow->addCell(1200)->addText(formatCurrency($total), ['bold' => true, 'size' => 8]);
    }
}

// Milestones
if (in_array('milestones', $sections)) {
    $milestones = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY sort_order, id");
    $milestones->execute([$projectId]);
    $milestones = $milestones->fetchAll();

    if (!empty($milestones)) {
        $section->addTextBreak();
        $section->addText('Milestones', ['bold' => true, 'size' => 11, 'color' => '1a56db']);

        $table = $section->addTable('projectTable');
        $headerRow = $table->addRow();
        foreach (['#', 'Title', 'Target Date', 'Completed', 'Status'] as $h) {
            $headerRow->addCell($h === 'Title' ? 3500 : 1500, ['bgColor' => '1a56db'])->addText($h, $tableHeaderFont);
        }

        foreach ($milestones as $i => $ms) {
            $row = $table->addRow();
            $row->addCell(1500)->addText($i + 1, $tableCellFont);
            $row->addCell(3500)->addText($ms['title'], $tableCellFont);
            $row->addCell(1500)->addText($ms['target_date'] ? formatDate($ms['target_date']) : '-', $tableCellFont);
            $row->addCell(1500)->addText($ms['completed_date'] ? formatDate($ms['completed_date']) : '-', $tableCellFont);
            $row->addCell(1500)->addText($ms['status'], $tableCellFont);
        }
    }
}

// Action Log
if (in_array('log', $sections)) {
    $logs = $pdo->prepare("SELECT * FROM action_logs WHERE project_id = ? ORDER BY log_date DESC");
    $logs->execute([$projectId]);
    $logs = $logs->fetchAll();

    if (!empty($logs)) {
        $section->addTextBreak();
        $section->addText('Action Log', ['bold' => true, 'size' => 11, 'color' => '1a56db']);

        $table = $section->addTable('projectTable');
        $headerRow = $table->addRow();
        foreach (['Date', 'Logged By', 'Action Taken', 'Notes'] as $h) {
            $headerRow->addCell(2250, ['bgColor' => '1a56db'])->addText($h, $tableHeaderFont);
        }

        foreach ($logs as $log) {
            $row = $table->addRow();
            $row->addCell(2250)->addText(formatDate($log['log_date']), $tableCellFont);
            $row->addCell(2250)->addText($log['logged_by'] ?? '', $tableCellFont);
            $row->addCell(2250)->addText($log['action_taken'], $tableCellFont);
            $row->addCell(2250)->addText($log['notes'] ?? '', $tableCellFont);
        }
    }
}

// Signature
$section->addTextBreak(2);
$section->addText('Prepared by:', ['size' => 9]);
$section->addTextBreak(2);
$section->addText(strtoupper($preparedBy), ['bold' => true, 'size' => 10]);
$section->addText($designation, ['size' => 9]);
$section->addTextBreak();
$section->addText('Generated: ' . date('M d, Y h:i A') . ' | ' . APP_NAME . ' v' . APP_VERSION, ['size' => 7, 'color' => '999999']);

// Output
$filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['title']) . '_Report.docx';
header('Content-Type: application/vnd.openxmlformats-officedocument.wordprocessingml.document');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Cache-Control: max-age=0');

$writer = IOFactory::createWriter($phpWord, 'Word2007');
$writer->save('php://output');
exit;

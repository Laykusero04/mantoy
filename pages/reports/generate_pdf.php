<?php
require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';
require_once __DIR__ . '/../../includes/functions.php';

$settings = getSettings($pdo);
$barangay  = $settings['barangay_name'] ?: 'Barangay';
$municipal = $settings['municipality'] ?: '';
$province  = $settings['province'] ?: '';
$preparedBy = $settings['prepared_by'] ?: '';
$designation = $settings['designation'] ?: '';

// ── Determine report type ───────────────────────────────────
$projectId = (int)($_GET['id'] ?? 0);
$reportType = $_GET['type'] ?? '';
$year = (int)($_GET['year'] ?? $settings['fiscal_year']);
$sections = $_GET['sections'] ?? ['details', 'budget', 'milestones', 'log'];

// ── Create PDF ──────────────────────────────────────────────
$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8');
$pdf->SetCreator(APP_NAME);
$pdf->SetAuthor($preparedBy);
$pdf->setPrintHeader(false);
$pdf->setPrintFooter(false);
$pdf->SetMargins(15, 15, 15);
$pdf->SetAutoPageBreak(true, 20);
$pdf->SetFont('helvetica', '', 10);

// ── Helper: page header block ───────────────────────────────
function pdfHeader($pdf, $barangay, $municipal, $province, $title) {
    $pdf->AddPage();
    $pdf->SetFont('helvetica', 'B', 14);
    $pdf->Cell(0, 7, $barangay, 0, 1, 'C');
    if ($municipal || $province) {
        $pdf->SetFont('helvetica', '', 10);
        $pdf->Cell(0, 5, trim("$municipal, $province", ', '), 0, 1, 'C');
    }
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, $title, 0, 1, 'C');
    $pdf->Ln(1);
    $pdf->SetDrawColor(26, 86, 219);
    $pdf->Line(15, $pdf->GetY(), 195, $pdf->GetY());
    $pdf->Ln(4);
    $pdf->SetFont('helvetica', '', 10);
}

// ── Helper: detail row ──────────────────────────────────────
function pdfDetailRow($pdf, $label, $value) {
    $pdf->SetFont('helvetica', 'B', 9);
    $pdf->Cell(50, 6, $label, 0, 0);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 6, $value, 0, 1);
}

// ── Helper: table header ────────────────────────────────────
function pdfTableHeader($pdf, $cols) {
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->SetFillColor(240, 240, 240);
    foreach ($cols as $col) {
        $pdf->Cell($col[0], 6, $col[1], 1, 0, $col[2] ?? 'L', true);
    }
    $pdf->Ln();
    $pdf->SetFont('helvetica', '', 8);
}

// ── Helper: signature block ─────────────────────────────────
function pdfSignature($pdf, $preparedBy, $designation) {
    $pdf->Ln(15);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, 'Prepared by:', 0, 1);
    $pdf->Ln(10);
    $pdf->SetFont('helvetica', 'B', 10);
    $pdf->Cell(0, 5, strtoupper($preparedBy), 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->Cell(0, 5, $designation, 0, 1);
    $pdf->Ln(3);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->SetTextColor(128, 128, 128);
    $pdf->Cell(0, 5, 'Generated: ' . date('M d, Y h:i A') . ' | ' . APP_NAME . ' v' . APP_VERSION, 0, 1);
    $pdf->SetTextColor(0, 0, 0);
}

// ══════════════════════════════════════════════════════════════
// INDIVIDUAL PROJECT REPORT
// ══════════════════════════════════════════════════════════════
if ($projectId > 0) {
    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id WHERE p.id = ? AND p.is_deleted = 0");
    $stmt->execute([$projectId]);
    $project = $stmt->fetch();

    if (!$project) {
        die('Project not found.');
    }

    $pdf->SetTitle($project['title'] . ' - Project Report');
    pdfHeader($pdf, $barangay, $municipal, $province, 'PROJECT REPORT');

    // Project title + type badge
    $pdf->SetFont('helvetica', 'B', 12);
    $pdf->Cell(0, 7, $project['title'], 0, 1);
    $pdf->SetFont('helvetica', '', 9);
    $pdf->SetTextColor(100, 100, 100);
    $pdf->Cell(0, 5, $project['project_type'] . ' | ' . $project['status'] . ' | ' . $project['priority'] . ' Priority', 0, 1);
    $pdf->SetTextColor(0, 0, 0);
    $pdf->Ln(4);

    // Details section
    if (in_array('details', $sections)) {
        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->SetFillColor(26, 86, 219);
        $pdf->SetTextColor(255, 255, 255);
        $pdf->Cell(0, 6, ' Project Details', 0, 1, 'L', true);
        $pdf->SetTextColor(0, 0, 0);
        $pdf->Ln(2);

        pdfDetailRow($pdf, 'Category:', $project['category_name'] ?? 'N/A');
        pdfDetailRow($pdf, 'Fiscal Year:', $project['fiscal_year']);
        pdfDetailRow($pdf, 'Funding Source:', $project['funding_source'] ?: 'N/A');
        pdfDetailRow($pdf, 'Resolution No.:', $project['resolution_no'] ?: 'N/A');
        pdfDetailRow($pdf, 'Location:', $project['location'] ?: 'N/A');
        pdfDetailRow($pdf, 'Contact Person:', $project['contact_person'] ?: 'N/A');
        pdfDetailRow($pdf, 'Start Date:', $project['start_date'] ? formatDate($project['start_date']) : 'N/A');
        pdfDetailRow($pdf, 'Target End Date:', $project['target_end_date'] ? formatDate($project['target_end_date']) : 'N/A');
        if ($project['actual_end_date']) {
            pdfDetailRow($pdf, 'Actual End Date:', formatDate($project['actual_end_date']));
        }
        pdfDetailRow($pdf, 'Budget Allocated:', formatCurrency($project['budget_allocated']));
        pdfDetailRow($pdf, 'Actual Cost:', formatCurrency($project['actual_cost']));
        $util = $project['budget_allocated'] > 0 ? round(($project['actual_cost'] / $project['budget_allocated']) * 100, 1) . '%' : 'N/A';
        pdfDetailRow($pdf, 'Utilization:', $util);

        if ($project['description']) {
            $pdf->Ln(2);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Description:', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $project['description'], 0, 'L');
        }
        if ($project['remarks']) {
            $pdf->Ln(1);
            $pdf->SetFont('helvetica', 'B', 9);
            $pdf->Cell(0, 5, 'Remarks:', 0, 1);
            $pdf->SetFont('helvetica', '', 9);
            $pdf->MultiCell(0, 5, $project['remarks'], 0, 'L');
        }
        $pdf->Ln(4);
    }

    // Budget section
    if (in_array('budget', $sections)) {
        $budgetItems = $pdo->prepare("SELECT * FROM budget_items WHERE project_id = ? ORDER BY id");
        $budgetItems->execute([$projectId]);
        $budgetItems = $budgetItems->fetchAll();

        if (!empty($budgetItems)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(26, 86, 219);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 6, ' Scope of Work', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);

            pdfTableHeader($pdf, [[8, '#', 'C'], [60, 'Item', 'L'], [20, 'Qty', 'R'], [20, 'Unit', 'L'], [30, 'Unit Cost', 'R'], [32, 'Total', 'R']]);

            $total = 0;
            foreach ($budgetItems as $i => $item) {
                $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
                $pdf->Cell(60, 5, $item['item_name'], 1, 0);
                $pdf->Cell(20, 5, number_format($item['quantity'], 2), 1, 0, 'R');
                $pdf->Cell(20, 5, $item['unit'], 1, 0);
                $pdf->Cell(30, 5, formatCurrency($item['unit_cost']), 1, 0, 'R');
                $pdf->Cell(32, 5, formatCurrency($item['total_cost']), 1, 1, 'R');
                $total += $item['total_cost'];
            }
            $pdf->SetFont('helvetica', 'B', 8);
            $pdf->Cell(123, 5, 'TOTAL:', 1, 0, 'R');
            $pdf->Cell(32, 5, formatCurrency($total), 1, 1, 'R');
            $pdf->Ln(4);
        }
    }

    // Milestones section
    if (in_array('milestones', $sections)) {
        $milestones = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY sort_order, id");
        $milestones->execute([$projectId]);
        $milestones = $milestones->fetchAll();

        if (!empty($milestones)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(26, 86, 219);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 6, ' Milestones', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);

            pdfTableHeader($pdf, [[8, '#', 'C'], [60, 'Title', 'L'], [30, 'Target Date', 'C'], [30, 'Completed', 'C'], [20, 'Status', 'C'], [32, 'Notes', 'L']]);

            foreach ($milestones as $i => $ms) {
                $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
                $pdf->Cell(60, 5, $ms['title'], 1, 0);
                $pdf->Cell(30, 5, $ms['target_date'] ? formatDate($ms['target_date']) : '-', 1, 0, 'C');
                $pdf->Cell(30, 5, $ms['completed_date'] ? formatDate($ms['completed_date']) : '-', 1, 0, 'C');
                $pdf->Cell(20, 5, $ms['status'], 1, 0, 'C');
                $pdf->Cell(32, 5, mb_substr($ms['notes'] ?? '', 0, 25), 1, 1);
            }
            $doneCnt = count(array_filter($milestones, fn($m) => $m['status'] === 'Done'));
            $pdf->SetFont('helvetica', 'I', 8);
            $pdf->Cell(0, 5, "Progress: $doneCnt/" . count($milestones) . " completed", 0, 1);
            $pdf->Ln(4);
        }
    }

    // Action Log section
    if (in_array('log', $sections)) {
        $logs = $pdo->prepare("SELECT * FROM action_logs WHERE project_id = ? ORDER BY log_date DESC");
        $logs->execute([$projectId]);
        $logs = $logs->fetchAll();

        if (!empty($logs)) {
            $pdf->SetFont('helvetica', 'B', 10);
            $pdf->SetFillColor(26, 86, 219);
            $pdf->SetTextColor(255, 255, 255);
            $pdf->Cell(0, 6, ' Action Log', 0, 1, 'L', true);
            $pdf->SetTextColor(0, 0, 0);
            $pdf->Ln(2);

            pdfTableHeader($pdf, [[30, 'Date', 'L'], [35, 'Logged By', 'L'], [55, 'Action Taken', 'L'], [60, 'Notes', 'L']]);

            foreach ($logs as $log) {
                $pdf->Cell(30, 5, formatDate($log['log_date']), 1, 0);
                $pdf->Cell(35, 5, mb_substr($log['logged_by'] ?? '', 0, 25), 1, 0);
                $pdf->Cell(55, 5, mb_substr($log['action_taken'], 0, 40), 1, 0);
                $pdf->Cell(60, 5, mb_substr($log['notes'] ?? '', 0, 45), 1, 1);
            }
            $pdf->Ln(4);
        }
    }

    pdfSignature($pdf, $preparedBy, $designation);
    $filename = preg_replace('/[^a-zA-Z0-9_-]/', '_', $project['title']) . '_Report.pdf';
    $pdf->Output($filename, 'D');
    exit;
}

// ══════════════════════════════════════════════════════════════
// SUMMARY REPORTS
// ══════════════════════════════════════════════════════════════
if ($reportType === 'summary') {
    $pdf->SetTitle("All Projects Summary - FY $year");
    pdfHeader($pdf, $barangay, $municipal, $province, "ALL PROJECTS SUMMARY - FY $year");

    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? ORDER BY p.project_type, p.title");
    $stmt->execute([$year]);
    $projects = $stmt->fetchAll();

    pdfTableHeader($pdf, [[8, '#', 'C'], [55, 'Title', 'L'], [25, 'Type', 'L'], [25, 'Status', 'L'], [22, 'Priority', 'C'], [25, 'Budget', 'R'], [20, 'Actual', 'R']]);

    $totalBudget = 0;
    $totalActual = 0;
    foreach ($projects as $i => $p) {
        $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
        $pdf->Cell(55, 5, mb_substr($p['title'], 0, 38), 1, 0);
        $pdf->Cell(25, 5, $p['project_type'], 1, 0);
        $pdf->Cell(25, 5, $p['status'], 1, 0);
        $pdf->Cell(22, 5, $p['priority'], 1, 0, 'C');
        $pdf->Cell(25, 5, formatCurrency($p['budget_allocated']), 1, 0, 'R');
        $pdf->Cell(20, 5, formatCurrency($p['actual_cost']), 1, 1, 'R');
        $totalBudget += $p['budget_allocated'];
        $totalActual += $p['actual_cost'];
    }
    $pdf->SetFont('helvetica', 'B', 8);
    $pdf->Cell(135, 5, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell(25, 5, formatCurrency($totalBudget), 1, 0, 'R');
    $pdf->Cell(20, 5, formatCurrency($totalActual), 1, 1, 'R');
    $pdf->Ln(2);
    $pdf->SetFont('helvetica', '', 8);
    $pdf->Cell(0, 5, 'Total Projects: ' . count($projects), 0, 1);

    pdfSignature($pdf, $preparedBy, $designation);
    $pdf->Output("All_Projects_Summary_FY{$year}.pdf", 'D');
    exit;
}

if ($reportType === 'budget') {
    $pdf->SetTitle("Budget Utilization Report - FY $year");
    pdfHeader($pdf, $barangay, $municipal, $province, "BUDGET UTILIZATION REPORT - FY $year");

    $stmt = $pdo->prepare("SELECT p.title, p.project_type, p.budget_allocated, p.actual_cost FROM projects p WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? ORDER BY p.budget_allocated DESC");
    $stmt->execute([$year]);
    $projects = $stmt->fetchAll();

    pdfTableHeader($pdf, [[8, '#', 'C'], [55, 'Title', 'L'], [25, 'Type', 'L'], [30, 'Budget', 'R'], [30, 'Actual Cost', 'R'], [32, 'Utilization', 'R']]);

    $totalB = 0; $totalA = 0;
    foreach ($projects as $i => $p) {
        $util = $p['budget_allocated'] > 0 ? round(($p['actual_cost'] / $p['budget_allocated']) * 100, 1) . '%' : 'N/A';
        $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
        $pdf->Cell(55, 5, mb_substr($p['title'], 0, 38), 1, 0);
        $pdf->Cell(25, 5, $p['project_type'], 1, 0);
        $pdf->Cell(30, 5, formatCurrency($p['budget_allocated']), 1, 0, 'R');
        $pdf->Cell(30, 5, formatCurrency($p['actual_cost']), 1, 0, 'R');
        $pdf->Cell(32, 5, $util, 1, 1, 'R');
        $totalB += $p['budget_allocated'];
        $totalA += $p['actual_cost'];
    }
    $pdf->SetFont('helvetica', 'B', 8);
    $totalUtil = $totalB > 0 ? round(($totalA / $totalB) * 100, 1) . '%' : 'N/A';
    $pdf->Cell(88, 5, 'TOTAL:', 1, 0, 'R');
    $pdf->Cell(30, 5, formatCurrency($totalB), 1, 0, 'R');
    $pdf->Cell(30, 5, formatCurrency($totalA), 1, 0, 'R');
    $pdf->Cell(32, 5, $totalUtil, 1, 1, 'R');

    pdfSignature($pdf, $preparedBy, $designation);
    $pdf->Output("Budget_Utilization_FY{$year}.pdf", 'D');
    exit;
}

if ($reportType === 'status') {
    $pdf->SetTitle("Project Status Report - FY $year");
    pdfHeader($pdf, $barangay, $municipal, $province, "PROJECT STATUS REPORT - FY $year");

    $statuses = STATUS_OPTIONS;
    foreach ($statuses as $status) {
        $stmt = $pdo->prepare("SELECT p.title, p.project_type, p.budget_allocated, p.start_date, p.target_end_date FROM projects p WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? AND p.status = ? ORDER BY p.title");
        $stmt->execute([$year, $status]);
        $projects = $stmt->fetchAll();

        if (empty($projects)) continue;

        $pdf->SetFont('helvetica', 'B', 10);
        $pdf->Cell(0, 6, "$status (" . count($projects) . ")", 0, 1);
        $pdf->Ln(1);

        pdfTableHeader($pdf, [[8, '#', 'C'], [60, 'Title', 'L'], [30, 'Type', 'L'], [30, 'Start', 'C'], [27, 'Target End', 'C'], [25, 'Budget', 'R']]);

        foreach ($projects as $i => $p) {
            $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
            $pdf->Cell(60, 5, mb_substr($p['title'], 0, 42), 1, 0);
            $pdf->Cell(30, 5, $p['project_type'], 1, 0);
            $pdf->Cell(30, 5, $p['start_date'] ? formatDate($p['start_date']) : '-', 1, 0, 'C');
            $pdf->Cell(27, 5, $p['target_end_date'] ? formatDate($p['target_end_date']) : '-', 1, 0, 'C');
            $pdf->Cell(25, 5, formatCurrency($p['budget_allocated']), 1, 1, 'R');
        }
        $pdf->Ln(4);
    }

    pdfSignature($pdf, $preparedBy, $designation);
    $pdf->Output("Status_Report_FY{$year}.pdf", 'D');
    exit;
}

if ($reportType === 'overdue') {
    $pdf->SetTitle("Overdue Projects Report - FY $year");
    pdfHeader($pdf, $barangay, $municipal, $province, "OVERDUE PROJECTS REPORT - FY $year");

    $stmt = $pdo->prepare("SELECT p.*, c.name as category_name FROM projects p LEFT JOIN categories c ON p.category_id = c.id WHERE p.is_deleted = 0 AND p.is_archived = 0 AND p.fiscal_year = ? AND p.status NOT IN ('Completed','Cancelled') AND p.target_end_date IS NOT NULL AND p.target_end_date < CURDATE() ORDER BY p.target_end_date");
    $stmt->execute([$year]);
    $projects = $stmt->fetchAll();

    if (empty($projects)) {
        $pdf->SetFont('helvetica', 'I', 10);
        $pdf->Cell(0, 10, 'No overdue projects found.', 0, 1, 'C');
    } else {
        pdfTableHeader($pdf, [[8, '#', 'C'], [50, 'Title', 'L'], [25, 'Type', 'L'], [22, 'Status', 'L'], [27, 'Due Date', 'C'], [22, 'Days Over', 'R'], [26, 'Budget', 'R']]);

        foreach ($projects as $i => $p) {
            $daysOver = floor((time() - strtotime($p['target_end_date'])) / 86400);
            $pdf->Cell(8, 5, $i + 1, 1, 0, 'C');
            $pdf->Cell(50, 5, mb_substr($p['title'], 0, 35), 1, 0);
            $pdf->Cell(25, 5, $p['project_type'], 1, 0);
            $pdf->Cell(22, 5, $p['status'], 1, 0);
            $pdf->Cell(27, 5, formatDate($p['target_end_date']), 1, 0, 'C');
            $pdf->Cell(22, 5, $daysOver . ' days', 1, 0, 'R');
            $pdf->Cell(26, 5, formatCurrency($p['budget_allocated']), 1, 1, 'R');
        }
        $pdf->Ln(2);
        $pdf->SetFont('helvetica', '', 8);
        $pdf->Cell(0, 5, 'Total Overdue: ' . count($projects) . ' project(s)', 0, 1);
    }

    pdfSignature($pdf, $preparedBy, $designation);
    $pdf->Output("Overdue_Projects_FY{$year}.pdf", 'D');
    exit;
}

// Fallback
flashMessage('danger', 'Invalid report parameters.');
redirect(BASE_URL . '/pages/reports/index.php');

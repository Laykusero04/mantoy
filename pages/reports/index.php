<?php
$pageTitle = 'Reports';
require_once __DIR__ . '/../../includes/header.php';

// Fetch projects for dropdown
$projects = $pdo->query("SELECT id, title, project_type FROM projects WHERE is_deleted = 0 ORDER BY title")->fetchAll();

// Fiscal years
$years = $pdo->query("SELECT DISTINCT fiscal_year FROM projects WHERE is_deleted = 0 ORDER BY fiscal_year DESC")->fetchAll(PDO::FETCH_COLUMN);
if (empty($years)) $years = [(int)$currentFiscalYear];
?>

<div class="mb-4">
    <h4 class="mb-1 fw-bold">Generate Reports</h4>
    <nav aria-label="breadcrumb">
        <ol class="breadcrumb mb-0 small">
            <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
            <li class="breadcrumb-item active">Reports</li>
        </ol>
    </nav>
</div>

<!-- Individual Project Report -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Individual Project Report</h6>
    </div>
    <div class="card-body">
        <form method="GET" action="<?= BASE_URL ?>/pages/reports/generate_pdf.php" id="projectReportForm">
            <div class="row g-3 align-items-end">
                <div class="col-md-5">
                    <label class="form-label">Select Project</label>
                    <select class="form-select" name="id" id="reportProjectId" required>
                        <option value="">Choose a project...</option>
                        <?php foreach ($projects as $p): ?>
                            <option value="<?= $p['id'] ?>"><?= sanitize($p['title']) ?> (<?= sanitize($p['project_type']) ?>)</option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="col-md-3">
                    <label class="form-label">Format</label>
                    <div class="d-flex gap-3 pt-1">
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="fmtPdf" value="pdf" checked>
                            <label class="form-check-label" for="fmtPdf">PDF</label>
                        </div>
                        <div class="form-check">
                            <input class="form-check-input" type="radio" name="format" id="fmtDocx" value="docx">
                            <label class="form-check-label" for="fmtDocx">DOCX</label>
                        </div>
                    </div>
                </div>
                <div class="col-md-4">
                    <label class="form-label">Include Sections</label>
                    <div class="d-flex flex-wrap gap-2">
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="sections[]" value="details" checked id="secDetails"><label class="form-check-label small" for="secDetails">Details</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="sections[]" value="budget" checked id="secBudget"><label class="form-check-label small" for="secBudget">Budget</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="sections[]" value="milestones" checked id="secMs"><label class="form-check-label small" for="secMs">Milestones</label></div>
                        <div class="form-check"><input class="form-check-input" type="checkbox" name="sections[]" value="log" checked id="secLog"><label class="form-check-label small" for="secLog">Action Log</label></div>
                    </div>
                </div>
            </div>
            <div class="mt-3">
                <button type="submit" class="btn btn-primary">
                    <i class="bi bi-file-earmark-arrow-down me-1"></i> Generate Report
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Summary Reports -->
<div class="card shadow-sm mb-4">
    <div class="card-header bg-white">
        <h6 class="mb-0 section-title">Summary Reports</h6>
    </div>
    <div class="card-body">
        <div class="row g-3 mb-3">
            <div class="col-md-3">
                <label class="form-label">Fiscal Year</label>
                <select class="form-select" id="summaryYear">
                    <?php foreach ($years as $y): ?>
                        <option value="<?= $y ?>" <?= $y == $currentFiscalYear ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
        </div>
        <div class="row g-3">
            <div class="col-md-3">
                <a href="#" class="btn btn-outline-primary w-100 summary-link" data-type="summary">
                    <i class="bi bi-file-earmark-text me-1"></i> All Projects Summary
                </a>
            </div>
            <div class="col-md-3">
                <a href="#" class="btn btn-outline-success w-100 summary-link" data-type="budget">
                    <i class="bi bi-wallet2 me-1"></i> Budget Utilization
                </a>
            </div>
            <div class="col-md-3">
                <a href="#" class="btn btn-outline-info w-100 summary-link" data-type="status">
                    <i class="bi bi-bar-chart me-1"></i> Project Status
                </a>
            </div>
            <div class="col-md-3">
                <a href="#" class="btn btn-outline-danger w-100 summary-link" data-type="overdue">
                    <i class="bi bi-exclamation-triangle me-1"></i> Overdue Projects
                </a>
            </div>
        </div>
    </div>
</div>

<?php
$pageScripts = '<script>
// Individual report: switch form action based on format
document.getElementById("projectReportForm")?.addEventListener("submit", function(e) {
    const fmt = document.querySelector("input[name=format]:checked").value;
    if (fmt === "docx") {
        this.action = "' . BASE_URL . '/pages/reports/generate_docx.php";
    } else {
        this.action = "' . BASE_URL . '/pages/reports/generate_pdf.php";
    }
});

// Summary links: build URL with year
document.querySelectorAll(".summary-link").forEach(function(link) {
    link.addEventListener("click", function(e) {
        e.preventDefault();
        const year = document.getElementById("summaryYear").value;
        const type = this.dataset.type;
        window.location.href = "' . BASE_URL . '/pages/reports/generate_pdf.php?type=" + type + "&year=" + year;
    });
});
</script>';

require_once __DIR__ . '/../../includes/footer.php';
?>

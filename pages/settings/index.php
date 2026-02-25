<?php
$pageTitle = 'Settings';
require_once __DIR__ . '/../../includes/header.php';

$s = getSettings($pdo);
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Settings</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item active">Settings</li>
            </ol>
        </nav>
    </div>
</div>

<form action="<?= BASE_URL ?>/actions/settings_actions.php" method="POST">
    <input type="hidden" name="action" value="save">

    <!-- Barangay Information -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title">Barangay Information</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="barangay_name" class="form-label">Barangay Name</label>
                    <input type="text" class="form-control" id="barangay_name" name="barangay_name"
                           value="<?= sanitize($s['barangay_name'] ?? '') ?>" placeholder="e.g., Barangay San Jose">
                </div>
                <div class="col-md-4">
                    <label for="municipality" class="form-label">Municipality / City</label>
                    <input type="text" class="form-control" id="municipality" name="municipality"
                           value="<?= sanitize($s['municipality'] ?? '') ?>">
                </div>
                <div class="col-md-4">
                    <label for="province" class="form-label">Province</label>
                    <input type="text" class="form-control" id="province" name="province"
                           value="<?= sanitize($s['province'] ?? '') ?>">
                </div>
            </div>
        </div>
    </div>

    <!-- Report Settings -->
    <div class="card shadow-sm mb-4">
        <div class="card-header bg-white">
            <h6 class="mb-0 section-title">Report Settings</h6>
        </div>
        <div class="card-body">
            <div class="row g-3">
                <div class="col-md-4">
                    <label for="prepared_by" class="form-label">Prepared By</label>
                    <input type="text" class="form-control" id="prepared_by" name="prepared_by"
                           value="<?= sanitize($s['prepared_by'] ?? '') ?>" placeholder="Full name">
                </div>
                <div class="col-md-4">
                    <label for="designation" class="form-label">Designation</label>
                    <input type="text" class="form-control" id="designation" name="designation"
                           value="<?= sanitize($s['designation'] ?? '') ?>" placeholder="e.g., Barangay Secretary">
                </div>
                <div class="col-md-4">
                    <label for="fiscal_year" class="form-label">Fiscal Year</label>
                    <input type="number" class="form-control" id="fiscal_year" name="fiscal_year"
                           value="<?= sanitize($s['fiscal_year'] ?? date('Y')) ?>" min="2020" max="2099">
                </div>
            </div>
        </div>
    </div>

    <div class="d-flex justify-content-end mb-4">
        <button type="submit" class="btn btn-primary">
            <i class="bi bi-check-lg me-1"></i> Save Settings
        </button>
    </div>
</form>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

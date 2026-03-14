<?php
$pageTitle = 'Holidays (No-Work Days)';
require_once __DIR__ . '/../../includes/header.php';

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
} catch (Exception $e) { }

$holidays = [];
try {
    $holidays = $pdo->query("SELECT id, holiday_date, description FROM holidays ORDER BY holiday_date")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) { }
?>

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Holidays (No-Work Days)</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/pages/settings/index.php">Settings</a></li>
                <li class="breadcrumb-item active">Holidays</li>
            </ol>
        </nav>
    </div>
</div>

<p class="text-muted small mb-3">These dates are treated as <strong>no-work days</strong> for all projects. Add <strong>individual dates</strong>, a <strong>date range</strong>, or <strong>all weekends</strong> in a year. Per-project weekend (Sat/Sun) override is in each project’s Edit.</p>

<div class="row">
    <div class="col-lg-5 mb-4">
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Add single date</h6>
            </div>
            <div class="card-body">
                <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST">
                    <input type="hidden" name="action" value="add">
                    <div class="mb-3">
                        <label for="holiday_date" class="form-label">Date <span class="text-danger">*</span></label>
                        <input type="date" class="form-control" id="holiday_date" name="holiday_date" required>
                    </div>
                    <div class="mb-3">
                        <label for="description" class="form-label">Description (optional)</label>
                        <input type="text" class="form-control" id="description" name="description" placeholder="e.g., Christmas">
                    </div>
                    <button type="submit" class="btn btn-primary btn-sm"><i class="bi bi-plus-lg me-1"></i> Add</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm mb-3">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Add date range</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Add every day between two dates as no-work.</p>
                <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST">
                    <input type="hidden" name="action" value="add_range">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="range_from" class="form-label small">From <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="range_from" name="range_from" required>
                        </div>
                        <div class="col-6">
                            <label for="range_to" class="form-label small">To <span class="text-danger">*</span></label>
                            <input type="date" class="form-control form-control-sm" id="range_to" name="range_to" required>
                        </div>
                    </div>
                    <div class="mb-2">
                        <label for="range_description" class="form-label small">Description (optional)</label>
                        <input type="text" class="form-control form-control-sm" id="range_description" name="range_description" placeholder="e.g., Christmas break">
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-range me-1"></i> Add range</button>
                </form>
            </div>
        </div>
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Add all weekends (year)</h6>
            </div>
            <div class="card-body">
                <p class="text-muted small mb-2">Add every Saturday and Sunday in a year as no-work.</p>
                <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST">
                    <input type="hidden" name="action" value="add_weekends">
                    <div class="row g-2 mb-2">
                        <div class="col-6">
                            <label for="weekends_year" class="form-label small">Year</label>
                            <select class="form-select form-select-sm" id="weekends_year" name="weekends_year">
                                <?php for ($y = date('Y') - 1; $y <= date('Y') + 2; $y++): ?>
                                <option value="<?= $y ?>" <?= $y == date('Y') ? 'selected' : '' ?>><?= $y ?></option>
                                <?php endfor; ?>
                            </select>
                        </div>
                        <div class="col-6">
                            <label for="weekends_description" class="form-label small">Description</label>
                            <input type="text" class="form-control form-control-sm" id="weekends_description" name="weekends_description" value="Weekend" placeholder="Weekend">
                        </div>
                    </div>
                    <button type="submit" class="btn btn-outline-primary btn-sm"><i class="bi bi-calendar-week me-1"></i> Add all weekends</button>
                </form>
            </div>
        </div>
    </div>
    <div class="col-lg-7">
        <div class="card shadow-sm">
            <div class="card-header bg-white d-flex justify-content-between align-items-center flex-wrap gap-2">
                <h6 class="mb-0 section-title">No-work days (holidays)</h6>
                <?php if (!empty($holidays)): ?>
                <div class="d-flex gap-2">
                    <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Remove all no-work days? This cannot be undone.');">
                        <input type="hidden" name="action" value="delete_all">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Remove all</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
            <div class="card-body p-0">
                <?php if (empty($holidays)): ?>
                    <div class="text-center text-muted py-4">No holidays defined yet. Add single dates, a range, or all weekends above.</div>
                <?php else: ?>
                    <div class="table-responsive">
                        <table class="table table-hover align-middle mb-0">
                            <thead class="table-light">
                                <tr>
                                    <th>Date</th>
                                    <th>Description</th>
                                    <th width="80"></th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($holidays as $h): ?>
                                <tr>
                                    <td><?= formatDate($h['holiday_date']) ?></td>
                                    <td><?= sanitize($h['description'] ?: '—') ?></td>
                                    <td>
                                        <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST" class="d-inline" onsubmit="return confirm('Remove this holiday?');">
                                            <input type="hidden" name="action" value="delete">
                                            <input type="hidden" name="holiday_id" value="<?= (int)$h['id'] ?>">
                                            <button type="submit" class="btn btn-outline-danger btn-sm" title="Remove"><i class="bi bi-trash"></i></button>
                                        </form>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <div class="p-3 border-top bg-light">
                        <p class="small text-muted mb-2">Remove a range of dates:</p>
                        <form action="<?= BASE_URL ?>/actions/holiday_actions.php" method="POST" class="d-inline-flex flex-wrap align-items-end gap-2" onsubmit="return confirm('Remove all no-work days between these dates?');">
                            <input type="hidden" name="action" value="delete_range">
                            <div>
                                <label for="del_range_from" class="form-label small mb-0">From</label>
                                <input type="date" class="form-control form-control-sm" id="del_range_from" name="range_from" required style="width:10rem;">
                            </div>
                            <div>
                                <label for="del_range_to" class="form-label small mb-0">To</label>
                                <input type="date" class="form-control form-control-sm" id="del_range_to" name="range_to" required style="width:10rem;">
                            </div>
                            <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash me-1"></i> Remove range</button>
                        </form>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php require_once __DIR__ . '/../../includes/footer.php'; ?>

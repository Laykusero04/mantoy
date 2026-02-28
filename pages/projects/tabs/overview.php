<?php
// Tab: Overview — receives $pdo, $project, $id, $activeTab from view.php
?>
<div class="row g-4">
    <div class="col-lg-8">
        <div class="card shadow-sm">
            <div class="card-header bg-white">
                <h6 class="mb-0 section-title">Project Details</h6>
            </div>
            <div class="card-body">
                <table class="table table-borderless mb-0">
                    <tbody>
                        <tr><td class="text-muted" style="width:35%">Visibility</td><td><span class="badge <?= $project['visibility'] === 'Private' ? 'bg-dark' : 'bg-success' ?>"><?= sanitize($project['visibility']) ?></span></td></tr>
                        <?php if ($project['visibility'] === 'Public' && !empty($project['department'])): ?>
                        <tr><td class="text-muted">Department</td><td><?= sanitize($project['department']) ?></td></tr>
                        <tr><td class="text-muted">Sub-type</td><td><?= sanitize($project['project_subtype'] ?? '—') ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Category</td><td><?= sanitize($project['category_name'] ?? '—') ?></td></tr>
                        <tr><td class="text-muted">Funding Source</td><td><?= sanitize($project['funding_source'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted">Resolution No.</td><td><?= sanitize($project['resolution_no'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted">Location</td><td><?= sanitize($project['location'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted">Contact Person</td><td><?= sanitize($project['contact_person'] ?: '—') ?></td></tr>
                        <tr><td class="text-muted">Start Date</td><td><?= formatDate($project['start_date']) ?></td></tr>
                        <tr>
                            <td class="text-muted">Target End Date</td>
                            <td>
                                <?= formatDate($project['target_end_date']) ?>
                                <?php if (isOverdue($project)): ?>
                                    <span class="text-danger small ms-1">(Overdue)</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                        <?php if ($project['actual_end_date']): ?>
                        <tr><td class="text-muted">Actual End Date</td><td><?= formatDate($project['actual_end_date']) ?></td></tr>
                        <?php endif; ?>
                        <tr><td class="text-muted">Fiscal Year</td><td><?= sanitize($project['fiscal_year']) ?></td></tr>
                    </tbody>
                </table>
            </div>
        </div>

        <?php if (!empty($project['description'])): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><h6 class="mb-0 section-title">Description</h6></div>
            <div class="card-body"><p class="mb-0"><?= nl2br(sanitize($project['description'])) ?></p></div>
        </div>
        <?php endif; ?>

        <?php if (!empty($project['remarks'])): ?>
        <div class="card shadow-sm mt-3">
            <div class="card-header bg-white"><h6 class="mb-0 section-title">Remarks</h6></div>
            <div class="card-body"><p class="mb-0"><?= nl2br(sanitize($project['remarks'])) ?></p></div>
        </div>
        <?php endif; ?>
    </div>

    <div class="col-lg-4">
        <div class="card shadow-sm">
            <div class="card-header bg-white"><h6 class="mb-0 section-title">Budget Overview</h6></div>
            <div class="card-body">
                <div class="mb-3">
                    <div class="small text-muted">Budget Allocated</div>
                    <div class="fs-5 fw-bold"><?= formatCurrency($project['budget_allocated']) ?></div>
                </div>
                <div class="mb-3">
                    <div class="small text-muted">Actual Cost</div>
                    <div class="fs-5 fw-bold"><?= formatCurrency($project['actual_cost']) ?></div>
                </div>
                <div>
                    <div class="d-flex justify-content-between small mb-1">
                        <span class="text-muted">Utilization</span>
                        <span class="fw-bold"><?= $utilization ?>%</span>
                    </div>
                    <div class="progress" style="height: 8px;">
                        <div class="progress-bar bg-<?= $utilization > 90 ? 'danger' : ($utilization > 70 ? 'warning' : 'success') ?>"
                             style="width: <?= min($utilization, 100) ?>%"></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="card shadow-sm mt-3">
            <div class="card-body small text-muted">
                <div class="d-flex justify-content-between mb-1">
                    <span>Created</span><span><?= formatDate($project['created_at']) ?></span>
                </div>
                <div class="d-flex justify-content-between">
                    <span>Last Updated</span><span><?= formatDate($project['updated_at']) ?></span>
                </div>
            </div>
        </div>
    </div>
</div>

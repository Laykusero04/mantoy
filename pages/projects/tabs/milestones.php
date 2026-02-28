<?php
// Tab: Milestones — receives $pdo, $project, $id, $activeTab from view.php

// Fetch milestones data
$msStmt = $pdo->prepare("SELECT * FROM milestones WHERE project_id = ? ORDER BY sort_order, id");
$msStmt->execute([$id]);
$milestones = $msStmt->fetchAll();
$totalMilestones = count($milestones);
$doneMilestones  = count(array_filter($milestones, fn($m) => $m['status'] === 'Done'));
$milestonePercent = $totalMilestones > 0 ? round(($doneMilestones / $totalMilestones) * 100) : 0;
?>

<!-- Progress Bar -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="d-flex justify-content-between align-items-center mb-2">
            <span class="section-title">Progress: <?= $doneMilestones ?>/<?= $totalMilestones ?> milestones</span>
            <span class="fw-bold"><?= $milestonePercent ?>%</span>
        </div>
        <div class="progress" style="height: 10px;">
            <div class="progress-bar bg-success" style="width: <?= $milestonePercent ?>%"></div>
        </div>
    </div>
</div>

<div class="card shadow-sm">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Milestones</h6>
        <button type="button" class="btn btn-primary btn-sm" data-bs-toggle="modal" data-bs-target="#addMilestoneModal">
            <i class="bi bi-plus-lg me-1"></i> Add Milestone
        </button>
    </div>
    <div class="card-body p-0">
        <?php if (empty($milestones)): ?>
            <div class="text-center text-muted py-5">
                <i class="bi bi-flag fs-1 d-block mb-2"></i>
                <p>No milestones yet.</p>
            </div>
        <?php else: ?>
        <ul class="list-group list-group-flush">
            <?php foreach ($milestones as $ms): ?>
            <li class="list-group-item d-flex justify-content-between align-items-center">
                <div class="d-flex align-items-center gap-3">
                    <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST" class="d-inline">
                        <input type="hidden" name="action" value="toggle">
                        <input type="hidden" name="project_id" value="<?= $id ?>">
                        <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                        <button type="submit" class="btn btn-sm p-0 border-0 bg-transparent" title="Toggle status">
                            <?php if ($ms['status'] === 'Done'): ?>
                                <i class="bi bi-check-circle-fill text-success fs-5"></i>
                            <?php else: ?>
                                <i class="bi bi-circle text-muted fs-5"></i>
                            <?php endif; ?>
                        </button>
                    </form>
                    <div>
                        <div class="fw-medium <?= $ms['status'] === 'Done' ? 'text-decoration-line-through text-muted' : '' ?>">
                            <?= sanitize($ms['title']) ?>
                        </div>
                        <div class="small text-muted">
                            <?php if ($ms['target_date']): ?>
                                Target: <?= formatDate($ms['target_date']) ?>
                            <?php endif; ?>
                            <?php if ($ms['completed_date']): ?>
                                &middot; Completed: <?= formatDate($ms['completed_date']) ?>
                            <?php endif; ?>
                            <?php if ($ms['notes']): ?>
                                &middot; <?= sanitize($ms['notes']) ?>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                <div class="d-flex gap-1 align-items-center">
                    <span class="badge <?= $ms['status'] === 'Done' ? 'bg-success' : 'bg-secondary' ?> me-2"><?= $ms['status'] ?></span>
                    <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST" class="d-inline"
                          onsubmit="return confirm('Delete this milestone?')">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="project_id" value="<?= $id ?>">
                        <input type="hidden" name="milestone_id" value="<?= $ms['id'] ?>">
                        <button type="submit" class="btn btn-outline-danger btn-sm"><i class="bi bi-trash"></i></button>
                    </form>
                </div>
            </li>
            <?php endforeach; ?>
        </ul>
        <?php endif; ?>
    </div>
</div>

<!-- Add Milestone Modal -->
<div class="modal fade" id="addMilestoneModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form action="<?= BASE_URL ?>/actions/milestone_actions.php" method="POST">
                <input type="hidden" name="action" value="add">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title">Add Milestone</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Target Date</label>
                        <input type="date" class="form-control" name="target_date">
                    </div>
                    <div>
                        <label class="form-label">Notes</label>
                        <textarea class="form-control" name="notes" rows="2"></textarea>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Add Milestone</button>
                </div>
            </form>
        </div>
    </div>
</div>

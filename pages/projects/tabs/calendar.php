<?php
// Tab: Calendar — receives $pdo, $project, $id, $activeTab from view.php
$budgetItemsStmt = $pdo->prepare("
    SELECT bi.id, bi.item_name, bi.quantity AS planned, bi.unit, bi.unit_cost,
           (bi.quantity * bi.unit_cost) AS total_cost,
           (SELECT COALESCE(SUM(al.quantity), 0) FROM action_logs al WHERE al.budget_item_id = bi.id AND al.is_scope_of_work = 1) AS used
    FROM budget_items bi
    WHERE bi.project_id = ?
    ORDER BY bi.item_name
");
$budgetItemsStmt->execute([$id]);
$calendarBudgetItems = $budgetItemsStmt->fetchAll();
foreach ($calendarBudgetItems as &$bi) {
    $bi['remaining'] = (float)$bi['planned'] - (float)$bi['used'];
}
unset($bi);
$projectBudgetAllocated = (float)($project['budget_allocated'] ?? 0);
$budgetItemsJson = json_encode(array_map(function ($b) {
    return [
        'id' => (int)$b['id'],
        'item_name' => $b['item_name'],
        'planned' => (float)$b['planned'],
        'used' => (float)$b['used'],
        'remaining' => (float)$b['remaining'],
        'unit' => (isset($b['unit']) && $b['unit'] !== '') ? $b['unit'] : 'pcs',
        'unit_cost' => (float)$b['unit_cost'],
        'total_cost' => (float)($b['total_cost'] ?? $b['planned'] * $b['unit_cost']),
    ];
}, $calendarBudgetItems));

// DUPA materials (for event materials: requested / delivered / notes)
$dupaMaterials = [];
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'project_dupa_items'");
    if ($chk && $chk->rowCount() > 0) {
        $colBi = $pdo->query("SHOW COLUMNS FROM project_dupa_items LIKE 'budget_item_id'");
        $onlyAssigned = $colBi && $colBi->rowCount() > 0;
        $sql = "SELECT id, description, quantity, unit, rate FROM project_dupa_items WHERE project_id = ? AND category = 'materials'" . ($onlyAssigned ? " AND budget_item_id IS NOT NULL" : "") . " ORDER BY sort_order, id";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$id]);
        $dupaMaterials = $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
$dupaMaterialsJson = json_encode(array_map(function ($m) {
    return [
        'id' => (int)$m['id'],
        'description' => $m['description'] ?? '',
        'quantity' => (float)($m['quantity'] ?? 0),
        'unit' => (isset($m['unit']) && $m['unit'] !== '') ? $m['unit'] : 'pcs',
        'rate' => (float)($m['rate'] ?? 0),
    ];
}, $dupaMaterials));

// SOW tasks (for "Tasks worked" in daily log — links to daily_log_tasks and updates task progress)
$sowTasksForProject = [];
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'sow_tasks'");
    if ($chk && $chk->rowCount() > 0) {
        $st = $pdo->prepare("SELECT id, code, description, unit, quantity, planned_duration_days FROM sow_tasks WHERE project_id = ? ORDER BY budget_item_id, parent_id, sort_order, id");
        $st->execute([$id]);
        $sowTasksForProject = $st->fetchAll(PDO::FETCH_ASSOC);
    }
} catch (Exception $e) {}
$sowTasksJson = json_encode(array_map(function ($t) {
    return [
        'id' => (int)$t['id'],
        'code' => $t['code'] ?? '',
        'description' => $t['description'] ?? '',
        'unit' => $t['unit'] ?? '',
        'quantity' => isset($t['quantity']) ? (float)$t['quantity'] : null,
        'planned_duration_days' => isset($t['planned_duration_days']) ? (int)$t['planned_duration_days'] : null,
    ];
}, $sowTasksForProject));
?>
<!-- Calendar Styles (FullCalendar) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Project Calendar</h6>
        <button type="button" class="btn btn-primary btn-sm" id="btnProjectNewEvent">
            <i class="bi bi-plus-lg me-1"></i> New Daily Log
        </button>
    </div>
    <div class="card-body">
        <div id="projectCalendar"></div>
    </div>
</div>

<!-- Project Event Modal -->
<div class="modal fade" id="projectEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/actions/calendar_actions.php" id="projectEventForm" enctype="multipart/form-data" data-budget-allocated="<?= $projectBudgetAllocated ?>" data-budget-items="<?= htmlspecialchars($budgetItemsJson, ENT_QUOTES, 'UTF-8') ?>" data-dupa-materials="<?= htmlspecialchars($dupaMaterialsJson, ENT_QUOTES, 'UTF-8') ?>" data-weather-options="<?= htmlspecialchars(json_encode(defined('WEATHER_OPTIONS') ? WEATHER_OPTIONS : []), ENT_QUOTES, 'UTF-8') ?>" data-sow-tasks="<?= htmlspecialchars($sowTasksJson, ENT_QUOTES, 'UTF-8') ?>">
                <input type="hidden" name="action" id="projectEventAction" value="create">
                <input type="hidden" name="id" id="projectEventId" value="">
                <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>&tab=calendar">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title" id="projectEventModalTitle">New Daily Log</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="event_date" id="projectEventDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="event_end_date" id="projectEventEndDate">
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="event_time" id="projectEventTime">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="is_all_day" id="projectEventAllDay" checked>
                                <label class="form-check-label" for="projectEventAllDay">All day</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="event_type" id="projectEventType">
                            <?php foreach (['deadline','milestone','inspection','meeting','activity','reminder','other'] as $type): ?>
                                <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <hr class="my-3">
                    <h6 class="text-muted mb-2">Daily log</h6>
                    <div class="row g-2 mb-3">
                        <div class="col-md-4">
                            <label class="form-label">Weather</label>
                            <select class="form-select" name="weather" id="projectEventWeather">
                                <option value="">— Select —</option>
                                <?php foreach (defined('WEATHER_OPTIONS') ? WEATHER_OPTIONS : [] as $w): ?>
                                    <option value="<?= htmlspecialchars($w, ENT_QUOTES, 'UTF-8') ?>"><?= htmlspecialchars($w) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Temperature (°C)</label>
                            <input type="number" class="form-control" name="temperature" id="projectEventTemperature" min="-50" max="60" step="0.1" placeholder="e.g. 28">
                        </div>
                        <div class="col-md-4">
                            <label class="form-label">Workers count</label>
                            <input type="number" class="form-control" name="workers_count" id="projectEventWorkersCount" min="0" max="10000" placeholder="e.g. 10">
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Equipment used</label>
                        <textarea class="form-control" name="equipment_used" id="projectEventEquipmentUsed" rows="2" placeholder="List equipment used this day"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Work performed</label>
                        <textarea class="form-control" name="actual_accomplishment" id="projectEventActualAccomplishment" rows="3" placeholder="What was accomplished this day"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Issues</label>
                        <textarea class="form-control" name="issues" id="projectEventIssues" rows="2" placeholder="Issues or concerns"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" name="description" id="projectEventDescription" rows="2"></textarea>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Scope of Work (daily accomplishment → Action Log)</label>
                        <p class="form-text small mb-2">Add one or more SOW items. Quantity cannot exceed remaining. Each line is recorded in the Action Log with this event date.</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="btnProjectAddSowRow">
                            <i class="bi bi-plus-lg me-1"></i> Add Scope of Work
                        </button>
                        <div id="projectEventSowRowsContainer"></div>
                        <div class="table-responsive mt-2" id="projectEventSowTableWrap" style="display: none;">
                            <table class="table table-sm table-bordered small">
                                <thead class="table-light">
                                    <tr>
                                        <th>Description</th>
                                        <th class="text-end" style="width:90px">Quantity</th>
                                        <th>Unit</th>
                                        <th class="text-end">Unit Cost</th>
                                        <th class="text-end">Amount</th>
                                        <th class="text-end">WT %</th>
                                        <th class="text-end">Remaining</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="projectEventSowTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3" id="projectEventTasksWrap" style="display: <?= count($sowTasksForProject) > 0 ? 'block' : 'none' ?>;">
                        <label class="form-label">Tasks worked (SOW tasks)</label>
                        <p class="form-text small mb-2">Link tasks worked this day. Progress (actual quantity/duration) is updated from daily logs.</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="btnProjectAddTaskRow">
                            <i class="bi bi-plus-lg me-1"></i> Add task
                        </button>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered small" id="projectEventTaskTableWrap">
                                <thead class="table-light">
                                    <tr>
                                        <th>Task</th>
                                        <th class="text-end" style="width:100px">Quantity done</th>
                                        <th class="text-end" style="width:100px">Duration (days)</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="projectEventTaskTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Materials (from DUPA)</label>
                        <p class="form-text small mb-2">Record materials requested / delivered for this event date. Shown in Monitoring tab.</p>
                        <button type="button" class="btn btn-outline-secondary btn-sm mb-2" id="btnProjectAddMaterialRow">
                            <i class="bi bi-plus-lg me-1"></i> Add material
                        </button>
                        <div class="table-responsive">
                            <table class="table table-sm table-bordered small" id="projectEventMaterialTableWrap">
                                <thead class="table-light">
                                    <tr>
                                        <th>Material</th>
                                        <th class="text-end" style="width:90px">Requested</th>
                                        <th class="text-end" style="width:90px">Delivered</th>
                                        <th>Notes</th>
                                        <th style="width:40px"></th>
                                    </tr>
                                </thead>
                                <tbody id="projectEventMaterialTableBody">
                                </tbody>
                            </table>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Manpower (person-days)</label>
                        <input type="number" class="form-control" name="manpower" id="projectEventManpower" min="0" step="0.5" placeholder="e.g. 10">
                        <div class="form-text small">Optional. Used in Monitoring tab for actual vs planned manpower.</div>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_on_main" id="projectEventShowOnMain" checked>
                            <label class="form-check-label" for="projectEventShowOnMain">
                                Also show on main calendar
                            </label>
                        </div>
                    </div>
                    <div class="mb-3" id="projectEventPhotosWrap" style="display: none;">
                        <label class="form-label">Photos (this log)</label>
                        <div id="projectEventPhotosList" class="d-flex flex-wrap gap-2"></div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Add photos (optional)</label>
                        <input type="file" class="form-control" name="attachments[]" multiple
                               accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                        <input type="text" class="form-control mt-1" name="attachment_description" placeholder="Attachment description (optional)">
                        <div class="form-text small">
                            Photos are linked to this daily log.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-outline-danger btn-sm me-auto" id="btnProjectDeleteEvent" style="display: none;">
                        <i class="bi bi-trash me-1"></i> Delete
                    </button>
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/actions/calendar_actions.php" id="projectDeleteEventForm" class="d-none">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="projectDeleteEventId" value="">
                <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>&tab=calendar">
            </form>
        </div>
    </div>
</div>

<?php
$projectCalendarScripts = '
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const calendarEl = document.getElementById("projectCalendar");
    const btnNew = document.getElementById("btnProjectNewEvent");
    const modalEl = document.getElementById("projectEventModal");
    const modal = new bootstrap.Modal(modalEl);

    const form = document.getElementById("projectEventForm");
    const deleteForm = document.getElementById("projectDeleteEventForm");
    const actionInput = document.getElementById("projectEventAction");
    const idInput = document.getElementById("projectEventId");
    const dateInput = document.getElementById("projectEventDate");
    const endDateInput = document.getElementById("projectEventEndDate");
    const timeInput = document.getElementById("projectEventTime");
    const allDayInput = document.getElementById("projectEventAllDay");
    const typeInput = document.getElementById("projectEventType");
    const descInput = document.getElementById("projectEventDescription");
    const sowTableWrap = document.getElementById("projectEventSowTableWrap");
    const sowTableBody = document.getElementById("projectEventSowTableBody");
    const sowRowsContainer = document.getElementById("projectEventSowRowsContainer");
    const btnAddSowRow = document.getElementById("btnProjectAddSowRow");
    const showOnMainInput = document.getElementById("projectEventShowOnMain");
    const projectBudgetAllocated = parseFloat(form.dataset.budgetAllocated || "0") || 0;
    let budgetItems = [];
    try { budgetItems = JSON.parse(form.dataset.budgetItems || "[]"); } catch (e) {}
    let dupaMaterials = [];
    try { dupaMaterials = JSON.parse(form.dataset.dupaMaterials || "[]"); } catch (e) {}
    let currentBudgetItems = budgetItems.slice();
    let sowRows = [];
    let materialRows = [];
    let sowTasks = [];
    try { sowTasks = JSON.parse(form.dataset.sowTasks || "[]"); } catch (e) {}
    let taskRows = [];
    const deleteIdInput = document.getElementById("projectDeleteEventId");
    const materialTableBody = document.getElementById("projectEventMaterialTableBody");
    const btnAddMaterialRow = document.getElementById("btnProjectAddMaterialRow");
    const taskTableBody = document.getElementById("projectEventTaskTableBody");
    const btnAddTaskRow = document.getElementById("btnProjectAddTaskRow");
    const modalTitle = document.getElementById("projectEventModalTitle");
    const btnDeleteEvent = document.getElementById("btnProjectDeleteEvent");
    const weatherInput = document.getElementById("projectEventWeather");
    const temperatureInput = document.getElementById("projectEventTemperature");
    const workersCountInput = document.getElementById("projectEventWorkersCount");
    const equipmentUsedInput = document.getElementById("projectEventEquipmentUsed");
    const actualAccomplishmentInput = document.getElementById("projectEventActualAccomplishment");
    const issuesInput = document.getElementById("projectEventIssues");
    const photosWrap = document.getElementById("projectEventPhotosWrap");
    const photosList = document.getElementById("projectEventPhotosList");

    function formatLocalDate(d) {
        if (!d) return "";
        const y = d.getFullYear();
        const m = String(d.getMonth() + 1).padStart(2, "0");
        const day = String(d.getDate()).padStart(2, "0");
        return y + "-" + m + "-" + day;
    }

    function resetForCreate(dateStr) {
        form.reset();
        actionInput.value = "create";
        idInput.value = "";
        modalTitle.textContent = "New Daily Log";
        dateInput.value = dateStr || "";
        endDateInput.value = "";
        allDayInput.checked = true;
        showOnMainInput.checked = true;
        if (weatherInput) weatherInput.value = "";
        if (temperatureInput) temperatureInput.value = "";
        if (workersCountInput) workersCountInput.value = "";
        if (equipmentUsedInput) equipmentUsedInput.value = "";
        if (actualAccomplishmentInput) actualAccomplishmentInput.value = "";
        if (issuesInput) issuesInput.value = "";
        if (photosWrap) { photosWrap.style.display = "none"; }
        if (photosList) photosList.innerHTML = "";
        currentBudgetItems = budgetItems.slice();
        sowRows = [];
        materialRows = [];
        renderSowTable();
        renderMaterialTable();
        btnDeleteEvent.style.display = "none";
    }

    function renderMaterialTable() {
        if (!materialTableBody) return;
        materialTableBody.innerHTML = "";
        var optHtml = "<option value=\"\">— Select material —</option>";
        dupaMaterials.forEach(function (m) {
            optHtml += "<option value=\"" + m.id + "\" data-unit=\"" + (m.unit || "pcs") + "\">" + (m.description || "—") + "</option>";
        });
        materialRows.forEach(function (r, idx) {
            var tr = document.createElement("tr");
            tr.setAttribute("data-mat-idx", idx);
            tr.innerHTML = "<td><select name=\"material_dupa_id[]\" class=\"form-select form-select-sm mat-dupa-select\">" + optHtml + "</select></td>" +
                "<td class=\"text-end\"><input type=\"number\" name=\"material_requested[]\" class=\"form-control form-control-sm text-end\" min=\"0\" step=\"0.01\" value=\"" + (r.requested != null && r.requested !== "" ? String(r.requested) : "") + "\" placeholder=\"0\"></td>" +
                "<td class=\"text-end\"><input type=\"number\" name=\"material_delivered[]\" class=\"form-control form-control-sm text-end\" min=\"0\" step=\"0.01\" value=\"" + (r.delivered != null && r.delivered !== "" ? String(r.delivered) : "") + "\" placeholder=\"0\"></td>" +
                "<td><input type=\"text\" name=\"material_notes[]\" class=\"form-control form-control-sm\" value=\"" + (r.notes || "").replace(/\"/g, "&quot;") + "\" placeholder=\"Notes\"></td>" +
                "<td><button type=\"button\" class=\"btn btn-outline-danger btn-sm py-0 px-1 mat-remove\" data-idx=\"" + idx + "\" title=\"Remove\"><i class=\"bi bi-x-lg\"></i></button></td>";
            materialTableBody.appendChild(tr);
            var sel = tr.querySelector(".mat-dupa-select");
            sel.value = r.dupa_item_id ? String(r.dupa_item_id) : "";
            tr.querySelector(".mat-remove").addEventListener("click", function () {
                materialRows.splice(parseInt(this.getAttribute("data-idx"), 10), 1);
                renderMaterialTable();
            });
        });
    }

    function formatPeso(n) {
        if (n == null || isNaN(n)) return "—";
        return "₱" + Number(n).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function getItemById(id) {
        return currentBudgetItems.find(function (b) { return b.id === id; }) || null;
    }

    function getRemainingForRow(rowIndex) {
        var row = sowRows[rowIndex];
        if (!row || !row.budget_item_id) return 0;
        var item = getItemById(row.budget_item_id);
        if (!item) return 0;
        var sumOthers = 0;
        for (var i = 0; i < sowRows.length; i++) {
            if (i !== rowIndex && sowRows[i].budget_item_id === row.budget_item_id) {
                sumOthers += parseFloat(sowRows[i].quantity) || 0;
            }
        }
        return Math.max(0, item.planned - item.used - sumOthers);
    }

    function updateRowDisplay(idx) {
        var row = sowRows[idx];
        if (!row) return;
        var item = getItemById(row.budget_item_id);
        var qty = parseFloat(row.quantity) || 0;
        var unitCost = item ? item.unit_cost : 0;
        var amount = qty * unitCost;
        var wtPct = projectBudgetAllocated > 0 && amount ? (Math.round((amount / projectBudgetAllocated) * 1000) / 10) + "%" : (projectBudgetAllocated > 0 && unitCost ? "0%" : "—");
        var remaining = getRemainingForRow(idx);
        var tr = sowTableBody.querySelector("tr[data-row-idx=\"" + idx + "\"]");
        if (!tr) return;
        var unitCell = tr.querySelector(".sow-unit");
        var unitCostCell = tr.querySelector(".sow-unit-cost");
        var amountCell = tr.querySelector(".sow-amount");
        var wtCell = tr.querySelector(".sow-wt");
        var remCell = tr.querySelector(".sow-remaining");
        if (unitCell) unitCell.textContent = item ? (item.unit || "pcs") : "—";
        if (unitCostCell) unitCostCell.textContent = item ? formatPeso(item.unit_cost) : "—";
        if (amountCell) amountCell.textContent = formatPeso(amount);
        if (wtCell) wtCell.textContent = wtPct;
        if (remCell) {
            remCell.textContent = remaining.toFixed(2);
            remCell.classList.toggle("text-danger", remaining <= 0 && item && item.planned > 0);
        }
    }

    function renderSowTable() {
        sowTableBody.innerHTML = "";
        if (sowRows.length === 0) {
            sowTableWrap.style.display = "none";
            return;
        }
        sowTableWrap.style.display = "block";
        var optHtml = "<option value=\"\">— Select —</option>";
        currentBudgetItems.forEach(function (b) {
            optHtml += "<option value=\"" + b.id + "\" data-planned=\"" + b.planned + "\" data-used=\"" + b.used + "\" data-remaining=\"" + b.remaining + "\" data-unit=\"" + (b.unit || "pcs") + "\" data-unit-cost=\"" + b.unit_cost + "\">" + (b.item_name || "—") + "</option>";
        });
        sowRows.forEach(function (r, idx) {
            var item = getItemById(r.budget_item_id);
            var unit = (item && item.unit) ? item.unit : (item ? "pcs" : "—");
            var cost = item ? formatPeso(item.unit_cost) : "—";
            var qty = parseFloat(r.quantity) || 0;
            var amount = qty * (item ? item.unit_cost : 0);
            var wtPct = projectBudgetAllocated > 0 && amount ? (Math.round((amount / projectBudgetAllocated) * 1000) / 10) + "%" : "—";
            var remaining = getRemainingForRow(idx);
            var tr = document.createElement("tr");
            tr.setAttribute("data-row-idx", idx);
            tr.innerHTML = "<td><select name=\"sow_budget_id[]\" class=\"form-select form-select-sm sow-item-select\" data-idx=\"" + idx + "\">" + optHtml + "</select></td>" +
                "<td class=\"text-end\"><input type=\"number\" name=\"sow_quantity[]\" class=\"form-control form-control-sm text-end sow-qty-input\" min=\"0\" step=\"0.01\" value=\"" + (r.quantity !== "" && r.quantity != null ? String(r.quantity) : "") + "\" data-idx=\"" + idx + "\"></td>" +
                "<td class=\"sow-unit\">" + unit + "</td><td class=\"text-end sow-unit-cost\">" + cost + "</td>" +
                "<td class=\"text-end sow-amount\">" + formatPeso(amount) + "</td><td class=\"text-end sow-wt\">" + wtPct + "</td>" +
                "<td class=\"text-end sow-remaining\">" + remaining.toFixed(2) + "</td>" +
                "<td><button type=\"button\" class=\"btn btn-outline-danger btn-sm py-0 px-1 sow-remove\" data-idx=\"" + idx + "\" title=\"Remove\"><i class=\"bi bi-x-lg\"></i></button></td>";
            sowTableBody.appendChild(tr);
            var sel = tr.querySelector(".sow-item-select");
            var qtyInput = tr.querySelector(".sow-qty-input");
            sel.value = r.budget_item_id ? String(r.budget_item_id) : "";
            sel.addEventListener("change", function () {
                var i = parseInt(this.dataset.idx, 10);
                sowRows[i].budget_item_id = this.value ? parseInt(this.value, 10) : null;
                sowRows[i].quantity = "";
                var it = getItemById(sowRows[i].budget_item_id);
                tr.querySelector(".sow-unit").textContent = it ? (it.unit || "pcs") : "—";
                var ucCell = tr.querySelector(".sow-unit-cost");
                if (ucCell) ucCell.textContent = it ? formatPeso(it.unit_cost) : "—";
                tr.querySelector(".sow-qty-input").value = "";
                for (var j = 0; j < sowRows.length; j++) updateRowDisplay(j);
            });
            qtyInput.addEventListener("input", function () {
                var i = parseInt(this.dataset.idx, 10);
                sowRows[i].quantity = this.value;
                for (var j = 0; j < sowRows.length; j++) updateRowDisplay(j);
            });
            tr.querySelector(".sow-remove").addEventListener("click", function () {
                sowRows.splice(parseInt(this.dataset.idx, 10), 1);
                renderSowTable();
            });
        });
        sowRows.forEach(function (_, j) { updateRowDisplay(j); });
    }

    btnAddSowRow.addEventListener("click", function () {
        sowRows.push({ budget_item_id: null, quantity: "" });
        renderSowTable();
    });

    if (btnAddMaterialRow) {
        btnAddMaterialRow.addEventListener("click", function () {
            materialRows.push({ dupa_item_id: null, requested: "", delivered: "", notes: "" });
            renderMaterialTable();
        });
    }

    form.addEventListener("submit", function (e) {
        for (var i = 0; i < sowRows.length; i++) {
            var rem = getRemainingForRow(i);
            var qty = parseFloat(sowRows[i].quantity) || 0;
            if (sowRows[i].budget_item_id && qty > rem) {
                e.preventDefault();
                var item = getItemById(sowRows[i].budget_item_id);
                alert("Quantity for \"" + (item ? item.item_name : "") + "\" cannot exceed remaining (" + rem.toFixed(2) + ").");
                return false;
            }
        }
    });

    function fillForEdit(event) {
        actionInput.value = "update";
        idInput.value = event.id;
        modalTitle.textContent = "Edit Daily Log";
        btnDeleteEvent.style.display = "inline-block";

        var props = event.extendedProps || {};
        typeInput.value = props.event_type || "other";
        descInput.value = props.description || "";
        if (weatherInput) weatherInput.value = props.weather || "";
        if (temperatureInput) temperatureInput.value = (props.temperature != null && props.temperature !== "") ? String(props.temperature) : "";
        if (workersCountInput) workersCountInput.value = (props.workers_count != null && props.workers_count !== "") ? String(props.workers_count) : "";
        if (equipmentUsedInput) equipmentUsedInput.value = props.equipment_used || "";
        if (actualAccomplishmentInput) actualAccomplishmentInput.value = props.actual_accomplishment || "";
        if (issuesInput) issuesInput.value = props.issues || "";
        var photos = props.photos || [];
        if (photosList && photosWrap) {
            photosList.innerHTML = "";
            if (photos.length > 0) {
                photosWrap.style.display = "block";
                photos.forEach(function (p) {
                    var isImage = /\.(jpg|jpeg|png|gif|webp)$/i.test(p.file_name || p.file_original_name || "");
                    var el = document.createElement("a");
                    el.href = p.url || "#";
                    el.target = "_blank";
                    el.rel = "noopener";
                    el.className = "d-inline-block";
                    if (isImage) {
                        var img = document.createElement("img");
                        img.src = p.url || "#";
                        img.alt = p.file_original_name || "Photo";
                        img.className = "rounded border";
                        img.style.width = "80px"; img.style.height = "80px"; img.style.objectFit = "cover";
                        el.appendChild(img);
                    } else {
                        el.textContent = p.file_original_name || p.file_name || "File";
                        el.className += " btn btn-outline-secondary btn-sm";
                    }
                    photosList.appendChild(el);
                });
            } else {
                photosWrap.style.display = "none";
            }
        }
        currentBudgetItems = budgetItems.map(function (b) { return Object.assign({}, b); });
        var sowLines = props.sow_lines || [];
        sowLines.forEach(function (line) {
            var it = currentBudgetItems.find(function (b) { return b.id === line.budget_item_id; });
            if (it) it.used = Math.max(0, (it.used || 0) - (parseFloat(line.quantity) || 0));
        });
        currentBudgetItems.forEach(function (b) { b.remaining = b.planned - b.used; });
        sowRows = sowLines.map(function (line) {
            return { budget_item_id: line.budget_item_id, quantity: line.quantity != null ? String(line.quantity) : "" };
        });
        renderSowTable();
        if (props.project_id) {
            showOnMainInput.checked = props.show_on_main !== false;
        } else {
            showOnMainInput.checked = true;
        }

        var start = event.start;
        var end = event.end || start;
        if (start) {
            dateInput.value = formatLocalDate(start);
        }
        if (end) {
            if (event.allDay && end) {
                var lastDay = new Date(end);
                lastDay.setDate(lastDay.getDate() - 1);
                endDateInput.value = formatLocalDate(lastDay);
            } else {
                endDateInput.value = formatLocalDate(end);
            }
        }

        if (event.allDay) {
            allDayInput.checked = true;
            timeInput.value = "";
        } else if (start) {
            allDayInput.checked = false;
            var h = start.getHours(), m = start.getMinutes();
            timeInput.value = String(h).padStart(2, "0") + ":" + String(m).padStart(2, "0");
        }

        deleteIdInput.value = event.id;
        var manpowerEl = document.getElementById("projectEventManpower");
        if (manpowerEl) manpowerEl.value = (props.manpower != null && props.manpower !== "") ? String(props.manpower) : "";
        var matLines = props.material_lines || [];
        materialRows = matLines.map(function (line) {
            return {
                dupa_item_id: line.dupa_item_id || null,
                requested: line.quantity_requested != null ? String(line.quantity_requested) : "",
                delivered: line.quantity != null ? String(line.quantity) : "",
                notes: line.remarks || ""
            };
        });
        renderMaterialTable();
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek"
        },
        firstDay: 0,
        selectable: true,
        events: {
            url: "'. BASE_URL .'/pages/calendar/events.php",
            method: "GET",
            extraParams: function () {
                return {
                    project_id: '. (int)$id .',
                    event_type: "",
                    only_main: ""
                };
            }
        },
        dateClick: function (info) {
            resetForCreate(info.dateStr);
            modal.show();
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            fillForEdit(info.event);
            modal.show();
        }
    });

    calendar.render();

    btnNew.addEventListener("click", function () {
        resetForCreate("");
        modal.show();
    });

    btnDeleteEvent.addEventListener("click", function () {
        if (!deleteIdInput.value) return;
        if (confirm("Delete this event? This cannot be undone.")) {
            deleteForm.submit();
        }
    });

    modalEl.addEventListener("keydown", function (e) {
        if (e.key === "Delete" && deleteIdInput.value) {
            if (confirm("Delete this event? This cannot be undone.")) {
                deleteForm.submit();
            }
        }
    });
});
</script>
';

if (isset($pageScripts)) {
    $pageScripts .= $projectCalendarScripts;
} else {
    $pageScripts = $projectCalendarScripts;
}
?>

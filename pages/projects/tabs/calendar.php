<?php
// Tab: Calendar — receives $pdo, $project, $id, $activeTab from view.php
?>
<!-- Calendar Styles (FullCalendar) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<div class="card shadow-sm mb-3">
    <div class="card-header bg-white d-flex justify-content-between align-items-center">
        <h6 class="mb-0 section-title">Project Calendar</h6>
        <button type="button" class="btn btn-primary btn-sm" id="btnProjectNewEvent">
            <i class="bi bi-plus-lg me-1"></i> New Event
        </button>
    </div>
    <div class="card-body">
        <div id="projectCalendar"></div>
    </div>
</div>

<!-- Project Event Modal -->
<div class="modal fade" id="projectEventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/actions/calendar_actions.php" id="projectEventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="projectEventAction" value="create">
                <input type="hidden" name="id" id="projectEventId" value="">
                <input type="hidden" name="redirect_url" value="<?= BASE_URL ?>/pages/projects/view.php?id=<?= $id ?>&tab=calendar">
                <input type="hidden" name="project_id" value="<?= $id ?>">
                <div class="modal-header">
                    <h6 class="modal-title" id="projectEventModalTitle">New Project Event</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="projectEventTitle" required>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
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
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" name="description" id="projectEventDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_on_main" id="projectEventShowOnMain" checked>
                            <label class="form-check-label" for="projectEventShowOnMain">
                                Also show on main calendar
                            </label>
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Attachments (optional)</label>
                        <input type="file" class="form-control" name="attachments[]" multiple
                               accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                        <input type="text" class="form-control mt-1" name="attachment_description" placeholder="Attachment description (optional)">
                        <div class="form-text small">
                            Select multiple files. Stored under this project and appear in the Files tab.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
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
    const titleInput = document.getElementById("projectEventTitle");
    const dateInput = document.getElementById("projectEventDate");
    const endDateInput = document.getElementById("projectEventEndDate");
    const timeInput = document.getElementById("projectEventTime");
    const allDayInput = document.getElementById("projectEventAllDay");
    const typeInput = document.getElementById("projectEventType");
    const descInput = document.getElementById("projectEventDescription");
    const showOnMainInput = document.getElementById("projectEventShowOnMain");
    const deleteIdInput = document.getElementById("projectDeleteEventId");
    const modalTitle = document.getElementById("projectEventModalTitle");

    function resetForCreate(dateStr) {
        form.reset();
        actionInput.value = "create";
        idInput.value = "";
        modalTitle.textContent = "New Project Event";
        dateInput.value = dateStr || "";
        endDateInput.value = "";
        allDayInput.checked = true;
        showOnMainInput.checked = true;
    }

    function fillForEdit(event) {
        actionInput.value = "update";
        idInput.value = event.id;
        modalTitle.textContent = "Edit Project Event";

        const props = event.extendedProps || {};
        titleInput.value = event.title || "";
        typeInput.value = props.event_type || "other";
        descInput.value = props.description || "";
        if (props.project_id) {
            showOnMainInput.checked = props.show_on_main !== false;
        } else {
            showOnMainInput.checked = true;
        }

        const start = event.start;
        const end = event.end || start;
        if (start) {
            dateInput.value = start.toISOString().slice(0, 10);
        }
        if (end) {
            endDateInput.value = end.toISOString().slice(0, 10);
        }

        if (event.allDay) {
            allDayInput.checked = true;
            timeInput.value = "";
        } else if (start) {
            allDayInput.checked = false;
            timeInput.value = start.toTimeString().slice(0, 5);
        }

        deleteIdInput.value = event.id;
    }

    const calendar = new FullCalendar.Calendar(calendarEl, {
        initialView: "dayGridMonth",
        headerToolbar: {
            left: "prev,next today",
            center: "title",
            right: "dayGridMonth,timeGridWeek"
        },
        firstDay: 1,
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

    modalEl.addEventListener("keydown", function (e) {
        if (e.key === "Delete" && deleteIdInput.value) {
            if (confirm("Delete this event?")) {
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

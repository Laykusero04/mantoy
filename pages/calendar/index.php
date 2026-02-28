<?php
$pageTitle = 'Calendar';
require_once __DIR__ . '/../../includes/header.php';

// Fetch active projects for filters and event association
$projStmt = $pdo->query("
    SELECT id, title
    FROM projects
    WHERE is_deleted = 0 AND is_archived = 0
    ORDER BY title
");
$allProjects = $projStmt->fetchAll();

// Build current URL for redirects after form submit
$currentUrl = BASE_URL . '/pages/calendar/index.php';
?>

<!-- Calendar Styles (FullCalendar) -->
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.css">

<div class="d-flex justify-content-between align-items-center mb-4">
    <div>
        <h4 class="mb-1 fw-bold">Main Calendar</h4>
        <nav aria-label="breadcrumb">
            <ol class="breadcrumb mb-0 small">
                <li class="breadcrumb-item"><a href="<?= BASE_URL ?>/">Dashboard</a></li>
                <li class="breadcrumb-item active">Calendar</li>
            </ol>
        </nav>
    </div>
</div>

<!-- Filters -->
<div class="card shadow-sm mb-3">
    <div class="card-body">
        <div class="row g-2 align-items-end">
            <div class="col-md-4">
                <label class="form-label small mb-1">Project</label>
                <select id="filterProject" class="form-select form-select-sm">
                    <option value="">All (global + projects)</option>
                    <option value="__global__">Global events only</option>
                    <?php foreach ($allProjects as $proj): ?>
                        <option value="<?= (int)$proj['id'] ?>"><?= sanitize($proj['title']) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-3">
                <label class="form-label small mb-1">Type</label>
                <select id="filterType" class="form-select form-select-sm">
                    <option value="">All types</option>
                    <?php
                    $types = ['deadline','milestone','inspection','meeting','activity','reminder','other'];
                    foreach ($types as $type):
                    ?>
                        <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="col-md-2 d-flex justify-content-end">
                <button id="btnNewEvent" type="button" class="btn btn-primary btn-sm mt-3 mt-md-0">
                    <i class="bi bi-plus-lg me-1"></i> New Event
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Calendar -->
<div class="card shadow-sm">
    <div class="card-body">
        <div id="mainCalendar"></div>
    </div>
</div>

<!-- Event Modal -->
<div class="modal fade" id="eventModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">
            <form method="POST" action="<?= BASE_URL ?>/actions/calendar_actions.php" id="eventForm" enctype="multipart/form-data">
                <input type="hidden" name="action" id="eventAction" value="create">
                <input type="hidden" name="id" id="eventId" value="">
                <input type="hidden" name="redirect_url" value="<?= $currentUrl ?>">
                <div class="modal-header">
                    <h6 class="modal-title" id="eventModalTitle">New Event</h6>
                    <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <div class="mb-3">
                        <label class="form-label">Title <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" name="title" id="eventTitle" required>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Project (optional)</label>
                        <select class="form-select" name="project_id" id="eventProject">
                            <option value="">Global (no project)</option>
                            <?php foreach ($allProjects as $proj): ?>
                                <option value="<?= (int)$proj['id'] ?>"><?= sanitize($proj['title']) ?></option>
                            <?php endforeach; ?>
                        </select>
                        <div class="form-text small">
                            Global events always appear on the main calendar.
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Start Date <span class="text-danger">*</span></label>
                            <input type="date" class="form-control" name="event_date" id="eventDate" required>
                        </div>
                        <div class="col-md-6">
                            <label class="form-label">End Date</label>
                            <input type="date" class="form-control" name="event_end_date" id="eventEndDate">
                            <div class="form-text small">Leave blank for single-day event.</div>
                        </div>
                    </div>
                    <div class="row g-2 mb-3">
                        <div class="col-md-6">
                            <label class="form-label">Time</label>
                            <input type="time" class="form-control" name="event_time" id="eventTime">
                        </div>
                        <div class="col-md-6 d-flex align-items-end">
                            <div class="form-check mt-3">
                                <input class="form-check-input" type="checkbox" name="is_all_day" id="eventAllDay" checked>
                                <label class="form-check-label" for="eventAllDay">All day</label>
                            </div>
                        </div>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Type</label>
                        <select class="form-select" name="event_type" id="eventType">
                            <?php foreach ($types as $type): ?>
                                <option value="<?= $type ?>"><?= ucfirst($type) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label class="form-label">Description / Notes</label>
                        <textarea class="form-control" name="description" id="eventDescription" rows="3"></textarea>
                    </div>
                    <div class="mb-3">
                        <div class="form-check">
                            <input class="form-check-input" type="checkbox" name="show_on_main" id="eventShowOnMain" checked>
                            <label class="form-check-label" for="eventShowOnMain">
                                Also show on main calendar
                            </label>
                        </div>
                        <div class="form-text small">
                            Applies only to project events. Global events always appear on the main calendar.
                        </div>
                    </div>
                    <div class="mb-0">
                        <label class="form-label">Attachments (optional)</label>
                        <input type="file" class="form-control" name="attachments[]" multiple
                               accept=".<?= implode(',.', ALLOWED_EXTENSIONS) ?>">
                        <input type="text" class="form-control mt-1" name="attachment_description" placeholder="Attachment description (optional)">
                        <div class="form-text small">
                            Select multiple files. Stored under the selected project's Files tab. If no project is selected, ignored.
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary btn-sm" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary btn-sm">Save</button>
                </div>
            </form>
            <form method="POST" action="<?= BASE_URL ?>/actions/calendar_actions.php" id="deleteEventForm" class="d-none">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" id="deleteEventId" value="">
                <input type="hidden" name="redirect_url" value="<?= $currentUrl ?>">
            </form>
        </div>
    </div>
</div>

<?php
$pageScripts = '
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script>
document.addEventListener("DOMContentLoaded", function () {
    const calendarEl = document.getElementById("mainCalendar");
    const projectFilter = document.getElementById("filterProject");
    const typeFilter = document.getElementById("filterType");
    const btnNewEvent = document.getElementById("btnNewEvent");
    const eventModalEl = document.getElementById("eventModal");
    const eventModal = new bootstrap.Modal(eventModalEl);

    const eventForm = document.getElementById("eventForm");
    const deleteEventForm = document.getElementById("deleteEventForm");

    const eventAction = document.getElementById("eventAction");
    const eventId = document.getElementById("eventId");
    const eventModalTitle = document.getElementById("eventModalTitle");
    const eventTitle = document.getElementById("eventTitle");
    const eventProject = document.getElementById("eventProject");
    const eventDate = document.getElementById("eventDate");
    const eventEndDate = document.getElementById("eventEndDate");
    const eventTime = document.getElementById("eventTime");
    const eventAllDay = document.getElementById("eventAllDay");
    const eventType = document.getElementById("eventType");
    const eventDescription = document.getElementById("eventDescription");
    const eventShowOnMain = document.getElementById("eventShowOnMain");
    const deleteEventId = document.getElementById("deleteEventId");

    function resetFormForCreate(dateStr) {
        eventForm.reset();
        eventAction.value = "create";
        eventId.value = "";
        eventModalTitle.textContent = "New Event";
        eventDate.value = dateStr || "";
        eventEndDate.value = "";
        eventAllDay.checked = true;
        eventShowOnMain.checked = true;
        // default project filter selection when creating from main calendar
        const selectedProject = projectFilter.value;
        if (selectedProject && selectedProject !== "__global__") {
            eventProject.value = selectedProject;
        } else {
            eventProject.value = "";
        }
    }

    function populateFormForEdit(event) {
        eventAction.value = "update";
        eventId.value = event.id;
        eventModalTitle.textContent = "Edit Event";

        const props = event.extendedProps || {};
        eventTitle.value = event.title || "";
        eventProject.value = props.project_id || "";

        const start = event.start;
        const end = event.end || start;

        if (start) {
            eventDate.value = start.toISOString().slice(0, 10);
        }
        if (end) {
            eventEndDate.value = end.toISOString().slice(0, 10);
        }

        if (event.allDay) {
            eventAllDay.checked = true;
            eventTime.value = "";
        } else if (start) {
            eventAllDay.checked = false;
            eventTime.value = start.toTimeString().slice(0, 5);
        }

        eventType.value = props.event_type || "other";
        eventDescription.value = props.description || "";
        if (props.project_id) {
            eventShowOnMain.checked = props.show_on_main !== false;
        } else {
            eventShowOnMain.checked = true;
        }

        deleteEventId.value = event.id;
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
                const projectValue = projectFilter.value;
                let projectId = "";
                let onlyMain = "1";

                if (projectValue === "__global__") {
                    projectId = "";
                    // backend will show only global + show_on_main=1; to get global only, we also filter by project_id IS NULL via type
                    // handled in backend by project_id empty and onlyMain flag; global vs project-only distinction is in query
                } else if (projectValue) {
                    projectId = projectValue;
                    onlyMain = ""; // project view: show all events for that project
                }

                return {
                    project_id: projectId,
                    event_type: typeFilter.value,
                    only_main: onlyMain
                };
            }
        },
        dateClick: function (info) {
            resetFormForCreate(info.dateStr);
            eventModal.show();
        },
        eventClick: function (info) {
            info.jsEvent.preventDefault();
            populateFormForEdit(info.event);
            eventModal.show();
        }
    });

    calendar.render();

    projectFilter.addEventListener("change", function () {
        calendar.refetchEvents();
    });

    typeFilter.addEventListener("change", function () {
        calendar.refetchEvents();
    });

    btnNewEvent.addEventListener("click", function () {
        resetFormForCreate("");
        eventModal.show();
    });

    // Delete support via long-press on Delete key when modal is open
    eventModalEl.addEventListener("keydown", function (e) {
        if (e.key === "Delete" && deleteEventId.value) {
            if (confirm("Delete this event?")) {
                deleteEventForm.submit();
            }
        }
    });
});
</script>
';

require_once __DIR__ . '/../../includes/footer.php';

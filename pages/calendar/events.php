<?php
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/database.php';

header('Content-Type: application/json');

$start = $_GET['start'] ?? null;
$end = $_GET['end'] ?? null;
$projectId = isset($_GET['project_id']) && $_GET['project_id'] !== '' ? (int)$_GET['project_id'] : null;
$eventType = $_GET['event_type'] ?? '';
$onlyMain = isset($_GET['only_main']) && $_GET['only_main'] === '1';

$where = [];
$params = [];

if ($start && $end) {
    $where[] = "(ce.event_date BETWEEN ? AND ? OR (ce.event_end_date IS NOT NULL AND ce.event_end_date BETWEEN ? AND ?))";
    $params[] = $start;
    $params[] = $end;
    $params[] = $start;
    $params[] = $end;
}

if ($projectId) {
    $where[] = "ce.project_id = ?";
    $params[] = $projectId;
} elseif ($onlyMain) {
    // Main calendar: show global events and project events marked for main
    $where[] = "(ce.project_id IS NULL OR ce.show_on_main = 1)";
}

if ($eventType !== '') {
    $where[] = "ce.event_type = ?";
    $params[] = $eventType;
}

if (empty($where)) {
    $whereSql = '1=1';
} else {
    $whereSql = implode(' AND ', $where);
}

// Map event types to default colors if none set
function calendarEventColor($row) {
    if (!empty($row['color'])) {
        return $row['color'];
    }
    $map = [
        'deadline'   => '#dc3545',
        'milestone'  => '#0d6efd',
        'inspection' => '#198754',
        'meeting'    => '#6610f2',
        'activity'   => '#0dcaf0',
        'reminder'   => '#ffc107',
        'other'      => '#6c757d',
    ];
    $type = $row['event_type'] ?? 'other';
    return $map[$type] ?? $map['other'];
}

$sql = "
    SELECT
        ce.*,
        p.title AS project_title
    FROM calendar_events ce
    LEFT JOIN projects p ON ce.project_id = p.id
    WHERE $whereSql
    ORDER BY ce.event_date ASC, ce.event_time ASC
";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$rows = $stmt->fetchAll();

// Fetch all SOW action_logs for these events (one event can have multiple SOW lines)
$sowByEvent = [];
if (!empty($rows)) {
    $eventIds = array_unique(array_column($rows, 'id'));
    $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
    try {
        $sowStmt = $pdo->prepare("
            SELECT al.calendar_event_id, al.budget_item_id, al.quantity, al.unit, al.unit_cost, al.amount, al.quantity_delivered, al.manpower, bi.item_name
            FROM action_logs al
            LEFT JOIN budget_items bi ON bi.id = al.budget_item_id
            WHERE al.calendar_event_id IN ($placeholders) AND al.is_scope_of_work = 1
            ORDER BY al.id
        ");
    } catch (Exception $e) {
        $sowStmt = $pdo->prepare("
            SELECT al.calendar_event_id, al.budget_item_id, al.quantity, al.unit, al.unit_cost, al.amount, al.quantity_delivered, bi.item_name
            FROM action_logs al
            LEFT JOIN budget_items bi ON bi.id = al.budget_item_id
            WHERE al.calendar_event_id IN ($placeholders) AND al.is_scope_of_work = 1
            ORDER BY al.id
        ");
    }
    $sowStmt->execute(array_values($eventIds));
    $manpowerByEvent = [];
    while ($sowRow = $sowStmt->fetch(PDO::FETCH_ASSOC)) {
        $eid = (int)$sowRow['calendar_event_id'];
        if (!isset($sowByEvent[$eid])) $sowByEvent[$eid] = [];
        if (isset($sowRow['manpower']) && $sowRow['manpower'] !== null && !isset($manpowerByEvent[$eid])) {
            $manpowerByEvent[$eid] = (float)$sowRow['manpower'];
        }
        $sowByEvent[$eid][] = [
            'budget_item_id' => (int)$sowRow['budget_item_id'],
            'item_name' => $sowRow['item_name'] ?? '',
            'quantity' => (float)$sowRow['quantity'],
            'unit' => $sowRow['unit'] ?? '',
            'unit_cost' => (float)$sowRow['unit_cost'],
            'amount' => (float)$sowRow['amount'],
            'quantity_delivered' => isset($sowRow['quantity_delivered']) ? (float)$sowRow['quantity_delivered'] : null,
        ];
    }
}
$manpowerByEvent = $manpowerByEvent ?? [];

// Event-linked material lines (requested / delivered) for edit form
$materialLinesByEvent = [];
try {
    $colEv = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'calendar_event_id'");
    $colReq = $pdo->query("SHOW COLUMNS FROM project_material_withdrawals LIKE 'quantity_requested'");
    if ($colEv && $colEv->rowCount() > 0 && $colReq && $colReq->rowCount() > 0 && !empty($rows)) {
        $eventIds = array_unique(array_column($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $matStmt = $pdo->prepare("
            SELECT calendar_event_id, dupa_item_id, quantity_requested, quantity, remarks
            FROM project_material_withdrawals
            WHERE calendar_event_id IN ($placeholders)
            ORDER BY id
        ");
        $matStmt->execute(array_values($eventIds));
        while ($mr = $matStmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int)$mr['calendar_event_id'];
            if (!isset($materialLinesByEvent[$eid])) $materialLinesByEvent[$eid] = [];
            $materialLinesByEvent[$eid][] = [
                'dupa_item_id' => (int)$mr['dupa_item_id'],
                'quantity_requested' => isset($mr['quantity_requested']) && $mr['quantity_requested'] !== null ? (float)$mr['quantity_requested'] : null,
                'quantity' => (float)$mr['quantity'],
                'remarks' => $mr['remarks'] ?? '',
            ];
        }
    }
} catch (Exception $e) {}

// Attachments (photos) linked to each event (daily log)
$photosByEvent = [];
try {
    $colModule = $pdo->query("SHOW COLUMNS FROM attachments LIKE 'module_type'");
    $colRecord = $pdo->query("SHOW COLUMNS FROM attachments LIKE 'module_record_id'");
    if ($colModule && $colModule->rowCount() > 0 && $colRecord && $colRecord->rowCount() > 0 && !empty($rows)) {
        $eventIds = array_unique(array_column($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $photoStmt = $pdo->prepare("
            SELECT id, project_id, module_record_id, file_name, file_original_name
            FROM attachments
            WHERE module_type = 'daily_log' AND module_record_id IN ($placeholders)
            ORDER BY id
        ");
        $photoStmt->execute(array_values($eventIds));
        while ($att = $photoStmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int)$att['module_record_id'];
            if (!isset($photosByEvent[$eid])) $photosByEvent[$eid] = [];
            $photosByEvent[$eid][] = [
                'id' => (int)$att['id'],
                'file_name' => $att['file_name'],
                'file_original_name' => $att['file_original_name'] ?? $att['file_name'],
                'url' => BASE_URL . '/uploads/' . (int)$att['project_id'] . '/' . $att['file_name'],
            ];
        }
    }
} catch (Exception $e) {}

// Daily log ↔ SOW tasks: tasks worked on each event
$taskLinesByEvent = [];
try {
    $chk = $pdo->query("SHOW TABLES LIKE 'daily_log_tasks'");
    if ($chk && $chk->rowCount() > 0 && !empty($rows)) {
        $eventIds = array_unique(array_column($rows, 'id'));
        $placeholders = implode(',', array_fill(0, count($eventIds), '?'));
        $taskStmt = $pdo->prepare("
            SELECT d.calendar_event_id, d.sow_task_id, d.quantity_done, d.duration_days_done,
                   s.code, s.description AS task_description
            FROM daily_log_tasks d
            LEFT JOIN sow_tasks s ON s.id = d.sow_task_id
            WHERE d.calendar_event_id IN ($placeholders)
            ORDER BY d.id
        ");
        $taskStmt->execute(array_values($eventIds));
        while ($tr = $taskStmt->fetch(PDO::FETCH_ASSOC)) {
            $eid = (int)$tr['calendar_event_id'];
            if (!isset($taskLinesByEvent[$eid])) $taskLinesByEvent[$eid] = [];
            $taskLinesByEvent[$eid][] = [
                'sow_task_id' => (int)$tr['sow_task_id'],
                'quantity_done' => isset($tr['quantity_done']) && $tr['quantity_done'] !== null ? (float)$tr['quantity_done'] : null,
                'duration_days_done' => isset($tr['duration_days_done']) && $tr['duration_days_done'] !== null ? (float)$tr['duration_days_done'] : null,
                'code' => $tr['code'] ?? '',
                'task_description' => $tr['task_description'] ?? '',
            ];
        }
    }
} catch (Exception $e) {}

$events = [];

foreach ($rows as $row) {
    $date = $row['event_date'];
    $endDate = $row['event_end_date'] ?: $row['event_date'];
    $time = $row['event_time'];
    $isAllDay = (bool)$row['is_all_day'];

    // All-day: use local midnight (no Z) so the client does not shift dates by timezone; end is exclusive (next day)
    if ($isAllDay) {
        $startIso = $date . 'T00:00:00';
        $endDateExclusive = date('Y-m-d', strtotime($endDate . ' +1 day'));
        $endIso = $endDateExclusive . 'T00:00:00';
    } else {
        $startIso = $date;
        $endIso = $endDate;
        if ($time) {
            $startIso .= 'T' . $time;
            $endIso .= 'T' . $time;
        }
    }

    $color = calendarEventColor($row);

    // Main calendar: show project name instead of event title when event has a project (so project is visible at a glance)
    $displayTitle = $row['title'];
    if ($onlyMain && !empty($row['project_id']) && !empty($row['project_title'])) {
        $displayTitle = $row['project_title'];
    }

    $events[] = [
        'id' => (int)$row['id'],
        'title' => $displayTitle,
        'start' => $startIso,
        'end' => $endIso,
        'allDay' => $isAllDay,
        'color' => $color,
        'extendedProps' => [
            'description' => $row['description'],
            'event_type' => $row['event_type'],
            'project_id' => $row['project_id'],
            'project_title' => $row['project_title'],
            'event_title' => $row['title'],
            'show_on_main' => isset($row['show_on_main']) ? (bool)$row['show_on_main'] : true,
            'daily_cost' => isset($row['daily_cost']) ? (float)$row['daily_cost'] : null,
            'actual_accomplishment' => $row['actual_accomplishment'] ?? '',
            'remaining_work' => $row['remaining_work'] ?? '',
            'weather' => isset($row['weather']) ? $row['weather'] : '',
            'temperature' => isset($row['temperature']) && $row['temperature'] !== null ? (float)$row['temperature'] : null,
            'workers_count' => isset($row['workers_count']) && $row['workers_count'] !== null ? (int)$row['workers_count'] : null,
            'equipment_used' => isset($row['equipment_used']) ? $row['equipment_used'] : '',
            'issues' => isset($row['issues']) ? $row['issues'] : '',
            'sow_lines' => $sowByEvent[(int)$row['id']] ?? [],
            'manpower' => isset($manpowerByEvent[(int)$row['id']]) ? $manpowerByEvent[(int)$row['id']] : null,
            'material_lines' => $materialLinesByEvent[(int)$row['id']] ?? [],
            'photos' => $photosByEvent[(int)$row['id']] ?? [],
            'task_lines' => $taskLinesByEvent[(int)$row['id']] ?? [],
        ],
    ];
}

echo json_encode($events);

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

$events = [];

foreach ($rows as $row) {
    $date = $row['event_date'];
    $endDate = $row['event_end_date'] ?: $row['event_date'];
    $time = $row['event_time'];
    $isAllDay = (bool)$row['is_all_day'];

    $startIso = $date;
    $endIso = $endDate;

    if (!$isAllDay && $time) {
        $startIso .= 'T' . $time;
        $endIso .= 'T' . $time;
    }

    $color = calendarEventColor($row);

    $events[] = [
        'id' => (int)$row['id'],
        'title' => $row['title'],
        'start' => $startIso,
        'end' => $endIso,
        'allDay' => $isAllDay,
        'color' => $color,
        'extendedProps' => [
            'description' => $row['description'],
            'event_type' => $row['event_type'],
            'project_id' => $row['project_id'],
            'project_title' => $row['project_title'],
            'show_on_main' => isset($row['show_on_main']) ? (bool)$row['show_on_main'] : true,
        ],
    ];
}

echo json_encode($events);

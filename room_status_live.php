<?php
require_once 'config.php';
header('Content-Type: application/json');

$today   = date('l');      // e.g. "Monday"
$nowTime = date('H:i:s'); // e.g. "10:30:00"

// Map today's full day name to the day_of_week string stored in schedules
$dayMap = [
    'Monday'    => 'Monday and Thursday',
    'Thursday'  => 'Monday and Thursday',
    'Tuesday'   => 'Tuesday and Wednesday',
    'Wednesday' => 'Tuesday and Wednesday',
];
$schedDay = $dayMap[$today] ?? null;

$result = [];

// ── 1. Fetch all comlab rooms ─────────────────────────────────────────
$roomsRes = $conn->query(
    "SELECT id, room_name, status FROM rooms WHERE room_name LIKE 'Comlab %' ORDER BY room_name ASC"
);
if (!$roomsRes instanceof mysqli_result) {
    echo json_encode($result);
    exit;
}
$roomRows = $roomsRes->fetch_all(MYSQLI_ASSOC);

// ── 2. Fetch rooms that have an active schedule RIGHT NOW ─────────────
$busyRooms = [];
if ($schedDay) {
    $schedSql = "SELECT s.room_id, s.subject, s.section, s.time_end
                 FROM schedules s
                 WHERE s.day_of_week = ?
                   AND s.status <> 'cancelled'
                   AND ? BETWEEN s.time_start AND s.time_end";
    $stmt = $conn->prepare($schedSql);
    if ($stmt) {
        $stmt->bind_param('ss', $schedDay, $nowTime);
        $stmt->execute();
        $schedRows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt->close();

        foreach ($schedRows as $sched) {
            $rid = (int)$sched['room_id'];
            $busyRooms[$rid] = [
                'subject'  => $sched['subject'],
                'section'  => $sched['section'],
                'time_end' => $sched['time_end'] ? date('g:i A', strtotime($sched['time_end'])) : null,
            ];
        }
    }
}

// ── 3. Build result — manual DB status always takes priority ──────────
foreach ($roomRows as $room) {
    $rid    = (int)$room['id'];
    $status = $room['status'] ?? 'available'; // available | occupied | out_of_service

    if ($status === 'out_of_service') {
        // Admin manually set out of service — always grey
        $result[$rid] = [
            'live_status' => 'oos',
            'subject'     => null,
            'section'     => null,
            'time_end'    => null,
        ];

    } elseif ($status === 'occupied') {
        // Admin manually set occupied — always red, regardless of schedule
        $result[$rid] = [
            'live_status' => 'occupied',
            'subject'     => $busyRooms[$rid]['subject'] ?? null,
            'section'     => $busyRooms[$rid]['section'] ?? null,
            'time_end'    => $busyRooms[$rid]['time_end'] ?? null,
        ];

    } elseif (isset($busyRooms[$rid])) {
        // Available in DB but a schedule is running right now — show red
        $result[$rid] = [
            'live_status' => 'occupied',
            'subject'     => $busyRooms[$rid]['subject'],
            'section'     => $busyRooms[$rid]['section'],
            'time_end'    => $busyRooms[$rid]['time_end'],
        ];

    } else {
        // Truly free — no manual override, no active schedule
        $result[$rid] = [
            'live_status' => 'free',
            'subject'     => null,
            'section'     => null,
            'time_end'    => null,
        ];
    }
}

echo json_encode($result);
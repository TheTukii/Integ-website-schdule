<?php
session_start();
require_once 'config.php';

$isLoggedIn = isset($_SESSION['user_id'], $_SESSION['user_role']);
$role = $isLoggedIn ? $_SESSION['user_role'] : 'student';
$canManageSchedules = in_array($role, ['admin', 'instructor'], true);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$userName = $_SESSION['user_name'] ?? 'Guest Student';
$dashboardCssPath = __DIR__ . '/includes/css/user-dashboard-css.css';
$comlabCssPath = __DIR__ . '/includes/css/comlab-map-css.css';
$dashboardCssInline = is_file($dashboardCssPath) ? file_get_contents($dashboardCssPath) : '';
$comlabCssInline = is_file($comlabCssPath) ? file_get_contents($comlabCssPath) : '';

$selectedRoomId = isset($_GET['room_id']) ? (int) $_GET['room_id'] : 0;
$selectedBuilding = isset($_GET['building']) ? $_GET['building'] : null;
$flashMessage = $_SESSION['schedule_flash'] ?? '';
$flashType = $_SESSION['schedule_flash_type'] ?? 'success';
unset($_SESSION['schedule_flash'], $_SESSION['schedule_flash_type']);

$days = ['Monday and Thursday', 'Tuesday and Wednesday'];
$today = date('l');

// ── Building definitions ──────────────────────────────────────────────
$buildings = [
    'highschool' => [
        'key'         => 'highschool',
        'name'        => 'High School Building',
        'short'       => 'HS Building',
        'description' => 'Computer laboratories on the lower floor',
        'icon'        => 'bi-building',
        'rooms'       => ['Comlab 8', 'Comlab 9', 'Comlab 10'],
        'color'       => '#1a6b9a',
        'pale'        => '#e8f4fb',
    ],
    'finance' => [
        'key'         => 'finance',
        'name'        => 'Finance Building',
        'short'       => 'Finance Bldg',
        'description' => 'Main computer laboratory on 3rd floor',
        'icon'        => 'bi-bank',
        'rooms'       => ['Comlab 1','Comlab 2','Comlab 3','Comlab 4','Comlab 5','Comlab 6','Comlab 7','Comlab 11','Comlab 12'],
        'color'       => '#1a7a5e',
        'pale'        => '#e8f8f4',
    ],
];

// Room layout per building (positions as % within their floor map)
$roomLayouts = [
    'highschool' => [
        ['label' => 'Comlab 8',  'style' => 'left:15%;top:15%;width:28%;height:28%;'],
        ['label' => 'Comlab 9',  'style' => 'left:15%;top:50%;width:28%;height:28%;'],
        ['label' => 'Comlab 10', 'style' => 'left:57%;top:15%;width:28%;height:63%;'],
    ],
    'finance' => [
        ['label' => 'Comlab 1',  'style' => 'left:71.5%;top:19%;width:11%;height:17%;'],
        ['label' => 'Comlab 2',  'style' => 'left:71.5%;top:36%;width:11%;height:17%;'],
        ['label' => 'Comlab 3',  'style' => 'left:71.5%;top:53%;width:11%;height:17%;'],
        ['label' => 'Comlab 4',  'style' => 'left:70%;top:84%;width:11%;height:14%;'],
        ['label' => 'Comlab 5',  'style' => 'left:61%;top:84%;width:9%;height:14%;'],
        ['label' => 'Comlab 6',  'style' => 'left:52%;top:84%;width:9%;height:14%;'],
        ['label' => 'Comlab 7',  'style' => 'left:43%;top:84%;width:9%;height:14%;'],
        ['label' => 'Comlab 11', 'style' => 'left:8.5%;top:84%;width:10%;height:14%;'],
        ['label' => 'Comlab 12', 'style' => 'left:8.5%;top:70%;width:10%;height:14%;'],
    ],
];

// ── Fetch rooms from DB ───────────────────────────────────────────────
$rooms = [];
$roomsByLabel = [];
$sqlRooms = "SELECT id, room_code, room_name, status, capacity FROM rooms WHERE room_name LIKE 'Comlab %' OR room_code LIKE 'COMLAB%' ORDER BY room_name ASC";
$roomResult = $conn->query($sqlRooms);
if ($roomResult instanceof mysqli_result) {
    while ($row = $roomResult->fetch_assoc()) {
        $label = trim($row['room_name']) !== '' ? $row['room_name'] : $row['room_code'];
        $row['display_label'] = $label;
        $rooms[] = $row;
        $roomsByLabel[strtolower($label)] = $row;
    }
}

// Auto-seed rooms if DB is empty
if (count($rooms) === 0) {
    $financeId = 0;
    $hsId = 0;
    
    $res = $conn->query("SELECT id, code FROM buildings WHERE code IN ('FINANCE', 'HS')");
    if ($res instanceof mysqli_result) {
        while ($row = $res->fetch_assoc()) {
            if ($row['code'] === 'FINANCE') $financeId = (int)$row['id'];
            if ($row['code'] === 'HS') $hsId = (int)$row['id'];
        }
    }
    
    if ($financeId === 0) {
        $conn->query("INSERT INTO buildings (code, name, description) VALUES ('FINANCE', 'Finance Building', 'Main academic classrooms')");
        $financeId = (int)$conn->insert_id;
    }
    if ($hsId === 0) {
        $conn->query("INSERT INTO buildings (code, name, description) VALUES ('HS', 'High School Building', 'Contains three additional rooms')");
        $hsId = (int)$conn->insert_id;
    }
    
    $insertRoom = $conn->prepare("INSERT INTO rooms (building_id, room_code, room_name, capacity, status, description) VALUES (?, ?, ?, 40, 'available', 'Auto-generated comlab')");
    if ($insertRoom) {
        for ($i = 1; $i <= 12; $i++) {
            $bId = in_array($i, [8, 9, 10]) ? $hsId : $financeId;
            $roomCode = 'COMLAB' . $i;
            $roomName = 'Comlab ' . $i;
            $insertRoom->bind_param('iss', $bId, $roomCode, $roomName);
            $insertRoom->execute();
        }
        $insertRoom->close();
    }
    $roomResult = $conn->query($sqlRooms);
    if ($roomResult instanceof mysqli_result) {
        while ($row = $roomResult->fetch_assoc()) {
            $label = trim($row['room_name']) !== '' ? $row['room_name'] : $row['room_code'];
            $row['display_label'] = $label;
            $rooms[] = $row;
            $roomsByLabel[strtolower($label)] = $row;
        }
    }
}

$roomById = [];
foreach ($roomsByLabel as $lbl => $rd) {
    $roomById[(int)$rd['id']] = $rd;
}

// ── Determine current view ────────────────────────────────────────────
if ($selectedRoomId > 0 && isset($roomById[$selectedRoomId]) && $selectedBuilding === null) {
    $rName = $roomById[$selectedRoomId]['display_label'];
    foreach ($buildings as $bKey => $bDef) {
        if (in_array($rName, $bDef['rooms'], true)) {
            $selectedBuilding = $bKey;
            break;
        }
    }
}

$currentBuildingDef = $selectedBuilding ? ($buildings[$selectedBuilding] ?? null) : null;
$currentLayout      = $selectedBuilding ? ($roomLayouts[$selectedBuilding] ?? []) : [];

// ── Selected room & its schedules ────────────────────────────────────
$selectedRoom = null;
if ($selectedRoomId > 0 && isset($roomById[$selectedRoomId])) {
    $selectedRoom = $roomById[$selectedRoomId];
} elseif ($selectedBuilding && !empty($currentLayout)) {
    $firstLabel = strtolower($currentLayout[0]['label']);
    if (isset($roomsByLabel[$firstLabel])) {
        $selectedRoom = $roomsByLabel[$firstLabel];
        $selectedRoomId = (int)$selectedRoom['id'];
    }
}

$selectedSchedule = [];
if ($selectedRoomId > 0) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.subject, s.section, s.day_of_week, s.time_start, s.time_end, s.status,
                u.name AS instructor_name, s.instructor_id
         FROM schedules s
         INNER JOIN users u ON u.id = s.instructor_id
         WHERE s.room_id = ?
         ORDER BY FIELD(s.day_of_week,'Monday and Thursday','Tuesday and Wednesday'), s.time_start"
    );
    $stmt->bind_param('i', $selectedRoomId);
    $stmt->execute();
    $selectedSchedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

// ── Live availability (today) — sidebar left ──────────────────────────
$todayRooms = [];
$todaySql = "SELECT r.id, r.room_name, r.status,
             EXISTS(SELECT 1 FROM schedules s WHERE s.room_id = r.id
               AND s.day_of_week = ? AND CURTIME() BETWEEN s.time_start AND s.time_end
               AND s.status <> 'cancelled') AS is_busy
             FROM rooms r WHERE r.room_name LIKE 'Comlab %' ORDER BY r.room_name ASC";
$todayStmt = $conn->prepare($todaySql);
if ($todayStmt) {
    $todayStmt->bind_param('s', $today);
    $todayStmt->execute();
    $todayRooms = $todayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $todayStmt->close();
}

// ── Schedule counts per room (for badges) ────────────────────────────
$schedCountMap = [];
$schedCountRes = $conn->query("SELECT room_id, COUNT(*) as cnt FROM schedules WHERE status <> 'cancelled' GROUP BY room_id");
if ($schedCountRes instanceof mysqli_result) {
    while ($scr = $schedCountRes->fetch_assoc()) {
        $schedCountMap[(int)$scr['room_id']] = (int)$scr['cnt'];
    }
}

// ── Building schedule counts ──────────────────────────────────────────
$buildingSchedCount = ['highschool' => 0, 'finance' => 0];
foreach ($buildings as $bKey => $bDef) {
    foreach ($bDef['rooms'] as $rName) {
        $rData = $roomsByLabel[strtolower($rName)] ?? null;
        if ($rData) {
            $buildingSchedCount[$bKey] += $schedCountMap[(int)$rData['id']] ?? 0;
        }
    }
}

// ── Instructor list (admin) ───────────────────────────────────────────
$instructors = [];
if ($role === 'admin') {
    $insRes = $conn->query("SELECT id, name FROM users WHERE role IN ('instructor','admin') ORDER BY name ASC");
    if ($insRes instanceof mysqli_result) {
        while ($r = $insRes->fetch_assoc()) $instructors[] = $r;
    }
}

// ── All schedules JSON for map search ────────────────────────────────
$allSchedRaw = $conn->query(
    "SELECT s.subject, s.section, u.name AS instructor_name, r.id AS room_id
     FROM schedules s
     INNER JOIN users u ON u.id = s.instructor_id
     INNER JOIN rooms r ON r.id = s.room_id
     WHERE r.room_name LIKE 'Comlab %' AND s.status <> 'cancelled'"
);
$allSchedulesForSearch = [];
if ($allSchedRaw instanceof mysqli_result) {
    while ($sr = $allSchedRaw->fetch_assoc()) {
        $rid = (int)$sr['room_id'];
        $allSchedulesForSearch[$rid][] = strtolower($sr['subject'].' '.$sr['section'].' '.$sr['instructor_name']);
    }
}
$allSchedulesJson = json_encode($allSchedulesForSearch);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Comlab Scheduling Map</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <?php if ($dashboardCssInline !== ''): ?>
    <style><?= $dashboardCssInline ?></style>
  <?php endif; ?>
  <?php if ($comlabCssInline !== ''): ?>
    <style><?= $comlabCssInline ?></style>
  <?php endif; ?>
  <style>
    body { overflow: hidden; }
    body.page-comlab-map { --sidebar-w: 220px; --right-w: 320px; }

    /* ── Sidebar left nav ── */
    .sidebar-live-region {
      border: 1px solid var(--border,#C8DFF0);
      border-radius: 10px;
      background: var(--navy-pale,#F4F8FD);
      padding: .75rem .65rem;
    }
    .panel-title-live {
      font-size: 11px; font-weight: 600; letter-spacing: .06em;
      text-transform: uppercase; color: var(--muted,#5A7A96); margin-bottom: .5rem;
    }
    .sidebar-live-caption { font-size: 11px; color: var(--muted,#5A7A96); margin-bottom: .5rem; }
    .sidebar-live-scroll {
      max-height: min(32vh,260px); overflow-y: auto; overscroll-behavior: contain;
      display: flex; flex-direction: column; gap: 4px; padding-right: 2px;
    }
    .live-availability-row {
      display: flex; align-items: center; justify-content: space-between; gap: 8px;
      padding: 7px 8px; font-size: 12px; border-radius: 8px;
      background: var(--white,#fff); border: 1px solid var(--border,#C8DFF0);
    }
    .live-room-name { color: var(--text,#0D1B2A); font-weight: 500; }
    .live-status-pair { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .live-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .live-status-dot.live-free { background: #1D9E75; }
    .live-status-dot.live-busy { background: #E24B4A; }
    .live-status-dot.live-out  { background: #94a3b8; }
    .live-status-label { font-size: 11px; font-weight: 600; color: var(--muted,#5A7A96); text-align: right; }

    /* ── Topbar search ── */
    .topbar-search-wrap { flex: 1; display: flex; align-items: center; max-width: 340px; }
    .topbar-search-box {
      display: flex; align-items: center; gap: 7px; width: 100%;
      border: 1px solid var(--border,#C8DFF0); border-radius: 20px;
      padding: 5px 12px; background: var(--navy-pale,#F4F8FD); transition: border-color .15s;
    }
    .topbar-search-box:focus-within { border-color: var(--accent,#378ADD); }
    .topbar-search-box input {
      border: none; background: transparent; font-size: 13px;
      font-family: 'DM Sans',sans-serif; color: var(--text,#0D1B2A); outline: none; flex: 1;
    }
    .topbar-search-box input::placeholder { color: var(--muted,#5A7A96); }
    .map-search-clear {
      border: none; background: transparent; color: var(--muted,#5A7A96);
      cursor: pointer; padding: 0; line-height: 1; display: flex; align-items: center; font-size: 15px;
    }
    .map-search-clear:hover { color: var(--text,#0D1B2A); }

    /* ═══════════════════════════════════════════
       LEVEL 1 — Campus view (full-bleed map + overlay cards)
    ═══════════════════════════════════════════ */
    .campus-view {
      flex: 1; display: flex; flex-direction: column;
      align-items: stretch; justify-content: stretch;
      padding: 0; gap: 0; overflow: hidden;
      position: relative;
    }

    /* Full-bleed map background */
    .campus-map-section {
      position: absolute;
      inset: 0;
      z-index: 0;
      background: #042C53;
      overflow: hidden;
    }
    .campus-map-section svg {
      display: block;
      width: 100%;
      height: 100%;
      preserveAspectRatio: xMidYMid slice;
    }

    /* Dark gradient overlay so cards are readable */
    .campus-map-overlay {
      position: absolute;
      inset: 0;
      z-index: 1;
      background: linear-gradient(
        to bottom,
        rgba(4,44,83,0.18) 0%,
        rgba(4,44,83,0.52) 60%,
        rgba(4,44,83,0.72) 100%
      );
      pointer-events: none;
    }

    /* Content layer on top of map */
    .campus-overlay-content {
      position: absolute;
      inset: 0;
      z-index: 2;
      display: flex;
      flex-direction: column;
      align-items: center;
      justify-content: flex-start;
      padding: 1rem 1.25rem 1.5rem;
      gap: 0.85rem;
    }

    .campus-heading { text-align: center; flex-shrink: 0; }
    .campus-heading h2 { font-size: 1.15rem; font-weight: 700; color: #fff; margin: 0 0 4px; text-shadow: 0 1px 6px rgba(0,0,0,.35); }
    .campus-heading p  { font-size: 12px; color: rgba(255,255,255,0.78); margin: 0; }

    .campus-buildings-grid {
      display: flex; gap: 1.25rem; flex-wrap: wrap;
      justify-content: center; width: 100%; max-width: 100%;
      flex-shrink: 0;
    }

    /* ── Second mini map card (inside overlay) ── */
    .campus-minimap-card {
      width: 100%;
      max-width: 100%;
      flex: 1;
      min-height: 0;
      border-radius: 14px;
      overflow: hidden;
      border: 1.5px solid rgba(255,255,255,0.45);
      background: rgba(255,255,255,0.10);
      backdrop-filter: blur(6px);
      -webkit-backdrop-filter: blur(6px);
      box-shadow: 0 8px 32px rgba(4,44,83,0.28);
      display: flex;
      flex-direction: column;
    }
    .campus-minimap-header {
      display: flex;
      align-items: center;
      gap: 6px;
      padding: 8px 14px;
      font-size: 11px;
      font-weight: 700;
      letter-spacing: .06em;
      text-transform: uppercase;
      color: rgba(255,255,255,0.85);
      background: rgba(4,44,83,0.25);
      border-bottom: 1px solid rgba(255,255,255,0.15);
      flex-shrink: 0;
    }
    .campus-minimap-body {
      flex: 1;
      min-height: 0;
      overflow: hidden;
    }
    .building-card {
      flex: 1; min-width: 240px; max-width: 300px;
      background: rgba(255,255,255,0.93);
      backdrop-filter: blur(8px);
      -webkit-backdrop-filter: blur(8px);
      border: 1.5px solid rgba(255,255,255,0.7);
      border-radius: 14px; padding: 1.5rem 1.25rem;
      text-decoration: none; color: inherit;
      display: flex; flex-direction: column; gap: 12px;
      transition: transform .18s, box-shadow .18s, border-color .18s, background .18s;
      cursor: pointer; position: relative; overflow: hidden;
      box-shadow: 0 8px 32px rgba(4,44,83,0.22);
    }
    .building-card::before {
      content: ''; position: absolute; top: 0; left: 0; right: 0;
      height: 4px; border-radius: 14px 14px 0 0;
    }
    .building-card.hs-card::before  { background: #1a6b9a; }
    .building-card.fin-card::before { background: #1a7a5e; }
    .building-card.hs-card {
      background: linear-gradient(135deg, #daeef9 0%, #c5e3f4 100%);
      border-color: rgba(26,107,154,0.35);
    }
    .building-card.fin-card {
      background: linear-gradient(135deg, #d4f0e8 0%, #bce5d8 100%);
      border-color: rgba(26,122,94,0.35);
    }
    .building-card:hover {
      transform: translateY(-3px); box-shadow: 0 8px 28px rgba(4,44,83,.13);
      border-color: var(--accent,#378ADD); text-decoration: none; color: inherit;
    }
    .building-card.hs-card:hover  { border-color: #1a6b9a; }
    .building-card.fin-card:hover { border-color: #1a7a5e; }
    .building-card-icon {
      width: 44px; height: 44px; border-radius: 12px;
      display: flex; align-items: center; justify-content: center; font-size: 22px;
    }
    .hs-card  .building-card-icon { background: #e8f4fb; color: #1a6b9a; }
    .fin-card .building-card-icon { background: #e8f8f4; color: #1a7a5e; }
    .building-card-body { flex: 1; }
    .building-card-name { font-size: 15px; font-weight: 700; color: var(--navy,#042C53); margin: 0 0 3px; }
    .building-card-desc { font-size: 12px; color: var(--muted,#5A7A96); margin: 0; }
    .building-card-meta { display: flex; gap: 8px; flex-wrap: wrap; }
    .building-meta-chip {
      font-size: 11px; font-weight: 600; padding: 3px 9px;
      border-radius: 20px; background: var(--navy-pale,#F4F8FD);
      color: var(--navy,#042C53); border: 1px solid var(--border,#C8DFF0);
    }
    .building-card-arrow {
      position: absolute; right: 1.1rem; top: 50%; transform: translateY(-50%);
      font-size: 18px; color: var(--border,#C8DFF0); transition: color .15s, transform .15s;
    }
    .building-card:hover .building-card-arrow {
      color: var(--accent,#378ADD); transform: translateY(-50%) translateX(3px);
    }

    /* ═══════════════════════════════════════════
       LEVEL 2 — Building floor view
    ═══════════════════════════════════════════ */
    .floor-view {
      flex: 1; display: flex; flex-direction: column;
      overflow: hidden; padding: .75rem 1rem; gap: .65rem;
    }
    .floor-breadcrumb {
      display: flex; align-items: center; gap: 8px; font-size: 12px; flex-shrink: 0;
    }
    .floor-breadcrumb a {
      color: var(--accent,#378ADD); text-decoration: none; font-weight: 600;
      display: flex; align-items: center; gap: 4px;
    }
    .floor-breadcrumb a:hover { text-decoration: underline; }
    .floor-breadcrumb .bc-sep { color: var(--muted,#5A7A96); }
    .floor-breadcrumb .bc-current { color: var(--navy,#042C53); font-weight: 700; }

    /* Map container */
    .comlab-map-container {
      flex: 1; display: flex; flex-direction: column; gap: 8px;
      overflow: hidden; min-height: 0;
    }
    .map-label {
      font-size: 11px; font-weight: 700; letter-spacing: .06em;
      text-transform: uppercase; color: var(--muted,#5A7A96); flex-shrink: 0;
    }
    .comlab-map-grid {
      flex: 1; position: relative;
      background: #d9f0f0; border: 2px solid #173049;
      border-radius: 8px; padding: 10px; overflow: hidden; min-height: 0;
    }
    .map-grid-hs { background: #ddf0ea; }

    /* ── Tile base ── */
    .comlab-tile {
      position: absolute; border: 2px solid #173049; border-radius: 6px;
      color: #0d1b2a; text-decoration: none; font-size: 13px; font-weight: 600;
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; text-align: center; padding: 4px 6px; gap: 2px;
      transition: transform .12s, box-shadow .12s, opacity .2s, filter .2s;
      z-index: 3;
    }
    .comlab-tile:hover { transform: translateY(-2px); box-shadow: 0 4px 14px rgba(4,44,83,.18); }
    .comlab-tile.active { outline: 3px solid #185fa5; outline-offset: 1px; }

    .status-free { background: #e7f8f1; }
    .status-busy { background: #fce9e9; }
    .status-out  { background: #eee; color: #737373; }

    .tile-dim   { opacity: .28; filter: grayscale(60%); }
    .tile-match { opacity: 1; filter: none; box-shadow: 0 0 0 3px #378ADD, 0 4px 16px rgba(55,138,221,.35); z-index: 10; }

    .tile-sched-badge {
      font-size: 10px; font-weight: 700;
      background: rgba(4,44,83,.12); border-radius: 10px;
      padding: 1px 6px; line-height: 1.4;
    }
    .tile-live-label {
      font-size: 9px; font-weight: 700; opacity: .75;
      line-height: 1.2; text-align: center;
      max-width: 100%; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }

    /* ── Map legend + live refresh bar ── */
    .map-legend-wrap {
      display: flex; align-items: center; flex-wrap: wrap; gap: 10px;
      justify-content: space-between; flex-shrink: 0;
    }
    .map-legend {
      display: flex; gap: 14px; align-items: center;
      font-size: 12px; color: #5a7a96; font-weight: 600;
    }
    .legend-dot {
      width: 10px; height: 10px; border-radius: 50%;
      display: inline-block; margin-right: 5px; border: 1px solid #17304933;
    }
    .legend-available { background: #e7f8f1; }
    .legend-occupied  { background: #fce9e9; }
    .legend-out       { background: #eee; }

    .live-refresh-bar {
      display: flex; align-items: center; gap: 6px;
      font-size: 11px; color: var(--muted,#5A7A96); flex-shrink: 0;
    }
    .live-refresh-pulse {
      width: 8px; height: 8px; border-radius: 50%; background: #1D9E75;
      animation: livePulse 2s infinite; flex-shrink: 0;
    }
    @keyframes livePulse {
      0%,100% { opacity: 1; }
      50%      { opacity: .2; }
    }

    .center-walkway {
      position: absolute; border: 2px solid #173049;
      background: #f3f3f3; border-radius: 4px;
      font-weight: 600; color: #5a7a96;
      display: flex; align-items: center; justify-content: center;
      font-size: 12px; z-index: 1;
    }

    /* ── Right sidebar ── */
    .sidebar-right { padding: .75rem .85rem; gap: .6rem; }
    .sidebar-right-inner { display: flex; flex-direction: column; gap: .6rem; }
    .panel-title { margin-bottom: 3px; }
    .schedule-item { padding: 6px 10px; }
    .full-room-schedule-list { max-height: 26vh !important; overflow-y: auto; }
    .schedule-form-stack .form-label,
    .schedule-form .form-label { font-size: 11px; margin-bottom: 1px; }
    .admin-room-status-form .form-select { min-width: 0; flex: 1 1 120px; }

    /* Campus-mode hides right sidebar */
    .campus-mode .sidebar-right { display: none !important; }

    /* ── Per-building floor-view backgrounds ── */
    body.building-highschool .floor-view {
      background: linear-gradient(135deg, #daeef9 0%, #c5e3f4 100%);
    }
    body.building-finance .floor-view {
      background: linear-gradient(135deg, #d4f0e8 0%, #bce5d8 100%);
    }
  </style>
</head>
<body class="page-comlab-map <?= $selectedBuilding ? 'building-' . htmlspecialchars($selectedBuilding) : 'campus-mode' ?>">

  <!-- ── Topbar ── -->
  <header class="topbar">
    <a class="topbar-brand" href="comlab-map.php">
      <div class="logo-mark">Bu</div>
      BukSU Rooms
    </a>

    <?php if ($selectedBuilding): ?>
    <div class="topbar-search-wrap">
      <div class="topbar-search-box">
        <i class="bi bi-search" style="font-size:13px;color:var(--muted);"></i>
        <input type="text" id="mapSearch" placeholder="Search section, subject, or instructor…" autocomplete="off">
        <button type="button" id="mapSearchClear" class="map-search-clear" style="display:none;" aria-label="Clear search">
          <i class="bi bi-x"></i>
        </button>
      </div>
    </div>
    <?php endif; ?>

    <div class="topbar-stats">
      <div class="stat-chip">
        <span class="dot dot-green"></span>
        <strong><?= count($roomById) ?></strong> Comlabs
      </div>
      <div class="stat-chip">
        <span class="dot dot-blue"></span>
        <strong><?= htmlspecialchars($today) ?></strong>
      </div>
    </div>

    <div class="topbar-user">
      <div class="user-meta">
        <div class="name"><?= htmlspecialchars($userName) ?></div>
        <div class="role"><?= ucfirst(htmlspecialchars($role)) ?></div>
      </div>
      <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
      <?php if ($isLoggedIn): ?>
        <a class="btn btn-sm btn-outline-primary" href="logout.php">Logout</a>
      <?php else: ?>
        <a class="btn btn-sm btn-primary" href="login-page.php">Login</a>
      <?php endif; ?>
    </div>
  </header>

  <!-- ── Body layout ── -->
  <div class="body-layout">

    <!-- Left sidebar -->
    <aside class="sidebar-left" aria-label="Main navigation">
      <div>
        <p class="nav-section-label">Navigation</p>
        <a href="comlab-map.php" class="nav-item active" style="text-decoration:none;">
          <i class="bi bi-map nav-icon"></i>
          Comlab Map
        </a>
        <?php if ($isLoggedIn): ?>
          <a href="my-schedules.php" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-calendar3 nav-icon"></i>
            <?= $role === 'student' ? 'Schedules' : 'My Schedules' ?>
          </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
          <a href="manage-users.php" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-people nav-icon"></i>
            Manage Users
          </a>
          <a href="admin-reports.php" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-exclamation-triangle nav-icon"></i>
            Room Reports
          </a>
        <?php endif; ?>
      </div>

      <nav class="sidebar-live-region mt-2" aria-labelledby="live-heading">
        <h2 id="live-heading" class="panel-title-live">Live availability</h2>
        <p class="sidebar-live-caption">Today (<?= htmlspecialchars($today) ?>)</p>
        <div class="sidebar-live-scroll" id="sidebarLiveScroll" tabindex="0">
          <?php if (empty($todayRooms)): ?>
            <small class="text-muted px-1">No room data loaded.</small>
          <?php else: ?>
            <?php foreach ($todayRooms as $liveRoom): ?>
              <?php
                $lsc = 'live-free'; $lsl = 'Free';
                if (($liveRoom['status'] ?? '') === 'out_of_service')       { $lsc = 'live-out';  $lsl = 'Out of service'; }
                elseif ((int)$liveRoom['is_busy'] === 1 || ($liveRoom['status'] ?? '') === 'occupied') { $lsc = 'live-busy'; $lsl = 'Occupied'; }
              ?>
              <div class="live-availability-row" data-sidebar-room-id="<?= (int)$liveRoom['id'] ?>">
                <span class="live-room-name"><?= htmlspecialchars($liveRoom['room_name']) ?></span>
                <div class="live-status-pair">
                  <span class="live-status-dot <?= $lsc ?>"></span>
                  <span class="live-status-label"><?= htmlspecialchars($lsl) ?></span>
                </div>
              </div>
            <?php endforeach; ?>
          <?php endif; ?>
        </div>
      </nav>

      <div class="sidebar-spacer"></div>
      <div class="profile-card">
        <div class="profile-card-top">
          <div class="avatar"><?= strtoupper(substr($userName, 0, 1)) ?></div>
          <div>
            <div class="name"><?= htmlspecialchars($userName) ?></div>
            <div class="id"><?= $isLoggedIn ? 'Logged In User' : 'Public Student View' ?></div>
          </div>
        </div>
        <span class="badge"><?= $canManageSchedules ? 'Can Add/Edit Schedule' : 'Can View Schedule Only' ?></span>
      </div>
    </aside>

    <!-- ══════════════════════════════════════
         LEVEL 1 — Campus view (no building selected)
    ══════════════════════════════════════ -->
    <?php if (!$selectedBuilding): ?>
    <main class="main-content campus-view">

      <!-- ── Full-bleed Campus Map Background ── -->
      <div class="campus-map-section"></div>
      <!-- ── End Campus Map Background ── -->

      <!-- Dark gradient overlay -->
      <div class="campus-map-overlay"></div>

      <!-- Overlay content: heading + cards -->
      <div class="campus-overlay-content">

        <!-- ── Second campus map card (floating above cards) ── -->
        <div class="campus-minimap-card">
          <div class="campus-minimap-header">
            <i class="bi bi-map" style="font-size:12px;"></i>
            Campus Overview
          </div>
          <div class="campus-minimap-body">
            <img src="includes/img/map.png"
                 alt="BukSU Campus Map"
                 style="width:100%;height:100%;object-fit:contain;display:block;border-radius:0 0 6px 6px;"
                 loading="lazy">
          </div>
        </div>
        <!-- ── End second campus map ── -->

        <div class="campus-heading">
          <h2><i class="bi bi-geo-alt me-2"></i>Select a Building</h2>
          <p>Click a building to view its computer laboratories</p>
        </div>

      <div class="campus-buildings-grid">
        <?php foreach ($buildings as $bKey => $bDef):
          $cardClass  = $bKey === 'highschool' ? 'hs-card' : 'fin-card';
          $roomCount  = count($bDef['rooms']);
          $schedCount = $buildingSchedCount[$bKey];
          $availCount = 0;
          foreach ($bDef['rooms'] as $rn) {
              $rd = $roomsByLabel[strtolower($rn)] ?? null;
              if ($rd && $rd['status'] === 'available') $availCount++;
          }
        ?>
        <a href="comlab-map.php?building=<?= $bKey ?>" class="building-card <?= $cardClass ?>">
          <div class="building-card-icon">
            <i class="bi <?= $bDef['icon'] ?>"></i>
          </div>
          <div class="building-card-body">
            <p class="building-card-name"><?= htmlspecialchars($bDef['name']) ?></p>
            <p class="building-card-desc"><?= htmlspecialchars($bDef['description']) ?></p>
          </div>
          <div class="building-card-meta">
            <span class="building-meta-chip"><i class="bi bi-display me-1"></i><?= $roomCount ?> Rooms</span>
            <span class="building-meta-chip"><i class="bi bi-check-circle me-1"></i><?= $availCount ?> Available</span>
            <?php if ($schedCount > 0): ?>
              <span class="building-meta-chip"><i class="bi bi-calendar3 me-1"></i><?= $schedCount ?> Schedules</span>
            <?php endif; ?>
          </div>
          <i class="bi bi-chevron-right building-card-arrow"></i>
        </a>
        <?php endforeach; ?>
        </div><!-- /.campus-buildings-grid -->

      </div><!-- /.campus-overlay-content -->
    </main>

    <?php else: ?>
    <!-- ══════════════════════════════════════
         LEVEL 2 — Floor map view
    ══════════════════════════════════════ -->
    <main class="main-content floor-view">

      <!-- Breadcrumb -->
      <div class="floor-breadcrumb">
        <a href="comlab-map.php"><i class="bi bi-geo-alt"></i> Campus</a>
        <span class="bc-sep"><i class="bi bi-chevron-right" style="font-size:10px;"></i></span>
        <span class="bc-current"><?= htmlspecialchars($currentBuildingDef['name']) ?></span>
      </div>

      <!-- Map -->
      <div class="comlab-map-container">
        <span class="map-label">
          <i class="bi <?= $currentBuildingDef['icon'] ?> me-1"></i>
          <?= htmlspecialchars($currentBuildingDef['name']) ?> — Floor Map
        </span>

        <div class="comlab-map-grid <?= $selectedBuilding === 'highschool' ? 'map-grid-hs' : '' ?>" id="comlabMapGrid">

          <?php foreach ($currentLayout as $layout):
            $label    = strtolower($layout['label']);
            $roomData = $roomsByLabel[$label] ?? null;
            if (!$roomData) continue;
            $isActive    = ((int)$roomData['id'] === $selectedRoomId);
            $statusClass = $roomData['status'] === 'out_of_service' ? 'status-out'
                         : ($roomData['status'] === 'occupied'      ? 'status-busy' : 'status-free');
            $schedCnt    = $schedCountMap[(int)$roomData['id']] ?? 0;
          ?>
            <a class="comlab-tile <?= $statusClass ?> <?= $isActive ? 'active' : '' ?>"
               style="<?= htmlspecialchars($layout['style']) ?>"
               href="comlab-map.php?building=<?= $selectedBuilding ?>&room_id=<?= (int)$roomData['id'] ?>"
               data-room-id="<?= (int)$roomData['id'] ?>">
              <span><?= htmlspecialchars($roomData['display_label']) ?></span>
              <?php if ($schedCnt > 0): ?>
                <span class="tile-sched-badge"><?= $schedCnt ?> sched<?= $schedCnt > 1 ? 's' : '' ?></span>
              <?php endif; ?>
              <span class="tile-live-label"></span>
            </a>
          <?php endforeach; ?>

          <?php if ($selectedBuilding === 'finance'): ?>
            <div class="center-walkway" style="left:24%;top:20%;width:41%;height:56%;">Walkway / Center</div>
          <?php elseif ($selectedBuilding === 'highschool'): ?>
            <div class="center-walkway" style="left:15%;top:82%;width:70%;height:10%;">Corridor</div>
          <?php endif; ?>
        </div>

        <!-- Legend + live refresh indicator -->
        <div class="map-legend-wrap">
          <div class="map-legend">
            <span><i class="legend-dot legend-available"></i> Available</span>
            <span><i class="legend-dot legend-occupied"></i> Occupied</span>
            <span><i class="legend-dot legend-out"></i> Out of Service</span>
          </div>
          <div class="live-refresh-bar">
            <span class="live-refresh-pulse" aria-hidden="true"></span>
            Live &middot; updated <span id="liveRefreshTs">—</span>
          </div>
        </div>
      </div>
    </main>
    <?php endif; ?>

    <!-- ── Right sidebar (floor view only) ── -->
    <?php if ($selectedBuilding && $selectedRoom): ?>
    <aside class="sidebar-right">
      <div class="sidebar-right-inner">

        <div>
          <p class="panel-title">Selected room</p>
          <div class="selected-room-card">
            <strong class="selected-room-title"><?= htmlspecialchars($selectedRoom['display_label'] ?? '') ?></strong>
            <span class="text-muted small">Status: <?= htmlspecialchars(ucwords(str_replace('_',' ',$selectedRoom['status'] ?? 'available'))) ?></span>

            <?php if ($role === 'admin' && $selectedRoomId > 0): ?>
              <form action="room_status_save.php" method="POST" class="mt-2 d-flex gap-2 flex-wrap align-items-center admin-room-status-form">
                <input type="hidden" name="room_id" value="<?= (int)$selectedRoomId ?>">
                <input type="hidden" name="building" value="<?= htmlspecialchars($selectedBuilding) ?>">
                <select class="form-select form-select-sm" name="status">
                  <option value="available"      <?= ($selectedRoom['status'] ?? '') === 'available'      ? 'selected' : '' ?>>Available</option>
                  <option value="occupied"       <?= ($selectedRoom['status'] ?? '') === 'occupied'       ? 'selected' : '' ?>>Occupied</option>
                  <option value="out_of_service" <?= ($selectedRoom['status'] ?? '') === 'out_of_service' ? 'selected' : '' ?>>Out of Service</option>
                </select>
                <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
              </form>
            <?php endif; ?>

            <?php
              $selStatus = $selectedRoom['status'] ?? 'available';
              $roomUnavailable = ($role === 'instructor' && in_array($selStatus, ['occupied','out_of_service'], true));
            ?>
            <?php if ($roomUnavailable): ?>
              <div class="alert alert-warning py-2 px-3 small mt-2 mb-0">
                This room is <?= htmlspecialchars(str_replace('_',' ',$selStatus)) ?>. Instructors cannot add schedules here.
              </div>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($flashMessage): ?>
          <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> py-2 px-3 small mb-0">
            <?= htmlspecialchars($flashMessage) ?>
          </div>
        <?php endif; ?>

        <hr class="divider" role="presentation">

        <div>
          <p class="panel-title">Schedule for this room</p>
          <div class="schedule-list full-room-schedule-list">
            <?php if (empty($selectedSchedule)): ?>
              <div class="schedule-item">
                <span class="subj">No schedules yet.</span>
                <span class="meta">This comlab is currently open for booking.</span>
              </div>
            <?php else: ?>
              <?php foreach ($selectedSchedule as $row): ?>
                <div class="schedule-item">
                  <span class="subj"><?= htmlspecialchars($row['subject']) ?><?= $row['section'] ? ' — '.htmlspecialchars($row['section']) : '' ?></span>
                  <span class="meta"><?= htmlspecialchars($row['day_of_week']) ?> · <?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?></span>
                  <span class="room-tag tag-blue">Instructor: <?= htmlspecialchars($row['instructor_name']) ?></span>
                  <?php if ($canManageSchedules && $selectedRoomId > 0): ?>
                    <?php
                      $canEdit         = ($role === 'admin') || ($role === 'instructor' && (int)$row['instructor_id'] === $userId);
                      $roomUnavailEdit = ($role === 'instructor' && in_array($selStatus, ['occupied','out_of_service'], true));
                    ?>
                    <div class="d-flex gap-2 mt-2">
                      <?php if ($canEdit && !$roomUnavailEdit): ?>
                        <button class="btn btn-sm btn-outline-primary edit-btn" type="button"
                                data-id="<?= (int)$row['id'] ?>"
                                data-subject="<?= htmlspecialchars($row['subject']) ?>"
                                data-section="<?= htmlspecialchars((string)$row['section']) ?>"
                                data-day="<?= htmlspecialchars($row['day_of_week']) ?>"
                                data-start="<?= htmlspecialchars($row['time_start']) ?>"
                                data-end="<?= htmlspecialchars($row['time_end']) ?>"
                                data-instructor-name="<?= htmlspecialchars($row['instructor_name']) ?>">
                          Edit
                        </button>
                      <?php endif; ?>
                      <form action="schedule_save.php" method="POST" onsubmit="return confirm('Delete this schedule?');">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="schedule_id" value="<?= (int)$row['id'] ?>">
                        <input type="hidden" name="room_id" value="<?= (int)$selectedRoomId ?>">
                        <input type="hidden" name="building" value="<?= htmlspecialchars($selectedBuilding) ?>">
                        <button class="btn btn-sm btn-outline-danger" type="submit">Delete</button>
                      </form>
                    </div>
                  <?php endif; ?>
                </div>
              <?php endforeach; ?>
            <?php endif; ?>
          </div>
        </div>

        <?php if ($canManageSchedules && $selectedRoomId > 0): ?>
        <hr class="divider" role="presentation">
        <div>
          <p class="panel-title">Add schedule</p>
          <?php if ($roomUnavailable): ?>
            <div class="alert alert-warning py-2 px-3 small mb-0">This room is currently unavailable.</div>
          <?php else: ?>
            <button type="button" class="quick-btn" data-bs-toggle="modal" data-bs-target="#addScheduleModal">
              <i class="bi bi-plus-lg"></i> Add schedule for <?= htmlspecialchars($selectedRoom['display_label'] ?? '') ?>
            </button>
          <?php endif; ?>
        </div>
        <?php endif; ?>

        <?php if (in_array($role, ['instructor', 'admin'], true) && $selectedRoomId > 0): ?>
        <hr class="divider" role="presentation">
        <div>
          <p class="panel-title">Report an issue</p>
          <button type="button" class="btn btn-sm btn-outline-danger w-100" data-bs-toggle="modal" data-bs-target="#reportIssueModal">
            <i class="bi bi-exclamation-triangle"></i> Report Room Issue
          </button>
        </div>
        <?php endif; ?>

      </div>
    </aside>
    <?php endif; ?>

  </div><!-- /.body-layout -->

  <!-- ── Edit Schedule Modal ── -->
  <?php if ($canManageSchedules && $selectedBuilding): ?>
  <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-labelledby="editScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="schedule_save.php">
          <div class="modal-header">
            <h5 class="modal-title" id="editScheduleModalLabel">Edit schedule</h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="update">
            <input type="hidden" name="schedule_id" id="edit_schedule_id">
            <input type="hidden" name="room_id" value="<?= (int)$selectedRoomId ?>">
            <input type="hidden" name="building" value="<?= htmlspecialchars($selectedBuilding) ?>">
            <div class="mb-2"><label class="form-label">Subject</label><input type="text" class="form-control" name="subject" id="edit_subject" required></div>
            <div class="mb-2"><label class="form-label">Section</label><input type="text" class="form-control" name="section" id="edit_section"></div>
            <div class="mb-2">
              <label class="form-label">Day</label>
              <select class="form-select" name="day_of_week" id="edit_day" required>
                <?php foreach ($days as $day): ?>
                  <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row">
              <div class="col"><label class="form-label">Start</label><input type="time" class="form-control" name="time_start" id="edit_start" required></div>
              <div class="col"><label class="form-label">End</label><input type="time" class="form-control" name="time_end" id="edit_end" required></div>
            </div>
            <p class="small text-muted mt-2 mb-0">Instructor: <strong id="edit_instructor_display">—</strong></p>
          </div>
          <div class="modal-footer">
            <button type="submit" class="btn btn-primary">Save changes</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Add Schedule Modal ── -->
  <?php if ($canManageSchedules && $selectedRoomId > 0 && $selectedBuilding): ?>
  <div class="modal fade" id="addScheduleModal" tabindex="-1" aria-labelledby="addScheduleModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="schedule_save.php">
          <div class="modal-header">
            <h5 class="modal-title" id="addScheduleModalLabel">
              <i class="bi bi-plus-lg me-1"></i> Add Schedule — <?= htmlspecialchars($selectedRoom['display_label'] ?? '') ?>
            </h5>
            <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="room_id" value="<?= (int)$selectedRoomId ?>">
            <input type="hidden" name="building" value="<?= htmlspecialchars($selectedBuilding) ?>">
            <div class="mb-2"><label class="form-label">Subject</label><input type="text" class="form-control" name="subject" required autofocus></div>
            <div class="mb-2"><label class="form-label">Section</label><input type="text" class="form-control" name="section" placeholder="e.g. BSIT-2A"></div>
            <div class="mb-2">
              <label class="form-label">Day</label>
              <select class="form-select" name="day_of_week" required>
                <?php foreach ($days as $day): ?>
                  <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="row g-2 mb-2">
              <div class="col"><label class="form-label">Start time</label><input type="time" class="form-control" name="time_start" required></div>
              <div class="col"><label class="form-label">End time</label><input type="time" class="form-control" name="time_end" required></div>
            </div>
            <?php if ($role === 'admin' && !empty($instructors)): ?>
            <div class="mb-2">
              <label class="form-label">Instructor</label>
              <select class="form-select" name="instructor_id" required>
                <?php foreach ($instructors as $ins): ?>
                  <option value="<?= (int)$ins['id'] ?>"><?= htmlspecialchars($ins['name']) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <?php endif; ?>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary"><i class="bi bi-check-lg me-1"></i>Save Schedule</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <!-- ── Report Issue Modal ── -->
  <?php if (in_array($role, ['instructor', 'admin'], true) && $selectedRoomId > 0 && $selectedBuilding): ?>
  <div class="modal fade" id="reportIssueModal" tabindex="-1" aria-labelledby="reportIssueModalLabel" aria-hidden="true">
    <div class="modal-dialog">
      <div class="modal-content">
        <form method="POST" action="room_report_save.php">
          <div class="modal-header bg-danger text-white">
            <h5 class="modal-title" id="reportIssueModalLabel">
              <i class="bi bi-exclamation-triangle me-1"></i> Report Issue — <?= htmlspecialchars($selectedRoom['display_label'] ?? '') ?>
            </h5>
            <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
          </div>
          <div class="modal-body">
            <input type="hidden" name="action" value="create">
            <input type="hidden" name="room_id" value="<?= (int)$selectedRoomId ?>">
            <input type="hidden" name="building" value="<?= htmlspecialchars($selectedBuilding) ?>">
            
            <div class="mb-3">
              <label class="form-label">Issue Type</label>
              <select class="form-select" name="issue_type" required>
                <option value="damaged_equipment">Damaged Equipment</option>
                <option value="no_internet">No Internet Connection</option>
                <option value="no_electricity">No Electricity</option>
                <option value="out_of_service">Room Out of Service</option>
                <option value="other" selected>Other</option>
              </select>
            </div>
            
            <div class="mb-3">
              <label class="form-label">Description</label>
              <textarea class="form-control" name="description" rows="4" placeholder="Please provide details about the issue..." required></textarea>
            </div>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-outline-secondary" data-bs-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-danger"><i class="bi bi-send me-1"></i>Submit Report</button>
          </div>
        </form>
      </div>
    </div>
  </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>

  <!-- ── Edit modal populate ── -->
  <?php if ($canManageSchedules && $selectedBuilding): ?>
  <script>
    const modalEl   = document.getElementById('editScheduleModal');
    const editModal = modalEl ? new bootstrap.Modal(modalEl) : null;
    if (editModal) {
      document.querySelectorAll('.edit-btn').forEach(btn => {
        btn.addEventListener('click', () => {
          document.getElementById('edit_schedule_id').value = btn.dataset.id;
          document.getElementById('edit_subject').value     = btn.dataset.subject;
          document.getElementById('edit_section').value     = btn.dataset.section;
          document.getElementById('edit_day').value         = btn.dataset.day;
          document.getElementById('edit_start').value       = btn.dataset.start;
          document.getElementById('edit_end').value         = btn.dataset.end;
          const dispEl = document.getElementById('edit_instructor_display');
          if (dispEl) dispEl.textContent = btn.dataset.instructorName || '—';
          editModal.show();
        });
      });
    }
  </script>
  <?php endif; ?>

  <!-- ── Map search ── -->
  <?php if ($selectedBuilding): ?>
  <script>
    (function () {
      const allSchedules = <?= $allSchedulesJson ?>;
      const mapSearch    = document.getElementById('mapSearch');
      const mapClear     = document.getElementById('mapSearchClear');
      const tiles        = document.querySelectorAll('#comlabMapGrid .comlab-tile');
      if (!mapSearch) return;

      function applyMapSearch() {
        const q = mapSearch.value.trim().toLowerCase();
        mapClear.style.display = q ? 'flex' : 'none';
        if (!q) { tiles.forEach(t => t.classList.remove('tile-dim','tile-match')); return; }
        tiles.forEach(t => {
          const rid   = parseInt(t.dataset.roomId, 10);
          const scheds = allSchedules[rid] || [];
          const match  = scheds.some(s => s.includes(q));
          t.classList.toggle('tile-match', match);
          t.classList.toggle('tile-dim',  !match);
        });
      }

      mapSearch.addEventListener('input', applyMapSearch);
      mapClear.addEventListener('click', () => { mapSearch.value = ''; applyMapSearch(); mapSearch.focus(); });
    })();
  </script>

  <!-- Real-time room availability auto-refresh (60s) -->
  <script>
    (function () {
      const INTERVAL       = 60000;
      const STATUS_CLASSES = ['status-free', 'status-busy', 'status-out'];

      function applyLiveData(data) {
        document.querySelectorAll('#comlabMapGrid .comlab-tile').forEach(function (tile) {
          const rid  = parseInt(tile.dataset.roomId, 10);
          const info = data[rid];
          if (!info) return;
          tile.classList.remove.apply(tile.classList, STATUS_CLASSES);
          const label = tile.querySelector('.tile-live-label');
          if (info.live_status === 'oos') {
            tile.classList.add('status-out');
            if (label) label.textContent = 'Out of service';
          } else if (info.live_status === 'occupied') {
            tile.classList.add('status-busy');
            if (label) label.textContent = info.time_end ? 'Until ' + info.time_end : 'In Use';
          } else {
            tile.classList.add('status-free');
            if (label) label.textContent = 'Free';
          }
        });

        document.querySelectorAll('[data-sidebar-room-id]').forEach(function (row) {
          const rid  = parseInt(row.dataset.sidebarRoomId, 10);
          const info = data[rid];
          if (!info) return;
          const dot  = row.querySelector('.live-status-dot');
          const lbl  = row.querySelector('.live-status-label');
          if (dot) dot.className = 'live-status-dot';
          if (lbl) lbl.textContent = '';
          if (info.live_status === 'oos') {
            if (dot) dot.classList.add('live-out');
            if (lbl) lbl.textContent = 'Out of service';
          } else if (info.live_status === 'occupied') {
            if (dot) dot.classList.add('live-busy');
            if (lbl) lbl.textContent = 'Occupied';
          } else {
            if (dot) dot.classList.add('live-free');
            if (lbl) lbl.textContent = 'Free';
          }
        });

        var ts = document.getElementById('liveRefreshTs');
        if (ts) {
          ts.textContent = new Date().toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
        }
      }

      function fetchLiveStatus() {
        fetch('room_status_live.php')
          .then(function (r) { return r.json(); })
          .then(function (data) { applyLiveData(data); })
          .catch(function () {});
      }

      fetchLiveStatus();
      setInterval(fetchLiveStatus, INTERVAL);
    })();
  </script>
  <?php endif; ?>

</body>
</html>
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
$flashMessage = $_SESSION['schedule_flash'] ?? '';
$flashType = $_SESSION['schedule_flash_type'] ?? 'success';
unset($_SESSION['schedule_flash'], $_SESSION['schedule_flash_type']);

$days = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];
$today = date('l');

$roomLayout = [
    ['label' => 'Comlab 1', 'slot' => 'lab1', 'style' => 'left:71.5%;top:19%;width:11%;height:17%;'],
    ['label' => 'Comlab 2', 'slot' => 'lab2', 'style' => 'left:71.5%;top:36%;width:11%;height:17%;'],
    ['label' => 'Comlab 3', 'slot' => 'lab3', 'style' => 'left:71.5%;top:53%;width:11%;height:17%;'],
    ['label' => 'Comlab 4', 'slot' => 'lab4', 'style' => 'left:70%;top:84%;width:11%;height:14%;'],
    ['label' => 'Comlab 5', 'slot' => 'lab5', 'style' => 'left:61%;top:84%;width:9%;height:14%;'],
    ['label' => 'Comlab 6', 'slot' => 'lab6', 'style' => 'left:52%;top:84%;width:9%;height:14%;'],
    ['label' => 'Comlab 7', 'slot' => 'lab7', 'style' => 'left:43%;top:84%;width:9%;height:14%;'],
    ['label' => 'Comlab 8', 'slot' => 'lab8', 'style' => 'left:89%;top:12%;width:10%;height:17%;'],
    ['label' => 'Comlab 9', 'slot' => 'lab9', 'style' => 'left:89%;top:29%;width:10%;height:17%;'],
    ['label' => 'Comlab 10', 'slot' => 'lab10', 'style' => 'left:89%;top:46%;width:10%;height:17%;'],
    ['label' => 'Comlab 11', 'slot' => 'lab11', 'style' => 'left:8.5%;top:84%;width:10%;height:14%;'],
    ['label' => 'Comlab 12', 'slot' => 'lab12', 'style' => 'left:8.5%;top:70%;width:10%;height:14%;'],
];

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

if (count($rooms) === 0) {
    $buildingId = 0;
    $buildingResult = $conn->query("SELECT id FROM buildings ORDER BY id ASC LIMIT 1");
    if ($buildingResult instanceof mysqli_result && $buildingRow = $buildingResult->fetch_assoc()) {
        $buildingId = (int) $buildingRow['id'];
    } else {
        $insertBuilding = $conn->prepare("INSERT INTO buildings (code, name, description) VALUES ('COMLAB', 'Computer Laboratory Building', 'Auto-generated building for comlab schedules')");
        if ($insertBuilding && $insertBuilding->execute()) {
            $buildingId = (int) $conn->insert_id;
        }
        if ($insertBuilding) {
            $insertBuilding->close();
        }
    }

    if ($buildingId > 0) {
        $insertRoom = $conn->prepare("INSERT INTO rooms (building_id, room_code, room_name, capacity, status, description) VALUES (?, ?, ?, 40, 'available', 'Auto-generated comlab')");
        if ($insertRoom) {
            for ($i = 1; $i <= 12; $i++) {
                $roomCode = 'COMLAB' . $i;
                $roomName = 'Comlab ' . $i;
                $insertRoom->bind_param('iss', $buildingId, $roomCode, $roomName);
                $insertRoom->execute();
            }
            $insertRoom->close();
        }
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
foreach ($roomLayout as $index => $layoutRoom) {
    $key = strtolower($layoutRoom['label']);
    if (isset($roomsByLabel[$key])) {
        $roomById[(int) $roomsByLabel[$key]['id']] = $roomsByLabel[$key];
        continue;
    }
    $fallback = [
        'id' => -($index + 1),
        'room_code' => 'COMLAB-' . ($index + 1),
        'room_name' => $layoutRoom['label'],
        'status' => 'available',
        'capacity' => 40,
        'display_label' => $layoutRoom['label'],
    ];
    $roomById[$fallback['id']] = $fallback;
}

$selectedRoom = null;
if ($selectedRoomId !== 0 && isset($roomById[$selectedRoomId])) {
    $selectedRoom = $roomById[$selectedRoomId];
} else {
    $selectedRoom = reset($roomById);
    $selectedRoomId = (int) $selectedRoom['id'];
}

$instructors = [];
$insResult = $conn->query("SELECT id, name FROM users WHERE role = 'instructor' ORDER BY name ASC");
if ($insResult instanceof mysqli_result) {
    while ($row = $insResult->fetch_assoc()) {
        $instructors[] = $row;
    }
}
$hasInstructors = count($instructors) > 0;

$selectedSchedule = [];
if ($selectedRoomId > 0) {
    $stmt = $conn->prepare(
        "SELECT s.id, s.subject, s.section, s.day_of_week, s.time_start, s.time_end, s.status, u.name AS instructor_name, s.instructor_id
         FROM schedules s
         INNER JOIN users u ON u.id = s.instructor_id
         WHERE s.room_id = ?
         ORDER BY FIELD(s.day_of_week, 'Monday','Tuesday','Wednesday','Thursday','Friday','Saturday','Sunday'), s.time_start"
    );
    $stmt->bind_param('i', $selectedRoomId);
    $stmt->execute();
    $selectedSchedule = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt->close();
}

$todayRooms = [];
$todaySql = "SELECT r.id, r.room_name, r.status, 
            EXISTS(
                SELECT 1 FROM schedules s
                WHERE s.room_id = r.id
                  AND s.day_of_week = ?
                  AND CURTIME() BETWEEN s.time_start AND s.time_end
                  AND s.status <> 'cancelled'
            ) AS is_busy
            FROM rooms r
            WHERE r.room_name LIKE 'Comlab %'
            ORDER BY r.room_name ASC";
$todayStmt = $conn->prepare($todaySql);
if ($todayStmt) {
    $todayStmt->bind_param('s', $today);
    $todayStmt->execute();
    $todayRooms = $todayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $todayStmt->close();
}

$todaySchedule = [];
if ($isLoggedIn && $role === 'instructor') {
    $todayStmt = $conn->prepare(
        "SELECT s.subject, s.section, s.time_start, s.time_end, r.room_name
         FROM schedules s
         INNER JOIN rooms r ON r.id = s.room_id
         WHERE s.instructor_id = ? AND s.day_of_week = ? AND s.status <> 'cancelled'
         ORDER BY s.time_start ASC"
    );
    $todayStmt->bind_param('is', $userId, $today);
    $todayStmt->execute();
    $todaySchedule = $todayStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $todayStmt->close();
}
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
    /* Fallback map styles in case external CSS is cached/missing */
    .comlab-map-container { max-width: 820px; aspect-ratio: 4 / 3; padding: 1rem; display: flex; flex-direction: column; gap: 10px; }
    .comlab-map-grid { width: 100%; height: 100%; min-height: 420px; position: relative; background: #d9f0f0; border: 2px solid #173049; border-radius: 8px; padding: 10px; overflow: hidden; }
    .comlab-tile { position: absolute; border: 2px solid #173049; border-radius: 4px; color: #0d1b2a; text-decoration: none; font-size: 14px; font-weight: 600; display: flex; align-items: center; justify-content: center; text-align: center; padding: 4px; transition: transform 0.12s ease, box-shadow 0.12s ease; z-index: 3; }
    .comlab-tile:hover { transform: translateY(-1px); box-shadow: 0 4px 12px rgba(4, 44, 83, 0.18); }
    .comlab-tile.active { outline: 3px solid #185fa5; }
    .status-free { background: #e7f8f1; }
    .status-busy { background: #fce9e9; }
    .status-out { background: #eeeeee; color: #737373; }
    .center-walkway { position: absolute; border: 2px solid #173049; background: #f3f3f3; border-radius: 4px; font-weight: 600; color: #5a7a96; display: flex; align-items: center; justify-content: center; z-index: 1; }
    .map-legend { display: flex; gap: 14px; align-items: center; justify-content: center; font-size: 12px; color: #5a7a96; font-weight: 600; }
    .legend-dot { width: 10px; height: 10px; border-radius: 50%; display: inline-block; margin-right: 5px; border: 1px solid #17304933; }
    .legend-available { background: #e7f8f1; }
    .legend-occupied { background: #fce9e9; }
    .legend-out { background: #eeeeee; }
  </style>
</head>
<body>
  <header class="topbar">
    <a class="topbar-brand" href="comlab-map.php">
      <div class="logo-mark">Bu</div>
      BukSU Rooms
    </a>

    <div class="topbar-stats">
      <div class="stat-chip">
        <span class="dot dot-green"></span>
        <strong><?= count($roomById) ?></strong> Comlabs
      </div>
      <div class="stat-chip">
        <span class="dot dot-blue"></span>
        <strong><?= htmlspecialchars($today) ?></strong> Live View
      </div>
      <div class="stat-chip">
        <span class="dot dot-amber"></span>
        <strong><?= $canManageSchedules ? 'Edit Enabled' : 'View Only' ?></strong>
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

  <div class="body-layout">
    <aside class="sidebar-left">
      <div>
        <p class="nav-section-label">Navigation</p>
        <a href="comlab-map.php" class="nav-item active" style="text-decoration:none;">
          <i class="bi bi-map nav-icon"></i>
          Comlab Map
        </a>
        <?php if ($isLoggedIn): ?>
          <a href="user-schedules.html" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-calendar3 nav-icon"></i>
            My Schedules
          </a>
        <?php endif; ?>
      </div>

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

    <main class="main-content">
      <div class="map-container comlab-map-container">
        <span class="map-label">Comlab Floor Map</span>
        <div class="comlab-map-grid">
          <?php foreach ($roomLayout as $layout): ?>
            <?php
            $label = strtolower($layout['label']);
            $roomData = $roomsByLabel[$label] ?? null;
            if (!$roomData) {
                foreach ($roomById as $fallbackRoom) {
                    if (strtolower($fallbackRoom['display_label']) === $label) {
                        $roomData = $fallbackRoom;
                        break;
                    }
                }
            }
            if (!$roomData) {
                continue;
            }
            $isActiveRoom = ((int) $roomData['id'] === $selectedRoomId);
            $statusClass = $roomData['status'] === 'out_of_service' ? 'status-out' : ($roomData['status'] === 'occupied' ? 'status-busy' : 'status-free');
            ?>
            <a class="comlab-tile <?= $layout['slot'] ?> <?= $statusClass ?> <?= $isActiveRoom ? 'active' : '' ?>"
               style="<?= htmlspecialchars($layout['style']) ?>"
               href="comlab-map.php?room_id=<?= (int) $roomData['id'] ?>">
              <span><?= htmlspecialchars($roomData['display_label']) ?></span>
            </a>
          <?php endforeach; ?>
          <div class="center-walkway" style="left:24%;top:20%;width:41%;height:56%;">Map Center</div>
        </div>
        <div class="map-legend">
          <span><i class="legend-dot legend-available"></i> Available</span>
          <span><i class="legend-dot legend-occupied"></i> Occupied</span>
          <span><i class="legend-dot legend-out"></i> Out of Service</span>
        </div>
      </div>
    </main>

    <aside class="sidebar-right">
      <div>
        <p class="panel-title">Selected Room</p>
        <div class="selected-room-card">
          <strong><?= htmlspecialchars($selectedRoom['display_label'] ?? 'No room selected') ?></strong>
          <span>Capacity: <?= (int) ($selectedRoom['capacity'] ?? 0) ?></span>
          <span>Status: <?= htmlspecialchars(ucwords(str_replace('_', ' ', $selectedRoom['status'] ?? 'available'))) ?></span>
          <?php if ($role === 'admin' && $selectedRoomId > 0): ?>
            <form action="room_status_save.php" method="POST" class="mt-2 d-flex gap-2 align-items-center">
              <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">
              <select class="form-select form-select-sm" name="status">
                <option value="available" <?= ($selectedRoom['status'] ?? '') === 'available' ? 'selected' : '' ?>>Available</option>
                <option value="occupied" <?= ($selectedRoom['status'] ?? '') === 'occupied' ? 'selected' : '' ?>>Occupied</option>
                <option value="out_of_service" <?= ($selectedRoom['status'] ?? '') === 'out_of_service' ? 'selected' : '' ?>>Out of Service</option>
              </select>
              <button type="submit" class="btn btn-sm btn-outline-secondary">Update</button>
            </form>
          <?php endif; ?>
        </div>
      </div>

      <?php if ($flashMessage): ?>
        <div class="alert alert-<?= $flashType === 'error' ? 'danger' : 'success' ?> py-2 px-3 small mb-0">
          <?= htmlspecialchars($flashMessage) ?>
        </div>
      <?php endif; ?>

      <hr class="divider">

      <div>
        <p class="panel-title">Full Room Schedule</p>
        <div class="schedule-list full-room-schedule-list">
          <?php if (empty($selectedSchedule)): ?>
            <div class="schedule-item">
              <span class="subj">No schedules yet.</span>
              <span class="meta">This comlab is currently open for booking.</span>
            </div>
          <?php else: ?>
            <?php foreach ($selectedSchedule as $row): ?>
              <div class="schedule-item">
                <span class="subj"><?= htmlspecialchars($row['subject']) ?><?= $row['section'] ? ' — ' . htmlspecialchars($row['section']) : '' ?></span>
                <span class="meta"><?= htmlspecialchars($row['day_of_week']) ?> · <?= date('g:i A', strtotime($row['time_start'])) ?> - <?= date('g:i A', strtotime($row['time_end'])) ?></span>
                <span class="room-tag tag-blue">Instructor: <?= htmlspecialchars($row['instructor_name']) ?></span>
                <?php if ($canManageSchedules && $selectedRoomId > 0): ?>
                  <div class="d-flex gap-2 mt-2">
                    <button class="btn btn-sm btn-outline-primary edit-btn"
                            type="button"
                            data-id="<?= (int) $row['id'] ?>"
                            data-subject="<?= htmlspecialchars($row['subject']) ?>"
                            data-section="<?= htmlspecialchars((string) $row['section']) ?>"
                            data-day="<?= htmlspecialchars($row['day_of_week']) ?>"
                            data-start="<?= htmlspecialchars($row['time_start']) ?>"
                            data-end="<?= htmlspecialchars($row['time_end']) ?>"
                            data-instructor-id="<?= (int) $row['instructor_id'] ?>">
                      Edit
                    </button>
                    <form action="schedule_save.php" method="POST" onsubmit="return confirm('Delete this schedule?');">
                      <input type="hidden" name="action" value="delete">
                      <input type="hidden" name="schedule_id" value="<?= (int) $row['id'] ?>">
                      <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">
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
      <hr class="divider">
      <div>
        <p class="panel-title">Add Schedule</p>
        <?php if (!$hasInstructors): ?>
          <div class="alert alert-warning py-2 px-3 small mb-0">
            No instructor account found yet. Add an instructor user first before creating schedules.
          </div>
        <?php else: ?>
          <form action="schedule_save.php" method="POST" class="schedule-form">
            <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">
            <input type="hidden" name="action" value="create">
            <div class="mb-2">
              <label class="form-label">Subject</label>
              <input type="text" class="form-control form-control-sm" name="subject" required>
            </div>
            <div class="mb-2">
              <label class="form-label">Section</label>
              <input type="text" class="form-control form-control-sm" name="section">
            </div>
            <div class="mb-2">
              <label class="form-label">Day</label>
              <select class="form-select form-select-sm" name="day_of_week" required>
                <?php foreach ($days as $day): ?>
                  <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="d-flex gap-2 mb-2">
              <div class="flex-fill">
                <label class="form-label">Start</label>
                <input type="time" class="form-control form-control-sm" name="time_start" required>
              </div>
              <div class="flex-fill">
                <label class="form-label">End</label>
                <input type="time" class="form-control form-control-sm" name="time_end" required>
              </div>
            </div>
            <div class="mb-3">
              <label class="form-label">Instructor</label>
              <select class="form-select form-select-sm" name="instructor_id" required>
                <?php foreach ($instructors as $ins): ?>
                  <option value="<?= (int) $ins['id'] ?>" <?= (!$role || $role === 'admin' ? '' : ((int) $ins['id'] === $userId ? 'selected' : '')) ?>>
                    <?= htmlspecialchars($ins['name']) ?>
                  </option>
                <?php endforeach; ?>
              </select>
            </div>
            <button type="submit" class="quick-btn">Save Schedule</button>
          </form>
        <?php endif; ?>
      </div>
      <?php endif; ?>

      <hr class="divider">
      <div>
        <p class="panel-title">Live Availability</p>
        <?php if (empty($todayRooms)): ?>
          <small class="text-muted">No room data loaded yet.</small>
        <?php else: ?>
          <?php foreach ($todayRooms as $liveRoom): ?>
            <div class="availability-row">
              <span class="room-name"><?= htmlspecialchars($liveRoom['room_name']) ?></span>
              <div class="status-pair">
                <div class="status-dot <?= ((int) $liveRoom['is_busy'] === 1 || $liveRoom['status'] === 'occupied') ? 'status-busy' : 'status-free' ?>"></div>
                <span class="status-label"><?= ((int) $liveRoom['is_busy'] === 1 || $liveRoom['status'] === 'occupied') ? 'Occupied' : 'Free' ?></span>
              </div>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>
    </aside>
  </div>

  <?php if ($canManageSchedules && $hasInstructors): ?>
    <div class="modal fade" id="editScheduleModal" tabindex="-1" aria-hidden="true">
      <div class="modal-dialog">
        <div class="modal-content">
          <form method="POST" action="schedule_save.php">
            <div class="modal-header">
              <h5 class="modal-title">Edit Schedule</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
              <input type="hidden" name="action" value="update">
              <input type="hidden" name="schedule_id" id="edit_schedule_id">
              <input type="hidden" name="room_id" value="<?= (int) $selectedRoomId ?>">
              <div class="mb-2">
                <label class="form-label">Subject</label>
                <input type="text" class="form-control" name="subject" id="edit_subject" required>
              </div>
              <div class="mb-2">
                <label class="form-label">Section</label>
                <input type="text" class="form-control" name="section" id="edit_section">
              </div>
              <div class="mb-2">
                <label class="form-label">Day</label>
                <select class="form-select" name="day_of_week" id="edit_day" required>
                  <?php foreach ($days as $day): ?>
                    <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="row">
                <div class="col">
                  <label class="form-label">Start</label>
                  <input type="time" class="form-control" name="time_start" id="edit_start" required>
                </div>
                <div class="col">
                  <label class="form-label">End</label>
                  <input type="time" class="form-control" name="time_end" id="edit_end" required>
                </div>
              </div>
              <div class="mt-2">
                <label class="form-label">Instructor</label>
                <select class="form-select" name="instructor_id" id="edit_instructor_id" required>
                  <?php foreach ($instructors as $ins): ?>
                    <option value="<?= (int) $ins['id'] ?>"><?= htmlspecialchars($ins['name']) ?></option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>
            <div class="modal-footer">
              <button type="submit" class="btn btn-primary">Save changes</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  <?php endif; ?>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <?php if ($canManageSchedules && $hasInstructors): ?>
  <script>
    const editModal = new bootstrap.Modal(document.getElementById('editScheduleModal'));
    document.querySelectorAll('.edit-btn').forEach((btn) => {
      btn.addEventListener('click', () => {
        document.getElementById('edit_schedule_id').value = btn.dataset.id;
        document.getElementById('edit_subject').value = btn.dataset.subject;
        document.getElementById('edit_section').value = btn.dataset.section;
        document.getElementById('edit_day').value = btn.dataset.day;
        document.getElementById('edit_start').value = btn.dataset.start;
        document.getElementById('edit_end').value = btn.dataset.end;
        document.getElementById('edit_instructor_id').value = btn.dataset.instructorId;
        editModal.show();
      });
    });
  </script>
  <?php endif; ?>
</body>
</html>

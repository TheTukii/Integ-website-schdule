<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role'])) {
    header('Location: login-page.php');
    exit;
}

$role     = $_SESSION['user_role'];
$userId   = (int) $_SESSION['user_id'];
$userName = $_SESSION['user_name'] ?? 'User';
$isLoggedIn = isset($_SESSION['user_id']);

// Instructors see their own. Admins can view any instructor's via ?instructor_id=
$viewId = $userId;
if ($role === 'admin' && isset($_GET['instructor_id'])) {
    $viewId = (int) $_GET['instructor_id'];
}

// Fetch the viewed instructor's name
$viewName = $userName;
if ($viewId !== $userId) {
    $nameStmt = $conn->prepare("SELECT name FROM users WHERE id = ? LIMIT 1");
    $nameStmt->bind_param('i', $viewId);
    $nameStmt->execute();
    $nameRow = $nameStmt->get_result()->fetch_assoc();
    $nameStmt->close();
    $viewName = $nameRow['name'] ?? "User #$viewId";
}

// Fetch all schedules for this instructor
$schedules = [];
$schedStmt = $conn->prepare(
    "SELECT s.id, s.subject, s.section, s.day_of_week, s.time_start, s.time_end, s.status,
            r.room_name, r.id AS room_id
     FROM schedules s
     INNER JOIN rooms r ON r.id = s.room_id
     WHERE s.instructor_id = ?
     ORDER BY FIELD(s.day_of_week, 'Monday and Thursday','Tuesday and Wednesday'), s.time_start ASC"
);
$schedStmt->bind_param('i', $viewId);
$schedStmt->execute();
$schedules = $schedStmt->get_result()->fetch_all(MYSQLI_ASSOC);
$schedStmt->close();

// Group by day
$byDay = [];
foreach ($schedules as $s) {
    $byDay[$s['day_of_week']][] = $s;
}

// Live availability for sidebar
$today = date('l');
$canManageSchedules = in_array($role, ['admin', 'instructor'], true);
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

$dashboardCssPath   = __DIR__ . '/includes/css/user-dashboard-css.css';
$dashboardCssInline = is_file($dashboardCssPath) ? file_get_contents($dashboardCssPath) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — My Schedules</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <?php if ($dashboardCssInline !== ''): ?>
    <style><?= $dashboardCssInline ?></style>
  <?php endif; ?>
  <style>
    body { overflow: hidden; background: #042C53; }

    /* ── Live availability panel (sidebar) ── */
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

    /* ── Main content ── */
    .my-sched-main {
      flex: 1;
      display: flex;
      flex-direction: column;
      overflow: hidden;
      padding: 1.25rem 1.5rem;
      gap: 1rem;
    }
    .page-header {
      display: flex;
      align-items: center;
      justify-content: space-between;
      flex-shrink: 0;
    }
    .page-header h1 {
      font-size: 1.1rem;
      font-weight: 700;
      color: var(--navy);
      margin: 0;
    }
    .page-header p {
      font-size: 12px;
      color: var(--muted);
      margin: 0;
    }
    .search-toolbar {
      display: flex;
      gap: 10px;
      align-items: center;
      flex-shrink: 0;
    }
    .search-toolbar .search-box { flex: 1; max-width: 360px; }
    .filter-select {
      font-size: 12px;
      padding: 7px 12px;
      border-radius: var(--radius-md);
      border: 1px solid var(--border);
      color: var(--text);
      background: var(--white);
    }
    .schedules-scroll {
      flex: 1;
      overflow-y: auto;
      display: flex;
      flex-direction: column;
      gap: 1rem;
    }
    .day-group-label {
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.08em;
      text-transform: uppercase;
      color: var(--muted);
      padding: 0 2px;
      margin-bottom: 4px;
    }
    .sched-card {
      display: flex;
      align-items: center;
      gap: 12px;
      background: var(--white);
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      padding: 12px 14px;
      transition: border-color 0.15s, box-shadow 0.15s;
    }
    .sched-card:hover {
      border-color: var(--accent);
      box-shadow: 0 2px 8px rgba(55,138,221,0.10);
    }
    .sched-time-block {
      flex-shrink: 0;
      width: 72px;
      text-align: center;
      background: var(--navy-pale);
      border-radius: 8px;
      padding: 8px 4px;
    }
    .sched-time-block .t-start {
      font-size: 13px;
      font-weight: 700;
      color: var(--navy);
      line-height: 1.2;
    }
    .sched-time-block .t-end {
      font-size: 11px;
      color: var(--muted);
    }
    .sched-info { flex: 1; min-width: 0; }
    .sched-info .subj {
      font-size: 14px;
      font-weight: 600;
      color: var(--text);
      white-space: nowrap;
      overflow: hidden;
      text-overflow: ellipsis;
    }
    .sched-info .sec {
      font-size: 12px;
      color: var(--muted);
    }
    .sched-room-tag {
      flex-shrink: 0;
      font-size: 11px;
      font-weight: 600;
      padding: 3px 10px;
      border-radius: 20px;
      background: var(--navy-light);
      color: var(--navy);
      text-decoration: none;
    }
    .sched-room-tag:hover { background: var(--border); }
    .status-pill {
      font-size: 10px;
      font-weight: 700;
      padding: 2px 8px;
      border-radius: 20px;
      text-transform: capitalize;
    }
    .status-pill.reserved  { background: #e6f1fb; color: #0c447c; }
    .status-pill.cancelled { background: #fce9e9; color: #8b1a1a; }
    .status-pill.available { background: #e7f8f1; color: #145c44; }
    .empty-state {
      text-align: center;
      color: var(--muted);
      padding: 3rem 1rem;
    }
    .empty-state i { font-size: 2.5rem; opacity: 0.3; }
    .empty-state p { margin-top: 0.75rem; font-size: 14px; }
    .flash-bar {
      padding: 10px 16px;
      border-radius: var(--radius-md);
      font-size: 13px;
      font-weight: 500;
      flex-shrink: 0;
    }
    .flash-bar.success { background: #e7f8f1; color: #145c44; border: 1px solid #b6e8d6; }
    .flash-bar.error   { background: #fce9e9; color: #8b1a1a; border: 1px solid #f0c4c4; }
  </style>
</head>
<body class="page-comlab-map">

  <header class="topbar">
    <a class="topbar-brand" href="comlab-map.php">
      <div class="logo-mark">Bu</div>
      BukSU Rooms
    </a>

    <div class="topbar-stats">
      <div class="stat-chip">
        <span class="dot dot-green"></span>
        <strong><?= count($schedules) ?></strong> Schedules
      </div>
      <div class="stat-chip">
        <span class="dot dot-blue"></span>
        <strong><?= htmlspecialchars($viewName) ?></strong>
      </div>
    </div>

    <div class="topbar-user">
      <div class="user-meta">
        <div class="name"><?= htmlspecialchars($userName) ?></div>
        <div class="role"><?= ucfirst(htmlspecialchars($role)) ?></div>
      </div>
      
      <a class="btn btn-sm btn-outline-primary" href="logout.php">Logout</a>
    </div>
  </header>

  <div class="body-layout">

    <!-- ── Sidebar ── -->
    <aside class="sidebar-left" aria-label="Main navigation">
      <div>
        <p class="nav-section-label">Navigation</p>
        <a href="comlab-map.php" class="nav-item" style="text-decoration:none;">
          <i class="bi bi-map nav-icon"></i>
          Comlab Map
        </a>
        <?php if ($isLoggedIn): ?>
          <a href="my-schedules.php" class="nav-item active" style="text-decoration:none;">
            <i class="bi bi-calendar3 nav-icon"></i>
            My Schedules
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

      <nav class="sidebar-live-region mt-2" aria-labelledby="live-heading-ms">
        <h2 id="live-heading-ms" class="panel-title-live">Live availability</h2>
        <p class="sidebar-live-caption">Today (<?= htmlspecialchars($today) ?>)</p>
        <div class="sidebar-live-scroll" tabindex="0">
          <?php if (empty($todayRooms)): ?>
            <small class="text-muted px-1">No room data loaded.</small>
          <?php else: ?>
            <?php foreach ($todayRooms as $liveRoom): ?>
              <?php
                $lsc = 'live-free'; $lsl = 'Free';
                if (($liveRoom['status'] ?? '') === 'out_of_service')                                          { $lsc = 'live-out';  $lsl = 'Out of service'; }
                elseif ((int)$liveRoom['is_busy'] === 1 || ($liveRoom['status'] ?? '') === 'occupied') { $lsc = 'live-busy'; $lsl = 'Occupied'; }
              ?>
              <div class="live-availability-row">
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
            <div class="id">Logged In User</div>
          </div>
        </div>
        <span class="badge"><?= $canManageSchedules ? 'Can Add/Edit Schedule' : 'Can View Schedule Only' ?></span>
      </div>
    </aside>

        

    <!-- ── Main content ── -->
    <main class="my-sched-main">

      <div class="page-header">
        <div>
          <h1><i class="bi bi-calendar3 me-2"></i>My Schedules</h1>
          <p>All schedules assigned to <strong><?= htmlspecialchars($viewName) ?></strong></p>
        </div>
      </div>

      <div class="search-toolbar">
        <div class="search-box">
          <i class="bi bi-search text-muted" style="font-size:13px;"></i>
          <input type="text" id="schedSearch" placeholder="Search subject, section, or room…" autocomplete="off">
        </div>
        <select class="filter-select" id="dayFilter">
          <option value="">All days</option>
          <?php foreach (array_keys($byDay) as $day): ?>
            <option value="<?= htmlspecialchars($day) ?>"><?= htmlspecialchars($day) ?></option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="schedules-scroll" id="schedScroll">
        <?php if (empty($schedules)): ?>
          <div class="empty-state">
            <i class="bi bi-calendar-x"></i>
            <p>No schedules assigned yet.</p>
          </div>
        <?php else: ?>
          <?php foreach ($byDay as $day => $rows): ?>
            <div class="day-group" data-day="<?= htmlspecialchars($day) ?>">
              <p class="day-group-label"><?= htmlspecialchars($day) ?></p>
              <?php foreach ($rows as $s): ?>
                <div class="sched-card mb-2"
                     data-search="<?= strtolower(htmlspecialchars($s['subject'] . ' ' . $s['section'] . ' ' . $s['room_name'])) ?>"
                     data-day="<?= htmlspecialchars($s['day_of_week']) ?>">
                  <div class="sched-time-block">
                    <div class="t-start"><?= date('g:i A', strtotime($s['time_start'])) ?></div>
                    <div class="t-end"><?= date('g:i A', strtotime($s['time_end'])) ?></div>
                  </div>
                  <div class="sched-info">
                    <div class="subj"><?= htmlspecialchars($s['subject']) ?></div>
                    <div class="sec"><?= $s['section'] ? htmlspecialchars($s['section']) : '<span style="opacity:.5">No section</span>' ?></div>
                  </div>
                  <a class="sched-room-tag" href="comlab-map.php?room_id=<?= (int)$s['room_id'] ?>">
                    <i class="bi bi-geo-alt"></i> <?= htmlspecialchars($s['room_name']) ?>
                  </a>
                  <span class="status-pill <?= htmlspecialchars($s['status']) ?>">
                    <?= ucfirst(htmlspecialchars($s['status'])) ?>
                  </span>
                </div>
              <?php endforeach; ?>
            </div>
          <?php endforeach; ?>
        <?php endif; ?>
      </div>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    const searchInput = document.getElementById('schedSearch');
    const dayFilter   = document.getElementById('dayFilter');
    const cards       = document.querySelectorAll('.sched-card');
    const dayGroups   = document.querySelectorAll('.day-group');

    function applyFilters() {
      const q   = searchInput.value.trim().toLowerCase();
      const day = dayFilter.value;

      cards.forEach(card => {
        const matchQ   = !q   || card.dataset.search.includes(q);
        const matchDay = !day || card.dataset.day === day;
        card.style.display = (matchQ && matchDay) ? '' : 'none';
      });

      // Hide day headers with no visible cards
      dayGroups.forEach(group => {
        const visible = group.querySelectorAll('.sched-card:not([style*="display: none"])').length;
        group.style.display = visible > 0 ? '' : 'none';
      });
    }

    searchInput.addEventListener('input', applyFilters);
    dayFilter.addEventListener('change', applyFilters);
  </script>
</body>
</html>
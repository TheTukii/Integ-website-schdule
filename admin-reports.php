<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login-page.php');
    exit;
}

$adminId   = (int) $_SESSION['user_id'];
$adminName = $_SESSION['user_name'] ?? 'Admin';
$role      = $_SESSION['user_role'];
$isLoggedIn = isset($_SESSION['user_id']);

$flashMessage = $_SESSION['admin_report_flash'] ?? '';
$flashType    = $_SESSION['admin_report_flash_type'] ?? 'success';
unset($_SESSION['admin_report_flash'], $_SESSION['admin_report_flash_type']);

// Fetch all reports
$reports = [];
$query = "SELECT rr.id, rr.room_id, rr.issue_type, rr.description, rr.status, rr.created_at, r.room_name, u.name as reported_by_name
          FROM room_reports rr
          INNER JOIN rooms r ON r.id = rr.room_id
          INNER JOIN users u ON u.id = rr.reported_by
          ORDER BY rr.created_at DESC";
$result = $conn->query($query);
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $reports[] = $row;
    }
}

// Live availability for sidebar
$today = date('l');
$canManageSchedules = true; // Admins always can
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

$dashboardCssPath  = __DIR__ . '/includes/css/user-dashboard-css.css';
$dashboardCssInline = is_file($dashboardCssPath) ? file_get_contents($dashboardCssPath) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Room Reports</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <?php if ($dashboardCssInline !== ''): ?>
    <style><?= $dashboardCssInline ?></style>
  <?php endif; ?>
  <style>
    body { overflow: hidden; background: #042C53; }

    /* ── Live availability panel (sidebar) ── */
    .sidebar-live-region { border: 1px solid var(--border,#C8DFF0); border-radius: 10px; background: var(--navy-pale,#F4F8FD); padding: .75rem .65rem; }
    .panel-title-live { font-size: 11px; font-weight: 600; letter-spacing: .06em; text-transform: uppercase; color: var(--muted,#5A7A96); margin-bottom: .5rem; }
    .sidebar-live-caption { font-size: 11px; color: var(--muted,#5A7A96); margin-bottom: .5rem; }
    .sidebar-live-scroll { max-height: min(32vh,260px); overflow-y: auto; overscroll-behavior: contain; display: flex; flex-direction: column; gap: 4px; padding-right: 2px; }
    .live-availability-row { display: flex; align-items: center; justify-content: space-between; gap: 8px; padding: 7px 8px; font-size: 12px; border-radius: 8px; background: var(--white,#fff); border: 1px solid var(--border,#C8DFF0); }
    .live-room-name { color: var(--text,#0D1B2A); font-weight: 500; }
    .live-status-pair { display: flex; align-items: center; gap: 6px; flex-shrink: 0; }
    .live-status-dot { width: 8px; height: 8px; border-radius: 50%; flex-shrink: 0; }
    .live-status-dot.live-free { background: #1D9E75; }
    .live-status-dot.live-busy { background: #E24B4A; }
    .live-status-dot.live-out  { background: #94a3b8; }
    .live-status-label { font-size: 11px; font-weight: 600; color: var(--muted,#5A7A96); text-align: right; }

    /* ── Main content ── */
    .manage-main { flex: 1; display: flex; flex-direction: column; overflow: hidden; padding: 1.25rem 1.5rem; gap: 1rem; }
    .page-header { display: flex; align-items: center; justify-content: space-between; flex-shrink: 0; }
    .page-header h1 { font-size: 1.15rem; font-weight: 700; color: var(--navy); margin: 0; }
    .page-header p { font-size: 12px; color: var(--muted); margin: 0; }
    
    .reports-table-wrap { flex: 1; overflow-y: auto; border: 1px solid var(--border); border-radius: var(--radius-md); background: var(--white); }
    .reports-table { width: 100%; border-collapse: collapse; font-size: 13px; }
    .reports-table thead { position: sticky; top: 0; z-index: 2; background: var(--navy-pale); }
    .reports-table thead th { padding: 10px 14px; font-size: 10px; font-weight: 700; letter-spacing: 0.06em; text-transform: uppercase; color: var(--muted); border-bottom: 1px solid var(--border); white-space: nowrap; }
    .reports-table tbody tr { border-bottom: 1px solid var(--border); transition: background 0.12s; }
    .reports-table tbody tr:last-child { border-bottom: none; }
    .reports-table tbody tr:hover { background: var(--navy-pale); }
    .reports-table td { padding: 10px 14px; vertical-align: middle; color: var(--text); }
    
    .report-desc { max-width: 300px; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
    
    .status-badge { font-size: 10px; font-weight: 700; padding: 3px 9px; border-radius: 20px; text-transform: capitalize; letter-spacing: 0.04em; }
    .status-badge.pending   { background: #fff3cd; color: #856404; border: 1px solid #ffeeba; }
    .status-badge.resolved  { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
    .status-badge.dismissed { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
    
    .actions-cell { display: flex; align-items: center; gap: 6px; flex-wrap: nowrap; }
    
    .btn-action { font-size: 11px; padding: 4px 10px; border-radius: 6px; font-weight: 600; cursor: pointer; transition: background 0.15s; border: none; }
    .btn-resolve { background: #1D9E75; color: white; }
    .btn-resolve:hover { background: #145c44; }
    .btn-dismiss { background: #e24b4a; color: white; }
    .btn-dismiss:hover { background: #a52a2a; }
    
    .empty-row td { text-align: center; color: var(--muted); padding: 2rem; font-size: 13px; }
    .flash-bar { padding: 10px 16px; border-radius: var(--radius-md); font-size: 13px; font-weight: 500; flex-shrink: 0; }
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
        <strong><?= count($reports) ?></strong> Reports
      </div>
      <div class="stat-chip">
        <span class="dot dot-blue"></span>
        <strong>Admin Panel</strong>
      </div>
    </div>

    <div class="topbar-user">
      <div class="user-meta">
        <div class="name"><?= htmlspecialchars($adminName) ?></div>
        <div class="role">Admin</div>
      </div>
      <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
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
          <a href="my-schedules.php" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-calendar3 nav-icon"></i>
            My Schedules
          </a>
        <?php endif; ?>
        <?php if ($role === 'admin'): ?>
          <a href="manage-users.php" class="nav-item" style="text-decoration:none;">
            <i class="bi bi-people nav-icon"></i>
            Manage Users
          </a>
          <a href="admin-reports.php" class="nav-item active" style="text-decoration:none;">
            <i class="bi bi-exclamation-triangle nav-icon"></i>
            Room Reports
          </a>
        <?php endif; ?>
      </div>

      <nav class="sidebar-live-region mt-2" aria-labelledby="live-heading-ar">
        <h2 id="live-heading-ar" class="panel-title-live">Live availability</h2>
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
          <div class="avatar"><?= strtoupper(substr($adminName, 0, 1)) ?></div>
          <div>
            <div class="name"><?= htmlspecialchars($adminName) ?></div>
            <div class="id">Logged In User</div>
          </div>
        </div>
        <span class="badge">Can Add/Edit Schedule</span>
      </div>
    </aside>

    <!-- ── Main content ── -->
    <main class="manage-main">

      <?php if ($flashMessage): ?>
        <div class="flash-bar <?= $flashType ?>">
          <?= htmlspecialchars($flashMessage) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <div>
          <h1><i class="bi bi-exclamation-triangle me-2"></i>Room Reports</h1>
          <p>Review and resolve issues reported by instructors.</p>
        </div>
      </div>

      <div class="reports-table-wrap">
        <table class="reports-table">
          <thead>
            <tr>
              <th>Date</th>
              <th>Room</th>
              <th>Reported By</th>
              <th>Issue Type</th>
              <th>Description</th>
              <th>Status</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($reports)): ?>
              <tr class="empty-row"><td colspan="7">No reports found.</td></tr>
            <?php else: ?>
              <?php foreach ($reports as $r): ?>
                <tr>
                  <td><?= date('M d, Y h:i A', strtotime($r['created_at'])) ?></td>
                  <td><strong><?= htmlspecialchars($r['room_name']) ?></strong></td>
                  <td><?= htmlspecialchars($r['reported_by_name']) ?></td>
                  <td><?= htmlspecialchars(ucwords(str_replace('_', ' ', $r['issue_type']))) ?></td>
                  <td class="report-desc" title="<?= htmlspecialchars($r['description']) ?>">
                    <?= htmlspecialchars($r['description']) ?>
                  </td>
                  <td>
                    <span class="status-badge <?= htmlspecialchars($r['status']) ?>">
                      <?= ucfirst(htmlspecialchars($r['status'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ($r['status'] === 'pending'): ?>
                      <div class="actions-cell">
                        <form action="room_report_save.php" method="POST" onsubmit="return confirm('Mark this issue as resolved?');">
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="resolved">
                          <button type="submit" class="btn-action btn-resolve"><i class="bi bi-check-lg"></i> Resolve</button>
                        </form>
                        <form action="room_report_save.php" method="POST" onsubmit="return confirm('Dismiss this report?');">
                          <input type="hidden" name="action" value="update_status">
                          <input type="hidden" name="report_id" value="<?= (int)$r['id'] ?>">
                          <input type="hidden" name="status" value="dismissed">
                          <button type="submit" class="btn-action btn-dismiss"><i class="bi bi-x-lg"></i> Dismiss</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span class="text-muted" style="font-size:11px;">No actions available</span>
                    <?php endif; ?>
                  </td>
                </tr>
              <?php endforeach; ?>
            <?php endif; ?>
          </tbody>
        </table>
      </div>

    </main>
  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>

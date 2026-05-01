<?php
session_start();
require_once 'config.php';

if (!isset($_SESSION['user_id'], $_SESSION['user_role']) || $_SESSION['user_role'] !== 'admin') {
    header('Location: login-page.php');
    exit;
}

$adminId   = (int) $_SESSION['user_id'];
$adminName = $_SESSION['user_name'] ?? 'Admin';

$flashMessage = $_SESSION['user_manage_flash'] ?? '';
$flashType    = $_SESSION['user_manage_flash_type'] ?? 'success';
unset($_SESSION['user_manage_flash'], $_SESSION['user_manage_flash_type']);

// Fetch all users
$users = [];
$result = $conn->query("SELECT id, name, email, role FROM users ORDER BY name ASC");
if ($result instanceof mysqli_result) {
    while ($row = $result->fetch_assoc()) {
        $users[] = $row;
    }
}

$dashboardCssPath  = __DIR__ . '/includes/css/user-dashboard-css.css';
$dashboardCssInline = is_file($dashboardCssPath) ? file_get_contents($dashboardCssPath) : '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>BukSU — Manage Users</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Serif+Display&display=swap" rel="stylesheet">
  <?php if ($dashboardCssInline !== ''): ?>
    <style><?= $dashboardCssInline ?></style>
  <?php endif; ?>
  <style>
    body {
      overflow: hidden;
    }
    .body-layout {
      height: calc(100vh - var(--topbar-h));
      overflow: hidden;
    }
    .manage-main {
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
      font-size: 1.15rem;
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
    .search-toolbar .search-box {
      flex: 1;
      max-width: 360px;
    }
    .users-table-wrap {
      flex: 1;
      overflow-y: auto;
      border: 1px solid var(--border);
      border-radius: var(--radius-md);
      background: var(--white);
    }
    .users-table {
      width: 100%;
      border-collapse: collapse;
      font-size: 13px;
    }
    .users-table thead {
      position: sticky;
      top: 0;
      z-index: 2;
      background: var(--navy-pale);
    }
    .users-table thead th {
      padding: 10px 14px;
      font-size: 10px;
      font-weight: 700;
      letter-spacing: 0.06em;
      text-transform: uppercase;
      color: var(--muted);
      border-bottom: 1px solid var(--border);
      white-space: nowrap;
    }
    .users-table tbody tr {
      border-bottom: 1px solid var(--border);
      transition: background 0.12s;
    }
    .users-table tbody tr:last-child {
      border-bottom: none;
    }
    .users-table tbody tr:hover {
      background: var(--navy-pale);
    }
    .users-table td {
      padding: 10px 14px;
      vertical-align: middle;
      color: var(--text);
    }
    .user-avatar-sm {
      width: 30px;
      height: 30px;
      border-radius: 50%;
      background: var(--navy-light);
      border: 1.5px solid var(--border);
      display: inline-flex;
      align-items: center;
      justify-content: center;
      font-size: 11px;
      font-weight: 700;
      color: var(--navy);
      flex-shrink: 0;
    }
    .user-name-cell {
      display: flex;
      align-items: center;
      gap: 10px;
    }
    .user-info .uname {
      font-weight: 600;
      color: var(--text);
      line-height: 1.2;
    }
    .user-info .uemail {
      font-size: 11px;
      color: var(--muted);
    }
    .role-badge {
      font-size: 10px;
      font-weight: 700;
      padding: 3px 9px;
      border-radius: 20px;
      text-transform: capitalize;
      letter-spacing: 0.04em;
    }
    .role-badge.admin    { background: #042C530F; color: var(--navy); border: 1px solid #042C5330; }
    .role-badge.instructor { background: #1D9E750F; color: #145c44; border: 1px solid #1D9E7530; }
    .role-badge.student  { background: #3779DD0F; color: #1a4e9b; border: 1px solid #3779DD30; }
    .actions-cell {
      display: flex;
      align-items: center;
      gap: 6px;
      flex-wrap: nowrap;
    }
    .role-form select {
      font-size: 12px;
      padding: 4px 8px;
      border-radius: 6px;
      border: 1px solid var(--border);
      color: var(--text);
      background: var(--white);
    }
    .btn-save-role {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 6px;
      background: var(--navy);
      color: #fff;
      border: none;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.15s;
    }
    .btn-save-role:hover { background: var(--navy-mid); }
    .btn-del {
      font-size: 11px;
      padding: 4px 10px;
      border-radius: 6px;
      background: transparent;
      color: #c0392b;
      border: 1px solid #e0c0be;
      cursor: pointer;
      font-weight: 600;
      transition: background 0.15s;
    }
    .btn-del:hover { background: #fce9e9; }
    .empty-row td {
      text-align: center;
      color: var(--muted);
      padding: 2rem;
      font-size: 13px;
    }
    .flash-bar {
      padding: 10px 16px;
      border-radius: var(--radius-md);
      font-size: 13px;
      font-weight: 500;
      flex-shrink: 0;
    }
    .flash-bar.success { background: #e7f8f1; color: #145c44; border: 1px solid #b6e8d6; }
    .flash-bar.error   { background: #fce9e9; color: #8b1a1a; border: 1px solid #f0c4c4; }
    .stat-count {
      font-size: 12px;
      color: var(--muted);
      font-weight: 500;
    }
    .you-badge {
      font-size: 9px;
      font-weight: 700;
      padding: 1px 6px;
      border-radius: 10px;
      background: var(--navy);
      color: #fff;
      margin-left: 4px;
      vertical-align: middle;
    }
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
        <strong><?= count($users) ?></strong> Users
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
      <a class="btn btn-sm btn-outline-secondary" href="comlab-map.php">
        <i class="bi bi-arrow-left"></i> Back to Map
      </a>
      <a class="btn btn-sm btn-outline-primary" href="logout.php">Logout</a>
    </div>
  </header>

  <div class="body-layout" style="flex-direction:column;">
    <main class="manage-main">

      <?php if ($flashMessage): ?>
        <div class="flash-bar <?= $flashType ?>">
          <?= htmlspecialchars($flashMessage) ?>
        </div>
      <?php endif; ?>

      <div class="page-header">
        <div>
          <h1><i class="bi bi-people me-2"></i>Manage Users</h1>
          <p>View and manage all registered accounts on the platform.</p>
        </div>
        <span class="stat-count" id="visibleCount"><?= count($users) ?> users</span>
      </div>

      <div class="search-toolbar">
        <div class="search-box">
          <i class="bi bi-search text-muted" style="font-size:13px;"></i>
          <input type="text" id="userSearch" placeholder="Search by name, email, or role…" autocomplete="off">
        </div>
      </div>

      <div class="users-table-wrap">
        <table class="users-table" id="usersTable">
          <thead>
            <tr>
              <th>User</th>
              <th>Role</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php if (empty($users)): ?>
              <tr class="empty-row"><td colspan="3">No users found.</td></tr>
            <?php else: ?>
              <?php foreach ($users as $u): ?>
                <tr data-search="<?= strtolower(htmlspecialchars($u['name'] . ' ' . $u['email'] . ' ' . $u['role'])) ?>">
                  <td>
                    <div class="user-name-cell">
                      <div class="user-avatar-sm"><?= strtoupper(substr($u['name'], 0, 1)) ?></div>
                      <div class="user-info">
                        <div class="uname">
                          <?= htmlspecialchars($u['name']) ?>
                          <?php if ((int)$u['id'] === $adminId): ?>
                            <span class="you-badge">You</span>
                          <?php endif; ?>
                        </div>
                        <div class="uemail"><?= htmlspecialchars($u['email']) ?></div>
                      </div>
                    </div>
                  </td>
                  <td>
                    <span class="role-badge <?= htmlspecialchars($u['role']) ?>">
                      <?= ucfirst(htmlspecialchars($u['role'])) ?>
                    </span>
                  </td>
                  <td>
                    <?php if ((int)$u['id'] !== $adminId): ?>
                      <div class="actions-cell">
                        <form class="role-form" action="user_manage.php" method="POST" style="display:flex;gap:5px;align-items:center;">
                          <input type="hidden" name="action" value="change_role">
                          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                          <select name="role" aria-label="Change role for <?= htmlspecialchars($u['name']) ?>">
                            <option value="student"    <?= $u['role'] === 'student'    ? 'selected' : '' ?>>Student</option>
                            <option value="instructor" <?= $u['role'] === 'instructor' ? 'selected' : '' ?>>Instructor</option>
                            <option value="admin"      <?= $u['role'] === 'admin'      ? 'selected' : '' ?>>Admin</option>
                          </select>
                          <button type="submit" class="btn-save-role">Save</button>
                        </form>
                        <form action="user_manage.php" method="POST" onsubmit="return confirm('Delete <?= htmlspecialchars(addslashes($u['name'])) ?>? This cannot be undone.');">
                          <input type="hidden" name="action" value="delete">
                          <input type="hidden" name="user_id" value="<?= (int)$u['id'] ?>">
                          <button type="submit" class="btn-del"><i class="bi bi-trash3"></i> Delete</button>
                        </form>
                      </div>
                    <?php else: ?>
                      <span style="font-size:11px;color:var(--muted);">Cannot edit own account</span>
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
  <script>
    const searchInput  = document.getElementById('userSearch');
    const tableRows    = document.querySelectorAll('#usersTable tbody tr[data-search]');
    const visibleCount = document.getElementById('visibleCount');

    searchInput.addEventListener('input', () => {
      const q = searchInput.value.trim().toLowerCase();
      let shown = 0;
      tableRows.forEach(row => {
        const match = !q || row.dataset.search.includes(q);
        row.style.display = match ? '' : 'none';
        if (match) shown++;
      });
      visibleCount.textContent = shown + ' user' + (shown !== 1 ? 's' : '');
    });
  </script>
</body>
</html>

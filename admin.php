<?php
/**
 * admin.php
 * Admin-only dashboard for managing users and reviewing reports.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
AuthController::requireAdmin(); // Redirects non-admins to dashboard

/*
  DB CONNECTION POINT — Admin Data
  ─────────────────────────────────────────────────────────
  AdminController::getAllUsers():
    → AdminDAL::getAllUsers()
      SELECT id, name, email, is_active, created_at FROM users ORDER BY created_at DESC

  AdminController::getPendingReports():
    → AdminDAL::getPendingReports()
      SELECT r.id, r.reason, r.created_at,
             reporter.name AS reporter_name, reported.name AS reported_name
      FROM reports r
      JOIN users reporter ON r.reporter_id = reporter.id
      JOIN users reported ON r.reported_id = reported.id
      WHERE r.status = 'pending'
      ORDER BY r.created_at ASC
  ─────────────────────────────────────────────────────────
*/

// --- Placeholder data (remove once AdminController is wired) ---
$users = [
  ['id'=>1, 'name'=>'Alice Ryan',   'email'=>'alice@example.com',  'is_active'=>1, 'created_at'=>'2025-01-10'],
  ['id'=>2, 'name'=>'Bob Walsh',    'email'=>'bob@example.com',    'is_active'=>1, 'created_at'=>'2025-01-12'],
  ['id'=>3, 'name'=>'Carol Doyle',  'email'=>'carol@example.com',  'is_active'=>0, 'created_at'=>'2025-01-15'],
  ['id'=>4, 'name'=>'Dan Murphy',   'email'=>'dan@example.com',    'is_active'=>1, 'created_at'=>'2025-01-18'],
];
$reports = [
  ['id'=>1, 'reporter_name'=>'Alice Ryan', 'reported_name'=>'Bob Walsh', 'reason'=>'Inappropriate messages', 'created_at'=>'2025-02-01'],
];

/* Uncomment when controllers are ready:
$ctrl    = new AdminController();
$users   = $ctrl->getAllUsers();
$reports = $ctrl->getPendingReports();
*/

$pageTitle = 'Admin';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/admin.php"     class="nav-item active"><i class="fas fa-shield-alt"></i><span>Admin</span></a>
      <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Back to App</span></a>
    </nav>
  </aside>

  <main class="hm-main">

    <div class="section-header">
      <h2 class="section-title">Admin Dashboard</h2>
      <p class="section-subtitle">User management &amp; moderation</p>
    </div>

    <!-- Stats row -->
    <div class="stats-row" style="margin-bottom:24px;">
      <div class="hm-card stat-card">
        <div class="stat-value"><?= count($users) ?></div>
        <div class="stat-label">Total Users</div>
      </div>
      <div class="hm-card stat-card">
        <div class="stat-value"><?= count(array_filter($users, fn($u) => $u['is_active'])) ?></div>
        <div class="stat-label">Active Users</div>
      </div>
      <div class="hm-card stat-card">
        <div class="stat-value"><?= count($reports) ?></div>
        <div class="stat-label">Pending Reports</div>
      </div>
    </div>

    <!-- Users table -->
    <div class="hm-card" style="margin-bottom:20px;overflow-x:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">All Users</h3>
        <div style="position:relative;">
          <i class="fas fa-search" style="position:absolute;left:11px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:12px;"></i>
          <input type="text" id="userSearch" class="form-input" placeholder="Search users…"
                 style="padding:8px 12px 8px 32px;font-size:13px;width:220px;"
                 oninput="filterTable(this.value)">
        </div>
      </div>

      <table class="admin-table" id="usersTable">
        <thead>
          <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Email</th>
            <th>Status</th>
            <th>Joined</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
        <?php foreach ($users as $u): ?>
          <tr>
            <td style="color:var(--text-muted);">#<?= $u['id'] ?></td>
            <td style="font-weight:600;"><?= htmlspecialchars($u['name']) ?></td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['email']) ?></td>
            <td>
              <span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
              </span>
            </td>
            <td style="color:var(--text-secondary);"><?= htmlspecialchars($u['created_at']) ?></td>
            <td>
              <!--
                DB CONNECTION POINT — Suspend / Reactivate
                ─────────────────────────────────────────────────────────
                adminAction('suspend'|'reactivate', userId) below calls:
                  POST /api/admin.php { action, user_id }
                AdminController::suspendUser($id)  → UserDAL::setActive($id, 0)
                AdminController::reactivateUser($id) → UserDAL::setActive($id, 1)
                ─────────────────────────────────────────────────────────
              -->
              <?php if ($u['is_active']): ?>
                <button class="btn-outline" style="color:#f87171;border-color:rgba(239,68,68,0.3);"
                        onclick="adminAction('suspend', <?= $u['id'] ?>)">
                  <i class="fas fa-ban"></i> Suspend
                </button>
              <?php else: ?>
                <button class="btn-outline" style="color:#34d399;border-color:rgba(16,185,129,0.3);"
                        onclick="adminAction('reactivate', <?= $u['id'] ?>)">
                  <i class="fas fa-check"></i> Reactivate
                </button>
              <?php endif; ?>
              <a href="/profile.php?id=<?= $u['id'] ?>" class="btn-outline" style="margin-left:4px;font-size:12px;padding:6px 10px;">
                <i class="fas fa-eye"></i>
              </a>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>

    <!-- Reports table -->
    <div class="hm-card" style="overflow-x:auto;">
      <h3 style="margin-bottom:16px;">
        Pending Reports
        <?php if (count($reports)): ?>
          <span class="badge badge-red" style="margin-left:8px;font-size:12px;"><?= count($reports) ?></span>
        <?php endif; ?>
      </h3>

      <?php if (empty($reports)): ?>
        <div style="text-align:center;padding:32px 0;color:var(--text-muted);">
          <i class="fas fa-check-circle" style="font-size:28px;margin-bottom:10px;color:rgba(16,185,129,0.4);display:block;"></i>
          No pending reports. All clear!
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Reporter</th>
              <th>Reported</th>
              <th>Reason</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($reports as $r): ?>
            <tr>
              <td style="color:var(--text-muted);">#<?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['reporter_name']) ?></td>
              <td style="font-weight:600;"><?= htmlspecialchars($r['reported_name']) ?></td>
              <td style="color:var(--text-secondary);max-width:240px;">
                <?= htmlspecialchars($r['reason']) ?>
              </td>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars($r['created_at']) ?></td>
              <td>
                <!--
                  DB CONNECTION POINT — Resolve Reports
                  ─────────────────────────────────────────────────────────
                  resolveReport(id, resolution) calls:
                    POST /api/admin.php { action:'resolve_report', report_id, resolution }
                  AdminController::resolveReport($id, $resolution):
                    → AdminDAL::updateReportStatus($id, 'actioned'|'dismissed')
                       UPDATE reports SET status=:status WHERE id=:id
                    If 'actioned': optionally also suspend the reported user
                  ─────────────────────────────────────────────────────────
                -->
                <button class="btn-primary" style="font-size:12px;padding:7px 12px;"
                        onclick="resolveReport(<?= $r['id'] ?>, 'actioned')">
                  <i class="fas fa-gavel"></i> Action
                </button>
                <button class="btn-outline" style="font-size:12px;padding:7px 12px;"
                        onclick="resolveReport(<?= $r['id'] ?>, 'dismissed')">
                  Dismiss
                </button>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<script>
  /*
    DB CONNECTION POINT — adminAction()
    POST /api/admin.php { action: 'suspend'|'reactivate', user_id }
    Reloads the page on success.
  */
  function adminAction(action, userId) {
    if (!confirm(`Are you sure you want to ${action} this user?`)) return;
    fetch('/api/admin.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `action=${action}&user_id=${userId}`
    }).then(r => r.json())
      .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + (data.error ?? 'Unknown error'));
      });
  }

  /*
    DB CONNECTION POINT — resolveReport()
    POST /api/admin.php { action: 'resolve_report', report_id, resolution }
  */
  function resolveReport(reportId, resolution) {
    if (!confirm(`Mark this report as "${resolution}"?`)) return;
    fetch('/api/admin.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: `action=resolve_report&report_id=${reportId}&resolution=${resolution}`
    }).then(r => r.json())
      .then(data => {
        if (data.success) location.reload();
        else alert('Error: ' + (data.error ?? 'Unknown error'));
      });
  }

  /* Simple client-side table filter */
  function filterTable(query) {
    const q    = query.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

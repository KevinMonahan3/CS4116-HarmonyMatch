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

$ctrl    = new AdminController();
$users   = $ctrl->getAllUsers();
$reportStatus = (string)($_GET['report_status'] ?? 'pending');
$reportQuery = trim((string)($_GET['report_query'] ?? ''));
$reports = $ctrl->getPendingReports($reportStatus, $reportQuery);
$auditLogs = $ctrl->getAuditLogs(12);

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
              <button class="btn-outline" style="margin-left:4px;font-size:12px;padding:6px 10px;"
                      onclick='editUserProfile(<?= json_encode([
                        'id' => (int)$u['id'],
                        'name' => (string)($u['name'] ?? ''),
                        'bio' => (string)($u['bio'] ?? ''),
                        'location' => (string)($u['location'] ?? ''),
                      ], JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) ?>)'>
                <i class="fas fa-pen"></i>
              </button>
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
      <div style="display:flex;justify-content:space-between;align-items:flex-end;gap:16px;flex-wrap:wrap;margin-bottom:16px;">
        <div>
          <h3 style="margin-bottom:4px;">Reports</h3>
          <p style="color:var(--text-secondary);font-size:13px;">Filter moderation queue and review recent activity.</p>
        </div>
        <form method="get" class="admin-toolbar">
          <div class="form-group">
            <label class="form-label">Status</label>
            <select name="report_status" class="form-input">
              <option value="pending" <?= $reportStatus === 'pending' ? 'selected' : '' ?>>Open</option>
              <option value="reviewing" <?= $reportStatus === 'reviewing' ? 'selected' : '' ?>>Reviewing</option>
              <option value="resolved" <?= $reportStatus === 'resolved' ? 'selected' : '' ?>>Resolved</option>
              <option value="dismissed" <?= $reportStatus === 'dismissed' ? 'selected' : '' ?>>Dismissed</option>
              <option value="all" <?= $reportStatus === 'all' ? 'selected' : '' ?>>All</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Search</label>
            <input type="text" name="report_query" class="form-input" value="<?= htmlspecialchars($reportQuery) ?>" placeholder="Reporter, reported, reason">
          </div>
          <div class="form-group">
            <button class="btn-primary" type="submit"><i class="fas fa-filter"></i> Filter</button>
          </div>
        </form>
      </div>

      <?php if (empty($reports)): ?>
        <div style="text-align:center;padding:32px 0;color:var(--text-muted);">
          <i class="fas fa-check-circle" style="font-size:28px;margin-bottom:10px;color:rgba(16,185,129,0.4);display:block;"></i>
          No reports match the current filters.
        </div>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>ID</th>
              <th>Reporter</th>
              <th>Reported</th>
              <th>Reason</th>
              <th>Status</th>
              <th>Date</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($reports as $r): ?>
            <tr>
              <td style="color:var(--text-muted);">#<?= $r['id'] ?></td>
              <td><?= htmlspecialchars($r['reporter_name']) ?></td>
              <td style="font-weight:600;">
                <a href="/profile.php?id=<?= (int)$r['reported_user_id'] ?>"><?= htmlspecialchars($r['reported_name']) ?></a>
              </td>
              <td style="color:var(--text-secondary);max-width:240px;">
                <?= htmlspecialchars($r['message'] ?: ucwords(str_replace('_', ' ', (string)$r['reason']))) ?>
              </td>
              <td>
                <span class="badge"><?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$r['status']))) ?></span>
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

    <div class="hm-card" style="margin-top:20px;overflow-x:auto;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">Recent Admin Activity</h3>
        <span class="badge" style="font-size:12px;"><?= count($auditLogs) ?> entries</span>
      </div>

      <?php if (empty($auditLogs)): ?>
        <p style="color:var(--text-muted);font-size:13px;">No audit log entries yet.</p>
      <?php else: ?>
        <table class="admin-table">
          <thead>
            <tr>
              <th>When</th>
              <th>Admin</th>
              <th>Action</th>
              <th>Target</th>
            </tr>
          </thead>
          <tbody>
          <?php foreach ($auditLogs as $log): ?>
            <tr>
              <td style="color:var(--text-secondary);"><?= htmlspecialchars((string)$log['created_at']) ?></td>
              <td><?= htmlspecialchars((string)($log['admin_name'] ?: 'Admin')) ?></td>
              <td style="font-weight:600;"><?= htmlspecialchars((string)$log['action_type']) ?></td>
              <td style="color:var(--text-secondary);">
                <?= htmlspecialchars((string)$log['target_type']) ?> #<?= htmlspecialchars((string)$log['target_id']) ?>
              </td>
            </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      <?php endif; ?>
    </div>

  </main>
</div>

<div class="modal-backdrop" id="editUserModal">
  <div class="modal-card">
    <div class="modal-head">
      <div>
        <h3>Edit User Profile</h3>
        <p style="font-size:13px;color:var(--text-secondary);">Make moderation edits without leaving the dashboard.</p>
      </div>
      <button class="btn-outline" type="button" onclick="closeEditModal()">Close</button>
    </div>

    <form id="editUserForm">
      <input type="hidden" name="user_id" id="editUserId">
      <div class="form-group">
        <label class="form-label">Display Name</label>
        <input type="text" class="form-input" name="name" id="editUserName" maxlength="80" required>
      </div>
      <div class="form-group">
        <label class="form-label">Location</label>
        <input type="text" class="form-input" name="location" id="editUserLocation">
      </div>
      <div class="form-group">
        <label class="form-label">Bio</label>
        <textarea class="form-input" name="bio" id="editUserBio" rows="5" maxlength="1000"></textarea>
      </div>
      <div style="display:flex;justify-content:flex-end;gap:10px;">
        <button class="btn-outline" type="button" onclick="closeEditModal()">Cancel</button>
        <button class="btn-primary" type="submit"><i class="fas fa-save"></i> Save Changes</button>
      </div>
    </form>
  </div>
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

  function editUserProfile(user) {
    document.getElementById('editUserId').value = user.id ?? '';
    document.getElementById('editUserName').value = user.name ?? '';
    document.getElementById('editUserLocation').value = user.location ?? '';
    document.getElementById('editUserBio').value = user.bio ?? '';
    document.getElementById('editUserModal').classList.add('open');
  }

  function closeEditModal() {
    document.getElementById('editUserModal').classList.remove('open');
  }

  /* Simple client-side table filter */
  function filterTable(query) {
    const q    = query.toLowerCase();
    const rows = document.querySelectorAll('#usersTable tbody tr');
    rows.forEach(row => {
      row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
    });
  }

  document.getElementById('editUserForm').addEventListener('submit', async e => {
    e.preventDefault();
    const body = new URLSearchParams(new FormData(e.target));
    body.set('action', 'update_profile');

    const res = await fetch('/api/admin.php', {
      method: 'POST',
      headers: {'Content-Type': 'application/x-www-form-urlencoded'},
      body: body.toString()
    });
    const data = await res.json();

    if (data.success) {
      window.location.reload();
      return;
    }
    alert('Error: ' + (data.error ?? 'Unknown error'));
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

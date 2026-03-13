<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/AdminController.php';
AuthController::requireAdmin();

$ctrl    = new AdminController();
$users   = $ctrl->getAllUsers();
$reports = $ctrl->getPendingReports();

$pageTitle = 'Admin';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/admin.php" class="nav-item active"><i class="fas fa-shield-alt"></i><span>Admin</span></a>
            <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Back to App</span></a>
        </nav>
    </aside>

    <main class="hm-main">
        <div class="section-header">
            <h2 class="section-title">Admin Dashboard</h2>
        </div>

        <!-- Stats row -->
        <div class="stats-row">
            <div class="hm-card stat-card">
                <div class="stat-value"><?= count($users) ?></div>
                <div class="stat-label">Total Users</div>
            </div>
            <div class="hm-card stat-card">
                <div class="stat-value"><?= count($reports) ?></div>
                <div class="stat-label">Pending Reports</div>
            </div>
        </div>

        <!-- Users Table -->
        <div class="hm-card" style="margin-top:1.5rem;">
            <h3>Users</h3>
            <table class="admin-table">
                <thead>
                    <tr><th>ID</th><th>Name</th><th>Email</th><th>Status</th><th>Joined</th><th>Actions</th></tr>
                </thead>
                <tbody>
                <?php foreach ($users as $u): ?>
                    <tr>
                        <td><?= $u['id'] ?></td>
                        <td><?= htmlspecialchars($u['name']) ?></td>
                        <td><?= htmlspecialchars($u['email']) ?></td>
                        <td><span class="badge <?= $u['is_active'] ? 'badge-green' : 'badge-red' ?>">
                            <?= $u['is_active'] ? 'Active' : 'Suspended' ?>
                        </span></td>
                        <td><?= htmlspecialchars($u['created_at']) ?></td>
                        <td>
                            <?php if ($u['is_active']): ?>
                                <button class="btn-outline" onclick="adminAction('suspend', <?= $u['id'] ?>)">Suspend</button>
                            <?php else: ?>
                                <button class="btn-outline" onclick="adminAction('reactivate', <?= $u['id'] ?>)">Reactivate</button>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Reports Table -->
        <div class="hm-card" style="margin-top:1.5rem;">
            <h3>Pending Reports</h3>
            <?php if (empty($reports)): ?>
                <p style="color:var(--text-secondary);">No pending reports.</p>
            <?php else: ?>
                <table class="admin-table">
                    <thead>
                        <tr><th>ID</th><th>Reporter</th><th>Reported</th><th>Reason</th><th>Date</th><th>Actions</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($reports as $r): ?>
                        <tr>
                            <td><?= $r['id'] ?></td>
                            <td><?= htmlspecialchars($r['reporter_name']) ?></td>
                            <td><?= htmlspecialchars($r['reported_name']) ?></td>
                            <td><?= htmlspecialchars($r['reason']) ?></td>
                            <td><?= htmlspecialchars($r['created_at']) ?></td>
                            <td>
                                <button class="btn-primary" onclick="resolveReport(<?= $r['id'] ?>, 'actioned')">Action</button>
                                <button class="btn-outline" onclick="resolveReport(<?= $r['id'] ?>, 'dismissed')">Dismiss</button>
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
function adminAction(action, userId) {
    if (!confirm(`Are you sure you want to ${action} this user?`)) return;
    fetch('/api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=${action}&user_id=${userId}`
    }).then(() => location.reload());
}
function resolveReport(reportId, resolution) {
    fetch('/api/admin.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=resolve_report&report_id=${reportId}&resolution=${resolution}`
    }).then(() => location.reload());
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

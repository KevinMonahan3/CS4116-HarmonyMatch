<?php
/**
 * dashboard.php
 * Main discovery feed — shows music-compatible match cards.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
AuthController::requireLogin();

$viewerProfile = (new UserController())->getProfile((int)$_SESSION['user_id']) ?: [];
$preferenceSummary = implode(' · ', array_filter([
    'Interested in ' . match ((string)($viewerProfile['desired_gender'] ?? 'everyone')) {
        'male' => 'men',
        'female' => 'women',
        'non_binary' => 'non-binary people',
        'other' => 'other genders',
        default => 'everyone',
    },
    ((int)($viewerProfile['min_age_pref'] ?? 18)) . '–' . ((int)($viewerProfile['max_age_pref'] ?? 40)) . ' age range',
    match ((string)($viewerProfile['location_scope'] ?? 'anywhere')) {
        'same_city' => 'same city',
        'same_country' => 'same country',
        default => 'any location',
    },
    !empty($viewerProfile['seeking_type'])
        ? ucfirst(str_replace('_', ' ', (string)$viewerProfile['seeking_type']))
        : null,
]));

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <!-- Sidebar navigation -->
  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"  class="nav-item active"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"     class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/likes.php"      class="nav-item"><i class="fas fa-heart"></i><span>Likes</span></a>
      <a href="/chat.php"       class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <!-- Main content -->
  <main class="hm-main">

    <div class="section-header" style="display:flex;align-items:flex-start;justify-content:space-between;flex-wrap:wrap;gap:0.75rem;">
    <div>
        <h2 class="section-title">Discover Matches</h2>
        <p class="section-subtitle">One profile at a time, ranked by music compatibility and your dating preferences.</p>
        <p class="text-sm text-muted mt-1"><?= htmlspecialchars($preferenceSummary) ?></p>
    </div>
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-self:center;">
      <a href="/profile-own.php#preferences" class="btn-outline" style="display:flex;align-items:center;gap:8px;white-space:nowrap;">
        <i class="fas fa-sliders-h"></i> Edit Preferences
      </a>
      <button class="btn-outline" id="refreshMatchesBtn" onclick="refreshMatches()" style="display:flex;align-items:center;gap:8px;white-space:nowrap;">
          <i class="fas fa-redo" id="refreshIcon"></i> Refresh Queue
      </button>
    </div>
    </div>

    <div class="discover-shell">
      <div class="hm-card discover-status-card">
        <div>
          <h3 style="margin:0 0 6px;">Your queue</h3>
          <p id="discoverQueueMeta" class="text-sm text-muted">Preparing a ranked list of profiles…</p>
        </div>
      </div>
      <div id="matchGrid" class="discover-feed"></div>
    </div>

  </main>
</div>

<script>
async function refreshMatches() {
    const btn  = document.getElementById('refreshMatchesBtn');
    const icon = document.getElementById('refreshIcon');
    btn.disabled = true;
    icon.style.animation = 'spin 0.8s linear infinite';

    try {
        await fetch('/api/matches.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=reset_skips'
        });
        window.location.href = '/dashboard.php';
    } finally {
        btn.disabled = false;
        icon.style.animation = '';
    }
}
</script>
<style>@keyframes spin { to { transform: rotate(360deg); } }</style>

<?php $extraScript = 'dashboard.js'; include __DIR__ . '/includes/footer.php'; ?>

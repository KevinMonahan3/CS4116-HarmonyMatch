<?php
/**
 * dashboard.php
 * Main discovery feed — shows music-compatible match cards.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

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
        <p class="section-subtitle">People who share your musical soul</p>
    </div>
    <button class="btn-outline" id="refreshMatchesBtn" onclick="refreshMatches()" style="display:flex;align-items:center;gap:8px;white-space:nowrap;align-self:center;">
        <i class="fas fa-redo" id="refreshIcon"></i> Refresh Matches
    </button>
</div>

    <!--
      DB CONNECTION POINT — Match Grid
      ─────────────────────────────────────────────────────────
      The <div id="matchGrid"> below is populated by assets/js/dashboard.js
      via a fetch() call to /api/matches.php?action=discover

      In /api/matches.php you should:
        1. Verify the session (AuthController::requireLogin())
        2. Call MatchController::getDiscoverFeed($currentUserId)
           which in turn calls MatchDAL::getDiscoverFeed() — a SQL query that:
             • Excludes users the current user has already swiped on
             • Excludes blocked/suspended users
             • JOINs on genres/artists to compute a compatibility score
             • Returns: id, name, profile_photo, location, age, genres[], compat_score
        3. json_encode() and return the array

      dashboard.js then renders each user as a .match-card element.
    ─────────────────────────────────────────────────────────
    -->
    <div id="matchGrid" class="match-grid">

      <!-- Static placeholder cards — replaced by JS once DB is wired -->
      <div class="match-card">
        <div class="match-card-photo">
          <div class="artist-bg-card"><i class="fas fa-music"></i><span>Arctic Monkeys</span></div>
          <span class="compat-badge">87%</span>
        </div>
        <div class="match-card-body">
          <div class="match-card-name">Alex M.</div>
          <div class="match-card-meta">24 · Dublin</div>
          <div class="top-artist-label"><i class="fas fa-music"></i> Arctic Monkeys</div>
        </div>
        <div class="match-card-actions">
          <button class="action-btn pass-btn"><i class="fas fa-times"></i><span>Skip</span></button>
          <button class="action-btn info-btn"><i class="fas fa-user"></i><span>Info</span></button>
          <button class="action-btn like-btn"><i class="fas fa-heart"></i><span>Like</span></button>
        </div>
      </div>

      <div class="match-card">
        <div class="match-card-photo">
          <div class="artist-bg-card"><i class="fas fa-music"></i><span>Kendrick Lamar</span></div>
          <span class="compat-badge">72%</span>
        </div>
        <div class="match-card-body">
          <div class="match-card-name">Sam K.</div>
          <div class="match-card-meta">27 · Cork</div>
          <div class="top-artist-label"><i class="fas fa-music"></i> Kendrick Lamar</div>
        </div>
        <div class="match-card-actions">
          <button class="action-btn pass-btn"><i class="fas fa-times"></i><span>Skip</span></button>
          <button class="action-btn info-btn"><i class="fas fa-user"></i><span>Info</span></button>
          <button class="action-btn like-btn"><i class="fas fa-heart"></i><span>Like</span></button>
        </div>
      </div>

      <div class="match-card">
        <div class="match-card-photo">
          <div class="artist-bg-card"><i class="fas fa-music"></i><span>Billie Eilish</span></div>
          <span class="compat-badge">61%</span>
        </div>
        <div class="match-card-body">
          <div class="match-card-name">Jordan L.</div>
          <div class="match-card-meta">22 · Galway</div>
          <div class="top-artist-label"><i class="fas fa-music"></i> Billie Eilish</div>
        </div>
        <div class="match-card-actions">
          <button class="action-btn pass-btn"><i class="fas fa-times"></i><span>Skip</span></button>
          <button class="action-btn info-btn"><i class="fas fa-user"></i><span>Info</span></button>
          <button class="action-btn like-btn"><i class="fas fa-heart"></i><span>Like</span></button>
        </div>
      </div>

    </div><!-- /#matchGrid -->

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

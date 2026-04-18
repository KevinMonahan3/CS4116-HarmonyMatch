<?php
/**
 * profile.php
 * Displays another user's public profile with compatibility score and action buttons.
 * URL: /profile.php?id=<userId>
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/MatchController.php';
AuthController::requireLogin();

/*
  DB CONNECTION POINT — Load Profile
  ─────────────────────────────────────────────────────────
  $viewUserId comes from the URL query param.
  UserController::getProfile($viewUserId) should call:
    UserDAL::getById($id)          — SELECT from users WHERE id = :id
    MusicDAL::getGenresForUser($id) — SELECT genres for this user
    MusicDAL::getArtistsForUser($id)— SELECT artists for this user
  Returns an associative array: [id, name, profile_photo, location, bio, genres[], artists[]]

  MatchController::computeCompatibility($myId, $viewUserId) should call:
    MatchDAL::computeCompatibility() — overlap scoring between the two users' music data
  Returns an integer 0–100.
  ─────────────────────────────────────────────────────────
*/
$viewUserId = (int)($_GET['id'] ?? 0);
if (!$viewUserId) { header('Location: /dashboard.php'); exit; }

$userCtrl  = new UserController();
$matchCtrl = new MatchController();
$profile   = $userCtrl->getProfile($viewUserId);
if (!$profile) { header('Location: /dashboard.php'); exit; }
$myId  = (int)$_SESSION['user_id'];
$score = $matchCtrl->computeCompatibility($myId, $viewUserId);
$topSongTitle = (string)($profile['songs'][0]['title'] ?? '');
$topSongArtist = (string)($profile['songs'][0]['artist'] ?? ($profile['artists'][0]['name'] ?? ''));
$spotifySeedArtist = $topSongArtist !== '' ? $topSongArtist : (string)($profile['artists'][0]['name'] ?? '');

$pageTitle = htmlspecialchars($profile['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/likes.php"       class="nav-item"><i class="fas fa-heart"></i><span>Likes</span></a>
      <a href="/chat.php"        class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <main class="hm-main">

    <!-- Profile header card -->
    <div class="hm-card profile-header" style="margin-bottom:20px;">

      <!-- Photo -->
      <div class="profile-photo-lg">
        <?php if ($profile['profile_photo']): ?>
          <img src="<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Profile photo">
        <?php else: ?>
          <div class="avatar-placeholder"><?= htmlspecialchars(substr($profile['name'], 0, 1)) ?></div>
        <?php endif; ?>
      </div>

      <!-- Info -->
      <div class="profile-info">
        <h2><?= htmlspecialchars($profile['name']) ?></h2>
        <p style="margin-bottom:12px;">
          <i class="fas fa-map-marker-alt" style="color:var(--accent-cyan);margin-right:5px;"></i>
          <?= htmlspecialchars($profile['location'] ?? 'Location not set') ?>
        </p>

        <!-- Compatibility bar -->
        <div class="compat-bar" style="max-width:300px;">
          <span class="compat-label">Music Match</span>
          <div class="compat-track">
            <div class="compat-fill" style="width:<?= $score ?>%"></div>
          </div>
          <span class="compat-value"><?= $score ?>%</span>
        </div>
      </div>

      <!-- Action buttons -->
      <!--
        DB CONNECTION POINT — Like / Message buttons
        ─────────────────────────────────────────────────────────
        Like button calls swipe() below → POST /api/matches.php
          action=swipe, to_user_id=<id>, action_type=like|pass

        MatchController::recordSwipe($fromId, $toId, $action):
          → MatchDAL::insertSwipe() — INSERT INTO swipes
          → MatchDAL::checkMutualLike() — check if $toId already liked $fromId
          → if mutual: MatchDAL::createMatch() — INSERT INTO matches
          → return { is_match: bool }

        Message link → /chat.php?with=<viewUserId>
        ─────────────────────────────────────────────────────────
      -->
      <div class="profile-actions">
        <button class="btn-outline" style="gap:8px;" onclick="swipe(<?= $viewUserId ?>, 'skip')">
          <i class="fas fa-times"></i> Skip
        </button>
        <button class="btn-primary" style="gap:8px;" onclick="swipe(<?= $viewUserId ?>, 'like')">
          <i class="fas fa-heart"></i> Like
        </button>
        <button class="btn-outline" style="padding:10px 14px;color:var(--text-muted);" onclick="blockUser(<?= $viewUserId ?>)" title="Block user">
          <i class="fas fa-user-slash"></i>
        </button>
        <button class="btn-outline" style="padding:10px 14px;color:var(--text-muted);" onclick="reportUser(<?= $viewUserId ?>)" title="Report user">
          <i class="fas fa-flag"></i>
        </button>
      </div>

    </div>

    <!-- About card -->
    <div class="hm-card" style="margin-bottom:16px;">
      <h3>About</h3>
      <p style="color:var(--text-secondary);line-height:1.7;">
        <?= htmlspecialchars($profile['bio'] ?? 'No bio yet.') ?>
      </p>
    </div>

    <!-- Music taste card -->
    <div class="hm-card">
      <h3>Music Taste</h3>

      <?php if (!empty($profile['genres'])): ?>
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:8px;">Genres</p>
        <div class="tag-container" style="margin-bottom:16px;">
          <?php foreach ($profile['genres'] as $g): ?>
            <span class="tag tag-purple"><?= htmlspecialchars($g['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <?php if (!empty($profile['artists'])): ?>
        <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:8px;">Favourite Artists</p>
        <div class="tag-container">
          <?php foreach ($profile['artists'] as $a): ?>
            <span class="tag tag-cyan"><?= htmlspecialchars($a['name']) ?></span>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <div class="hm-card" style="margin-top:16px;">
      <h3>Listen To Their Vibe</h3>
      <p style="color:var(--text-secondary);margin-bottom:14px;">Preview this match through a Spotify embed.</p>
      <div id="spotifyEmbedBox"
           data-track="<?= htmlspecialchars($topSongTitle) ?>"
           data-artist="<?= htmlspecialchars($spotifySeedArtist) ?>">
        <p style="color:var(--text-muted);font-size:13px;">Loading player…</p>
      </div>
    </div>

  </main>
</div>

<script>
/*
  DB CONNECTION POINT — swipe()
  Sends a like/pass action to the API.
  On a mutual like, shows a match notification then redirects to dashboard.
*/
async function swipe(toUserId, action) {
  const res  = await fetch('/api/matches.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=swipe&to_user_id=${toUserId}&action_type=${action}`
  });
  const data = await res.json();
  if (data.is_match) {
    window.location = '/chat.php?with=' + toUserId;
  } else {
    window.location = '/dashboard.php';
  }
}

/*
  DB CONNECTION POINT — reportUser()
  Should POST to /api/reports.php with { reported_id, reason }.
  ReportController::create() → ReportDAL::insert()
*/
function reportUser(userId) {
  const reason = prompt('Why are you reporting this user?');
  if (!reason) return;
  fetch('/api/reports.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=report&reported_id=${userId}&reason=${encodeURIComponent(reason)}`
  }).then(async res => {
    const data = await res.json();
    alert(data.success ? 'Report submitted. Thank you.' : `Error: ${data.error ?? 'Unable to submit report.'}`);
  });
}

function blockUser(userId) {
  if (!confirm('Block this user? They will no longer be able to interact with you.')) return;
  fetch('/api/reports.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
    body: `action=block&blocked_id=${userId}`
  }).then(async res => {
    const data = await res.json();
    if (data.success) {
      alert('User blocked.');
      window.location = '/dashboard.php';
      return;
    }
    alert(`Error: ${data.error ?? 'Unable to block user.'}`);
  });
}

async function loadSpotifyEmbed() {
  const box = document.getElementById('spotifyEmbedBox');
  if (!box) return;

  const track = box.dataset.track || '';
  const artist = box.dataset.artist || '';
  if (!track && !artist) {
    box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No music data available for an embed yet.</p>';
    return;
  }

  const params = new URLSearchParams({ action: 'spotify_embed', track, artist });
  const data = await fetch('/api/music.php?' + params.toString()).then(r => r.json()).catch(() => null);
  const result = data?.result;

  if (!data?.success || !result) {
    box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Spotify player could not be loaded.</p>';
    return;
  }

  if (!result.configured) {
    box.innerHTML = `
      <p style="color:var(--text-muted);font-size:13px;margin-bottom:8px;">Spotify app credentials are not configured yet.</p>
      <p style="color:var(--text-secondary);font-size:13px;">Add Spotify client credentials in <code>config/music.local.php</code> or VM environment variables to enable embeds.</p>`;
    return;
  }

  if (!result.found || !result.embed_url) {
    box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No Spotify match was found for this profile yet.</p>';
    return;
  }

  box.innerHTML = `
    <div class="spotify-embed-wrap">
      <iframe
        src="${result.embed_url}"
        width="100%"
        height="${result.type === 'artist' ? '352' : '152'}"
        frameborder="0"
        allowfullscreen
        allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
        loading="lazy"
        style="border-radius:12px;">
      </iframe>
      <p style="margin-top:10px;color:var(--text-secondary);font-size:13px;">
        ${result.title ? result.title : 'Spotify'}${result.subtitle ? ' · ' + result.subtitle : ''}
      </p>
    </div>`;
}

document.addEventListener('DOMContentLoaded', loadSpotifyEmbed);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

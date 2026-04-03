<?php
/**
 * profile-own.php
 * The current user's own profile editor — name, bio, location, photo upload.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
AuthController::requireLogin();

$userId = (int)$_SESSION['user_id'];

/*
  DB CONNECTION POINT — Load Own Profile
  ─────────────────────────────────────────────────────────
  UserController::getProfile($userId) should call:
    UserDAL::getById($userId)           — SELECT user row
    MusicDAL::getGenresForUser($userId) — SELECT genres
    MusicDAL::getArtistsForUser($userId)— SELECT artists
  ─────────────────────────────────────────────────────────
*/

// --- Placeholder data (remove once controllers are wired) ---
$profile = [
  'name'          => 'Your Name',
  'profile_photo' => null,
  'location'      => '',
  'bio'           => '',
  'genres'        => [],
  'artists'       => [],
];

/* Uncomment when controller is ready:
$ctrl    = new UserController();
$profile = $ctrl->getProfile($userId);
*/

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/chat.php"        class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item active"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <main class="hm-main">

    <div class="section-header">
      <h2 class="section-title">My Profile</h2>
      <p class="section-subtitle">Keep your details up to date</p>
    </div>

    <!-- Profile photo + basic info -->
    <div class="hm-card" style="display:flex;gap:24px;align-items:flex-start;flex-wrap:wrap;margin-bottom:16px;">

      <!-- Avatar -->
      <div style="flex-shrink:0;text-align:center;">
        <div class="profile-photo-lg" style="margin:0 auto 12px;">
          <?php if ($profile['profile_photo']): ?>
            <img src="<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Your photo">
          <?php else: ?>
            <div class="avatar-placeholder"><?= htmlspecialchars(substr($profile['name'], 0, 1)) ?></div>
          <?php endif; ?>
        </div>
        <!--
          DB CONNECTION POINT — Photo Upload
          ─────────────────────────────────────────────────────────
          Wire this input to POST /api/users.php?action=upload_photo
          using FormData + fetch (multipart/form-data).
          UserController::uploadPhoto():
            → validates file type / size
            → moves to /assets/img/uploads/<userId>.jpg
            → UserDAL::updatePhoto($userId, $path)
          ─────────────────────────────────────────────────────────
        -->
        <label class="btn-outline" style="font-size:13px;cursor:pointer;">
          <i class="fas fa-camera"></i> Change Photo
          <input type="file" id="photoInput" accept="image/*" style="display:none;">
        </label>
      </div>

      <!-- Edit form -->
      <div style="flex:1;min-width:220px;">
        <form id="profileForm">

          <div class="form-group">
            <label class="form-label">Display Name</label>
            <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($profile['name']) ?>" required>
          </div>

          <div class="form-group">
            <label class="form-label">Location</label>
            <div style="position:relative;">
              <i class="fas fa-map-marker-alt" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
              <input type="text" name="location" class="form-input" style="padding-left:36px;"
                     value="<?= htmlspecialchars($profile['location'] ?? '') ?>" placeholder="e.g. Dublin, Ireland">
            </div>
          </div>

          <div class="form-group">
            <label class="form-label">Bio</label>
            <textarea name="bio" class="form-input" rows="4"
                      placeholder="Tell potential matches a bit about yourself…"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
          </div>

          <!--
            DB CONNECTION POINT — Save Profile
            ─────────────────────────────────────────────────────────
            Wire to /api/users.php?action=update_profile via fetch() in profile.js.
            UserController::updateProfile($userId, $data):
              → validates input
              → UserDAL::update($userId, ['name'=>..., 'location'=>..., 'bio'=>...])
                 UPDATE users SET name=:name, location=:location, bio=:bio WHERE id=:id
            Returns JSON { success: true } or { error: '...' }
            ─────────────────────────────────────────────────────────
          -->
          <p id="profileMsg" style="display:none;font-size:13.5px;margin-bottom:12px;"></p>
          <button type="submit" class="btn-primary">
            <i class="fas fa-save"></i> Save Changes
          </button>

        </form>
      </div>
    </div>

    <!-- Music preferences summary -->
    <div class="hm-card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">My Music</h3>
        <a href="/onboarding.php" class="btn-outline" style="font-size:13px;">
          <i class="fas fa-pen"></i> Edit Taste
        </a>
      </div>

      <?php if (empty($profile['genres']) && empty($profile['artists'])): ?>
        <p style="color:var(--text-secondary);font-size:14px;">
          You haven't set your music taste yet.
          <a href="/onboarding.php" style="color:var(--accent-purple-light);">Set it up →</a>
        </p>
      <?php else: ?>
        <?php if (!empty($profile['genres'])): ?>
          <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:8px;">Genres</p>
          <div class="tag-container" style="margin-bottom:14px;">
            <?php foreach ($profile['genres'] as $g): ?>
              <span class="tag tag-purple"><?= htmlspecialchars($g['name']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
        <?php if (!empty($profile['artists'])): ?>
          <p style="font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:0.05em;color:var(--text-muted);margin-bottom:8px;">Artists</p>
          <div class="tag-container">
            <?php foreach ($profile['artists'] as $a): ?>
              <span class="tag tag-cyan"><?= htmlspecialchars($a['name']) ?></span>
            <?php endforeach; ?>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- Account / Danger zone -->
    <div class="hm-card">
      <h3>Account</h3>
      <div style="display:flex;gap:12px;flex-wrap:wrap;">
        <!--
          DB CONNECTION POINT — Logout
          GET /api/auth.php?action=logout
          AuthController::logout() → session_destroy() → redirect to /login.php
        -->
        <a href="/api/auth.php?action=logout" class="btn-outline">
          <i class="fas fa-sign-out-alt"></i> Sign Out
        </a>

        <!--
          DB CONNECTION POINT — Delete Account
          POST /api/users.php { action: 'delete_account' }
          UserController::deleteAccount($userId):
            → soft-delete: UserDAL::deactivate($userId)  UPDATE users SET is_active=0
            → or hard-delete with cascade (adjust to your schema)
        -->
        <button class="btn-outline" style="color:#f87171;border-color:rgba(239,68,68,0.3);"
                onclick="if(confirm('Delete your account? This cannot be undone.')) deleteAccount()">
          <i class="fas fa-trash"></i> Delete Account
        </button>
      </div>
    </div>

  </main>
</div>

<script>
  /* Profile form save — wire properly in profile.js */
  document.getElementById('profileForm').addEventListener('submit', async e => {
    e.preventDefault();
    const msgEl = document.getElementById('profileMsg');
    const data  = new URLSearchParams(new FormData(e.target));
    data.append('action', 'update_profile');

    /*
      DB CONNECTION POINT
      fetch('/api/users.php', { method:'POST', body: data })
        → UserController::updateProfile()
    */
    // Placeholder feedback until API is wired:
    msgEl.style.display = 'block';
    msgEl.style.color   = '#10b981';
    msgEl.textContent   = 'Profile saved! (API not yet connected)';
    setTimeout(() => msgEl.style.display = 'none', 3000);
  });

  function deleteAccount() {
    /*
      DB CONNECTION POINT
      fetch('/api/users.php', { method:'POST', body:'action=delete_account' })
        → redirect to /login.php on success
    */
    alert('Delete account API not yet connected.');
  }
</script>

<?php $extraScript = 'profile.js'; include __DIR__ . '/includes/footer.php'; ?>

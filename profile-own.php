<?php
/**
 * profile-own.php
 * The current user's own profile editor — name, bio, location, photo upload.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/dal/MusicDAL.php';
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

$ctrl    = new UserController();
$profile = $ctrl->getProfile($userId) ?: [
  'name'          => 'Your Name',
  'profile_photo' => null,
  'photos'        => [],
  'location'      => '',
  'bio'           => '',
  'genres'        => [],
  'artists'       => [],
];
$genders = $ctrl->getGenderOptions();
$genres = (new MusicDAL())->getAllGenres();
$selectedGenreIds = array_map(
  static fn(array $genre): int => (int)($genre['id'] ?? 0),
  $profile['genres'] ?? []
);
$topOwnSongTitle = (string)($profile['songs'][0]['title'] ?? '');
$topOwnSongArtist = (string)($profile['songs'][0]['artist'] ?? ($profile['artists'][0]['name'] ?? ''));
$spotifyOwnSeedArtist = $topOwnSongArtist !== '' ? $topOwnSongArtist : (string)($profile['artists'][0]['name'] ?? '');

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/likes.php"       class="nav-item"><i class="fas fa-heart"></i><span>Likes</span></a>
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
    <div class="hm-card own-profile-card">

      <!-- Avatar -->
      <div class="own-photo-panel">
        <div class="profile-photo-lg own-photo-preview" id="primaryPhotoPreview">
          <?php if ($profile['profile_photo']): ?>
            <img src="<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Your photo">
          <?php else: ?>
            <div class="avatar-placeholder"><?= htmlspecialchars(substr($profile['name'], 0, 1)) ?></div>
          <?php endif; ?>
        </div>
        <label class="btn-outline own-photo-button">
          <i class="fas fa-camera"></i> Add Photo
          <input type="file" id="photoInput" accept="image/*" style="display:none;">
        </label>
        <p id="photoMsg" class="own-photo-msg"></p>
        <p class="own-photo-hint">Up to 10 photos, 3MB each.</p>
      </div>

      <!-- Edit form -->
      <div class="own-profile-form">
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

    <div class="hm-card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:14px;">
        <h3 style="margin:0;">Photos</h3>
        <span id="photoCount" style="font-size:13px;color:var(--text-secondary);"><?= count($profile['photos'] ?? []) ?>/10 uploaded</span>
      </div>
      <div class="photo-grid" id="photoGrid">
        <?php foreach (($profile['photos'] ?? []) as $photo): ?>
          <div class="photo-tile" data-photo-id="<?= (int)$photo['id'] ?>">
            <img src="<?= htmlspecialchars((string)$photo['photo_url']) ?>" alt="Profile photo">
            <?php if (!empty($photo['is_primary'])): ?>
              <span class="photo-badge">Primary</span>
            <?php endif; ?>
            <div class="photo-actions">
              <?php if (empty($photo['is_primary'])): ?>
                <button type="button" class="photo-action" data-action="primary" title="Set as primary"><i class="fas fa-star"></i></button>
              <?php endif; ?>
              <button type="button" class="photo-action danger" data-action="delete" title="Delete photo"><i class="fas fa-trash"></i></button>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
      <p id="photoEmpty" style="<?= empty($profile['photos']) ? '' : 'display:none;' ?>color:var(--text-muted);font-size:13px;">No photos uploaded yet.</p>
    </div>

    <div id="preferences" class="hm-card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">Dating Preferences</h3>
        <span style="font-size:13px;color:var(--text-secondary);">Discover uses these rules before it ranks people by music match.</span>
      </div>

      <form id="preferencesForm">
        <div class="filter-row">
          <div class="form-group">
            <label class="form-label">My Gender</label>
            <select name="gender" class="form-input">
              <option value="">Select gender</option>
              <?php foreach ($genders as $gender): ?>
                <option value="<?= htmlspecialchars((string)$gender['name']) ?>" <?= (string)($profile['gender'] ?? '') === (string)$gender['name'] ? 'selected' : '' ?>>
                  <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$gender['name']))) ?>
                </option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Interested In</label>
            <select name="desired_gender" class="form-input">
              <option value="everyone" <?= ($profile['desired_gender'] ?? 'everyone') === 'everyone' ? 'selected' : '' ?>>Everyone</option>
              <option value="male" <?= ($profile['desired_gender'] ?? '') === 'male' ? 'selected' : '' ?>>Men</option>
              <option value="female" <?= ($profile['desired_gender'] ?? '') === 'female' ? 'selected' : '' ?>>Women</option>
              <option value="non_binary" <?= ($profile['desired_gender'] ?? '') === 'non_binary' ? 'selected' : '' ?>>Non-binary people</option>
              <option value="other" <?= ($profile['desired_gender'] ?? '') === 'other' ? 'selected' : '' ?>>Other genders</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Looking For</label>
            <select name="seeking_type" class="form-input">
              <option value="dating" <?= ($profile['seeking_type'] ?? 'dating') === 'dating' ? 'selected' : '' ?>>Dating</option>
              <option value="friendship" <?= ($profile['seeking_type'] ?? '') === 'friendship' ? 'selected' : '' ?>>Friendship</option>
              <option value="music_buddy" <?= ($profile['seeking_type'] ?? '') === 'music_buddy' ? 'selected' : '' ?>>Music Buddy</option>
              <option value="networking" <?= ($profile['seeking_type'] ?? '') === 'networking' ? 'selected' : '' ?>>Networking</option>
            </select>
          </div>
        </div>

        <div class="filter-row">
          <div class="form-group">
            <label class="form-label">Preferred Min Age</label>
            <input type="number" name="min_age_pref" class="form-input" min="18" max="100" value="<?= htmlspecialchars((string)($profile['min_age_pref'] ?? 18)) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Preferred Max Age</label>
            <input type="number" name="max_age_pref" class="form-input" min="18" max="100" value="<?= htmlspecialchars((string)($profile['max_age_pref'] ?? 40)) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Location Scope</label>
            <select name="location_scope" class="form-input">
              <option value="anywhere" <?= ($profile['location_scope'] ?? 'anywhere') === 'anywhere' ? 'selected' : '' ?>>Anywhere</option>
              <option value="same_country" <?= ($profile['location_scope'] ?? '') === 'same_country' ? 'selected' : '' ?>>Same country</option>
              <option value="same_city" <?= ($profile['location_scope'] ?? '') === 'same_city' ? 'selected' : '' ?>>Same city</option>
            </select>
          </div>
        </div>

        <p id="preferencesMsg" style="display:none;font-size:13.5px;margin-bottom:12px;"></p>
        <button type="submit" class="btn-primary">
          <i class="fas fa-sliders-h"></i> Save Dating Preferences
        </button>
      </form>
    </div>

    <!-- Music preferences summary -->
    <div class="hm-card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:16px;">
        <h3 style="margin:0;">My Music</h3>
        <span style="font-size:13px;color:var(--text-secondary);">Update your taste without leaving this page</span>
      </div>

      <form id="musicProfileForm">
        <div class="form-group">
          <label class="form-label">Favourite Genres</label>
          <div class="genre-grid" id="profileGenreList">
            <?php foreach ($genres as $genre): ?>
              <div
                class="genre-chip <?= in_array((int)$genre['id'], $selectedGenreIds, true) ? 'selected' : '' ?>"
                data-genre-id="<?= (int)$genre['id'] ?>"
              >
                <?= htmlspecialchars((string)$genre['name']) ?>
              </div>
            <?php endforeach; ?>
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Favourite Artists</label>
          <div style="position:relative;">
            <i class="fas fa-microphone-alt" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
            <input type="text" id="profileArtistInput" class="form-input" style="padding-left:36px;" placeholder="Search artist">
            <div id="profileArtistSuggestions" class="music-suggestions" style="display:none;"></div>
          </div>
          <div class="tag-container" id="profileArtistTags" style="margin-top:10px;"></div>
        </div>

        <div class="form-group">
          <label class="form-label">Favourite Songs</label>
          <div style="position:relative;">
            <i class="fas fa-headphones" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
            <input type="text" id="profileSongInput" class="form-input" style="padding-left:36px;" placeholder="Search song">
            <div id="profileSongSuggestions" class="music-suggestions" style="display:none;"></div>
          </div>
          <div class="tag-container" id="profileSongTags" style="margin-top:10px;"></div>
        </div>

        <p id="musicProfileMsg" style="display:none;font-size:13.5px;margin-bottom:12px;"></p>
        <button type="submit" class="btn-primary">
          <i class="fas fa-music"></i> Save Music Preferences
        </button>
      </form>
    </div>

    <div class="hm-card" style="margin-bottom:16px;">
      <div style="display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap;margin-bottom:12px;">
        <div>
          <h3 style="margin:0;">Spotify Preview</h3>
          <p style="margin:6px 0 0;color:var(--text-secondary);font-size:13px;">See how your profile music will appear to other users.</p>
        </div>
        <a id="ownSpotifyLink" href="#" target="_blank" rel="noopener noreferrer" class="btn-outline" style="display:none;">
          <i class="fab fa-spotify"></i> Open In Spotify
        </a>
      </div>
      <div id="ownSpotifyEmbedBox"
           data-track="<?= htmlspecialchars($topOwnSongTitle) ?>"
           data-artist="<?= htmlspecialchars($spotifyOwnSeedArtist) ?>">
        <p style="color:var(--text-muted);font-size:13px;">Loading player…</p>
      </div>
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

<style>
  .music-suggestions {
    position: absolute;
    top: calc(100% + 8px);
    left: 0;
    right: 0;
    background: rgba(19, 19, 31, 0.98);
    border: 1px solid var(--border);
    border-radius: 12px;
    box-shadow: var(--shadow-card);
    max-height: 240px;
    overflow-y: auto;
    z-index: 20;
  }
  .music-suggestion {
    padding: 10px 12px;
    border-bottom: 1px solid rgba(255,255,255,0.05);
    cursor: pointer;
  }
  .music-suggestion:last-child {
    border-bottom: 0;
  }
  .music-suggestion:hover,
  .music-suggestion.active {
    background: rgba(124,58,237,0.18);
  }
  .music-suggestion-title {
    color: var(--text-primary);
    font-size: 14px;
    font-weight: 600;
  }
  .music-suggestion-meta {
    color: var(--text-secondary);
    font-size: 12px;
    margin-top: 2px;
  }
</style>

<script>
  function setPhotoMessage(message, ok = true) {
    const msg = document.getElementById('photoMsg');
    if (!msg) return;
    msg.textContent = message;
    msg.style.display = 'block';
    msg.style.color = ok ? 'var(--accent-green)' : 'var(--accent-red)';
  }

  function escapeHtml(value) {
    return String(value ?? '').replace(/[&<>"']/g, char => ({
      '&': '&amp;',
      '<': '&lt;',
      '>': '&gt;',
      '"': '&quot;',
      "'": '&#039;',
    }[char]));
  }

  function renderPhotos(photos) {
    const grid = document.getElementById('photoGrid');
    const empty = document.getElementById('photoEmpty');
    const count = document.getElementById('photoCount');
    const preview = document.getElementById('primaryPhotoPreview');
    if (!grid) return;

    grid.innerHTML = '';
    photos.forEach(photo => {
      const tile = document.createElement('div');
      tile.className = 'photo-tile';
      tile.dataset.photoId = photo.id;
      tile.innerHTML = `
        <img src="${escapeHtml(photo.photo_url)}" alt="Profile photo">
        ${Number(photo.is_primary) ? '<span class="photo-badge">Primary</span>' : ''}
        <div class="photo-actions">
          ${Number(photo.is_primary) ? '' : '<button type="button" class="photo-action" data-action="primary" title="Set as primary"><i class="fas fa-star"></i></button>'}
          <button type="button" class="photo-action danger" data-action="delete" title="Delete photo"><i class="fas fa-trash"></i></button>
        </div>
      `;
      grid.appendChild(tile);
    });

    if (empty) empty.style.display = photos.length ? 'none' : 'block';
    if (count) count.textContent = `${photos.length}/10 uploaded`;

    const primary = photos.find(photo => Number(photo.is_primary)) || photos[0];
    if (preview) {
      preview.innerHTML = primary
        ? `<img src="${escapeHtml(primary.photo_url)}" alt="Your photo">`
        : `<div class="avatar-placeholder"><?= htmlspecialchars(substr($profile['name'], 0, 1)) ?></div>`;
    }
  }

  document.getElementById('photoInput')?.addEventListener('change', async event => {
    const file = event.target.files?.[0];
    if (!file) return;

    const body = new FormData();
    body.append('action', 'upload_photo');
    body.append('photo', file);

    const res = await fetch('/api/users.php', { method: 'POST', body });
    const data = await res.json();
    event.target.value = '';

    if (!data.success) {
      setPhotoMessage(data.error || 'Unable to upload photo.', false);
      return;
    }

    renderPhotos(data.photos || []);
    setPhotoMessage('Photo uploaded.');
  });

  document.getElementById('photoGrid')?.addEventListener('click', async event => {
    const button = event.target.closest('[data-action]');
    if (!button) return;

    const tile = button.closest('[data-photo-id]');
    const photoId = tile?.dataset.photoId;
    if (!photoId) return;

    const action = button.dataset.action;
    if (action === 'delete' && !confirm('Delete this photo?')) {
      return;
    }

    const data = await apiPost('/api/users.php', {
      action: action === 'primary' ? 'set_primary_photo' : 'delete_photo',
      photo_id: photoId,
    });

    if (!data.success) {
      setPhotoMessage(data.error || 'Unable to update photo.', false);
      return;
    }

    renderPhotos(data.photos || []);
    setPhotoMessage(action === 'primary' ? 'Primary photo updated.' : 'Photo deleted.');
  });

  async function musicSearch(action, params) {
    const query = new URLSearchParams({ action, ...params });
    const res = await fetch(`/api/music.php?${query.toString()}`);
    return res.json();
  }

  function createTagManager(config) {
    const input = document.getElementById(config.inputId);
    const box = document.getElementById(config.containerId);
    const suggestionBox = document.getElementById(config.suggestionBoxId);
    const tags = [...(config.initialValues || [])];
    let suggestions = [];
    let activeIndex = -1;
    let debounce = null;

    function renderTags() {
      box.innerHTML = '';
      tags.forEach(value => {
        const tag = document.createElement('span');
        tag.className = 'tag ' + config.tagClass;
        tag.innerHTML = `${value} <span class="tag-remove" data-val="${value}">×</span>`;
        tag.querySelector('.tag-remove').addEventListener('click', () => {
          const index = tags.indexOf(value);
          if (index !== -1) {
            tags.splice(index, 1);
          }
          renderTags();
          config.onChange?.([...tags]);
        });
        box.appendChild(tag);
      });
    }

    function hideSuggestions() {
      suggestionBox.style.display = 'none';
      suggestionBox.innerHTML = '';
      suggestions = [];
      activeIndex = -1;
    }

    function addTag(value) {
      const normalized = value.trim();
      if (!normalized || tags.includes(normalized)) {
        input.value = '';
        hideSuggestions();
        return;
      }

      tags.push(normalized);
      renderTags();
      config.onChange?.([...tags]);
      input.value = '';
      hideSuggestions();
    }

    function refreshActiveSuggestion() {
      [...suggestionBox.children].forEach((node, index) => {
        node.classList.toggle('active', index === activeIndex);
      });
    }

    function renderSuggestions(items) {
      suggestions = items;
      activeIndex = items.length ? 0 : -1;
      suggestionBox.innerHTML = '';

      if (!items.length) {
        hideSuggestions();
        return;
      }

      items.forEach((item, index) => {
        const row = document.createElement('div');
        row.className = 'music-suggestion' + (index === activeIndex ? ' active' : '');
        row.innerHTML = `
          <div class="music-suggestion-title">${item.label}</div>
          <div class="music-suggestion-meta">${item.meta}</div>
        `;
        row.addEventListener('mousedown', e => {
          e.preventDefault();
          addTag(item.value);
        });
        suggestionBox.appendChild(row);
      });

      suggestionBox.style.display = 'block';
    }

    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' && suggestions.length) {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, suggestions.length - 1);
        refreshActiveSuggestion();
        return;
      }

      if (e.key === 'ArrowUp' && suggestions.length) {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        refreshActiveSuggestion();
        return;
      }

      if (e.key === 'Escape') {
        hideSuggestions();
        return;
      }

      if (e.key !== 'Enter') return;
      e.preventDefault();

      if (suggestions.length && activeIndex >= 0) {
        addTag(suggestions[activeIndex].value);
        return;
      }

      addTag(input.value);
    });

    input.addEventListener('input', () => {
      const query = input.value.trim();
      if (debounce) {
        clearTimeout(debounce);
      }

      if (query.length < 2) {
        hideSuggestions();
        return;
      }

      debounce = setTimeout(async () => {
        const payload = { query };
        if (typeof config.extraParams === 'function') {
          Object.assign(payload, config.extraParams());
        }

        const data = await musicSearch(config.searchAction, payload);
        if (!data.success) {
          hideSuggestions();
          return;
        }

        const items = (data.results || []).map(result => {
          if (config.searchAction === 'search_track') {
            const title = (result.title || '').trim();
            const artist = (result.artist || '').trim();
            return {
              value: artist ? `${title} - ${artist}` : title,
              label: title || 'Unknown track',
              meta: artist || result.disambiguation || 'Track result',
            };
          }

          return {
            value: (result.name || '').trim(),
            label: (result.name || '').trim() || 'Unknown artist',
            meta: [result.country, result.disambiguation].filter(Boolean).join(' • ') || 'Artist result',
          };
        }).filter(item => item.value !== '');

        renderSuggestions(items);
      }, 250);
    });

    input.addEventListener('blur', () => {
      setTimeout(hideSuggestions, 150);
    });

    renderTags();

    return {
      getValues: () => [...tags],
      setOnChange: callback => {
        config.onChange = callback;
      },
    };
  }

  document.querySelectorAll('#profileGenreList .genre-chip').forEach(chip => {
    chip.addEventListener('click', () => chip.classList.toggle('selected'));
  });

  const profileArtistManager = createTagManager({
    inputId: 'profileArtistInput',
    containerId: 'profileArtistTags',
    suggestionBoxId: 'profileArtistSuggestions',
    tagClass: 'tag-cyan',
    searchAction: 'search_artist',
    initialValues: <?= json_encode(array_values(array_map(static fn(array $artist): string => (string)$artist['name'], $profile['artists'] ?? []))) ?>,
  });

  const profileSongManager = createTagManager({
    inputId: 'profileSongInput',
    containerId: 'profileSongTags',
    suggestionBoxId: 'profileSongSuggestions',
    tagClass: 'tag-pink',
    searchAction: 'search_track',
    initialValues: <?= json_encode(array_values(array_map(static fn(array $song): string => trim((string)($song['title'] ?? '') . ' - ' . (string)($song['artist'] ?? '')), $profile['songs'] ?? []))) ?>,
    extraParams: () => {
      const artists = profileArtistManager.getValues();
      const preferredArtist = artists[artists.length - 1] || '';
      return preferredArtist ? { artist: preferredArtist } : {};
    },
  });

  async function fetchSpotifyPreview(track, artist) {
    const params = new URLSearchParams({ action: 'spotify_embed', track, artist });
    const res = await fetch(`/api/music.php?${params.toString()}`);
    return res.json();
  }

  function getPrimarySongSeed() {
    const songs = profileSongManager.getValues();
    const firstSong = songs[0] || '';
    if (!firstSong) {
      return { track: '', artist: '' };
    }

    const parts = firstSong.split(/\s+[–-]\s+/);
    return {
      track: (parts[0] || firstSong).trim(),
      artist: (parts[1] || '').trim(),
    };
  }

  async function updateOwnSpotifyPreview() {
    const box = document.getElementById('ownSpotifyEmbedBox');
    const link = document.getElementById('ownSpotifyLink');
    if (!box || !link) return;

    const seed = getPrimarySongSeed();
    const artists = profileArtistManager.getValues();
    const track = seed.track || box.dataset.track || '';
    const artist = seed.artist || artists[0] || box.dataset.artist || '';

    if (!track && !artist) {
      box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Add a favourite artist or song to generate a Spotify preview.</p>';
      link.style.display = 'none';
      return;
    }

    box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Loading player…</p>';
    const data = await fetchSpotifyPreview(track, artist).catch(() => null);
    const result = data?.result;

    if (!data?.success || !result) {
      box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Spotify preview could not be loaded right now.</p>';
      link.style.display = 'none';
      return;
    }

    if (!result.configured) {
      box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Spotify app credentials are not configured yet.</p>';
      link.style.display = 'none';
      return;
    }

    if (!result.found || !result.embed_url) {
      box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No Spotify result found for your current music selection yet.</p>';
      link.style.display = 'none';
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
      </div>
    `;

    if (result.spotify_url) {
      link.href = result.spotify_url;
      link.style.display = 'inline-flex';
    } else {
      link.style.display = 'none';
    }
  }

  profileArtistManager.setOnChange(updateOwnSpotifyPreview);
  profileSongManager.setOnChange(updateOwnSpotifyPreview);
  document.addEventListener('DOMContentLoaded', updateOwnSpotifyPreview);

  document.getElementById('profileForm').addEventListener('submit', async e => {
    e.preventDefault();
    const msgEl = document.getElementById('profileMsg');
    const data  = new URLSearchParams(new FormData(e.target));
    data.append('action', 'update_profile');

    const res = await fetch('/api/users.php', { method: 'POST', body: data });
    const json = await res.json();
    msgEl.style.display = 'block';
    msgEl.style.color   = json.success ? '#10b981' : '#ef4444';
    msgEl.textContent   = json.success ? 'Profile saved.' : (json.error || 'Unable to save profile.');
    setTimeout(() => msgEl.style.display = 'none', 3000);
  });

  document.getElementById('preferencesForm').addEventListener('submit', async e => {
    e.preventDefault();
    const msgEl = document.getElementById('preferencesMsg');
    const data = new URLSearchParams(new FormData(e.target));
    data.append('action', 'update_profile');

    const res = await fetch('/api/users.php', { method: 'POST', body: data });
    const json = await res.json();
    msgEl.style.display = 'block';
    msgEl.style.color = json.success ? '#10b981' : '#ef4444';
    msgEl.textContent = json.success ? 'Dating preferences saved.' : (json.error || 'Unable to save dating preferences.');
    setTimeout(() => {
      msgEl.style.display = 'none';
    }, 3000);
  });

  document.getElementById('musicProfileForm').addEventListener('submit', async e => {
    e.preventDefault();
    const msgEl = document.getElementById('musicProfileMsg');
    const selectedGenres = [...document.querySelectorAll('#profileGenreList .genre-chip.selected')]
      .map(chip => chip.dataset.genreId);

    if (selectedGenres.length < 2) {
      msgEl.style.display = 'block';
      msgEl.style.color = '#ef4444';
      msgEl.textContent = 'Please select at least 2 genres.';
      return;
    }

    const data = new URLSearchParams();
    data.append('action', 'update_music');
    selectedGenres.forEach(genreId => data.append('genres[]', genreId));
    profileArtistManager.getValues().forEach(artist => data.append('artists[]', artist));

    const songs = profileSongManager.getValues().map(song => {
      const parts = song.split(/\s+[–-]\s+/);
      return {
        title: (parts[0] || song).trim(),
        artist: (parts[1] || '').trim(),
      };
    });
    data.append('songs', JSON.stringify(songs));

    const res = await fetch('/api/users.php', { method: 'POST', body: data });
    const json = await res.json();
    msgEl.style.display = 'block';
    msgEl.style.color = json.success ? '#10b981' : '#ef4444';
    msgEl.textContent = json.success ? 'Music preferences saved.' : (json.error || 'Unable to save music preferences.');
    if (json.success) {
      setTimeout(() => window.location.reload(), 700);
    }
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

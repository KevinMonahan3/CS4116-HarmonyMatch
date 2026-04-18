<?php
/**
 * onboarding.php
 * Multi-step profile setup wizard shown after registration.
 * Step 1: Personal details  /  Step 2: Music taste  /  Step 3: Done
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/dal/MusicDAL.php';
AuthController::requireLogin();

$userId = (int)($_SESSION['user_id'] ?? 0);
$userCtrl = new UserController();
$profile = $userCtrl->getProfile($userId) ?: [];
$genders = $userCtrl->getGenderOptions();
$genres = (new MusicDAL())->getAllGenres();
$selectedGenreIds = array_map(
    static fn(array $genre): int => (int)($genre['id'] ?? 0),
    $profile['genres'] ?? []
);

$pageTitle = 'Set Up Your Profile';
include __DIR__ . '/includes/header.php';
?>

<div style="max-width:640px;margin:40px auto;padding:0 24px;">

  <!-- Step indicators -->
  <div class="onboarding-steps">
    <div class="step active" id="step-indicator-1">
      <i class="fas fa-user" style="margin-right:6px;"></i>Personal Details
    </div>
    <div class="step" id="step-indicator-2">
      <i class="fas fa-music" style="margin-right:6px;"></i>Music Taste
    </div>
    <div class="step" id="step-indicator-3">
      <i class="fas fa-check" style="margin-right:6px;"></i>Complete
    </div>
  </div>

  <!-- ─────────── Step 1: Personal Details ─────────── -->
  <div id="step1" class="hm-card">
    <h2 style="font-size:20px;font-weight:800;margin-bottom:6px;">Tell us about yourself</h2>
    <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px;">
      This is what other members will see on your profile.
    </p>

    <form id="step1Form">

      <div class="form-group">
        <label class="form-label">Display Name</label>
        <input type="text" name="name" class="form-input" placeholder="e.g. Jamie Cole" value="<?= htmlspecialchars((string)($profile['name'] ?? '')) ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Date of Birth</label>
        <input type="date" name="dob" class="form-input" value="<?= !empty($profile['birth_year']) ? htmlspecialchars((string)$profile['birth_year']) . '-01-01' : '' ?>" required>
      </div>

      <div class="form-group">
        <label class="form-label">Gender</label>
        <select name="gender" class="form-input" required>
          <option value="">Select gender</option>
          <?php foreach ($genders as $gender): ?>
            <option value="<?= htmlspecialchars((string)$gender['name']) ?>" <?= (string)($profile['gender'] ?? '') === (string)$gender['name'] ? 'selected' : '' ?>>
              <?= htmlspecialchars(ucwords(str_replace('_', ' ', (string)$gender['name']))) ?>
            </option>
          <?php endforeach; ?>
        </select>
      </div>

      <div class="form-group">
        <label class="form-label">Location</label>
        <div style="position:relative;">
          <i class="fas fa-map-marker-alt" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
          <input type="text" name="location" class="form-input" style="padding-left:36px;"
                 value="<?= htmlspecialchars((string)($profile['location'] ?? '')) ?>" placeholder="e.g. Dublin, Ireland">
          <div id="locationSuggestions" class="music-suggestions" style="display:none;"></div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Bio <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea name="bio" class="form-input" rows="3"
                  placeholder="Tell potential matches a little about yourself…"><?= htmlspecialchars((string)($profile['bio'] ?? '')) ?></textarea>
      </div>

      <div class="hm-card" style="background:rgba(255,255,255,0.02);margin-bottom:18px;">
        <h3 style="margin-bottom:8px;">Dating Preferences</h3>
        <p style="color:var(--text-secondary);font-size:13px;margin-bottom:18px;">These preferences shape your Discover queue. Search will still let you browse more widely inside those boundaries.</p>

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
            <label class="form-label">Location Range</label>
            <select name="location_scope" class="form-input">
              <option value="anywhere" <?= ($profile['location_scope'] ?? 'anywhere') === 'anywhere' ? 'selected' : '' ?>>Anywhere</option>
              <option value="same_country" <?= ($profile['location_scope'] ?? '') === 'same_country' ? 'selected' : '' ?>>Same country</option>
              <option value="same_city" <?= ($profile['location_scope'] ?? '') === 'same_city' ? 'selected' : '' ?>>Same city</option>
            </select>
          </div>
        </div>
      </div>

      <p id="step1Error" style="color:#ef4444;font-size:13.5px;margin-bottom:12px;display:none;"></p>

      <!--
        DB CONNECTION POINT — Save Personal Details (Step 1)
        ─────────────────────────────────────────────────────────
        On submit, POST to /api/users.php?action=update_profile with:
          { name, dob, location, bio }
        UserController::updateProfile($userId, $data):
          → UserDAL::update($userId, [...]) — UPDATE users SET ...
        On success, show step 2.
        ─────────────────────────────────────────────────────────
      -->
      <button type="submit" class="btn-primary" style="width:100%;justify-content:center;padding:13px;">
        Next <i class="fas fa-arrow-right"></i>
      </button>

    </form>
  </div>

  <!-- ─────────── Step 2: Music Taste ─────────── -->
  <div id="step2" class="hm-card" style="display:none;">
    <h2 style="font-size:20px;font-weight:800;margin-bottom:6px;">What's your music vibe?</h2>
    <p style="color:var(--text-secondary);font-size:14px;margin-bottom:24px;">
      Pick at least 2 genres, and add some favourite artists and songs.
    </p>

    <form id="step2Form">

      <!-- Genres -->
      <div class="form-group">
        <label class="form-label">Favourite Genres <span style="font-weight:400;color:var(--text-muted);">(pick at least 2)</span></label>
        <!--
          DB CONNECTION POINT — Genre list
          ─────────────────────────────────────────────────────────
          Populate via onboarding.js:
            fetch('/api/users.php?action=genres') → MusicDAL::getAllGenres()
          Returns: [{ id, name }]
          onboarding.js renders each as a .genre-chip with data-id attribute.
          On click, toggle 'selected' class and track selected IDs in an array.
          ─────────────────────────────────────────────────────────
        -->
        <div class="genre-grid" id="genreList">
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

      <!-- Artists -->
      <div class="form-group">
        <label class="form-label">Favourite Artists <span style="font-weight:400;color:var(--text-muted);">(press Enter to add)</span></label>
        <div style="position:relative;">
          <i class="fas fa-microphone-alt" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
          <input type="text" id="artistInput" class="form-input" style="padding-left:36px;"
                 placeholder="e.g. Arctic Monkeys">
          <div id="artistSuggestions" class="music-suggestions" style="display:none;"></div>
        </div>
        <div class="tag-container" id="artistTags" style="margin-top:10px;"></div>
      </div>

      <!-- Songs -->
      <div class="form-group">
        <label class="form-label">Favourite Songs <span style="font-weight:400;color:var(--text-muted);">(press Enter to add)</span></label>
        <div style="position:relative;">
          <i class="fas fa-headphones" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
          <input type="text" id="songInput" class="form-input" style="padding-left:36px;"
                 placeholder="e.g. Do I Wanna Know – Arctic Monkeys">
          <div id="songSuggestions" class="music-suggestions" style="display:none;"></div>
        </div>
        <div class="tag-container" id="songTags" style="margin-top:10px;"></div>
      </div>

      <p id="step2Error" style="color:#ef4444;font-size:13.5px;margin-bottom:12px;display:none;"></p>

      <!--
        DB CONNECTION POINT — Save Music Taste (Step 2)
        ─────────────────────────────────────────────────────────
        On submit, POST to /api/users.php?action=update_music with:
          { genre_ids: [1,3,5], artists: ['Arctic Monkeys',...], songs: ['Do I Wanna Know',...] }
        UserController::updateMusicTaste($userId, $data):
          → MusicDAL::setGenres($userId, $genreIds)
             DELETE FROM user_genres WHERE user_id=:id; INSERT ...
          → MusicDAL::setArtists($userId, $artistNames)
          → MusicDAL::setSongs($userId, $songNames)
        On success, show step 3.
        ─────────────────────────────────────────────────────────
      -->
      <div style="display:flex;gap:12px;">
        <button type="button" class="btn-outline" onclick="goToStep(1)" style="flex:0 0 auto;">
          <i class="fas fa-arrow-left"></i> Back
        </button>
        <button type="submit" class="btn-primary" style="flex:1;justify-content:center;padding:13px;">
          Complete Profile <i class="fas fa-arrow-right"></i>
        </button>
      </div>

    </form>
  </div>

  <!-- ─────────── Step 3: Done ─────────── -->
  <div id="step3" class="hm-card" style="display:none;text-align:center;padding:48px 32px;">
    <div style="
      width:90px;height:90px;border-radius:50%;margin:0 auto 24px;
      background: radial-gradient(circle at 30% 30%, rgba(255,255,255,0.2), transparent 40%),
                  linear-gradient(135deg,#06b6d4,#7c3aed,#d946ef);
      display:flex;align-items:center;justify-content:center;
      font-size:40px;color:#fff;
      box-shadow:0 0 40px rgba(124,58,237,0.4);
      animation: float 4s ease-in-out infinite;
    ">🎵</div>
    <h2 style="font-size:24px;font-weight:800;margin-bottom:10px;">You're all set!</h2>
    <p style="color:var(--text-secondary);margin-bottom:32px;max-width:340px;margin-left:auto;margin-right:auto;">
      Your music profile is live. Start discovering people who share your sound.
    </p>
    <a href="/dashboard.php" class="btn-primary" style="font-size:16px;padding:14px 32px;">
      <i class="fas fa-heart"></i> Find My Matches
    </a>
  </div>

</div><!-- /container -->

<style>
  @keyframes float { 0%,100%{transform:translateY(0)} 50%{transform:translateY(-10px)} }
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
  /* ── Step navigation ── */
  function goToStep(n) {
    [1,2,3].forEach(i => {
      document.getElementById('step' + i).style.display          = i === n ? 'block' : 'none';
      document.getElementById('step-indicator-' + i).classList.toggle('active', i === n);
      if (i < n) document.getElementById('step-indicator-' + i).classList.add('done');
    });
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  /* ── Genre chip toggle ── */
  document.querySelectorAll('.genre-chip').forEach(chip => {
    chip.addEventListener('click', () => chip.classList.toggle('selected'));
  });

  async function musicSearch(action, params) {
    const query = new URLSearchParams({ action, ...params });
    const res = await fetch(`/api/music.php?${query.toString()}`);
    return res.json();
  }

  async function locationSearch(query) {
    const params = new URLSearchParams({ action: 'search_locations', query });
    const res = await fetch(`/api/users.php?${params.toString()}`);
    return res.json();
  }

  /* ── Tag inputs (artists & songs) ── */
  function makeTagInput(inputId, containerId, tagClass, options = {}) {
    const input = document.getElementById(inputId);
    const box   = document.getElementById(containerId);
    const suggestionBox = options.suggestionBoxId ? document.getElementById(options.suggestionBoxId) : null;
    const tags  = [];
    let suggestions = [];
    let activeIndex = -1;
    let debounce = null;

    function hideSuggestions() {
      if (!suggestionBox) return;
      suggestionBox.style.display = 'none';
      suggestionBox.innerHTML = '';
      suggestions = [];
      activeIndex = -1;
    }

    function removeTag(value, element) {
      const index = tags.indexOf(value);
      if (index !== -1) {
        tags.splice(index, 1);
      }
      element.remove();
    }

    function addTag(value) {
      const normalized = value.trim();
      if (!normalized || tags.includes(normalized)) {
        input.value = '';
        hideSuggestions();
        return;
      }

      tags.push(normalized);
      const tag = document.createElement('span');
      tag.className = 'tag ' + tagClass;
      tag.innerHTML = `${normalized} <span class="tag-remove" data-val="${normalized}">×</span>`;
      tag.querySelector('.tag-remove').addEventListener('click', () => removeTag(normalized, tag));
      box.appendChild(tag);
      input.value = '';
      hideSuggestions();
    }

    function renderSuggestions(items) {
      if (!suggestionBox) return;

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

    function refreshActiveSuggestion() {
      if (!suggestionBox) return;
      [...suggestionBox.children].forEach((node, index) => {
        node.classList.toggle('active', index === activeIndex);
      });
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
      if (!options.searchAction || !suggestionBox) {
        return;
      }

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
        if (typeof options.extraParams === 'function') {
          Object.assign(payload, options.extraParams());
        }

        const data = await musicSearch(options.searchAction, payload);
        if (!data.success) {
          hideSuggestions();
          return;
        }

        const items = (data.results || []).map(result => {
          if (options.searchAction === 'search_track') {
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

    return tags; // reference kept for form submission
  }

  const artistTags = makeTagInput('artistInput', 'artistTags', 'tag-cyan', {
    suggestionBoxId: 'artistSuggestions',
    searchAction: 'search_artist',
  });

  const songTags = makeTagInput('songInput', 'songTags', 'tag-pink', {
    suggestionBoxId: 'songSuggestions',
    searchAction: 'search_track',
    extraParams: () => {
      const preferredArtist = artistTags[artistTags.length - 1] || '';
      return preferredArtist ? { artist: preferredArtist } : {};
    },
  });

  function setupLocationAutocomplete() {
    const input = document.querySelector('#step1Form [name="location"]');
    const box = document.getElementById('locationSuggestions');
    let items = [];
    let activeIndex = -1;
    let debounce = null;

    function hide() {
      box.style.display = 'none';
      box.innerHTML = '';
      items = [];
      activeIndex = -1;
    }

    function choose(value) {
      input.value = value;
      hide();
    }

    function render(results) {
      items = results;
      activeIndex = results.length ? 0 : -1;
      box.innerHTML = '';

      if (!results.length) {
        hide();
        return;
      }

      results.forEach((result, index) => {
        const row = document.createElement('div');
        row.className = 'music-suggestion' + (index === activeIndex ? ' active' : '');
        row.innerHTML = `
          <div class="music-suggestion-title">${result.label}</div>
          <div class="music-suggestion-meta">Saved city</div>
        `;
        row.addEventListener('mousedown', e => {
          e.preventDefault();
          choose(result.label);
        });
        box.appendChild(row);
      });

      box.style.display = 'block';
    }

    function refresh() {
      [...box.children].forEach((node, index) => {
        node.classList.toggle('active', index === activeIndex);
      });
    }

    input.addEventListener('keydown', e => {
      if (e.key === 'ArrowDown' && items.length) {
        e.preventDefault();
        activeIndex = Math.min(activeIndex + 1, items.length - 1);
        refresh();
        return;
      }

      if (e.key === 'ArrowUp' && items.length) {
        e.preventDefault();
        activeIndex = Math.max(activeIndex - 1, 0);
        refresh();
        return;
      }

      if (e.key === 'Escape') {
        hide();
        return;
      }

      if (e.key === 'Enter' && items.length && activeIndex >= 0) {
        e.preventDefault();
        choose(items[activeIndex].label);
      }
    });

    input.addEventListener('input', () => {
      const query = input.value.trim();
      if (debounce) {
        clearTimeout(debounce);
      }

      if (query.length < 2) {
        hide();
        return;
      }

      debounce = setTimeout(async () => {
        const data = await locationSearch(query);
        if (!data.success) {
          hide();
          return;
        }
        render(data.results || []);
      }, 200);
    });

    input.addEventListener('blur', () => {
      setTimeout(hide, 150);
    });
  }

  setupLocationAutocomplete();

  /* ── Step 1 submit ── */
  document.getElementById('step1Form').addEventListener('submit', async e => {
    e.preventDefault();
    const errEl = document.getElementById('step1Error');
    errEl.style.display = 'none';

    const formData = new URLSearchParams(new FormData(e.target));
    formData.append('action', 'update_profile');

    const res = await fetch('/api/users.php', { method: 'POST', body: formData });
    const data = await res.json();
    if (!data.success) {
      errEl.textContent = data.error || 'Unable to save your profile.';
      errEl.style.display = 'block';
      return;
    }

    goToStep(2);
  });

  /* ── Step 2 submit ── */
  document.getElementById('step2Form').addEventListener('submit', async e => {
    e.preventDefault();
    const errEl      = document.getElementById('step2Error');
    const selectedGenres = [...document.querySelectorAll('.genre-chip.selected')].map(c => c.dataset.genreId);
    errEl.style.display = 'none';

    if (selectedGenres.length < 2) {
      errEl.textContent   = 'Please select at least 2 genres.';
      errEl.style.display = 'block';
      return;
    }

    const musicData = new URLSearchParams();
    musicData.append('action', 'onboarding_music');
    selectedGenres.forEach(genreId => musicData.append('genres[]', genreId));
    artistTags.forEach(artist => musicData.append('artists[]', artist));

    const songs = songTags.map(song => {
      const parts = song.split(/\s+[–-]\s+/);
      return {
        title: (parts[0] || song).trim(),
        artist: (parts[1] || '').trim(),
      };
    });
    musicData.append('songs', JSON.stringify(songs));

    const musicRes = await fetch('/api/users.php', { method: 'POST', body: musicData });
    const musicJson = await musicRes.json();
    if (!musicJson.success) {
      errEl.textContent = musicJson.error || 'Unable to save your music taste.';
      errEl.style.display = 'block';
      return;
    }

    const doneData = new URLSearchParams({ action: 'complete_onboarding' });
    const doneRes = await fetch('/api/users.php', { method: 'POST', body: doneData });
    const doneJson = await doneRes.json();
    if (!doneJson.success) {
      errEl.textContent = doneJson.error || 'Unable to finish onboarding.';
      errEl.style.display = 'block';
      return;
    }

    goToStep(3);
  });
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

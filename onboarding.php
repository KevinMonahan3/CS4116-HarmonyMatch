<?php
/**
 * onboarding.php
 * Multi-step profile setup wizard shown after registration.
 * Step 1: Personal details  /  Step 2: Music taste  /  Step 3: Done
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

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
        <input type="text" name="name" class="form-input" placeholder="e.g. Jamie Cole" required>
      </div>

      <div class="form-group">
        <label class="form-label">Date of Birth</label>
        <input type="date" name="dob" class="form-input" required>
      </div>

      <div class="form-group">
        <label class="form-label">Location</label>
        <div style="position:relative;">
          <i class="fas fa-map-marker-alt" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
          <input type="text" name="location" class="form-input" style="padding-left:36px;"
                 placeholder="e.g. Dublin, Ireland">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Bio <span style="font-weight:400;color:var(--text-muted);">(optional)</span></label>
        <textarea name="bio" class="form-input" rows="3"
                  placeholder="Tell potential matches a little about yourself…"></textarea>
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
          <!-- Static placeholders — onboarding.js will replace these from the API -->
          <?php
          $genres = ['Indie','Electronic','Hip-Hop','Jazz','Classical','Pop','R&B','Metal','Folk','Reggae','Country','Punk'];
          foreach ($genres as $g):
          ?>
            <div class="genre-chip" data-genre="<?= htmlspecialchars($g) ?>">
              <?= htmlspecialchars($g) ?>
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

  /* ── Tag inputs (artists & songs) ── */
  function makeTagInput(inputId, containerId, tagClass) {
    const input = document.getElementById(inputId);
    const box   = document.getElementById(containerId);
    const tags  = [];

    input.addEventListener('keydown', e => {
      if (e.key !== 'Enter') return;
      e.preventDefault();
      const val = input.value.trim();
      if (!val || tags.includes(val)) { input.value = ''; return; }
      tags.push(val);
      const tag = document.createElement('span');
      tag.className = 'tag ' + tagClass;
      tag.innerHTML = `${val} <span class="tag-remove" data-val="${val}">×</span>`;
      tag.querySelector('.tag-remove').addEventListener('click', () => {
        tags.splice(tags.indexOf(val), 1);
        tag.remove();
      });
      box.appendChild(tag);
      input.value = '';
    });

    return tags; // reference kept for form submission
  }

  const artistTags = makeTagInput('artistInput', 'artistTags', 'tag-cyan');
  const songTags   = makeTagInput('songInput',   'songTags',   'tag-pink');

  /* ── Step 1 submit ── */
  document.getElementById('step1Form').addEventListener('submit', async e => {
    e.preventDefault();
    const errEl = document.getElementById('step1Error');
    errEl.style.display = 'none';

    const formData = new FormData(e.target);
    formData.append('action', 'update_profile');

    /*
      DB CONNECTION POINT
      const res  = await fetch('/api/users.php', { method:'POST', body: new URLSearchParams(formData) });
      const data = await res.json();
      if (!data.success) { errEl.textContent = data.error; errEl.style.display='block'; return; }
    */

    // Placeholder — remove when API is wired:
    goToStep(2);
  });

  /* ── Step 2 submit ── */
  document.getElementById('step2Form').addEventListener('submit', async e => {
    e.preventDefault();
    const errEl      = document.getElementById('step2Error');
    const selectedGenres = [...document.querySelectorAll('.genre-chip.selected')].map(c => c.dataset.genre);
    errEl.style.display = 'none';

    if (selectedGenres.length < 2) {
      errEl.textContent   = 'Please select at least 2 genres.';
      errEl.style.display = 'block';
      return;
    }

    /*
      DB CONNECTION POINT
      const payload = { action:'update_music', genres: selectedGenres, artists: artistTags, songs: songTags };
      const res  = await fetch('/api/users.php', { method:'POST',
        headers:{'Content-Type':'application/json'}, body:JSON.stringify(payload) });
      const data = await res.json();
      if (!data.success) { errEl.textContent = data.error; errEl.style.display='block'; return; }
    */

    // Placeholder — remove when API is wired:
    goToStep(3);
  });
</script>

<?php $extraScript = 'onboarding.js'; include __DIR__ . '/includes/footer.php'; ?>

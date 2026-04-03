<?php
/**
 * search.php
 * Browse and filter all users by age, location, compatibility, and genre.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$pageTitle = 'Search';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item active"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/chat.php"        class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <main class="hm-main">

    <div class="section-header">
      <h2 class="section-title">Find Someone</h2>
      <p class="section-subtitle">Filter by age, vibe, and musical taste</p>
    </div>

    <!-- Filter bar -->
    <div class="hm-card" style="margin-bottom:1.5rem;">
      <div class="filter-row">

        <!-- Age range -->
        <div class="form-group">
          <label class="form-label">Age Range</label>
          <input type="range" id="ageMin" min="18" max="60" value="18">
          <input type="range" id="ageMax" min="18" max="60" value="40" style="margin-top:6px;">
          <span id="ageDisplay" style="font-size:13px;color:var(--text-secondary);">18 – 40</span>
        </div>

        <!-- Min compatibility -->
        <div class="form-group">
          <label class="form-label">Min Compatibility</label>
          <input type="range" id="compatMin" min="0" max="100" value="50">
          <span id="compatDisplay" style="font-size:13px;color:var(--text-secondary);">50%</span>
        </div>

        <!-- Genre filter -->
        <div class="form-group">
          <label class="form-label">Genre</label>
          <!--
            DB CONNECTION POINT — Genre dropdown
            ────────────────────────────────────────────────────
            Populate these <option> elements dynamically:
              • Fetch from /api/users.php?action=genres (calls MusicDAL::getAllGenres())
              • Or render server-side with a PHP foreach over $genres
            ────────────────────────────────────────────────────
          -->
          <select id="genreFilter" class="form-input">
            <option value="">Any genre</option>
            <option value="1">Indie</option>
            <option value="2">Electronic</option>
            <option value="3">Hip-Hop</option>
            <option value="4">Jazz</option>
            <option value="5">Classical</option>
            <option value="6">Pop</option>
            <option value="7">R&B</option>
          </select>
        </div>

        <!-- Keyword search -->
        <div class="form-group" style="flex:1;min-width:200px;">
          <label class="form-label">Name / Location</label>
          <div style="position:relative;">
            <i class="fas fa-search" style="position:absolute;left:13px;top:50%;transform:translateY(-50%);color:var(--text-muted);font-size:13px;"></i>
            <input type="text" id="searchQuery" class="form-input" placeholder="Search…" style="padding-left:38px;">
          </div>
        </div>

        <div class="form-group" style="align-self:flex-end;">
          <button class="btn-primary" id="applyFilters">
            <i class="fas fa-filter"></i> Apply
          </button>
        </div>

      </div>
    </div>

    <!--
      DB CONNECTION POINT — Search Results
      ─────────────────────────────────────────────────────────
      #searchResults is populated by assets/js/search.js via:
        fetch('/api/users.php?action=search&min_age=X&max_age=Y&compat=Z&genre_id=W&q=keyword')

      In /api/users.php (action=search) you should:
        1. Sanitise & validate query params
        2. Call UserController::search($params, $currentUserId)
           → UserDAL::search() runs a parameterised SQL query with:
              • WHERE u.dob BETWEEN ... AND ...   (age range)
              • AND compat_score >= :compat        (JOIN-computed or pre-computed)
              • AND genres LIKE :genre_id          (genre filter)
              • AND (u.name LIKE :q OR u.location LIKE :q)
              • ORDER BY compat_score DESC
              • LIMIT 50
        3. Return JSON array of user objects (same shape as discover feed)

      search.js renders the results as .match-card elements in #searchResults.
    ─────────────────────────────────────────────────────────
    -->
    <div id="searchResults" class="match-grid">
      <p style="color:var(--text-secondary);">Use the filters above or browse all users.</p>
    </div>

  </main>
</div>

<script>
  /* Live display of slider values */
  const ageMin     = document.getElementById('ageMin');
  const ageMax     = document.getElementById('ageMax');
  const ageDisplay = document.getElementById('ageDisplay');
  const compatMin  = document.getElementById('compatMin');
  const compatDisp = document.getElementById('compatDisplay');

  function updateAge()   { ageDisplay.textContent = ageMin.value + ' – ' + ageMax.value; }
  function updateCompat(){ compatDisp.textContent  = compatMin.value + '%'; }

  ageMin.addEventListener('input', updateAge);
  ageMax.addEventListener('input', updateAge);
  compatMin.addEventListener('input', updateCompat);

  /*
    DB CONNECTION POINT — Apply Filters button
    ─────────────────────────────────────────────────────────
    Move this logic into assets/js/search.js once the API is ready.
    The handler should:
      1. Read all filter values
      2. fetch('/api/users.php?action=search&...')
      3. Render returned users as .match-card elements in #searchResults
    ─────────────────────────────────────────────────────────
  */
  document.getElementById('applyFilters').addEventListener('click', () => {
    // TODO: wire to search.js / API
    alert('Search API not yet connected. Wire up /api/users.php?action=search here.');
  });
</script>

<?php $extraScript = 'search.js'; include __DIR__ . '/includes/footer.php'; ?>

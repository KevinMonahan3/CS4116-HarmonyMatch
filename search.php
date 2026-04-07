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
      <a href="/likes.php"       class="nav-item"><i class="fas fa-heart"></i><span>Likes</span></a>
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
          <select id="genreFilter" class="form-input">
            <option value="">Any genre</option>
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

<?php $extraScript = 'search.js'; include __DIR__ . '/includes/footer.php'; ?>

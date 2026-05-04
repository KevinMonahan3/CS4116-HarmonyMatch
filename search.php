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
      <p class="section-subtitle">Browse profiles that already fit your discovery preferences, then narrow further.</p>
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

        <div class="form-group">
          <label class="form-label">Gender</label>
          <select id="genderFilter" class="form-input">
            <option value="">Any gender</option>
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

    <div id="searchResults" class="match-grid">
      <p style="color:var(--text-secondary);">Use the filters above or browse all users.</p>
    </div>

  </main>
</div>

<?php $extraScript = 'search.js'; include __DIR__ . '/includes/footer.php'; ?>

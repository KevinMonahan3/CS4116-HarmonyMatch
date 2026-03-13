<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$pageTitle = 'Search';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
            <a href="/search.php" class="nav-item active"><i class="fas fa-search"></i><span>Search</span></a>
            <a href="/chat.php" class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
            <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
        </nav>
    </aside>

    <main class="hm-main">
        <div class="section-header">
            <h2 class="section-title">Discover People</h2>
        </div>

        <!-- Filters -->
        <div class="hm-card" style="margin-bottom:1.5rem;">
            <div class="filter-row">
                <div class="form-group">
                    <label class="form-label">Age Range</label>
                    <input type="range" id="ageMin" min="18" max="60" value="18">
                    <input type="range" id="ageMax" min="18" max="60" value="40">
                    <span id="ageDisplay">18 – 40</span>
                </div>
                <div class="form-group">
                    <label class="form-label">Min Compatibility</label>
                    <input type="range" id="compatMin" min="0" max="100" value="50">
                    <span id="compatDisplay">50%</span>
                </div>
                <button class="btn-primary" id="applyFilters">Apply Filters</button>
            </div>
        </div>

        <div id="searchResults" class="match-grid">
            <p style="color:var(--text-secondary);">Use filters above or browse all users.</p>
        </div>
    </main>
</div>

<?php $extraScript = 'search.js'; include __DIR__ . '/includes/footer.php'; ?>

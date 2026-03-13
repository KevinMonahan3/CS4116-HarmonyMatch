<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$pageTitle = 'Dashboard';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <!-- Sidebar -->
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item active"><i class="fas fa-home"></i><span>Discover</span></a>
            <a href="/search.php" class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
            <a href="/chat.php" class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
            <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
        </nav>
    </aside>

    <!-- Main content -->
    <main class="hm-main">
        <div class="section-header">
            <h2 class="section-title">Discover Matches</h2>
            <p class="section-subtitle">People who share your musical soul</p>
        </div>

        <!-- Match cards will be injected here by dashboard.js -->
        <div id="matchGrid" class="match-grid">
            <p style="color:var(--text-secondary);">Loading matches...</p>
        </div>
    </main>
</div>

<?php $extraScript = 'dashboard.js'; include __DIR__ . '/includes/footer.php'; ?>

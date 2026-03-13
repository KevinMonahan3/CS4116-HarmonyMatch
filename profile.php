<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
require_once __DIR__ . '/controllers/MatchController.php';
AuthController::requireLogin();

$viewUserId = (int)($_GET['id'] ?? 0);
if (!$viewUserId) { header('Location: /dashboard.php'); exit; }

$userCtrl  = new UserController();
$matchCtrl = new MatchController();
$profile   = $userCtrl->getProfile($viewUserId);
if (!$profile) { header('Location: /dashboard.php'); exit; }

$myId  = (int)$_SESSION['user_id'];
$score = $matchCtrl->computeCompatibility($myId, $viewUserId);

$pageTitle = htmlspecialchars($profile['name']);
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
            <a href="/search.php" class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
            <a href="/chat.php" class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
            <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
        </nav>
    </aside>

    <main class="hm-main">
        <div class="hm-card profile-header">
            <div class="profile-photo-lg">
                <?php if ($profile['profile_photo']): ?>
                    <img src="<?= htmlspecialchars($profile['profile_photo']) ?>" alt="Profile photo">
                <?php else: ?>
                    <div class="avatar-placeholder"><?= htmlspecialchars(substr($profile['name'],0,1)) ?></div>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h2><?= htmlspecialchars($profile['name']) ?></h2>
                <p><?= htmlspecialchars($profile['location'] ?? '') ?></p>
                <div class="compat-bar">
                    <span class="compat-label">Music Compatibility</span>
                    <div class="compat-track">
                        <div class="compat-fill" style="width:<?= $score ?>%"></div>
                    </div>
                    <span class="compat-value"><?= $score ?>%</span>
                </div>
            </div>
            <div class="profile-actions">
                <button class="action-btn like-btn" onclick="swipe(<?= $viewUserId ?>, 'like')">
                    <i class="fas fa-heart"></i>
                </button>
                <a href="/chat.php?with=<?= $viewUserId ?>" class="action-btn msg-btn">
                    <i class="fas fa-comment"></i>
                </a>
            </div>
        </div>

        <div class="hm-card">
            <h3>About</h3>
            <p><?= htmlspecialchars($profile['bio'] ?? 'No bio yet.') ?></p>
        </div>

        <div class="hm-card">
            <h3>Music Taste</h3>
            <div class="tag-container">
                <?php foreach ($profile['genres'] as $g): ?>
                    <span class="tag tag-purple"><?= htmlspecialchars($g['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <div style="margin-top:1rem;">
                <?php foreach ($profile['artists'] as $a): ?>
                    <span class="tag tag-cyan"><?= htmlspecialchars($a['name']) ?></span>
                <?php endforeach; ?>
            </div>
        </div>
    </main>
</div>

<script>
function swipe(toUserId, action) {
    fetch('/api/matches.php', {
        method: 'POST',
        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
        body: `action=swipe&to_user_id=${toUserId}&action_type=${action}`
    }).then(r => r.json()).then(data => {
        if (data.is_match) alert('It\'s a match! 🎵');
        window.location = '/dashboard.php';
    });
}
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

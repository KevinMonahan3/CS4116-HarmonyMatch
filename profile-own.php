<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
require_once __DIR__ . '/controllers/UserController.php';
AuthController::requireLogin();

$userId  = (int)$_SESSION['user_id'];
$ctrl    = new UserController();
$profile = $ctrl->getProfile($userId);

$pageTitle = 'My Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">
    <aside class="hm-sidebar">
        <nav class="sidebar-nav">
            <a href="/dashboard.php" class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
            <a href="/search.php" class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
            <a href="/chat.php" class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
            <a href="/profile-own.php" class="nav-item active"><i class="fas fa-user"></i><span>Profile</span></a>
        </nav>
    </aside>

    <main class="hm-main">
        <div class="section-header">
            <h2 class="section-title">My Profile</h2>
        </div>

        <div class="hm-card">
            <form id="profileForm">
                <div class="form-group">
                    <label class="form-label">Name</label>
                    <input type="text" name="name" class="form-input" value="<?= htmlspecialchars($profile['name']) ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Location</label>
                    <input type="text" name="location" class="form-input" value="<?= htmlspecialchars($profile['location'] ?? '') ?>">
                </div>
                <div class="form-group">
                    <label class="form-label">Bio</label>
                    <textarea name="bio" class="form-input" rows="4"><?= htmlspecialchars($profile['bio'] ?? '') ?></textarea>
                </div>
                <p id="profileMsg" style="display:none;"></p>
                <button type="submit" class="btn-primary">Save Changes</button>
            </form>
        </div>

        <div class="hm-card" style="margin-top:1rem;">
            <h3>My Music</h3>
            <div class="tag-container">
                <?php foreach ($profile['genres'] as $g): ?>
                    <span class="tag tag-purple"><?= htmlspecialchars($g['name']) ?></span>
                <?php endforeach; ?>
                <?php foreach ($profile['artists'] as $a): ?>
                    <span class="tag tag-cyan"><?= htmlspecialchars($a['name']) ?></span>
                <?php endforeach; ?>
            </div>
            <a href="/onboarding.php" style="color:var(--accent-purple);display:block;margin-top:1rem;">Update music taste →</a>
        </div>

        <div class="hm-card" style="margin-top:1rem;">
            <h3>Account</h3>
            <a href="/api/auth.php?action=logout" class="btn-outline">Sign Out</a>
        </div>
    </main>
</div>

<?php $extraScript = 'profile.js'; include __DIR__ . '/includes/footer.php'; ?>

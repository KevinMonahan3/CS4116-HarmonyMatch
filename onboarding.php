<?php
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$pageTitle = 'Set Up Your Profile';
include __DIR__ . '/includes/header.php';
?>

<div class="onboarding-container">
    <!-- Step indicators -->
    <div class="onboarding-steps">
        <div class="step active" id="step-indicator-1">1. Personal Details</div>
        <div class="step" id="step-indicator-2">2. Music Taste</div>
        <div class="step" id="step-indicator-3">3. Complete</div>
    </div>

    <!-- Step 1: Personal Details -->
    <div id="step1" class="onboarding-step hm-card">
        <h2>Tell us about yourself</h2>
        <form id="step1Form">
            <div class="form-group">
                <label class="form-label">Display Name</label>
                <input type="text" name="name" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Date of Birth</label>
                <input type="date" name="dob" class="form-input" required>
            </div>
            <div class="form-group">
                <label class="form-label">Location</label>
                <input type="text" name="location" class="form-input" placeholder="e.g. Dublin, Ireland">
            </div>
            <div class="form-group">
                <label class="form-label">Bio</label>
                <textarea name="bio" class="form-input" rows="3" placeholder="Tell potential matches about yourself..."></textarea>
            </div>
            <button type="submit" class="btn-primary">Next →</button>
        </form>
    </div>

    <!-- Step 2: Music Taste (hidden initially) -->
    <div id="step2" class="onboarding-step hm-card" style="display:none;">
        <h2>What's your music vibe?</h2>
        <form id="step2Form">
            <div class="form-group">
                <label class="form-label">Favourite Genres</label>
                <div id="genreList"><!-- loaded by onboarding.js --></div>
            </div>
            <div class="form-group">
                <label class="form-label">Favourite Artists</label>
                <input type="text" id="artistInput" class="form-input" placeholder="Type an artist and press Enter">
                <div id="artistTags" class="tag-container"></div>
            </div>
            <div class="form-group">
                <label class="form-label">Favourite Songs</label>
                <input type="text" id="songInput" class="form-input" placeholder="Song – Artist, press Enter">
                <div id="songTags" class="tag-container"></div>
            </div>
            <button type="submit" class="btn-primary">Complete Profile →</button>
        </form>
    </div>

    <!-- Step 3: Done -->
    <div id="step3" class="onboarding-step hm-card" style="display:none;text-align:center;">
        <div style="font-size:3rem;margin-bottom:1rem;">🎵</div>
        <h2>You're all set!</h2>
        <p style="color:var(--text-secondary);margin-bottom:2rem;">Your music profile is ready. Start discovering compatible matches.</p>
        <a href="/dashboard.php" class="btn-primary">Find My Matches</a>
    </div>
</div>

<?php $extraScript = 'onboarding.js'; include __DIR__ . '/includes/footer.php'; ?>

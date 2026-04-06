<?php
/**
 * likes.php
 * Shows users who have liked the current user.
 * Liking back creates a match and opens the chat.
 */
require_once __DIR__ . '/includes/session.php';
require_once __DIR__ . '/controllers/AuthController.php';
AuthController::requireLogin();

$pageTitle = 'Likes';
include __DIR__ . '/includes/header.php';
?>

<div class="hm-layout">

  <aside class="hm-sidebar">
    <nav class="sidebar-nav">
      <a href="/dashboard.php"   class="nav-item"><i class="fas fa-home"></i><span>Discover</span></a>
      <a href="/search.php"      class="nav-item"><i class="fas fa-search"></i><span>Search</span></a>
      <a href="/likes.php"       class="nav-item active"><i class="fas fa-heart"></i><span>Likes</span></a>
      <a href="/chat.php"        class="nav-item"><i class="fas fa-comment"></i><span>Messages</span></a>
      <a href="/profile-own.php" class="nav-item"><i class="fas fa-user"></i><span>Profile</span></a>
    </nav>
  </aside>

  <main class="hm-main">

    <div class="section-header">
      <h2 class="section-title">People Who Liked You</h2>
      <p class="section-subtitle">Like them back to start a conversation</p>
    </div>

    <div id="likesGrid" class="match-grid">
      <p style="color:var(--text-secondary);">Loading...</p>
    </div>

  </main>
</div>

<script>
const CURRENT_USER_ID = <?= (int)$_SESSION['user_id'] ?>;

async function loadLikes() {
    const grid = document.getElementById('likesGrid');
    const likers = await apiGet('/api/matches.php?action=likes_received');

    if (!Array.isArray(likers) || likers.length === 0) {
        grid.innerHTML = '<p style="color:var(--text-secondary);">No likes yet — keep discovering!</p>';
        return;
    }

    grid.innerHTML = likers.map(u => `
        <div class="match-card" id="liker-${u.id}">
            <div class="match-card-photo">
                ${u.profile_photo
                    ? `<img src="${u.profile_photo}" alt="${u.name}">`
                    : u.top_artist
                        ? `<div class="artist-bg-card"><i class="fas fa-music"></i><span>${u.top_artist}</span></div>`
                        : `<div class="avatar-placeholder">${u.name.charAt(0)}</div>`
                }
                <span class="likes-heart-badge"><i class="fas fa-heart"></i></span>
            </div>
            <div class="match-card-body">
                <div class="match-card-name">
                    <a href="/profile.php?id=${u.id}" class="profile-name-link">${u.name}</a>
                </div>
                <div class="match-card-meta">${u.location ?? ''}</div>
                ${u.top_artist ? `<div class="top-artist-label"><i class="fas fa-music"></i> ${u.top_artist}</div>` : ''}
            </div>
            <div class="match-card-actions">
                <button class="action-btn pass-btn" onclick="dismissLike(${u.id}, this)">
                    <i class="fas fa-times"></i><span>Skip</span>
                </button>
                <button class="action-btn info-btn" onclick="window.location='/profile.php?id=${u.id}'">
                    <i class="fas fa-user"></i><span>Info</span>
                </button>
                <button class="action-btn like-btn" onclick="likeBack(${u.id}, this)">
                    <i class="fas fa-heart"></i><span>Like Back</span>
                </button>
            </div>
        </div>
    `).join('');
}

async function likeBack(toUserId, btn) {
    btn.disabled = true;
    const data = await apiPost('/api/matches.php', {
        action: 'swipe',
        to_user_id: toUserId,
        action_type: 'like'
    });
    if (data.is_match) {
        window.location = '/chat.php?with=' + toUserId;
    } else {
        document.getElementById('liker-' + toUserId)?.remove();
    }
}

async function dismissLike(toUserId, btn) {
    btn.disabled = true;
    await apiPost('/api/matches.php', {
        action: 'swipe',
        to_user_id: toUserId,
        action_type: 'skip'
    });
    document.getElementById('liker-' + toUserId)?.remove();
}

document.addEventListener('DOMContentLoaded', loadLikes);
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>

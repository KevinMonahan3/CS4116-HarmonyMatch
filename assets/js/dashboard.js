// dashboard.js — load and render match cards

async function loadMatches() {
    const grid = document.getElementById('matchGrid');
    if (!grid) return;

    grid.innerHTML = '<p style="color:var(--text-secondary);">Loading matches...</p>';

    const matches = await apiGet('/api/matches.php?action=dashboard');
    if (!Array.isArray(matches) || matches.length === 0) {
        grid.innerHTML = '<p style="color:var(--text-secondary);">No new matches right now. Check back later!</p>';
        return;
    }

    grid.innerHTML = matches.map(m => `
        <div class="match-card hm-card">
            <div class="match-photo">
                ${m.profile_photo
                    ? `<img src="${m.profile_photo}" alt="${m.name}">`
                    : `<div class="avatar-placeholder">${m.name.charAt(0)}</div>`}
                <span class="compat-badge">${m.compatibility}%</span>
            </div>
            <div class="match-info">
                <h3>${m.name}</h3>
                <p>${m.location ?? ''}</p>
            </div>
            <div class="match-actions">
                <button class="action-btn skip-btn" onclick="doSwipe(${m.id}, 'skip', this)">
                    <i class="fas fa-times"></i>
                </button>
                <a href="/profile.php?id=${m.id}" class="action-btn info-btn">
                    <i class="fas fa-info"></i>
                </a>
                <button class="action-btn like-btn" onclick="doSwipe(${m.id}, 'like', this)">
                    <i class="fas fa-heart"></i>
                </button>
            </div>
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', loadMatches);

async function doSwipe(toUserId, action, btn) {
    btn.disabled = true;
    const data = await apiPost('/api/matches.php', { action: 'swipe', to_user_id: toUserId, action_type: action });
    if (data.is_match) {
        alert("It's a match! 🎵 Start the conversation.");
    }
    btn.closest('.match-card').remove();
}
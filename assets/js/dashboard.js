// dashboard.js — load and render match cards

function buildCardPhoto(m) {
    if (m.profile_photo) {
        return `<img src="${m.profile_photo}" alt="${m.name}">`;
    }
    if (m.top_artist) {
        return `<div class="artist-bg-card">
            <i class="fas fa-music"></i>
            <span>${m.top_artist}</span>
        </div>`;
    }
    return `<div class="avatar-placeholder">${m.name.charAt(0)}</div>`;
}

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
        <div class="match-card">
            <div class="match-card-photo">
                ${buildCardPhoto(m)}
                <span class="compat-badge">${m.compatibility}%</span>
            </div>
            <div class="match-card-body">
                <div class="match-card-name">${m.name}</div>
                <div class="match-card-meta">${m.location ?? ''}</div>
                ${m.top_artist ? `<div class="top-artist-label"><i class="fas fa-music"></i> ${m.top_artist}</div>` : ''}
            </div>
            <div class="match-card-actions">
                <button class="action-btn pass-btn" onclick="doSwipe(${m.id}, 'skip', this)" title="Skip">
                    <i class="fas fa-times"></i><span>Skip</span>
                </button>
                <button class="action-btn info-btn" onclick="window.location='/profile.php?id=${m.id}'" title="View Profile">
                    <i class="fas fa-user"></i><span>Info</span>
                </button>
                <button class="action-btn like-btn" onclick="doSwipe(${m.id}, 'like', this)" title="Like">
                    <i class="fas fa-heart"></i><span>Like</span>
                </button>
            </div>
        </div>
    `).join('');
}

document.addEventListener('DOMContentLoaded', loadMatches);

async function doSwipe(toUserId, action, btn) {
    btn.disabled = true;
    const data = await apiPost('/api/matches.php', { action: 'swipe', to_user_id: toUserId, action_type: action });
    btn.closest('.match-card').remove();
    if (data.is_match) {
        window.location.href = `/chat.php?with=${toUserId}`;
    }
}

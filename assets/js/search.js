// search.js — search/discover page

document.addEventListener('DOMContentLoaded', () => {
    loadResults();

    document.getElementById('applyFilters')?.addEventListener('click', loadResults);

    // Live display of range slider values
    const ageMin   = document.getElementById('ageMin');
    const ageMax   = document.getElementById('ageMax');
    const compat   = document.getElementById('compatMin');
    const ageDisp  = document.getElementById('ageDisplay');
    const compatDp = document.getElementById('compatDisplay');

    [ageMin, ageMax].forEach(el => el?.addEventListener('input', () => {
        ageDisp.textContent = `${ageMin.value} – ${ageMax.value}`;
    }));
    compat?.addEventListener('input', () => {
        compatDp.textContent = `${compat.value}%`;
    });
});

function buildCardPhoto(u) {
    if (u.profile_photo) {
        return `<img src="${u.profile_photo}" alt="${u.name}">`;
    }
    if (u.top_artist) {
        return `<div class="artist-bg-card">
            <i class="fas fa-music"></i>
            <span>${u.top_artist}</span>
        </div>`;
    }
    return `<div class="avatar-placeholder">${u.name.charAt(0)}</div>`;
}

async function loadResults() {
    const results = document.getElementById('searchResults');
    results.innerHTML = '<p style="color:var(--text-secondary);">Searching...</p>';

    // TODO: pass filters to API when backend supports it
    const users = await apiGet('/api/matches.php?action=dashboard');
    if (!Array.isArray(users) || users.length === 0) {
        results.innerHTML = '<p style="color:var(--text-secondary);">No results found.</p>';
        return;
    }
    results.innerHTML = users.map(u => `
        <div class="match-card">
            <div class="match-card-photo">
                ${buildCardPhoto(u)}
                <span class="compat-badge">${u.compatibility}%</span>
            </div>
            <div class="match-card-body">
                <div class="match-card-name">${u.name}</div>
                <div class="match-card-meta">${u.location ?? ''}</div>
                ${u.top_artist ? `<div class="top-artist-label"><i class="fas fa-music"></i> ${u.top_artist}</div>` : ''}
            </div>
            <div class="match-card-actions">
                <button class="action-btn pass-btn" onclick="doSwipe(${u.id}, 'skip', this)" title="Skip">
                    <i class="fas fa-times"></i><span>Skip</span>
                </button>
                <button class="action-btn info-btn" onclick="window.location='/profile.php?id=${u.id}'" title="View Profile">
                    <i class="fas fa-user"></i><span>Info</span>
                </button>
                <button class="action-btn like-btn" onclick="doSwipe(${u.id}, 'like', this)" title="Like">
                    <i class="fas fa-heart"></i><span>Like</span>
                </button>
            </div>
        </div>
    `).join('');
}

async function doSwipe(toUserId, action, btn) {
    btn.disabled = true;
    const data = await apiPost('/api/matches.php', { action: 'swipe', to_user_id: toUserId, action_type: action });
    if (data.is_match) {
        alert("It's a match! 🎵 Start the conversation.");
    }
    btn.closest('.match-card').remove();
}

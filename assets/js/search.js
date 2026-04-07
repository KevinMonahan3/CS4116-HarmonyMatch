// search.js — search/discover page

document.addEventListener('DOMContentLoaded', () => {
    loadGenres();
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

    const ageMin = document.getElementById('ageMin')?.value ?? '18';
    const ageMax = document.getElementById('ageMax')?.value ?? '40';
    const compat = document.getElementById('compatMin')?.value ?? '0';
    const genreId = document.getElementById('genreFilter')?.value ?? '';
    const query = document.getElementById('searchQuery')?.value?.trim() ?? '';

    const params = new URLSearchParams({
        action: 'search',
        min_age: ageMin,
        max_age: ageMax,
        min_compatibility: compat,
    });

    if (genreId) {
        params.set('genre_id', genreId);
    }
    if (query) {
        params.set('query', query);
    }

    const users = await apiGet(`/api/users.php?${params.toString()}`);
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
                <div class="match-card-meta">${[u.age, u.location].filter(Boolean).join(' · ')}</div>
                ${u.top_artist ? `<div class="top-artist-label"><i class="fas fa-music"></i> ${u.top_artist}</div>` : ''}
                ${(u.genres || []).length ? `
                    <div class="tag-container" style="margin-top:10px;">
                        ${(u.genres || []).slice(0, 3).map(g => `<span class="tag tag-purple">${g.name}</span>`).join('')}
                    </div>
                ` : ''}
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

async function loadGenres() {
    const select = document.getElementById('genreFilter');
    if (!select) {
        return;
    }

    const genres = await apiGet('/api/users.php?action=genres');
    if (!Array.isArray(genres)) {
        return;
    }

    const currentValue = select.value;
    select.innerHTML = '<option value="">Any genre</option>' + genres.map(
        genre => `<option value="${genre.id}">${genre.name}</option>`
    ).join('');
    select.value = currentValue;
}

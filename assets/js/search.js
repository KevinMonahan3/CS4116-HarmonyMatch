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
        <div class="match-card hm-card">
            <div class="match-photo">
                ${u.profile_photo
                    ? `<img src="${u.profile_photo}" alt="${u.name}">`
                    : `<div class="avatar-placeholder">${u.name.charAt(0)}</div>`}
                <span class="compat-badge">${u.compatibility}%</span>
            </div>
            <div class="match-info">
                <h3><a href="/profile.php?id=${u.id}">${u.name}</a></h3>
                <p>${u.location ?? ''}</p>
            </div>
        </div>
    `).join('');
}

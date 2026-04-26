// dashboard.js — one-by-one discovery queue

function escHtml(value) {
    return String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;')
        .replace(/'/g, '&#39;');
}

const discoveryState = {
    queue: [],
    index: 0,
};

function buildCardPhoto(match) {
    if (match.profile_photo) {
        return `<img src="${escHtml(match.profile_photo)}" alt="${escHtml(match.name)}">`;
    }
    if (match.top_artist) {
        return `<div class="artist-bg-card">
            <i class="fas fa-music"></i>
            <span>${escHtml(match.top_artist)}</span>
        </div>`;
    }
    return `<div class="avatar-placeholder">${escHtml((match.name || '?').charAt(0))}</div>`;
}

function updateQueueMeta() {
    const meta = document.getElementById('discoverQueueMeta');
    if (!meta) return;

    if (!discoveryState.queue.length) {
        meta.textContent = 'No profiles available right now. Try broadening your preferences or refreshing later.';
        return;
    }

    const remaining = discoveryState.queue.length - discoveryState.index - 1;
    const current = discoveryState.queue[discoveryState.index];
    meta.textContent = `${current.compatibility}% music match. ${remaining} more profile${remaining === 1 ? '' : 's'} in your current queue.`;
}

function renderEmptyState() {
    const grid = document.getElementById('matchGrid');
    if (!grid) return;

    grid.innerHTML = `
        <div class="hm-card discover-empty">
            <i class="fas fa-compact-disc"></i>
            <h3>No more profiles in your queue</h3>
            <p>Try widening your age range or location preference, then refresh the queue.</p>
            <a href="/profile-own.php#preferences" class="btn-outline">Update Preferences</a>
        </div>
    `;
    updateQueueMeta();
}

function renderCurrentCard() {
    const grid = document.getElementById('matchGrid');
    if (!grid) return;

    const current = discoveryState.queue[discoveryState.index];
    if (!current) {
        renderEmptyState();
        return;
    }

    const metaBits = [current.age, current.location].filter(Boolean).join(' · ');
    grid.innerHTML = `
        <div class="match-card discover-card">
            <div class="match-card-photo discover-photo">
                ${buildCardPhoto(current)}
                <span class="compat-badge">${current.compatibility}%</span>
            </div>
            <div class="match-card-body">
                <div class="discover-card-topline">
                    <div>
                        <div class="match-card-name discover-card-name">${escHtml(current.name)}</div>
                        <div class="match-card-meta">${escHtml(metaBits)}</div>
                    </div>
                    <span class="discover-intent">${escHtml(String(current.seeking_type || 'dating').replace(/_/g, ' '))}</span>
                </div>
                ${current.top_artist ? `<div class="top-artist-label"><i class="fas fa-music"></i> ${escHtml(current.top_artist)}</div>` : ''}
                ${current.match_reason ? `<div class="match-why">${escHtml(current.match_reason)}</div>` : ''}
                ${current.shared_summary ? `<div class="match-summary">${escHtml(current.shared_summary)}</div>` : ''}
                ${(current.shared_genres || []).length ? `
                    <div class="tag-container" style="margin-top:12px;">
                        ${(current.shared_genres || []).map(genre => `<span class="tag tag-purple">${escHtml(genre)}</span>`).join('')}
                    </div>
                ` : ''}
                <div class="discover-vibe">
                    <button class="btn-outline discover-vibe-toggle" type="button" onclick="toggleDiscoverVibe()">
                        <i class="fab fa-spotify"></i> Hear Their Vibe
                    </button>
                    <div id="discoverVibeBox"
                         class="discover-vibe-box"
                         data-track="${escHtml(current.top_song_title || '')}"
                         data-artist="${escHtml(current.spotify_seed_artist || '')}"
                         style="display:none;"></div>
                </div>
            </div>
            <div class="match-card-actions discover-actions">
                <button class="action-btn pass-btn" onclick="doSwipe(${current.id}, 'skip', this)" title="Skip">
                    <i class="fas fa-times"></i><span>Skip</span>
                </button>
                <button class="action-btn info-btn" onclick="window.location='/profile.php?id=${current.id}'" title="View Profile">
                    <i class="fas fa-user"></i><span>Info</span>
                </button>
                <button class="action-btn info-btn" onclick="reportUser(${current.id})" title="Report">
                    <i class="fas fa-flag"></i><span>Report</span>
                </button>
                <button class="action-btn like-btn" onclick="doSwipe(${current.id}, 'like', this)" title="Like">
                    <i class="fas fa-heart"></i><span>Like</span>
                </button>
            </div>
        </div>
    `;

    updateQueueMeta();
}

async function loadMatches() {
    const grid = document.getElementById('matchGrid');
    if (!grid) return;

    grid.innerHTML = '<p style="color:var(--text-secondary);">Loading your discovery queue...</p>';
    const matches = await apiGet('/api/matches.php?action=dashboard');
    discoveryState.queue = Array.isArray(matches) ? matches : [];
    discoveryState.index = 0;

    if (!discoveryState.queue.length) {
        renderEmptyState();
        return;
    }

    renderCurrentCard();
}

document.addEventListener('DOMContentLoaded', loadMatches);

async function toggleDiscoverVibe() {
    const box = document.getElementById('discoverVibeBox');
    if (!box) return;

    if (box.dataset.loaded === 'true') {
        box.style.display = box.style.display === 'none' ? 'block' : 'none';
        return;
    }

    const track = box.dataset.track || '';
    const artist = box.dataset.artist || '';
    if (!track && !artist) {
        box.style.display = 'block';
        box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No music preview available for this profile yet.</p>';
        box.dataset.loaded = 'true';
        return;
    }

    box.style.display = 'block';
    box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Loading Spotify preview…</p>';

    const params = new URLSearchParams({ action: 'spotify_embed', track, artist });
    const data = await fetch('/api/music.php?' + params.toString()).then(r => r.json()).catch(() => null);
    const result = data?.result;

    if (!data?.success || !result) {
        box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Spotify preview could not be loaded.</p>';
        box.dataset.loaded = 'true';
        return;
    }

    if (!result.configured) {
        box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">Spotify preview is not configured on the server yet.</p>';
        box.dataset.loaded = 'true';
        return;
    }

    if (!result.found || !result.embed_url) {
        box.innerHTML = '<p style="color:var(--text-muted);font-size:13px;">No Spotify match was found for this profile yet.</p>';
        box.dataset.loaded = 'true';
        return;
    }

    box.innerHTML = `
        <div class="spotify-embed-wrap">
            <iframe
                src="${result.embed_url}"
                width="100%"
                height="${result.type === 'artist' ? '352' : '152'}"
                frameborder="0"
                allowfullscreen
                allow="autoplay; clipboard-write; encrypted-media; fullscreen; picture-in-picture"
                loading="lazy"
                style="border-radius:12px;">
            </iframe>
        </div>
    `;
    box.dataset.loaded = 'true';
}

async function doSwipe(toUserId, action, btn) {
    btn.disabled = true;
    const data = await apiPost('/api/matches.php', { action: 'swipe', to_user_id: toUserId, action_type: action });
    if (data.is_match) {
        window.location.href = `/chat.php?with=${toUserId}`;
        return;
    }

    discoveryState.index += 1;
    renderCurrentCard();
}

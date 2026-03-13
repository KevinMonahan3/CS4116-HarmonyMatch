// onboarding.js — multi-step onboarding wizard

const artists = [];
const songs   = [];

document.addEventListener('DOMContentLoaded', async () => {
    // Load genre checkboxes
    const genreList = document.getElementById('genreList');
    if (genreList) {
        const genres = await apiGet('/api/users.php?action=genres'); // TODO: add genres endpoint
        // Fallback: hardcoded list matching schema seed
        const defaultGenres = ['Pop','Rock','Hip-Hop','R&B','Electronic','Jazz','Classical',
                               'Country','Indie','Metal','Folk','Reggae','Latin','Blues','Punk'];
        const list = Array.isArray(genres) ? genres.map(g => g.name) : defaultGenres;
        genreList.innerHTML = list.map((g, i) => `
            <label class="genre-checkbox">
                <input type="checkbox" name="genres[]" value="${i+1}"> ${g}
            </label>
        `).join('');
    }

    // Step 1 form
    document.getElementById('step1Form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const data = await apiPost('/api/users.php', {
            action:   'update_profile',
            name:     form.querySelector('[name=name]').value,
            location: form.querySelector('[name=location]').value,
            bio:      form.querySelector('[name=bio]').value,
        });
        if (data.success) goToStep(2);
    });

    // Artist tag input
    document.getElementById('artistInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = e.target.value.trim();
            if (val) { artists.push(val); renderTags('artistTags', artists); e.target.value = ''; }
        }
    });

    // Song tag input
    document.getElementById('songInput')?.addEventListener('keydown', (e) => {
        if (e.key === 'Enter') {
            e.preventDefault();
            const val = e.target.value.trim();
            if (val) { songs.push(val); renderTags('songTags', songs); e.target.value = ''; }
        }
    });

    // Step 2 form
    document.getElementById('step2Form')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const genreIds = [...document.querySelectorAll('[name="genres[]"]:checked')].map(el => el.value);
        const songObjs = songs.map(s => {
            const parts = s.split(' – ');
            return { title: parts[0] ?? s, artist: parts[1] ?? '' };
        });
        await apiPost('/api/users.php', {
            action:  'onboarding_music',
            genres:  genreIds,
            artists: artists,
            songs:   JSON.stringify(songObjs),
        });
        const done = await apiPost('/api/users.php', { action: 'complete_onboarding' });
        if (done.success) goToStep(3);
    });
});

function goToStep(n) {
    document.querySelectorAll('.onboarding-step').forEach((el, i) => {
        el.style.display = (i + 1 === n) ? '' : 'none';
    });
    document.querySelectorAll('.step').forEach((el, i) => {
        el.classList.toggle('active', i + 1 === n);
    });
}

function renderTags(containerId, items) {
    const el = document.getElementById(containerId);
    el.innerHTML = items.map((item, i) => `
        <span class="tag tag-purple">${item} <button onclick="removeTag('${containerId}',${i})">×</button></span>
    `).join('');
}

function removeTag(containerId, index) {
    const arr = containerId === 'artistTags' ? artists : songs;
    arr.splice(index, 1);
    renderTags(containerId, arr);
}

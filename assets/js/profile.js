// profile.js — own profile edit page

document.addEventListener('DOMContentLoaded', () => {
    document.getElementById('profileForm')?.addEventListener('submit', async (e) => {
        e.preventDefault();
        const form = e.target;
        const msg  = document.getElementById('profileMsg');
        const data = await apiPost('/api/users.php', {
            action:   'update_profile',
            name:     form.querySelector('[name=name]').value,
            location: form.querySelector('[name=location]').value,
            bio:      form.querySelector('[name=bio]').value,
        });
        msg.style.display  = 'block';
        msg.textContent    = data.success ? 'Profile updated!' : (data.error ?? 'Error saving.');
        msg.style.color    = data.success ? 'var(--accent-green)' : 'var(--accent-red)';
    });
});

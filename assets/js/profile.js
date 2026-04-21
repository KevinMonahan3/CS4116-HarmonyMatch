// profile.js — own profile edit page

document.addEventListener('DOMContentLoaded', () => {
    const photoInput = document.getElementById('photoInput');
    if (photoInput) {
        photoInput.addEventListener('change', async () => {
            const file = photoInput.files[0];
            if (!file) return;

            const formData = new FormData();
            formData.append('action', 'upload_photo');
            formData.append('photo', file);

            const res  = await fetch('/api/users.php?action=upload_photo', { method: 'POST', body: formData });
            const data = await res.json();

            if (data.success) {
                const container = photoInput.closest('[style*="flex-shrink"]').querySelector('.profile-photo-lg');
                container.innerHTML = `<img src="${data.photo_url}" alt="Your photo">`;
            } else {
                alert(data.error ?? 'Photo upload failed.');
            }
        });
    }

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

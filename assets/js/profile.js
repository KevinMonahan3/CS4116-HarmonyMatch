// profile.js — own profile edit page

document.addEventListener('DOMContentLoaded', () => {
    const photoInput = document.getElementById('photoInput');
    if (!photoInput) return;

    photoInput.addEventListener('change', async () => {
        const file = photoInput.files[0];
        if (!file) return;

        const formData = new FormData();
        formData.append('photo', file);

        try {
            const res  = await fetch('/api/users.php?action=upload_photo', { method: 'POST', body: formData });
            const text = await res.text();
            let data;
            try {
                data = JSON.parse(text);
            } catch {
                console.error('Non-JSON response from upload:', text);
                alert('Upload error: unexpected server response. Check browser console.');
                return;
            }

            if (data.success) {
                const container = photoInput.closest('[style*="flex-shrink"]').querySelector('.profile-photo-lg');
                container.innerHTML = `<img src="${data.photo_url}?t=${Date.now()}" alt="Your photo">`;
            } else {
                alert(data.error ?? 'Photo upload failed.');
            }
        } catch (err) {
            console.error('Upload fetch error:', err);
            alert('Upload failed: could not reach server.');
        }
    });
});

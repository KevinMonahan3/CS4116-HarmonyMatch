// main.js — shared utilities loaded on every page

// Highlight the correct bottom nav item for the current page
document.addEventListener('DOMContentLoaded', function () {
    const path = window.location.pathname;
    document.querySelectorAll('.bottom-nav-item').forEach(function (item) {
        try {
            // href may be an absolute URL (e.g. http://localhost/dashboard.php)
            // so extract just the pathname for comparison
            const itemPath = new URL(item.getAttribute('href'), window.location.origin).pathname;
            if (path === itemPath) {
                item.classList.add('active');
            }
        } catch (e) {}
    });
});

/**
 * Generic fetch wrapper that posts form data to a PHP API endpoint.
 * @param {string} url
 * @param {Object} data  key-value pairs
 * @returns {Promise<Object>}
 */
async function apiPost(url, data = {}) {
    const body = new URLSearchParams(data);
    const res  = await fetch(url, {
        method:  'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body:    body.toString()
    });
    return res.json();
}

async function apiGet(url) {
    const res = await fetch(url);
    return res.json();
}

async function reportUser(userId) {
    const reasonOptions = [
        'spam',
        'fake_profile',
        'harassment',
        'abuse',
        'other',
    ];
    const choice = prompt(
        'Report reason:\n' +
        '1. Spam\n' +
        '2. Fake profile\n' +
        '3. Harassment\n' +
        '4. Abuse\n' +
        '5. Other\n\n' +
        'Type a number or write a short reason.'
    );
    if (!choice) return;

    const index = Number(choice.trim()) - 1;
    const reason = reasonOptions[index] || choice.trim();
    const data = await apiPost('/api/reports.php', {
        action: 'report',
        reported_id: userId,
        reason,
    });

    alert(data.success ? 'Report submitted. Thank you.' : `Error: ${data.error ?? 'Unable to submit report.'}`);
}

async function blockUser(userId) {
    if (!confirm('Block this user? They will no longer be able to interact with you.')) return;
    const data = await apiPost('/api/reports.php', {
        action: 'block',
        blocked_id: userId,
    });

    if (data.success) {
        alert('User blocked.');
        window.location = '/dashboard.php';
        return;
    }

    alert(`Error: ${data.error ?? 'Unable to block user.'}`);
}

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

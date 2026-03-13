// main.js — shared utilities loaded on every page

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

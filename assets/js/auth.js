// auth.js — login / register page logic

document.addEventListener('DOMContentLoaded', () => {
    const loginForm = document.getElementById('loginForm');
    if (loginForm) {
        loginForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const email    = loginForm.querySelector('[name=email]').value;
            const password = loginForm.querySelector('[name=password]').value;

            const data = await apiPost('/api/auth.php', { action: 'login', email, password });
            if (data.success) {
                window.location = '/' + data.redirect;
            } else {
                const err = document.getElementById('loginError');
                err.textContent = data.error;
                err.style.display = 'block';
            }
        });
    }
});

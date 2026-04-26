<?php
require_once __DIR__ . '/includes/session.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/dashboard.php');
    exit;
}

$token = trim((string)($_GET['token'] ?? ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>Reset Password — HarmonyMatch</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
</head>
<body>
  <div class="auth-wrap">
    <div class="auth-right" style="width:100%;">
      <div class="auth-card">
        <div class="logo-wrap" style="margin-bottom:28px;">
          <div class="logo-icon"><i class="fas fa-music"></i></div>
          <span class="logo-text">HarmonyMatch</span>
        </div>

        <h1 style="font-size:26px;font-weight:800;letter-spacing:-0.4px;">Reset password</h1>
        <p id="resetIntro" style="font-size:14.5px;color:var(--text-secondary);margin:6px 0 24px;">Choose a new password for your account.</p>

        <p class="error-msg" id="resetError"></p>
        <p class="success-msg" id="resetSuccess"></p>

        <form id="resetForm">
          <input type="hidden" id="resetToken" value="<?= htmlspecialchars($token) ?>">
          <div class="field">
            <label class="field-label">New Password</label>
            <input class="field-input" type="password" id="newPassword" autocomplete="new-password" required>
          </div>
          <div class="field">
            <label class="field-label">Confirm Password</label>
            <input class="field-input" type="password" id="confirmPassword" autocomplete="new-password" required>
          </div>
          <button class="btn-primary" type="submit" style="width:100%;justify-content:center;font-size:16px;padding:14px;">
            <i class="fas fa-key"></i> Save New Password
          </button>
        </form>

        <p style="text-align:center;font-size:13.5px;color:var(--text-secondary);margin-top:20px;">
          <a class="form-link" href="/login.php">Back to login</a>
        </p>
      </div>
    </div>
  </div>

  <script>
    const token = document.getElementById('resetToken').value;
    const form = document.getElementById('resetForm');
    const err = document.getElementById('resetError');
    const success = document.getElementById('resetSuccess');

    async function validateToken() {
      if (!token) {
        err.textContent = 'Reset link is missing a token.';
        err.style.display = 'block';
        form.style.display = 'none';
        return;
      }

      const res = await fetch('/api/auth.php?action=validate_reset_token&token=' + encodeURIComponent(token));
      const data = await res.json();
      if (!data.success) {
        err.textContent = data.error || 'Reset link is invalid or expired.';
        err.style.display = 'block';
        form.style.display = 'none';
      }
    }

    form.addEventListener('submit', async (event) => {
      event.preventDefault();
      err.style.display = 'none';
      success.style.display = 'none';

      const body = new URLSearchParams({
        action: 'reset_password',
        token,
        password: document.getElementById('newPassword').value,
        confirm: document.getElementById('confirmPassword').value,
      });

      const res = await fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: body.toString(),
      });
      const data = await res.json();
      if (!data.success) {
        err.textContent = data.error || 'Unable to reset password.';
        err.style.display = 'block';
        return;
      }

      success.textContent = 'Password updated. Redirecting to login...';
      success.style.display = 'block';
      setTimeout(() => window.location = '/login.php', 900);
    });

    validateToken();
  </script>
</body>
</html>

<?php
require_once __DIR__ . '/includes/session.php';

if (!empty($_SESSION['user_id']) && !empty($_SESSION['is_admin'])) {
    header('Location: ' . $baseUrl . '/admin.php');
    exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Admin Login — HarmonyMatch</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css">
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>
<body class="admin-login-body">
  <main class="admin-login-shell">
    <section class="admin-login-card">
      <div class="admin-login-badge">
        <i class="fas fa-shield-halved"></i>
        Staff Only
      </div>
      <h1>Admin Console</h1>
      <p class="admin-login-copy">Sign in with an administrator account to access moderation tools, audit history, and user management.</p>

      <div id="adminLoginError" class="alert-error" style="display:none;"></div>

      <form id="adminLoginForm" class="admin-login-form">
        <label class="form-label" for="admin-email">Admin Email</label>
        <input id="admin-email" class="form-input" type="email" name="email" autocomplete="username" required>

        <label class="form-label" for="admin-password">Password</label>
        <input id="admin-password" class="form-input" type="password" name="password" autocomplete="current-password" required>

        <button class="btn-primary admin-login-submit" type="submit">
          <i class="fas fa-right-to-bracket"></i>
          Sign In To Admin
        </button>
      </form>

      <div class="admin-login-links">
        <a href="<?= $baseUrl ?>/login.php">User login</a>
        <span>&bull;</span>
        <a href="<?= $baseUrl ?>/index.php">Main site</a>
      </div>
    </section>
  </main>

  <script src="<?= $baseUrl ?>/assets/js/main.js"></script>
  <script src="<?= $baseUrl ?>/assets/js/auth.js"></script>
</body>
</html>

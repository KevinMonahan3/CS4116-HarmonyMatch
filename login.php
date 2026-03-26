<?php
require_once __DIR__ . '/includes/session.php';
if (!empty($_SESSION['user_id'])) {
    header('Location: ' . $baseUrl . '/dashboard.php'); exit;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>HarmonyMatch — Login / Register</title>
  <link rel="stylesheet" href="<?= $baseUrl ?>/assets/css/styles.css" />
  <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css" />
  <style>
    .deploy-badge {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 8px 14px; margin-bottom: 18px; border-radius: 999px;
      background: rgba(6,182,212,0.14); border: 1px solid rgba(6,182,212,0.28);
      color: #7dd3fc; font-size: 12px; font-weight: 700; letter-spacing: 0.08em; text-transform: uppercase;
      box-shadow: 0 0 24px rgba(6,182,212,0.14);
    }
    .deploy-badge i { font-size: 12px; }
    .feature-item { display: flex; align-items: flex-start; gap: 14px; margin-bottom: 24px; }
    .feature-icon {
      width: 40px; height: 40px; border-radius: 10px;
      background: rgba(124,58,237,0.2); border: 1px solid rgba(124,58,237,0.35);
      display: flex; align-items: center; justify-content: center;
      color: var(--accent-purple-light); font-size: 16px; flex-shrink: 0;
    }
    .feature-title { font-size: 14.5px; font-weight: 600; color: var(--text-primary); }
    .feature-desc  { font-size: 13px; color: var(--text-secondary); margin-top: 2px; }
    .heart-hero {
      width: 160px; height: 160px; margin-bottom: 40px;
      background:
        radial-gradient(circle at 30% 30%, rgba(255,255,255,0.24), transparent 35%),
        linear-gradient(135deg, #06b6d4 0%, #7c3aed 45%, #d946ef 100%);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 72px; color: #fff; box-shadow: 0 0 60px rgba(6,182,212,0.35), 0 0 120px rgba(217,70,239,0.2);
      animation: float 4s ease-in-out infinite;
    }
    @keyframes float { 0%,100% { transform: translateY(0); } 50% { transform: translateY(-12px); } }
    .auth-brand-title { font-size: 28px; font-weight: 800; color: var(--text-primary); margin-bottom: 10px; letter-spacing: -0.5px; }
    .auth-brand-sub   { font-size: 15px; color: var(--text-secondary); margin-bottom: 40px; font-style: italic; }
    .divider-or { display: flex; align-items: center; gap: 12px; margin: 20px 0; }
    .divider-or span { font-size: 13px; color: var(--text-muted); flex-shrink: 0; }
    .divider-or::before, .divider-or::after { content: ''; flex: 1; height: 1px; background: var(--border); }
    .input-wrap { position: relative; }
    .input-wrap i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
    .input-wrap .field-input { padding-left: 40px; }
    .form-link { font-size: 13.5px; color: var(--accent-purple-light); cursor: pointer; }
    .form-link:hover { text-decoration: underline; }
    .check-row { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-secondary); }
    .check-row input { accent-color: var(--accent-purple); width: 15px; height: 15px; }
    .error-msg { color: #ef4444; font-size: 13.5px; margin-bottom: 12px; display: none; }
    .success-msg { color: #10b981; font-size: 13.5px; margin-bottom: 12px; display: none; }
  </style>
</head>
<body>
  <div class="auth-wrap">

    <!-- Left brand panel -->
    <div class="auth-left">
      <div style="position:relative;z-index:1;text-align:center;width:100%;">
        <div class="deploy-badge">
          <i class="fas fa-cloud"></i>
          Oracle VM Build Live
        </div>
        <div class="heart-hero" style="margin:0 auto 40px;">
          <i class="fas fa-heart"></i>
        </div>
        <div class="logo-wrap" style="justify-content:center;margin-bottom:12px;">
          <div class="logo-icon"><i class="fas fa-music"></i></div>
          <span class="logo-text" style="font-size:26px;">HarmonyMatch</span>
        </div>
        <p class="auth-brand-title" style="font-size:20px;font-weight:600;margin-top:8px;">Find Love in the Same Frequency.</p>
        <p class="auth-brand-sub">Where playlists become introductions.</p>

        <div style="text-align:left;margin-top:8px;">
          <div class="feature-item">
            <div class="feature-icon"><i class="fas fa-sliders"></i></div>
            <div>
              <div class="feature-title">Music-Powered Matching</div>
              <div class="feature-desc">Matched by genres, songs, artists &amp; moods.</div>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon" style="background:rgba(217,70,239,0.15);border-color:rgba(217,70,239,0.3);color:#e879f9;">
              <i class="fas fa-percentage"></i>
            </div>
            <div>
              <div class="feature-title">Compatibility Score</div>
              <div class="feature-desc">See exactly how well you vibe, in numbers.</div>
            </div>
          </div>
          <div class="feature-item">
            <div class="feature-icon" style="background:rgba(6,182,212,0.12);border-color:rgba(6,182,212,0.25);color:var(--accent-cyan);">
              <i class="fas fa-list"></i>
            </div>
            <div>
              <div class="feature-title">Shared Playlist</div>
              <div class="feature-desc">Auto-generated playlist for every match.</div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Right auth panel -->
    <div class="auth-right">
      <div class="auth-card">

        <div style="margin-bottom:32px;">
          <h1 id="formTitle" style="font-size:26px;font-weight:800;letter-spacing:-0.4px;">Welcome back</h1>
          <p style="font-size:14.5px;color:var(--text-secondary);margin-top:6px;">Sign in to find your musical soulmate.</p>
        </div>

        <!-- Tab switcher -->
        <div class="auth-tabs">
          <div class="auth-tab active" id="tab-login" onclick="switchTab('login')">Log In</div>
          <div class="auth-tab"        id="tab-reg"   onclick="switchTab('reg')">Register</div>
        </div>

        <!-- LOGIN FORM -->
        <div id="login-form">
          <p class="error-msg" id="loginError"></p>
          <div class="field">
            <label class="field-label">Email Address</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input class="field-input" type="email" id="loginEmail" placeholder="you@example.com" />
            </div>
          </div>
          <div class="field">
            <label class="field-label">Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="loginPassword" placeholder="••••••••" />
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <label class="check-row"><input type="checkbox" /> Remember me</label>
          </div>
          <button class="btn-primary" onclick="doLogin()" style="width:100%;justify-content:center;font-size:16px;padding:14px;">
            <i class="fas fa-sign-in-alt"></i> Sign In
          </button>
          <p style="text-align:center;font-size:13.5px;color:var(--text-secondary);margin-top:20px;">
            New here? <a class="form-link" onclick="switchTab('reg')">Create an account</a>
          </p>
        </div>

        <!-- REGISTER FORM -->
        <div id="reg-form" style="display:none;">
          <p class="error-msg" id="regError"></p>
          <p class="success-msg" id="regSuccess"></p>
          <div style="display:grid;grid-template-columns:1fr 1fr;gap:16px;">
            <div class="field" style="margin-bottom:0;">
              <label class="field-label">First Name</label>
              <input class="field-input" type="text" id="regFirstName" placeholder="Jamie" />
            </div>
            <div class="field" style="margin-bottom:0;">
              <label class="field-label">Last Name</label>
              <input class="field-input" type="text" id="regLastName" placeholder="Cole" />
            </div>
          </div>
          <div class="field" style="margin-top:16px;">
            <label class="field-label">Email Address</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input class="field-input" type="email" id="regEmail" placeholder="you@example.com" />
            </div>
          </div>
          <div class="field">
            <label class="field-label">Date of Birth</label>
            <input class="field-input" type="date" id="regDob" />
          </div>
          <div class="field">
            <label class="field-label">Gender</label>
            <select class="field-input" id="regGender">
              <option value="">Select...</option>
              <option value="male">Male</option>
              <option value="female">Female</option>
              <option value="non-binary">Non-binary</option>
              <option value="other">Other</option>
            </select>
          </div>
          <div class="field">
            <label class="field-label">Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="regPassword" placeholder="Min. 8 characters" />
            </div>
          </div>
          <div class="field">
            <label class="field-label">Confirm Password</label>
            <div class="input-wrap">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="regConfirm" placeholder="Re-enter password" />
            </div>
          </div>
          <label class="check-row" style="margin-bottom:20px;">
            <input type="checkbox" id="regTerms" /> I agree to the <a class="form-link" style="margin-left:4px;">Terms &amp; Privacy Policy</a>
          </label>
          <button class="btn-primary" onclick="doRegister()" style="width:100%;justify-content:center;font-size:16px;padding:14px;">
            <i class="fas fa-user-plus"></i> Create Account
          </button>
          <p style="text-align:center;font-size:13.5px;color:var(--text-secondary);margin-top:20px;">
            Already have an account? <a class="form-link" onclick="switchTab('login')">Log in</a>
          </p>
        </div>

      </div>
    </div>
  </div>

  <script>
    function switchTab(tab) {
      document.getElementById('login-form').style.display = tab === 'login' ? 'block' : 'none';
      document.getElementById('reg-form').style.display   = tab === 'reg'   ? 'block' : 'none';
      document.getElementById('tab-login').classList.toggle('active', tab === 'login');
      document.getElementById('tab-reg').classList.toggle('active',   tab === 'reg');
      document.getElementById('formTitle').textContent = tab === 'login' ? 'Welcome back' : 'Create account';
    }

    async function doLogin() {
      const email    = document.getElementById('loginEmail').value.trim();
      const password = document.getElementById('loginPassword').value;
      const errEl    = document.getElementById('loginError');
      errEl.style.display = 'none';

      const res  = await fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=login&email=${encodeURIComponent(email)}&password=${encodeURIComponent(password)}`
      });
      const data = await res.json();
      if (data.success) {
        window.location = '/' + data.redirect;
      } else {
        errEl.textContent    = data.error;
        errEl.style.display  = 'block';
      }
    }

    async function doRegister() {
      const firstName = document.getElementById('regFirstName').value.trim();
      const lastName  = document.getElementById('regLastName').value.trim();
      const email     = document.getElementById('regEmail').value.trim();
      const dob       = document.getElementById('regDob').value;
      const gender    = document.getElementById('regGender').value;
      const password  = document.getElementById('regPassword').value;
      const confirm   = document.getElementById('regConfirm').value;
      const terms     = document.getElementById('regTerms').checked;
      const errEl     = document.getElementById('regError');
      const sucEl     = document.getElementById('regSuccess');

      errEl.style.display = 'none';
      sucEl.style.display = 'none';

      if (password !== confirm) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; return; }
      if (!terms)               { errEl.textContent = 'Please agree to the terms.'; errEl.style.display = 'block'; return; }

      const res  = await fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=register&name=${encodeURIComponent(firstName + ' ' + lastName)}&email=${encodeURIComponent(email)}&dob=${encodeURIComponent(dob)}&gender=${encodeURIComponent(gender)}&password=${encodeURIComponent(password)}`
      });
      const data = await res.json();
      if (data.success) {
        sucEl.textContent   = 'Account created! Redirecting...';
        sucEl.style.display = 'block';
        setTimeout(() => window.location = '/onboarding.php', 1000);
      } else {
        errEl.textContent   = data.error;
        errEl.style.display = 'block';
      }
    }

    // Allow Enter key to submit
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const loginVisible = document.getElementById('login-form').style.display !== 'none';
        loginVisible ? doLogin() : doRegister();
      }
    });
  </script>
</body>
</html>

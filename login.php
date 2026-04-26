<?php
// Fetch latest commit from GitHub, cached for 5 minutes to avoid rate-limiting
$latestCommit = null;
$cacheFile    = sys_get_temp_dir() . '/hm_latest_commit.json';
$cacheTtl     = 300; // seconds

if (is_file($cacheFile) && (time() - filemtime($cacheFile)) < $cacheTtl) {
    // Serve from cache
    $cached = @json_decode(file_get_contents($cacheFile), true);
    if (!empty($cached['sha'])) {
        $latestCommit = $cached;
    }
} else {
    $ghRepo = 'KevinMonahan3/CS4116-HarmonyMatch';
    $ghApi  = "https://api.github.com/repos/{$ghRepo}/commits?per_page=1";
    $ctx    = stream_context_create(['http' => [
        'header'  => "User-Agent: HarmonyMatch\r\n",
        'timeout' => 4,
    ]]);
    $raw = @file_get_contents($ghApi, false, $ctx);
    if ($raw) {
        $commits = json_decode($raw, true);
        if (!empty($commits[0])) {
            $c = $commits[0];
            $latestCommit = [
                'sha'     => substr($c['sha'], 0, 7),
                'message' => strtok($c['commit']['message'], "\n"),
                'author'  => $c['commit']['author']['name'],
                'date'    => date('d M Y', strtotime($c['commit']['author']['date'])),
            ];
            // Write to cache even if we already have one, so the TTL resets
            @file_put_contents($cacheFile, json_encode($latestCommit));
        }
    } elseif (is_file($cacheFile)) {
        // API failed but we have a stale cache — use it rather than showing nothing
        $cached = @json_decode(file_get_contents($cacheFile), true);
        if (!empty($cached['sha'])) {
            $latestCommit = $cached;
        }
    }
}

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
    .input-wrap > i { position: absolute; left: 14px; top: 50%; transform: translateY(-50%); color: var(--text-muted); font-size: 14px; }
    .input-wrap .field-input { padding-left: 40px; }
    .form-link { font-size: 13.5px; color: var(--accent-purple-light); cursor: pointer; }
    .form-link:hover { text-decoration: underline; }
    .check-row { display: flex; align-items: center; gap: 8px; font-size: 13.5px; color: var(--text-secondary); }
    .check-row input { accent-color: var(--accent-purple); width: 15px; height: 15px; }
    .error-msg { color: #ef4444; font-size: 13.5px; margin-bottom: 12px; display: none; }
    .success-msg { color: #10b981; font-size: 13.5px; margin-bottom: 12px; display: none; }
    .pw-reqs { margin-top:8px; padding:10px 12px; border-radius:8px; background:rgba(255,255,255,0.04); border:1px solid rgba(255,255,255,0.08); }
    .pw-reqs-title { font-size:11px; font-weight:700; color:var(--text-muted); margin-bottom:6px; text-transform:uppercase; letter-spacing:0.06em; }
    .pw-req { font-size:12.5px; color:var(--text-muted); display:flex; align-items:center; gap:6px; margin-bottom:3px; transition:color 0.2s; }
    .pw-req:last-child { margin-bottom:0; }
    .pw-req.met { color:#10b981; }
    .pw-req i { font-size:11px; width:12px; }
    /* Password visibility toggle */
    .pw-toggle {
      position:absolute; right:12px; top:50%; transform:translateY(-50%);
      background:none; border:none; color:var(--text-muted); font-size:14px;
      cursor:pointer; padding:4px; line-height:1;
      -webkit-tap-highlight-color:transparent; transition:color 0.15s;
    }
    .pw-toggle:hover { color:var(--text-secondary); }
    .input-wrap.has-toggle .field-input { padding-right: 40px; }
    /* Mobile-only logo above the auth card */
    .mobile-auth-logo { display:none; align-items:center; justify-content:center; gap:10px; margin-bottom:28px; }
    @media (max-width:900px) { .mobile-auth-logo { display:flex; } }
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
          <!-- Line at bottom showing last commit made -->
          <?php if ($latestCommit): ?>
          <div style="margin-top:32px;padding:12px 16px;border-radius:10px;background:rgba(255,255,255,0.04);border:1px solid rgba(255,255,255,0.08);text-align:left;">
              <div style="font-size:11px;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:var(--text-muted);margin-bottom:6px;">
                <i class="fas fa-code-branch" style="margin-right:5px;"></i>Latest Commit
              </div>
              <div style="font-size:13px;color:var(--text-primary);font-weight:500;margin-bottom:4px;">
                  <?= htmlspecialchars($latestCommit['message']) ?>
              </div>
              <div style="font-size:12px;color:var(--text-secondary);">
                  <code style="background:rgba(124,58,237,0.2);padding:1px 6px;border-radius:4px;font-size:11px;">
                      <?= htmlspecialchars($latestCommit['sha']) ?>
                  </code>
                  &nbsp;<?= htmlspecialchars($latestCommit['author']) ?> · <?= htmlspecialchars($latestCommit['date']) ?>
              </div>
          </div>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Right auth panel -->
    <div class="auth-right">
      <div class="auth-card">

        <div class="mobile-auth-logo">
          <div class="logo-icon"><i class="fas fa-music"></i></div>
          <span class="logo-text" style="font-size:22px;">HarmonyMatch</span>
        </div>

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
          <p class="success-msg" id="resetRequestSuccess"></p>
          <div class="field">
            <label class="field-label">Email Address</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input class="field-input" type="email" id="loginEmail" placeholder="you@example.com" />
            </div>
          </div>
          <div class="field">
            <label class="field-label">Password</label>
            <div class="input-wrap has-toggle">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="loginPassword" placeholder="••••••••" />
              <button type="button" class="pw-toggle" onclick="togglePw('loginPassword', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
            </div>
          </div>
          <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:24px;">
            <label class="check-row"><input type="checkbox" /> Remember me</label>
            <a class="form-link" onclick="showForgotPassword()">Forgot password?</a>
          </div>
          <button class="btn-primary" onclick="doLogin()" style="width:100%;justify-content:center;font-size:16px;padding:14px;">
            <i class="fas fa-sign-in-alt"></i> Sign In
          </button>
          <p style="text-align:center;font-size:13.5px;color:var(--text-secondary);margin-top:20px;">
            New here? <a class="form-link" onclick="switchTab('reg')">Create an account</a>
          </p>
        </div>

        <div id="forgot-form" style="display:none;">
          <p class="error-msg" id="forgotError"></p>
          <p class="success-msg" id="forgotSuccess"></p>
          <div class="field">
            <label class="field-label">Email Address</label>
            <div class="input-wrap">
              <i class="fas fa-envelope"></i>
              <input class="field-input" type="email" id="forgotEmail" placeholder="you@example.com" />
            </div>
          </div>
          <button class="btn-primary" onclick="requestPasswordReset()" style="width:100%;justify-content:center;font-size:16px;padding:14px;">
            <i class="fas fa-paper-plane"></i> Send Reset Link
          </button>
          <p style="text-align:center;font-size:13.5px;color:var(--text-secondary);margin-top:20px;">
            <a class="form-link" onclick="switchTab('login')">Back to login</a>
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
            <div style="font-size:12.5px;color:var(--text-secondary);margin-bottom:6px;">You must be at least 18 years old to register.</div>
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
            <label class="field-label">Profile Photo</label>
            <div style="display:flex;align-items:center;gap:12px;">
              <div id="regPhotoPreview" style="width:54px;height:54px;border-radius:50%;border:1px solid var(--border);background:var(--bg-surface);display:flex;align-items:center;justify-content:center;color:var(--text-muted);overflow:hidden;flex-shrink:0;">
                <i class="fas fa-user"></i>
              </div>
              <label class="btn-outline" style="font-size:13px;cursor:pointer;">
                <i class="fas fa-camera"></i> Add Photo
                <input type="file" id="regPhoto" accept="image/*" style="display:none;">
              </label>
            </div>
            <div style="font-size:12.5px;color:var(--text-muted);margin-top:6px;">Optional. JPG, PNG, or WEBP up to 3MB.</div>
          </div>
          <div class="field">
            <label class="field-label">Password</label>
            <div class="input-wrap has-toggle">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="regPassword" placeholder="Create a password" oninput="checkPwReqs()" />
              <button type="button" class="pw-toggle" onclick="togglePw('regPassword', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
            </div>
            <div class="pw-reqs">
              <div class="pw-reqs-title">Password must have:</div>
              <div class="pw-req" id="req-length"><i class="fas fa-circle-xmark"></i> At least 8 characters</div>
              <div class="pw-req" id="req-upper"><i class="fas fa-circle-xmark"></i> At least 1 uppercase letter</div>
              <div class="pw-req" id="req-number"><i class="fas fa-circle-xmark"></i> At least 1 number</div>
              <div class="pw-req" id="req-special"><i class="fas fa-circle-xmark"></i> At least 1 special character (!@#$% etc.)</div>
            </div>
          </div>
          <div class="field">
            <label class="field-label">Confirm Password</label>
            <div class="input-wrap has-toggle">
              <i class="fas fa-lock"></i>
              <input class="field-input" type="password" id="regConfirm" placeholder="Re-enter password" />
              <button type="button" class="pw-toggle" onclick="togglePw('regConfirm', this)" aria-label="Toggle password visibility"><i class="fas fa-eye"></i></button>
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
    function togglePw(inputId, btn) {
      const input = document.getElementById(inputId);
      const icon  = btn.querySelector('i');
      if (input.type === 'password') {
        input.type = 'text';
        icon.className = 'fas fa-eye-slash';
      } else {
        input.type = 'password';
        icon.className = 'fas fa-eye';
      }
    }

    function switchTab(tab) {
      document.getElementById('login-form').style.display = tab === 'login' ? 'block' : 'none';
      document.getElementById('reg-form').style.display   = tab === 'reg'   ? 'block' : 'none';
      document.getElementById('forgot-form').style.display = 'none';
      document.getElementById('tab-login').classList.toggle('active', tab === 'login');
      document.getElementById('tab-reg').classList.toggle('active',   tab === 'reg');
      document.getElementById('formTitle').textContent = tab === 'login' ? 'Welcome back' : 'Create account';
    }

    function showForgotPassword() {
      document.getElementById('login-form').style.display = 'none';
      document.getElementById('reg-form').style.display = 'none';
      document.getElementById('forgot-form').style.display = 'block';
      document.getElementById('tab-login').classList.add('active');
      document.getElementById('tab-reg').classList.remove('active');
      document.getElementById('formTitle').textContent = 'Reset password';
      document.getElementById('forgotEmail').value = document.getElementById('loginEmail').value.trim();
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

    function checkPwReqs() {
      const pw = document.getElementById('regPassword').value;
      const set = (id, ok) => {
        const el = document.getElementById(id);
        el.classList.toggle('met', ok);
        el.querySelector('i').className = ok ? 'fas fa-circle-check' : 'fas fa-circle-xmark';
      };
      set('req-length',  pw.length >= 8);
      set('req-upper',   /[A-Z]/.test(pw));
      set('req-number',  /[0-9]/.test(pw));
      set('req-special', /[^A-Za-z0-9]/.test(pw));
    }

    function getPwErrors(pw) {
      const missing = [];
      if (pw.length < 8)           missing.push('at least 8 characters');
      if (!/[A-Z]/.test(pw))       missing.push('an uppercase letter');
      if (!/[0-9]/.test(pw))       missing.push('a number');
      if (!/[^A-Za-z0-9]/.test(pw)) missing.push('a special character');
      return missing;
    }

    function calculateAge(dob) {
      if (!dob) return 0;
      const birthDate = new Date(dob + 'T00:00:00');
      const today = new Date();
      let age = today.getFullYear() - birthDate.getFullYear();
      const monthDiff = today.getMonth() - birthDate.getMonth();
      if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age--;
      }
      return age;
    }

    document.getElementById('regPhoto')?.addEventListener('change', () => {
      const file = document.getElementById('regPhoto').files?.[0];
      const preview = document.getElementById('regPhotoPreview');
      if (!file || !preview) return;

      const url = URL.createObjectURL(file);
      preview.innerHTML = `<img src="${url}" alt="Profile preview" style="width:100%;height:100%;object-fit:cover;">`;
    });

    async function doRegister() {
      const firstName = document.getElementById('regFirstName').value.trim();
      const lastName  = document.getElementById('regLastName').value.trim();
      const email     = document.getElementById('regEmail').value.trim();
      const dob       = document.getElementById('regDob').value;
      const gender    = document.getElementById('regGender').value;
      const password  = document.getElementById('regPassword').value;
      const confirm   = document.getElementById('regConfirm').value;
      const terms     = document.getElementById('regTerms').checked;
      const photo     = document.getElementById('regPhoto').files?.[0] || null;
      const errEl     = document.getElementById('regError');
      const sucEl     = document.getElementById('regSuccess');

      errEl.style.display = 'none';
      sucEl.style.display = 'none';

      const pwErrors = getPwErrors(password);
      if (pwErrors.length > 0) {
        errEl.textContent = 'Password is missing: ' + pwErrors.join(', ') + '.';
        errEl.style.display = 'block';
        return;
      }
      if (calculateAge(dob) < 18) {
        errEl.textContent = 'You must be at least 18 years old to register.';
        errEl.style.display = 'block';
        return;
      }
      if (password !== confirm) { errEl.textContent = 'Passwords do not match.'; errEl.style.display = 'block'; return; }
      if (!terms)               { errEl.textContent = 'Please agree to the terms.'; errEl.style.display = 'block'; return; }

      const formData = new FormData();
      formData.append('action', 'register');
      formData.append('name', firstName + ' ' + lastName);
      formData.append('email', email);
      formData.append('dob', dob);
      formData.append('gender', gender);
      formData.append('password', password);
      if (photo) {
        formData.append('photo', photo);
      }

      const res  = await fetch('/api/auth.php', {
        method: 'POST',
        body: formData
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

    async function requestPasswordReset() {
      const email = document.getElementById('forgotEmail').value.trim();
      const errEl = document.getElementById('forgotError');
      const sucEl = document.getElementById('forgotSuccess');
      errEl.style.display = 'none';
      sucEl.style.display = 'none';

      const res = await fetch('/api/auth.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
        body: `action=request_password_reset&email=${encodeURIComponent(email)}`
      });
      const data = await res.json();

      if (!data.success) {
        errEl.textContent = data.error || 'Unable to request a reset link.';
        errEl.style.display = 'block';
        return;
      }

      sucEl.textContent = data.message || 'If that email exists, a reset link has been sent.';
      sucEl.style.display = 'block';
    }

    // Allow Enter key to submit
    document.addEventListener('keydown', (e) => {
      if (e.key === 'Enter') {
        const forgotVisible = document.getElementById('forgot-form').style.display !== 'none';
        const loginVisible = document.getElementById('login-form').style.display !== 'none';
        if (forgotVisible) requestPasswordReset();
        else loginVisible ? doLogin() : doRegister();
      }
    });
  </script>
</body>
</html>

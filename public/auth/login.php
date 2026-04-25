<?php
// ============================================================
// public/auth/login.php — Universal Login (Controller + View)
//
// MVC Role:
//   Model      → (uses auth.php directly for attemptLogin)
//   Controller → POST handling at the top
//   View       → HTML below
//
// Access: Public — no login required
// ============================================================
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../includes/auth.php';

// Already logged in → go to dashboard
if (isLoggedIn()) {
    redirect(getDashboardUrl());
}

$error = '';
if (isPost()) {
    verifyCsrf(APP_URL . '/public/auth/login.php');
    $username = inputStr('username');
    $password = $_POST['password'] ?? '';

    if (empty($username) || empty($password)) {
        $error = 'Please enter your username and password.';
    } else {
        $user = attemptLogin($username, $password);
        if ($user) {
            createUserSession($user);
            logActivity(null, 'user_login', $user['user_id'], null, 'User logged in');
            redirect(getDashboardUrl());
        } else {
            $error = 'Invalid credentials or account is inactive.';
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Sign In — <?= APP_NAME ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="login-page">

<!-- Top bar -->
<div class="login-topbar">
  <div class="brand">
    <img src="<?= APP_URL ?>/assets/images/spud_logo.png" alt="St Paul University Dumaguete" class="login-topbar-logo">
    <span class="login-wordmark">
      <strong>St Paul University</strong>
      <em>Dumaguete</em>
    </span>
  </div>
  <div class="login-nav">
    <a href="<?= APP_URL ?>/">Home</a>
    <span></span>
    <a href="#" class="help-link">Need help?</a>
  </div>
</div>

<!-- Body -->
<div class="login-body">

  <!-- Left Panel -->
  <div class="login-panel-left">
    <div class="login-hero-copy">
      <div class="login-gold-bar"></div>
      <h1>Welcome back!</h1>
      <p>Sign in to continue to the guest monitoring portal, visit records, and campus access tools.</p>
    </div>
  </div>

  <!-- Right Panel -->
  <div class="login-panel-right">
    <div class="login-card">
      <div class="login-card-logo">
        <img src="<?= APP_URL ?>/assets/images/spud_logo.png" alt="St Paul University Dumaguete">
      </div>
      <div class="login-title">Sign In</div>
      <div class="login-subtitle">Access your account to continue</div>

      <?php if ($error): ?>
      <div class="flash-msg flash-error" style="margin-bottom:16px">
        <i data-lucide="alert-circle"></i>
        <span><?= e($error) ?></span>
        <button class="flash-close" onclick="this.parentElement.remove()">
          <i data-lucide="x" style="width:14px;height:14px"></i>
        </button>
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['timeout'])): ?>
      <div class="flash-msg flash-warning" style="margin-bottom:16px">
        <i data-lucide="clock"></i>
        <span>Your session expired. Please sign in again.</span>
      </div>
      <?php endif; ?>

      <?php if (isset($_GET['logout'])): ?>
      <div class="flash-msg flash-success" style="margin-bottom:16px">
        <i data-lucide="check-circle"></i>
        <span>You have been signed out successfully.</span>
      </div>
      <?php endif; ?>

      <form method="POST" autocomplete="off">
        <?= csrfField() ?>

        <!-- Username -->
        <div class="form-group">
          <label class="form-label">Username</label>
          <div class="input-icon-wrap">
            <i data-lucide="mail" class="input-icon"></i>
            <input type="text" name="username" class="form-control"
                   placeholder="Enter your username"
                   value="<?= e($_POST['username'] ?? '') ?>"
                   required autofocus>
          </div>
        </div>

        <!-- Password -->
        <div class="form-group">
          <label class="form-label">Password</label>
          <div class="input-icon-wrap">
            <i data-lucide="lock" class="input-icon"></i>
            <input type="password" name="password" id="pwdInput" class="form-control"
                   placeholder="Enter your password" required>
            <button type="button" class="input-icon-right" onclick="togglePwd()" id="eyeBtn">
              <i data-lucide="eye" id="eyeIcon"></i>
            </button>
          </div>
        </div>

        <!-- Remember + Forgot -->
        <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;">
          <label style="display:flex;align-items:center;gap:7px;font-size:.8rem;color:var(--text-s);cursor:pointer">
            <input type="checkbox" name="remember" style="width:15px;height:15px;">
            Remember me
          </label>
          <a href="#" style="font-size:.8rem;color:var(--accent);font-weight:600;">Forgot password?</a>
        </div>

        <!-- Sign In button -->
        <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:11px;font-size:.9375rem;margin-bottom:10px;">
          Sign In
        </button>

        <!-- Back -->
        <a href="<?= APP_URL ?>/" class="btn btn-outline w-100" style="justify-content:center;padding:11px;">
          <i data-lucide="globe"></i> Back to Main Site
        </a>
      </form>

      <!-- Footer note -->
      <div style="text-align:center;margin-top:20px;font-size:.78rem;color:var(--text-m);">
        <i data-lucide="shield" style="width:13px;height:13px;vertical-align:middle;"></i>
        Authorized personnel only<br>
        All access is monitored and recorded for security.
      </div>
    </div>
  </div>
</div>

<!-- Footer bar -->
<div class="login-footer-bar">
  <span>&copy; <?= date('Y') ?> St. Paul University Dumaguete. All rights reserved.</span>
  <span class="divider">|</span>
  <span>Guest Monitoring and Visitor Management System v1.0</span>
</div>

<script>
lucide.createIcons();
function togglePwd() {
  const inp = document.getElementById('pwdInput');
  const ico = document.getElementById('eyeIcon');
  if (inp.type === 'password') {
    inp.type = 'text';
    ico.setAttribute('data-lucide','eye-off');
  } else {
    inp.type = 'password';
    ico.setAttribute('data-lucide','eye');
  }
  lucide.createIcons();
}
</script>
</body>
</html>

<?php
/**
 * index.php - Public Landing Page
 * Public paths: Guest Pre-Registration, Staff Login, and Visit Status
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/helpers.php';
require_once __DIR__ . '/includes/auth.php';

if (isLoggedIn()) { redirect(getDashboardUrl()); }

$publicPageTitle = APP_NAME;
$publicBodyClass = 'landing-page home-landing-page';
include __DIR__ . '/includes/public_header.php';
?>

<!-- Hero -->
<div class="landing-hero">
  <div class="landing-shell">

    <section class="landing-visual" aria-label="St Paul University Dumaguete campus">
      <img src="<?= APP_URL ?>/assets/images/home1.jpg" alt="St Paul University Dumaguete campus" class="landing-photo">
      <div class="landing-visual-panel">
        <img src="<?= APP_URL ?>/assets/images/spud_logo.png" alt="St Paul University Dumaguete" class="landing-seal">
        <div>
          <div class="landing-uni">St Paul University Dumaguete</div>
          <div class="landing-sys">Guest Monitoring & Visitor Management System</div>
        </div>
      </div>
    </section>

    <section class="landing-actions" aria-label="Guest monitoring actions">
      <div class="landing-brand">
        <div class="landing-kicker">Visitor access portal</div>
        <h1>Choose how you want to continue.</h1>
        <p class="landing-tagline">
          Pre-register before arriving, sign in as authorized personnel, or check the current status of an existing visit.
        </p>
      </div>

      <div class="landing-cards">

        <!-- Card 1: Guest Pre-Registration -->
        <a href="<?= APP_URL ?>/public/preregister.php" class="landing-card">
          <div class="landing-card-icon guest">
            <i data-lucide="calendar-plus"></i>
          </div>
          <div class="landing-card-copy">
            <h3>Pre-Register Your Visit</h3>
            <p>Register online before arriving at the gate and receive a reference number for faster verification.</p>
          </div>
          <span class="card-action accent">
            <i data-lucide="arrow-right"></i>
            Register
          </span>
        </a>

        <!-- Card 2: Staff Login -->
        <a href="<?= APP_URL ?>/public/auth/login.php" class="landing-card">
          <div class="landing-card-icon staff">
            <i data-lucide="lock-keyhole"></i>
          </div>
          <div class="landing-card-copy">
            <h3>Staff Portal</h3>
            <p>Authorized access for guards, office staff, and administrators managing campus visits.</p>
          </div>
          <span class="card-action primary">
            <i data-lucide="log-in"></i>
            Sign In
          </span>
        </a>

      </div>

      <a href="<?= APP_URL ?>/public/check_status.php" class="landing-status-card">
        <span>
          <i data-lucide="search"></i>
          Check Visit Status
        </span>
        <i data-lucide="chevron-right"></i>
      </a>
    </section>

  </div>
</div>

<?php include __DIR__ . '/includes/public_footer.php'; ?>

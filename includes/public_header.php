<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Shared public-facing page header for pages that guests can open without logging in.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * Public page shell header.
 * Used by the landing page, guest pre-registration, and status checker.
 */

$publicPageTitle = $publicPageTitle ?? APP_NAME;
$publicBodyClass = $publicBodyClass ?? 'landing-page';
$publicBackUrl = $publicBackUrl ?? null;
$publicBackLabel = $publicBackLabel ?? 'Back to Home';
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title><?= htmlspecialchars($publicPageTitle) ?></title>
<link rel="stylesheet" href="<?= APP_URL ?>/assets/css/style.css">
<script src="https://unpkg.com/lucide@latest/dist/umd/lucide.min.js"></script>
</head>
<body class="<?= htmlspecialchars($publicBodyClass) ?>">

<div class="login-topbar public-topbar">
  <a href="<?= APP_URL ?>/" class="brand public-brand">
    <img src="<?= APP_URL ?>/assets/images/spud_logo.png" alt="St. Paul University Dumaguete" class="public-brand-logo">
    <span>Guest Monitoring System</span>
  </a>

  <?php if ($publicBackUrl): ?>
  <a href="<?= htmlspecialchars($publicBackUrl) ?>" class="help-link">
    <i data-lucide="arrow-left"></i> <?= htmlspecialchars($publicBackLabel) ?>
  </a>
  <?php else: ?>
  <a href="<?= APP_URL ?>/public/help/index.php?audience=guest" class="help-link">
    <i data-lucide="help-circle"></i> Need help?
  </a>
  <?php endif; ?>
</div>

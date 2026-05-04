<?php
/**
 * public/help/index.php - Public help guide for guests and staff.
 */
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/helpers.php';

$audience = ($_GET['audience'] ?? 'guest') === 'staff' ? 'staff' : 'guest';
$publicPageTitle = 'Need Help? - ' . APP_NAME;
$publicBodyClass = 'landing-page';
$publicBackUrl = APP_URL . '/';
$publicBackLabel = 'Back to Home';
include __DIR__ . '/../../includes/public_header.php';
?>

<div style="flex:1;padding:34px 24px;">
  <div style="max-width:980px;margin:0 auto;">
    <div style="margin-bottom:22px;">
      <div class="page-title">Need Help?</div>
      <p style="color:var(--text-s);font-size:.95rem;margin-top:6px;max-width:720px;">
        Quick guide for using the University Guest Monitoring and Visitor Management System.
      </p>
    </div>

    <div style="display:flex;gap:10px;flex-wrap:wrap;margin-bottom:20px;">
      <a href="#guest-help" class="btn <?= $audience === 'guest' ? 'btn-primary' : 'btn-outline' ?>">
        <i data-lucide="user"></i> Guest Guide
      </a>
      <a href="#staff-help" class="btn <?= $audience === 'staff' ? 'btn-primary' : 'btn-outline' ?>">
        <i data-lucide="shield-check"></i> Staff Guide
      </a>
    </div>

    <?php if ($audience === 'staff'): ?>
      <?php include __DIR__ . '/partials/staff_help.php'; ?>
      <?php include __DIR__ . '/partials/guest_help.php'; ?>
    <?php else: ?>
      <?php include __DIR__ . '/partials/guest_help.php'; ?>
      <?php include __DIR__ . '/partials/staff_help.php'; ?>
    <?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/../../includes/public_footer.php'; ?>

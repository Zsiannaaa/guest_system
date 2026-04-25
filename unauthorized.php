<?php
/**
 * unauthorized.php — Access Denied Page
 */
require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/includes/auth.php';
require_once __DIR__ . '/includes/helpers.php';
$pageTitle = 'Access Denied';
include __DIR__ . '/includes/header.php';
?>

<div style="display:flex;align-items:center;justify-content:center;min-height:60vh;">
  <div style="text-align:center;">
    <div style="width:80px;height:80px;background:var(--danger-l);border-radius:50%;margin:0 auto 20px;display:flex;align-items:center;justify-content:center;">
      <i data-lucide="shield-x" style="width:38px;height:38px;color:var(--danger);"></i>
    </div>
    <h2 style="color:var(--primary);font-weight:700;margin-bottom:8px;">Access Denied</h2>
    <p style="color:var(--text-s);margin-bottom:20px;">You do not have permission to access this page.</p>
    <a href="<?= getDashboardUrl() ?>" class="btn btn-primary">
      <i data-lucide="arrow-left"></i> Return to Dashboard
    </a>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/includes/footer.php'; ?>

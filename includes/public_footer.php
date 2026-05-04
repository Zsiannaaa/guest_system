<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Shared public-facing footer that closes public pages and loads common scripts.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * Public page shell footer.
 */

$publicExtraScripts = $publicExtraScripts ?? '';
?>
<div class="login-footer-bar">
  <span>&copy; <?= date('Y') ?> St. Paul University Dumaguete. All rights reserved.</span>
  <span class="divider">|</span>
  <span>Guest Monitoring and Visitor Management System v<?= APP_VERSION ?></span>
</div>

<script>
lucide.createIcons();
<?= $publicExtraScripts ?>
</script>
</body>
</html>

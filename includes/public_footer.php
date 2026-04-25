<?php
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

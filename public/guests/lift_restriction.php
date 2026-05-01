<?php
/**
 * guests/lift_restriction.php
 * Admin: Lift/remove an active restriction from a guest
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests/guests_module.php';

requireRole(ROLE_ADMIN);
$db = getDB();

$guestId = (int) ($_GET['id'] ?? 0);
if (!$guestId) redirect(APP_URL . '/public/guests/restricted.php');

$gStmt = $db->prepare("SELECT * FROM guests WHERE guest_id=:id AND is_restricted=1");
$gStmt->execute([':id' => $guestId]);
$guest = $gStmt->fetch();
if (!$guest) {
    setFlash('info', 'This guest is not restricted or does not exist.');
    redirect(APP_URL . '/public/guests/restricted.php');
}

if (!isPost()) {
    $pageTitle = 'Lift Restriction';
    include __DIR__ . '/../../includes/header.php';
    ?>
    <div class="page-top">
      <div>
        <div class="page-title">Lift Restriction</div>
        <ul class="breadcrumb">
          <li><a href="<?= APP_URL ?>/public/guests/restricted.php">Restricted Guests</a></li>
          <li><?= e($guest['full_name']) ?></li>
        </ul>
      </div>
    </div>

    <div class="info-box warning" style="margin-bottom:20px;">
      <i data-lucide="alert-triangle"></i>
      <div>You are about to allow <strong><?= e($guest['full_name']) ?></strong> to be processed for future campus visits again.</div>
    </div>

    <div class="form-card">
      <div class="card">
        <div class="card-header"><i data-lucide="shield-check" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Confirm Action</div>
        <div class="card-body">
          <form method="POST">
            <?= csrfField() ?>
            <div style="display:flex;gap:10px;">
              <button type="submit" class="btn btn-success"><i data-lucide="shield-check"></i> Lift Restriction</button>
              <a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $guestId ?>" class="btn btn-outline">Cancel</a>
            </div>
          </form>
        </div>
      </div>
    </div>
    <script>lucide.createIcons();</script>
    <?php
    include __DIR__ . '/../../includes/footer.php';
    exit;
}

verifyCsrf(APP_URL . '/public/guests/lift_restriction.php?id=' . $guestId);

$db->beginTransaction();

// Lift restriction record
$db->prepare("
    UPDATE restricted_guests
    SET is_active = 0, lifted_at = NOW(), lifted_by_user_id = :uid
    WHERE guest_id = :gid AND is_active = 1
")->execute([':uid' => currentUserId(), ':gid' => $guestId]);

// Update guest flag
$db->prepare("UPDATE guests SET is_restricted=0, restriction_reason=NULL WHERE guest_id=:id")
   ->execute([':id' => $guestId]);

logActivity(null, 'other', currentUserId(), null,
    "Restriction lifted for guest '{$guest['full_name']}' (ID:{$guestId}) by admin");

$db->commit();

setFlash('success', "Restriction for <strong>{$guest['full_name']}</strong> has been lifted. Guest is now allowed campus access.");
redirect(APP_URL . '/public/guests/view.php?id=' . $guestId);

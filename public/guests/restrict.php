<?php
/**
 * guests/restrict.php — Flag guest as restricted
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests_module.php';
requireRole(ROLE_ADMIN); $db = getDB();
$guestId = (int)($_GET['id'] ?? 0);
if (!$guestId) redirect(APP_URL.'/public/guests/list.php');
$gStmt = $db->prepare("SELECT * FROM guests WHERE guest_id=:id"); $gStmt->execute([':id'=>$guestId]);
$guest = $gStmt->fetch(); if (!$guest) redirect(APP_URL.'/public/guests/list.php');
if ($guest['is_restricted']) { setFlash('info','Already restricted.'); redirect(APP_URL.'/public/guests/view.php?id='.$guestId); }
$pageTitle = 'Restrict Guest'; $errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guests/restrict.php?id=' . $guestId);
    $reason = trim($_POST['reason']??'');
    if (empty($reason)) $errors[]='Reason is required.';
    if (empty($errors)) {
        $db->beginTransaction();
        $db->prepare("UPDATE guests SET is_restricted=1, restriction_reason=:r WHERE guest_id=:id")->execute([':r'=>$reason,':id'=>$guestId]);
        $db->prepare("INSERT INTO restricted_guests (guest_id,reason,restricted_by_user_id,is_active) VALUES (:g,:r,:u,1)")->execute([':g'=>$guestId,':r'=>$reason,':u'=>currentUserId()]);
        logActivity(null,'guest_restricted',currentUserId(),null,"'{$guest['full_name']}' restricted: {$reason}");
        $db->commit();
        setFlash('success',"<strong>{$guest['full_name']}</strong> restricted."); redirect(APP_URL.'/public/guests/view.php?id='.$guestId);
    }
}
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title" style="color:var(--danger);">Restrict Guest</div>
  <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/guests/list.php">Guests</a></li><li><a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $guestId ?>"><?= e($guest['full_name']) ?></a></li><li>Restrict</li></ul></div>
</div>

<div class="info-box warning" style="margin-bottom:20px;"><i data-lucide="alert-triangle"></i><div>You are about to <strong>restrict</strong> <strong><?= e($guest['full_name']) ?></strong>. They will be flagged at the gate during check-in.</div></div>

<?php if (!empty($errors)): ?>
<div class="error-box"><ul><?php foreach($errors as $err):?><li><?= e($err) ?></li><?php endforeach;?></ul></div>
<?php endif; ?>

<div class="form-card">
<div class="card" style="border-color:var(--danger);">
  <div class="card-header" style="background:var(--danger);color:#fff;">
    <span><i data-lucide="shield-off" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Restriction Details</span>
  </div>
  <div class="card-body">
    <div style="margin-bottom:16px;font-size:.9rem;">
      <strong>Guest:</strong> <?= e($guest['full_name']) ?><br>
      <strong>Organization:</strong> <?= e($guest['organization'] ?? '—') ?><br>
      <strong>Contact:</strong> <?= e($guest['contact_number'] ?? '—') ?>
    </div>
    <form method="POST">
        <?= csrfField() ?>
      <div class="form-group"><label class="form-label">Reason for Restriction <span class="required-star">*</span></label>
        <textarea name="reason" class="form-control" rows="3" placeholder="Explain why this guest is being restricted" required><?= e($_POST['reason']??'') ?></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn" style="background:var(--danger);color:#fff;font-weight:700;"><i data-lucide="shield-off"></i> Confirm Restriction</button>
        <a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $guestId ?>" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

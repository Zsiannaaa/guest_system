<?php
/**
 * admin/edit_office.php — Edit Office
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/admin/users_module.php';
require_once __DIR__ . '/../../modules/admin/offices_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Edit Office'; $db = getDB();
$officeId = (int)($_GET['id'] ?? 0);
if (!$officeId) redirect(APP_URL.'/public/admin/offices.php');
$oStmt = $db->prepare("SELECT * FROM offices WHERE office_id=:id"); $oStmt->execute([':id'=>$officeId]);
$office = $oStmt->fetch();
if (!$office) { setFlash('error','Office not found.'); redirect(APP_URL.'/public/admin/offices.php'); }
$errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/admin/edit_office.php?id=' . $officeId);
    $name = trim($_POST['office_name']??''); $code = strtoupper(trim($_POST['office_code']??''));
    $location = trim($_POST['office_location']??''); $needsConf = isset($_POST['requires_arrival_confirmation'])?1:0;
    $status = $_POST['status']??'active';
    if (empty($name)) $errors[]='Office name is required.'; if (empty($code)) $errors[]='Office code is required.';
    if (empty($errors)) { $chk=$db->prepare("SELECT COUNT(*) FROM offices WHERE office_code=:c AND office_id!=:id"); $chk->execute([':c'=>$code,':id'=>$officeId]); if($chk->fetchColumn()>0) $errors[]="Code '{$code}' is already used."; }
    if (empty($errors)) {
        $db->prepare("UPDATE offices SET office_name=:n,office_code=:c,office_location=:l,requires_arrival_confirmation=:conf,status=:s WHERE office_id=:id")
           ->execute([':n'=>$name,':c'=>$code,':l'=>$location?:null,':conf'=>$needsConf,':s'=>$status,':id'=>$officeId]);
        setFlash('success',"Office <strong>{$name}</strong> updated."); redirect(APP_URL.'/public/admin/offices.php');
    }
}
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Edit Office</div>
    <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li><li><a href="<?= APP_URL ?>/public/admin/offices.php">Offices</a></li><li>Edit</li></ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box"><div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Error</div>
<ul><?php foreach($errors as $er): ?><li><?= e($er) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="form-card">
<div class="card">
  <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Edit: <?= e($office['office_name']) ?></div>
  <div class="card-body">
    <form method="POST">
        <?= csrfField() ?>
      <div class="form-group"><label class="form-label">Office Name <span class="required-star">*</span></label>
        <input type="text" name="office_name" class="form-control" value="<?= e($_POST['office_name'] ?? $office['office_name']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Office Code <span class="required-star">*</span></label>
          <input type="text" name="office_code" class="form-control" value="<?= e($_POST['office_code'] ?? $office['office_code']) ?>" maxlength="30" style="text-transform:uppercase" required></div>
        <div class="form-group"><label class="form-label">Location</label>
          <input type="text" name="office_location" class="form-control" value="<?= e($_POST['office_location'] ?? $office['office_location'] ?? '') ?>"></div>
      </div>
      <div class="check-item" style="margin-bottom:16px;">
        <input type="checkbox" name="requires_arrival_confirmation" id="req_conf"
          <?= (isset($_POST['requires_arrival_confirmation']) || $office['requires_arrival_confirmation']) ? 'checked' : '' ?>>
        <label for="req_conf">Requires arrival confirmation</label>
      </div>
      <div class="form-group"><label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= ($_POST['status']??$office['status'])==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($_POST['status']??$office['status'])==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;"><button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Save</button><a href="<?= APP_URL ?>/public/admin/offices.php" class="btn btn-outline">Cancel</a></div>
    </form>
  </div>
</div>
</div>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

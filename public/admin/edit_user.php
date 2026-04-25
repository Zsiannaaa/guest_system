<?php
/**
 * admin/edit_user.php — Edit existing user
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/users_module.php';
require_once __DIR__ . '/../../modules/offices_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Edit User';
$db = getDB();
$userId = (int)($_GET['id'] ?? 0);
if (!$userId) redirect(APP_URL.'/public/admin/users.php');
$uStmt = $db->prepare("SELECT * FROM users WHERE user_id=:id"); $uStmt->execute([':id'=>$userId]);
$user = $uStmt->fetch();
if (!$user) { setFlash('error','User not found.'); redirect(APP_URL.'/public/admin/users.php'); }
$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();
$errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/admin/edit_user.php?id=' . $userId);
    $fullName = trim($_POST['full_name']??''); $email = trim($_POST['email']??'');
    $username = trim($_POST['username']??''); $role = $_POST['role']??'';
    $officeId = !empty($_POST['office_id'])?(int)$_POST['office_id']:null;
    $status = $_POST['status']??'active'; $newPwd = $_POST['new_password']??''; $confirm = $_POST['password_confirm']??'';

    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (empty($email)) $errors[] = 'Email is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (!in_array($role,['admin','guard','office_staff'])) $errors[] = 'Invalid role.';
    if ($role==='office_staff' && !$officeId) $errors[] = 'Office staff must have an assigned office.';
    if (!empty($newPwd)) { if (strlen($newPwd)<8) $errors[]='New password must be at least 8 characters.'; if ($newPwd!==$confirm) $errors[]='Passwords do not match.'; }
    if (empty($errors)) { $chk=$db->prepare("SELECT COUNT(*) FROM users WHERE (email=:e OR username=:u) AND user_id!=:id"); $chk->execute([':e'=>$email,':u'=>$username,':id'=>$userId]); if($chk->fetchColumn()>0) $errors[]='Email or username already used.'; }
    if (empty($errors)) {
        if (!empty($newPwd)) {
            $hash = password_hash($newPwd, PASSWORD_DEFAULT);
            $db->prepare("UPDATE users SET full_name=:n,email=:e,username=:u,role=:r,office_id=:o,status=:s,password_hash=:h WHERE user_id=:id")
               ->execute([':n'=>$fullName,':e'=>$email,':u'=>$username,':r'=>$role,':o'=>$officeId,':s'=>$status,':h'=>$hash,':id'=>$userId]);
        } else {
            $db->prepare("UPDATE users SET full_name=:n,email=:e,username=:u,role=:r,office_id=:o,status=:s WHERE user_id=:id")
               ->execute([':n'=>$fullName,':e'=>$email,':u'=>$username,':r'=>$role,':o'=>$officeId,':s'=>$status,':id'=>$userId]);
        }
        logActivity(null,'user_updated',currentUserId(),null,"User '{$username}' updated");
        setFlash('success',"User <strong>{$fullName}</strong> updated."); redirect(APP_URL.'/public/admin/users.php');
    }
    $user = array_merge($user, compact('fullName','email','username','role','officeId','status'));
}
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Edit User</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li>
      <li><a href="<?= APP_URL ?>/public/admin/users.php">Users</a></li>
      <li>Edit</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box"><div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix:</div>
<ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="form-card">
<div class="card">
  <div class="card-header"><i data-lucide="pencil" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Edit: <?= e($user['full_name']) ?></div>
  <div class="card-body">
    <form method="POST">
        <?= csrfField() ?>
      <div class="form-group"><label class="form-label">Full Name <span class="required-star">*</span></label>
        <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? $user['full_name']) ?>" required></div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Email <span class="required-star">*</span></label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? $user['email']) ?>" required></div>
        <div class="form-group"><label class="form-label">Username <span class="required-star">*</span></label><input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? $user['username']) ?>" required></div>
      </div>
      <div class="form-row">
        <div class="form-group"><label class="form-label">Role <span class="required-star">*</span></label>
          <select name="role" id="role" class="form-select" required onchange="handleRole(this.value)">
            <?php foreach(['admin'=>'Administrator','guard'=>'Guard / Reception','office_staff'=>'Office Staff'] as $v=>$l): ?>
            <option value="<?= $v ?>" <?= ($_POST['role']??$user['role'])===$v?'selected':'' ?>><?= $l ?></option>
            <?php endforeach; ?>
          </select></div>
        <div class="form-group"><label class="form-label">Assigned Office</label>
          <select name="office_id" id="office_id" class="form-select">
            <option value="">— None —</option>
            <?php foreach($offices as $o): ?>
            <option value="<?= $o['office_id'] ?>" <?= ($_POST['office_id']??$user['office_id'])==$o['office_id']?'selected':'' ?>><?= e($o['office_name']) ?></option>
            <?php endforeach; ?>
          </select></div>
      </div>
      <div class="form-group"><label class="form-label">Status</label>
        <select name="status" class="form-select" <?= $userId===currentUserId()?'disabled':'' ?>>
          <option value="active" <?= ($_POST['status']??$user['status'])==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($_POST['status']??$user['status'])==='inactive'?'selected':'' ?>>Inactive</option>
          <option value="suspended" <?= ($_POST['status']??$user['status'])==='suspended'?'selected':'' ?>>Suspended</option>
        </select>
        <?php if ($userId===currentUserId()): ?><input type="hidden" name="status" value="<?= e($user['status']) ?>"><div class="form-hint" style="color:var(--warning);">You cannot change your own status.</div><?php endif; ?>
      </div>

      <div style="background:var(--bg);border-radius:var(--radius-s);padding:14px 16px;margin-bottom:16px;">
        <div style="font-size:.82rem;font-weight:600;color:var(--text-s);margin-bottom:8px;display:flex;align-items:center;gap:6px;">
          <i data-lucide="lock" style="width:14px;height:14px;"></i> Change Password <span style="font-weight:400;color:var(--text-m);">(leave blank to keep current)</span>
        </div>
        <div class="form-row">
          <div class="form-group" style="margin-bottom:0;"><input type="password" name="new_password" class="form-control" placeholder="New password (min. 8 chars)"></div>
          <div class="form-group" style="margin-bottom:0;"><input type="password" name="password_confirm" class="form-control" placeholder="Confirm new password"></div>
        </div>
      </div>
      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Save Changes</button>
        <a href="<?= APP_URL ?>/public/admin/users.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>

<script>
function handleRole(r){document.getElementById('office_id').required=r==='office_staff';}
handleRole(document.getElementById('role').value);
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

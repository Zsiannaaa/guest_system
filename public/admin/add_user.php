<?php
// ============================================================
// public/admin/add_user.php — Add New User (Controller + View)
//
// MVC Role:
//   Model      → modules/users_module.php
//   Controller → POST handling at the top
//   View       → HTML below
//
// Access: Admin only
// ============================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/users_module.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Add New User';
$db = getDB();
$offices = getActiveOfficesForDropdown($db);
$errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/admin/add_user.php');
    $fullName = inputStr('full_name'); $email = inputStr('email');
    $username = inputStr('username'); $password = $_POST['password'] ?? '';
    $confirm = $_POST['password_confirm'] ?? ''; $role = inputStr('role');
    $officeId = !empty($_POST['office_id']) ? inputInt('office_id', 'POST') : null;
    $status = inputStr('status') ?: 'active';

    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    if (empty($errors)) {
        $err = createUser($db, $fullName, $email, $username, $password, $role, $officeId, $status);
        if ($err) { $errors[] = $err; }
        else {
            logActivity(null, 'user_created', currentUserId(), null, "New user '{$username}' role '{$role}'");
            setFlash('success', "User <strong>{$fullName}</strong> created.");
            redirect(APP_URL . '/public/admin/users.php');
        }
    }
}
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Add New User</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li>
      <li><a href="<?= APP_URL ?>/public/admin/users.php">Users</a></li>
      <li>Add User</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix the following:</div>
  <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-card">
<div class="card">
  <div class="card-header"><i data-lucide="user-plus" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>User Information</div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>
      <div class="form-group">
        <label class="form-label">Full Name <span class="required-star">*</span></label>
        <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required autofocus>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Email <span class="required-star">*</span></label>
          <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" required>
        </div>
        <div class="form-group">
          <label class="form-label">Username <span class="required-star">*</span></label>
          <input type="text" name="username" class="form-control" value="<?= e($_POST['username'] ?? '') ?>" pattern="[a-zA-Z0-9_]+" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Password <span class="required-star">*</span></label>
          <input type="password" name="password" class="form-control" placeholder="Min. 8 characters" required>
        </div>
        <div class="form-group">
          <label class="form-label">Confirm Password <span class="required-star">*</span></label>
          <input type="password" name="password_confirm" class="form-control" placeholder="Repeat password" required>
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Role <span class="required-star">*</span></label>
          <select name="role" id="role" class="form-select" required onchange="handleRole(this.value)">
            <option value="">— Select Role —</option>
            <option value="admin" <?= ($_POST['role']??'')==='admin'?'selected':'' ?>>Administrator</option>
            <option value="guard" <?= ($_POST['role']??'')==='guard'?'selected':'' ?>>Guard / Reception</option>
            <option value="office_staff" <?= ($_POST['role']??'')==='office_staff'?'selected':'' ?>>Office Staff</option>
          </select>
        </div>
        <div class="form-group" id="officeField">
          <label class="form-label">Assigned Office <span class="required-star" id="officeReq" style="display:none">*</span></label>
          <select name="office_id" id="office_id" class="form-select">
            <option value="">— None —</option>
            <?php foreach ($offices as $o): ?>
            <option value="<?= $o['office_id'] ?>" <?= ($_POST['office_id']??'')==$o['office_id']?'selected':'' ?>><?= e($o['office_name']) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Required for Office Staff role</div>
        </div>
      </div>
      <div class="form-group">
        <label class="form-label">Status</label>
        <select name="status" class="form-select">
          <option value="active" <?= ($_POST['status']??'active')==='active'?'selected':'' ?>>Active</option>
          <option value="inactive" <?= ($_POST['status']??'')==='inactive'?'selected':'' ?>>Inactive</option>
        </select>
      </div>
      <div style="display:flex;gap:10px;margin-top:8px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Create User</button>
        <a href="<?= APP_URL ?>/public/admin/users.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>

<script>
function handleRole(r) {
  document.getElementById('officeReq').style.display = r==='office_staff' ? 'inline' : 'none';
  document.getElementById('office_id').required = r==='office_staff';
}
handleRole(document.getElementById('role').value);
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

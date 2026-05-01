<?php
/**
 * guests/edit.php - Edit guest personal information
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests_module.php';

requireRole([ROLE_ADMIN, ROLE_GUARD]);

$pageTitle = 'Edit Guest';
$db = getDB();
$guestId = requireValidId($_GET['id'] ?? 0, APP_URL . '/public/guests/list.php');
$guest = getGuestById($db, $guestId);

if (!$guest) {
    setFlash('error', 'Guest not found.');
    redirect(APP_URL . '/public/guests/list.php');
}

$errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guests/edit.php?id=' . $guestId);

    $err = updateGuest($db, $guestId, $_POST);
    if ($err) {
        $errors[] = $err;
        $guest = array_merge($guest, [
            'full_name'      => trim($_POST['full_name'] ?? ''),
            'contact_number' => trim($_POST['contact_number'] ?? ''),
            'email'          => trim($_POST['email'] ?? ''),
            'organization'   => trim($_POST['organization'] ?? ''),
            'address'        => trim($_POST['address'] ?? ''),
            'id_type'        => trim($_POST['id_type'] ?? ''),
        ]);
    } else {
        logActivity(null, 'other', currentUserId(), null, "Guest #{$guestId} personal information updated");
        setFlash('success', 'Guest information updated.');
        redirect(APP_URL . '/public/guests/view.php?id=' . $guestId);
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Edit Guest</div>
    <ul class="breadcrumb">
      <li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li>
      <li><a href="<?= APP_URL ?>/public/guests/list.php">Guests</a></li>
      <li><a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $guestId ?>"><?= e($guest['full_name']) ?></a></li>
      <li>Edit</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix:</div>
  <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-card">
  <div class="card">
    <div class="card-header">
      <i data-lucide="pencil" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>
      Personal Information
    </div>
    <div class="card-body">
      <form method="POST">
        <?= csrfField() ?>

        <div class="form-group">
          <label class="form-label">Full Name <span class="required-star">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($guest['full_name'] ?? '') ?>" required>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" value="<?= e($guest['contact_number'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Email</label>
            <input type="email" name="email" class="form-control" value="<?= e($guest['email'] ?? '') ?>">
          </div>
        </div>

        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Organization / Institution</label>
            <input type="text" name="organization" class="form-control" value="<?= e($guest['organization'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">ID Type</label>
            <input type="text" name="id_type" class="form-control" value="<?= e($guest['id_type'] ?? '') ?>" placeholder="School ID, Passport, Driver's License">
          </div>
        </div>

        <div class="form-group">
          <label class="form-label">Address</label>
          <textarea name="address" class="form-control" rows="3"><?= e($guest['address'] ?? '') ?></textarea>
        </div>

        <div style="display:flex;gap:10px;flex-wrap:wrap;">
          <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Save Changes</button>
          <a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $guestId ?>" class="btn btn-outline">Cancel</a>
        </div>
      </form>
    </div>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

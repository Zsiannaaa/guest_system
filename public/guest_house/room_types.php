<?php
/**
 * public/guest_house/room_types.php — Manage room types (admin-only for edits)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_rooms_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Room Types';
$db = getDB();

if (isPost()) {
    // Only admins can mutate types
    if (!isAdmin()) {
        setFlash('error', 'Only admins can edit room types.');
        redirect(APP_URL . '/public/guest_house/room_types.php');
    }
    verifyCsrf(APP_URL . '/public/guest_house/room_types.php');

    if (isset($_POST['add_type'])) {
        $err = ghCreateRoomType($db, inputStr('type_name'), inputInt('default_capacity','POST'), inputStr('description'));
        if ($err) setFlash('error', $err); else setFlash('success', 'Room type added.');
        redirect(APP_URL . '/public/guest_house/room_types.php');
    }
    if (isset($_POST['update_type'])) {
        $tid = inputInt('type_id','POST');
        $err = ghUpdateRoomType($db, $tid, inputStr('type_name'), inputInt('default_capacity','POST'),
                                 inputStr('description'), inputStr('status'));
        if ($err) setFlash('error', $err); else setFlash('success', 'Room type updated.');
        redirect(APP_URL . '/public/guest_house/room_types.php');
    }
}

$types = ghListRoomTypes($db);
$editType = null;
if (($eid = inputInt('edit','GET')) && isAdmin()) {
    $editType = ghGetRoomType($db, $eid);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Room Types</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Room Types</li>
    </ul>
  </div>
  <?php if (isAdmin()): ?>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addPanel').style.display=document.getElementById('addPanel').style.display==='none'?'block':'none'">
      <i data-lucide="plus"></i> Add Type
    </button>
  </div>
  <?php endif; ?>
</div>

<?php if (isAdmin()): ?>
<div class="card" id="addPanel" style="<?= $editType ? '' : 'display:none;' ?>margin-bottom:20px;">
  <div class="card-header"><?= $editType ? 'Edit: ' . e($editType['type_name']) : 'Add Room Type' ?></div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>
      <?php if ($editType): ?><input type="hidden" name="type_id" value="<?= (int)$editType['type_id'] ?>"><?php endif; ?>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Type Name <span class="required-star">*</span></label>
          <input type="text" name="type_name" class="form-control" required
                 value="<?= e($editType['type_name'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Default Capacity <span class="required-star">*</span></label>
          <input type="number" name="default_capacity" class="form-control" min="1" required
                 value="<?= (int)($editType['default_capacity'] ?? 1) ?>">
        </div>
        <?php if ($editType): ?>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <option value="active"   <?= ($editType['status']==='active')  ?'selected':'' ?>>Active</option>
            <option value="inactive" <?= ($editType['status']==='inactive')?'selected':'' ?>>Inactive</option>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label">Description</label>
        <textarea name="description" rows="2" class="form-control"><?= e($editType['description'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <?php if ($editType): ?>
        <button type="submit" name="update_type" class="btn btn-primary"><i data-lucide="check"></i> Save</button>
        <a href="<?= APP_URL ?>/public/guest_house/room_types.php" class="btn btn-outline">Cancel</a>
        <?php else: ?>
        <button type="submit" name="add_type" class="btn btn-primary"><i data-lucide="plus"></i> Add Type</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>
<?php endif; ?>

<div class="card">
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Type</th><th>Default Capacity</th><th>Description</th><th>Status</th><?php if(isAdmin()): ?><th>Actions</th><?php endif; ?></tr></thead>
      <tbody>
      <?php foreach ($types as $t): ?>
      <tr>
        <td style="font-weight:600;"><?= e($t['type_name']) ?></td>
        <td><?= (int)$t['default_capacity'] ?></td>
        <td style="color:var(--text-s);font-size:.83rem;"><?= e($t['description'] ?? '—') ?></td>
        <td><span class="badge <?= $t['status']==='active'?'badge-success':'badge-danger' ?>"><?= statusLabel($t['status']) ?></span></td>
        <?php if (isAdmin()): ?>
        <td><a href="?edit=<?= (int)$t['type_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="pencil"></i> Edit</a></td>
        <?php endif; ?>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

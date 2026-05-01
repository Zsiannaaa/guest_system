<?php
/**
 * public/guest_house/rooms.php — Room management
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_rooms_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Rooms';
$db = getDB();

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guest_house/rooms.php');

    if (isset($_POST['add_room'])) {
        $err = ghCreateRoom($db, [
            'room_number'   => inputStr('room_number'),
            'type_id'       => inputInt('type_id','POST'),
            'capacity'      => inputInt('capacity','POST'),
            'floor'         => inputStr('floor'),
            'location_note' => inputStr('location_note'),
            'notes'         => inputStr('notes'),
        ]);
        if ($err) setFlash('error', $err);
        else {
            logActivity(null, 'gh_room_created', currentUserId(), null, 'Created GH room ' . inputStr('room_number'));
            setFlash('success', 'Room added.');
        }
        redirect(APP_URL . '/public/guest_house/rooms.php');
    }

    if (isset($_POST['update_room'])) {
        $rid = inputInt('room_id','POST');
        $err = ghUpdateRoom($db, $rid, [
            'room_number'   => inputStr('room_number'),
            'type_id'       => inputInt('type_id','POST'),
            'capacity'      => inputInt('capacity','POST'),
            'status'        => inputStr('status'),
            'floor'         => inputStr('floor'),
            'location_note' => inputStr('location_note'),
            'notes'         => inputStr('notes'),
        ]);
        if ($err) setFlash('error', $err);
        else {
            logActivity(null, 'gh_room_updated', currentUserId(), null, "Updated GH room #{$rid}");
            setFlash('success', 'Room updated.');
        }
        redirect(APP_URL . '/public/guest_house/rooms.php');
    }

    if (isset($_POST['set_status'])) {
        $rid = inputInt('room_id','POST');
        ghSetRoomStatus($db, $rid, inputStr('status'));
        logActivity(null, 'gh_room_updated', currentUserId(), null, "GH room #{$rid} status -> " . inputStr('status'));
        setFlash('success', 'Room status updated.');
        redirect(APP_URL . '/public/guest_house/rooms.php');
    }
}

$filters = [
    'status'  => inputStr('status','GET'),
    'type_id' => inputInt('type_id','GET'),
    'q'       => inputStr('q','GET'),
];
$rooms = ghListRooms($db, $filters);
$types = ghListRoomTypes($db, true);
$editRoom = null;
if ($eid = inputInt('edit','GET')) {
    $editRoom = ghGetRoom($db, $eid);
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Guest House Rooms</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Rooms</li>
    </ul>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addPanel').style.display=document.getElementById('addPanel').style.display==='none'?'block':'none'">
      <i data-lucide="plus"></i> Add Room
    </button>
  </div>
</div>

<!-- Add/Edit panel -->
<div class="card" id="addPanel" style="<?= $editRoom ? '' : 'display:none;' ?>margin-bottom:20px;">
  <div class="card-header"><?= $editRoom ? 'Edit Room ' . e($editRoom['room_number']) : 'Add New Room' ?></div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>
      <?php if ($editRoom): ?>
      <input type="hidden" name="room_id" value="<?= (int)$editRoom['room_id'] ?>">
      <?php endif; ?>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Room Number <span class="required-star">*</span></label>
          <input type="text" name="room_number" class="form-control" required
                 value="<?= e($editRoom['room_number'] ?? '') ?>" placeholder="e.g. GH-103">
        </div>
        <div class="form-group">
          <label class="form-label">Room Type <span class="required-star">*</span></label>
          <select name="type_id" class="form-select" required>
            <option value="">— Select —</option>
            <?php foreach ($types as $t): ?>
            <option value="<?= (int)$t['type_id'] ?>" data-cap="<?= (int)$t['default_capacity'] ?>"
              <?= ($editRoom && (int)$editRoom['type_id']===(int)$t['type_id']) ? 'selected' : '' ?>>
              <?= e($t['type_name']) ?> (cap <?= (int)$t['default_capacity'] ?>)
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">Capacity <span class="required-star">*</span></label>
          <input type="number" name="capacity" class="form-control" min="1" required
                 value="<?= (int)($editRoom['capacity'] ?? 1) ?>">
        </div>
      </div>
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Floor</label>
          <input type="text" name="floor" class="form-control"
                 value="<?= e($editRoom['floor'] ?? '') ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Location note</label>
          <input type="text" name="location_note" class="form-control"
                 value="<?= e($editRoom['location_note'] ?? '') ?>" placeholder="e.g. East Wing">
        </div>
        <?php if ($editRoom): ?>
        <div class="form-group">
          <label class="form-label">Status</label>
          <select name="status" class="form-select">
            <?php foreach (['available','occupied','maintenance','inactive'] as $s): ?>
            <option value="<?= $s ?>" <?= ($editRoom['status']===$s)?'selected':'' ?>><?= statusLabel($s) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <?php endif; ?>
      </div>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="2" class="form-control"><?= e($editRoom['notes'] ?? '') ?></textarea>
      </div>
      <div style="display:flex;gap:10px;">
        <?php if ($editRoom): ?>
        <button type="submit" name="update_room" class="btn btn-primary"><i data-lucide="check"></i> Save Changes</button>
        <a href="<?= APP_URL ?>/public/guest_house/rooms.php" class="btn btn-outline">Cancel</a>
        <?php else: ?>
        <button type="submit" name="add_room" class="btn btn-primary"><i data-lucide="plus"></i> Add Room</button>
        <?php endif; ?>
      </div>
    </form>
  </div>
</div>

<div class="card">
  <form method="GET" class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search room number…"></div>
    <select name="type_id" class="table-filter">
      <option value="">All Types</option>
      <?php foreach ($types as $t): ?>
      <option value="<?= (int)$t['type_id'] ?>" <?= ((int)$filters['type_id']===(int)$t['type_id'])?'selected':'' ?>><?= e($t['type_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="status" class="table-filter">
      <option value="">All Status</option>
      <?php foreach (['available','occupied','maintenance','inactive'] as $s): ?>
      <option value="<?= $s ?>" <?= ($filters['status']===$s)?'selected':'' ?>><?= statusLabel($s) ?></option>
      <?php endforeach; ?>
    </select>
    <button class="btn btn-outline"><i data-lucide="filter"></i> Filter</button>
    <div class="table-count"><?= count($rooms) ?> rooms</div>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>Room</th><th>Type</th><th>Capacity</th><th>Floor</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($rooms)): ?>
      <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-m);">No rooms found.</td></tr>
      <?php else: foreach ($rooms as $r): ?>
      <tr>
        <td style="font-weight:600;"><?= e($r['room_number']) ?></td>
        <td><span class="badge badge-blue"><?= e($r['type_name']) ?></span></td>
        <td><?= (int)$r['capacity'] ?></td>
        <td style="color:var(--text-s);font-size:.83rem;"><?= e(trim(($r['floor']??'') . ' ' . ($r['location_note']??'')) ?: '—') ?></td>
        <td>
          <span class="badge <?= match($r['status']) {
              'available'   => 'badge-success',
              'occupied'    => 'badge-warning',
              'maintenance' => 'badge-secondary',
              default       => 'badge-danger',
            } ?>"><?= statusLabel($r['status']) ?></span>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="?edit=<?= (int)$r['room_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="pencil"></i> Edit</a>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
// auto-fill capacity when type changes on Add form
document.querySelectorAll('select[name="type_id"]').forEach(sel => {
  sel.addEventListener('change', function() {
    const opt = this.options[this.selectedIndex];
    const capIn = this.form.querySelector('input[name="capacity"]');
    if (capIn && opt.dataset.cap) capIn.value = opt.dataset.cap;
  });
});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

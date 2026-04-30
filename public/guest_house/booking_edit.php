<?php
/**
 * public/guest_house/booking_edit.php — Edit an existing booking
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';
require_once __DIR__ . '/../../modules/gh_rooms_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Edit Booking';
$db = getDB();
$bid = requireValidId($_GET['id'] ?? 0, APP_URL . '/public/guest_house/bookings.php');

$b = ghGetBooking($db, $bid);
if (!$b) { setFlash('error', 'Booking not found.'); redirect(APP_URL . '/public/guest_house/bookings.php'); }
if (in_array($b['status'], ['checked_out','cancelled'], true)) {
    setFlash('error', 'This booking is closed and cannot be edited.');
    redirect(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid);
}

$errors = [];
if (isPost()) {
    verifyCsrf(APP_URL . '/public/guest_house/booking_edit.php?id=' . $bid);
    $err = ghUpdateBooking($db, $bid, [
        'room_id'              => inputInt('room_id','POST'),
        'check_in_date'        => inputStr('check_in_date'),
        'check_out_date'       => inputStr('check_out_date'),
        'purpose_of_stay'      => inputStr('purpose_of_stay'),
        'sponsoring_office_id' => inputInt('sponsoring_office_id','POST'),
        'external_sponsor'     => inputStr('external_sponsor'),
        'number_of_guests'     => inputInt('number_of_guests','POST'),
        'notes'                => inputStr('notes'),
    ], currentUserId());
    if ($err) $errors[] = $err;
    else { setFlash('success','Booking updated.'); redirect(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid); }
}

$from = $_POST['check_in_date']  ?? $b['check_in_date'];
$to   = $_POST['check_out_date'] ?? $b['check_out_date'];
$rooms   = ghAvailableRoomsForRange($db, $from, $to);
$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Edit Booking <?= e($b['booking_reference']) ?></div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/guest_house/bookings.php">Bookings</a></li>
      <li><a href="booking_view.php?id=<?= $bid ?>"><?= e($b['booking_reference']) ?></a></li>
      <li>Edit</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box"><div class="error-title"><i data-lucide="alert-circle"></i> Please fix:</div>
<ul><?php foreach($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul></div>
<?php endif; ?>

<div class="form-card"><div class="card"><div class="card-body">
<form method="POST">
  <?= csrfField() ?>

  <div class="form-group">
    <label class="form-label">Guest</label>
    <div><strong><?= e($b['guest_name']) ?></strong> (guest cannot be changed after creation)</div>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label">Check-in Date <span class="required-star">*</span></label>
      <input type="date" name="check_in_date" class="form-control" required value="<?= e($from) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Check-out Date <span class="required-star">*</span></label>
      <input type="date" name="check_out_date" class="form-control" required value="<?= e($to) ?>">
    </div>
    <div class="form-group">
      <label class="form-label">Guests</label>
      <input type="number" name="number_of_guests" class="form-control" min="1"
             value="<?= (int)($_POST['number_of_guests'] ?? $b['number_of_guests']) ?>">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Room</label>
    <select name="room_id" class="form-select">
      <option value="">— Unassigned —</option>
      <?php foreach ($rooms as $r):
          $selected = ((int)($_POST['room_id'] ?? $b['room_id']) === (int)$r['room_id']);
          $free = (int)$r['is_free_for_range'] === 1;
      ?>
      <option value="<?= (int)$r['room_id'] ?>" <?= $selected?'selected':'' ?> <?= ($free || $selected)?'':'disabled' ?>>
        <?= e($r['room_number']) ?> — <?= e($r['type_name']) ?> (cap <?= (int)$r['capacity'] ?>)<?= ($free || $selected)?'':' — booked' ?>
      </option>
      <?php endforeach; ?>
    </select>
  </div>

  <div class="form-group">
    <label class="form-label">Purpose of Stay <span class="required-star">*</span></label>
    <textarea name="purpose_of_stay" rows="3" class="form-control" required><?= e($_POST['purpose_of_stay'] ?? $b['purpose_of_stay']) ?></textarea>
  </div>

  <div class="form-row">
    <div class="form-group">
      <label class="form-label">Sponsoring Office</label>
      <select name="sponsoring_office_id" class="form-select">
        <option value="">— None —</option>
        <?php foreach ($offices as $o): ?>
        <option value="<?= (int)$o['office_id'] ?>" <?= ((int)($_POST['sponsoring_office_id']??$b['sponsoring_office_id'])===(int)$o['office_id'])?'selected':'' ?>>
          <?= e($o['office_name']) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>
    <div class="form-group">
      <label class="form-label">External Sponsor</label>
      <input type="text" name="external_sponsor" class="form-control"
             value="<?= e($_POST['external_sponsor'] ?? $b['external_sponsor'] ?? '') ?>">
    </div>
  </div>

  <div class="form-group">
    <label class="form-label">Notes</label>
    <textarea name="notes" rows="2" class="form-control"><?= e($_POST['notes'] ?? $b['notes'] ?? '') ?></textarea>
  </div>

  <div style="display:flex;gap:10px;">
    <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Save Changes</button>
    <a href="booking_view.php?id=<?= $bid ?>" class="btn btn-outline">Cancel</a>
  </div>
</form>
</div></div></div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

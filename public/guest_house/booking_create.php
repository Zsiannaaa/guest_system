<?php
/**
 * public/guest_house/booking_create.php - Add an expected Guest House guest
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_bookings_module.php';
require_once __DIR__ . '/../../modules/guest_house/gh_rooms_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'New Expected Guest';
$db = getDB();
$errors = [];

$from = $_POST['check_in_date'] ?? date('Y-m-d');
$to = $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day'));

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guest_house/booking_create.php');

    [$err, $bid, $ref] = ghCreateBooking($db, [
        'guest_name'       => inputStr('guest_name'),
        'organization'     => inputStr('organization'),
        'contact_number'   => inputStr('contact_number'),
        'room_id'          => inputInt('room_id', 'POST'),
        'check_in_date'    => inputStr('check_in_date'),
        'check_out_date'   => inputStr('check_out_date'),
        'purpose_of_stay'  => inputStr('purpose_of_stay'),
        'number_of_guests' => inputInt('number_of_guests', 'POST'),
        'notes'            => inputStr('notes'),
    ], currentUserId());

    if ($err) {
        $errors[] = $err;
    } else {
        setFlash('success', "Expected guest <strong>{$ref}</strong> added.");
        redirect(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid);
    }
}

$rooms = ghAvailableRoomsForRange($db, $from, $to);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">New Expected Guest</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/guest_house/bookings.php">Expected Guests</a></li>
      <li>New</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix:</div>
  <ul><?php foreach ($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-card">
<div class="card">
  <div class="card-header"><i data-lucide="calendar-plus" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest House Stay</div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Guest Name <span class="required-star">*</span></label>
        <input type="text" name="guest_name" class="form-control" required
               value="<?= e($_POST['guest_name'] ?? '') ?>" placeholder="Full name of expected guest">
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Organization / Institution</label>
          <input type="text" name="organization" class="form-control"
                 value="<?= e($_POST['organization'] ?? '') ?>" placeholder="Optional">
        </div>
        <div class="form-group">
          <label class="form-label">Contact Number</label>
          <input type="text" name="contact_number" class="form-control"
                 value="<?= e($_POST['contact_number'] ?? '') ?>" placeholder="Optional">
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Expected Arrival <span class="required-star">*</span></label>
          <input type="date" name="check_in_date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= e($from) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Expected Departure <span class="required-star">*</span></label>
          <input type="date" name="check_out_date" class="form-control" required min="<?= date('Y-m-d') ?>" value="<?= e($to) ?>">
        </div>
        <div class="form-group">
          <label class="form-label">Guests</label>
          <input type="number" name="number_of_guests" class="form-control" min="1" value="<?= (int)($_POST['number_of_guests'] ?? 1) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Room</label>
        <select name="room_id" class="form-select">
          <option value="">Unassigned (assign later)</option>
          <?php foreach ($rooms as $r):
              $free = (int)$r['is_free_for_range'] === 1;
          ?>
          <option value="<?= (int)$r['room_id'] ?>"
                  <?= ((int)($_POST['room_id'] ?? 0) === (int)$r['room_id']) ? 'selected' : '' ?>
                  <?= $free ? '' : 'disabled' ?>>
            <?= e($r['room_number']) ?> - <?= e($r['type_name']) ?> (cap <?= (int)$r['capacity'] ?>)<?= $free ? '' : ' - booked' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Rooms that are already booked for the selected dates are disabled.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Purpose / Remarks</label>
        <textarea name="purpose_of_stay" rows="3" class="form-control" placeholder="Optional"><?= e($_POST['purpose_of_stay'] ?? '') ?></textarea>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="2" class="form-control"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Add Expected Guest</button>
        <a href="<?= APP_URL ?>/public/guest_house/bookings.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

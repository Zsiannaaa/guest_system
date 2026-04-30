<?php
/**
 * public/guest_house/booking_create.php — Create a new booking
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';
require_once __DIR__ . '/../../modules/gh_rooms_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'New Guest House Booking';
$db = getDB();
$errors = [];

$from = $_POST['check_in_date']  ?? date('Y-m-d');
$to   = $_POST['check_out_date'] ?? date('Y-m-d', strtotime('+1 day'));

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guest_house/booking_create.php');

    [$err, $bid, $ref] = ghCreateBooking($db, [
        'guest_id'             => inputInt('guest_id','POST'),
        'room_id'              => inputInt('room_id','POST'),
        'check_in_date'        => inputStr('check_in_date'),
        'check_out_date'       => inputStr('check_out_date'),
        'purpose_of_stay'      => inputStr('purpose_of_stay'),
        'sponsoring_office_id' => inputInt('sponsoring_office_id','POST'),
        'external_sponsor'     => inputStr('external_sponsor'),
        'number_of_guests'     => inputInt('number_of_guests','POST'),
        'notes'                => inputStr('notes'),
    ], currentUserId());

    if ($err) {
        $errors[] = $err;
    } else {
        setFlash('success', "Booking <strong>{$ref}</strong> created.");
        redirect(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid);
    }
}

$guests  = $db->query("SELECT guest_id, full_name, organization FROM guests WHERE is_restricted=0 ORDER BY full_name")->fetchAll();
$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();
$rooms   = ghAvailableRoomsForRange($db, $from, $to);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">New Booking</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/guest_house/bookings.php">Bookings</a></li>
      <li>New</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix:</div>
  <ul><?php foreach($errors as $e): ?><li><?= e($e) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<div class="form-card">
<div class="card">
  <div class="card-header"><i data-lucide="calendar-plus" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Booking Details</div>
  <div class="card-body">
    <form method="POST">
      <?= csrfField() ?>

      <div class="form-group">
        <label class="form-label">Guest <span class="required-star">*</span></label>
        <select name="guest_id" class="form-select" required>
          <option value="">— Select a guest —</option>
          <?php foreach ($guests as $g): ?>
          <option value="<?= (int)$g['guest_id'] ?>" <?= ((int)($_POST['guest_id']??0)===(int)$g['guest_id']) ? 'selected' : '' ?>>
            <?= e($g['full_name']) ?><?= $g['organization'] ? ' — ' . e($g['organization']) : '' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">
          Guest not in the list? <a href="<?= APP_URL ?>/public/guests/list.php">Add guest</a> first, then return here.
        </div>
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
          <input type="number" name="number_of_guests" class="form-control" min="1" value="<?= (int)($_POST['number_of_guests'] ?? 1) ?>">
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Room</label>
        <select name="room_id" class="form-select">
          <option value="">— Unassigned (assign later) —</option>
          <?php foreach ($rooms as $r):
              $free = (int)$r['is_free_for_range'] === 1;
          ?>
          <option value="<?= (int)$r['room_id'] ?>"
                  <?= ((int)($_POST['room_id']??0)===(int)$r['room_id'])?'selected':'' ?>
                  <?= $free ? '' : 'disabled' ?>>
            <?= e($r['room_number']) ?> — <?= e($r['type_name']) ?> (cap <?= (int)$r['capacity'] ?>)
            <?= $free ? '' : ' — booked' ?>
          </option>
          <?php endforeach; ?>
        </select>
        <div class="form-hint">Only rooms free for the selected dates are selectable. Change dates then reload to refresh.</div>
      </div>

      <div class="form-group">
        <label class="form-label">Purpose of Stay <span class="required-star">*</span></label>
        <textarea name="purpose_of_stay" rows="3" class="form-control" required><?= e($_POST['purpose_of_stay'] ?? '') ?></textarea>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Sponsoring Office</label>
          <select name="sponsoring_office_id" class="form-select">
            <option value="">— None / External —</option>
            <?php foreach ($offices as $o): ?>
            <option value="<?= (int)$o['office_id'] ?>" <?= ((int)($_POST['sponsoring_office_id']??0)===(int)$o['office_id'])?'selected':'' ?>>
              <?= e($o['office_name']) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="form-group">
          <label class="form-label">External Sponsor</label>
          <input type="text" name="external_sponsor" class="form-control"
                 value="<?= e($_POST['external_sponsor'] ?? '') ?>" placeholder="e.g. University of the Philippines">
          <div class="form-hint">Use if sponsor is not a campus office.</div>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Notes</label>
        <textarea name="notes" rows="2" class="form-control"><?= e($_POST['notes'] ?? '') ?></textarea>
      </div>

      <div style="display:flex;gap:10px;">
        <button type="submit" class="btn btn-primary"><i data-lucide="check"></i> Create Booking</button>
        <a href="<?= APP_URL ?>/public/guest_house/bookings.php" class="btn btn-outline">Cancel</a>
      </div>
    </form>
  </div>
</div>
</div>

<script>
// Reload room list when dates change (simple approach: submit the page as GET to refresh)
document.querySelectorAll('input[name="check_in_date"], input[name="check_out_date"]').forEach(el => {
  el.addEventListener('change', () => {
    const f = document.querySelector('input[name="check_in_date"]').value;
    const t = document.querySelector('input[name="check_out_date"]').value;
    if (f && t) {
      const url = new URL(window.location.href);
      url.searchParams.set('_from', f);
      url.searchParams.set('_to', t);
      // just show a hint; full refresh is optional
      console.info('Date range updated. Save/reload to refresh room availability.');
    }
  });
});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

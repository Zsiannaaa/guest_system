<?php
/**
 * public/guest_house/booking_view.php - Expected Guest House guest details
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_bookings_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Expected Guest Details';
$db = getDB();

$bid = requireValidId($_GET['id'] ?? 0, APP_URL . '/public/guest_house/bookings.php');

if (isPost()) {
    verifyCsrf(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid);

    if (isset($_POST['do_arrived'])) {
        $err = ghCheckIn($db, $bid, currentUserId());
        $err ? setFlash('error', $err) : setFlash('success', 'Guest marked as arrived.');
    } elseif (isset($_POST['do_left'])) {
        $err = ghCheckOut($db, $bid, currentUserId(), inputStr('checkout_notes') ?: null);
        $err ? setFlash('error', $err) : setFlash('success', 'Guest marked as left.');
    } elseif (isset($_POST['do_cancel'])) {
        $err = ghCancelBooking($db, $bid, inputStr('cancel_reason'), currentUserId());
        $err ? setFlash('error', $err) : setFlash('success', 'Expected guest cancelled.');
    }
    redirect(APP_URL . '/public/guest_house/booking_view.php?id=' . $bid);
}

$b = ghGetBooking($db, $bid);
if (!$b) {
    setFlash('error', 'Expected guest record not found.');
    redirect(APP_URL . '/public/guest_house/bookings.php');
}

include __DIR__ . '/../../includes/header.php';

$statusBadge = match ($b['status']) {
    'reserved' => 'badge-warning',
    'checked_in', 'occupied' => 'badge-success',
    'checked_out' => 'badge-secondary',
    'cancelled' => 'badge-danger',
    default => 'badge-secondary',
};
?>

<div class="page-top">
  <div>
    <div class="page-title"><?= e($b['guest_name']) ?></div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/guest_house/bookings.php">Expected Guests</a></li>
      <li><?= e($b['booking_reference']) ?></li>
    </ul>
  </div>
  <div class="page-actions">
    <span class="badge <?= $statusBadge ?>" style="font-size:.9rem;padding:6px 12px;"><?= statusLabel($b['status']) ?></span>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 320px;gap:20px;">

  <div class="card">
    <div class="card-header">Stay Information</div>
    <div class="card-body">
      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Guest</label>
          <div><strong><?= e($b['guest_name']) ?></strong></div>
          <?php if (!empty($b['organization'])): ?>
          <div style="color:var(--text-s);font-size:.85rem;"><?= e($b['organization']) ?></div>
          <?php endif; ?>
          <?php if (!empty($b['contact_number'])): ?>
          <div style="color:var(--text-m);font-size:.83rem;"><i data-lucide="phone" style="width:12px;height:12px;"></i> <?= e($b['contact_number']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Reference</label>
          <div><code style="font-size:.85rem;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($b['booking_reference']) ?></code></div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Room</label>
          <div><?= e($b['room_number'] ?? 'Unassigned') ?></div>
          <?php if (!empty($b['type_name'])): ?>
          <div style="color:var(--text-s);font-size:.85rem;"><?= e($b['type_name']) ?> - capacity <?= (int)$b['room_capacity'] ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Guests</label>
          <div><?= (int)$b['number_of_guests'] ?></div>
        </div>
      </div>

      <div class="form-row">
        <div class="form-group">
          <label class="form-label">Expected Arrival</label>
          <div><?= formatDate($b['check_in_date']) ?></div>
          <?php if (!empty($b['actual_check_in'])): ?>
          <div style="color:var(--success);font-size:.83rem;">Arrived: <?= formatDateTime($b['actual_check_in']) ?></div>
          <?php endif; ?>
        </div>
        <div class="form-group">
          <label class="form-label">Expected Departure</label>
          <div><?= formatDate($b['check_out_date']) ?></div>
          <?php if (!empty($b['actual_check_out'])): ?>
          <div style="color:var(--secondary, #64748b);font-size:.83rem;">Left: <?= formatDateTime($b['actual_check_out']) ?></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="form-group">
        <label class="form-label">Purpose / Remarks</label>
        <div><?= nl2br(e($b['purpose_of_stay'])) ?></div>
      </div>

      <?php if (!empty($b['notes'])): ?>
      <div class="form-group">
        <label class="form-label">Notes</label>
        <div style="white-space:pre-wrap;background:var(--bg);padding:10px;border-radius:var(--radius-s);"><?= e($b['notes']) ?></div>
      </div>
      <?php endif; ?>

      <div style="display:flex;gap:10px;flex-wrap:wrap;">
        <?php if (!in_array($b['status'], ['checked_out', 'cancelled'], true)): ?>
        <a href="booking_edit.php?id=<?= (int)$b['booking_id'] ?>" class="btn btn-outline"><i data-lucide="pencil"></i> Edit</a>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Actions</div>
    <div class="card-body">

      <?php if ($b['status'] === 'reserved'): ?>
      <form method="POST" style="margin-bottom:12px;">
        <?= csrfField() ?>
        <button type="submit" name="do_arrived" class="btn btn-primary w-100" style="justify-content:center;"
                data-confirm="Mark this guest as arrived?">
          <i data-lucide="log-in"></i> Mark Arrived
        </button>
      </form>
      <?php endif; ?>

      <?php if (in_array($b['status'], ['checked_in', 'occupied'], true)): ?>
      <form method="POST" style="margin-bottom:12px;">
        <?= csrfField() ?>
        <textarea name="checkout_notes" rows="2" class="form-control" style="margin-bottom:8px;"
                  placeholder="Departure notes (optional)"></textarea>
        <button type="submit" name="do_left" class="btn btn-primary w-100" style="justify-content:center;"
                data-confirm="Mark this guest as left?">
          <i data-lucide="log-out"></i> Mark Left
        </button>
      </form>
      <?php endif; ?>

      <?php if (!in_array($b['status'], ['checked_out', 'cancelled'], true)): ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="text" name="cancel_reason" class="form-control" placeholder="Cancellation reason" style="margin-bottom:8px;">
        <button type="submit" name="do_cancel" class="btn btn-outline w-100" style="justify-content:center;color:var(--danger);border-color:var(--danger);"
                data-confirm="Cancel this expected guest?">
          <i data-lucide="x-circle"></i> Cancel
        </button>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>lucide.createIcons();</script>

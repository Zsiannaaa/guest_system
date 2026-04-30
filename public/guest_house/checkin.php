<?php
/**
 * public/guest_house/checkin.php — Check-in queue
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Check-in';
$db = getDB();

if (isPost() && isset($_POST['do_checkin'])) {
    verifyCsrf(APP_URL . '/public/guest_house/checkin.php');
    $bid = inputInt('booking_id','POST');
    $err = ghCheckIn($db, $bid, currentUserId());
    $err ? setFlash('error', $err) : setFlash('success','Guest checked in.');
    redirect(APP_URL . '/public/guest_house/checkin.php');
}

$queue = $db->query("
    SELECT b.*, g.full_name AS guest_name, g.organization, r.room_number, t.type_name
    FROM guest_house_bookings b
    JOIN guests g ON b.guest_id = g.guest_id
    LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
    LEFT JOIN gh_room_types t ON r.type_id = t.type_id
    WHERE b.status = 'reserved' AND b.check_in_date <= CURDATE()
    ORDER BY b.check_in_date, b.booking_id
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Check-in Queue</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Check-in</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guest_house/bookings.php?status=reserved" class="btn btn-outline">
      <i data-lucide="calendar"></i> All Reservations
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">Reservations ready to check in (<?= count($queue) ?>)</div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>Guest</th><th>Room</th><th>Planned Dates</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (empty($queue)): ?>
      <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-m);">No bookings waiting for check-in.</td></tr>
      <?php else: foreach ($queue as $b): ?>
      <tr>
        <td><code style="font-size:.8rem;"><?= e($b['booking_reference']) ?></code></td>
        <td>
          <div class="guest-cell">
            <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'],0,1)) ?></div>
            <div>
              <div class="guest-name"><?= e($b['guest_name']) ?></div>
              <?php if ($b['organization']): ?><div class="guest-ref"><?= e($b['organization']) ?></div><?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <?php if ($b['room_number']): ?>
            <strong><?= e($b['room_number']) ?></strong>
            <div style="font-size:.75rem;color:var(--text-m);"><?= e($b['type_name']) ?></div>
          <?php else: ?>
            <span style="color:var(--danger);font-size:.83rem;">Not assigned — edit to assign first</span>
          <?php endif; ?>
        </td>
        <td style="font-size:.83rem;">
          <?= formatDate($b['check_in_date']) ?> → <?= formatDate($b['check_out_date']) ?>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a>
            <?php if ($b['room_number']): ?>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
              <button type="submit" name="do_checkin" class="btn-tbl btn-tbl-success" data-confirm="Check in <?= e($b['guest_name']) ?>?">
                <i data-lucide="log-in"></i> Check-in
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

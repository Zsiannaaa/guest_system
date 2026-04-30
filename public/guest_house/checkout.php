<?php
/**
 * public/guest_house/checkout.php — Check-out queue
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Check-out';
$db = getDB();

if (isPost() && isset($_POST['do_checkout'])) {
    verifyCsrf(APP_URL . '/public/guest_house/checkout.php');
    $bid = inputInt('booking_id','POST');
    $err = ghCheckOut($db, $bid, currentUserId());
    $err ? setFlash('error', $err) : setFlash('success','Guest checked out.');
    redirect(APP_URL . '/public/guest_house/checkout.php');
}

$queue = $db->query("
    SELECT b.*, g.full_name AS guest_name, g.organization, r.room_number,
           DATEDIFF(CURDATE(), b.check_out_date) AS days_overdue
    FROM guest_house_bookings b
    JOIN guests g ON b.guest_id = g.guest_id
    LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
    WHERE b.status IN ('checked_in','occupied')
    ORDER BY b.check_out_date, b.booking_id
")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Check-out Queue</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Check-out</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guest_house/occupants.php" class="btn btn-outline">
      <i data-lucide="bed-double"></i> All Occupants
    </a>
  </div>
</div>

<div class="card">
  <div class="card-header">Currently staying (<?= count($queue) ?>)</div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>Guest</th><th>Room</th><th>Planned End</th><th>Status</th><th>Action</th></tr></thead>
      <tbody>
      <?php if (empty($queue)): ?>
      <tr><td colspan="6" style="text-align:center;padding:32px;color:var(--text-m);">No active stays.</td></tr>
      <?php else: foreach ($queue as $b):
          $overdue = (int)$b['days_overdue'] > 0;
      ?>
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
        <td><strong><?= e($b['room_number'] ?? '—') ?></strong></td>
        <td style="font-size:.85rem;"><?= formatDate($b['check_out_date']) ?></td>
        <td>
          <?php if ($overdue): ?>
            <span class="badge badge-danger">Overdue <?= (int)$b['days_overdue'] ?>d</span>
          <?php else: ?>
            <span class="badge badge-success">On schedule</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
              <button type="submit" name="do_checkout" class="btn-tbl btn-tbl-warn"
                      data-confirm="Check out <?= e($b['guest_name']) ?>?">
                <i data-lucide="log-out"></i> Check-out
              </button>
            </form>
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

<?php
/**
 * public/dashboard/guest_house.php - Simple Guest House dashboard
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_bookings_module.php';
require_once __DIR__ . '/../../modules/guest_house/gh_reports_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House Dashboard';
$db = getDB();

if (isPost()) {
    verifyCsrf(APP_URL . '/public/dashboard/guest_house.php');
    $bid = inputInt('booking_id', 'POST');

    if (isset($_POST['do_arrived'])) {
        $err = ghCheckIn($db, $bid, currentUserId());
        $err ? setFlash('error', $err) : setFlash('success', 'Guest marked as arrived.');
    } elseif (isset($_POST['do_left'])) {
        $err = ghCheckOut($db, $bid, currentUserId());
        $err ? setFlash('error', $err) : setFlash('success', 'Guest marked as left.');
    }

    redirect(APP_URL . '/public/dashboard/guest_house.php');
}

$occ = ghOccupancyToday($db);
$expectedToday = ghExpectedToday($db);
$currentStays = ghCurrentOccupants($db);
$upcoming = ghUpcomingExpected($db);

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="page-title">Guest House</div>
    <div class="page-subtitle">Expected guests, room assignments, and current stays.</div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= APP_URL ?>/public/guest_house/booking_create.php" class="btn btn-primary">
      <i data-lucide="calendar-plus"></i> Add Expected Guest
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/rooms.php" class="btn btn-outline">
      <i data-lucide="door-open"></i> Rooms
    </a>
  </div>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $occ['occupied'] ?>/<?= $occ['total'] ?></div>
        <div class="stat-label">Rooms Occupied</div>
      </div>
      <div class="stat-icon-wrap blue"><i data-lucide="bed-double"></i></div>
    </div>
    <div class="stat-trend live"><span class="live-dot"></span> <?= $occ['percent'] ?>% occupancy</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= count($expectedToday) ?></div>
        <div class="stat-label">Expected Today</div>
      </div>
      <div class="stat-icon-wrap green"><i data-lucide="log-in"></i></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= count($currentStays) ?></div>
        <div class="stat-label">Currently Staying</div>
      </div>
      <div class="stat-icon-wrap orange"><i data-lucide="users"></i></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= count($upcoming) ?></div>
        <div class="stat-label">Upcoming</div>
      </div>
      <div class="stat-icon-wrap purple"><i data-lucide="calendar-days"></i></div>
    </div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

  <div class="card">
    <div class="card-header">
      Expected Today
      <a href="<?= APP_URL ?>/public/guest_house/bookings.php?status=reserved" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Room</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (empty($expectedToday)): ?>
        <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--text-m);">No expected arrivals today.</td></tr>
        <?php else: foreach ($expectedToday as $b): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'], 0, 1)) ?></div>
              <div>
                <div class="guest-name"><?= e($b['guest_name']) ?></div>
                <?php if (!empty($b['organization'])): ?><div class="guest-ref"><?= e($b['organization']) ?></div><?php endif; ?>
              </div>
            </div>
          </td>
          <td><?= e($b['room_number'] ?? 'Unassigned') ?></td>
          <td>
            <div class="tbl-actions">
              <a href="<?= APP_URL ?>/public/guest_house/booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a>
              <?php if (!empty($b['room_id'])): ?>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <button type="submit" name="do_arrived" class="btn-tbl btn-tbl-success" data-confirm="Mark this guest as arrived?">
                  <i data-lucide="log-in"></i> Arrived
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

  <div class="card">
    <div class="card-header">
      Currently Staying
      <a href="<?= APP_URL ?>/public/guest_house/bookings.php?status=checked_in" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Room</th><th>Action</th></tr></thead>
        <tbody>
        <?php if (empty($currentStays)): ?>
        <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--text-m);">No guests currently staying.</td></tr>
        <?php else: foreach ($currentStays as $b): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'], 0, 1)) ?></div>
              <div>
                <div class="guest-name"><?= e($b['guest_name']) ?></div>
                <div class="guest-ref">Until <?= formatDate($b['check_out_date']) ?></div>
              </div>
            </div>
          </td>
          <td><?= e($b['room_number'] ?? 'Unassigned') ?></td>
          <td>
            <div class="tbl-actions">
              <a href="<?= APP_URL ?>/public/guest_house/booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a>
              <form method="POST" style="display:inline;">
                <?= csrfField() ?>
                <input type="hidden" name="booking_id" value="<?= (int)$b['booking_id'] ?>">
                <button type="submit" name="do_left" class="btn-tbl btn-tbl-warn" data-confirm="Mark this guest as left?">
                  <i data-lucide="log-out"></i> Left
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
</div>

<div class="card">
  <div class="card-header">
    Upcoming Expected Guests
    <a href="<?= APP_URL ?>/public/guest_house/bookings.php" class="view-all">Open list</a>
  </div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Guest</th><th>Organization</th><th>Room</th><th>Expected Dates</th><th></th></tr></thead>
      <tbody>
      <?php if (empty($upcoming)): ?>
      <tr><td colspan="5" style="text-align:center;padding:28px;color:var(--text-m);">No upcoming expected guests.</td></tr>
      <?php else: foreach ($upcoming as $b): ?>
      <tr>
        <td style="font-weight:600;"><?= e($b['guest_name']) ?></td>
        <td><?= e($b['organization'] ?? '') ?></td>
        <td><?= e($b['room_number'] ?? 'Unassigned') ?></td>
        <td style="font-size:.85rem;"><?= formatDate($b['check_in_date']) ?> to <?= formatDate($b['check_out_date']) ?></td>
        <td><a href="<?= APP_URL ?>/public/guest_house/booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>
<script>lucide.createIcons();</script>

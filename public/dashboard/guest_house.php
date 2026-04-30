<?php
/**
 * public/dashboard/guest_house.php — Guest House staff dashboard
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';
require_once __DIR__ . '/../../modules/gh_reports_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House Dashboard';
$db = getDB();
$today = date('Y-m-d');

$occ = ghOccupancyToday($db);

$arrivalsToday   = $db->query("
    SELECT b.*, g.full_name AS guest_name, r.room_number
    FROM guest_house_bookings b
    JOIN guests g ON b.guest_id = g.guest_id
    LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
    WHERE b.check_in_date = CURDATE() AND b.status IN ('reserved','checked_in','occupied')
    ORDER BY b.booking_id DESC
")->fetchAll();

$departuresToday = $db->query("
    SELECT b.*, g.full_name AS guest_name, r.room_number
    FROM guest_house_bookings b
    JOIN guests g ON b.guest_id = g.guest_id
    LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
    WHERE b.check_out_date = CURDATE() AND b.status IN ('checked_in','occupied')
    ORDER BY b.booking_id DESC
")->fetchAll();

$activeBookings  = (int)$db->query("SELECT COUNT(*) FROM guest_house_bookings WHERE status IN ('checked_in','occupied')")->fetchColumn();
$reservedFuture  = (int)$db->query("SELECT COUNT(*) FROM guest_house_bookings WHERE status='reserved' AND check_in_date >= CURDATE()")->fetchColumn();

// 7-day occupancy
$daily = ghDailyOccupancy($db, date('Y-m-d', strtotime('-6 days')), $today);

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="page-title">Welcome, <?= e(explode(' ', currentUserName())[0]) ?>!</div>
    <div class="page-subtitle">Guest House accommodation overview.</div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= APP_URL ?>/public/guest_house/booking_create.php" class="btn btn-primary">
      <i data-lucide="calendar-plus"></i> New Booking
    </a>
    <a href="<?= APP_URL ?>/public/guest_house/occupants.php" class="btn btn-outline">
      <i data-lucide="bed-double"></i> Occupants
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
        <div class="stat-value"><?= count($arrivalsToday) ?></div>
        <div class="stat-label">Arrivals Today</div>
      </div>
      <div class="stat-icon-wrap green"><i data-lucide="log-in"></i></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= count($departuresToday) ?></div>
        <div class="stat-label">Departures Today</div>
      </div>
      <div class="stat-icon-wrap purple"><i data-lucide="log-out"></i></div>
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $activeBookings ?></div>
        <div class="stat-label">Active Bookings</div>
      </div>
      <div class="stat-icon-wrap orange"><i data-lucide="users"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);"><?= $reservedFuture ?> future reservations</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">

  <div class="card">
    <div class="card-header">
      Today's Arrivals
      <a href="<?= APP_URL ?>/public/guest_house/checkin.php" class="view-all">Check-in queue</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Room</th><th>Status</th></tr></thead>
        <tbody>
        <?php if (empty($arrivalsToday)): ?>
        <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--text-m);">No arrivals today.</td></tr>
        <?php else: foreach ($arrivalsToday as $b): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'],0,1)) ?></div>
              <div>
                <div class="guest-name"><?= e($b['guest_name']) ?></div>
                <div class="guest-ref">REF: <?= e($b['booking_reference']) ?></div>
              </div>
            </div>
          </td>
          <td><?= e($b['room_number'] ?? '—') ?></td>
          <td><span class="badge <?= $b['status']==='reserved'?'badge-warning':'badge-success' ?>"><?= statusLabel($b['status']) ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">
      Today's Departures
      <a href="<?= APP_URL ?>/public/guest_house/checkout.php" class="view-all">Check-out queue</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Room</th><th>Planned</th></tr></thead>
        <tbody>
        <?php if (empty($departuresToday)): ?>
        <tr><td colspan="3" style="text-align:center;padding:28px;color:var(--text-m);">No departures today.</td></tr>
        <?php else: foreach ($departuresToday as $b): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'],0,1)) ?></div>
              <div>
                <div class="guest-name"><?= e($b['guest_name']) ?></div>
                <div class="guest-ref">REF: <?= e($b['booking_reference']) ?></div>
              </div>
            </div>
          </td>
          <td><?= e($b['room_number'] ?? '—') ?></td>
          <td style="font-size:.85rem;"><?= formatDate($b['check_out_date']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<div class="card">
  <div class="card-header">Occupancy — Last 7 days</div>
  <div class="card-body">
    <canvas id="ghDailyChart" height="90"></canvas>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
const ctx = document.getElementById('ghDailyChart');
if (ctx) {
  new Chart(ctx, {
    type: 'bar',
    data: {
      labels: <?= json_encode(array_column($daily, 'date')) ?>,
      datasets: [{
        label: 'Occupancy %',
        data: <?= json_encode(array_column($daily, 'percent')) ?>,
        backgroundColor: '#1f7a35',
        borderRadius: 6,
      }]
    },
    options: {
      responsive: true,
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
    }
  });
}
lucide.createIcons();
</script>

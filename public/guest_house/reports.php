<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Guest House page/controller for reports. It connects forms and views to Guest House booking/room modules.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * public/guest_house/reports.php — Guest House reports & analytics
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_reports_module.php';

// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Reports';
$db = getDB();

$from = inputStr('from','GET') ?: date('Y-m-d', strtotime('-29 days'));
$to   = inputStr('to','GET')   ?: date('Y-m-d');

$occToday = ghOccupancyToday($db);
$stays    = ghStaysByPeriod($db, $from, $to);
$avgLen   = ghAverageStayLength($db, $from, $to);
$topOff   = ghTopSponsoringOffices($db, $from, $to, 8);
$roomUtil = ghRoomUtilization($db, $from, $to);
$daily    = ghDailyOccupancy($db, $from, $to);

// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Guest House Reports</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Reports</li>
    </ul>
  </div>
  <form method="GET" style="display:flex;gap:8px;align-items:flex-end;">
    <div>
      <label class="form-label">From</label>
      <input type="date" name="from" class="form-control" value="<?= e($from) ?>">
    </div>
    <div>
      <label class="form-label">To</label>
      <input type="date" name="to" class="form-control" value="<?= e($to) ?>">
    </div>
    <button class="btn btn-primary"><i data-lucide="filter"></i> Apply</button>
  </form>
</div>

<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $occToday['percent'] ?>%</div><div class="stat-label">Occupancy Today</div></div>
      <div class="stat-icon-wrap blue"><i data-lucide="bed-double"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);"><?= $occToday['occupied'] ?>/<?= $occToday['total'] ?> rooms</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= (int)$stays['total_bookings'] ?></div><div class="stat-label">Bookings in range</div></div>
      <div class="stat-icon-wrap green"><i data-lucide="calendar-check"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">
      <?= (int)$stays['completed'] ?> completed · <?= (int)$stays['active'] ?> active
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $avgLen ?></div><div class="stat-label">Avg. nights / stay</div></div>
      <div class="stat-icon-wrap purple"><i data-lucide="moon"></i></div>
    </div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= (int)$stays['cancelled'] + (int)$stays['no_show'] ?></div><div class="stat-label">Cancelled / No-show</div></div>
      <div class="stat-icon-wrap red"><i data-lucide="x-circle"></i></div>
    </div>
  </div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header">Daily Occupancy (<?= e($from) ?> → <?= e($to) ?>)</div>
  <div class="card-body">
    <canvas id="dailyChart" height="90"></canvas>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

  <div class="card">
    <div class="card-header">Room Utilization</div>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Room</th><th>Type</th><th>Nights Booked</th><th>%</th></tr></thead>
        <tbody>
        <?php foreach ($roomUtil as $r): ?>
        <tr>
          <td style="font-weight:600;"><?= e($r['room_number']) ?></td>
          <td><?= e($r['type_name']) ?></td>
          <td><?= (int)$r['nights_booked'] ?> / <?= (int)$r['nights_available'] ?></td>
          <td>
            <div style="background:#e2e8f0;border-radius:10px;height:6px;overflow:hidden;width:80px;">
              <div style="background:#1f7a35;height:100%;width:<?= min(100,(int)$r['utilization_pct']) ?>%;"></div>
            </div>
            <span style="font-size:.75rem;color:var(--text-m);"><?= (int)$r['utilization_pct'] ?>%</span>
          </td>
        </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>

  <div class="card">
    <div class="card-header">Top Sponsoring Offices</div>
    <div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Office</th><th>Bookings</th></tr></thead>
        <tbody>
        <?php if (empty($topOff)): ?>
        <tr><td colspan="2" style="text-align:center;color:var(--text-m);padding:20px;">No sponsor data in range.</td></tr>
        <?php else: foreach ($topOff as $o): ?>
        <tr>
          <td><?= e($o['office_name']) ?></td>
          <td><span class="badge badge-blue"><?= (int)$o['total'] ?></span></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
const ctx = document.getElementById('dailyChart');
if (ctx) {
  new Chart(ctx, {
    type: 'line',
    data: {
      labels: <?= json_encode(array_column($daily, 'date')) ?>,
      datasets: [{
        label: 'Occupancy %',
        data: <?= json_encode(array_column($daily, 'percent')) ?>,
        borderColor: '#1f7a35',
        backgroundColor: 'rgba(31,122,53,.15)',
        fill: true,
        tension: .25,
      }]
    },
    options: {
      plugins: { legend: { display: false } },
      scales: { y: { beginAtZero: true, max: 100, ticks: { callback: v => v + '%' } } }
    }
  });
}
lucide.createIcons();
</script>

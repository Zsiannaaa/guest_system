<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/dashboard/dashboard_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Dashboard';
$db = getDB();
$today = date('Y-m-d');

// Stats
$totalToday    = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d", [':d'=>$today]);
$insideNow     = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='checked_in'");
$checkedOutToday = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND overall_status='checked_out'", [':d'=>$today]);
$withVehicle   = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND has_vehicle=1", [':d'=>$today]);
$overstayed    = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='overstayed'");
$yesterday     = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d", [':d'=>date('Y-m-d', strtotime('-1 day'))]);
$pctChange     = $yesterday > 0 ? round((($totalToday - $yesterday) / $yesterday) * 100) : 0;

// Active visitors
$activeStmt = $db->prepare("
    SELECT gv.visit_id, gv.visit_reference, gv.actual_check_in, gv.registration_type,
           g.full_name AS guest_name,
           GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations,
           MAX(CASE WHEN vd.is_unplanned=1 THEN 'Unplanned' ELSE 'Primary' END) AS dest_type
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id=g.guest_id
    LEFT JOIN visit_destinations vd ON gv.visit_id=vd.visit_id
    LEFT JOIN offices o ON vd.office_id=o.office_id
    WHERE gv.overall_status='checked_in'
    GROUP BY gv.visit_id
    ORDER BY gv.actual_check_in DESC
    LIMIT 5
");
$activeStmt->execute();
$activeVisitors = $activeStmt->fetchAll();

// Recent registrations
$recentStmt = $db->prepare("
    SELECT gv.visit_id, gv.visit_reference, gv.registration_type, gv.overall_status,
           gv.actual_check_in, gv.purpose_of_visit,
           g.full_name AS guest_name
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id=g.guest_id
    ORDER BY gv.created_at DESC LIMIT 5
");
$recentStmt->execute();
$recentVisits = $recentStmt->fetchAll();

// Visitors by office
$officeStmt = $db->prepare("
    SELECT o.office_name, COUNT(vd.destination_id) AS total
    FROM visit_destinations vd
    JOIN offices o ON vd.office_id=o.office_id
    JOIN guest_visits gv ON vd.visit_id=gv.visit_id
    WHERE gv.visit_date=:d
    GROUP BY o.office_id ORDER BY total DESC
");
$officeStmt->execute([':d'=>$today]);
$byOffice = $officeStmt->fetchAll();
$officeChartDate = $today;
$officeChartTitle = 'Visitors by Office (Today)';

if (empty($byOffice)) {
    $latestVisitDate = $db->query("SELECT MAX(visit_date) FROM guest_visits")->fetchColumn();
    if ($latestVisitDate) {
        $officeStmt->execute([':d' => $latestVisitDate]);
        $byOffice = $officeStmt->fetchAll();
        $officeChartDate = $latestVisitDate;
        $officeChartTitle = 'Visitors by Office (Latest Records)';
    }
}

$officeChartTotal = array_sum(array_map('intval', array_column($byOffice, 'total')));
$officeChartRows = array_slice($byOffice, 0, 5);
if (count($byOffice) > 5) {
    $otherTotal = array_sum(array_map('intval', array_column(array_slice($byOffice, 5), 'total')));
    $officeChartRows[] = ['office_name' => 'Others', 'total' => $otherTotal];
}

include __DIR__ . '/../../includes/header.php';
?>

<!-- Welcome row -->
<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="page-title">Welcome back, <?= htmlspecialchars(explode(' ', currentUserName())[0]) ?>!</div>
    <div class="page-subtitle">Here's what's happening in the university today.</div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= APP_URL ?>/public/visits/walkin.php" class="btn btn-primary">
      <i data-lucide="user-plus"></i> Walk-in Registration
    </a>
    <a href="<?= APP_URL ?>/public/visits/preregister.php" class="btn btn-outline">
      <i data-lucide="calendar-plus"></i> Pre-Registration
    </a>
  </div>
</div>

<!-- Stat Cards -->
<div class="stat-grid">
  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $totalToday ?></div>
        <div class="stat-label">Total Visitors Today</div>
      </div>
      <div class="stat-icon-wrap blue"><i data-lucide="users"></i></div>
    </div>
    <div class="stat-trend <?= $pctChange >= 0 ? 'up' : 'down' ?>">
      <i data-lucide="<?= $pctChange >= 0 ? 'trending-up' : 'trending-down' ?>" style="width:13px;height:13px;"></i>
      <?= abs($pctChange) ?>% from yesterday
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $insideNow ?></div>
        <div class="stat-label">Currently Inside</div>
      </div>
      <div class="stat-icon-wrap green"><i data-lucide="map-pin"></i></div>
    </div>
    <div class="stat-trend live"><span class="live-dot"></span> Live</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $checkedOutToday ?></div>
        <div class="stat-label">Checked-Out Today</div>
      </div>
      <div class="stat-icon-wrap purple"><i data-lucide="calendar-check"></i></div>
    </div>
    <div class="stat-trend up">
      <i data-lucide="trending-up" style="width:13px;height:13px;"></i>
      Today's completed visits
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $withVehicle ?></div>
        <div class="stat-label">Visitors with Vehicles</div>
      </div>
      <div class="stat-icon-wrap orange"><i data-lucide="car"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">
      <?= $totalToday > 0 ? round(($withVehicle/$totalToday)*100) : 0 ?>% of total visitors
    </div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $overstayed ?></div>
        <div class="stat-label">Overstayed Visitors</div>
      </div>
      <div class="stat-icon-wrap red"><i data-lucide="alert-triangle"></i></div>
    </div>
    <?php if ($overstayed > 0): ?>
    <a href="<?= APP_URL ?>/public/visits/active.php" class="stat-trend" style="color:var(--danger);font-size:.75rem;font-weight:600;">
      View all <i data-lucide="arrow-right" style="width:12px;height:12px;"></i>
    </a>
    <?php else: ?>
    <div class="stat-trend" style="color:var(--text-m);">No overstays today</div>
    <?php endif; ?>
  </div>
</div>

<!-- Middle Row: Active Visitors + Visitors by Office -->
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;margin-bottom:20px;">

  <!-- Active Visitors Table -->
  <div class="card">
    <div class="card-header">
      Active Visitors Inside Campus
      <a href="<?= APP_URL ?>/public/visits/active.php" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr>
          <th>Guest</th><th>Destination</th><th>Check-In</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php if (empty($activeVisitors)): ?>
        <tr><td colspan="4" style="text-align:center;padding:32px;color:var(--text-m);">No active visitors right now.</td></tr>
        <?php else: foreach ($activeVisitors as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($v['guest_name'],0,1)) ?></div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref">REF: <?= htmlspecialchars($v['visit_reference']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <div style="font-size:.85rem;font-weight:500;"><?= htmlspecialchars($v['destinations'] ?? '—') ?></div>
            <span class="badge-tag <?= ($v['dest_type'] === 'Unplanned') ? 'unplanned' : '' ?>"
                  style="font-size:.68rem;padding:2px 7px;border-radius:4px;font-weight:600;
                         background:<?= ($v['dest_type']==='Unplanned') ? 'var(--orange-l)' : 'var(--accent-l)' ?>;
                         color:<?= ($v['dest_type']==='Unplanned') ? 'var(--orange)' : 'var(--accent)' ?>;">
              <?= $v['dest_type'] ?>
            </span>
          </td>
          <td style="font-size:.85rem;"><?= formatTime($v['actual_check_in']) ?></td>
          <td><span class="badge badge-inside">Inside</span></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Visitors by Office (Donut) -->
  <div class="card office-chart-card">
    <div class="card-header">
      <?= e($officeChartTitle) ?>
      <a href="<?= APP_URL ?>/public/reports/index.php" class="view-all">View report</a>
    </div>
    <div class="card-body office-chart-body">
      <div class="office-chart-wrap">
        <canvas id="officeChart"></canvas>
        <div class="office-chart-center">
          <div class="office-chart-total"><?= $officeChartTotal ?></div>
          <div class="office-chart-label">Total</div>
        </div>
      </div>
      <div class="office-chart-legend">
        <?php
        $chartColors = ['#0f5724','#1f7a35','#7aa866','#f4d44d','#c7e4b6','#94a3b8'];
        foreach ($officeChartRows as $i => $o):
          $pct = $officeChartTotal > 0 ? round(((int)$o['total']/$officeChartTotal)*100) : 0;
        ?>
        <div class="office-chart-legend-row">
          <span class="office-chart-swatch" style="background:<?= $chartColors[$i] ?>;"></span>
          <span class="office-chart-name"><?= htmlspecialchars($o['office_name']) ?></span>
          <span class="office-chart-count"><?= (int)$o['total'] ?> (<?= $pct ?>%)</span>
        </div>
        <?php endforeach; ?>
        <?php if (empty($officeChartRows)): ?>
        <div class="office-chart-empty">No visit destinations recorded yet.</div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<!-- Bottom Row: Recent Visits + Quick Lookup -->
<div style="display:grid;grid-template-columns:1fr 340px;gap:20px;">

  <!-- Recent Visit Registrations -->
  <div class="card">
    <div class="card-header">
      Recent Visit Registrations
      <a href="<?= APP_URL ?>/public/visits/list.php" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr>
          <th>Guest</th><th>Type</th><th>Purpose</th><th>Check-In</th><th>Status</th>
        </tr></thead>
        <tbody>
        <?php if (empty($recentVisits)): ?>
        <tr><td colspan="5" style="text-align:center;padding:32px;color:var(--text-m);">No recent visits.</td></tr>
        <?php else: foreach ($recentVisits as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar">
                <?= strtoupper(substr($v['guest_name'],0,1)) ?>
              </div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref">REF: <?= htmlspecialchars($v['visit_reference']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge <?= $v['registration_type']==='walk_in' ? 'badge-warning' : 'badge-blue' ?>">
              <?= $v['registration_type']==='walk_in' ? 'Walk-in' : 'Pre-Registered' ?>
            </span>
          </td>
          <td style="font-size:.83rem;color:var(--text-s);"><?= htmlspecialchars(substr($v['purpose_of_visit'],0,30)) ?>…</td>
          <td style="font-size:.83rem;"><?= formatTime($v['actual_check_in']) ?></td>
          <td>
            <?php
            $sc = match($v['overall_status']) {
              'checked_in'  => 'badge-success',
              'checked_out' => 'badge-secondary',
              'pending'     => 'badge-warning',
              default       => 'badge-secondary',
            };
            $sl = match($v['overall_status']) {
              'checked_in'  => 'Checked-in',
              'checked_out' => 'Checked-out',
              'pending'     => 'Pending',
              default       => ucfirst($v['overall_status']),
            };
            ?>
            <span class="badge <?= $sc ?>"><?= $sl ?></span>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Quick Lookup -->
  <div class="card">
    <div class="card-header">Quick Lookup</div>
    <div class="card-body">
      <div class="lookup-tabs" id="lookupTabs">
        <span class="lookup-tab active" data-tab="ref">By Reference No.</span>
        <span class="lookup-tab" data-tab="qr">By QR Code</span>
        <span class="lookup-tab" data-tab="name">By Name</span>
      </div>
      <form action="<?= APP_URL ?>/public/visits/lookup.php" method="GET">
        <div class="input-icon-wrap" style="margin-bottom:10px;">
          <i data-lucide="search" class="input-icon"></i>
          <input type="text" name="q" id="lookupInput" class="form-control"
                 placeholder="Enter reference number..." required>
        </div>
        <button type="submit" class="btn btn-primary w-100" style="justify-content:center;">
          <i data-lucide="search"></i> Search
        </button>
      </form>
      <div style="margin-top:14px;background:var(--accent-l);border-radius:var(--radius-s);padding:12px;display:flex;gap:10px;align-items:flex-start;">
        <i data-lucide="info" style="width:15px;height:15px;color:var(--accent);flex-shrink:0;margin-top:1px;"></i>
        <p style="font-size:.78rem;color:var(--accent);margin:0;line-height:1.5;">
          Use the guest's reference number to quickly find their visit records.
        </p>
      </div>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

<script>
// Donut chart
const officeCtx = document.getElementById('officeChart');
if (officeCtx) {
  const officeLabels = <?= json_encode(array_column($officeChartRows, 'office_name')) ?>;
  const officeData = <?= json_encode(array_map('intval', array_column($officeChartRows, 'total'))) ?>;
  const hasOfficeData = officeData.length > 0 && officeData.some(v => v > 0);
  new Chart(officeCtx, {
    type: 'doughnut',
    data: {
      labels: hasOfficeData ? officeLabels : ['No data'],
      datasets: [{
        data: hasOfficeData ? officeData : [1],
        backgroundColor: hasOfficeData ? ['#0f5724','#1f7a35','#7aa866','#f4d44d','#c7e4b6','#94a3b8'] : ['#e2e8f0'],
        borderWidth: 2,
        borderColor: '#fff',
        hoverOffset: 4,
      }]
    },
    options: {
      cutout: '68%',
      plugins: { legend: { display: false }, tooltip: { callbacks: {
        label: ctx => hasOfficeData ? ' ' + ctx.label + ': ' + ctx.parsed : ' No visit destinations yet'
      }}},
      maintainAspectRatio: false,
    }
  });
}

// Lookup tabs
const tabs = document.querySelectorAll('.lookup-tab');
const placeholders = { ref: 'Enter reference number...', qr: 'Enter QR token...', name: 'Enter guest name...' };
tabs.forEach(t => t.addEventListener('click', function() {
  tabs.forEach(x => x.classList.remove('active'));
  this.classList.add('active');
  document.getElementById('lookupInput').placeholder = placeholders[this.dataset.tab] || '';
}));
lucide.createIcons();
</script>

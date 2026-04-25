<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/dashboard_module.php';
requireRole(ROLE_GUARD);
$pageTitle = 'Guard Dashboard';
$db  = getDB();
$today = date('Y-m-d');

$insideNow  = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='checked_in'");
$todayTotal = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d",[':d'=>$today]);
$pending    = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='pending' AND visit_date=:d",[':d'=>$today]);
$walkins    = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND registration_type='walk_in'",[':d'=>$today]);

$activeStmt = $db->prepare("
    SELECT gv.visit_id,gv.visit_reference,gv.actual_check_in,
           g.full_name AS guest_name,g.is_restricted,
           GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id=g.guest_id
    LEFT JOIN visit_destinations vd ON gv.visit_id=vd.visit_id
    LEFT JOIN offices o ON vd.office_id=o.office_id
    WHERE gv.overall_status='checked_in'
    GROUP BY gv.visit_id ORDER BY gv.actual_check_in DESC LIMIT 6
");
$activeStmt->execute();
$active = $activeStmt->fetchAll();

$pendingStmt = $db->prepare("
    SELECT gv.visit_id,gv.visit_reference,gv.visit_date,gv.expected_time_in,
           g.full_name AS guest_name,
           GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id=g.guest_id
    LEFT JOIN visit_destinations vd ON gv.visit_id=vd.visit_id
    LEFT JOIN offices o ON vd.office_id=o.office_id
    WHERE gv.overall_status='pending' AND gv.visit_date=:d
    GROUP BY gv.visit_id ORDER BY gv.expected_time_in ASC LIMIT 5
");
$pendingStmt->execute([':d'=>$today]);
$pendingVisits = $pendingStmt->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="page-title">Gate Dashboard</div>
    <div class="page-subtitle">Monitor and manage campus entry & exit.</div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= APP_URL ?>/public/visits/walkin.php"  class="btn btn-primary"><i data-lucide="user-plus"></i> Walk-In</a>
    <a href="<?= APP_URL ?>/public/visits/checkin.php" class="btn btn-outline"><i data-lucide="log-in"></i> Check-In</a>
    <a href="<?= APP_URL ?>/public/visits/checkout.php" class="btn btn-outline"><i data-lucide="log-out"></i> Check-Out</a>
  </div>
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:22px;">
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $insideNow ?></div><div class="stat-label">Currently Inside</div></div>
      <div class="stat-icon-wrap green"><i data-lucide="map-pin"></i></div>
    </div>
    <div class="stat-trend live"><span class="live-dot"></span> Live</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $todayTotal ?></div><div class="stat-label">Total Today</div></div>
      <div class="stat-icon-wrap blue"><i data-lucide="users"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">All registrations today</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $pending ?></div><div class="stat-label">Pending Arrivals</div></div>
      <div class="stat-icon-wrap orange"><i data-lucide="clock"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Pre-registered, not arrived</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $walkins ?></div><div class="stat-label">Walk-Ins Today</div></div>
      <div class="stat-icon-wrap purple"><i data-lucide="user-plus"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Unregistered arrivals</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <!-- Active -->
  <div class="card">
    <div class="card-header">
      Active Visitors
      <a href="<?= APP_URL ?>/public/visits/active.php" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Destination</th><th>Check-In</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($active)): ?>
        <tr><td colspan="4" style="text-align:center;padding:28px;color:var(--text-m);">No active visitors.</td></tr>
        <?php else: foreach($active as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar" style="<?= $v['is_restricted'] ? 'background:var(--danger-l);color:var(--danger)' : '' ?>">
                <?= strtoupper(substr($v['guest_name'],0,1)) ?>
              </div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref"><?= htmlspecialchars($v['visit_reference']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:.83rem;"><?= htmlspecialchars($v['destinations'] ?? '—') ?></td>
          <td style="font-size:.83rem;"><?= formatTime($v['actual_check_in']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/public/visits/checkout.php?id=<?= $v['visit_id'] ?>" class="btn btn-sm btn-outline">
              <i data-lucide="log-out" style="width:13px;height:13px;"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Pending -->
  <div class="card">
    <div class="card-header">
      Pending Arrivals Today
      <a href="<?= APP_URL ?>/public/visits/checkin.php" class="view-all">Check in</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Destination</th><th>Expected</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($pendingVisits)): ?>
        <tr><td colspan="4" style="text-align:center;padding:28px;color:var(--text-m);">No pending arrivals.</td></tr>
        <?php else: foreach($pendingVisits as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($v['guest_name'],0,1)) ?></div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref"><?= htmlspecialchars($v['visit_reference']) ?></div>
              </div>
            </div>
          </td>
          <td style="font-size:.83rem;"><?= htmlspecialchars($v['destinations'] ?? '—') ?></td>
          <td style="font-size:.83rem;"><?= formatTime($v['expected_time_in']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/public/visits/checkin.php?id=<?= $v['visit_id'] ?>" class="btn btn-sm btn-primary">
              <i data-lucide="log-in" style="width:13px;height:13px;"></i>
            </a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

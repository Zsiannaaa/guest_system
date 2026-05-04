<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Reports page/controller for index. It reads filters, runs report queries, and renders or exports results.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * reports/index.php — Visit Reports
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/reports/reports_module.php';
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole(ROLE_ADMIN);
$pageTitle = 'Reports'; $db = getDB();
$dateFrom = $_GET['date_from'] ?? date('Y-m-01'); $dateTo = $_GET['date_to'] ?? date('Y-m-d');
if (!strtotime($dateFrom)) $dateFrom = date('Y-m-01'); if (!strtotime($dateTo)) $dateTo = date('Y-m-d');
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];
$summary = getReportSummary($db, $dateFrom, $dateTo);
$perDay = getVisitsPerDay($db, $dateFrom, $dateTo);
$perOffice = getVisitsPerOffice($db, $dateFrom, $dateTo);
$statusBreakdown = getStatusBreakdown($db, $dateFrom, $dateTo);
$guestList = getGuestVisitLog($db, $dateFrom, $dateTo);

// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Visit Reports</div>
  <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li><li>Reports</li></ul></div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/reports/export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-success">
      <i data-lucide="file-spreadsheet"></i> Export Range
    </a>
    <a href="<?= APP_URL ?>/public/reports/export.php" class="btn btn-outline">
      <i data-lucide="download"></i> Export All
    </a>
  </div>
</div>

<!-- Date Filter -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><i data-lucide="filter" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Date Range</div>
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;align-items:flex-end;flex-wrap:wrap;">
      <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
        <label class="form-label">From</label>
        <input type="date" name="date_from" class="form-control" value="<?= e($dateFrom) ?>">
      </div>
      <div class="form-group" style="flex:1;min-width:160px;margin-bottom:0;">
        <label class="form-label">To</label>
        <input type="date" name="date_to" class="form-control" value="<?= e($dateTo) ?>">
      </div>
      <button type="submit" class="btn btn-primary" style="height:38px;"><i data-lucide="filter"></i> Apply</button>
      <a href="<?= APP_URL ?>/public/reports/export.php?date_from=<?= urlencode($dateFrom) ?>&date_to=<?= urlencode($dateTo) ?>" class="btn btn-outline" style="height:38px;"><i data-lucide="download"></i> Export</a>
    </form>
    <div style="margin-top:8px;font-size:.82rem;color:var(--text-s);">Showing <strong><?= formatDate($dateFrom) ?></strong> to <strong><?= formatDate($dateTo) ?></strong></div>
  </div>
</div>

<!-- Summary Stats -->
<div class="stat-grid reports-summary-grid">
  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $summary['total_visits'] ?></div>
        <div class="stat-label">Total Visits</div>
      </div>
      <div class="stat-icon-wrap blue"><i data-lucide="file-text"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Selected date range</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $summary['walk_ins'] ?></div>
        <div class="stat-label">Walk-Ins</div>
      </div>
      <div class="stat-icon-wrap green"><i data-lucide="footprints"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Gate registrations</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $summary['pre_registered'] ?></div>
        <div class="stat-label">Pre-Registered</div>
      </div>
      <div class="stat-icon-wrap purple"><i data-lucide="calendar-check"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Online registrations</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $summary['with_vehicle'] ?></div>
        <div class="stat-label">With Vehicle</div>
      </div>
      <div class="stat-icon-wrap orange"><i data-lucide="car"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Vehicle entries</div>
  </div>

  <div class="stat-card">
    <div class="stat-card-top">
      <div>
        <div class="stat-value"><?= $summary['unique_guests'] ?></div>
        <div class="stat-label">Unique Guests</div>
      </div>
      <div class="stat-icon-wrap red"><i data-lucide="users"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Distinct visitors</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;margin-bottom:20px;">
  <!-- Per Day -->
  <div class="card">
    <div class="card-header"><i data-lucide="calendar" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visits Per Day</div>
    <div class="card-body p-0"><div class="table-responsive" style="max-height:350px;overflow-y:auto;">
      <table class="data-table"><thead><tr><th>Date</th><th>Total</th><th>Walk-In</th><th>Pre-Reg</th></tr></thead><tbody>
      <?php if (empty($perDay)): ?><tr><td colspan="4"><div class="table-empty">No data</div></td></tr>
      <?php else: foreach($perDay as $d): ?>
      <tr><td style="font-size:.83rem;"><?= formatDate($d['visit_date']) ?></td><td><strong><?= $d['total'] ?></strong></td><td><?= $d['walk_ins'] ?></td><td><?= $d['pre_reg'] ?></td></tr>
      <?php endforeach; endif; ?></tbody></table>
    </div></div>
  </div>

  <!-- Per Office -->
  <div class="card">
    <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visits Per Office</div>
    <div class="card-body p-0"><div class="table-responsive" style="max-height:350px;overflow-y:auto;">
      <table class="data-table"><thead><tr><th>Office</th><th>Total</th><th>Unplanned</th></tr></thead><tbody>
      <?php if (empty($perOffice)): ?><tr><td colspan="3"><div class="table-empty">No data</div></td></tr>
      <?php else: foreach($perOffice as $o): ?>
      <tr><td style="font-weight:600;"><?= e($o['office_name']) ?></td><td><strong><?= $o['total'] ?></strong></td><td><?= $o['unplanned'] ?: '—' ?></td></tr>
      <?php endforeach; endif; ?></tbody></table>
    </div></div>
  </div>
</div>

<!-- Status Breakdown -->
<div style="display:grid;grid-template-columns:300px 1fr;gap:20px;">
  <div class="card">
    <div class="card-header"><i data-lucide="pie-chart" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Status Breakdown</div>
    <div class="card-body">
      <?php foreach ($statusBreakdown as $sb): $sc=match($sb['overall_status']){'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary','cancelled'=>'badge-danger','overstayed'=>'badge-danger',default=>'badge-secondary'};?>
      <div style="display:flex;justify-content:space-between;align-items:center;margin-bottom:10px;">
        <span class="badge <?= $sc ?>" style="padding:5px 14px;"><?= statusLabel($sb['overall_status']) ?></span>
        <strong><?= $sb['cnt'] ?></strong>
      </div>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- Full Guest List -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between;">
      <span><i data-lucide="list" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest Visit Log</span>
      <span class="badge badge-secondary"><?= count($guestList) ?> records</span>
    </div>
    <div class="table-toolbar">
      <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
        <input type="text" id="searchInput" placeholder="Search log…" autocomplete="off"></div>
    </div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="data-table" id="tbl">
        <thead><tr><th>Reference</th><th>Guest</th><th>Date</th><th>Type</th><th>Status</th><th>In</th><th>Out</th></tr></thead>
        <tbody>
        <?php if (empty($guestList)): ?><tr><td colspan="7"><div class="table-empty">No visits for this period.</div></td></tr>
        <?php else: foreach ($guestList as $g): ?>
        <tr>
          <td><span class="ref-chip"><?= e($g['visit_reference']) ?></span></td>
          <td><div><div style="font-weight:600;font-size:.85rem;"><?= e($g['guest_name']) ?></div><div style="font-size:.75rem;color:var(--text-m);"><?= e($g['organization']??'') ?></div></div></td>
          <td style="font-size:.8rem;"><?= formatDate($g['visit_date']) ?></td>
          <td><span class="badge <?= $g['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= $g['registration_type']==='walk_in'?'Walk-in':'Pre-Reg' ?></span></td>
          <td><?php $sc2=match($g['overall_status']){'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary',default=>'badge-secondary'};?><span class="badge <?= $sc2 ?>"><?= statusLabel($g['overall_status']) ?></span></td>
          <td style="font-size:.78rem;"><?= formatTime($g['actual_check_in']) ?></td>
          <td style="font-size:.78rem;"><?= formatTime($g['actual_check_out']) ?></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),t=document.getElementById('tbl');
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(()=>{const q=s.value.toLowerCase().trim();
t.querySelectorAll('tbody tr').forEach(r=>{if(!r.querySelector('.table-empty'))r.style.display=(!q||r.textContent.toLowerCase().includes(q))?'':'none';});},200);});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

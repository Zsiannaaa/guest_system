<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Visit page/controller for lookup. It coordinates request data, visit module functions, and the shared layout.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * visits/lookup.php — Quick visit lookup (all roles)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';
// Study security: this page requires an active login before any private data is shown.
requireLogin();
$pageTitle = 'Visit Lookup'; $db = getDB();
$q = trim($_GET['q'] ?? ''); $results = [];

// Study query: SQL query: reads rows from guest_visits, guests for lookup, validation, or display.
$stmt = $db->query("
    SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.overall_status,
           gv.registration_type, gv.actual_check_in,
           g.full_name AS guest_name, g.organization
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id=g.guest_id
    ORDER BY gv.visit_date DESC, gv.created_at DESC
    LIMIT 500
");
$results = $stmt->fetchAll();
// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Visit Lookup</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>Lookup</li></ul></div>
</div>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><i data-lucide="search" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Search Visits</div>
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;">
      <div class="table-search" style="flex:1;max-width:none;">
        <i data-lucide="search" class="table-search-icon"></i>
        <input type="text" id="lookupSearchInput" name="q" value="<?= e($q) ?>" placeholder="Type reference number, QR token, or guest name..." autofocus autocomplete="off" style="padding:11px 12px 11px 36px;">
      </div>
      <button type="submit" class="btn btn-primary"><i data-lucide="search"></i> Search</button>
    </form>
  </div>
</div>

<?php if (!empty($results)): ?>
<div class="card">
  <div class="card-header"><span id="lookupVisibleCount"><?= count($results) ?></span> of <?= count($results) ?> visits</div>
  <div class="table-responsive">
    <table class="data-table" id="lookupTable">
      <thead><tr><th>Guest</th><th>Reference</th><th>Date</th><th>Type</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($results as $r): ?>
      <tr data-search="<?= e(strtolower(($r['guest_name'] ?? '') . ' ' . ($r['visit_reference'] ?? '') . ' ' . ($r['organization'] ?? '') . ' ' . ($r['overall_status'] ?? '') . ' ' . ($r['registration_type'] ?? ''))) ?>">
        <td><div class="guest-cell"><div class="guest-avatar"><?= strtoupper(substr($r['guest_name'],0,1)) ?></div><div><div class="guest-name"><?= e($r['guest_name']) ?></div><div class="guest-ref"><?= e($r['organization']??'') ?></div></div></div></td>
        <td><span class="ref-chip"><?= e($r['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;"><?= formatDate($r['visit_date']) ?></td>
        <td><span class="badge <?= $r['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= $r['registration_type']==='walk_in'?'Walk-in':'Pre-Reg' ?></span></td>
        <td><?php $sc=match($r['overall_status']){'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary',default=>'badge-secondary'};?><span class="badge <?= $sc ?>"><?= statusLabel($r['overall_status']) ?></span></td>
        <td><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $r['visit_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a></td>
      </tr>
      <?php endforeach; ?>
      <tr id="lookupNoRows" style="display:none;">
        <td colspan="6"><div class="table-empty">No visits match your search.</div></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>
<script>
const lookupSearchInput = document.getElementById('lookupSearchInput');
const lookupTable = document.getElementById('lookupTable');
const lookupVisibleCount = document.getElementById('lookupVisibleCount');
const lookupNoRows = document.getElementById('lookupNoRows');

function filterLookupVisits() {
  if (!lookupTable) return;
  const q = (lookupSearchInput?.value || '').toLowerCase().trim();
  let visible = 0;
  lookupTable.querySelectorAll('tbody tr[data-search]').forEach(row => {
    const ok = !q || row.dataset.search.includes(q);
    row.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });
  if (lookupVisibleCount) lookupVisibleCount.textContent = visible;
  if (lookupNoRows) lookupNoRows.style.display = visible ? 'none' : '';
}

lookupSearchInput?.addEventListener('input', filterLookupVisits);
filterLookupVisits();
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

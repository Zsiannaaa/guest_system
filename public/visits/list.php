<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Visit page/controller for list. It coordinates request data, visit module functions, and the shared layout.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * visits/list.php — All Visits List
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';
// Study security: this page requires an active login before any private data is shown.
requireLogin();
$pageTitle = 'All Visits'; $db = getDB();

$where = '1=1'; $params = [];
if (isOfficeStaff()) {
    $where .= " AND EXISTS (SELECT 1 FROM visit_destinations vd WHERE vd.visit_id=gv.visit_id AND vd.office_id=:oid)";
    $params[':oid'] = currentUser()['office_id'];
}
// Study query: Prepared SQL: reads rows from guest_visits, guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$stmt = $db->prepare("SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.overall_status, gv.registration_type, gv.actual_check_in, gv.actual_check_out, g.full_name AS guest_name, g.organization FROM guest_visits gv JOIN guests g ON gv.guest_id=g.guest_id WHERE {$where} ORDER BY gv.visit_date DESC, gv.created_at DESC LIMIT 500");
$stmt->execute($params);
$visits = $stmt->fetchAll(); $total = count($visits);
// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">All Visits</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>All Visits</li></ul></div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search by name, reference…" autocomplete="off"></div>
    <select class="table-filter" id="statusFilter">
      <option value="">All Status</option><option value="pending">Pending</option><option value="checked_in">Checked In</option><option value="checked_out">Checked Out</option><option value="cancelled">Cancelled</option>
    </select>
    <select class="table-filter" id="typeFilter">
      <option value="">All Types</option><option value="walk_in">Walk-in</option><option value="pre_registered">Pre-Registered</option>
    </select>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?></div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Guest</th><th>Reference</th><th>Date</th><th>Type</th><th>Status</th><th>Check-In</th><th>Check-Out</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($visits as $v): ?>
      <tr data-status="<?= $v['overall_status'] ?>" data-type="<?= $v['registration_type'] ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar"><?= strtoupper(substr($v['guest_name'],0,1)) ?></div>
            <div><div class="guest-name"><?= e($v['guest_name']) ?></div><div class="guest-ref"><?= e($v['organization']??'') ?></div></div>
          </div>
        </td>
        <td><span class="ref-chip"><?= e($v['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;"><?= formatDate($v['visit_date']) ?></td>
        <td><span class="badge <?= $v['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= $v['registration_type']==='walk_in'?'Walk-in':'Pre-Reg' ?></span></td>
        <td><?php
          $sc = match($v['overall_status']) { 'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary','cancelled'=>'badge-danger','overstayed'=>'badge-danger',default=>'badge-secondary' };
        ?><span class="badge <?= $sc ?>"><?= statusLabel($v['overall_status']) ?></span></td>
        <td style="font-size:.8rem;"><?= formatTime($v['actual_check_in']) ?></td>
        <td style="font-size:.8rem;"><?= formatTime($v['actual_check_out']) ?></td>
        <td><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),sf=document.getElementById('statusFilter'),tf=document.getElementById('typeFilter'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
function f(){const q=s.value.toLowerCase().trim(),st=sf.value,ty=tf.value;let n=0;
t.querySelectorAll('tbody tr[data-status]').forEach(r=>{const ok=(!q||r.textContent.toLowerCase().includes(q))&&(!st||r.dataset.status===st)&&(!ty||r.dataset.type===ty);r.style.display=ok?'':'none';if(ok)n++;});c.textContent=n;}
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(f,200);});sf.addEventListener('change',f);tf.addEventListener('change',f);
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

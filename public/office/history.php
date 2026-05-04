<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Office staff page/controller for history visits and destinations. It connects office actions to visit destination records.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * office/history.php — Past visits to this office
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/destinations_module.php';
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole([ROLE_OFFICE_STAFF, ROLE_ADMIN]);
$pageTitle = 'Visit History'; $db = getDB();
$officeId = isAdmin() ? (int)($_GET['office'] ?? 0) : currentOfficeId();

// Study query: Prepared SQL: reads rows from visit_destinations, guest_visits, guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$stmt = $db->prepare("SELECT vd.*, gv.visit_reference, gv.visit_date, gv.overall_status, g.full_name AS guest_name, g.organization FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id JOIN guests g ON gv.guest_id=g.guest_id WHERE vd.office_id=:oid AND vd.destination_status='completed' ORDER BY vd.completed_time DESC LIMIT 200");
$stmt->execute([':oid'=>$officeId]); $visits = $stmt->fetchAll(); $total=count($visits);
// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Visit History</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>History</li></ul></div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search by name or reference…" autocomplete="off"></div>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?></div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Guest</th><th>Reference</th><th>Visit Date</th><th>Arrived</th><th>Completed</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($visits as $v): ?>
      <tr>
        <td><div class="guest-cell"><div class="guest-avatar"><?= strtoupper(substr($v['guest_name'],0,1)) ?></div><div><div class="guest-name"><?= e($v['guest_name']) ?></div><div class="guest-ref"><?= e($v['organization']??'') ?></div></div></div></td>
        <td><span class="ref-chip"><?= e($v['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;"><?= formatDate($v['visit_date']) ?></td>
        <td style="font-size:.8rem;"><?= formatDateTime($v['arrival_time']) ?></td>
        <td style="font-size:.8rem;"><?= formatDateTime($v['completed_time']) ?></td>
        <td><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i></a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(()=>{const q=s.value.toLowerCase().trim();let n=0;t.querySelectorAll('tbody tr').forEach(r=>{const ok=!q||r.textContent.toLowerCase().includes(q);r.style.display=ok?'':'none';if(ok)n++;});c.textContent=n;},200);});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

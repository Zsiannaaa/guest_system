<?php
/**
 * office/incoming.php — Guests heading to this office
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/destinations_module.php';
requireRole([ROLE_OFFICE_STAFF, ROLE_ADMIN]);
$pageTitle = 'Incoming Guests'; $db = getDB();
$officeId = isAdmin() ? (int)($_GET['office'] ?? 0) : currentOfficeId();
if (!$officeId && isAdmin()) { setFlash('error','Select an office.'); redirect(APP_URL.'/public/admin/offices.php'); }

$stmt = $db->prepare("SELECT vd.*, gv.visit_reference, gv.actual_check_in, gv.purpose_of_visit, g.full_name AS guest_name, g.organization FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id JOIN guests g ON gv.guest_id=g.guest_id WHERE vd.office_id=:oid AND vd.destination_status='pending' AND gv.overall_status='checked_in' ORDER BY gv.actual_check_in DESC");
$stmt->execute([':oid'=>$officeId]); $guests = $stmt->fetchAll(); $total=count($guests);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Incoming Guests</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>Incoming</li></ul></div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search…" autocomplete="off"></div>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?> guests</div>
  </div>
  <div class="card-header" style="background:#fff7ed;"><span style="display:flex;align-items:center;gap:8px;"><i data-lucide="arrow-down-circle" style="width:16px;height:16px;color:var(--warning);"></i><strong><?= $total ?></strong> guest<?= $total!==1?'s':'' ?> heading to your office</span></div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Guest</th><th>Reference</th><th>Purpose</th><th>Checked In</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($guests)): ?><tr><td colspan="5"><div class="table-empty">No incoming guests right now.</div></td></tr>
      <?php else: foreach ($guests as $g): ?>
      <tr>
        <td><div class="guest-cell"><div class="guest-avatar"><?= strtoupper(substr($g['guest_name'],0,1)) ?></div><div><div class="guest-name"><?= e($g['guest_name']) ?></div><div class="guest-ref"><?= e($g['organization']??'') ?></div></div></div></td>
        <td><span class="ref-chip"><?= e($g['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;max-width:200px;"><?= e($g['purpose_of_visit']) ?></td>
        <td style="font-size:.83rem;"><?= formatDateTime($g['actual_check_in']) ?></td>
        <td><div class="tbl-actions">
          <a href="<?= APP_URL ?>/public/office/handle.php?dest_id=<?= $g['destination_id'] ?>" class="btn-tbl btn-tbl-success"><i data-lucide="check"></i> Receive</a>
          <a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $g['visit_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i></a>
        </div></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(()=>{const q=s.value.toLowerCase().trim();let n=0;t.querySelectorAll('tbody tr').forEach(r=>{if(!r.querySelector('.table-empty')){const ok=!q||r.textContent.toLowerCase().includes(q);r.style.display=ok?'':'none';if(ok)n++;}});c.textContent=n;},200);});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

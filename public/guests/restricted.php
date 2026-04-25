<?php
/**
 * guests/restricted.php — Restricted Guests List
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Restricted Guests'; $db = getDB();
$stmt = $db->prepare("SELECT g.*, r.reason, r.restricted_at, r.restriction_id, u.full_name AS restricted_by_name FROM guests g JOIN restricted_guests r ON g.guest_id=r.guest_id LEFT JOIN users u ON r.restricted_by_user_id=u.user_id WHERE r.is_active=1 ORDER BY r.restricted_at DESC");
$stmt->execute(); $restricted = $stmt->fetchAll(); $total=count($restricted);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Restricted Guests</div>
  <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li><li><a href="<?= APP_URL ?>/public/guests/list.php">Guests</a></li><li>Restricted</li></ul></div>
</div>

<?php if (empty($restricted)): ?>
<div class="info-box success"><i data-lucide="shield-check"></i><div>No restricted guests. All guests currently have campus access.</div></div>
<?php else: ?>

<div class="info-box warning" style="margin-bottom:20px;">
  <i data-lucide="alert-triangle"></i>
  <div><strong><?= $total ?> guest<?= $total!==1?'s':'' ?></strong> restricted from campus. Guards are alerted on check-in attempts.</div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search…" autocomplete="off"></div>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?></div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Guest</th><th>Contact</th><th>Reason</th><th>Restricted By</th><th>Date</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($restricted as $r): ?>
      <tr>
        <td><div class="guest-cell"><div class="guest-avatar" style="background:var(--danger-l);color:var(--danger);"><?= strtoupper(substr($r['full_name'],0,1)) ?></div><div><div class="guest-name"><?= e($r['full_name']) ?></div><div class="guest-ref"><?= e($r['organization']??'') ?></div></div></div></td>
        <td style="font-size:.83rem;"><?= e($r['contact_number'] ?? '—') ?></td>
        <td style="font-size:.83rem;max-width:200px;"><?= e($r['reason']) ?></td>
        <td style="font-size:.83rem;"><?= e($r['restricted_by_name'] ?? 'Admin') ?></td>
        <td style="font-size:.8rem;"><?= formatDateTime($r['restricted_at']) ?></td>
        <td><div class="tbl-actions">
          <a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $r['guest_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i></a>
          <a href="<?= APP_URL ?>/public/guests/lift_restriction.php?id=<?= $r['guest_id'] ?>" class="btn-tbl btn-tbl-success"><i data-lucide="shield-check"></i> Lift</a>
        </div></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(()=>{const q=s.value.toLowerCase().trim();let n=0;t.querySelectorAll('tbody tr').forEach(r=>{const ok=!q||r.textContent.toLowerCase().includes(q);r.style.display=ok?'':'none';if(ok)n++;});c.textContent=n;},200);});
</script>
<?php endif; ?>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

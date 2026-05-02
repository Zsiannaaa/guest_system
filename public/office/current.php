<?php
/**
 * office/current.php — Currently serving guests
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/destinations_module.php';
requireRole([ROLE_OFFICE_STAFF, ROLE_ADMIN]);
$pageTitle = 'Arrived Guests'; $db = getDB();
$officeId = isAdmin() ? (int)($_GET['office'] ?? 0) : currentOfficeId();

$stmt = $db->prepare("SELECT vd.*, gv.visit_reference, gv.purpose_of_visit, g.full_name AS guest_name, g.organization FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id JOIN guests g ON gv.guest_id=g.guest_id WHERE vd.office_id=:oid AND vd.destination_status IN('arrived','in_service') AND gv.overall_status='checked_in' ORDER BY vd.arrival_time DESC");
$stmt->execute([':oid'=>$officeId]); $guests = $stmt->fetchAll(); $total=count($guests);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Arrived Guests</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>Arrived Guests</li></ul></div>
</div>

<div class="card">
  <div class="card-header" style="background:#f0fdf4;"><span style="display:flex;align-items:center;gap:8px;"><span class="live-dot"></span><strong><?= $total ?></strong> guest<?= $total!==1?'s':'' ?> received by your office</span></div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Guest</th><th>Reference</th><th>Purpose</th><th>Arrived</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php if (empty($guests)): ?><tr><td colspan="6"><div class="table-empty">No arrived guests right now.</div></td></tr>
      <?php else: foreach ($guests as $g): ?>
      <tr>
        <td><div class="guest-cell"><div class="guest-avatar" style="background:var(--success-l);color:var(--success);"><?= strtoupper(substr($g['guest_name'],0,1)) ?></div><div><div class="guest-name"><?= e($g['guest_name']) ?></div></div></div></td>
        <td><span class="ref-chip"><?= e($g['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;"><?= e($g['purpose_of_visit']) ?></td>
        <td style="font-size:.83rem;"><?= formatDateTime($g['arrival_time']) ?></td>
        <td><span class="badge <?= $g['destination_status']==='in_service'?'badge-blue':'badge-info' ?>"><?= statusLabel($g['destination_status']) ?></span></td>
        <td><a href="<?= APP_URL ?>/public/office/handle.php?dest_id=<?= $g['destination_id'] ?>" class="btn-tbl btn-tbl-primary"><i data-lucide="check-circle"></i> Complete</a></td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
/**
 * guests/view.php — Guest Profile
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests_module.php';
requireLogin();
$pageTitle = 'Guest Profile'; $db = getDB();
$guestId = (int)($_GET['id'] ?? 0);
if (!$guestId) redirect(getDashboardUrl());
$gStmt = $db->prepare("SELECT * FROM guests WHERE guest_id=:id"); $gStmt->execute([':id'=>$guestId]);
$guest = $gStmt->fetch();
if (!$guest) { setFlash('error','Guest not found.'); redirect(APP_URL.'/public/guests/list.php'); }
$vStmt = $db->prepare("SELECT gv.*, u.full_name AS guard_name, GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS offices FROM guest_visits gv LEFT JOIN users u ON gv.processed_by_guard_id=u.user_id LEFT JOIN visit_destinations vd ON gv.visit_id=vd.visit_id LEFT JOIN offices o ON vd.office_id=o.office_id WHERE gv.guest_id=:gid GROUP BY gv.visit_id ORDER BY gv.visit_date DESC");
$vStmt->execute([':gid'=>$guestId]); $visits = $vStmt->fetchAll();
$rStmt = $db->prepare("SELECT r.*, u.full_name AS restricted_by FROM restricted_guests r LEFT JOIN users u ON r.restricted_by_user_id=u.user_id WHERE r.guest_id=:gid AND r.is_active=1 LIMIT 1");
$rStmt->execute([':gid'=>$guestId]); $restriction = $rStmt->fetch();
$vehStmt = $db->prepare("
    SELECT ve.*, gv.visit_reference, gv.visit_date
    FROM vehicle_entries ve
    JOIN guest_visits gv ON gv.visit_id=ve.visit_id
    WHERE gv.guest_id=:gid
    ORDER BY gv.visit_date DESC, gv.created_at DESC, ve.created_at DESC
    LIMIT 1
");
$vehStmt->execute([':gid'=>$guestId]); $vehicle = $vehStmt->fetch();
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Guest Profile</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li><a href="<?= APP_URL ?>/public/guests/list.php">Guests</a></li><li><?= e($guest['full_name']) ?></li></ul></div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guests/export.php?id=<?= $guestId ?>" class="btn btn-outline">
      <i data-lucide="download"></i> Export Profile
    </a>
    <?php if (isAdmin() && !$guest['is_restricted']): ?>
    <a href="<?= APP_URL ?>/public/guests/restrict.php?id=<?= $guestId ?>" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger);"><i data-lucide="shield-off"></i> Restrict</a>
    <?php elseif (isAdmin() && $guest['is_restricted']): ?>
    <a href="<?= APP_URL ?>/public/guests/lift_restriction.php?id=<?= $guestId ?>" class="btn btn-success"><i data-lucide="shield-check"></i> Lift Restriction</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($guest['is_restricted']): ?>
<div class="info-box danger" style="margin-bottom:20px;"><i data-lucide="shield-alert"></i><div>
  <strong>RESTRICTED GUEST</strong>
  <?php if ($restriction): ?><br>By <strong><?= e($restriction['restricted_by']??'Admin') ?></strong> on <?= formatDateTime($restriction['restricted_at']) ?>.<br>Reason: <?= e($restriction['reason']) ?><?php endif; ?>
</div></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:380px 1fr;gap:20px;">
  <div>
  <div class="card" style="margin-bottom:16px;">
    <div class="card-header"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Personal Information</div>
    <div class="card-body" style="text-align:center;padding:24px;">
      <div class="guest-avatar" style="width:64px;height:64px;font-size:1.6rem;margin:0 auto 12px;<?= $guest['is_restricted']?'background:var(--danger-l);color:var(--danger);':'' ?>"><?= strtoupper(substr($guest['full_name'],0,1)) ?></div>
      <div style="font-size:1.1rem;font-weight:700;margin-bottom:2px;"><?= e($guest['full_name']) ?></div>
      <?php if ($guest['organization']): ?><div style="font-size:.85rem;color:var(--text-s);"><?= e($guest['organization']) ?></div><?php endif; ?>
    </div>
    <div style="padding:0;">
      <dl style="margin:0;">
        <div class="detail-row" style="padding:10px 18px;"><dt>Contact</dt><dd><?= e($guest['contact_number'] ?? '—') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Email</dt><dd style="font-size:.83rem;"><?= e($guest['email'] ?? '—') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Address</dt><dd style="font-size:.83rem;"><?= e($guest['address'] ?? '—') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>ID Type</dt><dd><?= e($guest['id_type'] ?? '—') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>First Record</dt><dd style="font-size:.8rem;"><?= formatDateTime($guest['created_at']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Total Visits</dt><dd><span class="badge badge-primary"><?= count($visits) ?></span></dd></div>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i data-lucide="car" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Vehicle Information</div>
    <?php if ($vehicle): ?>
    <div class="card-body" style="padding:0;">
      <dl style="margin:0;">
        <div class="detail-row" style="padding:10px 18px;"><dt>Plate Number</dt><dd style="font-weight:700;"><?= e($vehicle['plate_number']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Type</dt><dd><?= e(statusLabel($vehicle['vehicle_type'])) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Color</dt><dd><?= e($vehicle['vehicle_color'] ?? 'â€”') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Model</dt><dd><?= e($vehicle['vehicle_model'] ?? 'â€”') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Driver</dt><dd><?= e($vehicle['driver_name'] ?: ($vehicle['is_driver_the_guest'] ? $guest['full_name'] : 'â€”')) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Recorded Visit</dt><dd style="font-size:.8rem;"><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $vehicle['visit_id'] ?>" style="color:var(--accent);font-weight:700;"><?= e($vehicle['visit_reference']) ?></a><br><?= formatDate($vehicle['visit_date']) ?></dd></div>
      </dl>
    </div>
    <?php else: ?>
    <div class="card-body" style="text-align:center;padding:26px 18px;">
      <div style="width:52px;height:52px;border-radius:8px;background:var(--bg);display:flex;align-items:center;justify-content:center;margin:0 auto 12px;color:var(--text-m);">
        <i data-lucide="car-off" style="width:24px;height:24px;"></i>
      </div>
      <div style="font-weight:700;color:var(--text);">No vehicle recorded</div>
      <div style="font-size:.82rem;color:var(--text-m);margin-top:4px;">This guest has no visit with vehicle details yet.</div>
    </div>
    <?php endif; ?>
  </div>
  </div>

  <div class="card">
    <div class="card-header"><i data-lucide="history" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit History</div>
    <div class="card-body p-0"><div class="table-responsive">
      <table class="data-table">
        <thead><tr><th>Reference</th><th>Date</th><th>Offices</th><th>Type</th><th>Status</th><th></th></tr></thead>
        <tbody>
        <?php if (empty($visits)): ?><tr><td colspan="6"><div class="table-empty">No visit records.</div></td></tr>
        <?php else: foreach ($visits as $v): ?>
        <tr>
          <td><span class="ref-chip"><?= e($v['visit_reference']) ?></span></td>
          <td style="font-size:.83rem;"><?= formatDate($v['visit_date']) ?></td>
          <td style="font-size:.8rem;max-width:200px;"><?= e($v['offices'] ?? '—') ?></td>
          <td><span class="badge <?= $v['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= $v['registration_type']==='walk_in'?'Walk-in':'Pre-Reg' ?></span></td>
          <td><?php $sc=match($v['overall_status']){'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary',default=>'badge-secondary'};?><span class="badge <?= $sc ?>"><?= statusLabel($v['overall_status']) ?></span></td>
          <td><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i></a></td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div></div>
  </div>
</div>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

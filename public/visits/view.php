<?php
/**
 * visits/view.php — Visit Detail View
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits_module.php';
requireLogin();
$pageTitle = 'Visit Details'; $db = getDB();
$visitId = (int)($_GET['id'] ?? 0);
if (!$visitId) { setFlash('error','Invalid visit ID.'); redirect(getDashboardUrl()); }

$stmt = $db->prepare("SELECT gv.*, g.full_name AS guest_name, g.contact_number, g.email, g.organization, g.id_type, g.is_restricted, u.full_name AS guard_name FROM guest_visits gv JOIN guests g ON gv.guest_id=g.guest_id LEFT JOIN users u ON gv.processed_by_guard_id=u.user_id WHERE gv.visit_id=:vid");
$stmt->execute([':vid'=>$visitId]); $visit = $stmt->fetch();
if (!$visit) { setFlash('error','Visit not found.'); redirect(getDashboardUrl()); }

if (isOfficeStaff()) {
    $chk = $db->prepare("SELECT 1 FROM visit_destinations WHERE visit_id=:vid AND office_id=:oid");
    $chk->execute([':vid'=>$visitId,':oid'=>currentOfficeId()]);
    if (!$chk->fetchColumn()) { setFlash('error','No permission.'); redirect(getDashboardUrl()); }
}

$destStmt = $db->prepare("SELECT vd.*, o.office_name, u.full_name AS received_by_name FROM visit_destinations vd JOIN offices o ON vd.office_id=o.office_id LEFT JOIN users u ON vd.received_by_user_id=u.user_id WHERE vd.visit_id=:vid ORDER BY vd.sequence_no");
$destStmt->execute([':vid'=>$visitId]); $destinations = $destStmt->fetchAll();

$vehStmt = $db->prepare("SELECT * FROM vehicle_entries WHERE visit_id=:vid");
$vehStmt->execute([':vid'=>$visitId]); $vehicle = $vehStmt->fetch();

$logStmt = $db->prepare("SELECT al.*, u.full_name AS actor_name FROM activity_logs al LEFT JOIN users u ON al.performed_by_user_id=u.user_id WHERE al.visit_id=:vid ORDER BY al.logged_at ASC");
$logStmt->execute([':vid'=>$visitId]); $logs = $logStmt->fetchAll();

$sc = match($visit['overall_status']) { 'pending'=>'badge-warning','checked_in'=>'badge-success','checked_out'=>'badge-secondary','cancelled'=>'badge-danger','overstayed'=>'badge-danger',default=>'badge-secondary' };

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Visit Details</div>
    <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li><?= e($visit['visit_reference']) ?></li></ul>
  </div>
  <div class="page-actions">
    <?php if ($visit['overall_status']==='pending' && isAdminOrGuard()): ?>
    <a href="<?= APP_URL ?>/public/visits/checkin.php?id=<?= $visitId ?>" class="btn btn-success"><i data-lucide="log-in"></i> Check In</a>
    <?php endif; ?>
    <?php if ($visit['overall_status']==='checked_in' && isAdminOrGuard()): ?>
    <a href="<?= APP_URL ?>/public/visits/checkout.php?id=<?= $visitId ?>" class="btn" style="background:var(--warning);color:#1a2744;font-weight:700;"><i data-lucide="log-out"></i> Check Out</a>
    <?php endif; ?>
  </div>
</div>

<?php if ($visit['is_restricted']): ?>
<div class="info-box danger" style="margin-bottom:20px;"><i data-lucide="shield-alert"></i><div><strong>RESTRICTED GUEST:</strong> This guest has been flagged. Do not allow entry without admin clearance.</div></div>
<?php endif; ?>

<div style="display:grid;grid-template-columns:400px 1fr;gap:20px;">
  <!-- Left -->
  <div>
    <!-- Reference & Status -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-body" style="text-align:center;padding:24px;">
        <div class="ref-chip" style="font-size:1.05rem;padding:8px 20px;display:inline-block;margin-bottom:10px;"><?= e($visit['visit_reference']) ?></div>
        <div style="margin-top:8px;display:flex;align-items:center;justify-content:center;gap:8px;flex-wrap:wrap;">
          <span class="badge <?= $sc ?>" style="font-size:.8rem;padding:5px 14px;"><?= statusLabel($visit['overall_status']) ?></span>
          <span class="badge <?= $visit['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= statusLabel($visit['registration_type']) ?></span>
          <?php if ($visit['has_vehicle']): ?><span class="badge badge-info"><i data-lucide="car" style="width:11px;height:11px;"></i> Vehicle</span><?php endif; ?>
        </div>
      </div>
    </div>

    <!-- Guest Info -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest Information</div>
      <div class="card-body" style="padding:0;">
        <dl style="margin:0;">
          <div class="detail-row" style="padding:10px 18px;"><dt>Full Name</dt><dd style="font-weight:700;"><?= e($visit['guest_name']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Contact</dt><dd><?= e($visit['contact_number'] ?? '—') ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Email</dt><dd><?= e($visit['email'] ?? '—') ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Organization</dt><dd><?= e($visit['organization'] ?? '—') ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>ID Type</dt><dd><?= e($visit['id_type'] ?? '—') ?></dd></div>
        </dl>
      </div>
    </div>

    <!-- Timing -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="clock" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit Timing</div>
      <div class="card-body" style="padding:0;">
        <dl style="margin:0;">
          <div class="detail-row" style="padding:10px 18px;"><dt>Visit Date</dt><dd><?= formatDate($visit['visit_date']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Expected In</dt><dd><?= formatTime($visit['expected_time_in']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Expected Out</dt><dd><?= formatTime($visit['expected_time_out']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Checked In</dt><dd style="color:var(--success);font-weight:600;"><?= formatDateTime($visit['actual_check_in']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Checked Out</dt><dd style="color:var(--danger);font-weight:600;"><?= formatDateTime($visit['actual_check_out']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Processed By</dt><dd><?= e($visit['guard_name'] ?? '—') ?></dd></div>
        </dl>
      </div>
    </div>

    <!-- Purpose -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="message-square" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Purpose</div>
      <div class="card-body">
        <p style="margin:0;font-size:.9rem;"><?= e($visit['purpose_of_visit']) ?></p>
        <?php if ($visit['notes']): ?>
        <div style="margin-top:10px;padding-top:10px;border-top:1px solid var(--border);font-size:.83rem;color:var(--text-s);"><strong>Notes:</strong> <?= e($visit['notes']) ?></div>
        <?php endif; ?>
      </div>
    </div>

    <!-- Vehicle -->
    <?php if ($vehicle): ?>
    <div class="card">
      <div class="card-header"><i data-lucide="car" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Vehicle</div>
      <div class="card-body" style="padding:0;">
        <dl style="margin:0;">
          <div class="detail-row" style="padding:10px 18px;"><dt>Type</dt><dd><?= e(ucfirst($vehicle['vehicle_type'])) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Plate</dt><dd style="font-weight:700;"><?= e($vehicle['plate_number']) ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Color</dt><dd><?= e($vehicle['vehicle_color'] ?? '—') ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Model</dt><dd><?= e($vehicle['vehicle_model'] ?? '—') ?></dd></div>
          <div class="detail-row" style="padding:10px 18px;"><dt>Driver</dt><dd><?= e($vehicle['driver_name'] ?? '—') ?></dd></div>
        </dl>
      </div>
    </div>
    <?php endif; ?>
  </div>

  <!-- Right -->
  <div>
    <!-- Destinations -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header" style="justify-content:space-between;">
        <span><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Office Destinations</span>
      </div>
      <div class="card-body p-0"><div class="table-responsive">
        <table class="data-table">
          <thead><tr><th>#</th><th>Office</th><th>Status</th><th>Arrived</th><th>Completed</th><th>Flags</th></tr></thead>
          <tbody>
          <?php foreach ($destinations as $d): ?>
          <tr>
            <td><span class="badge badge-secondary"><?= $d['sequence_no'] ?></span></td>
            <td style="font-weight:600;"><?= e($d['office_name']) ?></td>
            <td><?php $dsc = match($d['destination_status']){'pending'=>'badge-warning','arrived'=>'badge-info','in_service'=>'badge-blue','completed'=>'badge-success','skipped'=>'badge-secondary',default=>'badge-secondary'};?>
              <span class="badge <?= $dsc ?>"><?= statusLabel($d['destination_status']) ?></span></td>
            <td style="font-size:.8rem;"><?= formatDateTime($d['arrival_time']) ?></td>
            <td style="font-size:.8rem;"><?= formatDateTime($d['completed_time']) ?></td>
            <td>
              <?php if ($d['is_primary']): ?><span class="badge badge-primary" style="font-size:.62rem;">Primary</span><?php endif; ?>
              <?php if ($d['is_unplanned']): ?><span class="badge badge-warning" style="font-size:.62rem;">Unplanned</span><?php endif; ?>
            </td>
          </tr>
          <?php endforeach; ?>
          </tbody>
        </table>
      </div></div>
    </div>

    <!-- Activity Log -->
    <div class="card">
      <div class="card-header"><i data-lucide="activity" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Activity Log</div>
      <div class="card-body p-0">
        <?php if (empty($logs)): ?>
        <div class="table-empty">No activity logged.</div>
        <?php else: ?>
        <div class="table-responsive">
          <table class="data-table">
            <thead><tr><th>Time</th><th>Action</th><th>By</th><th>Details</th></tr></thead>
            <tbody>
            <?php foreach ($logs as $log): ?>
            <tr>
              <td style="font-size:.78rem;color:var(--text-m);white-space:nowrap;"><?= formatDateTime($log['logged_at']) ?></td>
              <td><span class="badge badge-secondary"><?= e(str_replace('_',' ',$log['action_type'])) ?></span></td>
              <td style="font-size:.83rem;"><?= e($log['actor_name'] ?? '—') ?></td>
              <td style="font-size:.8rem;color:var(--text-s);"><?= e($log['description'] ?? '') ?></td>
            </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

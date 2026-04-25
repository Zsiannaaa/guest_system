<?php
/**
 * office/handle.php — Handle a guest at this office
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/destinations_module.php';
requireRole([ROLE_OFFICE_STAFF, ROLE_ADMIN]);
$pageTitle = 'Handle Visit'; $db = getDB();
$destId = (int)($_GET['dest_id'] ?? $_POST['dest_id'] ?? 0);
if (!$destId) { setFlash('error','Invalid destination.'); redirect(getDashboardUrl()); }

$stmt = $db->prepare("SELECT vd.*, o.office_name, o.requires_arrival_confirmation, gv.visit_reference, gv.purpose_of_visit, gv.overall_status, gv.actual_check_in, gv.visit_id, g.full_name AS guest_name, g.contact_number, g.organization FROM visit_destinations vd JOIN offices o ON vd.office_id=o.office_id JOIN guest_visits gv ON vd.visit_id=gv.visit_id JOIN guests g ON gv.guest_id=g.guest_id WHERE vd.destination_id=:did");
$stmt->execute([':did'=>$destId]); $dest = $stmt->fetch();
if (!$dest) { setFlash('error','Not found.'); redirect(getDashboardUrl()); }
if (isOfficeStaff() && $dest['office_id']!==currentOfficeId()) { setFlash('error','Not your office.'); redirect(getDashboardUrl()); }

if (isPost()) {
    verifyCsrf(APP_URL . '/public/office/handle.php?dest_id=' . $destId);
    $action = $_POST['action'] ?? '';
    if ($action==='confirm_arrival') {
        $db->prepare("UPDATE visit_destinations SET destination_status='arrived',received_by_user_id=:uid,arrival_time=NOW() WHERE destination_id=:did AND destination_status='pending'")->execute([':uid'=>currentUserId(),':did'=>$destId]);
        logActivity($dest['visit_id'],'destination_confirmed',currentUserId(),$dest['office_id'],"{$dest['office_name']} confirmed {$dest['guest_name']}");
        setFlash('success',"<strong>{$dest['guest_name']}</strong> arrival confirmed."); redirect(APP_URL.'/public/visits/view.php?id='.$dest['visit_id']);
    }
    if ($action==='start_service') {
        $db->prepare("UPDATE visit_destinations SET destination_status='in_service' WHERE destination_id=:did AND destination_status IN('pending','arrived')")->execute([':did'=>$destId]);
        logActivity($dest['visit_id'],'destination_confirmed',currentUserId(),$dest['office_id'],"{$dest['office_name']} started serving {$dest['guest_name']}");
        setFlash('success',"Now serving <strong>{$dest['guest_name']}</strong>."); redirect(APP_URL.'/public/visits/view.php?id='.$dest['visit_id']);
    }
    if ($action==='complete') {
        $notes = trim($_POST['completion_notes']??'');
        $db->prepare("UPDATE visit_destinations SET destination_status='completed',completed_time=NOW(),notes=COALESCE(NULLIF(:notes,''),notes) WHERE destination_id=:did")->execute([':notes'=>$notes,':did'=>$destId]);
        logActivity($dest['visit_id'],'destination_completed',currentUserId(),$dest['office_id'],"{$dest['office_name']} completed {$dest['guest_name']}");
        setFlash('success',"<strong>{$dest['guest_name']}</strong> completed at {$dest['office_name']}."); redirect(APP_URL.'/public/visits/view.php?id='.$dest['visit_id']);
    }
}
$status = $dest['destination_status'];
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Handle Guest Visit</div>
  <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li><a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $dest['visit_id'] ?>">Visit</a></li><li>Handle</li></ul></div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">
  <div class="card">
    <div class="card-header"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest & Visit Info</div>
    <div class="card-body" style="padding:0;">
      <dl style="margin:0;">
        <div class="detail-row" style="padding:10px 18px;"><dt>Guest</dt><dd style="font-weight:700;"><?= e($dest['guest_name']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Organization</dt><dd><?= e($dest['organization'] ?? '—') ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Reference</dt><dd><span class="ref-chip"><?= e($dest['visit_reference']) ?></span></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Purpose</dt><dd><?= e($dest['purpose_of_visit']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Checked In</dt><dd><?= formatDateTime($dest['actual_check_in']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Office</dt><dd style="font-weight:700;color:var(--primary);"><?= e($dest['office_name']) ?></dd></div>
        <div class="detail-row" style="padding:10px 18px;"><dt>Status</dt><dd>
          <?php $dsc = match($status){'pending'=>'badge-warning','arrived'=>'badge-info','in_service'=>'badge-blue','completed'=>'badge-success',default=>'badge-secondary'};?>
          <span class="badge <?= $dsc ?>" style="font-size:.8rem;padding:4px 12px;"><?= statusLabel($status) ?></span>
        </dd></div>
        <?php if ($dest['is_unplanned']): ?><div class="detail-row" style="padding:10px 18px;"><dt>Type</dt><dd><span class="badge badge-warning">Unplanned Transfer</span></dd></div><?php endif; ?>
        <?php if ($dest['arrival_time']): ?><div class="detail-row" style="padding:10px 18px;"><dt>Arrived</dt><dd><?= formatDateTime($dest['arrival_time']) ?></dd></div><?php endif; ?>
      </dl>
    </div>
  </div>

  <div class="card">
    <div class="card-header"><i data-lucide="settings" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Actions</div>
    <div class="card-body">
      <?php if ($status==='completed' || $status==='cancelled'): ?>
      <div class="info-box info"><i data-lucide="info"></i><div>This destination is <strong><?= statusLabel($status) ?></strong>. No further actions.</div></div>
      <?php else: ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="dest_id" value="<?= $destId ?>">

        <?php if ($dest['requires_arrival_confirmation'] && $status==='pending'): ?>
        <button type="submit" name="action" value="confirm_arrival" class="btn btn-accent w-100" style="justify-content:center;padding:12px;margin-bottom:10px;">
          <i data-lucide="user-check"></i> Confirm Guest Arrived
        </button>
        <?php endif; ?>

        <?php if (in_array($status, ['pending','arrived'])): ?>
        <button type="submit" name="action" value="start_service" class="btn btn-primary w-100" style="justify-content:center;padding:12px;margin-bottom:10px;">
          <i data-lucide="play"></i> Start Serving Guest
        </button>
        <?php endif; ?>

        <?php if (in_array($status, ['pending','arrived','in_service'])): ?>
        <div class="form-group" style="margin-top:12px;">
          <label class="form-label">Completion Notes (optional)</label>
          <textarea name="completion_notes" class="form-control" rows="2" placeholder="e.g. Documents submitted"></textarea>
        </div>
        <button type="submit" name="action" value="complete" class="btn btn-success w-100" style="justify-content:center;padding:12px;">
          <i data-lucide="check-circle"></i> Mark as Completed
        </button>
        <?php endif; ?>
      </form>
      <?php endif; ?>

    </div>
  </div>
</div>
<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

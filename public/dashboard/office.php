<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/dashboard/dashboard_module.php';
requireRole(ROLE_OFFICE_STAFF);
$pageTitle = 'Office Dashboard';
$db = getDB();
$oid = currentOfficeId();
$stats = getOfficeDashboardStats($db, $oid);
$incoming = $stats['incoming'];
$serving = $stats['serving'];
$doneToday = $stats['completed'];
$incomingList = getOfficeIncomingList($db, $oid);
$servingList = getOfficeServingList($db, $oid);

include __DIR__ . '/../../includes/header.php';
?>

<div style="display:flex;align-items:flex-start;justify-content:space-between;margin-bottom:22px;flex-wrap:wrap;gap:12px;">
  <div>
    <div class="page-title">Office Dashboard</div>
    <div class="page-subtitle">
      <?= htmlspecialchars($_SESSION['office_name'] ?? 'Your Office') ?> — <?= date('F j, Y') ?>
    </div>
  </div>
  <div style="display:flex;gap:10px;">
    <a href="<?= APP_URL ?>/public/office/lookup.php" class="btn btn-primary"><i data-lucide="scan-search"></i> Receive Visitor</a>
  </div>
</div>

<!-- Stats -->
<div class="stat-grid" style="grid-template-columns:repeat(3,1fr);margin-bottom:22px;">
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $incoming ?></div><div class="stat-label">Incoming (Pending)</div></div>
      <div class="stat-icon-wrap orange"><i data-lucide="arrow-down-circle"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Awaiting your attention</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $serving ?></div><div class="stat-label">Arrived Guests</div></div>
      <div class="stat-icon-wrap green"><i data-lucide="user-check"></i></div>
    </div>
    <div class="stat-trend live"><span class="live-dot"></span> Live</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $doneToday ?></div><div class="stat-label">Completed Today</div></div>
      <div class="stat-icon-wrap blue"><i data-lucide="check-circle"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Today's served guests</div>
  </div>
</div>

<div style="display:grid;grid-template-columns:1fr 1fr;gap:20px;">

  <!-- Incoming -->
  <div class="card">
    <div class="card-header">
      Incoming Guests
      <a href="<?= APP_URL ?>/public/office/incoming.php" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Purpose</th><th>Check-In</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($incomingList)): ?>
        <tr><td colspan="4" style="text-align:center;padding:28px;color:var(--text-m);">No incoming guests.</td></tr>
        <?php else: foreach($incomingList as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar"><?= strtoupper(substr($v['guest_name'],0,1)) ?></div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref"><?= htmlspecialchars($v['visit_reference']) ?>
                  <?php if($v['is_unplanned']): ?>
                  <span style="color:var(--orange);font-weight:700;"> · Unplanned</span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
          </td>
          <td style="font-size:.82rem;color:var(--text-s);"><?= htmlspecialchars(substr($v['purpose_of_visit'],0,30)) ?>…</td>
          <td style="font-size:.82rem;"><?= formatTime($v['actual_check_in']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/public/office/handle.php?dest_id=<?= $v['destination_id'] ?>" class="btn btn-sm btn-primary">Handle</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

  <!-- Arrived Guests -->
  <div class="card">
    <div class="card-header">
      Arrived Guests
      <a href="<?= APP_URL ?>/public/office/current.php" class="view-all">View all</a>
    </div>
    <div class="card-body p-0">
      <table class="data-table">
        <thead><tr><th>Guest</th><th>Status</th><th>Arrived</th><th></th></tr></thead>
        <tbody>
        <?php if(empty($servingList)): ?>
        <tr><td colspan="4" style="text-align:center;padding:28px;color:var(--text-m);">No arrived guests.</td></tr>
        <?php else: foreach($servingList as $v): ?>
        <tr>
          <td>
            <div class="guest-cell">
              <div class="guest-avatar" style="background:var(--success-l);color:var(--success);">
                <?= strtoupper(substr($v['guest_name'],0,1)) ?>
              </div>
              <div>
                <div class="guest-name"><?= htmlspecialchars($v['guest_name']) ?></div>
                <div class="guest-ref"><?= htmlspecialchars($v['visit_reference']) ?></div>
              </div>
            </div>
          </td>
          <td>
            <span class="badge <?= $v['destination_status']==='in_service' ? 'badge-success' : 'badge-info' ?>">
              <?= $v['destination_status']==='in_service' ? 'In Service' : 'Arrived' ?>
            </span>
          </td>
          <td style="font-size:.82rem;"><?= formatTime($v['arrival_time']) ?></td>
          <td>
            <a href="<?= APP_URL ?>/public/office/handle.php?dest_id=<?= $v['destination_id'] ?>" class="btn btn-sm btn-outline">Complete</a>
          </td>
        </tr>
        <?php endforeach; endif; ?>
        </tbody>
      </table>
    </div>
  </div>

</div>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

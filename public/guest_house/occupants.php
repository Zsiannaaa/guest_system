<?php
/**
 * public/guest_house/occupants.php — Current guest-house occupants
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guest_house/gh_bookings_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Current Occupants';
$db = getDB();

$occupants = ghCurrentOccupants($db);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Current Occupants</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Occupants</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guest_house/checkout.php" class="btn btn-outline"><i data-lucide="log-out"></i> Check-out Queue</a>
  </div>
</div>

<div class="card">
  <div class="card-header"><?= count($occupants) ?> guest(s) currently in the guest house</div>
  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>Guest</th><th>Room</th><th>Check-in</th><th>Planned Out</th><th>Nights so far</th><th>Sponsor</th><th></th>
      </tr></thead>
      <tbody>
      <?php if (empty($occupants)): ?>
      <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-m);">No occupants right now.</td></tr>
      <?php else: foreach ($occupants as $b): ?>
      <tr>
        <td>
          <div class="guest-cell">
            <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'],0,1)) ?></div>
            <div>
              <div class="guest-name"><?= e($b['guest_name']) ?></div>
              <div class="guest-ref">REF: <?= e($b['booking_reference']) ?></div>
              <?php if ($b['organization']): ?>
              <div style="font-size:.75rem;color:var(--text-m);"><?= e($b['organization']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <strong><?= e($b['room_number'] ?? '—') ?></strong>
          <?php if ($b['type_name']): ?>
          <div style="font-size:.75rem;color:var(--text-m);"><?= e($b['type_name']) ?><?= $b['floor'] ? ' · Floor ' . e($b['floor']) : '' ?></div>
          <?php endif; ?>
        </td>
        <td style="font-size:.85rem;"><?= formatDateTime($b['actual_check_in']) ?></td>
        <td style="font-size:.85rem;"><?= formatDate($b['check_out_date']) ?></td>
        <td><span class="badge badge-blue"><?= (int)$b['nights_so_far'] ?> / <?= (int)$b['nights_planned'] ?></span></td>
        <td style="font-size:.83rem;"><?= e($b['sponsor_office_name'] ?? $b['external_sponsor'] ?? '—') ?></td>
        <td>
          <a href="booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="eye"></i> View</a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

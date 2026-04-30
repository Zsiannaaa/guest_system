<?php
/**
 * public/guest_house/bookings.php — Booking list
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/gh_bookings_module.php';

requireRole([ROLE_ADMIN, ROLE_GUEST_HOUSE_STAFF]);
$pageTitle = 'Guest House — Bookings';
$db = getDB();

$filters = [
    'status'    => inputStr('status','GET'),
    'q'         => inputStr('q','GET'),
    'from'      => inputStr('from','GET'),
    'to'        => inputStr('to','GET'),
    'office_id' => inputInt('office_id','GET'),
];
$bookings = ghListBookings($db, $filters);
$offices  = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Guest House Bookings</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/guest_house.php">Guest House</a></li>
      <li>Bookings</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guest_house/booking_create.php" class="btn btn-primary">
      <i data-lucide="calendar-plus"></i> New Booking
    </a>
  </div>
</div>

<div class="card">
  <form method="GET" class="table-toolbar" style="flex-wrap:wrap;">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" name="q" value="<?= e($filters['q']) ?>" placeholder="Search guest or reference…"></div>
    <select name="status" class="table-filter">
      <option value="">All Status</option>
      <?php foreach (['reserved','checked_in','occupied','checked_out','cancelled','no_show'] as $s): ?>
      <option value="<?= $s ?>" <?= ($filters['status']===$s)?'selected':'' ?>><?= statusLabel($s) ?></option>
      <?php endforeach; ?>
    </select>
    <select name="office_id" class="table-filter">
      <option value="">All Sponsors</option>
      <?php foreach ($offices as $o): ?>
      <option value="<?= (int)$o['office_id'] ?>" <?= ((int)$filters['office_id']===(int)$o['office_id'])?'selected':'' ?>><?= e($o['office_name']) ?></option>
      <?php endforeach; ?>
    </select>
    <input type="date" name="from" value="<?= e($filters['from']) ?>" class="table-filter" title="From">
    <input type="date" name="to"   value="<?= e($filters['to']) ?>"   class="table-filter" title="To">
    <button class="btn btn-outline"><i data-lucide="filter"></i> Filter</button>
    <a class="btn btn-outline" href="<?= APP_URL ?>/public/guest_house/bookings.php"><i data-lucide="x"></i> Reset</a>
    <div class="table-count"><?= count($bookings) ?> bookings</div>
  </form>

  <div class="table-responsive">
    <table class="data-table">
      <thead><tr>
        <th>Reference</th><th>Guest</th><th>Room</th><th>Dates</th><th>Sponsor</th><th>Status</th><th>Actions</th>
      </tr></thead>
      <tbody>
      <?php if (empty($bookings)): ?>
      <tr><td colspan="7" style="text-align:center;padding:32px;color:var(--text-m);">No bookings found.</td></tr>
      <?php else: foreach ($bookings as $b): ?>
      <tr>
        <td><code style="font-size:.8rem;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($b['booking_reference']) ?></code></td>
        <td>
          <div class="guest-cell">
            <div class="guest-avatar"><?= strtoupper(substr($b['guest_name'],0,1)) ?></div>
            <div>
              <div class="guest-name"><?= e($b['guest_name']) ?></div>
              <?php if (!empty($b['organization'])): ?>
              <div class="guest-ref"><?= e($b['organization']) ?></div>
              <?php endif; ?>
            </div>
          </div>
        </td>
        <td>
          <?php if (!empty($b['room_number'])): ?>
            <strong><?= e($b['room_number']) ?></strong>
            <div style="font-size:.75rem;color:var(--text-m);"><?= e($b['type_name'] ?? '') ?></div>
          <?php else: ?><span style="color:var(--text-m);">Unassigned</span><?php endif; ?>
        </td>
        <td style="font-size:.83rem;">
          <?= formatDate($b['check_in_date']) ?><br>
          <span style="color:var(--text-m);">to <?= formatDate($b['check_out_date']) ?></span>
        </td>
        <td style="font-size:.83rem;">
          <?= e($b['sponsor_office_name'] ?? $b['external_sponsor'] ?? '—') ?>
        </td>
        <td>
          <span class="badge <?= match($b['status']) {
              'reserved'    => 'badge-warning',
              'checked_in','occupied' => 'badge-success',
              'checked_out' => 'badge-secondary',
              'cancelled'   => 'badge-danger',
              default       => 'badge-secondary',
          } ?>"><?= statusLabel($b['status']) ?></span>
        </td>
        <td>
          <a href="booking_view.php?id=<?= (int)$b['booking_id'] ?>" class="btn-tbl btn-tbl-outline">
            <i data-lucide="eye"></i> View
          </a>
        </td>
      </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests/guests_module.php';
requireLogin();
$pageTitle = 'Guest Directory';
$db = getDB();

$stmt = $db->prepare("
    SELECT g.*,
           COUNT(gv.visit_id) AS total_visits,
           MAX(gv.visit_date) AS last_visit
    FROM guests g
    LEFT JOIN guest_visits gv ON g.guest_id=gv.guest_id
    GROUP BY g.guest_id
    ORDER BY g.full_name
");
$stmt->execute();
$guests = $stmt->fetchAll();
$total = count($guests);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Guest Directory</div>
    <ul class="breadcrumb">
      <li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li>
      <li>Guests</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/guests/export.php" class="btn btn-outline">
      <i data-lucide="download"></i> Export Guests
    </a>
    <?php if (isAdminOrGuard()): ?>
    <a href="<?= APP_URL ?>/public/visits/walkin.php" class="btn btn-primary">
      <i data-lucide="user-plus"></i> Register Walk-In
    </a>
    <?php endif; ?>
    <?php if (isAdmin()): ?>
    <a href="<?= APP_URL ?>/public/guests/restricted.php" class="btn btn-outline" style="color:var(--danger);border-color:var(--danger);">
      <i data-lucide="ban"></i> Restricted List
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search">
      <i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search by name, contact, or organization…" autocomplete="off">
    </div>
    <select class="table-filter" id="statusFilter">
      <option value="">All Guests</option>
      <option value="active">Active</option>
      <option value="restricted">Restricted</option>
    </select>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?> guests</div>
  </div>

  <div class="card-header">
    <span><i data-lucide="contact" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>All Registered Guests</span>
  </div>

  <div class="table-responsive">
    <table class="data-table" id="guestsTable">
      <thead>
        <tr>
          <th>Guest Name</th>
          <th>Contact</th>
          <th>Organization</th>
          <th>ID Type</th>
          <th>Total Visits</th>
          <th>Last Visit</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($guests)): ?>
      <tr><td colspan="8"><div class="table-empty">No guest records found.</div></td></tr>
      <?php else: ?>
      <?php foreach ($guests as $g): ?>
      <tr data-status="<?= $g['is_restricted'] ? 'restricted' : 'active' ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar" style="<?= $g['is_restricted'] ? 'background:var(--danger-l);color:var(--danger)' : '' ?>">
              <?= strtoupper(substr($g['full_name'],0,1)) ?>
            </div>
            <div>
              <div class="guest-name">
                <?php if ($g['is_restricted']): ?>
                <i data-lucide="shield-alert" style="width:13px;height:13px;color:var(--danger);vertical-align:middle;margin-right:3px;"></i>
                <?php endif; ?>
                <?= htmlspecialchars($g['full_name']) ?>
              </div>
            </div>
          </div>
        </td>
        <td style="font-size:.85rem;"><?= htmlspecialchars($g['contact_number'] ?? '—') ?></td>
        <td style="font-size:.83rem;color:var(--text-s);"><?= htmlspecialchars($g['organization'] ?? '—') ?></td>
        <td style="font-size:.83rem;"><?= htmlspecialchars($g['id_type'] ?? '—') ?></td>
        <td>
          <span class="badge badge-blue"><?= (int)$g['total_visits'] ?> visits</span>
        </td>
        <td style="font-size:.83rem;color:var(--text-s);"><?= formatDate($g['last_visit']) ?></td>
        <td>
          <?php if ($g['is_restricted']): ?>
          <span class="badge badge-danger">Restricted</span>
          <?php else: ?>
          <span class="badge badge-success">Active</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="<?= APP_URL ?>/public/guests/view.php?id=<?= $g['guest_id'] ?>" class="btn-tbl btn-tbl-outline">
              <i data-lucide="eye"></i> View
            </a>
            <?php if (isAdminOrGuard()): ?>
            <a href="<?= APP_URL ?>/public/guests/edit.php?id=<?= $g['guest_id'] ?>" class="btn-tbl btn-tbl-outline">
              <i data-lucide="pencil"></i> Edit
            </a>
            <?php endif; ?>
            <?php if (isAdmin() && !$g['is_restricted']): ?>
            <a href="<?= APP_URL ?>/public/guests/restrict.php?id=<?= $g['guest_id'] ?>" class="btn-tbl btn-tbl-danger">
              <i data-lucide="ban"></i> Restrict
            </a>
            <?php elseif (isAdmin() && $g['is_restricted']): ?>
            <a href="<?= APP_URL ?>/public/guests/lift_restriction.php?id=<?= $g['guest_id'] ?>"
               class="btn-tbl btn-tbl-success"
               data-confirm="Lift restriction for <?= htmlspecialchars($g['full_name']) ?>?">
              <i data-lucide="shield-check"></i> Lift
            </a>
            <?php endif; ?>
            <?php if (isAdmin()): ?>
            <form method="POST" action="<?= APP_URL ?>/public/guests/delete.php" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="guest_id" value="<?= (int)$g['guest_id'] ?>">
              <input type="hidden" name="return_to" value="<?= APP_URL ?>/public/guests/list.php">
              <button type="submit" class="btn-tbl btn-tbl-danger"
                      data-confirm="Delete <?= e($g['full_name']) ?>? This only works when the guest has no visit or Guest House history.">
                <i data-lucide="trash-2"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      <?php endif; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const search = document.getElementById('searchInput');
const statF  = document.getElementById('statusFilter');
const tbl    = document.getElementById('guestsTable');
const cnt    = document.getElementById('visibleCount');
function filter() {
  const q = search.value.toLowerCase().trim(), s = statF.value;
  let n = 0;
  tbl.querySelectorAll('tbody tr[data-status]').forEach(row => {
    const ok = (!q || row.textContent.toLowerCase().includes(q)) && (!s || row.dataset.status===s);
    row.style.display = ok ? '' : 'none';
    if(ok) n++;
  });
  cnt.textContent = n;
}
let d; search.addEventListener('input', ()=>{ clearTimeout(d); d=setTimeout(filter,200); });
statF.addEventListener('change', filter);
lucide.createIcons();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

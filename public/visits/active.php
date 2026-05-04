<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Visit page/controller for active. It coordinates request data, visit module functions, and the shared layout.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * visits/active.php — Active Visitors (currently checked in)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole([ROLE_GUARD, ROLE_ADMIN]);
$pageTitle = 'Active Visitors';
$db = getDB();

// Study query: Prepared SQL: reads rows from guest_visits, guests, visit_destinations, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$stmt = $db->prepare("
    SELECT gv.visit_id, gv.visit_reference, gv.actual_check_in,
           gv.registration_type, gv.has_vehicle, gv.expected_time_out,
           g.full_name AS guest_name, g.organization, g.is_restricted,
           GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id = g.guest_id
    LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
    LEFT JOIN offices o ON vd.office_id = o.office_id
    WHERE gv.overall_status = 'checked_in'
    GROUP BY gv.visit_id
    ORDER BY gv.actual_check_in DESC
");
$stmt->execute();
$visitors = $stmt->fetchAll();
$total = count($visitors);

// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Active Visitors</div>
    <ul class="breadcrumb">
      <li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li>
      <li>Active Visitors</li>
    </ul>
  </div>
  <div class="page-actions">
    <?php if (isAdminOrGuard()): ?>
    <a href="<?= APP_URL ?>/public/visits/walkin.php" class="btn btn-primary">
      <i data-lucide="user-plus"></i> Register Walk-In
    </a>
    <a href="<?= APP_URL ?>/public/visits/checkout.php" class="btn btn-outline">
      <i data-lucide="log-out"></i> Check Out
    </a>
    <?php endif; ?>
  </div>
</div>

<div class="card">
  <!-- Toolbar -->
  <div class="table-toolbar">
    <div class="table-search">
      <i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search by name, reference, or office…" autocomplete="off">
    </div>
    <select class="table-filter" id="typeFilter">
      <option value="">All Types</option>
      <option value="walk_in">Walk-in</option>
      <option value="pre_registered">Pre-Registered</option>
    </select>
    <select class="table-filter" id="vehicleFilter">
      <option value="">All Visitors</option>
      <option value="1">With Vehicle</option>
      <option value="0">No Vehicle</option>
    </select>
    <div class="table-count">
      <span id="visibleCount"><?= $total ?></span> of <?= $total ?> visitors
    </div>
  </div>

  <!-- Card header -->
  <div class="card-header" style="background:#f0fdf4;">
    <span style="display:flex;align-items:center;gap:8px;">
      <span class="live-dot"></span>
      <strong><?= $total ?> <?= $total === 1 ? 'person' : 'people' ?></strong> currently inside campus
    </span>
    <span style="font-size:.8rem;color:var(--text-m);font-weight:400;">Live — auto-updates on search</span>
  </div>

  <!-- Table -->
  <div class="table-responsive">
    <table class="data-table" id="visitorsTable">
      <thead>
        <tr>
          <th>Guest</th>
          <th>Reference No.</th>
          <th>Destination(s)</th>
          <th>Type</th>
          <th>Check-In Time</th>
          <th>Vehicle</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($visitors)): ?>
      <tr>
        <td colspan="7">
          <div class="table-empty">
            <i data-lucide="users" style="width:40px;height:40px;margin:0 auto 12px;display:block;opacity:.3;"></i>
            No active visitors right now.
          </div>
        </td>
      </tr>
      <?php else: ?>
      <?php foreach ($visitors as $v): ?>
      <tr data-type="<?= htmlspecialchars($v['registration_type']) ?>"
          data-vehicle="<?= $v['has_vehicle'] ? '1' : '0' ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar" style="<?= $v['is_restricted'] ? 'background:var(--danger-l);color:var(--danger)' : '' ?>">
              <?= strtoupper(substr($v['guest_name'], 0, 1)) ?>
            </div>
            <div>
              <div class="guest-name">
                <?php if ($v['is_restricted']): ?>
                <i data-lucide="shield-alert" style="width:13px;height:13px;color:var(--danger);vertical-align:middle;margin-right:3px;"></i>
                <?php endif; ?>
                <?= htmlspecialchars($v['guest_name']) ?>
              </div>
              <div class="guest-ref"><?= htmlspecialchars($v['organization'] ?? '') ?></div>
            </div>
          </div>
        </td>
        <td>
          <span class="ref-chip"><?= htmlspecialchars($v['visit_reference']) ?></span>
        </td>
        <td style="max-width:200px;">
          <div style="font-size:.85rem;color:var(--text);"><?= htmlspecialchars($v['destinations'] ?? '—') ?></div>
        </td>
        <td>
          <span class="badge <?= $v['registration_type'] === 'walk_in' ? 'badge-warning' : 'badge-blue' ?>">
            <?= $v['registration_type'] === 'walk_in' ? 'Walk-in' : 'Pre-Reg' ?>
          </span>
        </td>
        <td style="white-space:nowrap;">
          <strong><?= formatTime($v['actual_check_in']) ?></strong>
          <div style="font-size:.72rem;color:var(--text-m);"><?= formatDate($v['actual_check_in']) ?></div>
        </td>
        <td>
          <?php if ($v['has_vehicle']): ?>
          <span class="badge badge-info"><i data-lucide="car" style="width:11px;height:11px;"></i> Yes</span>
          <?php else: ?>
          <span style="color:var(--text-m);font-size:.8rem;">—</span>
          <?php endif; ?>
        </td>
        <td>
          <div class="tbl-actions">
            <a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-outline">
              <i data-lucide="eye"></i> View
            </a>
            <a href="<?= APP_URL ?>/public/visits/receipt.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-outline">
              <i data-lucide="printer"></i> Slip
            </a>
            <?php if (isAdminOrGuard()): ?>
            <a href="<?= APP_URL ?>/public/visits/checkout.php?id=<?= $v['visit_id'] ?>" class="btn-tbl btn-tbl-warn">
              <i data-lucide="log-out"></i> Check Out
            </a>
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
// Auto-search + filter
const searchInput   = document.getElementById('searchInput');
const typeFilter    = document.getElementById('typeFilter');
const vehicleFilter = document.getElementById('vehicleFilter');
const table         = document.getElementById('visitorsTable');
const countEl       = document.getElementById('visibleCount');

function applyFilters() {
  const q   = searchInput.value.toLowerCase().trim();
  const typ = typeFilter.value;
  const veh = vehicleFilter.value;
  let visible = 0;

  table.querySelectorAll('tbody tr[data-type]').forEach(row => {
    const text    = row.textContent.toLowerCase();
    const rowType = row.dataset.type;
    const rowVeh  = row.dataset.vehicle;

    const matchQ   = !q   || text.includes(q);
    const matchTyp = !typ || rowType === typ;
    const matchVeh = !veh || rowVeh  === veh;

    if (matchQ && matchTyp && matchVeh) {
      row.style.display = '';
      visible++;
    } else {
      row.style.display = 'none';
    }
  });

  countEl.textContent = visible;

  // Show/hide no-results row
  let noRow = table.querySelector('.no-results-row');
  if (visible === 0) {
    if (!noRow) {
      noRow = document.createElement('tr');
      noRow.className = 'no-results-row';
      noRow.innerHTML = '<td colspan="7"><div class="table-empty">No visitors match your search.</div></td>';
      table.querySelector('tbody').appendChild(noRow);
    }
    noRow.style.display = '';
  } else if (noRow) {
    noRow.style.display = 'none';
  }
}

// Live on every keystroke (debounced 200ms)
let debounce;
searchInput.addEventListener('input', () => { clearTimeout(debounce); debounce = setTimeout(applyFilters, 200); });
typeFilter.addEventListener('change', applyFilters);
vehicleFilter.addEventListener('change', applyFilters);

lucide.createIcons();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

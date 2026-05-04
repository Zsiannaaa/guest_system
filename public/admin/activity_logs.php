<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Admin page/controller for activity logs. It manages users, offices, or audit information.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * admin/activity_logs.php - Audit Logs
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/admin/users_module.php';
require_once __DIR__ . '/../../modules/admin/offices_module.php';
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole(ROLE_ADMIN);

$pageTitle = 'Activity Logs';
$db = getDB();

$q = trim($_GET['q'] ?? '');
$action = trim($_GET['action'] ?? '');
$userId = (int)($_GET['user_id'] ?? 0);
$dateFrom = trim($_GET['date_from'] ?? '');
$dateTo = trim($_GET['date_to'] ?? '');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 50;
$offset = ($page - 1) * $perPage;

if ($dateFrom && !strtotime($dateFrom)) $dateFrom = '';
if ($dateTo && !strtotime($dateTo)) $dateTo = '';
if ($dateFrom && $dateTo && $dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

$where = [];
$params = [];

if ($q !== '') {
    $where[] = "(al.description LIKE :q OR al.action_type LIKE :q OR al.ip_address LIKE :q OR u.full_name LIKE :q OR gv.visit_reference LIKE :q)";
    $params[':q'] = '%' . $q . '%';
}
if ($action !== '') {
    $where[] = "al.action_type = :action";
    $params[':action'] = $action;
}
if ($userId > 0) {
    $where[] = "al.performed_by_user_id = :user_id";
    $params[':user_id'] = $userId;
}
if ($dateFrom !== '') {
    $where[] = "DATE(al.logged_at) >= :date_from";
    $params[':date_from'] = $dateFrom;
}
if ($dateTo !== '') {
    $where[] = "DATE(al.logged_at) <= :date_to";
    $params[':date_to'] = $dateTo;
}

$whereSql = $where ? 'WHERE ' . implode(' AND ', $where) : '';

// Study query: SQL query: reads rows from activity_logs for lookup, validation, or display.
$actions = $db->query("SELECT DISTINCT action_type FROM activity_logs ORDER BY action_type")->fetchAll(PDO::FETCH_COLUMN);
$users = $db->query("
    SELECT DISTINCT u.user_id, u.full_name, u.role
    FROM activity_logs al
    JOIN users u ON al.performed_by_user_id=u.user_id
    ORDER BY u.full_name
")->fetchAll();

// Study query: Prepared SQL: reads rows from activity_logs, users, guest_visits for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$countStmt = $db->prepare("
    SELECT COUNT(*)
    FROM activity_logs al
    LEFT JOIN users u ON al.performed_by_user_id=u.user_id
    LEFT JOIN guest_visits gv ON gv.visit_id=al.visit_id
    $whereSql
");
$countStmt->execute($params);
$total = (int)$countStmt->fetchColumn();
$totalPages = max(1, (int)ceil($total / $perPage));
if ($page > $totalPages) {
    $page = $totalPages;
    $offset = ($page - 1) * $perPage;
}

// Study query: Prepared SQL: reads rows from activity_logs, users, offices, guest_visits for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$logStmt = $db->prepare("
    SELECT
        al.*,
        u.full_name AS user_name,
        u.role AS user_role,
        o.office_name,
        gv.visit_reference
    FROM activity_logs al
    LEFT JOIN users u ON al.performed_by_user_id=u.user_id
    LEFT JOIN offices o ON al.office_id=o.office_id
    LEFT JOIN guest_visits gv ON gv.visit_id=al.visit_id
    $whereSql
    ORDER BY al.logged_at DESC
    LIMIT :limit OFFSET :offset
");
foreach ($params as $key => $value) {
    $logStmt->bindValue($key, $value);
}
$logStmt->bindValue(':limit', $perPage, PDO::PARAM_INT);
$logStmt->bindValue(':offset', $offset, PDO::PARAM_INT);
$logStmt->execute();
$logs = $logStmt->fetchAll();

$todayTotal = getCountQuery("SELECT COUNT(*) FROM activity_logs WHERE DATE(logged_at)=CURDATE()");
$systemTotal = getCountQuery("SELECT COUNT(*) FROM activity_logs WHERE performed_by_user_id IS NULL");
$actorTotal = getCountQuery("SELECT COUNT(DISTINCT performed_by_user_id) FROM activity_logs WHERE performed_by_user_id IS NOT NULL");
$showingFrom = $total ? $offset + 1 : 0;
$showingTo = min($offset + $perPage, $total);

function auditQuery(array $overrides = []): string
{
    $query = array_merge($_GET, $overrides);
    foreach ($query as $key => $value) {
        if ($value === '' || $value === null) unset($query[$key]);
    }
    return http_build_query($query);
}

function auditActionClass(string $type): string
{
    if (str_contains($type, 'login')) return 'badge-success';
    if (str_contains($type, 'restricted') || str_contains($type, 'cancelled')) return 'badge-danger';
    if (str_contains($type, 'created') || str_contains($type, 'registration')) return 'badge-blue';
    if (str_contains($type, 'updated') || str_contains($type, 'added')) return 'badge-warning';
    return 'badge-secondary';
}

// Study flow: controller work is done above; the shared header starts the visible page layout below.
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Activity Logs</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li>
      <li>Activity Logs</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/admin/activity_logs.php" class="btn btn-outline">
      <i data-lucide="rotate-ccw"></i> Reset
    </a>
  </div>
</div>

<div class="stat-grid" style="grid-template-columns:repeat(4,1fr);margin-bottom:18px;">
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $total ?></div><div class="stat-label">Matching Entries</div></div>
      <div class="stat-icon-wrap blue"><i data-lucide="list-filter"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Current filters</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $todayTotal ?></div><div class="stat-label">Logged Today</div></div>
      <div class="stat-icon-wrap green"><i data-lucide="calendar-days"></i></div>
    </div>
    <div class="stat-trend live"><span class="live-dot"></span> Live audit trail</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $actorTotal ?></div><div class="stat-label">Active Actors</div></div>
      <div class="stat-icon-wrap purple"><i data-lucide="users"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">Users with logged activity</div>
  </div>
  <div class="stat-card">
    <div class="stat-card-top">
      <div><div class="stat-value"><?= $systemTotal ?></div><div class="stat-label">System Entries</div></div>
      <div class="stat-icon-wrap orange"><i data-lucide="server"></i></div>
    </div>
    <div class="stat-trend" style="color:var(--text-m);">No direct user actor</div>
  </div>
</div>

<div class="card" style="margin-bottom:18px;">
  <div class="card-header">
    <span><i data-lucide="sliders-horizontal" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Filters</span>
  </div>
  <div class="card-body">
    <form method="GET" class="audit-filter-form">
      <div class="table-search audit-search">
        <i data-lucide="search" class="table-search-icon"></i>
        <input type="text" name="q" placeholder="Search action, details, actor, IP, or visit ref..." value="<?= e($q) ?>" autocomplete="off">
      </div>

      <select name="action" class="table-filter">
        <option value="">All actions</option>
        <?php foreach ($actions as $a): ?>
        <option value="<?= e($a) ?>" <?= $action === $a ? 'selected' : '' ?>><?= e(statusLabel($a)) ?></option>
        <?php endforeach; ?>
      </select>

      <select name="user_id" class="table-filter">
        <option value="0">All actors</option>
        <?php foreach ($users as $u): ?>
        <option value="<?= (int)$u['user_id'] ?>" <?= $userId === (int)$u['user_id'] ? 'selected' : '' ?>>
          <?= e($u['full_name']) ?> (<?= e(statusLabel($u['role'])) ?>)
        </option>
        <?php endforeach; ?>
      </select>

      <input type="date" name="date_from" class="form-control audit-date" value="<?= e($dateFrom) ?>">
      <input type="date" name="date_to" class="form-control audit-date" value="<?= e($dateTo) ?>">

      <button type="submit" class="btn btn-primary">
        <i data-lucide="filter"></i> Apply
      </button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div>
      <div style="font-weight:800;color:var(--text);">Audit Trail</div>
      <div style="font-size:.8rem;color:var(--text-m);margin-top:2px;">
        Showing <?= $showingFrom ?>-<?= $showingTo ?> of <?= $total ?> entries, 50 per page
      </div>
    </div>
    <div class="table-count">Page <?= $page ?> of <?= $totalPages ?></div>
  </div>

  <div class="table-responsive">
    <table class="data-table audit-table">
      <thead>
        <tr>
          <th>Time</th>
          <th>Action</th>
          <th>Actor</th>
          <th>Context</th>
          <th>Details</th>
          <th>IP</th>
        </tr>
      </thead>
      <tbody>
      <?php if (empty($logs)): ?>
        <tr><td colspan="6"><div class="table-empty">No activity logs match your filters.</div></td></tr>
      <?php else: foreach ($logs as $l): ?>
        <tr>
          <td style="white-space:nowrap;">
            <div style="font-weight:700;font-size:.82rem;"><?= formatDateTime($l['logged_at']) ?></div>
            <div style="font-size:.72rem;color:var(--text-m);"><?= e(date('l', strtotime($l['logged_at']))) ?></div>
          </td>
          <td><span class="badge <?= auditActionClass($l['action_type']) ?>"><?= e(statusLabel($l['action_type'])) ?></span></td>
          <td>
            <div style="font-weight:700;font-size:.86rem;"><?= e($l['user_name'] ?? 'System') ?></div>
            <div style="font-size:.72rem;color:var(--text-m);"><?= $l['user_role'] ? e(statusLabel($l['user_role'])) : 'Automated / public action' ?></div>
          </td>
          <td style="font-size:.8rem;">
            <?php if ($l['visit_reference']): ?>
            <a href="<?= APP_URL ?>/public/visits/view.php?id=<?= (int)$l['visit_id'] ?>" class="ref-chip"><?= e($l['visit_reference']) ?></a>
            <?php else: ?>
            <span style="color:var(--text-m);">No visit</span>
            <?php endif; ?>
            <?php if ($l['office_name']): ?>
            <div style="margin-top:5px;color:var(--text-s);"><?= e($l['office_name']) ?></div>
            <?php endif; ?>
          </td>
          <td style="font-size:.84rem;color:var(--text-s);max-width:520px;line-height:1.55;"><?= e($l['description'] ?? '-') ?></td>
          <td style="font-size:.78rem;color:var(--text-m);white-space:nowrap;"><?= e($l['ip_address'] ?? '-') ?></td>
        </tr>
      <?php endforeach; endif; ?>
      </tbody>
    </table>
  </div>

  <?php if ($totalPages > 1): ?>
  <div class="audit-pagination">
    <a class="btn btn-outline btn-sm <?= $page <= 1 ? 'disabled' : '' ?>" href="<?= $page <= 1 ? '#' : '?' . auditQuery(['page' => $page - 1]) ?>">
      <i data-lucide="chevron-left"></i> Previous
    </a>
    <div class="audit-page-links">
      <?php
        $start = max(1, $page - 2);
        $end = min($totalPages, $page + 2);
        if ($start > 1) echo '<a href="?' . e(auditQuery(['page' => 1])) . '">1</a><span>...</span>';
        for ($p = $start; $p <= $end; $p++):
      ?>
      <a href="?<?= e(auditQuery(['page' => $p])) ?>" class="<?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
      <?php endfor;
        if ($end < $totalPages) echo '<span>...</span><a href="?' . e(auditQuery(['page' => $totalPages])) . '">' . $totalPages . '</a>';
      ?>
    </div>
    <a class="btn btn-outline btn-sm <?= $page >= $totalPages ? 'disabled' : '' ?>" href="<?= $page >= $totalPages ? '#' : '?' . auditQuery(['page' => $page + 1]) ?>">
      Next <i data-lucide="chevron-right"></i>
    </a>
  </div>
  <?php endif; ?>
</div>

<style>
.audit-filter-form{
  display:grid;
  grid-template-columns:minmax(260px,2fr) minmax(160px,1fr) minmax(190px,1fr) 150px 150px auto;
  gap:10px;
  align-items:end;
}
.audit-search{max-width:none;min-width:0;}
.audit-date{height:34px;font-size:.8rem;}
.audit-table td{vertical-align:top;}
.audit-pagination{
  display:flex;
  align-items:center;
  justify-content:space-between;
  gap:12px;
  padding:14px 18px;
  border-top:1px solid var(--border);
}
.audit-page-links{display:flex;align-items:center;gap:6px;flex-wrap:wrap;justify-content:center;}
.audit-page-links a,.audit-page-links span{
  min-width:30px;
  height:30px;
  display:inline-flex;
  align-items:center;
  justify-content:center;
  border-radius:8px;
  font-size:.82rem;
  font-weight:700;
  color:var(--text-s);
}
.audit-page-links a{border:1px solid var(--border);background:#fff;}
.audit-page-links a.active{background:var(--primary);border-color:var(--primary);color:#fff;}
.audit-pagination .disabled{opacity:.45;pointer-events:none;}
@media(max-width:1200px){
  .audit-filter-form{grid-template-columns:1fr 1fr;}
  .audit-search{grid-column:1 / -1;}
}
@media(max-width:768px){
  .audit-filter-form{grid-template-columns:1fr;}
  .audit-pagination{flex-direction:column;}
}
</style>

<script>
lucide.createIcons();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

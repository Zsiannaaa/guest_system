<?php
/**
 * admin/activity_logs.php — Audit Logs
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/users_module.php';
require_once __DIR__ . '/../../modules/offices_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Activity Logs'; $db = getDB();
$logs = $db->query("SELECT al.*, u.full_name AS user_name FROM activity_logs al LEFT JOIN users u ON al.performed_by_user_id=u.user_id ORDER BY al.created_at DESC LIMIT 500")->fetchAll();
$total = count($logs);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div><div class="page-title">Activity Logs</div>
  <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li><li>Activity Logs</li></ul></div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search logs…" autocomplete="off"></div>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?> entries</div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Time</th><th>Action</th><th>By</th><th>Details</th></tr></thead>
      <tbody>
      <?php foreach ($logs as $l): ?>
      <tr>
        <td style="white-space:nowrap;font-size:.8rem;color:var(--text-s);"><?= formatDateTime($l['created_at']) ?></td>
        <td><span class="badge badge-secondary"><?= e($l['action_type']) ?></span></td>
        <td style="font-weight:600;font-size:.85rem;"><?= e($l['user_name'] ?? 'System') ?></td>
        <td style="font-size:.83rem;color:var(--text-s);max-width:400px;"><?= e($l['details'] ?? '—') ?></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(()=>{const q=s.value.toLowerCase().trim();let n=0;
t.querySelectorAll('tbody tr').forEach(r=>{const ok=!q||r.textContent.toLowerCase().includes(q);r.style.display=ok?'':'none';if(ok)n++;});c.textContent=n;},200);});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

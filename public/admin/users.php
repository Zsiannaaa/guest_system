<?php
// ============================================================
// public/admin/users.php — User Management (Controller + View)
//
// MVC Role:
//   Model      → modules/users_module.php
//   Controller → POST handling at the top
//   View       → HTML below
//
// Access: Admin only
// ============================================================
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/users_module.php';

requireRole(ROLE_ADMIN);
$pageTitle = 'Manage Users';
$db = getDB();

// —— TOGGLE STATUS (POST + CSRF) ——————————————————————————
if (isPost() && isset($_POST['toggle_status'])) {
    verifyCsrf(APP_URL . '/public/admin/users.php');
    $uid = inputInt('user_id', 'POST');
    if ($uid === currentUserId()) {
        setFlash('error', 'You cannot deactivate your own account.');
    } else {
        $newStatus = toggleUserStatus($db, $uid);
        logActivity(null, 'user_updated', currentUserId(), null, "User #{$uid} status changed to {$newStatus}");
        setFlash('success', 'User status updated.');
    }
    redirect(APP_URL . '/public/admin/users.php');
}

// —— DELETE (POST + CSRF) ——————————————————————————————————
if (isPost() && isset($_POST['delete_user'])) {
    verifyCsrf(APP_URL . '/public/admin/users.php');
    $uid = inputInt('user_id', 'POST');
    if ($uid === currentUserId()) {
        setFlash('error', 'Cannot delete your own account.');
    } else {
        deleteUser($db, $uid);
        logActivity(null, 'user_updated', currentUserId(), null, "User #{$uid} deleted");
        setFlash('success', 'User deleted.');
    }
    redirect(APP_URL . '/public/admin/users.php');
}

// —— FETCH LIST ——————————————————————————————————————————
$users = getUsers($db);

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Manage Users</div>
    <ul class="breadcrumb">
      <li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li>
      <li>Users</li>
    </ul>
  </div>
  <div class="page-actions">
    <a href="<?= APP_URL ?>/public/admin/add_user.php" class="btn btn-primary">
      <i data-lucide="user-plus"></i> Add New User
    </a>
  </div>
</div>

<div class="card">
  <!-- Toolbar -->
  <div class="table-toolbar">
    <div class="table-search">
      <i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search by name, username or email…" autocomplete="off">
    </div>
    <select class="table-filter" id="roleFilter">
      <option value="">All Roles</option>
      <option value="admin">Administrator</option>
      <option value="guard">Guard / Reception</option>
      <option value="office_staff">Office Staff</option>
    </select>
    <select class="table-filter" id="statusFilter">
      <option value="">All Status</option>
      <option value="active">Active</option>
      <option value="inactive">Inactive</option>
    </select>
    <div class="table-count"><span id="visibleCount"><?= count($users) ?></span> of <?= count($users) ?> users</div>
  </div>

  <div class="card-header">
    <span><i data-lucide="users" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>System Accounts</span>
    <span style="font-size:.8rem;font-weight:400;color:var(--text-m);"><?= count($users) ?> total users</span>
  </div>

  <div class="table-responsive">
    <table class="data-table" id="usersTable">
      <thead>
        <tr>
          <th>Full Name</th><th>Username</th><th>Email</th>
          <th>Role</th><th>Office</th><th>Status</th>
          <th>Last Login</th><th>Actions</th>
        </tr>
      </thead>
      <tbody>
      <?php foreach ($users as $u): ?>
      <tr data-role="<?= e($u['role']) ?>" data-status="<?= e($u['status']) ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar">
              <?= strtoupper(substr($u['full_name'],0,1)) ?>
            </div>
            <div>
              <div class="guest-name">
                <?= e($u['full_name']) ?>
                <?php if ($u['user_id']===currentUserId()): ?>
                <span class="badge badge-secondary" style="font-size:.62rem;padding:1px 6px;margin-left:4px;">You</span>
                <?php endif; ?>
              </div>
            </div>
          </div>
        </td>
        <td><code style="font-size:.82rem;background:var(--bg);padding:2px 6px;border-radius:4px;"><?= e($u['username']) ?></code></td>
        <td style="font-size:.83rem;color:var(--text-s);"><?= e($u['email']) ?></td>
        <td>
          <span class="badge <?= $u['role']==='admin' ? 'badge-primary' : ($u['role']==='guard' ? 'badge-blue' : 'badge-purple') ?>">
            <?= statusLabel($u['role']) ?>
          </span>
        </td>
        <td style="font-size:.83rem;"><?= e($u['office_name'] ?? '—') ?></td>
        <td>
          <span class="badge <?= $u['status']==='active' ? 'badge-success' : 'badge-danger' ?>">
            <?= $u['status']==='active' ? 'Active' : 'Inactive' ?>
          </span>
        </td>
        <td style="font-size:.8rem;color:var(--text-m);"><?= formatDateTime($u['last_login'] ?? null) ?></td>
        <td>
          <div class="tbl-actions">
            <a href="<?= APP_URL ?>/public/admin/edit_user.php?id=<?= $u['user_id'] ?>" class="btn-tbl btn-tbl-outline">
              <i data-lucide="pencil"></i> Edit
            </a>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
              <input type="hidden" name="current_status" value="<?= $u['status'] ?>">
              <button type="submit" name="toggle_status"
                      class="btn-tbl <?= $u['status']==='active' ? 'btn-tbl-warn' : 'btn-tbl-success' ?>"
                      <?= $u['user_id']===currentUserId() ? 'disabled' : '' ?>
                      data-confirm="<?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?> <?= e($u['full_name']) ?>?">
                <i data-lucide="<?= $u['status']==='active' ? 'user-x' : 'user-check' ?>"></i>
                <?= $u['status']==='active' ? 'Deactivate' : 'Activate' ?>
              </button>
            </form>
            <?php if ($u['user_id']!==currentUserId()): ?>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="user_id" value="<?= $u['user_id'] ?>">
              <button type="submit" name="delete_user"
                      class="btn-tbl btn-tbl-danger"
                      data-confirm="Permanently delete '<?= e($u['full_name']) ?>'? This cannot be undone.">
                <i data-lucide="trash-2"></i>
              </button>
            </form>
            <?php endif; ?>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const search = document.getElementById('searchInput');
const roleF  = document.getElementById('roleFilter');
const statF  = document.getElementById('statusFilter');
const tbl    = document.getElementById('usersTable');
const cnt    = document.getElementById('visibleCount');

function filter() {
  const q = search.value.toLowerCase().trim();
  const r = roleF.value, s = statF.value;
  let n = 0;
  tbl.querySelectorAll('tbody tr[data-role]').forEach(row => {
    const ok = (!q || row.textContent.toLowerCase().includes(q))
            && (!r || row.dataset.role === r)
            && (!s || row.dataset.status === s);
    row.style.display = ok ? '' : 'none';
    if (ok) n++;
  });
  cnt.textContent = n;
}
let d; search.addEventListener('input', ()=>{ clearTimeout(d); d=setTimeout(filter,200); });
roleF.addEventListener('change', filter);
statF.addEventListener('change', filter);
lucide.createIcons();
</script>

<?php include __DIR__ . '/../../includes/footer.php'; ?>

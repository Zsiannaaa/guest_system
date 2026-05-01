<?php
/**
 * admin/offices.php — Office Management
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/admin/users_module.php';
require_once __DIR__ . '/../../modules/admin/offices_module.php';
requireRole(ROLE_ADMIN);
$pageTitle = 'Manage Offices';
$db = getDB();

if (isPost() && isset($_POST['toggle_status'])) {
    verifyCsrf(APP_URL . '/public/admin/offices.php');
    $oid = (int)$_POST['office_id']; $new = $_POST['current_status']==='active'?'inactive':'active';
    $db->prepare("UPDATE offices SET status=:s WHERE office_id=:id")->execute([':s'=>$new,':id'=>$oid]);
    setFlash('success','Office status updated.'); redirect(APP_URL.'/public/admin/offices.php');
}
if (isPost() && isset($_POST['add_office'])) {
    verifyCsrf(APP_URL . '/public/admin/offices.php');
    $name = trim($_POST['office_name']??''); $loc = trim($_POST['office_location']??'');
    if (!empty($name)) {
        $code = generateUniqueOfficeCode($db, $name);
        $db->prepare("INSERT INTO offices (office_name,office_code,office_location,status) VALUES (:n,:c,:l,'active')")
           ->execute([':n'=>$name,':c'=>$code,':l'=>$loc?:null]);
        setFlash('success',"Office <strong>{$name}</strong> added.");
    } redirect(APP_URL.'/public/admin/offices.php');
}

$offices = $db->query("SELECT o.*, (SELECT COUNT(*) FROM users u WHERE u.office_id=o.office_id AND u.status='active') AS staff_count FROM offices o ORDER BY o.office_name")->fetchAll();
$total = count($offices);
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Manage Offices</div>
    <ul class="breadcrumb"><li><a href="<?= APP_URL ?>/public/dashboard/admin.php">Dashboard</a></li><li>Offices</li></ul>
  </div>
  <div class="page-actions">
    <button type="button" class="btn btn-primary" onclick="document.getElementById('addPanel').style.display=document.getElementById('addPanel').style.display==='none'?'block':'none'">
      <i data-lucide="plus"></i> Add Office
    </button>
  </div>
</div>

<!-- Quick Add -->
<div class="card" id="addPanel" style="display:none;margin-bottom:20px;">
  <div class="card-header">Add New Office</div>
  <div class="card-body">
    <form method="POST" style="display:flex;gap:12px;flex-wrap:wrap;align-items:flex-end;">
      <?= csrfField() ?>
      <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
        <label class="form-label">Office Name <span class="required-star">*</span></label>
        <input type="text" name="office_name" class="form-control" required placeholder="e.g. Registrar's Office">
      </div>
      <div class="form-group" style="flex:1;min-width:200px;margin-bottom:0;">
        <label class="form-label">Location</label>
        <input type="text" name="office_location" class="form-control" placeholder="e.g. Admin Bldg, 2nd Floor">
      </div>
      <button type="submit" name="add_office" class="btn btn-primary" style="height:38px;"><i data-lucide="plus"></i> Add</button>
    </form>
  </div>
</div>

<div class="card">
  <div class="table-toolbar">
    <div class="table-search"><i data-lucide="search" class="table-search-icon"></i>
      <input type="text" id="searchInput" placeholder="Search offices…" autocomplete="off"></div>
    <select class="table-filter" id="statusFilter">
      <option value="">All Status</option><option value="active">Active</option><option value="inactive">Inactive</option>
    </select>
    <div class="table-count"><span id="visibleCount"><?= $total ?></span> of <?= $total ?> offices</div>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="tbl">
      <thead><tr><th>Office Name</th><th>Location</th><th>Staff</th><th>Status</th><th>Actions</th></tr></thead>
      <tbody>
      <?php foreach ($offices as $o): ?>
      <tr data-status="<?= $o['status'] ?>">
        <td style="font-weight:600;"><?= e($o['office_name']) ?></td>
        <td style="font-size:.83rem;color:var(--text-s);"><?= e($o['office_location'] ?? '—') ?></td>
        <td><span class="badge badge-blue"><?= (int)$o['staff_count'] ?> staff</span></td>
        <td><span class="badge <?= $o['status']==='active'?'badge-success':'badge-danger' ?>"><?= ucfirst($o['status']) ?></span></td>
        <td>
          <div class="tbl-actions">
            <a href="<?= APP_URL ?>/public/admin/edit_office.php?id=<?= $o['office_id'] ?>" class="btn-tbl btn-tbl-outline"><i data-lucide="pencil"></i> Edit</a>
            <form method="POST" style="display:inline;">
              <?= csrfField() ?>
              <input type="hidden" name="office_id" value="<?= $o['office_id'] ?>">
              <input type="hidden" name="current_status" value="<?= $o['status'] ?>">
              <button type="submit" name="toggle_status" class="btn-tbl <?= $o['status']==='active'?'btn-tbl-warn':'btn-tbl-success' ?>">
                <i data-lucide="<?= $o['status']==='active'?'eye-off':'eye' ?>"></i> <?= $o['status']==='active'?'Deactivate':'Activate' ?>
              </button>
            </form>
          </div>
        </td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<script>
const s=document.getElementById('searchInput'),f=document.getElementById('statusFilter'),t=document.getElementById('tbl'),c=document.getElementById('visibleCount');
function filter(){const q=s.value.toLowerCase().trim(),st=f.value;let n=0;
t.querySelectorAll('tbody tr[data-status]').forEach(r=>{const ok=(!q||r.textContent.toLowerCase().includes(q))&&(!st||r.dataset.status===st);r.style.display=ok?'':'none';if(ok)n++;});c.textContent=n;}
let d;s.addEventListener('input',()=>{clearTimeout(d);d=setTimeout(filter,200);});f.addEventListener('change',filter);
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

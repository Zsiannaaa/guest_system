<?php
/**
 * visits/preregister.php — Staff Pre-Registration (Guard/Admin)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';
requireRole([ROLE_GUARD, ROLE_ADMIN]);
$pageTitle = 'Pre-Register Guest';
$db = getDB();

$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();
$errors = [];
$idTypes = ["Driver's License","Passport","SSS ID","PhilHealth ID","UMID","Voter's ID","Company ID","School ID","Government ID","Other"];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/visits/preregister.php');
    $fullName = trim($_POST['full_name'] ?? ''); $contact = trim($_POST['contact_number'] ?? '');
    $email = trim($_POST['email'] ?? ''); $organization = trim($_POST['organization'] ?? '');
    $idType = trim($_POST['id_type'] ?? '');
    $purpose = trim($_POST['purpose_of_visit'] ?? ''); $visitDate = $_POST['visit_date'] ?? '';
    $expectedIn = $_POST['expected_time_in'] ?? ''; $expectedOut = $_POST['expected_time_out'] ?? '';
    $hasVehicle = isset($_POST['has_vehicle']) ? 1 : 0; $notes = trim($_POST['notes'] ?? '');
    $destOffices = $_POST['dest_offices'] ?? [];
    $plateNumber = trim($_POST['plate_number'] ?? ''); $vehicleType = trim($_POST['vehicle_type'] ?? 'car');
    $hasSticker = isset($_POST['has_university_sticker']) ? 1 : 0; $stickerNumber = trim($_POST['sticker_number'] ?? '');
    $vehicleColor = trim($_POST['vehicle_color'] ?? ''); $vehicleModel = trim($_POST['vehicle_model'] ?? '');
    $driverName = trim($_POST['driver_name'] ?? ''); $driverIsGuest = isset($_POST['driver_is_guest']) ? 1 : 0;

    if (empty($fullName)) $errors[] = 'Full name is required.';
    if (empty($purpose))  $errors[] = 'Purpose of visit is required.';
    if (empty($visitDate)) $errors[] = 'Visit date is required.';
    if (!empty($visitDate) && $visitDate < date('Y-m-d')) $errors[] = 'Visit date cannot be in the past.';
    if (empty($destOffices)) $errors[] = 'At least one destination office is required.';
    if ($hasVehicle && empty($plateNumber)) $errors[] = 'Plate number is required for vehicle entry.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $db->prepare("INSERT INTO guests (full_name,contact_number,email,organization,id_type) VALUES (:n,:c,:e,:o,:t)")
               ->execute([':n'=>$fullName,':c'=>$contact?:null,':e'=>$email?:null,':o'=>$organization?:null,':t'=>$idType?:null]);
            $guestId = (int)$db->lastInsertId();
            $visitRef = generateVisitReference(); $qrToken = generateQrToken();
            $db->prepare("INSERT INTO guest_visits (guest_id,visit_reference,qr_token,registration_type,purpose_of_visit,visit_date,expected_time_in,expected_time_out,overall_status,has_vehicle,processed_by_guard_id,notes) VALUES (:gid,:ref,:qr,'pre_registered',:purpose,:vdate,:ein,:eout,'pending',:vehicle,:guard,:notes)")
               ->execute([':gid'=>$guestId,':ref'=>$visitRef,':qr'=>$qrToken,':purpose'=>$purpose,':vdate'=>$visitDate,':ein'=>$expectedIn?:null,':eout'=>$expectedOut?:null,':vehicle'=>$hasVehicle,':guard'=>currentUserId(),':notes'=>$notes?:null]);
            $visitId = (int)$db->lastInsertId();
            foreach ($destOffices as $seq => $oid) {
                $db->prepare("INSERT INTO visit_destinations (visit_id,office_id,sequence_no,destination_status,is_primary) VALUES (:v,:o,:s,'pending',:p)")
                   ->execute([':v'=>$visitId,':o'=>(int)$oid,':s'=>$seq+1,':p'=>$seq===0?1:0]);
            }
            if ($hasVehicle && $plateNumber) {
                $db->prepare("INSERT INTO vehicle_entries (visit_id,vehicle_type,plate_number,has_university_sticker,sticker_number,vehicle_color,vehicle_model,driver_name,is_driver_the_guest) VALUES (:v,:t,:p,:hs,:sn,:c,:m,:d,:g)")
                   ->execute([':v'=>$visitId,':t'=>$vehicleType,':p'=>$plateNumber,':hs'=>$hasSticker,':sn'=>$stickerNumber?:null,':c'=>$vehicleColor?:null,':m'=>$vehicleModel?:null,':d'=>$driverIsGuest?$fullName:($driverName?:null),':g'=>$driverIsGuest]);
            }
            logActivity($visitId, 'pre_registration', currentUserId(), null, "Guest '{$fullName}' pre-registered: {$visitRef}");
            $db->commit();
            setFlash('success', "Pre-registration successful! Reference: <strong>{$visitRef}</strong>");
            redirect(APP_URL . '/public/visits/view.php?id=' . $visitId);
        } catch (PDOException $e) {
            $db->rollBack(); error_log('Pre-reg error: ' . $e->getMessage());
            $errors[] = 'A database error occurred.';
        }
    }
}
include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Pre-Register Guest</div>
    <ul class="breadcrumb">
      <li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li>
      <li>Pre-Registration</li>
    </ul>
  </div>
</div>

<div class="info-box info" style="margin-bottom:20px;">
  <i data-lucide="info"></i>
  <div>Pre-registering creates a <strong>pending visit</strong> with a reference number. The guest still needs to <strong>check in at the gate</strong> when they arrive.</div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix the following:</div>
  <ul><?php foreach ($errors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="preRegForm">
<?= csrfField() ?>
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">
  <div>
    <!-- Guest Info -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest Information</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="required-star">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" required>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Contact</label><input type="text" name="contact_number" class="form-control" value="<?= e($_POST['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX"></div>
          <div class="form-group"><label class="form-label">Email</label><input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Organization</label><input type="text" name="organization" class="form-control" value="<?= e($_POST['organization'] ?? '') ?>"></div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">ID Type</label><select name="id_type" class="form-select"><option value="">— Select —</option><?php foreach($idTypes as $t): ?><option value="<?= e($t) ?>" <?= ($_POST['id_type']??'')===$t?'selected':'' ?>><?= e($t) ?></option><?php endforeach; ?></select></div>
        </div>
      </div>
    </div>

    <!-- Schedule -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="calendar" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Schedule & Visit Details</div>
      <div class="card-body">
        <div class="form-row col-3">
          <div class="form-group"><label class="form-label">Visit Date <span class="required-star">*</span></label><input type="date" name="visit_date" class="form-control" value="<?= e($_POST['visit_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required></div>
          <div class="form-group"><label class="form-label">Expected Arrival</label><input type="time" name="expected_time_in" class="form-control" value="<?= e($_POST['expected_time_in'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Expected Departure</label><input type="time" name="expected_time_out" class="form-control" value="<?= e($_POST['expected_time_out'] ?? '') ?>"></div>
        </div>
        <div class="form-group"><label class="form-label">Purpose of Visit <span class="required-star">*</span></label><textarea name="purpose_of_visit" class="form-control" rows="2" required><?= e($_POST['purpose_of_visit'] ?? '') ?></textarea></div>
        <div class="form-group"><label class="form-label">Notes (optional)</label><input type="text" name="notes" class="form-control" value="<?= e($_POST['notes'] ?? '') ?>"></div>
      </div>
    </div>

    <!-- Vehicle -->
    <div class="card">
      <div class="card-header" style="justify-content:space-between;">
        <span><i data-lucide="car" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Vehicle</span>
        <label class="toggle-wrap"><span style="font-size:.8rem;color:var(--text-s);">Has Vehicle</span><div class="toggle-switch"><input type="checkbox" name="has_vehicle" <?= isset($_POST['has_vehicle'])?'checked':'' ?> onchange="document.getElementById('vehF').style.display=this.checked?'block':'none'"><span class="toggle-slider"></span></div></label>
      </div>
      <div class="card-body" id="vehF" style="display:<?= isset($_POST['has_vehicle'])?'block':'none' ?>;">
        <div class="form-row">
          <div class="form-group"><label class="form-label">Type</label><select name="vehicle_type" class="form-select"><?php foreach(['car'=>'Car','motorcycle'=>'Motorcycle','van'=>'Van','truck'=>'Truck','other'=>'Other'] as $v=>$l): ?><option value="<?= $v ?>"><?= $l ?></option><?php endforeach; ?></select></div>
          <div class="form-group"><label class="form-label">Plate Number</label><input type="text" name="plate_number" class="form-control" value="<?= e($_POST['plate_number'] ?? '') ?>" style="text-transform:uppercase;"></div>
        </div>
        <div class="check-item">
          <input type="checkbox" id="hasSticker" name="has_university_sticker" <?= isset($_POST['has_university_sticker'])?'checked':'' ?> onchange="document.getElementById('stickerField').style.display=this.checked?'block':'none'">
          <label for="hasSticker">Vehicle has university sticker/pass</label>
        </div>
        <div id="stickerField" style="display:<?= isset($_POST['has_university_sticker'])?'block':'none' ?>;">
          <div class="form-group"><label class="form-label">Sticker / Pass Number</label><input type="text" name="sticker_number" class="form-control" value="<?= e($_POST['sticker_number'] ?? '') ?>" placeholder="Optional"></div>
        </div>
        <div class="form-row">
          <div class="form-group"><label class="form-label">Color</label><input type="text" name="vehicle_color" class="form-control" value="<?= e($_POST['vehicle_color'] ?? '') ?>"></div>
          <div class="form-group"><label class="form-label">Model</label><input type="text" name="vehicle_model" class="form-control" value="<?= e($_POST['vehicle_model'] ?? '') ?>"></div>
        </div>
      </div>
    </div>
  </div>

  <!-- Right: Destinations -->
  <div>
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Destination(s) <span class="required-star">*</span></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:2px;">
          <?php foreach ($offices as $o): ?>
          <label class="office-check">
            <input type="checkbox" name="dest_offices[]" value="<?= $o['office_id'] ?>" <?= in_array($o['office_id'],$_POST['dest_offices']??[])?'checked':'' ?> onchange="this.closest('.office-check').classList.toggle('checked',this.checked)">
            <span style="font-weight:500;"><?= e($o['office_name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer" style="display:flex;flex-direction:column;gap:8px;">
        <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:12px;"><i data-lucide="calendar-check"></i> Create Pre-Registration</button>
        <a href="<?= getDashboardUrl() ?>" class="btn btn-outline w-100" style="justify-content:center;">Cancel</a>
      </div>
    </div>
  </div>
</div>
</form>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

<?php
/**
 * visits/walkin.php — Walk-In Guest Registration (Guard/Admin)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';
requireRole([ROLE_GUARD, ROLE_ADMIN]);
$pageTitle = 'Walk-In Registration';
$db = getDB();

$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();
$errors = [];

if (isPost()) {
    verifyCsrf(APP_URL . '/public/visits/walkin.php');
    $fullName     = trim($_POST['full_name'] ?? '');
    $contact      = trim($_POST['contact_number'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $idType       = trim($_POST['id_type'] ?? '');
    $purpose      = trim($_POST['purpose_of_visit'] ?? '');
    $visitDate    = $_POST['visit_date'] ?? date('Y-m-d');
    $hasVehicle   = isset($_POST['has_vehicle']) ? 1 : 0;
    $notes        = trim($_POST['notes'] ?? '');
    $destOffices  = $_POST['dest_offices'] ?? [];

    if (empty($fullName))    $errors[] = 'Guest full name is required.';
    if (empty($purpose))     $errors[] = 'Purpose of visit is required.';
    if (empty($destOffices)) $errors[] = 'At least one destination office is required.';
    if (!empty($visitDate) && $visitDate < date('Y-m-d')) $errors[] = 'Visit date cannot be in the past.';

    $vehicleType  = trim($_POST['vehicle_type'] ?? 'car');
    $plateNumber  = trim($_POST['plate_number'] ?? '');
    $hasSticker   = isset($_POST['has_university_sticker']) ? 1 : 0;
    $stickerNumber = trim($_POST['sticker_number'] ?? '');
    $vehicleColor = trim($_POST['vehicle_color'] ?? '');
    $vehicleModel = trim($_POST['vehicle_model'] ?? '');
    $driverName   = trim($_POST['driver_name'] ?? '');
    $driverIsGuest = isset($_POST['driver_is_guest']) ? 1 : 0;

    if ($hasVehicle && empty($plateNumber)) $errors[] = 'Plate number is required for vehicle entry.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            $existStmt = $db->prepare("SELECT guest_id FROM guests WHERE full_name=:name AND (contact_number=:contact OR contact_number IS NULL) LIMIT 1");
            $existStmt->execute([':name'=>$fullName,':contact'=>$contact?:null]);
            $guestId = $existStmt->fetchColumn();

            if ($guestId) {
                $db->prepare("UPDATE guests SET email=COALESCE(NULLIF(:email,''),email),organization=COALESCE(NULLIF(:org,''),organization),id_type=COALESCE(NULLIF(:idt,''),id_type),updated_at=NOW() WHERE guest_id=:gid")
                   ->execute([':email'=>$email?:null,':org'=>$organization?:null,':idt'=>$idType?:null,':gid'=>$guestId]);
            } else {
                $db->prepare("INSERT INTO guests (full_name,contact_number,email,organization,id_type) VALUES (:n,:c,:e,:o,:t)")
                   ->execute([':n'=>$fullName,':c'=>$contact?:null,':e'=>$email?:null,':o'=>$organization?:null,':t'=>$idType?:null]);
                $guestId = (int)$db->lastInsertId();
            }

            // Check restricted
            $isRestricted = $db->prepare("SELECT is_restricted FROM guests WHERE guest_id=:g");
            $isRestricted->execute([':g'=>$guestId]);
            if ($isRestricted->fetchColumn()) {
                $db->rollBack();
                $errors[] = '⚠️ This guest is RESTRICTED and cannot be checked in. Contact an administrator.';
            } else {
                $visitRef = generateVisitReference();
                $qrToken  = generateQrToken();
                $db->prepare("INSERT INTO guest_visits (guest_id,visit_reference,qr_token,registration_type,purpose_of_visit,visit_date,actual_check_in,overall_status,has_vehicle,processed_by_guard_id,notes) VALUES (:gid,:ref,:qr,'walk_in',:purpose,:vdate,NOW(),'checked_in',:vehicle,:guard,:notes)")
                   ->execute([':gid'=>$guestId,':ref'=>$visitRef,':qr'=>$qrToken,':purpose'=>$purpose,':vdate'=>$visitDate,':vehicle'=>$hasVehicle,':guard'=>currentUserId(),':notes'=>$notes?:null]);
                $visitId = (int)$db->lastInsertId();

                foreach ($destOffices as $seq => $oid) {
                    $db->prepare("INSERT INTO visit_destinations (visit_id,office_id,sequence_no,destination_status,is_primary) VALUES (:v,:o,:s,'pending',:p)")
                       ->execute([':v'=>$visitId,':o'=>(int)$oid,':s'=>$seq+1,':p'=>$seq===0?1:0]);
                }

                if ($hasVehicle && $plateNumber) {
                    $db->prepare("INSERT INTO vehicle_entries (visit_id,vehicle_type,plate_number,has_university_sticker,sticker_number,vehicle_color,vehicle_model,driver_name,is_driver_the_guest) VALUES (:v,:t,:p,:hs,:sn,:c,:m,:d,:g)")
                       ->execute([':v'=>$visitId,':t'=>$vehicleType,':p'=>$plateNumber,':hs'=>$hasSticker,':sn'=>$stickerNumber?:null,':c'=>$vehicleColor?:null,':m'=>$vehicleModel?:null,':d'=>$driverIsGuest?$fullName:($driverName?:null),':g'=>$driverIsGuest]);
                }

                logActivity($visitId, 'walk_in_registration', currentUserId(), null, "Walk-in guest '{$fullName}' registered: {$visitRef}");
                logActivity($visitId, 'check_in', currentUserId(), null, "Guard checked in walk-in guest '{$fullName}'");
                $db->commit();
                $printUrl = APP_URL . '/public/visits/receipt.php?id=' . $visitId;
                setFlash('success', "Walk-in guest registered! Reference: <strong>" . e($visitRef) . "</strong>. <a href=\"{$printUrl}\" style=\"font-weight:800;color:inherit;text-decoration:underline;\">Print gate slip</a>");
                redirect(APP_URL . '/public/visits/view.php?id=' . $visitId);
            }
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Walk-in error: ' . $e->getMessage());
            $errors[] = 'A database error occurred. Please try again.';
        }
    }
}

include __DIR__ . '/../../includes/header.php';
$idTypes = ["Driver's License","Passport","SSS ID","PhilHealth ID","UMID","Voter's ID","Postal ID","Company ID","School ID","Government ID","Other"];
?>

<div class="page-top">
  <div>
    <div class="page-title">Walk-In Guest Registration</div>
    <ul class="breadcrumb">
      <li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li>
      <li>Walk-In Registration</li>
    </ul>
  </div>
</div>

<?php if (!empty($errors)): ?>
<div class="error-box">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix the following:</div>
  <ul><?php foreach ($errors as $err): ?><li><?= $err ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<form method="POST" id="walkinForm">
<?= csrfField() ?>
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;">

  <!-- LEFT COLUMN -->
  <div>
    <!-- Guest Info -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Guest Personal Information</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="required-star">*</span></label>
          <input type="text" name="full_name" class="form-control" value="<?= e($_POST['full_name'] ?? '') ?>" placeholder="Juan Dela Cruz" required>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <input type="text" name="contact_number" class="form-control" value="<?= e($_POST['contact_number'] ?? '') ?>" placeholder="09XXXXXXXXX">
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <input type="email" name="email" class="form-control" value="<?= e($_POST['email'] ?? '') ?>" placeholder="guest@example.com">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Company / Organization</label>
          <input type="text" name="organization" class="form-control" value="<?= e($_POST['organization'] ?? '') ?>" placeholder="e.g. DepEd, BIR, Individual">
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">ID Type Presented</label>
            <select name="id_type" class="form-select">
              <option value="">— Select ID Type —</option>
              <?php foreach ($idTypes as $t): ?>
              <option value="<?= e($t) ?>" <?= ($_POST['id_type'] ?? '')===$t ? 'selected' : '' ?>><?= e($t) ?></option>
              <?php endforeach; ?>
            </select>
          </div>
        </div>
      </div>
    </div>

    <!-- Visit Details -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="file-text" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit Details</div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Purpose of Visit <span class="required-star">*</span></label>
          <textarea name="purpose_of_visit" class="form-control" rows="2" placeholder="e.g. Submitting documents, Meeting with HR" required><?= e($_POST['purpose_of_visit'] ?? '') ?></textarea>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Visit Date</label>
            <input type="date" name="visit_date" class="form-control" min="<?= date('Y-m-d') ?>" value="<?= e($_POST['visit_date'] ?? date('Y-m-d')) ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Guard Notes (optional)</label>
            <input type="text" name="notes" class="form-control" value="<?= e($_POST['notes'] ?? '') ?>" placeholder="Any additional notes">
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle -->
    <div class="card">
      <div class="card-header" style="justify-content:space-between;">
        <span><i data-lucide="car" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Vehicle Information</span>
        <label class="toggle-wrap">
          <span style="font-size:.8rem;font-weight:500;color:var(--text-s);">Has Vehicle</span>
          <div class="toggle-switch">
            <input type="checkbox" name="has_vehicle" id="hasVehicle" <?= isset($_POST['has_vehicle']) ? 'checked' : '' ?> onchange="document.getElementById('vehFields').style.display=this.checked?'block':'none'">
            <span class="toggle-slider"></span>
          </div>
        </label>
      </div>
      <div class="card-body" id="vehFields" style="display:<?= isset($_POST['has_vehicle']) ? 'block' : 'none' ?>;">
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Vehicle Type</label>
            <select name="vehicle_type" class="form-select">
              <?php foreach (['car'=>'Car','motorcycle'=>'Motorcycle','van'=>'Van','truck'=>'Truck','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($_POST['vehicle_type'] ?? 'car')===$v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Plate Number <span class="required-star">*</span></label>
            <input type="text" name="plate_number" class="form-control" value="<?= e($_POST['plate_number'] ?? '') ?>" placeholder="ABC 1234" style="text-transform:uppercase;">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Vehicle Color</label>
            <input type="text" name="vehicle_color" class="form-control" value="<?= e($_POST['vehicle_color'] ?? '') ?>" placeholder="e.g. White">
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Model</label>
            <input type="text" name="vehicle_model" class="form-control" value="<?= e($_POST['vehicle_model'] ?? '') ?>" placeholder="e.g. Toyota Vios">
          </div>
        </div>
        <div class="check-item">
          <input type="checkbox" id="hasSticker" name="has_university_sticker" <?= isset($_POST['has_university_sticker']) ? 'checked' : '' ?> onchange="document.getElementById('stickerField').style.display=this.checked?'block':'none'">
          <label for="hasSticker">Vehicle has university sticker/pass</label>
        </div>
        <div id="stickerField" style="display:<?= isset($_POST['has_university_sticker']) ? 'block' : 'none' ?>;">
          <div class="form-group">
            <label class="form-label">Sticker / Pass Number</label>
            <input type="text" name="sticker_number" class="form-control" value="<?= e($_POST['sticker_number'] ?? '') ?>" placeholder="Optional">
          </div>
        </div>
        <div class="check-item">
          <input type="checkbox" id="driverIsGuest" name="driver_is_guest" checked onchange="document.getElementById('driverField').style.display=this.checked?'none':'block'">
          <label for="driverIsGuest">Guest is the driver</label>
        </div>
        <div id="driverField" style="display:none;">
          <div class="form-group">
            <label class="form-label">Driver Name</label>
            <input type="text" name="driver_name" class="form-control" value="<?= e($_POST['driver_name'] ?? '') ?>" placeholder="Driver's full name">
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- RIGHT COLUMN: Destination -->
  <div>
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Destination Office(s) <span class="required-star">*</span></div>
      <div class="card-body">
        <p style="font-size:.82rem;color:var(--text-m);margin-bottom:12px;">Select where the guest will go.</p>
        <div style="display:flex;flex-direction:column;gap:2px;">
          <?php foreach ($offices as $o): ?>
          <label class="office-check <?= in_array($o['office_id'], $_POST['dest_offices'] ?? []) ? 'checked' : '' ?>">
            <input type="checkbox" name="dest_offices[]" value="<?= $o['office_id'] ?>"
              <?= in_array($o['office_id'], $_POST['dest_offices'] ?? []) ? 'checked' : '' ?>
              onchange="this.closest('.office-check').classList.toggle('checked',this.checked)">
            <span style="font-weight:500;"><?= e($o['office_name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer" style="display:flex;flex-direction:column;gap:8px;">
        <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:12px;">
          <i data-lucide="user-check"></i> Register & Check In
        </button>
        <a href="<?= getDashboardUrl() ?>" class="btn btn-outline w-100" style="justify-content:center;">Cancel</a>
      </div>
    </div>
  </div>

</div>
</form>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

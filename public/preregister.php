<?php
/**
 * public/preregister.php — Guest Self Pre-Registration (No login required)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/session.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$offices = $db->query("SELECT office_id, office_name, office_location FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();

$errors  = [];
$success = null;
$idTypes = ["Driver's License","Passport","SSS ID","PhilHealth ID","UMID","Voter's ID","Postal ID","Company ID","School ID","Government ID","Other"];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf(APP_URL . '/public/preregister.php');
    $fullName     = trim($_POST['full_name'] ?? '');
    $contact      = trim($_POST['contact_number'] ?? '');
    $email        = trim($_POST['email'] ?? '');
    $organization = trim($_POST['organization'] ?? '');
    $idType       = trim($_POST['id_type'] ?? '');
    $purpose      = trim($_POST['purpose_of_visit'] ?? '');
    $visitDate    = $_POST['visit_date'] ?? '';
    $expectedTime = $_POST['expected_time'] ?? '';
    $expectedOut  = $_POST['expected_time_out'] ?? '';
    $destOffices  = $_POST['dest_offices'] ?? [];
    $hasVehicle   = isset($_POST['has_vehicle']) ? 1 : 0;
    $vehicleType  = trim($_POST['vehicle_type'] ?? 'car');
    $plateNumber  = trim($_POST['plate_number'] ?? '');
    $hasSticker   = isset($_POST['has_university_sticker']) ? 1 : 0;
    $stickerNumber = trim($_POST['sticker_number'] ?? '');
    $vehicleColor = trim($_POST['vehicle_color'] ?? '');
    $vehicleModel = trim($_POST['vehicle_model'] ?? '');
    $driverName   = trim($_POST['driver_name'] ?? '');
    $driverIsGuest = isset($_POST['driver_is_guest']) ? 1 : 0;

    if (empty($fullName))    $errors[] = 'Your full name is required.';
    if (empty($purpose))     $errors[] = 'Purpose of visit is required.';
    if (empty($visitDate))   $errors[] = 'Please select your visit date.';
    if (empty($destOffices)) $errors[] = 'Please select at least one office to visit.';
    if ($visitDate && $visitDate < date('Y-m-d')) $errors[] = 'Visit date cannot be in the past.';
    if ($hasVehicle && empty($plateNumber)) $errors[] = 'Plate number is required if you will bring a vehicle.';

    if (empty($errors)) {
        try {
            $db->beginTransaction();
            // Find or create guest
            $stmt = $db->prepare("SELECT guest_id FROM guests WHERE full_name=:n AND (contact_number=:c OR contact_number IS NULL) LIMIT 1");
            $stmt->execute([':n' => $fullName, ':c' => $contact ?: null]);
            $guestId = $stmt->fetchColumn();

            if (!$guestId) {
                $ins = $db->prepare("INSERT INTO guests (full_name, contact_number, email, organization, id_type) VALUES (:n,:c,:e,:o,:idt)");
                $ins->execute([':n'=>$fullName,':c'=>$contact?:null,':e'=>$email?:null,':o'=>$organization?:null,':idt'=>$idType?:null]);
                $guestId = (int)$db->lastInsertId();
            } else {
                $db->prepare("
                    UPDATE guests
                    SET email=COALESCE(NULLIF(:email,''),email),
                        organization=COALESCE(NULLIF(:org,''),organization),
                        id_type=COALESCE(NULLIF(:idt,''),id_type),
                        updated_at=NOW()
                    WHERE guest_id=:gid
                ")->execute([
                    ':email'=>$email,
                    ':org'=>$organization,
                    ':idt'=>$idType,
                    ':gid'=>$guestId,
                ]);
            }

            $visitRef = generateVisitReference();
            $qrToken  = generateQrToken();

            $ins = $db->prepare("
                INSERT INTO guest_visits (guest_id, visit_reference, qr_token, registration_type,
                    purpose_of_visit, visit_date, expected_time_in, expected_time_out, overall_status, has_vehicle)
                VALUES (:gid,:ref,:qr,'pre_registered',:purpose,:vdate,:etime,:eout,'pending',:vehicle)
            ");
            $ins->execute([
                ':gid'=>$guestId, ':ref'=>$visitRef, ':qr'=>$qrToken,
                ':purpose'=>$purpose, ':vdate'=>$visitDate,
                ':etime'=>$expectedTime ?: null,
                ':eout'=>$expectedOut ?: null,
                ':vehicle'=>$hasVehicle
            ]);
            $visitId = (int)$db->lastInsertId();

            foreach ($destOffices as $seq => $oid) {
                $db->prepare("INSERT INTO visit_destinations (visit_id, office_id, sequence_no, destination_status, is_primary) VALUES (:v,:o,:s,'pending',:p)")
                   ->execute([':v'=>$visitId, ':o'=>(int)$oid, ':s'=>$seq+1, ':p'=>$seq===0?1:0]);
            }

            if ($hasVehicle && $plateNumber) {
                $db->prepare("
                    INSERT INTO vehicle_entries
                        (visit_id, vehicle_type, plate_number, has_university_sticker, sticker_number,
                         vehicle_color, vehicle_model, driver_name, is_driver_the_guest)
                    VALUES (:v,:t,:p,:hs,:sn,:c,:m,:d,:g)
                ")->execute([
                    ':v'=>$visitId,
                    ':t'=>$vehicleType,
                    ':p'=>$plateNumber,
                    ':hs'=>$hasSticker,
                    ':sn'=>$stickerNumber?:null,
                    ':c'=>$vehicleColor?:null,
                    ':m'=>$vehicleModel?:null,
                    ':d'=>$driverIsGuest?$fullName:($driverName?:null),
                    ':g'=>$driverIsGuest,
                ]);
            }

            $db->prepare("
                INSERT INTO activity_logs (visit_id, action_type, performed_by_user_id, office_id, description, ip_address)
                VALUES (:visit_id, 'pre_registration', NULL, NULL, :description, :ip)
            ")->execute([
                ':visit_id' => $visitId,
                ':description' => "Guest '{$fullName}' self-registered online with reference {$visitRef}",
                ':ip' => $_SERVER['REMOTE_ADDR'] ?? null,
            ]);

            $db->commit();
            $success = [
                'reference' => $visitRef,
                'qr_token' => $qrToken,
            ];
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Public pre-reg error: ' . $e->getMessage());
            $errors[] = 'Something went wrong. Please try again.';
        }
    }
}
$publicPageTitle = 'Pre-Register Your Visit - ' . APP_NAME;
$publicBackUrl = APP_URL . '/';
$publicBackLabel = 'Back to Home';
include __DIR__ . '/../includes/public_header.php';
?>

<div style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:32px 24px;">
<div style="max-width:680px;width:100%;">

  <?php if ($success): ?>
  <!-- SUCCESS -->
  <div class="card" style="text-align:center;padding:48px 32px;">
    <div style="width:64px;height:64px;background:var(--success-l);border-radius:50%;margin:0 auto 16px;display:flex;align-items:center;justify-content:center;">
      <i data-lucide="check-circle" style="width:32px;height:32px;color:var(--success);"></i>
    </div>
    <h2 style="font-size:1.375rem;font-weight:800;color:var(--text);margin-bottom:6px;">Visit Pre-Registered!</h2>
    <p style="color:var(--text-s);margin-bottom:20px;">Your visit has been successfully registered. Please save or screenshot your reference number below.</p>

    <div class="success-box" style="background:var(--bg);border:2px dashed var(--border);padding:20px;">
      <div style="font-size:.75rem;color:var(--text-m);font-weight:600;text-transform:uppercase;letter-spacing:.1em;margin-bottom:4px;">Your Reference Number</div>
      <div class="ref-big"><?= htmlspecialchars($success['reference']) ?></div>
    </div>

    <div style="margin-top:18px;display:grid;grid-template-columns:180px 1fr;gap:18px;align-items:center;text-align:left;">
      <div id="qrCodeBox" style="width:180px;height:180px;border:1px solid var(--border);border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;margin:0 auto;"></div>
      <div>
        <div style="font-size:.75rem;color:var(--text-m);font-weight:700;text-transform:uppercase;letter-spacing:.08em;margin-bottom:6px;">Temporary QR Code</div>
        <p style="font-size:.86rem;color:var(--text-s);line-height:1.6;margin:0 0 12px;">This QR is tied only to this visit. It is for quick lookup by the guard or office staff and becomes unusable for check-in/office receiving after checkout.</p>
        <div style="display:flex;gap:8px;flex-wrap:wrap;">
          <button type="button" class="btn btn-outline" onclick="openQrModal()"><i data-lucide="maximize"></i> Enlarge</button>
          <button type="button" class="btn btn-outline" onclick="window.print()"><i data-lucide="printer"></i> Print</button>
        </div>
      </div>
    </div>

    <div id="qrModal" style="display:none;position:fixed;inset:0;background:rgba(15,23,42,.72);z-index:500;align-items:center;justify-content:center;padding:24px;">
      <div style="background:#fff;border-radius:8px;padding:24px;max-width:420px;width:100%;text-align:center;box-shadow:0 24px 80px rgba(0,0,0,.35);">
        <div style="display:flex;justify-content:flex-end;margin-bottom:8px;"><button type="button" class="btn btn-outline" onclick="closeQrModal()" style="padding:8px;"><i data-lucide="x"></i></button></div>
        <div id="qrCodeLarge" style="width:300px;height:300px;margin:0 auto 16px;background:#fff;display:flex;align-items:center;justify-content:center;"></div>
        <div class="ref-big" style="font-size:1.2rem;"><?= htmlspecialchars($success['reference']) ?></div>
      </div>
    </div>

    <div class="info-box info" style="text-align:left;margin:20px 0;">
      <i data-lucide="info"></i>
      <div>
        <strong>What to do next:</strong>
        <ol style="margin:6px 0 0 16px;font-size:.82rem;line-height:1.8;">
          <li>Save or screenshot this reference number</li>
          <li>Present it at the university gate on your visit date</li>
          <li>The guard will verify and check you in quickly</li>
        </ol>
      </div>
    </div>

    <div style="display:flex;gap:12px;justify-content:center;">
      <a href="<?= APP_URL ?>/public/check_status.php?ref=<?= urlencode($success['reference']) ?>" class="btn btn-outline">
        <i data-lucide="search"></i> Check Status
      </a>
      <a href="<?= APP_URL ?>/" class="btn btn-primary">
        <i data-lucide="home"></i> Back to Home
      </a>
    </div>
  </div>

  <?php else: ?>
  <!-- FORM -->
  <div style="margin-bottom:22px;">
    <div class="page-title">Pre-Register Your Visit</div>
    <p style="color:var(--text-s);font-size:.9rem;margin-top:4px;">Fill out the form below. You'll receive a reference number to present at the university gate.</p>
  </div>

  <?php if (!empty($errors)): ?>
  <div class="error-box">
    <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix the following:</div>
    <ul><?php foreach ($errors as $err): ?><li><?= htmlspecialchars($err) ?></li><?php endforeach; ?></ul>
  </div>
  <?php endif; ?>

  <form method="POST" id="preregForm">
    <?= csrfField() ?>

    <!-- Personal Info -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <span><i data-lucide="user" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Your Information</span>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Full Name <span class="required-star">*</span></label>
          <div class="input-icon-wrap">
            <i data-lucide="user" class="input-icon"></i>
            <input type="text" name="full_name" class="form-control" placeholder="e.g. Juan Dela Cruz" value="<?= htmlspecialchars($_POST['full_name'] ?? '') ?>" required>
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Contact Number</label>
            <div class="input-icon-wrap">
              <i data-lucide="phone" class="input-icon"></i>
              <input type="text" name="contact_number" class="form-control" placeholder="09XXXXXXXXX" value="<?= htmlspecialchars($_POST['contact_number'] ?? '') ?>">
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Email Address</label>
            <div class="input-icon-wrap">
              <i data-lucide="mail" class="input-icon"></i>
              <input type="email" name="email" class="form-control" placeholder="your@email.com" value="<?= htmlspecialchars($_POST['email'] ?? '') ?>">
            </div>
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Company / Organization</label>
          <div class="input-icon-wrap">
            <i data-lucide="building-2" class="input-icon"></i>
            <input type="text" name="organization" class="form-control" placeholder="e.g. DepEd, BIR, Individual" value="<?= htmlspecialchars($_POST['organization'] ?? '') ?>">
          </div>
        </div>
        <div class="form-group">
          <label class="form-label">Valid ID to Present</label>
          <select name="id_type" class="form-select">
            <option value="">Select ID type</option>
            <?php foreach ($idTypes as $t): ?>
            <option value="<?= htmlspecialchars($t) ?>" <?= ($_POST['id_type'] ?? '') === $t ? 'selected' : '' ?>><?= htmlspecialchars($t) ?></option>
            <?php endforeach; ?>
          </select>
          <div class="form-hint">Bring this valid ID to the gate. The guard will verify it before check-in.</div>
        </div>
      </div>
    </div>

    <!-- Visit Details -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <span><i data-lucide="calendar" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit Details</span>
      </div>
      <div class="card-body">
        <div class="form-group">
          <label class="form-label">Purpose of Visit <span class="required-star">*</span></label>
          <textarea name="purpose_of_visit" class="form-control" rows="2" placeholder="e.g. Submit enrollment documents, Meeting with the Registrar" required><?= htmlspecialchars($_POST['purpose_of_visit'] ?? '') ?></textarea>
        </div>
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Planned Visit Date <span class="required-star">*</span></label>
            <input type="date" name="visit_date" class="form-control" value="<?= htmlspecialchars($_POST['visit_date'] ?? '') ?>" min="<?= date('Y-m-d') ?>" required>
          </div>
          <div class="form-group">
            <label class="form-label">Expected Arrival Time</label>
            <input type="time" name="expected_time" class="form-control" value="<?= htmlspecialchars($_POST['expected_time'] ?? '') ?>">
          </div>
          <div class="form-group">
            <label class="form-label">Expected Departure Time</label>
            <input type="time" name="expected_time_out" class="form-control" value="<?= htmlspecialchars($_POST['expected_time_out'] ?? '') ?>">
          </div>
        </div>
      </div>
    </div>

    <!-- Vehicle Details -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header" style="justify-content:space-between;">
        <span><i data-lucide="car" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Vehicle Details</span>
        <label class="toggle-wrap">
          <span style="font-size:.8rem;font-weight:500;color:var(--text-s);">Bringing Vehicle</span>
          <div class="toggle-switch">
            <input type="checkbox" name="has_vehicle" id="hasVehicle" <?= isset($_POST['has_vehicle']) ? 'checked' : '' ?> onchange="document.getElementById('vehicleFields').style.display=this.checked?'block':'none'">
            <span class="toggle-slider"></span>
          </div>
        </label>
      </div>
      <div class="card-body" id="vehicleFields" style="display:<?= isset($_POST['has_vehicle']) ? 'block' : 'none' ?>;">
        <div class="form-row col-3">
          <div class="form-group">
            <label class="form-label">Vehicle Type</label>
            <select name="vehicle_type" class="form-select">
              <?php foreach (['car'=>'Car','motorcycle'=>'Motorcycle','van'=>'Van','truck'=>'Truck','other'=>'Other'] as $v=>$l): ?>
              <option value="<?= $v ?>" <?= ($_POST['vehicle_type'] ?? 'car') === $v ? 'selected' : '' ?>><?= $l ?></option>
              <?php endforeach; ?>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Plate Number <span class="required-star">*</span></label>
            <input type="text" name="plate_number" class="form-control" value="<?= htmlspecialchars($_POST['plate_number'] ?? '') ?>" placeholder="ABC 1234" style="text-transform:uppercase;">
          </div>
        </div>
        <div class="check-item">
          <input type="checkbox" id="hasSticker" name="has_university_sticker" <?= isset($_POST['has_university_sticker']) ? 'checked' : '' ?> onchange="document.getElementById('stickerField').style.display=this.checked?'block':'none'">
          <label for="hasSticker">Vehicle has university sticker/pass</label>
        </div>
        <div id="stickerField" style="display:<?= isset($_POST['has_university_sticker']) ? 'block' : 'none' ?>;">
          <div class="form-group">
            <label class="form-label">Sticker / Pass Number</label>
            <input type="text" name="sticker_number" class="form-control" value="<?= htmlspecialchars($_POST['sticker_number'] ?? '') ?>" placeholder="Optional">
          </div>
        </div>
        <div class="form-row">
          <div class="form-group">
            <label class="form-label">Vehicle Color</label>
            <input type="text" name="vehicle_color" class="form-control" value="<?= htmlspecialchars($_POST['vehicle_color'] ?? '') ?>" placeholder="e.g. White">
          </div>
          <div class="form-group">
            <label class="form-label">Vehicle Model</label>
            <input type="text" name="vehicle_model" class="form-control" value="<?= htmlspecialchars($_POST['vehicle_model'] ?? '') ?>" placeholder="e.g. Toyota Vios">
          </div>
        </div>
        <div class="check-item">
          <input type="checkbox" id="driverIsGuest" name="driver_is_guest" <?= (!isset($_POST['has_vehicle']) || isset($_POST['driver_is_guest'])) ? 'checked' : '' ?> onchange="document.getElementById('driverField').style.display=this.checked?'none':'block'">
          <label for="driverIsGuest">I am the driver</label>
        </div>
        <div id="driverField" style="display:<?= (!isset($_POST['has_vehicle']) || isset($_POST['driver_is_guest'])) ? 'none' : 'block' ?>;">
          <div class="form-group">
            <label class="form-label">Driver Name</label>
            <input type="text" name="driver_name" class="form-control" value="<?= htmlspecialchars($_POST['driver_name'] ?? '') ?>" placeholder="Driver's full name">
          </div>
        </div>
      </div>
    </div>

    <!-- Destination Offices -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header">
        <span><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Office(s) to Visit <span class="required-star">*</span></span>
      </div>
      <div class="card-body">
        <p style="font-size:.82rem;color:var(--text-m);margin-bottom:12px;">Select the office(s) you plan to visit. You may choose more than one.</p>
        <div class="office-grid" id="officeGrid">
          <?php foreach ($offices as $o): ?>
          <label class="office-check" id="oc_<?= $o['office_id'] ?>">
            <input type="checkbox" name="dest_offices[]" value="<?= $o['office_id'] ?>"
              <?= in_array($o['office_id'], $_POST['dest_offices'] ?? []) ? 'checked' : '' ?>
              onchange="this.closest('.office-check').classList.toggle('checked', this.checked)">
            <div>
              <div style="font-weight:600;"><?= htmlspecialchars($o['office_name']) ?></div>
              <?php if ($o['office_location']): ?>
              <div style="font-size:.72rem;color:var(--text-m);"><?= htmlspecialchars($o['office_location']) ?></div>
              <?php endif; ?>
            </div>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Submit -->
    <div style="display:flex;gap:12px;">
      <button type="submit" class="btn btn-primary" style="flex:1;justify-content:center;padding:12px;font-size:.9375rem;">
        <i data-lucide="send"></i> Submit Pre-Registration
      </button>
      <a href="<?= APP_URL ?>/" class="btn btn-outline" style="padding:12px 24px;">Cancel</a>
    </div>
  </form>
  <?php endif; ?>

</div>
</div>

<?php
$publicExtraScripts = "// Init checked state\n";
$publicExtraScripts .= "document.querySelectorAll('.office-check input:checked').forEach(i => i.closest('.office-check').classList.add('checked'));\n";
if ($success) {
    $qrTokenJson = json_encode($success['qr_token']);
    $publicExtraScripts .= <<<JS
const visitQrToken = {$qrTokenJson};
function renderVisitQr() {
  const small = document.getElementById('qrCodeBox');
  const large = document.getElementById('qrCodeLarge');
  if (!window.QRCode || !small || !large) return;
  small.innerHTML = '';
  large.innerHTML = '';
  new QRCode(small, { text: visitQrToken, width: 160, height: 160, correctLevel: QRCode.CorrectLevel.M });
  new QRCode(large, { text: visitQrToken, width: 300, height: 300, correctLevel: QRCode.CorrectLevel.M });
}
function openQrModal() {
  const modal = document.getElementById('qrModal');
  if (modal) modal.style.display = 'flex';
}
function closeQrModal() {
  const modal = document.getElementById('qrModal');
  if (modal) modal.style.display = 'none';
}
const qrScript = document.createElement('script');
qrScript.src = 'https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js';
qrScript.onload = renderVisitQr;
document.head.appendChild(qrScript);
document.addEventListener('keydown', event => {
  if (event.key === 'Escape') closeQrModal();
});
JS;
}
include __DIR__ . '/../includes/public_footer.php';
?>

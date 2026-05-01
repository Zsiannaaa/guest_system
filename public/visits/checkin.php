<?php
/**
 * visits/checkin.php — Guard Check-In (pre-registered guests)
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits_module.php';
requireRole([ROLE_GUARD, ROLE_ADMIN]);
$pageTitle = 'Guest Check-In';
$db = getDB();
$searchResult = null; $searchError = ''; $visit = null; $destinations = [];
$directId = isset($_GET['id']) ? (int)$_GET['id'] : null;
$selectedGuestId = isset($_GET['guest_id']) ? (int)$_GET['guest_id'] : null;
$selectedGuest = null;
$knownCheckinErrors = [];
$query = trim($_GET['q'] ?? '');
$offices = $db->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();

$pendingStmt = $db->query("
    SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.expected_time_in,
           gv.registration_type, gv.overall_status,
           g.full_name AS guest_name, g.contact_number, g.organization, g.is_restricted,
           GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id = g.guest_id
    LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
    LEFT JOIN offices o ON vd.office_id = o.office_id
    WHERE gv.overall_status = 'pending'
      AND g.is_restricted = 0
    GROUP BY gv.visit_id
    ORDER BY gv.visit_date ASC, gv.created_at ASC
    LIMIT 300
");
$pendingVisits = $pendingStmt->fetchAll();

$restrictedPendingStmt = $db->query("
    SELECT gv.visit_id, gv.visit_reference, gv.visit_date,
           g.full_name AS guest_name, g.restriction_reason
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id = g.guest_id
    WHERE gv.overall_status = 'pending'
      AND g.is_restricted = 1
    ORDER BY gv.visit_date ASC, gv.created_at ASC
    LIMIT 20
");
$restrictedPendingVisits = $restrictedPendingStmt->fetchAll();

$knownGuestsStmt = $db->query("
    SELECT g.guest_id, g.full_name, g.contact_number, g.organization, g.id_type,
           g.is_restricted, g.restriction_reason,
           COUNT(gv.visit_id) AS visit_count,
           MAX(gv.visit_date) AS last_visit_date,
           ve.vehicle_type, ve.plate_number, ve.vehicle_color, ve.vehicle_model
    FROM guests g
    LEFT JOIN guest_visits gv ON g.guest_id = gv.guest_id
    LEFT JOIN (
        SELECT gv2.guest_id, MAX(ve2.vehicle_id) AS vehicle_id
        FROM vehicle_entries ve2
        JOIN guest_visits gv2 ON ve2.visit_id = gv2.visit_id
        GROUP BY gv2.guest_id
    ) latest_vehicle ON latest_vehicle.guest_id = g.guest_id
    LEFT JOIN vehicle_entries ve ON ve.vehicle_id = latest_vehicle.vehicle_id
    GROUP BY g.guest_id
    ORDER BY g.full_name ASC
    LIMIT 500
");
$knownGuests = $knownGuestsStmt->fetchAll();

if ($selectedGuestId) {
    $selectedGuestStmt = $db->prepare("
        SELECT g.*, ve.vehicle_type, ve.plate_number, ve.vehicle_color, ve.vehicle_model
        FROM guests g
        LEFT JOIN (
            SELECT gv2.guest_id, MAX(ve2.vehicle_id) AS vehicle_id
            FROM vehicle_entries ve2
            JOIN guest_visits gv2 ON ve2.visit_id = gv2.visit_id
            GROUP BY gv2.guest_id
        ) latest_vehicle ON latest_vehicle.guest_id = g.guest_id
        LEFT JOIN vehicle_entries ve ON ve.vehicle_id = latest_vehicle.vehicle_id
        WHERE g.guest_id = :gid
        LIMIT 1
    ");
    $selectedGuestStmt->execute([':gid' => $selectedGuestId]);
    $selectedGuest = $selectedGuestStmt->fetch();
}

if (isset($_GET['search']) || $directId) {
    if ($directId) {
        $stmt = $db->prepare("SELECT gv.*, g.full_name AS guest_name, g.contact_number, g.organization, g.id_type, g.is_restricted FROM guest_visits gv JOIN guests g ON gv.guest_id=g.guest_id WHERE gv.visit_id=:id LIMIT 1");
        $stmt->execute([':id'=>$directId]);
    } elseif (!empty($query)) {
        $stmt = $db->prepare("SELECT gv.*, g.full_name AS guest_name, g.contact_number, g.organization, g.id_type, g.is_restricted FROM guest_visits gv JOIN guests g ON gv.guest_id=g.guest_id WHERE gv.overall_status='pending' AND g.is_restricted=0 AND (gv.visit_reference=:q1 OR gv.qr_token=:q2 OR g.full_name LIKE :q3) ORDER BY gv.visit_date DESC LIMIT 5");
        $stmt->execute([':q1'=>$query, ':q2'=>$query, ':q3'=>"%{$query}%"]);
    }
    if (isset($stmt)) {
        $results = $stmt->fetchAll();
        if (count($results) === 1) { $visit = $results[0]; }
        elseif (count($results) > 1) { $searchResult = $results; }
        else { $searchError = 'No pending visit found. Use a saved guest record below to create a new check-in.'; }
    }
    if ($visit) {
        $destStmt = $db->prepare("SELECT vd.*, o.office_name FROM visit_destinations vd JOIN offices o ON vd.office_id=o.office_id WHERE vd.visit_id=:vid ORDER BY vd.sequence_no");
        $destStmt->execute([':vid'=>$visit['visit_id']]);
        $destinations = $destStmt->fetchAll();
    }
}

if (isPost() && isset($_POST['confirm_checkin'])) {
    verifyCsrf(APP_URL . '/public/visits/checkin.php');
    $visitId = (int)($_POST['visit_id'] ?? 0);
    $checkStmt = $db->prepare("SELECT gv.*, g.full_name AS guest_name, g.is_restricted FROM guest_visits gv JOIN guests g ON gv.guest_id=g.guest_id WHERE gv.visit_id=:vid AND gv.overall_status='pending'");
    $checkStmt->execute([':vid'=>$visitId]);
    $toCheckIn = $checkStmt->fetch();
    if (!$toCheckIn) { setFlash('error', 'This visit is no longer pending.'); redirect(APP_URL.'/public/visits/checkin.php'); }
    if ($toCheckIn['is_restricted']) { setFlash('error', 'This guest is restricted and cannot be checked in. Contact an administrator.'); redirect(APP_URL.'/public/visits/checkin.php'); }
    $db->prepare("UPDATE guest_visits SET overall_status='checked_in', actual_check_in=NOW(), processed_by_guard_id=:guard WHERE visit_id=:vid")
       ->execute([':guard'=>currentUserId(),':vid'=>$visitId]);
    logActivity($visitId, 'check_in', currentUserId(), null, "Checked in '{$toCheckIn['guest_name']}'");
    setFlash('success', "Guest <strong>{$toCheckIn['guest_name']}</strong> checked in successfully!");
    redirect(APP_URL.'/public/visits/view.php?id='.$visitId);
}

if (isPost() && isset($_POST['create_known_checkin'])) {
    verifyCsrf(APP_URL . '/public/visits/checkin.php');
    $guestId = (int)($_POST['guest_id'] ?? 0);
    $purpose = trim($_POST['purpose_of_visit'] ?? '');
    $visitDate = $_POST['visit_date'] ?? date('Y-m-d');
    $notes = trim($_POST['notes'] ?? '');
    $destOffices = $_POST['dest_offices'] ?? [];
    $reuseVehicle = isset($_POST['reuse_vehicle']) ? 1 : 0;
    $plateNumber = trim($_POST['plate_number'] ?? '');
    $vehicleType = trim($_POST['vehicle_type'] ?? 'car');
    $vehicleColor = trim($_POST['vehicle_color'] ?? '');
    $vehicleModel = trim($_POST['vehicle_model'] ?? '');

    $guestStmt = $db->prepare("SELECT * FROM guests WHERE guest_id=:gid LIMIT 1");
    $guestStmt->execute([':gid' => $guestId]);
    $guestForCheckin = $guestStmt->fetch();

    if (!$guestForCheckin) $knownCheckinErrors[] = 'Please select a saved guest record.';
    elseif ($guestForCheckin['is_restricted']) $knownCheckinErrors[] = 'This guest is restricted and cannot be checked in. Contact an administrator.';
    if (empty($purpose)) $knownCheckinErrors[] = 'Purpose of visit is required.';
    if (empty($visitDate)) $knownCheckinErrors[] = 'Visit date is required.';
    if (!empty($visitDate) && $visitDate < date('Y-m-d')) $knownCheckinErrors[] = 'Visit date cannot be in the past.';
    if (empty($destOffices)) $knownCheckinErrors[] = 'At least one destination office is required.';
    if ($reuseVehicle && empty($plateNumber)) $knownCheckinErrors[] = 'Plate number is required when vehicle entry is selected.';

    if (empty($knownCheckinErrors)) {
        try {
            $db->beginTransaction();
            $visitRef = generateVisitReference();
            $qrToken = generateQrToken();

            $db->prepare("
                INSERT INTO guest_visits
                    (guest_id, visit_reference, qr_token, registration_type, purpose_of_visit,
                     visit_date, actual_check_in, overall_status, has_vehicle, processed_by_guard_id, notes)
                VALUES
                    (:gid, :ref, :qr, 'walk_in', :purpose, :vdate, NOW(), 'checked_in', :vehicle, :guard, :notes)
            ")->execute([
                ':gid' => $guestId,
                ':ref' => $visitRef,
                ':qr' => $qrToken,
                ':purpose' => $purpose,
                ':vdate' => $visitDate,
                ':vehicle' => $reuseVehicle,
                ':guard' => currentUserId(),
                ':notes' => $notes ?: null,
            ]);
            $newVisitId = (int)$db->lastInsertId();

            foreach ($destOffices as $seq => $oid) {
                $db->prepare("
                    INSERT INTO visit_destinations (visit_id, office_id, sequence_no, destination_status, is_primary)
                    VALUES (:v, :o, :s, 'pending', :p)
                ")->execute([
                    ':v' => $newVisitId,
                    ':o' => (int)$oid,
                    ':s' => $seq + 1,
                    ':p' => $seq === 0 ? 1 : 0,
                ]);
            }

            if ($reuseVehicle) {
                $db->prepare("
                    INSERT INTO vehicle_entries
                        (visit_id, vehicle_type, plate_number, vehicle_color, vehicle_model, driver_name, is_driver_the_guest)
                    VALUES (:v, :t, :p, :c, :m, :d, 1)
                ")->execute([
                    ':v' => $newVisitId,
                    ':t' => $vehicleType,
                    ':p' => $plateNumber,
                    ':c' => $vehicleColor ?: null,
                    ':m' => $vehicleModel ?: null,
                    ':d' => $guestForCheckin['full_name'],
                ]);
            }

            logActivity($newVisitId, 'walk_in_registration', currentUserId(), null, "Known guest '{$guestForCheckin['full_name']}' checked in from saved record: {$visitRef}");
            logActivity($newVisitId, 'check_in', currentUserId(), null, "Guard checked in known guest '{$guestForCheckin['full_name']}'");
            $db->commit();
            setFlash('success', "Guest <strong>{$guestForCheckin['full_name']}</strong> checked in successfully! Reference: <strong>{$visitRef}</strong>");
            redirect(APP_URL . '/public/visits/view.php?id=' . $newVisitId);
        } catch (PDOException $e) {
            $db->rollBack();
            error_log('Known guest check-in error: ' . $e->getMessage());
            $knownCheckinErrors[] = 'A database error occurred. Please try again.';
        }
    }

    if (!empty($knownCheckinErrors)) {
        $selectedGuestId = $guestId;
        if ($guestId) {
            $selectedGuestStmt = $db->prepare("
                SELECT g.*, ve.vehicle_type, ve.plate_number, ve.vehicle_color, ve.vehicle_model
                FROM guests g
                LEFT JOIN (
                    SELECT gv2.guest_id, MAX(ve2.vehicle_id) AS vehicle_id
                    FROM vehicle_entries ve2
                    JOIN guest_visits gv2 ON ve2.visit_id = gv2.visit_id
                    GROUP BY gv2.guest_id
                ) latest_vehicle ON latest_vehicle.guest_id = g.guest_id
                LEFT JOIN vehicle_entries ve ON ve.vehicle_id = latest_vehicle.vehicle_id
                WHERE g.guest_id = :gid
                LIMIT 1
            ");
            $selectedGuestStmt->execute([':gid' => $guestId]);
            $selectedGuest = $selectedGuestStmt->fetch();
        }
    }
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Guest Check-In</div>
    <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>Check-In</li></ul>
  </div>
</div>

<!-- Search -->
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><i data-lucide="search" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Find Pending Guest Visit</div>
  <div class="card-body">
    <form method="GET" style="display:flex;gap:12px;">
      <div class="table-search" style="flex:1;max-width:none;">
        <i data-lucide="search" class="table-search-icon"></i>
        <input type="text" id="pendingSearchInput" name="q" value="<?= e($query) ?>" placeholder="Type reference number, QR code, or guest name..." autofocus autocomplete="off" style="padding:11px 12px 11px 36px;">
      </div>
      <button type="submit" name="search" class="btn btn-primary"><i data-lucide="search"></i> Search</button>
    </form>
  </div>
</div>

<?php if ($searchError): ?>
<div class="info-box warning"><i data-lucide="alert-triangle"></i><div><?= e($searchError) ?></div></div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header" style="justify-content:space-between;">
    <span><i data-lucide="clipboard-list" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Pending Arrivals</span>
    <span style="font-size:.8rem;color:var(--text-m);font-weight:500;"><span id="pendingVisibleCount"><?= count($pendingVisits) ?></span> of <?= count($pendingVisits) ?> pending</span>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="pendingVisitsTable">
      <thead><tr><th>Guest</th><th>Reference</th><th>Destination</th><th>Visit Date</th><th>Expected In</th><th>Type</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($pendingVisits as $r): ?>
      <tr data-search="<?= e(strtolower(($r['guest_name'] ?? '') . ' ' . ($r['visit_reference'] ?? '') . ' ' . ($r['contact_number'] ?? '') . ' ' . ($r['organization'] ?? '') . ' ' . ($r['destinations'] ?? ''))) ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar"><?= strtoupper(substr($r['guest_name'],0,1)) ?></div>
            <div>
              <div class="guest-name"><?= e($r['guest_name']) ?> <?= $r['is_restricted'] ? '<span class="badge badge-danger">Restricted</span>' : '' ?></div>
              <div class="guest-ref"><?= e($r['organization'] ?? '') ?></div>
            </div>
          </div>
        </td>
        <td><span class="ref-chip"><?= e($r['visit_reference']) ?></span></td>
        <td style="font-size:.83rem;"><?= e($r['destinations'] ?: 'No destination') ?></td>
        <td style="font-size:.83rem;"><?= formatDate($r['visit_date']) ?></td>
        <td style="font-size:.83rem;"><?= formatTime($r['expected_time_in']) ?></td>
        <td><span class="badge <?= $r['registration_type']==='walk_in'?'badge-warning':'badge-blue' ?>"><?= $r['registration_type']==='walk_in'?'Walk-in':'Pre-Reg' ?></span></td>
        <td><a href="?id=<?= $r['visit_id'] ?>" class="btn-tbl btn-tbl-primary"><i data-lucide="log-in"></i> Check In</a></td>
      </tr>
      <?php endforeach; ?>
      <tr id="pendingNoRows" style="display:<?= empty($pendingVisits) ? '' : 'none' ?>;">
        <td colspan="7"><div class="table-empty"><?= empty($pendingVisits) ? 'No pending arrivals yet.' : 'No pending visits match your search.' ?></div></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($restrictedPendingVisits)): ?>
<div class="info-box danger" style="margin-bottom:20px;">
  <i data-lucide="shield-alert"></i>
  <div>
    <strong><?= count($restrictedPendingVisits) ?> restricted pending visit<?= count($restrictedPendingVisits)!==1?'s':'' ?> blocked from check-in.</strong>
    These records still exist in history, but they are hidden from the normal pending check-in list.
  </div>
</div>
<?php endif; ?>

<div class="card" style="margin-bottom:20px;">
  <div class="card-header" style="justify-content:space-between;">
    <span><i data-lucide="contact" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Saved Guest Records</span>
    <span style="font-size:.8rem;color:var(--text-m);font-weight:500;"><span id="guestVisibleCount"><?= count($knownGuests) ?></span> of <?= count($knownGuests) ?> guests</span>
  </div>
  <div class="table-responsive">
    <table class="data-table" id="knownGuestsTable">
      <thead><tr><th>Guest</th><th>Contact</th><th>ID Type</th><th>Last Vehicle</th><th>Visits</th><th>Last Visit</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($knownGuests as $g): ?>
      <tr data-search="<?= e(strtolower(($g['full_name'] ?? '') . ' ' . ($g['contact_number'] ?? '') . ' ' . ($g['organization'] ?? '') . ' ' . ($g['id_type'] ?? '') . ' ' . ($g['plate_number'] ?? ''))) ?>">
        <td>
          <div class="guest-cell">
            <div class="guest-avatar" style="<?= $g['is_restricted'] ? 'background:var(--danger-l);color:var(--danger);' : '' ?>"><?= strtoupper(substr($g['full_name'],0,1)) ?></div>
            <div>
              <div class="guest-name"><?= e($g['full_name']) ?> <?= $g['is_restricted'] ? '<span class="badge badge-danger">Restricted</span>' : '' ?></div>
              <div class="guest-ref"><?= e($g['organization'] ?? '') ?></div>
            </div>
          </div>
        </td>
        <td style="font-size:.83rem;"><?= e($g['contact_number'] ?? '-') ?></td>
        <td style="font-size:.83rem;"><?= e($g['id_type'] ?? '-') ?></td>
        <td style="font-size:.83rem;"><?= $g['plate_number'] ? e(trim(($g['plate_number'] ?? '') . ' ' . ($g['vehicle_model'] ?? ''))) : '-' ?></td>
        <td><span class="badge badge-secondary"><?= (int)$g['visit_count'] ?></span></td>
        <td style="font-size:.83rem;"><?= formatDate($g['last_visit_date']) ?></td>
        <td>
          <?php if ($g['is_restricted']): ?>
          <span class="badge badge-danger">Blocked</span>
          <?php else: ?>
          <a href="?guest_id=<?= $g['guest_id'] ?>#knownGuestCheckin" class="btn-tbl btn-tbl-primary"><i data-lucide="log-in"></i> Use Record</a>
          <?php endif; ?>
        </td>
      </tr>
      <?php endforeach; ?>
      <tr id="guestNoRows" style="display:<?= empty($knownGuests) ? '' : 'none' ?>;">
        <td colspan="7"><div class="table-empty"><?= empty($knownGuests) ? 'No saved guest records yet.' : 'No saved guests match your search.' ?></div></td>
      </tr>
      </tbody>
    </table>
  </div>
</div>

<?php if (!empty($knownCheckinErrors)): ?>
<div class="error-box" id="knownGuestCheckinErrors">
  <div class="error-title"><i data-lucide="alert-circle" style="width:16px;height:16px;"></i> Please fix the following:</div>
  <ul><?php foreach ($knownCheckinErrors as $err): ?><li><?= e($err) ?></li><?php endforeach; ?></ul>
</div>
<?php endif; ?>

<?php if ($selectedGuest): ?>
<form method="POST" id="knownGuestCheckin">
<?= csrfField() ?>
<input type="hidden" name="guest_id" value="<?= (int)$selectedGuest['guest_id'] ?>">
<div style="display:grid;grid-template-columns:1fr 380px;gap:20px;margin-bottom:20px;">
  <div>
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="user-check" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Selected Guest Record</div>
      <div class="card-body">
        <?php if ($selectedGuest['is_restricted']): ?>
        <div class="info-box danger" style="margin-bottom:14px;">
          <i data-lucide="shield-alert"></i>
          <div><strong>This guest is restricted.</strong> Check-in is blocked until an administrator lifts the restriction.</div>
        </div>
        <?php endif; ?>
        <dl style="margin:0;">
          <div class="detail-row"><dt>Guest Name</dt><dd style="font-weight:700;"><?= e($selectedGuest['full_name']) ?></dd></div>
          <div class="detail-row"><dt>Contact</dt><dd><?= e($selectedGuest['contact_number'] ?? '-') ?></dd></div>
          <div class="detail-row"><dt>Organization</dt><dd><?= e($selectedGuest['organization'] ?? '-') ?></dd></div>
          <div class="detail-row"><dt>ID Type</dt><dd><?= e($selectedGuest['id_type'] ?? '-') ?></dd></div>
          <div class="detail-row"><dt>Last Vehicle</dt><dd><?= !empty($selectedGuest['plate_number']) ? e(trim(($selectedGuest['plate_number'] ?? '') . ' ' . ($selectedGuest['vehicle_model'] ?? ''))) : '-' ?></dd></div>
        </dl>
      </div>
    </div>

    <div class="card">
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

        <?php if (!empty($selectedGuest['plate_number'])): ?>
        <div class="check-item" style="margin-top:4px;">
          <input type="checkbox" id="reuseVehicle" name="reuse_vehicle" checked onchange="document.getElementById('knownVehicleFields').style.display=this.checked?'block':'none'">
          <label for="reuseVehicle">Use saved vehicle for this visit</label>
        </div>
        <div id="knownVehicleFields" style="margin-top:12px;">
          <div class="form-row">
            <div class="form-group">
              <label class="form-label">Vehicle Type</label>
              <select name="vehicle_type" class="form-select">
                <?php foreach (['car'=>'Car','motorcycle'=>'Motorcycle','van'=>'Van','truck'=>'Truck','other'=>'Other'] as $v=>$l): ?>
                <option value="<?= $v ?>" <?= ($selectedGuest['vehicle_type'] ?? 'car')===$v ? 'selected' : '' ?>><?= $l ?></option>
                <?php endforeach; ?>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Plate Number</label>
              <input type="text" name="plate_number" class="form-control" value="<?= e($selectedGuest['plate_number'] ?? '') ?>" style="text-transform:uppercase;">
            </div>
          </div>
          <div class="form-row">
            <div class="form-group"><label class="form-label">Color</label><input type="text" name="vehicle_color" class="form-control" value="<?= e($selectedGuest['vehicle_color'] ?? '') ?>"></div>
            <div class="form-group"><label class="form-label">Model</label><input type="text" name="vehicle_model" class="form-control" value="<?= e($selectedGuest['vehicle_model'] ?? '') ?>"></div>
          </div>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </div>

  <div>
    <div class="card" style="position:sticky;top:80px;">
      <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Destination Office(s) <span class="required-star">*</span></div>
      <div class="card-body">
        <div style="display:flex;flex-direction:column;gap:2px;">
          <?php foreach ($offices as $o): ?>
          <label class="office-check <?= in_array($o['office_id'], $_POST['dest_offices'] ?? []) ? 'checked' : '' ?>">
            <input type="checkbox" name="dest_offices[]" value="<?= $o['office_id'] ?>" <?= in_array($o['office_id'], $_POST['dest_offices'] ?? []) ? 'checked' : '' ?> onchange="this.closest('.office-check').classList.toggle('checked',this.checked)">
            <span style="font-weight:500;"><?= e($o['office_name']) ?></span>
          </label>
          <?php endforeach; ?>
        </div>
      </div>
      <div class="card-footer" style="display:flex;flex-direction:column;gap:8px;">
        <button type="submit" name="create_known_checkin" class="btn btn-primary w-100" style="justify-content:center;padding:12px;" <?= $selectedGuest['is_restricted'] ? 'disabled' : '' ?>>
          <i data-lucide="log-in"></i> Check In Guest
        </button>
        <a href="<?= APP_URL ?>/public/visits/checkin.php" class="btn btn-outline w-100" style="justify-content:center;">Cancel</a>
      </div>
    </div>
  </div>
</div>
</form>
<?php endif; ?>

<?php if ($searchResult): ?>
<div class="card" style="margin-bottom:20px;">
  <div class="card-header"><i data-lucide="users" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Multiple Results — Select One</div>
  <div class="card-body p-0"><div class="table-responsive">
    <table class="data-table">
      <thead><tr><th>Reference</th><th>Guest</th><th>Visit Date</th><th>Status</th><th></th></tr></thead>
      <tbody>
      <?php foreach ($searchResult as $r): ?>
      <tr>
        <td><span class="ref-chip"><?= e($r['visit_reference']) ?></span></td>
        <td class="fw-600"><?= e($r['guest_name']) ?></td>
        <td><?= formatDate($r['visit_date']) ?></td>
        <td><span class="badge badge-warning"><?= statusLabel($r['overall_status']) ?></span></td>
        <td><a href="?id=<?= $r['visit_id'] ?>" class="btn-tbl btn-tbl-primary"><i data-lucide="arrow-right"></i> Select</a></td>
      </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div></div>
</div>
<?php endif; ?>

<?php if ($visit): ?>
<style>
.checkin-modal-backdrop{position:fixed;inset:0;background:rgba(15,23,42,.48);z-index:300;display:flex;align-items:center;justify-content:center;padding:24px;}
.checkin-modal{width:min(1040px,96vw);max-height:92vh;overflow:auto;background:#fff;border:1px solid var(--border);border-radius:8px;box-shadow:0 22px 70px rgba(15,23,42,.28);}
.checkin-modal-head{position:sticky;top:0;z-index:2;display:flex;align-items:center;justify-content:space-between;padding:16px 20px;background:#fff;border-bottom:1px solid var(--border);}
.checkin-modal-title{display:flex;align-items:center;gap:8px;font-size:1rem;font-weight:800;color:var(--primary);}
.checkin-modal-close{width:36px;height:36px;border:1px solid var(--border);border-radius:8px;background:#fff;display:flex;align-items:center;justify-content:center;color:var(--text-s);cursor:pointer;}
.checkin-modal-close:hover{background:#f8fafc;color:var(--danger);border-color:#fecaca;}
.checkin-modal-grid{display:grid;grid-template-columns:minmax(0,1fr) 360px;gap:18px;padding:18px;background:var(--bg);}
@media(max-width:900px){.checkin-modal-backdrop{align-items:flex-start;padding:12px;}.checkin-modal-grid{grid-template-columns:1fr;}.checkin-modal{max-height:96vh;}}
</style>
<div class="checkin-modal-backdrop" id="checkinModalBackdrop" role="dialog" aria-modal="true" aria-labelledby="checkinModalTitle">
<div class="checkin-modal">
  <div class="checkin-modal-head">
    <div class="checkin-modal-title" id="checkinModalTitle">
      <i data-lucide="log-in" style="width:18px;height:18px;"></i>
      Check In <?= e($visit['guest_name']) ?>
    </div>
    <button type="button" class="checkin-modal-close" id="checkinModalClose" title="Close">
      <i data-lucide="x" style="width:18px;height:18px;"></i>
    </button>
  </div>
<div class="checkin-modal-grid">
  <!-- Visit Details -->
  <div class="card">
    <div class="card-header" style="justify-content:space-between;">
      <span><i data-lucide="clipboard-list" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit Details</span>
      <?php if ($visit['is_restricted']): ?>
      <span class="badge badge-danger"><i data-lucide="shield-alert" style="width:12px;height:12px;"></i> RESTRICTED</span>
      <?php endif; ?>
    </div>
    <div class="card-body">
      <?php if ($visit['is_restricted']): ?>
      <div class="info-box danger" style="margin-bottom:16px;">
        <i data-lucide="shield-alert"></i>
        <div><strong>WARNING: This guest is restricted!</strong><br>Do NOT allow entry without admin clearance.</div>
      </div>
      <?php endif; ?>
      <dl style="margin:0;">
        <div class="detail-row"><dt>Reference</dt><dd><span class="ref-chip"><?= e($visit['visit_reference']) ?></span></dd></div>
        <div class="detail-row"><dt>Guest Name</dt><dd style="font-weight:700;"><?= e($visit['guest_name']) ?></dd></div>
        <div class="detail-row"><dt>Organization</dt><dd><?= e($visit['organization'] ?? '—') ?></dd></div>
        <div class="detail-row"><dt>ID Type</dt><dd><?= e($visit['id_type'] ?? '—') ?></dd></div>
        <div class="detail-row"><dt>Purpose</dt><dd><?= e($visit['purpose_of_visit']) ?></dd></div>
        <div class="detail-row"><dt>Visit Date</dt><dd><?= formatDate($visit['visit_date']) ?></dd></div>
        <div class="detail-row"><dt>Expected In</dt><dd><?= formatTime($visit['expected_time_in']) ?></dd></div>
        <div class="detail-row"><dt>Vehicle</dt><dd><?= $visit['has_vehicle'] ? '<span class="badge badge-info"><i data-lucide="car" style="width:11px;height:11px;"></i> Yes</span>' : '<span style="color:var(--text-m)">No</span>' ?></dd></div>
        <div class="detail-row"><dt>Status</dt><dd><span class="badge badge-warning"><?= statusLabel($visit['overall_status']) ?></span></dd></div>
      </dl>
    </div>
  </div>

  <div>
    <!-- Destinations -->
    <div class="card" style="margin-bottom:16px;">
      <div class="card-header"><i data-lucide="building-2" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Destination Office(s)</div>
      <div class="card-body" style="padding:0;">
        <?php foreach ($destinations as $d): ?>
        <div style="display:flex;align-items:center;justify-content:space-between;padding:12px 18px;border-bottom:1px solid var(--border);">
          <span style="display:flex;align-items:center;gap:8px;">
            <span class="badge badge-secondary">#<?= $d['sequence_no'] ?></span>
            <span style="font-weight:600;font-size:.875rem;"><?= e($d['office_name']) ?></span>
          </span>
          <span class="badge <?= $d['destination_status']==='pending' ? 'badge-warning' : 'badge-success' ?>">
            <?= statusLabel($d['destination_status']) ?>
          </span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Check-In Confirmation -->
    <?php if ($visit['overall_status'] === 'pending' && !$visit['is_restricted']): ?>
    <div class="card" style="border-color:var(--success);">
      <div class="card-header" style="background:var(--success);color:#fff;border-color:var(--success);">
        <span><i data-lucide="check-circle" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Confirm Check-In</span>
      </div>
      <div class="card-body">
        <p style="font-size:.9rem;margin-bottom:14px;">
          You are about to check in <strong><?= e($visit['guest_name']) ?></strong>.<br>
          Please verify the guest's valid ID before proceeding.
        </p>
        <form method="POST">
        <?= csrfField() ?>
          <input type="hidden" name="visit_id" value="<?= $visit['visit_id'] ?>">
          <div class="check-item" style="margin-bottom:14px;">
            <input type="checkbox" id="idVerified" required>
            <label for="idVerified">I have verified the guest's valid ID.</label>
          </div>
          <button type="submit" name="confirm_checkin" class="btn btn-success w-100" style="justify-content:center;padding:12px;font-size:.95rem;">
            <i data-lucide="log-in"></i> Confirm Check-In
          </button>
        </form>
      </div>
    </div>
    <?php elseif ($visit['overall_status'] !== 'pending'): ?>
    <div class="info-box info"><i data-lucide="info"></i><div>This visit is <strong><?= statusLabel($visit['overall_status']) ?></strong> and cannot be checked in again.</div></div>
    <?php endif; ?>
  </div>
</div>
</div>
</div>
<?php endif; ?>

<script>
const pendingSearchInput = document.getElementById('pendingSearchInput');
const pendingTable = document.getElementById('pendingVisitsTable');
const pendingVisibleCount = document.getElementById('pendingVisibleCount');
const pendingNoRows = document.getElementById('pendingNoRows');
const knownGuestsTable = document.getElementById('knownGuestsTable');
const guestVisibleCount = document.getElementById('guestVisibleCount');
const guestNoRows = document.getElementById('guestNoRows');
const checkinModalBackdrop = document.getElementById('checkinModalBackdrop');
const checkinModalClose = document.getElementById('checkinModalClose');

function closeCheckinModal() {
  window.location.href = '<?= APP_URL ?>/public/visits/checkin.php';
}

function filterPendingVisits() {
  if (!pendingTable) return;
  const q = (pendingSearchInput?.value || '').toLowerCase().trim();
  let visible = 0;
  pendingTable.querySelectorAll('tbody tr[data-search]').forEach(row => {
    const ok = !q || row.dataset.search.includes(q);
    row.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });
  if (pendingVisibleCount) pendingVisibleCount.textContent = visible;
  if (pendingNoRows) {
    pendingNoRows.style.display = visible ? 'none' : '';
    const empty = pendingNoRows.querySelector('.table-empty');
    if (empty) empty.textContent = q ? 'No pending visits match your search.' : 'No pending arrivals yet.';
  }
}

function filterKnownGuests() {
  if (!knownGuestsTable) return;
  const q = (pendingSearchInput?.value || '').toLowerCase().trim();
  let visible = 0;
  knownGuestsTable.querySelectorAll('tbody tr[data-search]').forEach(row => {
    const ok = !q || row.dataset.search.includes(q);
    row.style.display = ok ? '' : 'none';
    if (ok) visible++;
  });
  if (guestVisibleCount) guestVisibleCount.textContent = visible;
  if (guestNoRows) {
    guestNoRows.style.display = visible ? 'none' : '';
    const empty = guestNoRows.querySelector('.table-empty');
    if (empty) empty.textContent = q ? 'No saved guests match your search.' : 'No saved guest records yet.';
  }
}

pendingSearchInput?.addEventListener('input', () => {
  filterPendingVisits();
  filterKnownGuests();
});
filterPendingVisits();
filterKnownGuests();
checkinModalClose?.addEventListener('click', closeCheckinModal);
checkinModalBackdrop?.addEventListener('click', event => {
  if (event.target === checkinModalBackdrop) closeCheckinModal();
});
document.addEventListener('keydown', event => {
  if (event.key === 'Escape' && checkinModalBackdrop) closeCheckinModal();
});
lucide.createIcons();
</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

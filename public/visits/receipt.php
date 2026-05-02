<?php
/**
 * visits/receipt.php - Printable gate slip for checked-in visitors.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/visits/visits_module.php';

requireLogin();

if (!isAdminOrGuard()) {
    setFlash('error', 'You do not have permission to print visitor gate slips.');
    redirect(getDashboardUrl());
}

$db = getDB();
$visitId = requireValidId($_GET['id'] ?? 0, getDashboardUrl());
$visit = getVisitDetails($db, $visitId);

if (!$visit) {
    setFlash('error', 'Visit not found.');
    redirect(getDashboardUrl());
}

if (isOfficeStaff() && !canOfficeViewVisit($db, $visitId, (int) currentOfficeId())) {
    setFlash('error', 'No permission.');
    redirect(getDashboardUrl());
}

$destinations = getVisitDestinationsWithOffice($db, $visitId);
$vehicle = getVehicleForVisit($db, $visitId);
$qrText = $visit['qr_token'] ?: $visit['visit_reference'];
$departmentList = implode(', ', array_map(static fn($d) => $d['office_name'], $destinations));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Gate Slip - <?= e($visit['visit_reference']) ?></title>
<style>
*{box-sizing:border-box}
body{margin:0;background:#eef3ee;color:#172033;font-family:Arial,Helvetica,sans-serif;font-size:13px}
.toolbar{position:sticky;top:0;z-index:5;display:flex;justify-content:space-between;align-items:center;gap:12px;padding:12px 18px;background:#fff;border-bottom:1px solid #d8e0d8}
.toolbar a,.toolbar button{border:1px solid #9ab19b;background:#fff;color:#0f5f2c;border-radius:6px;padding:9px 13px;font-weight:700;text-decoration:none;cursor:pointer}
.toolbar button{background:#13823b;color:#fff;border-color:#13823b}
.page{width:760px;max-width:calc(100vw - 32px);margin:22px auto;background:#fff;border:1px solid #cfd8cf;box-shadow:0 12px 34px rgba(23,32,51,.12);padding:24px}
.slip-head{display:flex;justify-content:space-between;gap:18px;border-bottom:3px solid #0f6b31;padding-bottom:14px}
.brand{display:flex;gap:12px;align-items:center}
.brand img{width:58px;height:58px;object-fit:contain}
.brand-title{font-size:16px;font-weight:800;color:#075c27;text-transform:uppercase;line-height:1.2}
.brand-sub{font-size:12px;font-weight:700;letter-spacing:.12em;color:#1f7b3d;margin-top:2px}
.slip-title{text-align:right}
.slip-title h1{margin:0;color:#172033;font-size:22px}
.slip-title div{margin-top:5px;color:#536273}
.ref-box{margin:18px 0;display:grid;grid-template-columns:1fr 180px;gap:18px;align-items:center}
.ref-main{border:2px dashed #9ab19b;padding:14px;text-align:center;background:#f8fbf8}
.label{font-size:10px;color:#657386;font-weight:800;text-transform:uppercase;letter-spacing:.1em}
.ref{font-size:24px;font-weight:900;letter-spacing:.04em;color:#0f6b31;margin-top:5px}
.qr{width:180px;height:180px;border:1px solid #cfd8cf;display:flex;align-items:center;justify-content:center;background:#fff}
.grid{display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px}
.section{border:1px solid #d8e0d8;border-radius:6px;overflow:hidden}
.section h2{margin:0;padding:9px 12px;background:#edf6ed;color:#0f5f2c;font-size:13px;text-transform:uppercase;letter-spacing:.05em}
.rows{padding:10px 12px}
.row{display:grid;grid-template-columns:120px 1fr;gap:8px;padding:5px 0;border-bottom:1px solid #eef2ee}
.row:last-child{border-bottom:0}
.row dt{font-weight:700;color:#536273}
.row dd{margin:0;font-weight:600;color:#172033}
.dest-table{width:100%;border-collapse:collapse}
.dest-table th,.dest-table td{border:1px solid #d8e0d8;padding:9px;text-align:left;vertical-align:top}
.dest-table th{background:#f8fbf8;color:#536273;font-size:11px;text-transform:uppercase}
.line{height:34px;border-bottom:1px solid #172033;margin-top:2px}
.note{margin-top:14px;padding:10px 12px;background:#fffbea;border:1px solid #fde68a;border-radius:6px;color:#654a05;line-height:1.5}
.foot{display:flex;justify-content:space-between;gap:12px;margin-top:18px;padding-top:12px;border-top:1px solid #d8e0d8;color:#657386;font-size:11px}
@media print{
  body{background:#fff}
  .toolbar{display:none}
  .page{width:auto;max-width:none;margin:0;border:0;box-shadow:none;padding:0}
  @page{margin:12mm}
}
@media(max-width:720px){
  .slip-head,.ref-box,.grid{grid-template-columns:1fr;display:grid}
  .slip-title{text-align:left}
  .qr{margin:0 auto}
}
</style>
</head>
<body>
<div class="toolbar">
  <a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $visitId ?>">Back to Visit</a>
  <button type="button" onclick="window.print()">Print Gate Slip</button>
</div>

<main class="page">
  <header class="slip-head">
    <div class="brand">
      <img src="<?= APP_URL ?>/assets/images/SPUD-LOGO1.png" alt="SPUD logo">
      <div>
        <div class="brand-title">St. Paul University</div>
        <div class="brand-sub">Dumaguete</div>
      </div>
    </div>
    <div class="slip-title">
      <h1>Visitor Gate Slip</h1>
      <div>Guest Monitoring and Visitor Management System</div>
    </div>
  </header>

  <section class="ref-box">
    <div class="ref-main">
      <div class="label">Visit Reference Number</div>
      <div class="ref"><?= e($visit['visit_reference']) ?></div>
      <div style="margin-top:8px;color:#536273;font-size:12px;">QR/reference is for fast lookup only. Guard still verifies a valid ID.</div>
    </div>
    <div>
      <div id="qrCodeBox" class="qr">
        <span style="color:#657386;font-size:11px;text-align:center;padding:10px;">QR loading...<br><?= e($qrText) ?></span>
      </div>
    </div>
  </section>

  <section class="grid">
    <div class="section">
      <h2>Guest Details</h2>
      <dl class="rows">
        <div class="row"><dt>Name</dt><dd><?= e($visit['guest_name']) ?></dd></div>
        <div class="row"><dt>Organization</dt><dd><?= e($visit['organization'] ?: '-') ?></dd></div>
        <div class="row"><dt>Contact</dt><dd><?= e($visit['contact_number'] ?: '-') ?></dd></div>
        <div class="row"><dt>ID Type</dt><dd><?= e($visit['id_type'] ?: '-') ?></dd></div>
        <div class="row"><dt>Purpose</dt><dd><?= e($visit['purpose_of_visit']) ?></dd></div>
      </dl>
    </div>

    <div class="section">
      <h2>Gate Timing</h2>
      <dl class="rows">
        <div class="row"><dt>Visit Date</dt><dd><?= formatDate($visit['visit_date']) ?></dd></div>
        <div class="row"><dt>Expected In</dt><dd><?= formatTime($visit['expected_time_in']) ?></dd></div>
        <div class="row"><dt>Checked In</dt><dd><?= formatDateTime($visit['actual_check_in']) ?></dd></div>
        <div class="row"><dt>Checked Out</dt><dd><?= formatDateTime($visit['actual_check_out']) ?></dd></div>
        <div class="row"><dt>Processed By</dt><dd><?= e($visit['guard_name'] ?: '-') ?></dd></div>
      </dl>
    </div>
  </section>

  <?php if ($vehicle): ?>
  <section class="section" style="margin-bottom:14px;">
    <h2>Vehicle Details</h2>
    <dl class="rows">
      <div class="row"><dt>Type / Plate</dt><dd><?= e(ucfirst($vehicle['vehicle_type'])) ?> - <?= e($vehicle['plate_number']) ?></dd></div>
      <div class="row"><dt>Sticker / Pass</dt><dd><?= !empty($vehicle['has_university_sticker']) ? 'Yes' : 'No' ?><?= !empty($vehicle['sticker_number']) ? ' - ' . e($vehicle['sticker_number']) : '' ?></dd></div>
      <div class="row"><dt>Color / Model</dt><dd><?= e(trim(($vehicle['vehicle_color'] ?: '-') . ' / ' . ($vehicle['vehicle_model'] ?: '-'))) ?></dd></div>
    </dl>
  </section>
  <?php endif; ?>

  <section class="section">
    <h2>Office / Employee Visited Acknowledgment</h2>
    <table class="dest-table">
      <thead>
        <tr>
          <th style="width:28%;">Department / Office</th>
          <th>Name of Employee Visited</th>
          <th>Employee Signature</th>
          <th style="width:16%;">Time</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($destinations as $d): ?>
        <tr>
          <td><strong><?= e($d['office_name']) ?></strong></td>
          <td><div class="line"></div></td>
          <td><div class="line"></div></td>
          <td><div class="line"></div></td>
        </tr>
        <?php endforeach; ?>
        <?php if (empty($destinations)): ?>
        <tr>
          <td><strong><?= e($departmentList ?: 'Department / Office') ?></strong></td>
          <td><div class="line"></div></td>
          <td><div class="line"></div></td>
          <td><div class="line"></div></td>
        </tr>
        <?php endif; ?>
      </tbody>
    </table>
  </section>

  <div class="note">
    This slip should be presented to the destination office. The office employee may write their name and signature above for the guard's physical record, while the digital system keeps the official check-in/check-out timestamps.
  </div>

  <footer class="foot">
    <div>Printed: <?= date('M d, Y h:i A') ?></div>
    <div>Status: <?= e(statusLabel($visit['overall_status'])) ?></div>
  </footer>
</main>

<script src="https://cdn.jsdelivr.net/npm/qrcodejs@1.0.0/qrcode.min.js"></script>
<script>
const qrBox = document.getElementById('qrCodeBox');
const qrText = <?= json_encode($qrText) ?>;
if (qrBox && window.QRCode) {
  qrBox.innerHTML = '';
  new QRCode(qrBox, { text: qrText, width: 168, height: 168, correctLevel: QRCode.CorrectLevel.M });
}
</script>
</body>
</html>

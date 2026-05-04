<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Public status lookup page where a guest can check a submitted visit reference.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * public/check_status.php - Guest Visit Status Checker (No login)
 */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../includes/helpers.php';

$db = getDB();
$result = null;
$lookupError = '';
$ref = trim($_GET['ref'] ?? $_POST['ref'] ?? '');
$lookupMode = str_starts_with(strtoupper($ref), 'QR-') ? 'qr' : 'reference';

if ($ref !== '') {
    if ($lookupMode === 'qr') {
        // Study query: Prepared SQL: reads rows from guest_visits, guests, visit_destinations, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
        $stmt = $db->prepare("
            SELECT gv.visit_reference, gv.visit_date, gv.overall_status, gv.registration_type,
                   gv.actual_check_in, gv.actual_check_out, gv.purpose_of_visit,
                   g.full_name,
                   GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
            FROM guest_visits gv
            JOIN guests g ON gv.guest_id = g.guest_id
            LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
            LEFT JOIN offices o ON vd.office_id = o.office_id
            WHERE gv.qr_token = :qr_token
            GROUP BY gv.visit_id
            LIMIT 1
        ");
        $stmt->execute([':qr_token' => $ref]);
        $result = $stmt->fetch();
    } else {
        $lookupError = 'For privacy, public status lookup now requires the visit QR token. Reference numbers are verified only by guards or authorized staff.';
    }
}

$publicPageTitle = 'Check Visit Status - ' . APP_NAME;
$publicBackUrl = APP_URL . '/';
$publicBackLabel = 'Back to Home';
include __DIR__ . '/../includes/public_header.php';
?>

<div style="flex:1;display:flex;align-items:flex-start;justify-content:center;padding:40px 24px;">
<div style="max-width:520px;width:100%;">

  <div style="text-align:center;margin-bottom:28px;">
    <div style="width:56px;height:56px;background:var(--accent-l);border-radius:50%;margin:0 auto 14px;display:flex;align-items:center;justify-content:center;">
      <i data-lucide="search" style="width:26px;height:26px;color:var(--accent);"></i>
    </div>
    <div class="page-title">Check Visit Status</div>
    <p style="color:var(--text-s);font-size:.9rem;margin-top:4px;">Enter the QR token from your visit confirmation to check your status.</p>
  </div>

  <form method="GET" class="card" style="margin-bottom:20px;">
    <div class="card-body">
      <div class="input-icon-wrap" style="margin-bottom:12px;">
        <i data-lucide="hash" class="input-icon"></i>
        <input type="text" name="ref" class="form-control" placeholder="e.g. QR-A1B2C3D4E5F6A7B8" value="<?= e($ref) ?>" required autofocus style="font-size:1rem;padding:12px 12px 12px 38px;font-weight:600;">
      </div>
      <button type="submit" class="btn btn-primary w-100" style="justify-content:center;padding:11px;">
        <i data-lucide="search"></i> Look Up
      </button>
    </div>
  </form>

  <?php if ($lookupError): ?>
  <div class="info-box warning">
    <i data-lucide="shield-alert"></i>
    <div><?= e($lookupError) ?></div>
  </div>
  <?php elseif ($ref !== '' && !$result): ?>
  <div class="info-box danger">
    <i data-lucide="alert-circle"></i>
    <div>No visit found with that QR token. Please check and try again.</div>
  </div>
  <?php endif; ?>

  <?php if ($result): ?>
  <?php
    $sc = match($result['overall_status']) {
      'pending' => 'badge-warning',
      'checked_in' => 'badge-success',
      'checked_out' => 'badge-secondary',
      'cancelled' => 'badge-danger',
      'overstayed' => 'badge-danger',
      default => 'badge-secondary',
    };
  ?>
  <div class="card">
    <div class="card-header">
      <span><i data-lucide="ticket" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Visit Status</span>
      <span class="badge <?= $sc ?>" style="font-size:.8rem;padding:5px 14px;">
        <?= statusLabel($result['overall_status']) ?>
      </span>
    </div>
    <div class="card-body">
      <dl style="margin:0;">
        <div class="detail-row">
          <dt>Reference</dt>
          <dd><span class="ref-chip" style="font-size:.85rem;"><?= e($result['visit_reference']) ?></span></dd>
        </div>
        <div class="detail-row">
          <dt>Guest Name</dt>
          <dd><?= e($result['full_name']) ?></dd>
        </div>
        <div class="detail-row">
          <dt>Visit Date</dt>
          <dd><?= formatDate($result['visit_date']) ?></dd>
        </div>
        <div class="detail-row">
          <dt>Purpose</dt>
          <dd style="max-width:240px;text-align:right;"><?= e($result['purpose_of_visit']) ?></dd>
        </div>
        <div class="detail-row">
          <dt>Office(s)</dt>
          <dd><?= e($result['destinations'] ?? '-') ?></dd>
        </div>
        <?php if ($result['actual_check_in']): ?>
        <div class="detail-row">
          <dt>Checked In</dt>
          <dd><?= formatDateTime($result['actual_check_in']) ?></dd>
        </div>
        <?php endif; ?>
        <?php if ($result['actual_check_out']): ?>
        <div class="detail-row">
          <dt>Checked Out</dt>
          <dd><?= formatDateTime($result['actual_check_out']) ?></dd>
        </div>
        <?php endif; ?>
      </dl>
    </div>
  </div>
  <?php endif; ?>

</div>
</div>

<?php include __DIR__ . '/../includes/public_footer.php'; ?>

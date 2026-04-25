<?php
/**
 * office/lookup.php - Receive expected or unexpected visitors
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireRole([ROLE_OFFICE_STAFF, ROLE_ADMIN]);

$pageTitle = 'Receive Visitor';
$db = getDB();
$officeId = isAdmin() ? null : currentOfficeId();
$q = trim($_GET['q'] ?? '');
$results = [];

if (isPost() && isset($_POST['receive_unexpected'])) {
    verifyCsrf(APP_URL . '/public/office/lookup.php');

    if (!$officeId) {
        setFlash('error', 'Only an office staff account can receive a visitor into an office.');
        redirect(APP_URL . '/public/office/lookup.php');
    }

    $visitId = (int)($_POST['visit_id'] ?? 0);
    $receiveMode = ($_POST['receive_mode'] ?? '') === 'complete' ? 'complete' : 'arrive';
    $visitStmt = $db->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.overall_status,
               g.full_name AS guest_name
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE gv.visit_id = :vid AND gv.overall_status = 'checked_in'
        LIMIT 1
    ");
    $visitStmt->execute([':vid' => $visitId]);
    $visit = $visitStmt->fetch();

    if (!$visit) {
        setFlash('error', 'This visitor is not currently checked in at the gate.');
        redirect(APP_URL . '/public/office/lookup.php');
    }

    $existingStmt = $db->prepare("SELECT destination_id FROM visit_destinations WHERE visit_id=:vid AND office_id=:oid LIMIT 1");
    $existingStmt->execute([':vid' => $visitId, ':oid' => $officeId]);
    $existingDestId = $existingStmt->fetchColumn();

    if ($existingDestId) {
        if ($receiveMode === 'complete') {
            $db->prepare("
                UPDATE visit_destinations
                SET destination_status='completed',
                    received_by_user_id = COALESCE(received_by_user_id, :uid),
                    arrival_time = COALESCE(arrival_time, NOW()),
                    completed_time = COALESCE(completed_time, NOW())
                WHERE destination_id=:did
            ")->execute([':uid' => currentUserId(), ':did' => $existingDestId]);

            logActivity($visitId, 'destination_completed', currentUserId(), $officeId, "Office recorded completed visit for '{$visit['guest_name']}' using reference {$visit['visit_reference']}");
            setFlash('success', "<strong>{$visit['guest_name']}</strong> recorded as completed at your office.");
            redirect(APP_URL . '/public/office/history.php');
        }

        $db->prepare("
            UPDATE visit_destinations
            SET destination_status = CASE WHEN destination_status='pending' THEN 'arrived' ELSE destination_status END,
                received_by_user_id = COALESCE(received_by_user_id, :uid),
                arrival_time = COALESCE(arrival_time, NOW())
            WHERE destination_id = :did
        ")->execute([':uid' => currentUserId(), ':did' => $existingDestId]);

        logActivity($visitId, 'destination_confirmed', currentUserId(), $officeId, "Office received '{$visit['guest_name']}' using reference {$visit['visit_reference']}");
        setFlash('success', "<strong>{$visit['guest_name']}</strong> received at your office.");
        redirect(APP_URL . '/public/office/handle.php?dest_id=' . $existingDestId);
    }

    $seqStmt = $db->prepare("SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM visit_destinations WHERE visit_id=:vid");
    $seqStmt->execute([':vid' => $visitId]);
    $nextSeq = (int)$seqStmt->fetchColumn();

    $newStatus = $receiveMode === 'complete' ? 'completed' : 'arrived';
    $completedTime = $receiveMode === 'complete' ? date('Y-m-d H:i:s') : null;

    $db->prepare("
        INSERT INTO visit_destinations
            (visit_id, office_id, sequence_no, destination_status, is_primary, is_unplanned,
             received_by_user_id, arrival_time, completed_time, notes)
        VALUES (:vid, :oid, :seq, :status, 0, 1, :uid, NOW(), :completed, :notes)
    ")->execute([
        ':vid' => $visitId,
        ':oid' => $officeId,
        ':seq' => $nextSeq,
        ':status' => $newStatus,
        ':uid' => currentUserId(),
        ':completed' => $completedTime,
        ':notes' => 'Received by office from reference/QR lookup.',
    ]);
    $destId = (int)$db->lastInsertId();

    if ($receiveMode === 'complete') {
        logActivity($visitId, 'destination_completed', currentUserId(), $officeId, "Unexpected office visit completed for '{$visit['guest_name']}' using reference {$visit['visit_reference']}");
        setFlash('success', "<strong>{$visit['guest_name']}</strong> recorded as completed at your office.");
        redirect(APP_URL . '/public/office/history.php');
    }

    logActivity($visitId, 'destination_confirmed', currentUserId(), $officeId, "Unexpected office visit recorded for '{$visit['guest_name']}' using reference {$visit['visit_reference']}");
    setFlash('success', "<strong>{$visit['guest_name']}</strong> received at your office.");
    redirect(APP_URL . '/public/office/handle.php?dest_id=' . $destId);
}

if ($q !== '') {
    $officeSelect = $officeId
        ? ", vd_self.destination_id AS office_destination_id, vd_self.destination_status AS office_destination_status"
        : ", NULL AS office_destination_id, NULL AS office_destination_status";
    $officeJoin = $officeId
        ? "LEFT JOIN visit_destinations vd_self ON vd_self.visit_id = gv.visit_id AND vd_self.office_id = {$officeId}"
        : "";

    $stmt = $db->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.purpose_of_visit, gv.actual_check_in,
               g.full_name AS guest_name, g.organization,
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS routed_offices
               {$officeSelect}
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN visit_destinations vd ON vd.visit_id = gv.visit_id
        LEFT JOIN offices o ON o.office_id = vd.office_id
        {$officeJoin}
        WHERE gv.overall_status = 'checked_in'
          AND (gv.visit_reference = :q1 OR gv.qr_token = :q2 OR g.full_name LIKE :q3)
        GROUP BY gv.visit_id
        ORDER BY gv.actual_check_in DESC
        LIMIT 20
    ");
    $stmt->execute([':q1' => $q, ':q2' => $q, ':q3' => "%{$q}%"]);
    $results = $stmt->fetchAll();
}

include __DIR__ . '/../../includes/header.php';
?>

<div class="page-top">
  <div>
    <div class="page-title">Receive Visitor</div>
    <ul class="breadcrumb"><li><a href="<?= getDashboardUrl() ?>">Dashboard</a></li><li>Receive Visitor</li></ul>
  </div>
</div>

<style>
.receive-search-card{border:1px solid #cfe3c9;background:#fff;}
.receive-search-form{display:grid;grid-template-columns:minmax(0,1fr) 140px;gap:12px;align-items:center;}
.receive-input-wrap{position:relative;}
.receive-input-wrap svg{position:absolute;left:16px;top:50%;transform:translateY(-50%);width:20px;height:20px;color:var(--text-m);}
.receive-input{width:100%;height:58px;padding:0 18px 0 50px;border:2px solid var(--accent);border-radius:8px;font-size:1rem;font-weight:600;background:#fff;color:var(--text);box-shadow:0 0 0 4px rgba(31,122,53,.08);}
.receive-input:focus{outline:none;box-shadow:0 0 0 4px rgba(31,122,53,.16);}
.receive-result-grid{display:grid;grid-template-columns:1fr 340px;gap:18px;}
.receive-result-card{border:1px solid var(--border);border-radius:8px;background:#fff;overflow:hidden;box-shadow:var(--shadow);}
.receive-result-head{display:flex;align-items:center;justify-content:space-between;gap:12px;padding:16px 18px;border-bottom:1px solid var(--border);background:#fbfdf9;}
.receive-actions{display:flex;flex-direction:column;gap:10px;padding:18px;}
.receive-actions form{display:block;}
.receive-action-btn{width:100%;justify-content:center;padding:12px;font-size:.92rem;}
@media(max-width:900px){.receive-search-form,.receive-result-grid{grid-template-columns:1fr;}.receive-search-form .btn{height:48px;justify-content:center;}}
</style>

<div class="card receive-search-card" style="margin-bottom:20px;">
  <div class="card-header"><i data-lucide="scan-search" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Enter Visitor Reference</div>
  <div class="card-body">
    <form method="GET" class="receive-search-form">
      <div class="receive-input-wrap">
        <i data-lucide="scan-line"></i>
        <input type="text" class="receive-input" name="q" value="<?= e($q) ?>" placeholder="Reference number, QR token, or guest name" autofocus autocomplete="off">
      </div>
      <button type="submit" class="btn btn-primary" style="height:58px;justify-content:center;"><i data-lucide="search"></i> Find</button>
    </form>
  </div>
</div>

<?php if ($q !== '' && empty($results)): ?>
<div class="info-box warning"><i data-lucide="alert-triangle"></i><div>No checked-in visitor found matching "<strong><?= e($q) ?></strong>".</div></div>
<?php endif; ?>

<?php if (!empty($results)): ?>
<?php foreach ($results as $r): ?>
<div class="receive-result-grid">
  <div class="receive-result-card">
    <div class="receive-result-head">
      <div class="guest-cell">
        <div class="guest-avatar"><?= strtoupper(substr($r['guest_name'],0,1)) ?></div>
        <div>
          <div class="guest-name"><?= e($r['guest_name']) ?></div>
          <div class="guest-ref"><?= e($r['organization'] ?? '') ?></div>
        </div>
      </div>
      <?php if ($r['office_destination_id']): ?>
      <span class="badge badge-info"><?= statusLabel($r['office_destination_status']) ?></span>
      <?php else: ?>
      <span class="badge badge-warning">Not Routed Here</span>
      <?php endif; ?>
    </div>
    <div class="card-body" style="padding:0;">
      <dl style="margin:0;">
        <div class="detail-row" style="padding:12px 18px;"><dt>Reference</dt><dd><span class="ref-chip"><?= e($r['visit_reference']) ?></span></dd></div>
        <div class="detail-row" style="padding:12px 18px;"><dt>Purpose</dt><dd><?= e($r['purpose_of_visit']) ?></dd></div>
        <div class="detail-row" style="padding:12px 18px;"><dt>Checked In</dt><dd><?= formatDateTime($r['actual_check_in']) ?></dd></div>
        <div class="detail-row" style="padding:12px 18px;"><dt>Routed Offices</dt><dd><?= e($r['routed_offices'] ?: '-') ?></dd></div>
      </dl>
    </div>
  </div>

  <div class="receive-result-card">
    <div class="card-header"><i data-lucide="clipboard-check" style="width:16px;height:16px;vertical-align:middle;margin-right:6px;"></i>Office Record</div>
    <div class="receive-actions">
      <?php if ($officeId): ?>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="visit_id" value="<?= $r['visit_id'] ?>">
        <input type="hidden" name="receive_mode" value="arrive">
        <button type="submit" name="receive_unexpected" class="btn btn-primary receive-action-btn">
          <i data-lucide="user-check"></i> Mark Arrived Here
        </button>
      </form>
      <form method="POST">
        <?= csrfField() ?>
        <input type="hidden" name="visit_id" value="<?= $r['visit_id'] ?>">
        <input type="hidden" name="receive_mode" value="complete">
        <button type="submit" name="receive_unexpected" class="btn btn-success receive-action-btn">
          <i data-lucide="check-circle"></i> Record Completed
        </button>
      </form>
      <?php endif; ?>
      <a href="<?= APP_URL ?>/public/visits/view.php?id=<?= $r['visit_id'] ?>" class="btn btn-outline receive-action-btn">
        <i data-lucide="eye"></i> View Visit Record
      </a>
    </div>
  </div>
</div>
<?php endforeach; ?>
<?php endif; ?>

<script>lucide.createIcons();</script>
<?php include __DIR__ . '/../../includes/footer.php'; ?>

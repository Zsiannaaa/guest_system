<?php
/**
 * modules/destinations_module.php — Office Destination Model
 *
 * Contains ALL database logic for visit destinations (office routing).
 * No HTML, no session checks — pure data functions.
 * Called by: public/office/*.php, public/visits/view.php
 */

/**
 * Get destinations for a visit.
 */
function getDestinationsForVisit(PDO $pdo, int $visitId): array {
    $stmt = $pdo->prepare("
        SELECT vd.*, o.office_name, u.full_name AS received_by_name
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        LEFT JOIN users u ON vd.received_by_user_id = u.user_id
        WHERE vd.visit_id = :vid
        ORDER BY vd.sequence_no
    ");
    $stmt->execute([':vid' => $visitId]);
    return $stmt->fetchAll();
}

/**
 * Get a single destination with full context.
 */
function getDestinationDetails(PDO $pdo, int $destId): array|false {
    $stmt = $pdo->prepare("
        SELECT vd.*, o.office_name, o.requires_arrival_confirmation,
               gv.visit_reference, gv.purpose_of_visit, gv.overall_status,
               gv.actual_check_in, gv.visit_id,
               g.full_name AS guest_name, g.contact_number, g.organization
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.destination_id = :did
    ");
    $stmt->execute([':did' => $destId]);
    return $stmt->fetch();
}

/**
 * Fetch incoming guests for an office (pending destinations of checked-in visits).
 */
function getIncomingGuests(PDO $pdo, int $officeId): array {
    $stmt = $pdo->prepare("
        SELECT vd.*, gv.visit_reference, gv.actual_check_in, gv.purpose_of_visit,
               g.full_name AS guest_name, g.organization
        FROM visit_destinations vd
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.office_id = :oid AND vd.destination_status = 'pending'
              AND gv.overall_status = 'checked_in'
        ORDER BY gv.actual_check_in DESC
    ");
    $stmt->execute([':oid' => $officeId]);
    return $stmt->fetchAll();
}

/**
 * Fetch currently serving guests for an office.
 */
function getCurrentlyServing(PDO $pdo, int $officeId): array {
    $stmt = $pdo->prepare("
        SELECT vd.*, gv.visit_reference, gv.purpose_of_visit,
               g.full_name AS guest_name, g.organization
        FROM visit_destinations vd
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.office_id = :oid AND vd.destination_status IN('arrived','in_service')
              AND gv.overall_status = 'checked_in'
        ORDER BY vd.arrival_time DESC
    ");
    $stmt->execute([':oid' => $officeId]);
    return $stmt->fetchAll();
}

/**
 * Fetch completed visit history for an office.
 */
function getOfficeVisitHistory(PDO $pdo, int $officeId, int $limit = 200): array {
    $stmt = $pdo->prepare("
        SELECT vd.*, gv.visit_reference, gv.visit_date, gv.overall_status,
               g.full_name AS guest_name, g.organization
        FROM visit_destinations vd
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.office_id = :oid AND vd.destination_status = 'completed'
        ORDER BY vd.completed_time DESC
        LIMIT {$limit}
    ");
    $stmt->execute([':oid' => $officeId]);
    return $stmt->fetchAll();
}

/**
 * Confirm arrival of a guest at an office.
 */
function confirmDestinationArrival(PDO $pdo, int $destId, int $userId): void {
    $pdo->prepare("
        UPDATE visit_destinations
        SET destination_status = 'arrived', received_by_user_id = :uid, arrival_time = NOW()
        WHERE destination_id = :did AND destination_status = 'pending'
    ")->execute([':uid' => $userId, ':did' => $destId]);
}

/**
 * Start serving a guest at an office.
 */
function startDestinationService(PDO $pdo, int $destId): void {
    $pdo->prepare("
        UPDATE visit_destinations SET destination_status = 'in_service'
        WHERE destination_id = :did AND destination_status IN ('pending','arrived')
    ")->execute([':did' => $destId]);
}

/**
 * Complete a destination.
 */
function completeDestination(PDO $pdo, int $destId, string $notes = ''): void {
    $pdo->prepare("
        UPDATE visit_destinations
        SET destination_status = 'completed', completed_time = NOW(),
            notes = COALESCE(NULLIF(:notes, ''), notes)
        WHERE destination_id = :did
    ")->execute([':notes' => $notes, ':did' => $destId]);
}

/**
 * Add a new destination to an active visit.
 * Returns the office name.
 */
function addDestinationToVisit(PDO $pdo, int $visitId, int $officeId, bool $isUnplanned,
                               string $notes, bool $autoConfirm, int $userId): string {
    // Get next sequence number
    $seqStmt = $pdo->prepare("SELECT COALESCE(MAX(sequence_no), 0) + 1 FROM visit_destinations WHERE visit_id = :vid");
    $seqStmt->execute([':vid' => $visitId]);
    $nextSeq = (int) $seqStmt->fetchColumn();

    $initStatus = $autoConfirm ? 'arrived' : 'pending';

    $pdo->prepare("
        INSERT INTO visit_destinations
            (visit_id, office_id, sequence_no, destination_status, is_primary, is_unplanned,
             received_by_user_id, arrival_time, notes)
        VALUES (:vid, :oid, :seq, :status, 0, :unp, :recv, :arr, :notes)
    ")->execute([
        ':vid' => $visitId, ':oid' => $officeId, ':seq' => $nextSeq,
        ':status' => $initStatus, ':unp' => $isUnplanned ? 1 : 0,
        ':recv' => $autoConfirm ? $userId : null,
        ':arr' => $autoConfirm ? date('Y-m-d H:i:s') : null,
        ':notes' => $notes ?: null,
    ]);

    $nameStmt = $pdo->prepare("SELECT office_name FROM offices WHERE office_id = :oid");
    $nameStmt->execute([':oid' => $officeId]);
    return $nameStmt->fetchColumn();
}

/**
 * Get office IDs already assigned to a visit.
 */
function getAssignedOfficeIds(PDO $pdo, int $visitId): array {
    $stmt = $pdo->prepare("SELECT office_id FROM visit_destinations WHERE visit_id = :vid");
    $stmt->execute([':vid' => $visitId]);
    return $stmt->fetchAll(PDO::FETCH_COLUMN);
}

/**
 * Search active visitors at an office.
 */
function lookupActiveVisitorsForOffice(PDO $pdo, string $query, ?int $officeId = null): array {
    $where = "AND gv.overall_status='checked_in'";
    if ($officeId) {
        $where .= " AND EXISTS (SELECT 1 FROM visit_destinations vd2 WHERE vd2.visit_id = gv.visit_id AND vd2.office_id = {$officeId})";
    }
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.purpose_of_visit, gv.actual_check_in,
               g.full_name AS guest_name, g.organization
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE 1=1 {$where} AND (gv.visit_reference = :q1 OR g.full_name LIKE :q2)
        ORDER BY gv.actual_check_in DESC LIMIT 20
    ");
    $stmt->execute([':q1' => $query, ':q2' => "%{$query}%"]);
    return $stmt->fetchAll();
}

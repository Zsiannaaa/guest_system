<?php
/**
 * modules/visits_module.php — Visit Model
 *
 * Contains ALL database logic for guest visits.
 * No HTML, no session checks — pure data functions.
 * Called by: public/visits/*.php
 */

/**
 * Fetch all visits (with optional office filter for staff).
 */
function getVisits(PDO $pdo, ?int $officeId = null, int $limit = 500): array {
    $where = '1=1';
    $params = [];
    if ($officeId) {
        $where .= " AND EXISTS (SELECT 1 FROM visit_destinations vd WHERE vd.visit_id = gv.visit_id AND vd.office_id = :oid)";
        $params[':oid'] = $officeId;
    }
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.overall_status,
               gv.registration_type, gv.actual_check_in, gv.actual_check_out,
               g.full_name AS guest_name, g.organization
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE {$where}
        ORDER BY gv.visit_date DESC, gv.created_at DESC
        LIMIT {$limit}
    ");
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Fetch active visitors (currently checked in).
 */
function getActiveVisitors(PDO $pdo): array {
    return $pdo->query("
        SELECT gv.visit_id, gv.visit_reference, gv.actual_check_in,
               gv.registration_type, gv.has_vehicle, gv.expected_time_out,
               g.full_name AS guest_name, g.organization, g.is_restricted,
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
        LEFT JOIN offices o ON vd.office_id = o.office_id
        WHERE gv.overall_status = 'checked_in'
        GROUP BY gv.visit_id
        ORDER BY gv.actual_check_in DESC
    ")->fetchAll();
}

/**
 * Fetch full visit details by ID (with guest + guard info).
 */
function getVisitDetails(PDO $pdo, int $visitId): array|false {
    $stmt = $pdo->prepare("
        SELECT gv.*, g.full_name AS guest_name, g.contact_number, g.email,
               g.organization, g.id_type, g.is_restricted,
               u.full_name AS guard_name
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN users u ON gv.processed_by_guard_id = u.user_id
        WHERE gv.visit_id = :vid
    ");
    $stmt->execute([':vid' => $visitId]);
    return $stmt->fetch();
}

/**
 * Check if office staff has permission to view a visit.
 */
function canOfficeViewVisit(PDO $pdo, int $visitId, int $officeId): bool {
    $stmt = $pdo->prepare("SELECT 1 FROM visit_destinations WHERE visit_id = :vid AND office_id = :oid");
    $stmt->execute([':vid' => $visitId, ':oid' => $officeId]);
    return (bool) $stmt->fetchColumn();
}

/**
 * Get vehicle information for a visit.
 */
function getVehicleForVisit(PDO $pdo, int $visitId): array|false {
    $stmt = $pdo->prepare("SELECT * FROM vehicle_entries WHERE visit_id = :vid");
    $stmt->execute([':vid' => $visitId]);
    return $stmt->fetch();
}

/**
 * Get activity logs for a visit.
 */
function getVisitActivityLogs(PDO $pdo, int $visitId): array {
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name AS actor_name
        FROM activity_logs al
        LEFT JOIN users u ON al.performed_by_user_id = u.user_id
        WHERE al.visit_id = :vid
        ORDER BY al.logged_at ASC
    ");
    $stmt->execute([':vid' => $visitId]);
    return $stmt->fetchAll();
}

/**
 * Search visits by reference, QR token, or guest name.
 */
function lookupVisits(PDO $pdo, string $query, int $limit = 20): array {
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.overall_status,
               gv.registration_type, gv.actual_check_in,
               g.full_name AS guest_name, g.organization
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE gv.visit_reference = :q1 OR gv.qr_token = :q2 OR g.full_name LIKE :q3
        ORDER BY gv.visit_date DESC
        LIMIT {$limit}
    ");
    $stmt->execute([':q1' => $query, ':q2' => $query, ':q3' => "%{$query}%"]);
    return $stmt->fetchAll();
}

/**
 * Create a walk-in visit. Returns [visit_id, visit_reference, qr_token].
 */
function createWalkinVisit(PDO $pdo, int $guestId, string $purpose, array $officeIds,
                           int $guardId, bool $hasVehicle, ?array $vehicleData): array {
    $ref = generateVisitReference();
    $qr  = generateQrToken();

    $pdo->beginTransaction();

    $pdo->prepare("
        INSERT INTO guest_visits (guest_id, visit_reference, qr_token, visit_date, registration_type,
                                  purpose_of_visit, overall_status, has_vehicle,
                                  processed_by_guard_id, actual_check_in)
        VALUES (:gid, :ref, :qr, CURDATE(), 'walk_in', :purpose, 'checked_in', :veh, :guard, NOW())
    ")->execute([
        ':gid' => $guestId, ':ref' => $ref, ':qr' => $qr,
        ':purpose' => $purpose, ':veh' => $hasVehicle ? 1 : 0, ':guard' => $guardId,
    ]);
    $visitId = (int) $pdo->lastInsertId();

    // Add destinations
    foreach ($officeIds as $seq => $oid) {
        $pdo->prepare("
            INSERT INTO visit_destinations (visit_id, office_id, sequence_no, destination_status, is_primary)
            VALUES (:vid, :oid, :seq, 'pending', :primary)
        ")->execute([':vid' => $visitId, ':oid' => $oid, ':seq' => $seq + 1, ':primary' => $seq === 0 ? 1 : 0]);
    }

    // Vehicle entry
    if ($hasVehicle && $vehicleData) {
        $pdo->prepare("
            INSERT INTO vehicle_entries (visit_id, vehicle_type, plate_number, vehicle_color, vehicle_model, driver_name)
            VALUES (:vid, :type, :plate, :color, :model, :driver)
        ")->execute([
            ':vid' => $visitId, ':type' => $vehicleData['type'] ?? 'car',
            ':plate' => $vehicleData['plate'] ?? '', ':color' => $vehicleData['color'] ?? null,
            ':model' => $vehicleData['model'] ?? null, ':driver' => $vehicleData['driver'] ?? null,
        ]);
    }

    $pdo->commit();
    return ['visit_id' => $visitId, 'visit_reference' => $ref, 'qr_token' => $qr];
}

/**
 * Check in a visit (set status to checked_in).
 */
function checkInVisit(PDO $pdo, int $visitId, int $guardId): void {
    $pdo->prepare("
        UPDATE guest_visits SET overall_status = 'checked_in', actual_check_in = NOW(),
                                processed_by_guard_id = :guard
        WHERE visit_id = :vid AND overall_status = 'pending'
    ")->execute([':guard' => $guardId, ':vid' => $visitId]);
}

/**
 * Check out a visit (set status to checked_out).
 */
function checkOutVisit(PDO $pdo, int $visitId, ?string $notes = null): void {
    $pdo->prepare("
        UPDATE guest_visits SET overall_status = 'checked_out', actual_check_out = NOW(), notes = COALESCE(:notes, notes)
        WHERE visit_id = :vid AND overall_status = 'checked_in'
    ")->execute([':notes' => $notes, ':vid' => $visitId]);
}

/**
 * Lookup a visit by reference or QR for checkin/checkout.
 */
function findVisitByReference(PDO $pdo, string $ref): array|false {
    $stmt = $pdo->prepare("
        SELECT gv.*, g.full_name AS guest_name, g.contact_number, g.organization, g.is_restricted
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE gv.visit_reference = :ref OR gv.qr_token = :qr
        LIMIT 1
    ");
    $stmt->execute([':ref' => $ref, ':qr' => $ref]);
    return $stmt->fetch();
}

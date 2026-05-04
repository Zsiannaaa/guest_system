<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Guest directory data-access module for saved guest profiles, restrictions, and visit history.
 * Flow: Called by browser pages in public/; returns data or performs database changes, then the page renders the result.
 * Security: These functions expect validated inputs from controllers and use prepared statements for database values.
 */
/**
 * modules/guests/guests_module.php — Guest Model
 *
 * Contains ALL database logic for guest records.
 * No HTML, no session checks — pure data functions.
 * Called by: public/guests/list.php, view.php, restricted.php, restrict.php, lift_restriction.php
 */

/**
 * Fetch all guests with visit counts.
 */
function getGuests(PDO $pdo): array {
    // Study query: SQL query: reads rows from guests, guest_visits for lookup, validation, or display.
    return $pdo->query("
        SELECT g.*,
               COUNT(gv.visit_id) AS total_visits,
               MAX(gv.visit_date) AS last_visit
        FROM guests g
        LEFT JOIN guest_visits gv ON g.guest_id = gv.guest_id
        GROUP BY g.guest_id
        ORDER BY g.full_name
    ")->fetchAll();
}

/**
 * Fetch a single guest by ID.
 */
function getGuestById(PDO $pdo, int $id): array|false {
    // Study query: Prepared SQL: reads rows from guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT * FROM guests WHERE guest_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Update editable personal information for a guest.
 * Returns error string or null on success.
 */
function updateGuest(PDO $pdo, int $id, array $data): ?string {
    $fullName = trim($data['full_name'] ?? '');
    $contact  = trim($data['contact_number'] ?? '');
    $email    = trim($data['email'] ?? '');
    $org      = trim($data['organization'] ?? '');
    $address  = trim($data['address'] ?? '');
    $idType   = trim($data['id_type'] ?? '');

    if ($id <= 0) return 'Invalid guest record.';
    if ($fullName === '') return 'Full name is required.';
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        return 'Enter a valid email address.';
    }

    // Study query: Prepared SQL: updates existing row(s) in GUESTS. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        UPDATE guests
        SET full_name = :full_name,
            contact_number = :contact_number,
            email = :email,
            organization = :organization,
            address = :address,
            id_type = :id_type
        WHERE guest_id = :id
    ");
    $stmt->execute([
        ':full_name'      => $fullName,
        ':contact_number' => $contact !== '' ? $contact : null,
        ':email'          => $email !== '' ? $email : null,
        ':organization'   => $org !== '' ? $org : null,
        ':address'        => $address !== '' ? $address : null,
        ':id_type'        => $idType !== '' ? $idType : null,
        ':id'             => $id,
    ]);

    return null;
}

/**
 * Delete a guest only when no visit or accommodation history exists.
 * Returns error string or null on success.
 */
function deleteGuest(PDO $pdo, int $id): ?string {
    if ($id <= 0) return 'Invalid guest record.';

    // Study query: Prepared SQL: reads rows from guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT full_name FROM guests WHERE guest_id = :id");
    $stmt->execute([':id' => $id]);
    if (!$stmt->fetchColumn()) return 'Guest record not found.';

    // Study query: Prepared SQL: reads rows from guest_visits for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $visitStmt = $pdo->prepare("SELECT COUNT(*) FROM guest_visits WHERE guest_id = :id");
    $visitStmt->execute([':id' => $id]);
    $visitCount = (int)$visitStmt->fetchColumn();

    $bookingCount = 0;
    // Study query: SQL query: runs database work for this step.
    $tableStmt = $pdo->query("SHOW TABLES LIKE 'guest_house_bookings'");
    if ($tableStmt->fetchColumn()) {
        $bookingStmt = $pdo->prepare("SELECT COUNT(*) FROM guest_house_bookings WHERE guest_id = :id");
        $bookingStmt->execute([':id' => $id]);
        $bookingCount = (int)$bookingStmt->fetchColumn();
    }

    if ($visitCount > 0 || $bookingCount > 0) {
        return 'This guest cannot be deleted because they already have visit or Guest House history. You can edit their personal information instead.';
    }

    // Study query: Prepared SQL: deletes row(s) from GUESTS. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("DELETE FROM guests WHERE guest_id = :id");
    $stmt->execute([':id' => $id]);
    return null;
}

/**
 * Fetch visit history for a guest.
 */
function getGuestVisitHistory(PDO $pdo, int $guestId): array {
    // Study query: Prepared SQL: reads rows from guest_visits, users, visit_destinations, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT gv.*, u.full_name AS guard_name,
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS offices
        FROM guest_visits gv
        LEFT JOIN users u ON gv.processed_by_guard_id = u.user_id
        LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
        LEFT JOIN offices o ON vd.office_id = o.office_id
        WHERE gv.guest_id = :gid
        GROUP BY gv.visit_id
        ORDER BY gv.visit_date DESC
    ");
    $stmt->execute([':gid' => $guestId]);
    return $stmt->fetchAll();
}

/**
 * Fetch active restriction info for a guest.
 */
function getGuestRestriction(PDO $pdo, int $guestId): array|false {
    // Study query: Prepared SQL: reads rows from restricted_guests, users for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT r.*, u.full_name AS restricted_by
        FROM restricted_guests r
        LEFT JOIN users u ON r.restricted_by_user_id = u.user_id
        WHERE r.guest_id = :gid AND r.is_active = 1
        LIMIT 1
    ");
    $stmt->execute([':gid' => $guestId]);
    return $stmt->fetch();
}

/**
 * Fetch all currently restricted guests.
 */
function getRestrictedGuests(PDO $pdo): array {
    // Study query: SQL query: reads rows from guests, restricted_guests, users for lookup, validation, or display.
    return $pdo->query("
        SELECT g.*, r.reason, r.restricted_at, r.restriction_id,
               u.full_name AS restricted_by_name
        FROM guests g
        JOIN restricted_guests r ON g.guest_id = r.guest_id
        LEFT JOIN users u ON r.restricted_by_user_id = u.user_id
        WHERE r.is_active = 1
        ORDER BY r.restricted_at DESC
    ")->fetchAll();
}

/**
 * Restrict a guest. Returns error string or null on success.
 */
function restrictGuest(PDO $pdo, int $guestId, string $reason, int $byUserId): ?string {
    if (empty($reason)) return 'Restriction reason is required.';

    // Study transaction: several related database changes must succeed together or be rolled back together.
    $pdo->beginTransaction();
    // Study query: Prepared SQL: updates existing row(s) in GUESTS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("UPDATE guests SET is_restricted=1, restriction_reason=:r WHERE guest_id=:id")
        ->execute([':r' => $reason, ':id' => $guestId]);
    $pdo->prepare("INSERT INTO restricted_guests (guest_id, reason, restricted_by_user_id, is_active) VALUES (:g, :r, :u, 1)")
        ->execute([':g' => $guestId, ':r' => $reason, ':u' => $byUserId]);
    // Study transaction: commit saves all database changes made since beginTransaction().
    $pdo->commit();
    return null;
}

/**
 * Lift a guest's restriction.
 */
function liftGuestRestriction(PDO $pdo, int $guestId, int $byUserId): void {
    // Study transaction: several related database changes must succeed together or be rolled back together.
    $pdo->beginTransaction();
    // Study query: Prepared SQL: updates existing row(s) in RESTRICTED_GUESTS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("UPDATE restricted_guests SET is_active=0, lifted_at=NOW(), lifted_by_user_id=:uid WHERE guest_id=:gid AND is_active=1")
        ->execute([':uid' => $byUserId, ':gid' => $guestId]);
    $pdo->prepare("UPDATE guests SET is_restricted=0, restriction_reason=NULL WHERE guest_id=:id")
        ->execute([':id' => $guestId]);
    // Study transaction: commit saves all database changes made since beginTransaction().
    $pdo->commit();
}

/**
 * Find or create a guest record. Returns guest_id.
 */
function findOrCreateGuest(PDO $pdo, string $fullName, ?string $contact, ?string $email,
                           ?string $org, ?string $address, ?string $idType, ?string $idNumber): int {
    // Try to find existing guest by name + contact
    if ($contact) {
        // Study query: Prepared SQL: reads rows from guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
        $stmt = $pdo->prepare("SELECT guest_id FROM guests WHERE full_name = :n AND contact_number = :c LIMIT 1");
        $stmt->execute([':n' => $fullName, ':c' => $contact]);
        $existing = $stmt->fetchColumn();
        if ($existing) return (int) $existing;
    }

    // Study query: Prepared SQL: creates a new row in GUESTS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("
        INSERT INTO guests (full_name, contact_number, email, organization, address, id_type)
        VALUES (:n, :c, :e, :o, :a, :idt)
    ")->execute([
        ':n' => $fullName, ':c' => $contact, ':e' => $email,
        ':o' => $org, ':a' => $address, ':idt' => $idType,
    ]);
    return (int) $pdo->lastInsertId();
}

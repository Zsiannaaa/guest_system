<?php
/**
 * modules/gh_bookings_module.php — Guest House Bookings Model
 *
 * Pure DB logic. No HTML, no session checks.
 * Called by: public/guest_house/bookings.php and related pages.
 */

require_once __DIR__ . '/gh_rooms_module.php';

/**
 * Generate a unique booking reference: GH-YYYYMMDD-####
 */
function ghGenerateBookingReference(PDO $pdo): string {
    $today  = date('Ymd');
    $prefix = GH_BOOKING_REF_PREFIX . '-' . $today . '-';

    $stmt = $pdo->prepare("
        SELECT booking_reference FROM guest_house_bookings
        WHERE booking_reference LIKE :p
        ORDER BY booking_id DESC LIMIT 1
    ");
    $stmt->execute([':p' => $prefix . '%']);
    $last = $stmt->fetchColumn();

    $next = $last ? ((int) substr($last, -4)) + 1 : 1;
    return $prefix . str_pad($next, 4, '0', STR_PAD_LEFT);
}

/**
 * List bookings with filters.
 * Filters: status, from, to, q (guest name / booking ref), room_id, office_id
 */
function ghListBookings(PDO $pdo, array $filters = []): array {
    $where = ['1=1'];
    $params = [];

    if (!empty($filters['status']))   { $where[] = 'b.status = :st';      $params[':st']  = $filters['status']; }
    if (!empty($filters['room_id']))  { $where[] = 'b.room_id = :rid';     $params[':rid'] = (int)$filters['room_id']; }
    if (!empty($filters['office_id'])) { $where[] = 'b.sponsoring_office_id = :oid'; $params[':oid'] = (int)$filters['office_id']; }
    if (!empty($filters['from']))     { $where[] = 'b.check_out_date >= :from'; $params[':from'] = $filters['from']; }
    if (!empty($filters['to']))       { $where[] = 'b.check_in_date  <= :to';   $params[':to']   = $filters['to']; }
    if (!empty($filters['q'])) {
        $where[] = '(g.full_name LIKE :q1 OR b.booking_reference LIKE :q2)';
        $params[':q1'] = '%' . $filters['q'] . '%';
        $params[':q2'] = '%' . $filters['q'] . '%';
    }

    $sql = "
        SELECT b.*,
               g.full_name  AS guest_name,
               g.organization,
               g.contact_number,
               r.room_number,
               t.type_name,
               o.office_name AS sponsor_office_name
        FROM guest_house_bookings b
        JOIN guests g                ON b.guest_id = g.guest_id
        LEFT JOIN guest_house_rooms r ON b.room_id  = r.room_id
        LEFT JOIN gh_room_types t     ON r.type_id  = t.type_id
        LEFT JOIN offices o           ON b.sponsoring_office_id = o.office_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY b.check_in_date DESC, b.booking_id DESC
    ";
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

function ghGetBooking(PDO $pdo, int $id): array|false {
    $stmt = $pdo->prepare("
        SELECT b.*,
               g.full_name AS guest_name, g.organization, g.contact_number,
               g.email AS guest_email, g.id_type, g.is_restricted,
               r.room_number, r.capacity AS room_capacity, r.floor,
               t.type_name,
               o.office_name AS sponsor_office_name,
               u.full_name AS created_by_name,
               gv.visit_reference AS linked_visit_reference
        FROM guest_house_bookings b
        JOIN guests g                 ON b.guest_id = g.guest_id
        LEFT JOIN guest_house_rooms r ON b.room_id  = r.room_id
        LEFT JOIN gh_room_types t     ON r.type_id  = t.type_id
        LEFT JOIN offices o           ON b.sponsoring_office_id = o.office_id
        LEFT JOIN users u             ON b.created_by_user_id = u.user_id
        LEFT JOIN guest_visits gv     ON b.linked_visit_id = gv.visit_id
        WHERE b.booking_id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Create a booking. Returns [null, booking_id, reference] on success,
 * or [error, 0, ''] on failure.
 */
function ghCreateBooking(PDO $pdo, array $data, int $actorUserId): array {
    $guestId   = (int)($data['guest_id'] ?? 0);
    $roomId    = !empty($data['room_id']) ? (int)$data['room_id'] : null;
    $from      = trim($data['check_in_date'] ?? '');
    $to        = trim($data['check_out_date'] ?? '');
    $purpose   = trim($data['purpose_of_stay'] ?? '');
    $officeId  = !empty($data['sponsoring_office_id']) ? (int)$data['sponsoring_office_id'] : null;
    $extSp     = trim($data['external_sponsor'] ?? '');
    $numGuests = max(1, (int)($data['number_of_guests'] ?? 1));
    $notes     = trim($data['notes'] ?? '');

    if ($guestId <= 0)       return ['Please select a guest.', 0, ''];
    if ($from === '' || $to === '') return ['Check-in and check-out dates are required.', 0, ''];
    if (strtotime($to) < strtotime($from)) return ['Check-out date must be on or after check-in date.', 0, ''];
    if ($purpose === '')     return ['Purpose of stay is required.', 0, ''];

    // Restricted guest guard
    $rstmt = $pdo->prepare("SELECT is_restricted, full_name FROM guests WHERE guest_id = :g");
    $rstmt->execute([':g' => $guestId]);
    $grow = $rstmt->fetch();
    if (!$grow) return ['Selected guest does not exist.', 0, ''];
    if ((int)$grow['is_restricted'] === 1) {
        return ["Guest '{$grow['full_name']}' is restricted and cannot be booked.", 0, ''];
    }

    // Capacity check
    if ($roomId) {
        $cap = (int)$pdo->query("SELECT capacity FROM guest_house_rooms WHERE room_id = " . (int)$roomId)->fetchColumn();
        if ($cap > 0 && $numGuests > $cap) {
            return ["Number of guests ({$numGuests}) exceeds room capacity ({$cap}).", 0, ''];
        }
        // Overlap check
        if (!ghIsRoomAvailable($pdo, $roomId, $from, $to)) {
            return ['Selected room is not available for the chosen dates.', 0, ''];
        }
    }

    $ref = ghGenerateBookingReference($pdo);

    $pdo->prepare("
        INSERT INTO guest_house_bookings (
            booking_reference, guest_id, room_id, check_in_date, check_out_date,
            purpose_of_stay, sponsoring_office_id, external_sponsor,
            number_of_guests, status, created_by_user_id, notes
        ) VALUES (
            :ref, :gid, :rid, :from, :to,
            :purpose, :oid, :ext,
            :num, 'reserved', :uid, :notes
        )
    ")->execute([
        ':ref'=>$ref, ':gid'=>$guestId, ':rid'=>$roomId,
        ':from'=>$from, ':to'=>$to,
        ':purpose'=>$purpose, ':oid'=>$officeId, ':ext'=>$extSp ?: null,
        ':num'=>$numGuests, ':uid'=>$actorUserId, ':notes'=>$notes ?: null,
    ]);
    $bookingId = (int)$pdo->lastInsertId();

    logActivity(null, 'gh_booking_created', $actorUserId, $officeId,
        "Created GH booking {$ref} for guest_id {$guestId}, room_id " . ($roomId ?? 'unassigned'));

    return [null, $bookingId, $ref];
}

function ghUpdateBooking(PDO $pdo, int $id, array $data, int $actorUserId): ?string {
    $cur = ghGetBooking($pdo, $id);
    if (!$cur) return 'Booking not found.';
    if (in_array($cur['status'], ['checked_out','cancelled'], true)) {
        return 'This booking is closed and cannot be edited.';
    }

    $roomId    = !empty($data['room_id']) ? (int)$data['room_id'] : null;
    $from      = trim($data['check_in_date'] ?? '');
    $to        = trim($data['check_out_date'] ?? '');
    $purpose   = trim($data['purpose_of_stay'] ?? '');
    $officeId  = !empty($data['sponsoring_office_id']) ? (int)$data['sponsoring_office_id'] : null;
    $extSp     = trim($data['external_sponsor'] ?? '');
    $numGuests = max(1, (int)($data['number_of_guests'] ?? 1));
    $notes     = trim($data['notes'] ?? '');

    if ($from === '' || $to === '') return 'Dates are required.';
    if (strtotime($to) < strtotime($from)) return 'Check-out date must be on or after check-in date.';
    if ($purpose === '') return 'Purpose of stay is required.';

    if ($roomId) {
        $cap = (int)$pdo->query("SELECT capacity FROM guest_house_rooms WHERE room_id = " . (int)$roomId)->fetchColumn();
        if ($cap > 0 && $numGuests > $cap) {
            return "Number of guests ({$numGuests}) exceeds room capacity ({$cap}).";
        }
        if (!ghIsRoomAvailable($pdo, $roomId, $from, $to, $id)) {
            return 'Selected room is not available for the chosen dates.';
        }
    }

    $pdo->prepare("
        UPDATE guest_house_bookings SET
            room_id=:rid, check_in_date=:from, check_out_date=:to,
            purpose_of_stay=:purpose, sponsoring_office_id=:oid,
            external_sponsor=:ext, number_of_guests=:num, notes=:notes
        WHERE booking_id=:id
    ")->execute([
        ':rid'=>$roomId, ':from'=>$from, ':to'=>$to,
        ':purpose'=>$purpose, ':oid'=>$officeId, ':ext'=>$extSp ?: null,
        ':num'=>$numGuests, ':notes'=>$notes ?: null, ':id'=>$id,
    ]);

    logActivity(null, 'gh_booking_updated', $actorUserId, $officeId,
        "Updated GH booking {$cur['booking_reference']}");
    return null;
}

function ghCancelBooking(PDO $pdo, int $id, string $reason, int $actorUserId): ?string {
    $cur = ghGetBooking($pdo, $id);
    if (!$cur) return 'Booking not found.';
    if (in_array($cur['status'], ['checked_out','cancelled'], true)) {
        return 'This booking is already closed.';
    }

    $pdo->prepare("
        UPDATE guest_house_bookings
        SET status='cancelled',
            notes = CONCAT(COALESCE(notes, ''), CASE WHEN notes IS NULL OR notes='' THEN '' ELSE '\n' END,
                          '[Cancelled] ', :reason)
        WHERE booking_id = :id
    ")->execute([':reason' => $reason ?: 'No reason given', ':id' => $id]);

    // If the room was marked occupied because of this booking, revert it to available
    // only when no other active booking currently occupies the same room.
    if (!empty($cur['room_id'])) {
        ghSyncRoomStatus($pdo, (int)$cur['room_id']);
    }

    logActivity(null, 'gh_booking_cancelled', $actorUserId, null,
        "Cancelled GH booking {$cur['booking_reference']}: " . ($reason ?: 'n/a'));
    return null;
}

function ghCheckIn(PDO $pdo, int $bookingId, int $actorUserId): ?string {
    $cur = ghGetBooking($pdo, $bookingId);
    if (!$cur) return 'Booking not found.';
    if ($cur['status'] !== 'reserved') return 'Only reserved bookings can be checked in.';
    if (empty($cur['room_id']))        return 'Assign a room to the booking before checking in.';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE guest_house_bookings
            SET status='checked_in', actual_check_in = NOW()
            WHERE booking_id = :id
        ")->execute([':id' => $bookingId]);

        $pdo->prepare("UPDATE guest_house_rooms SET status='occupied' WHERE room_id = :rid")
            ->execute([':rid' => $cur['room_id']]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Failed to check in: ' . $e->getMessage();
    }

    logActivity(null, 'gh_checked_in', $actorUserId, null,
        "GH check-in: {$cur['booking_reference']} (room {$cur['room_number']})");
    return null;
}

function ghCheckOut(PDO $pdo, int $bookingId, int $actorUserId, ?string $notes = null): ?string {
    $cur = ghGetBooking($pdo, $bookingId);
    if (!$cur) return 'Booking not found.';
    if (!in_array($cur['status'], ['checked_in','occupied'], true)) {
        return 'Only checked-in bookings can be checked out.';
    }

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            UPDATE guest_house_bookings
            SET status='checked_out', actual_check_out = NOW(),
                notes = COALESCE(:notes, notes)
            WHERE booking_id = :id
        ")->execute([':notes' => $notes ?: null, ':id' => $bookingId]);

        if (!empty($cur['room_id'])) {
            ghSyncRoomStatus($pdo, (int)$cur['room_id']);
        }
        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return 'Failed to check out: ' . $e->getMessage();
    }

    logActivity(null, 'gh_checked_out', $actorUserId, null,
        "GH check-out: {$cur['booking_reference']} (room {$cur['room_number']})");
    return null;
}

/**
 * Re-evaluate physical room status based on whether any booking still holds it.
 */
function ghSyncRoomStatus(PDO $pdo, int $roomId): void {
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM guest_house_bookings
        WHERE room_id = :rid AND status IN ('checked_in','occupied')
    ");
    $stmt->execute([':rid' => $roomId]);
    $hasActive = (int)$stmt->fetchColumn() > 0;

    $current = $pdo->prepare("SELECT status FROM guest_house_rooms WHERE room_id = :rid");
    $current->execute([':rid' => $roomId]);
    $cur = $current->fetchColumn();

    if ($cur === 'maintenance' || $cur === 'inactive') return; // do not override

    $new = $hasActive ? 'occupied' : 'available';
    if ($cur !== $new) {
        $pdo->prepare("UPDATE guest_house_rooms SET status = :s WHERE room_id = :rid")
            ->execute([':s' => $new, ':rid' => $roomId]);
    }
}

/**
 * All current occupants (status in checked_in/occupied).
 */
function ghCurrentOccupants(PDO $pdo): array {
    return $pdo->query("
        SELECT b.*,
               g.full_name AS guest_name, g.organization, g.contact_number,
               r.room_number, t.type_name, r.floor,
               o.office_name AS sponsor_office_name,
               DATEDIFF(b.check_out_date, b.check_in_date) AS nights_planned,
               GREATEST(1, DATEDIFF(CURDATE(), b.check_in_date)) AS nights_so_far
        FROM guest_house_bookings b
        JOIN guests g                 ON b.guest_id = g.guest_id
        LEFT JOIN guest_house_rooms r ON b.room_id  = r.room_id
        LEFT JOIN gh_room_types t     ON r.type_id  = t.type_id
        LEFT JOIN offices o           ON b.sponsoring_office_id = o.office_id
        WHERE b.status IN ('checked_in','occupied')
        ORDER BY b.actual_check_in DESC
    ")->fetchAll();
}

/**
 * Generate a matching guest_visits row from a booking. Stores the visit id
 * on the booking (linked_visit_id) for later reference.
 */
function ghGenerateLinkedVisit(PDO $pdo, int $bookingId, int $actorUserId): array {
    $b = ghGetBooking($pdo, $bookingId);
    if (!$b) return ['Booking not found.', null, null];
    if (!empty($b['linked_visit_id'])) {
        return ['This booking already has a linked visit record.', (int)$b['linked_visit_id'], $b['linked_visit_reference']];
    }
    if (!in_array($b['status'], ['checked_in','occupied','reserved'], true)) {
        return ['Only active bookings can generate a visit record.', null, null];
    }

    $ref = generateVisitReference();
    $qr  = generateQrToken();
    $purpose = '[Guest House] ' . $b['purpose_of_stay'] . ' (Booking ' . $b['booking_reference'] . ')';

    $pdo->beginTransaction();
    try {
        $pdo->prepare("
            INSERT INTO guest_visits (guest_id, visit_reference, qr_token, visit_date, registration_type,
                                     purpose_of_visit, overall_status, has_vehicle,
                                     processed_by_guard_id, actual_check_in)
            VALUES (:gid, :ref, :qr, CURDATE(), 'walk_in', :purpose, 'checked_in', 0, :uid, NOW())
        ")->execute([
            ':gid' => $b['guest_id'], ':ref' => $ref, ':qr' => $qr,
            ':purpose' => $purpose, ':uid' => $actorUserId,
        ]);
        $visitId = (int)$pdo->lastInsertId();

        // Link sponsoring office as a destination if present
        if (!empty($b['sponsoring_office_id'])) {
            $pdo->prepare("
                INSERT INTO visit_destinations (visit_id, office_id, sequence_no, destination_status, is_primary)
                VALUES (:vid, :oid, 1, 'pending', 1)
            ")->execute([':vid' => $visitId, ':oid' => $b['sponsoring_office_id']]);
        }

        $pdo->prepare("UPDATE guest_house_bookings SET linked_visit_id = :vid WHERE booking_id = :bid")
            ->execute([':vid' => $visitId, ':bid' => $bookingId]);

        $pdo->commit();
    } catch (Throwable $e) {
        $pdo->rollBack();
        return ['Failed to generate visit record: ' . $e->getMessage(), null, null];
    }

    logActivity($visitId, 'gh_visit_generated', $actorUserId, $b['sponsoring_office_id'] ?? null,
        "Generated visit {$ref} from booking {$b['booking_reference']}");

    return [null, $visitId, $ref];
}

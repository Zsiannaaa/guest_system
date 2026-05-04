<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Guest House data-access module for gh rooms module. Guest House public pages call these functions for rooms, bookings, and reports.
 * Flow: Called by browser pages in public/; returns data or performs database changes, then the page renders the result.
 * Security: These functions expect validated inputs from controllers and use prepared statements for database values.
 */
/**
 * modules/guest_house/gh_rooms_module.php — Guest House Rooms + Room Types Model
 *
 * Pure DB logic. No HTML, no session checks.
 * Called by: public/guest_house/rooms.php, room_types.php,
 *            and by gh_bookings_module.php for availability.
 */

// ─── Room Types ────────────────────────────────────────────

/**
 * Study function: Supports the guest house list room types workflow in this feature area.
 */
function ghListRoomTypes(PDO $pdo, bool $activeOnly = false): array {
    $sql = "SELECT * FROM gh_room_types";
    if ($activeOnly) $sql .= " WHERE status='active'";
    $sql .= " ORDER BY type_name";
    // Study query: SQL query: runs database work for this step.
    return $pdo->query($sql)->fetchAll();
}

/**
 * Study function: Supports the guest house get room type workflow in this feature area.
 */
function ghGetRoomType(PDO $pdo, int $id): array|false {
    // Study query: Prepared SQL: reads rows from gh_room_types for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT * FROM gh_room_types WHERE type_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Study function: Supports the guest house create room type workflow in this feature area.
 */
function ghCreateRoomType(PDO $pdo, string $name, int $defaultCapacity, ?string $description): ?string {
    $name = trim($name);
    if ($name === '') return 'Type name is required.';
    if ($defaultCapacity < 1) return 'Default capacity must be at least 1.';

    // Study query: Prepared SQL: reads rows from gh_room_types for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM gh_room_types WHERE type_name = :n");
    $chk->execute([':n' => $name]);
    if ($chk->fetchColumn() > 0) return "Room type '{$name}' already exists.";

    // Study query: Prepared SQL: creates a new row in GH_ROOM_TYPES. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("
        INSERT INTO gh_room_types (type_name, default_capacity, description, status)
        VALUES (:n, :c, :d, 'active')
    ")->execute([':n' => $name, ':c' => $defaultCapacity, ':d' => $description ?: null]);
    return null;
}

/**
 * Study function: Supports the guest house update room type workflow in this feature area.
 */
function ghUpdateRoomType(PDO $pdo, int $id, string $name, int $defaultCapacity,
                           ?string $description, string $status): ?string {
    $name = trim($name);
    if ($name === '') return 'Type name is required.';
    if ($defaultCapacity < 1) return 'Default capacity must be at least 1.';
    if (!in_array($status, ['active','inactive'], true)) return 'Invalid status.';

    // Study query: Prepared SQL: reads rows from gh_room_types for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM gh_room_types WHERE type_name = :n AND type_id != :id");
    $chk->execute([':n' => $name, ':id' => $id]);
    if ($chk->fetchColumn() > 0) return "Another room type already uses '{$name}'.";

    // Study query: Prepared SQL: updates existing row(s) in GH_ROOM_TYPES. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("
        UPDATE gh_room_types
        SET type_name=:n, default_capacity=:c, description=:d, status=:s
        WHERE type_id=:id
    ")->execute([':n'=>$name, ':c'=>$defaultCapacity, ':d'=>$description ?: null, ':s'=>$status, ':id'=>$id]);
    return null;
}

// ─── Rooms ─────────────────────────────────────────────────

/**
 * List rooms with filters.
 * Filters: status, type_id, q (room_number substring)
 */
function ghListRooms(PDO $pdo, array $filters = []): array {
    $where = ['1=1'];
    $params = [];
    if (!empty($filters['status']))  { $where[] = 'r.status = :st';     $params[':st']  = $filters['status']; }
    if (!empty($filters['type_id'])) { $where[] = 'r.type_id = :tid';   $params[':tid'] = (int)$filters['type_id']; }
    if (!empty($filters['q']))       { $where[] = 'r.room_number LIKE :q'; $params[':q']  = '%' . $filters['q'] . '%'; }

    $sql = "
        SELECT r.*, t.type_name, t.default_capacity
        FROM guest_house_rooms r
        JOIN gh_room_types t ON r.type_id = t.type_id
        WHERE " . implode(' AND ', $where) . "
        ORDER BY r.room_number
    ";
    // Study query: Prepared SQL: runs database work for this step. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt->fetchAll();
}

/**
 * Study function: Supports the guest house get room workflow in this feature area.
 */
function ghGetRoom(PDO $pdo, int $id): array|false {
    // Study query: Prepared SQL: reads rows from guest_house_rooms, gh_room_types for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT r.*, t.type_name
        FROM guest_house_rooms r
        JOIN gh_room_types t ON r.type_id = t.type_id
        WHERE r.room_id = :id
    ");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Study function: Supports the guest house create room workflow in this feature area.
 */
function ghCreateRoom(PDO $pdo, array $data): ?string {
    $number   = trim($data['room_number'] ?? '');
    $typeId   = (int)($data['type_id'] ?? 0);
    $capacity = (int)($data['capacity'] ?? 0);
    $floor    = trim($data['floor'] ?? '');
    $note     = trim($data['location_note'] ?? '');
    $notes    = trim($data['notes'] ?? '');

    if ($number === '')    return 'Room number is required.';
    if ($typeId <= 0)      return 'Room type is required.';
    if ($capacity < 1)     return 'Capacity must be at least 1.';

    // Study query: Prepared SQL: reads rows from guest_house_rooms for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM guest_house_rooms WHERE room_number = :n");
    $chk->execute([':n' => $number]);
    if ($chk->fetchColumn() > 0) return "Room number '{$number}' already exists.";

    // Study query: Prepared SQL: creates a new row in GUEST_HOUSE_ROOMS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("
        INSERT INTO guest_house_rooms (room_number, type_id, capacity, floor, location_note, status, notes)
        VALUES (:n, :t, :c, :fl, :loc, 'available', :notes)
    ")->execute([
        ':n' => $number, ':t' => $typeId, ':c' => $capacity,
        ':fl' => $floor ?: null, ':loc' => $note ?: null, ':notes' => $notes ?: null,
    ]);
    return null;
}

/**
 * Study function: Supports the guest house update room workflow in this feature area.
 */
function ghUpdateRoom(PDO $pdo, int $id, array $data): ?string {
    $number   = trim($data['room_number'] ?? '');
    $typeId   = (int)($data['type_id'] ?? 0);
    $capacity = (int)($data['capacity'] ?? 0);
    $status   = $data['status'] ?? 'available';
    $floor    = trim($data['floor'] ?? '');
    $note     = trim($data['location_note'] ?? '');
    $notes    = trim($data['notes'] ?? '');

    if ($number === '')    return 'Room number is required.';
    if ($typeId <= 0)      return 'Room type is required.';
    if ($capacity < 1)     return 'Capacity must be at least 1.';
    if (!in_array($status, ['available','occupied','maintenance','inactive'], true)) return 'Invalid status.';

    // Study query: Prepared SQL: reads rows from guest_house_rooms for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $chk = $pdo->prepare("SELECT COUNT(*) FROM guest_house_rooms WHERE room_number = :n AND room_id != :id");
    $chk->execute([':n' => $number, ':id' => $id]);
    if ($chk->fetchColumn() > 0) return "Another room already uses number '{$number}'.";

    // Study query: Prepared SQL: updates existing row(s) in GUEST_HOUSE_ROOMS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("
        UPDATE guest_house_rooms
        SET room_number=:n, type_id=:t, capacity=:c, status=:s,
            floor=:fl, location_note=:loc, notes=:notes
        WHERE room_id=:id
    ")->execute([
        ':n'=>$number, ':t'=>$typeId, ':c'=>$capacity, ':s'=>$status,
        ':fl'=>$floor ?: null, ':loc'=>$note ?: null, ':notes'=>$notes ?: null, ':id'=>$id,
    ]);
    return null;
}

/**
 * Study function: Supports the guest house set room status workflow in this feature area.
 */
function ghSetRoomStatus(PDO $pdo, int $id, string $status): void {
    if (!in_array($status, ['available','occupied','maintenance','inactive'], true)) return;
    // Study query: Prepared SQL: updates existing row(s) in GUEST_HOUSE_ROOMS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("UPDATE guest_house_rooms SET status = :s WHERE room_id = :id")
        ->execute([':s' => $status, ':id' => $id]);
}

/**
 * Check if a room has no overlapping reserved/checked_in booking in [$from, $to).
 * $from and $to are 'Y-m-d'.
 */
function ghIsRoomAvailable(PDO $pdo, int $roomId, string $from, string $to,
                            ?int $ignoreBookingId = null): bool {
    $sql = "
        SELECT COUNT(*) FROM guest_house_bookings
        WHERE room_id = :rid
          AND status IN ('reserved','checked_in','occupied')
          AND check_in_date < :to
          AND check_out_date > :from
    ";
    $params = [':rid' => $roomId, ':to' => $to, ':from' => $from];
    if ($ignoreBookingId) {
        $sql .= " AND booking_id != :bid";
        $params[':bid'] = $ignoreBookingId;
    }
    // Study query: Prepared SQL: runs database work for this step. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return ((int)$stmt->fetchColumn()) === 0;
}

/**
 * List active (available / occupied) rooms for dropdowns, optionally filtering
 * by availability in a date range.
 */
function ghAvailableRoomsForRange(PDO $pdo, string $from, string $to): array {
    // Study query: SQL query: reads rows from guest_house_rooms, gh_room_types for lookup, validation, or display.
    $rooms = $pdo->query("
        SELECT r.*, t.type_name
        FROM guest_house_rooms r
        JOIN gh_room_types t ON r.type_id = t.type_id
        WHERE r.status IN ('available','occupied')
        ORDER BY r.room_number
    ")->fetchAll();

    $out = [];
    foreach ($rooms as $r) {
        $r['is_free_for_range'] = ghIsRoomAvailable($pdo, (int)$r['room_id'], $from, $to) ? 1 : 0;
        $out[] = $r;
    }
    return $out;
}

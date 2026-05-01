<?php
/**
 * modules/guest_house/gh_reports_module.php — Guest House Reports Model
 * Pure DB logic.
 */

function ghOccupancyToday(PDO $pdo): array {
    $total    = (int)$pdo->query("SELECT COUNT(*) FROM guest_house_rooms WHERE status != 'inactive'")->fetchColumn();
    $occupied = (int)$pdo->query("
        SELECT COUNT(DISTINCT room_id) FROM guest_house_bookings
        WHERE status IN ('checked_in','occupied')
    ")->fetchColumn();
    return [
        'total'    => $total,
        'occupied' => $occupied,
        'free'     => max(0, $total - $occupied),
        'percent'  => $total > 0 ? round(($occupied / $total) * 100) : 0,
    ];
}

function ghExpectedToday(PDO $pdo): array {
    return $pdo->query("
        SELECT b.*, g.full_name AS guest_name, g.organization, r.room_number
        FROM guest_house_bookings b
        JOIN guests g ON b.guest_id = g.guest_id
        LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
        WHERE b.check_in_date <= CURDATE()
          AND b.status = 'reserved'
        ORDER BY b.check_in_date, b.booking_id
    ")->fetchAll();
}

function ghUpcomingExpected(PDO $pdo, int $limit = 8): array {
    $stmt = $pdo->prepare("
        SELECT b.*, g.full_name AS guest_name, g.organization, r.room_number
        FROM guest_house_bookings b
        JOIN guests g ON b.guest_id = g.guest_id
        LEFT JOIN guest_house_rooms r ON b.room_id = r.room_id
        WHERE b.check_in_date > CURDATE()
          AND b.status = 'reserved'
        ORDER BY b.check_in_date, b.booking_id
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

function ghStaysByPeriod(PDO $pdo, string $from, string $to): array {
    $stmt = $pdo->prepare("
        SELECT
            COUNT(*)                                                          AS total_bookings,
            SUM(CASE WHEN status = 'checked_out' THEN 1 ELSE 0 END)            AS completed,
            SUM(CASE WHEN status IN ('checked_in','occupied') THEN 1 ELSE 0 END) AS active,
            SUM(CASE WHEN status = 'cancelled' THEN 1 ELSE 0 END)              AS cancelled,
            SUM(CASE WHEN status = 'no_show' THEN 1 ELSE 0 END)                AS no_show
        FROM guest_house_bookings
        WHERE check_in_date <= :to AND check_out_date >= :from
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $row = $stmt->fetch() ?: [];
    return array_map(fn($v) => (int)($v ?? 0), $row);
}

function ghAverageStayLength(PDO $pdo, string $from, string $to): float {
    $stmt = $pdo->prepare("
        SELECT AVG(DATEDIFF(check_out_date, check_in_date))
        FROM guest_house_bookings
        WHERE status = 'checked_out'
          AND check_in_date >= :from AND check_out_date <= :to
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    $avg = $stmt->fetchColumn();
    return $avg ? round((float)$avg, 1) : 0.0;
}

function ghTopSponsoringOffices(PDO $pdo, string $from, string $to, int $limit = 10): array {
    $limit = max(1, (int)$limit);
    $stmt = $pdo->prepare("
        SELECT o.office_id, o.office_name, COUNT(b.booking_id) AS total
        FROM guest_house_bookings b
        JOIN offices o ON b.sponsoring_office_id = o.office_id
        WHERE b.check_in_date <= :to AND b.check_out_date >= :from
          AND b.status != 'cancelled'
        GROUP BY o.office_id
        ORDER BY total DESC
        LIMIT {$limit}
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

function ghRoomUtilization(PDO $pdo, string $from, string $to): array {
    $days = max(1, (int)((strtotime($to) - strtotime($from)) / 86400) + 1);

    $stmt = $pdo->prepare("
        SELECT r.room_id, r.room_number, t.type_name, r.capacity,
               COALESCE(SUM(
                   DATEDIFF(
                       LEAST(b.check_out_date, :to),
                       GREATEST(b.check_in_date, :from)
                   ) + 1
               ), 0) AS nights_booked
        FROM guest_house_rooms r
        JOIN gh_room_types t ON r.type_id = t.type_id
        LEFT JOIN guest_house_bookings b
          ON b.room_id = r.room_id
         AND b.status != 'cancelled'
         AND b.check_in_date <= :to2
         AND b.check_out_date >= :from2
        WHERE r.status != 'inactive'
        GROUP BY r.room_id
        ORDER BY nights_booked DESC, r.room_number
    ");
    $stmt->execute([
        ':from' => $from, ':to' => $to,
        ':from2' => $from, ':to2' => $to,
    ]);
    $rows = $stmt->fetchAll();

    foreach ($rows as &$r) {
        $r['nights_booked'] = max(0, (int)$r['nights_booked']);
        $r['nights_available'] = $days;
        $r['utilization_pct'] = $days > 0 ? round(($r['nights_booked'] / $days) * 100) : 0;
    }
    return $rows;
}

/**
 * Daily occupancy percentages for a date range (for chart).
 */
function ghDailyOccupancy(PDO $pdo, string $from, string $to): array {
    $totalRooms = (int)$pdo->query("SELECT COUNT(*) FROM guest_house_rooms WHERE status != 'inactive'")->fetchColumn();
    if ($totalRooms === 0) return [];

    $days = [];
    $cursor = strtotime($from);
    $end    = strtotime($to);
    while ($cursor <= $end) {
        $days[date('Y-m-d', $cursor)] = 0;
        $cursor += 86400;
    }

    $stmt = $pdo->prepare("
        SELECT check_in_date, check_out_date, room_id
        FROM guest_house_bookings
        WHERE status IN ('checked_in','occupied','checked_out','reserved')
          AND check_in_date <= :to AND check_out_date >= :from
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    foreach ($stmt->fetchAll() as $b) {
        $s = max(strtotime($b['check_in_date']), strtotime($from));
        $e = min(strtotime($b['check_out_date']), strtotime($to));
        for ($t = $s; $t <= $e; $t += 86400) {
            $key = date('Y-m-d', $t);
            if (isset($days[$key])) $days[$key]++;
        }
    }

    $out = [];
    foreach ($days as $d => $count) {
        $out[] = [
            'date'     => $d,
            'occupied' => $count,
            'percent'  => round(($count / $totalRooms) * 100),
        ];
    }
    return $out;
}

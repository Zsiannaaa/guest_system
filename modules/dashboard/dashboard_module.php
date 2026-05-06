<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Dashboard data-access module that gathers counts and recent activity for role-specific dashboards.
 * Flow: Called by browser pages in public/; returns data or performs database changes, then the page renders the result.
 * Security: These functions expect validated inputs from controllers and use prepared statements for database values.
 */
/**
 * modules/dashboard/dashboard_module.php — Dashboard Model
 *
 * Contains ALL database logic for dashboard stats and widgets.
 * No HTML, no session checks — pure data functions.
 * Called by: public/dashboard/admin.php, guard.php, office.php
 */

/**
 * Get admin dashboard statistics.
 */
function getAdminDashboardStats(PDO $pdo): array {
    $today = date('Y-m-d');
    $yesterday = date('Y-m-d', strtotime('-1 day'));

    $totalToday      = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d", [':d' => $today]);
    $insideNow       = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='checked_in'");
    $checkedOutToday = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND overall_status='checked_out'", [':d' => $today]);
    $withVehicle     = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND has_vehicle=1", [':d' => $today]);
    $overstayed      = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='overstayed'");
    $yesterdayTotal   = getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d", [':d' => $yesterday]);
    $pctChange       = $yesterdayTotal > 0 ? round((($totalToday - $yesterdayTotal) / $yesterdayTotal) * 100) : 0;

    return compact('totalToday', 'insideNow', 'checkedOutToday', 'withVehicle', 'overstayed', 'pctChange');
}

/**
 * Fetch active visitors for dashboard widget (limit 5).
 */
function getActiveVisitorsForDashboard(PDO $pdo, int $limit = 5, bool $includeRestricted = false): array {
    $restrictedSelect = $includeRestricted ? ', g.is_restricted' : '';
    // Study query: Prepared SQL: reads rows from guest_visits, guests, visit_destinations, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.actual_check_in, gv.registration_type,
               g.full_name AS guest_name{$restrictedSelect},
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations,
               MAX(CASE WHEN vd.is_unplanned=1 THEN 'Unplanned' ELSE 'Primary' END) AS dest_type
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
        LEFT JOIN offices o ON vd.office_id = o.office_id
        WHERE gv.overall_status = 'checked_in'
        GROUP BY gv.visit_id
        ORDER BY gv.actual_check_in DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch recent visit registrations for dashboard widget.
 */
function getRecentVisitsForDashboard(PDO $pdo, int $limit = 5): array {
    // Study query: Prepared SQL: reads rows from guest_visits, guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.registration_type, gv.overall_status,
               gv.actual_check_in, gv.purpose_of_visit,
               g.full_name AS guest_name
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        ORDER BY gv.created_at DESC LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch visitors by office for today (donut chart).
 */
function getVisitorsByOfficeToday(PDO $pdo): array {
    return getVisitorsByOfficeForDate($pdo, date('Y-m-d'));
}

/**
 * Fetch visitors by office for a specific date.
 */
function getVisitorsByOfficeForDate(PDO $pdo, string $date): array {
    // Study query: Prepared SQL: reads rows from visit_destinations, offices, guest_visits for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT o.office_name, COUNT(vd.destination_id) AS total
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        WHERE gv.visit_date = :d
        GROUP BY o.office_id ORDER BY total DESC
    ");
    $stmt->execute([':d' => $date]);
    return $stmt->fetchAll();
}

/**
 * Fetch visitors by office across all recorded visit history.
 */
function getVisitorsByOfficeAllTime(PDO $pdo): array {
    // Study query: SQL query: reads rows from visit_destinations, offices, guest_visits for dashboard chart totals.
    $stmt = $pdo->query("
        SELECT o.office_name, COUNT(vd.destination_id) AS total
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        GROUP BY o.office_id
        ORDER BY total DESC
    ");
    return $stmt->fetchAll();
}

/**
 * Study function: Loads get latest visit date records for the page/controller.
 */
function getLatestVisitDate(PDO $pdo): ?string {
    // Study query: SQL query: reads rows from guest_visits for lookup, validation, or display.
    $date = $pdo->query("SELECT MAX(visit_date) FROM guest_visits")->fetchColumn();
    return $date ?: null;
}

/**
 * Get guard dashboard stats.
 */
function getGuardDashboardStats(PDO $pdo): array {
    $today = date('Y-m-d');
    return [
        'totalToday'  => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d", [':d' => $today]),
        'insideNow'   => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE overall_status='checked_in'"),
        'pendingToday'=> getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND overall_status='pending'", [':d' => $today]),
        'withVehicle' => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND has_vehicle=1", [':d' => $today]),
        'walkinsToday'=> getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date=:d AND registration_type='walk_in'", [':d' => $today]),
    ];
}

/**
 * Study function: Loads get pending arrivals for guard records for the page/controller.
 */
function getPendingArrivalsForGuard(PDO $pdo, string $date, int $limit = 5): array {
    // Study query: Prepared SQL: reads rows from guest_visits, guests, visit_destinations, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT gv.visit_id, gv.visit_reference, gv.visit_date, gv.expected_time_in,
               g.full_name AS guest_name,
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
        LEFT JOIN offices o ON vd.office_id = o.office_id
        WHERE gv.overall_status = 'pending' AND gv.visit_date = :d
        GROUP BY gv.visit_id
        ORDER BY gv.expected_time_in ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':d', $date);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Get office dashboard stats.
 */
function getOfficeDashboardStats(PDO $pdo, int $officeId): array {
    $today = date('Y-m-d');
    return [
        'incoming'  => getCountQuery("SELECT COUNT(*) FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id WHERE vd.office_id=:o AND vd.destination_status='pending' AND gv.overall_status='checked_in'", [':o' => $officeId]),
        'serving'   => getCountQuery("SELECT COUNT(*) FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id WHERE vd.office_id=:o AND vd.destination_status IN('arrived','in_service') AND gv.overall_status='checked_in'", [':o' => $officeId]),
        'completed' => getCountQuery("SELECT COUNT(*) FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id WHERE vd.office_id=:o AND vd.destination_status='completed' AND gv.visit_date=:d", [':o' => $officeId, ':d' => $today]),
    ];
}

/**
 * Study function: Loads get office incoming list records for the page/controller.
 */
function getOfficeIncomingList(PDO $pdo, int $officeId, int $limit = 8): array {
    // Study query: Prepared SQL: reads rows from visit_destinations, guest_visits, guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT vd.destination_id, vd.is_unplanned, gv.visit_id, gv.visit_reference,
               gv.purpose_of_visit, gv.actual_check_in, gv.registration_type,
               g.full_name AS guest_name, g.organization
        FROM visit_destinations vd
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.office_id = :o AND vd.destination_status = 'pending' AND gv.overall_status = 'checked_in'
        ORDER BY gv.actual_check_in ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':o', $officeId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Study function: Loads get office serving list records for the page/controller.
 */
function getOfficeServingList(PDO $pdo, int $officeId, int $limit = 5): array {
    // Study query: Prepared SQL: reads rows from visit_destinations, guest_visits, guests for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT vd.destination_id, vd.destination_status, gv.visit_id, gv.visit_reference,
               vd.arrival_time, g.full_name AS guest_name, g.organization
        FROM visit_destinations vd
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE vd.office_id = :o AND vd.destination_status IN ('arrived','in_service')
        ORDER BY vd.arrival_time ASC
        LIMIT :lim
    ");
    $stmt->bindValue(':o', $officeId, PDO::PARAM_INT);
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

/**
 * Fetch activity logs (with optional limit).
 */
function getActivityLogs(PDO $pdo, int $limit = 200): array {
    // Study query: Prepared SQL: reads rows from activity_logs, users, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT al.*, u.full_name AS actor_name, o.office_name
        FROM activity_logs al
        LEFT JOIN users u ON al.performed_by_user_id = u.user_id
        LEFT JOIN offices o ON al.office_id = o.office_id
        ORDER BY al.logged_at DESC
        LIMIT :lim
    ");
    $stmt->bindValue(':lim', $limit, PDO::PARAM_INT);
    $stmt->execute();
    return $stmt->fetchAll();
}

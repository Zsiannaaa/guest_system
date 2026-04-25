<?php
/**
 * modules/dashboard_module.php — Dashboard Model
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
function getActiveVisitorsForDashboard(PDO $pdo): array {
    return $pdo->query("
        SELECT gv.visit_id, gv.visit_reference, gv.actual_check_in, gv.registration_type,
               g.full_name AS guest_name,
               GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR ', ') AS destinations,
               MAX(CASE WHEN vd.is_unplanned=1 THEN 'Unplanned' ELSE 'Primary' END) AS dest_type
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        LEFT JOIN visit_destinations vd ON gv.visit_id = vd.visit_id
        LEFT JOIN offices o ON vd.office_id = o.office_id
        WHERE gv.overall_status = 'checked_in'
        GROUP BY gv.visit_id
        ORDER BY gv.actual_check_in DESC
        LIMIT 5
    ")->fetchAll();
}

/**
 * Fetch recent visit registrations for dashboard widget.
 */
function getRecentVisitsForDashboard(PDO $pdo, int $limit = 5): array {
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
    $today = date('Y-m-d');
    $stmt = $pdo->prepare("
        SELECT o.office_name, COUNT(vd.destination_id) AS total
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        WHERE gv.visit_date = :d
        GROUP BY o.office_id ORDER BY total DESC LIMIT 6
    ");
    $stmt->execute([':d' => $today]);
    return $stmt->fetchAll();
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
    ];
}

/**
 * Get office dashboard stats.
 */
function getOfficeDashboardStats(PDO $pdo, int $officeId): array {
    return [
        'incoming'  => getCountQuery("SELECT COUNT(*) FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id WHERE vd.office_id=:o AND vd.destination_status='pending' AND gv.overall_status='checked_in'", [':o' => $officeId]),
        'serving'   => getCountQuery("SELECT COUNT(*) FROM visit_destinations vd JOIN guest_visits gv ON vd.visit_id=gv.visit_id WHERE vd.office_id=:o AND vd.destination_status IN('arrived','in_service') AND gv.overall_status='checked_in'", [':o' => $officeId]),
        'completed' => getCountQuery("SELECT COUNT(*) FROM visit_destinations WHERE office_id=:o AND destination_status='completed'", [':o' => $officeId]),
    ];
}

/**
 * Fetch activity logs (with optional limit).
 */
function getActivityLogs(PDO $pdo, int $limit = 200): array {
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

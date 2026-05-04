<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Reports data-access module for filtered visit reports and summary statistics.
 * Flow: Called by browser pages in public/; returns data or performs database changes, then the page renders the result.
 * Security: These functions expect validated inputs from controllers and use prepared statements for database values.
 */
/**
 * modules/reports/reports_module.php — Report Model
 *
 * Contains ALL database logic for reports.
 * No HTML, no session checks — pure data functions.
 * Called by: public/reports/index.php
 */

/**
 * Get summary statistics for a date range.
 */
function getReportSummary(PDO $pdo, string $from, string $to): array {
    $p = [':from' => $from, ':to' => $to];
    return [
        'total_visits'   => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date BETWEEN :from AND :to", $p),
        'walk_ins'       => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date BETWEEN :from AND :to AND registration_type='walk_in'", $p),
        'pre_registered' => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date BETWEEN :from AND :to AND registration_type='pre_registered'", $p),
        'with_vehicle'   => getCountQuery("SELECT COUNT(*) FROM guest_visits WHERE visit_date BETWEEN :from AND :to AND has_vehicle=1", $p),
        'unique_guests'  => getCountQuery("SELECT COUNT(DISTINCT guest_id) FROM guest_visits WHERE visit_date BETWEEN :from AND :to", $p),
    ];
}

/**
 * Visits per day for a date range.
 */
function getVisitsPerDay(PDO $pdo, string $from, string $to): array {
    // Study query: Prepared SQL: reads rows from guest_visits, AND for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT visit_date, COUNT(*) AS total,
               SUM(registration_type='walk_in') AS walk_ins,
               SUM(registration_type='pre_registered') AS pre_reg
        FROM guest_visits
        WHERE visit_date BETWEEN :from AND :to
        GROUP BY visit_date ORDER BY visit_date DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

/**
 * Visits per office for a date range.
 */
function getVisitsPerOffice(PDO $pdo, string $from, string $to): array {
    // Study query: Prepared SQL: reads rows from visit_destinations, offices, guest_visits, AND for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT o.office_name, COUNT(vd.destination_id) AS total, SUM(vd.is_unplanned) AS unplanned
        FROM visit_destinations vd
        JOIN offices o ON vd.office_id = o.office_id
        JOIN guest_visits gv ON vd.visit_id = gv.visit_id
        WHERE gv.visit_date BETWEEN :from AND :to
        GROUP BY o.office_id ORDER BY total DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

/**
 * Status breakdown for a date range.
 */
function getStatusBreakdown(PDO $pdo, string $from, string $to): array {
    // Study query: Prepared SQL: reads rows from guest_visits, AND for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT overall_status, COUNT(*) AS cnt
        FROM guest_visits
        WHERE visit_date BETWEEN :from AND :to
        GROUP BY overall_status
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

/**
 * Full guest visit log for a date range.
 */
function getGuestVisitLog(PDO $pdo, string $from, string $to): array {
    // Study query: Prepared SQL: reads rows from guest_visits, guests, AND for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        SELECT gv.visit_reference, gv.visit_date, gv.overall_status,
               gv.registration_type, gv.actual_check_in, gv.actual_check_out,
               gv.has_vehicle, g.full_name AS guest_name, g.organization
        FROM guest_visits gv
        JOIN guests g ON gv.guest_id = g.guest_id
        WHERE gv.visit_date BETWEEN :from AND :to
        ORDER BY gv.visit_date DESC, gv.actual_check_in DESC
    ");
    $stmt->execute([':from' => $from, ':to' => $to]);
    return $stmt->fetchAll();
}

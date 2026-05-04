<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Reports page/controller for export. It reads filters, runs report queries, and renders or exports results.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * reports/export.php - Excel-compatible visit export
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
requireRole(ROLE_ADMIN);

$db = getDB();
$dateFrom = $_GET['date_from'] ?? '1970-01-01';
$dateTo = $_GET['date_to'] ?? '9999-12-31';
if (!strtotime($dateFrom)) $dateFrom = '1970-01-01';
if (!strtotime($dateTo)) $dateTo = '9999-12-31';
if ($dateFrom > $dateTo) [$dateFrom, $dateTo] = [$dateTo, $dateFrom];

// Study query: Prepared SQL: reads rows from the database for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
$stmt = $db->prepare("
    SELECT
        gv.visit_reference,
        gv.qr_token,
        gv.visit_date,
        gv.registration_type,
        gv.overall_status,
        gv.purpose_of_visit,
        gv.expected_time_in,
        gv.expected_time_out,
        gv.actual_check_in,
        gv.actual_check_out,
        gv.has_vehicle,
        gv.notes,
        g.full_name AS guest_name,
        g.contact_number,
        g.email,
        g.organization,
        g.id_type,
        g.is_restricted,
        GROUP_CONCAT(DISTINCT o.office_name ORDER BY vd.sequence_no SEPARATOR '; ') AS destinations,
        GROUP_CONCAT(DISTINCT CONCAT(o.office_name, ' - ', vd.destination_status) ORDER BY vd.sequence_no SEPARATOR '; ') AS destination_statuses,
        ve.vehicle_type,
        ve.plate_number,
        ve.has_university_sticker,
        ve.sticker_number,
        ve.vehicle_color,
        ve.vehicle_model,
        ve.driver_name,
        u.full_name AS processed_by
    FROM guest_visits gv
    JOIN guests g ON gv.guest_id = g.guest_id
    LEFT JOIN visit_destinations vd ON vd.visit_id = gv.visit_id
    LEFT JOIN offices o ON o.office_id = vd.office_id
    LEFT JOIN vehicle_entries ve ON ve.visit_id = gv.visit_id
    LEFT JOIN users u ON u.user_id = gv.processed_by_guard_id
    WHERE gv.visit_date BETWEEN :from AND :to
    GROUP BY gv.visit_id
    ORDER BY gv.visit_date DESC, gv.created_at DESC
");
$stmt->execute([':from' => $dateFrom, ':to' => $dateTo]);
$rows = $stmt->fetchAll();

$fileTo = $dateTo === '9999-12-31' ? 'all' : $dateTo;
$filename = 'guest_visit_records_' . $dateFrom . '_to_' . $fileTo . '.csv';
header('Content-Type: text/csv; charset=UTF-8');
header('Content-Disposition: attachment; filename="' . $filename . '"');
header('Pragma: no-cache');
header('Expires: 0');

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, [
    'Reference',
    'QR Token',
    'Visit Date',
    'Guest Name',
    'Contact',
    'Email',
    'Organization',
    'ID Type Presented',
    'Restricted',
    'Registration Type',
    'Status',
    'Purpose',
    'Destinations',
    'Destination Statuses',
    'Expected In',
    'Expected Out',
    'Actual Check In',
    'Actual Check Out',
    'Has Vehicle',
    'Vehicle Type',
    'Plate Number',
    'University Sticker / Pass',
    'Sticker / Pass Number',
    'Vehicle Color',
    'Vehicle Model',
    'Driver Name',
    'Processed By',
    'Notes',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['visit_reference'],
        $r['qr_token'],
        $r['visit_date'],
        $r['guest_name'],
        $r['contact_number'],
        $r['email'],
        $r['organization'],
        $r['id_type'],
        $r['is_restricted'] ? 'Yes' : 'No',
        statusLabel($r['registration_type']),
        statusLabel($r['overall_status']),
        $r['purpose_of_visit'],
        $r['destinations'],
        $r['destination_statuses'],
        $r['expected_time_in'],
        $r['expected_time_out'],
        $r['actual_check_in'],
        $r['actual_check_out'],
        $r['has_vehicle'] ? 'Yes' : 'No',
        $r['vehicle_type'],
        $r['plate_number'],
        $r['has_university_sticker'] ? 'Yes' : 'No',
        $r['sticker_number'],
        $r['vehicle_color'],
        $r['vehicle_model'],
        $r['driver_name'],
        $r['processed_by'],
        $r['notes'],
    ]);
}
fclose($out);
exit;

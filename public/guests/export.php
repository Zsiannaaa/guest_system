<?php
/**
 * guests/export.php - Excel-compatible guest export
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

$db = getDB();
$guestId = (int)($_GET['id'] ?? 0);

function csvValue($value): string
{
    return $value === null || $value === '' ? '' : (string)$value;
}

header('Content-Type: text/csv; charset=UTF-8');
header('Pragma: no-cache');
header('Expires: 0');

if ($guestId > 0) {
    $guestStmt = $db->prepare("SELECT * FROM guests WHERE guest_id=:id LIMIT 1");
    $guestStmt->execute([':id' => $guestId]);
    $guest = $guestStmt->fetch();

    if (!$guest) {
        http_response_code(404);
        exit('Guest not found.');
    }

    $safeName = preg_replace('/[^A-Za-z0-9_-]+/', '_', strtolower($guest['full_name']));
    header('Content-Disposition: attachment; filename="guest_profile_' . $guestId . '_' . trim($safeName, '_') . '.csv"');

    $vehicleStmt = $db->prepare("
        SELECT ve.*, gv.visit_reference, gv.visit_date
        FROM vehicle_entries ve
        JOIN guest_visits gv ON gv.visit_id=ve.visit_id
        WHERE gv.guest_id=:gid
        ORDER BY gv.visit_date DESC, gv.created_at DESC, ve.created_at DESC
    ");
    $vehicleStmt->execute([':gid' => $guestId]);
    $vehicles = $vehicleStmt->fetchAll();

    $visitStmt = $db->prepare("
        SELECT
            gv.visit_reference,
            gv.visit_date,
            gv.registration_type,
            gv.overall_status,
            gv.purpose_of_visit,
            gv.actual_check_in,
            gv.actual_check_out,
            gv.has_vehicle,
            GROUP_CONCAT(o.office_name ORDER BY vd.sequence_no SEPARATOR '; ') AS offices,
            ve.vehicle_type,
            ve.plate_number,
            ve.vehicle_color,
            ve.vehicle_model,
            ve.driver_name
        FROM guest_visits gv
        LEFT JOIN visit_destinations vd ON vd.visit_id=gv.visit_id
        LEFT JOIN offices o ON o.office_id=vd.office_id
        LEFT JOIN vehicle_entries ve ON ve.visit_id=gv.visit_id
        WHERE gv.guest_id=:gid
        GROUP BY gv.visit_id
        ORDER BY gv.visit_date DESC, gv.created_at DESC
    ");
    $visitStmt->execute([':gid' => $guestId]);
    $visits = $visitStmt->fetchAll();

    $out = fopen('php://output', 'w');
    fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));

    fputcsv($out, ['Guest Profile']);
    fputcsv($out, ['Field', 'Value']);
    fputcsv($out, ['Guest ID', $guest['guest_id']]);
    fputcsv($out, ['Full Name', $guest['full_name']]);
    fputcsv($out, ['Contact', $guest['contact_number']]);
    fputcsv($out, ['Email', $guest['email']]);
    fputcsv($out, ['Organization', $guest['organization']]);
    fputcsv($out, ['Address', $guest['address']]);
    fputcsv($out, ['ID Type', $guest['id_type']]);
    fputcsv($out, ['Restricted', $guest['is_restricted'] ? 'Yes' : 'No']);
    fputcsv($out, ['Restriction Reason', $guest['restriction_reason']]);
    fputcsv($out, ['First Record', $guest['created_at']]);
    fputcsv($out, ['Last Updated', $guest['updated_at']]);
    fputcsv($out, []);

    fputcsv($out, ['Vehicle Records']);
    if (empty($vehicles)) {
        fputcsv($out, ['No vehicle records']);
    } else {
        fputcsv($out, ['Visit Reference', 'Visit Date', 'Type', 'Plate Number', 'Color', 'Model', 'Driver', 'Driver Is Guest']);
        foreach ($vehicles as $v) {
            fputcsv($out, [
                $v['visit_reference'],
                $v['visit_date'],
                statusLabel($v['vehicle_type']),
                $v['plate_number'],
                $v['vehicle_color'],
                $v['vehicle_model'],
                $v['driver_name'],
                $v['is_driver_the_guest'] ? 'Yes' : 'No',
            ]);
        }
    }
    fputcsv($out, []);

    fputcsv($out, ['Visit History']);
    fputcsv($out, ['Reference', 'Date', 'Type', 'Status', 'Purpose', 'Offices', 'Check In', 'Check Out', 'Has Vehicle', 'Vehicle Type', 'Plate Number', 'Vehicle Color', 'Vehicle Model', 'Driver Name']);
    foreach ($visits as $v) {
        fputcsv($out, [
            $v['visit_reference'],
            $v['visit_date'],
            statusLabel($v['registration_type']),
            statusLabel($v['overall_status']),
            $v['purpose_of_visit'],
            $v['offices'],
            $v['actual_check_in'],
            $v['actual_check_out'],
            $v['has_vehicle'] ? 'Yes' : 'No',
            $v['vehicle_type'],
            $v['plate_number'],
            $v['vehicle_color'],
            $v['vehicle_model'],
            $v['driver_name'],
        ]);
    }
    fclose($out);
    exit;
}

header('Content-Disposition: attachment; filename="guest_directory_export_' . date('Y-m-d') . '.csv"');

$stmt = $db->prepare("
    SELECT
        g.guest_id,
        g.full_name,
        g.contact_number,
        g.email,
        g.organization,
        g.address,
        g.id_type,
        g.is_restricted,
        g.restriction_reason,
        g.created_at,
        COUNT(DISTINCT gv.visit_id) AS total_visits,
        MAX(gv.visit_date) AS last_visit,
        SUM(gv.has_vehicle=1) AS vehicle_visit_count,
        latest_vehicle.vehicle_type,
        latest_vehicle.plate_number,
        latest_vehicle.vehicle_color,
        latest_vehicle.vehicle_model,
        latest_vehicle.driver_name
    FROM guests g
    LEFT JOIN guest_visits gv ON gv.guest_id=g.guest_id
    LEFT JOIN (
        SELECT ve.*, gv2.guest_id
        FROM vehicle_entries ve
        JOIN guest_visits gv2 ON gv2.visit_id=ve.visit_id
        JOIN (
            SELECT gv3.guest_id, MAX(ve3.vehicle_id) AS latest_vehicle_id
            FROM vehicle_entries ve3
            JOIN guest_visits gv3 ON gv3.visit_id=ve3.visit_id
            GROUP BY gv3.guest_id
        ) latest ON latest.latest_vehicle_id=ve.vehicle_id
    ) latest_vehicle ON latest_vehicle.guest_id=g.guest_id
    GROUP BY g.guest_id
    ORDER BY g.full_name
");
$stmt->execute();
$rows = $stmt->fetchAll();

$out = fopen('php://output', 'w');
fprintf($out, chr(0xEF) . chr(0xBB) . chr(0xBF));
fputcsv($out, [
    'Guest ID',
    'Full Name',
    'Contact',
    'Email',
    'Organization',
    'Address',
    'ID Type',
    'Restricted',
    'Restriction Reason',
    'First Record',
    'Total Visits',
    'Last Visit',
    'Vehicle Visit Count',
    'Latest Vehicle Type',
    'Latest Plate Number',
    'Latest Vehicle Color',
    'Latest Vehicle Model',
    'Latest Driver Name',
]);

foreach ($rows as $r) {
    fputcsv($out, [
        $r['guest_id'],
        $r['full_name'],
        $r['contact_number'],
        $r['email'],
        $r['organization'],
        $r['address'],
        $r['id_type'],
        $r['is_restricted'] ? 'Yes' : 'No',
        $r['restriction_reason'],
        $r['created_at'],
        $r['total_visits'],
        $r['last_visit'],
        $r['vehicle_visit_count'] ?: 0,
        $r['vehicle_type'],
        $r['plate_number'],
        $r['vehicle_color'],
        $r['vehicle_model'],
        $r['driver_name'],
    ]);
}
fclose($out);
exit;

<?php
/**
 * guests/delete.php - Admin-only guest deletion endpoint
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
require_once __DIR__ . '/../../modules/guests_module.php';

requireRole(ROLE_ADMIN);

if (!isPost()) {
    redirect(APP_URL . '/public/guests/list.php');
}

$db = getDB();
$guestId = inputInt('guest_id', 'POST');
$returnTo = $_POST['return_to'] ?? APP_URL . '/public/guests/list.php';
if (!str_starts_with($returnTo, APP_URL . '/public/guests/')) {
    $returnTo = APP_URL . '/public/guests/list.php';
}

verifyCsrf($returnTo);

$guest = $guestId > 0 ? getGuestById($db, $guestId) : false;
if (!$guest) {
    setFlash('error', 'Guest not found.');
    redirect(APP_URL . '/public/guests/list.php');
}

$err = deleteGuest($db, $guestId);
if ($err) {
    setFlash('error', $err);
    redirect($returnTo);
}

logActivity(null, 'other', currentUserId(), null, "Guest #{$guestId} ({$guest['full_name']}) deleted");
setFlash('success', 'Guest record deleted.');
redirect(APP_URL . '/public/guests/list.php');

<?php
/**
 * Deprecated route: manual destination adding was replaced by office receive lookup.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
requireLogin();

setFlash('info', 'Manual destination adding has been replaced. Use Receive Visitor to record unexpected office visits.');

if (isOfficeStaff()) {
    redirect(APP_URL . '/public/office/lookup.php');
}

redirect(APP_URL . '/public/visits/lookup.php');

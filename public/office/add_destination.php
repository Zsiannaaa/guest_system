<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Office staff page/controller for add destination visits and destinations. It connects office actions to visit destination records.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Role checks, CSRF checks, prepared statements, and escaped output are used here to protect forms and direct URL access.
 */
/**
 * Deprecated route: manual destination adding was replaced by office receive lookup.
 */
require_once __DIR__ . '/../../config/db.php';
require_once __DIR__ . '/../../config/constants.php';
require_once __DIR__ . '/../../includes/auth.php';
require_once __DIR__ . '/../../includes/helpers.php';
// Study security: this page requires an active login before any private data is shown.
requireLogin();

setFlash('info', 'Manual destination adding has been replaced. Use Receive Visitor to record unexpected office visits.');

if (isOfficeStaff()) {
    // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
    redirect(APP_URL . '/public/office/lookup.php');
}

// Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
redirect(APP_URL . '/public/visits/lookup.php');

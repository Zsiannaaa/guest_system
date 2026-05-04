<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Logout endpoint that destroys the current session and returns the user to the login page.
 * Flow: Browser-accessible route: load config/includes, protect access if needed, handle GET/POST, call modules or SQL, then render HTML.
 * Security: Clears session data so protected pages cannot continue using the old login state.
 */
require_once __DIR__ . "/../../config/constants.php";
require_once __DIR__ . "/../../config/db.php";
require_once __DIR__ . "/../../includes/helpers.php";
require_once __DIR__ . "/../../includes/auth.php";
logout();

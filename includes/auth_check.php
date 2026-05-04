<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Small guard include that protects pages by checking the current user role before the page continues.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * includes/auth_check.php
 * General authentication guard — require any logged-in user.
 *
 * Usage: require_once 'includes/auth_check.php';
 * Place at the top of any page that requires login (regardless of role).
 *
 * Redirects to login page if the user is not authenticated.
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/session.php';     // Start session + handle timeout

// If no user_id in session → not logged in → redirect to login
if (empty($_SESSION['user_id'])) {
    // Preserve the intended destination so we can redirect after login (future enhancement)
    $_SESSION['redirect_after_login'] = $_SERVER['REQUEST_URI'] ?? '';
    // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
    header('Location: ' . APP_URL . '/public/auth/login.php');
    exit;
}

<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Small guard include that protects pages by checking the current user role before the page continues.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * includes/admin_check.php
 * Admin-only access guard.
 *
 * Usage: require_once 'includes/admin_check.php';
 * Place at the top of any admin-only page.
 *
 * Redirects to unauthorized.php if the logged-in user is not an admin.
 */

require_once __DIR__ . '/auth_check.php';   // Ensures user is logged in first

if ($_SESSION['user_role'] !== 'admin') {
    // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

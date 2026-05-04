<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Small guard include that protects pages by checking the current user role before the page continues.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Keep access checks in the calling page and escape user-controlled output before displaying it.
 */
/**
 * includes/guest_house_check.php
 * Access guard: Admin or Guest House staff only.
 *
 * Usage (at top of any /public/guest_house/* page):
 *   require_once __DIR__ . '/../../includes/guest_house_check.php';
 */

require_once __DIR__ . '/auth_check.php';

if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'guest_house_staff'], true)) {
    // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

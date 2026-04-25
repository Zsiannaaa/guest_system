<?php
/**
 * includes/office_check.php
 * Office staff access guard.
 *
 * Usage: require_once 'includes/office_check.php';
 * Place at the top of office staff pages.
 *
 * Allows 'office_staff' AND 'admin' roles.
 * Redirects to unauthorized.php if the logged-in user is neither.
 */

require_once __DIR__ . '/auth_check.php';   // Ensures user is logged in first

$allowedRoles = ['office_staff', 'admin'];

if (!in_array($_SESSION['user_role'], $allowedRoles, true)) {
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

// Ensure office_staff users have an assigned office
// (Admin may not have an office_id — that's fine)
if ($_SESSION['user_role'] === 'office_staff' && empty($_SESSION['office_id'])) {
    // Office staff without an assigned office cannot use office pages
    $_SESSION = [];
    session_destroy();
    header('Location: ' . APP_URL . '/public/auth/login.php?error=no_office');
    exit;
}

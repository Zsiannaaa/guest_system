<?php
/**
 * includes/guard_check.php
 * Guard / Reception staff access guard.
 *
 * Usage: require_once 'includes/guard_check.php';
 * Place at the top of guard-only pages.
 *
 * Allows both 'guard' role AND 'admin' (admins can view guard pages).
 * Redirects to unauthorized.php if the logged-in user is neither.
 */

require_once __DIR__ . '/auth_check.php';   // Ensures user is logged in first

$allowedRoles = ['guard', 'admin'];

if (!in_array($_SESSION['user_role'], $allowedRoles, true)) {
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

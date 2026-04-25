<?php
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
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

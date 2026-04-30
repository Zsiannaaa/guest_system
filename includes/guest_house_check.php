<?php
/**
 * includes/guest_house_check.php
 * Access guard: Admin or Guest House staff only.
 *
 * Usage (at top of any /public/guest_house/* page):
 *   require_once __DIR__ . '/../../includes/guest_house_check.php';
 */

require_once __DIR__ . '/auth_check.php';

if (!in_array($_SESSION['user_role'] ?? '', ['admin', 'guest_house_staff'], true)) {
    header('Location: ' . APP_URL . '/unauthorized.php');
    exit;
}

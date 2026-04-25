<?php
/**
 * includes/session.php
 * Session initialization with security hardening
 * Include this at the top of every page before any output
 */

// Only start session if not already active
if (session_status() === PHP_SESSION_NONE) {
    // Secure cookie flags
    ini_set('session.cookie_httponly', 1);    // Prevent JS access to session cookie
    ini_set('session.use_only_cookies', 1);   // No session IDs in URL
    ini_set('session.use_strict_mode', 1);    // Reject uninitialized session IDs
    // ini_set('session.cookie_secure', 1);   // Enable this on HTTPS

    session_start();
}

// ── Session timeout enforcement ───────────────────────────────────
// If user has been inactive longer than SESSION_TIMEOUT, destroy session

// Load constants if not already loaded
if (!defined('SESSION_TIMEOUT')) {
    require_once __DIR__ . '/../config/constants.php';
}

if (
    isset($_SESSION['user_id'], $_SESSION['last_activity'])
    && !in_array($_SESSION['user_role'] ?? '', ['guard', 'office_staff'], true)
) {
    $idle = time() - $_SESSION['last_activity'];
    if ($idle > SESSION_TIMEOUT) {
        // Session expired — clean up and redirect
        $_SESSION = [];
        if (ini_get('session.use_cookies')) {
            $p = session_get_cookie_params();
            setcookie(
                session_name(), '',
                time() - 42000,
                $p['path'], $p['domain'],
                $p['secure'], $p['httponly']
            );
        }
        session_destroy();
        header('Location: ' . APP_URL . '/public/auth/login.php?timeout=1');
        exit;
    }
}

// Update last activity timestamp
if (isset($_SESSION['user_id'])) {
    $_SESSION['last_activity'] = time();
}

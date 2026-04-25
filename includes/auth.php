<?php
/**
 * includes/auth.php
 * Session handling, authentication, RBAC, and security enforcement
 *
 * Security features:
 *   - Session timeout (idle check)
 *   - Session fixation prevention (regenerate ID on login)
 *   - Role-Based Access Control (RBAC) with requireRole()
 *   - CSRF protection via helpers.php
 *   - Anti-URL-tampering via inputInt()/inputStr() in helpers.php
 *   - HTTP-only session cookies
 */

require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/helpers.php';

// Start session only if not already started
if (session_status() === PHP_SESSION_NONE) {
    ini_set('session.cookie_httponly', 1);
    ini_set('session.use_only_cookies', 1);
    ini_set('session.use_strict_mode', 1);
    // ini_set('session.cookie_secure', 1); // Enable on HTTPS
    session_start();
}

// ─────────────────────────────────────────────────────────────
// SESSION TIMEOUT CHECK
// ─────────────────────────────────────────────────────────────
function checkSessionTimeout(): void {
    if (in_array($_SESSION['user_role'] ?? '', [ROLE_GUARD, ROLE_OFFICE_STAFF], true)) {
        $_SESSION['last_activity'] = time();
        return;
    }

    if (isset($_SESSION['last_activity'])) {
        $idle = time() - $_SESSION['last_activity'];
        if ($idle > SESSION_TIMEOUT) {
            sessionDestroy();
            header('Location: ' . APP_URL . '/public/auth/login.php?timeout=1');
            exit;
        }
    }
    $_SESSION['last_activity'] = time();
}

// ─────────────────────────────────────────────────────────────
// AUTHENTICATION
// ─────────────────────────────────────────────────────────────

/**
 * Check if a user is logged in.
 */
function isLoggedIn(): bool {
    return isset($_SESSION['user_id']) && !empty($_SESSION['user_id']);
}

/**
 * Require the user to be logged in.
 * Redirects to login page if not authenticated.
 */
function requireLogin(): void {
    checkSessionTimeout();
    if (!isLoggedIn()) {
        header('Location: ' . APP_URL . '/public/auth/login.php');
        exit;
    }
}

/**
 * Require a specific role (or array of roles).
 * RBAC enforcement: blocks unauthorized URL access.
 *
 * @param string|array $roles Allowed role(s)
 */
function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        // Log the unauthorized access attempt
        logActivity(null, 'other', currentUserId(), null,
            "Blocked access to " . ($_SERVER['REQUEST_URI'] ?? 'unknown') .
            " — Role: " . ($_SESSION['user_role'] ?? 'none'));
        header('Location: ' . APP_URL . '/unauthorized.php');
        exit;
    }
}

/**
 * Verify that a resource belongs to the current user's office.
 * Prevents office_staff from accessing other offices' data via URL tampering.
 *
 * @param int $resourceOfficeId The office_id of the resource being accessed
 */
function requireOwnOffice(int $resourceOfficeId): void {
    if (isAdmin()) return; // Admins can access everything
    if (isOfficeStaff() && currentOfficeId() !== $resourceOfficeId) {
        logActivity(null, 'other', currentUserId(), $resourceOfficeId,
            "Office staff tried to access office #{$resourceOfficeId} data");
        setFlash('error', 'You can only access data for your own office.');
        redirect(getDashboardUrl());
    }
}

/**
 * Validate that a record ID is a positive integer (anti-tampering).
 * Redirects if invalid.
 *
 * @param mixed  $id       The ID to validate
 * @param string $redirect URL to redirect to if invalid
 * @return int The validated ID
 */
function requireValidId(mixed $id, string $redirect): int {
    $id = (int) $id;
    if ($id <= 0) {
        setFlash('error', 'Invalid request.');
        redirect($redirect);
    }
    return $id;
}

/**
 * Attempt to log in a user with username and password.
 * Returns user data array on success, false on failure.
 */
function attemptLogin(string $username, string $password): array|false {
    $db = getDB();
    $login = trim($username);

    $stmt = $db->prepare("
        SELECT u.*, o.office_name
        FROM users u
        LEFT JOIN offices o ON u.office_id = o.office_id
        WHERE (LOWER(u.username) = LOWER(:login_username) OR LOWER(u.email) = LOWER(:login_email))
          AND u.status = 'active'
        LIMIT 1
    ");
    $stmt->execute([':login_username' => $login, ':login_email' => $login]);
    $user = $stmt->fetch();

    if (!$user) return false;
    if (!password_verify($password, $user['password_hash'])) return false;

    // Update last login timestamp
    $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id")
       ->execute([':id' => $user['user_id']]);

    return $user;
}

/**
 * Create authenticated session for a user.
 */
function createUserSession(array $user): void {
    // Regenerate session ID to prevent session fixation
    session_regenerate_id(true);

    $_SESSION['user_id']       = $user['user_id'];
    $_SESSION['user_name']     = $user['full_name'];
    $_SESSION['user_role']     = $user['role'];
    $_SESSION['user_email']    = $user['email'];
    $_SESSION['user_username'] = $user['username'];
    $_SESSION['office_id']     = $user['office_id'];
    $_SESSION['office_name']   = $user['office_name'] ?? null;
    $_SESSION['last_activity'] = time();
    $_SESSION['login_time']    = time();
    $_SESSION['ip_address']    = $_SERVER['REMOTE_ADDR'] ?? '';

    // Generate a fresh CSRF token on login
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

/**
 * Destroy the current session and log out.
 */
function sessionDestroy(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000,
            $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

/**
 * Log a user out and redirect to login page.
 */
function logout(): void {
    if (isLoggedIn()) {
        logActivity(null, 'user_logout', $_SESSION['user_id'], null, 'User logged out');
    }
    sessionDestroy();
    header('Location: ' . APP_URL . '/public/auth/login.php?logout=1');
    exit;
}

// ─────────────────────────────────────────────────────────────
// ROLE HELPER FUNCTIONS
// ─────────────────────────────────────────────────────────────

function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_ADMIN;
}

function isGuard(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_GUARD;
}

function isOfficeStaff(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_OFFICE_STAFF;
}

function isAdminOrGuard(): bool {
    return isAdmin() || isGuard();
}

function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

function currentUser(): array {
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['user_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'office_id' => $_SESSION['office_id'] ?? null,
    ];
}

function currentUserName(): string {
    return $_SESSION['user_name'] ?? 'Unknown';
}

function currentUserRole(): string {
    return $_SESSION['user_role'] ?? '';
}

function currentOfficeId(): ?int {
    return $_SESSION['office_id'] ?? null;
}

/**
 * Get the dashboard URL for the current user's role.
 */
function getDashboardUrl(): string {
    return match ($_SESSION['user_role'] ?? '') {
        ROLE_ADMIN        => APP_URL . '/public/dashboard/admin.php',
        ROLE_GUARD        => APP_URL . '/public/dashboard/guard.php',
        ROLE_OFFICE_STAFF => APP_URL . '/public/dashboard/office.php',
        default           => APP_URL . '/public/auth/login.php',
    };
}

// ─────────────────────────────────────────────────────────────
// ACTIVITY LOGGING
// ─────────────────────────────────────────────────────────────

/**
 * Insert a record into activity_logs.
 */
function logActivity(
    ?int $visitId,
    string $actionType,
    ?int $performedByUserId = null,
    ?int $officeId = null,
    ?string $description = null
): void {
    try {
        $db   = getDB();
        $ip   = $_SERVER['REMOTE_ADDR'] ?? null;
        $stmt = $db->prepare("
            INSERT INTO activity_logs
                (visit_id, action_type, performed_by_user_id, office_id, description, ip_address)
            VALUES
                (:visit_id, :action_type, :user_id, :office_id, :description, :ip)
        ");
        $stmt->execute([
            ':visit_id'    => $visitId,
            ':action_type' => $actionType,
            ':user_id'     => $performedByUserId,
            ':office_id'   => $officeId,
            ':description' => $description,
            ':ip'          => $ip,
        ]);
    } catch (PDOException $e) {
        error_log('Failed to write activity log: ' . $e->getMessage());
    }
}

<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Central authentication, session, role access, and activity logging helper file.
 * Flow: Included by public pages and modules to reuse common behavior across the system.
 * Security: Implements login checks, role-based access control, session timeout, session fixation protection, and audit logs.
 */
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

if (!headers_sent()) {
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Cache-Control: post-check=0, pre-check=0', false);
    header('Pragma: no-cache');
    header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');
}

// ─────────────────────────────────────────────────────────────
// SESSION TIMEOUT CHECK
// ─────────────────────────────────────────────────────────────
/**
 * Study function: Checks check session timeout rules before the workflow continues.
 */
function checkSessionTimeout(): void {
    if (in_array($_SESSION['user_role'] ?? '', [ROLE_GUARD, ROLE_OFFICE_STAFF], true)) {
        $_SESSION['last_activity'] = time();
        return;
    }

    if (isset($_SESSION['last_activity'])) {
        $idle = time() - $_SESSION['last_activity'];
        if ($idle > SESSION_TIMEOUT) {
            sessionDestroy();
            // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
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
// Study security: this page requires an active login before any private data is shown.
/**
 * Study function: Checks require login rules before the workflow continues.
 */
function requireLogin(): void {
    checkSessionTimeout();
    if (!isLoggedIn()) {
        // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
        header('Location: ' . APP_URL . '/public/auth/login.php');
        exit;
    }
    autoCheckoutExpiredCampusVisits();
}

/**
 * Automatically closes campus visits that were left checked in for more than
 * 24 hours. This keeps the active visitor list from carrying stale records
 * when a guard forgets to check someone out.
 */
function autoCheckoutExpiredCampusVisits(int $hours = 24): int {
    static $hasRun = false;
    if ($hasRun) return 0;
    $hasRun = true;

    $hours = max(1, min($hours, 168));
    $db = getDB();

    try {
        $select = $db->prepare("
            SELECT visit_id, visit_reference, actual_check_in
            FROM guest_visits
            WHERE overall_status = 'checked_in'
              AND actual_check_in IS NOT NULL
              AND actual_check_in <= DATE_SUB(NOW(), INTERVAL {$hours} HOUR)
              AND (purpose_of_visit IS NULL OR purpose_of_visit NOT LIKE '[Guest House]%')
            ORDER BY actual_check_in ASC
            LIMIT 100
        ");
        $select->execute();
        $visits = $select->fetchAll();

        if (empty($visits)) return 0;

        $db->beginTransaction();

        $visitUpdate = $db->prepare("
            UPDATE guest_visits
            SET overall_status = 'checked_out',
                actual_check_out = DATE_ADD(actual_check_in, INTERVAL {$hours} HOUR),
                notes = CONCAT(
                    COALESCE(NULLIF(notes, ''), ''),
                    CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE '\n' END,
                    '[Auto checkout] System closed this visit after {$hours} hours because it was still marked checked in.'
                )
            WHERE visit_id = :visit_id
              AND overall_status = 'checked_in'
        ");

        $destinationUpdate = $db->prepare("
            UPDATE visit_destinations
            SET destination_status = 'completed',
                completed_time = COALESCE(completed_time, NOW()),
                notes = CONCAT(
                    COALESCE(NULLIF(notes, ''), ''),
                    CASE WHEN notes IS NULL OR notes = '' THEN '' ELSE '\n' END,
                    '[Auto checkout] Visit was automatically closed after {$hours} hours.'
                )
            WHERE visit_id = :visit_id
              AND destination_status IN ('pending', 'arrived', 'in_service')
        ");

        $log = $db->prepare("
            INSERT INTO activity_logs
                (visit_id, action_type, performed_by_user_id, office_id, description, ip_address)
            VALUES
                (:visit_id, 'check_out', NULL, NULL, :description, :ip)
        ");

        $ip = $_SERVER['REMOTE_ADDR'] ?? null;
        $closed = 0;

        foreach ($visits as $visit) {
            $visitUpdate->execute([':visit_id' => $visit['visit_id']]);
            if ($visitUpdate->rowCount() < 1) continue;

            $destinationUpdate->execute([':visit_id' => $visit['visit_id']]);
            $log->execute([
                ':visit_id' => $visit['visit_id'],
                ':description' => "System auto-checked out {$visit['visit_reference']} after {$hours} hours.",
                ':ip' => $ip,
            ]);
            $closed++;
        }

        $db->commit();
        return $closed;
    } catch (Throwable $e) {
        if ($db->inTransaction()) {
            $db->rollBack();
        }
        error_log('Auto checkout failed: ' . $e->getMessage());
        return 0;
    }
}

/**
 * Require a specific role (or array of roles).
 * RBAC enforcement: blocks unauthorized URL access.
 *
 * @param string|array $roles Allowed role(s)
 */
// Study security: role-based access control blocks users from opening this page by URL unless their role is allowed.
/**
 * Study function: Checks require role rules before the workflow continues.
 */
function requireRole(string|array $roles): void {
    requireLogin();
    $roles = (array) $roles;
    if (!in_array($_SESSION['user_role'], $roles, true)) {
        // Log the unauthorized access attempt
        // Study audit: this records the important action in activity_logs for review and accountability.
        logActivity(null, 'other', currentUserId(), null,
            "Blocked access to " . ($_SERVER['REQUEST_URI'] ?? 'unknown') .
            " — Role: " . ($_SESSION['user_role'] ?? 'none'));
        // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
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
// Study security: office staff can only work with records that belong to their assigned office.
/**
 * Study function: Checks require own office rules before the workflow continues.
 */
function requireOwnOffice(int $resourceOfficeId): void {
    if (isAdmin()) return; // Admins can access everything
    if (isOfficeStaff() && currentOfficeId() !== $resourceOfficeId) {
        // Study audit: this records the important action in activity_logs for review and accountability.
        logActivity(null, 'other', currentUserId(), $resourceOfficeId,
            "Office staff tried to access office #{$resourceOfficeId} data");
        setFlash('error', 'You can only access data for your own office.');
        // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
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
        // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
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

    // Study query: Prepared SQL: reads rows from users, offices for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
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
    // Study security: password_verify compares the typed password with the stored hash without exposing the real password.
    if (!password_verify($password, $user['password_hash'])) return false;

    // Update last login timestamp
    // Study query: Prepared SQL: updates existing row(s) in USERS. Placeholders keep user/form values separate from the SQL text.
    $db->prepare("UPDATE users SET last_login = NOW() WHERE user_id = :id")
       ->execute([':id' => $user['user_id']]);

    return $user;
}

/**
 * Create authenticated session for a user.
 */
function createUserSession(array $user): void {
    // Regenerate session ID to prevent session fixation
    // Study security: regenerating the session ID prevents session fixation after login.
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
    $_SESSION['play_login_sound'] = 1;

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
        // Study audit: this records the important action in activity_logs for review and accountability.
        logActivity(null, 'user_logout', $_SESSION['user_id'], null, 'User logged out');
    }
    sessionDestroy();
    // Study flow: redirect after this step moves the user to the next page and helps avoid duplicate form submissions.
    header('Location: ' . APP_URL . '/public/auth/login.php?logout=1');
    exit;
}

// ─────────────────────────────────────────────────────────────
// ROLE HELPER FUNCTIONS
// ─────────────────────────────────────────────────────────────

/**
 * Study function: Returns true when the logged-in user has the admin role.
 */
function isAdmin(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_ADMIN;
}

/**
 * Study function: Returns true when the logged-in user has the guard role.
 */
function isGuard(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_GUARD;
}

/**
 * Study function: Returns true when the logged-in user belongs to office staff.
 */
function isOfficeStaff(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_OFFICE_STAFF;
}

/**
 * Study function: Returns true when the logged-in user belongs to Guest House staff.
 */
function isGuestHouseStaff(): bool {
    return isLoggedIn() && $_SESSION['user_role'] === ROLE_GUEST_HOUSE_STAFF;
}

/**
 * Study function: Returns true for users allowed to manage Guest House workflows.
 */
function isGuestHouseManager(): bool {
    return isAdmin() || isGuestHouseStaff();
}

/**
 * Study function: Returns true for users allowed to perform admin-or-guard actions.
 */
function isAdminOrGuard(): bool {
    return isAdmin() || isGuard();
}

/**
 * Study function: Returns the logged-in user ID stored in the session.
 */
function currentUserId(): ?int {
    return $_SESSION['user_id'] ?? null;
}

/**
 * Study function: Returns a small array describing the logged-in user from the session.
 */
function currentUser(): array {
    return [
        'user_id'   => $_SESSION['user_id'] ?? null,
        'full_name' => $_SESSION['user_name'] ?? '',
        'role'      => $_SESSION['user_role'] ?? '',
        'office_id' => $_SESSION['office_id'] ?? null,
    ];
}

/**
 * Study function: Returns the logged-in user name stored in the session.
 */
function currentUserName(): string {
    return $_SESSION['user_name'] ?? 'Unknown';
}

/**
 * Study function: Returns the logged-in user role stored in the session.
 */
function currentUserRole(): string {
    return $_SESSION['user_role'] ?? '';
}

/**
 * Study function: Returns the office ID assigned to the logged-in user, if any.
 */
function currentOfficeId(): ?int {
    return $_SESSION['office_id'] ?? null;
}

/**
 * Get the dashboard URL for the current user's role.
 */
function getDashboardUrl(): string {
    return match ($_SESSION['user_role'] ?? '') {
        ROLE_ADMIN              => APP_URL . '/public/dashboard/admin.php',
        ROLE_GUARD              => APP_URL . '/public/dashboard/guard.php',
        ROLE_OFFICE_STAFF       => APP_URL . '/public/dashboard/office.php',
        ROLE_GUEST_HOUSE_STAFF  => APP_URL . '/public/dashboard/guest_house.php',
        default                 => APP_URL . '/public/auth/login.php',
    };
}

// ─────────────────────────────────────────────────────────────
// ACTIVITY LOGGING
// ─────────────────────────────────────────────────────────────

/**
 * Insert a record into activity_logs.
 */
// Study audit: this records the important action in activity_logs for review and accountability.
/**
 * Study function: Writes log activity information to the audit trail.
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
        // Study query: Prepared SQL: creates a new row in ACTIVITY_LOGS. Placeholders keep user/form values separate from the SQL text.
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

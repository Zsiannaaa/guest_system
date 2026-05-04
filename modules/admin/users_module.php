<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Admin data-access module for users module records. Public admin pages call these functions instead of writing all SQL inline.
 * Flow: Called by browser pages in public/; returns data or performs database changes, then the page renders the result.
 * Security: These functions expect validated inputs from controllers and use prepared statements for database values.
 */
/**
 * modules/admin/users_module.php — User Model
 *
 * Contains ALL database logic for user management.
 * No HTML, no session checks — pure data functions.
 * Called by: public/admin/users.php, add_user.php, edit_user.php
 */

/**
 * Fetch all users with office name, ordered by role then name.
 */
function getUsers(PDO $pdo): array {
    // Study query: SQL query: reads rows from users, offices for lookup, validation, or display.
    return $pdo->query("
        SELECT u.*, o.office_name FROM users u
        LEFT JOIN offices o ON u.office_id = o.office_id
        ORDER BY u.role, u.full_name
    ")->fetchAll();
}

/**
 * Fetch a single user by primary key.
 */
function getUserById(PDO $pdo, int $id): array|false {
    // Study query: Prepared SQL: reads rows from users for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT * FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Check if email or username already exists (excluding a given user ID).
 */
function isUserDuplicate(PDO $pdo, string $email, string $username, int $excludeId = 0): bool {
    // Study query: Prepared SQL: reads rows from users for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM users WHERE (email = :e OR username = :u) AND user_id != :id");
    $stmt->execute([':e' => $email, ':u' => $username, ':id' => $excludeId]);
    return $stmt->fetchColumn() > 0;
}

/**
 * Create a new user. Returns error string or null on success.
 */
function createUser(PDO $pdo, string $fullName, string $email, string $username,
                    string $password, string $role, ?int $officeId, string $status): ?string {
    if (!$fullName || !$email || !$username || !$password) {
        return 'All required fields must be filled.';
    }
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (!in_array($role, ['admin', 'guard', 'office_staff', 'guest_house_staff'])) {
        return 'Invalid role selected.';
    }
    if ($role === 'office_staff' && !$officeId) {
        return 'Office staff must have an assigned office.';
    }
    if (isUserDuplicate($pdo, $email, $username)) {
        return 'Email or username already in use.';
    }

    $hash = password_hash($password, PASSWORD_DEFAULT);
    // Study query: Prepared SQL: creates a new row in USERS. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("
        INSERT INTO users (full_name, email, username, password_hash, role, office_id, status)
        VALUES (:n, :e, :u, :h, :r, :o, :s)
    ");
    $stmt->execute([
        ':n' => $fullName, ':e' => $email, ':u' => $username,
        ':h' => $hash, ':r' => $role, ':o' => $officeId, ':s' => $status,
    ]);
    return null;
}

/**
 * Update an existing user. Returns error string or null on success.
 */
function updateUser(PDO $pdo, int $id, string $fullName, string $email, string $username,
                    string $role, ?int $officeId, string $status, string $newPassword = ''): ?string {
    if (!$fullName || !$email || !$username) {
        return 'Full name, email, and username are required.';
    }
    if (!in_array($role, ['admin', 'guard', 'office_staff', 'guest_house_staff'])) {
        return 'Invalid role.';
    }
    if ($role === 'office_staff' && !$officeId) {
        return 'Office staff must have an assigned office.';
    }
    if ($newPassword && strlen($newPassword) < 8) {
        return 'Password must be at least 8 characters.';
    }
    if (isUserDuplicate($pdo, $email, $username, $id)) {
        return 'Email or username already used by another account.';
    }

    if ($newPassword) {
        $hash = password_hash($newPassword, PASSWORD_DEFAULT);
        // Study query: Prepared SQL: updates existing row(s) in USERS. Placeholders keep user/form values separate from the SQL text.
        $pdo->prepare("UPDATE users SET full_name=:n, email=:e, username=:u, role=:r, office_id=:o, status=:s, password_hash=:h WHERE user_id=:id")
            ->execute([':n'=>$fullName, ':e'=>$email, ':u'=>$username, ':r'=>$role, ':o'=>$officeId, ':s'=>$status, ':h'=>$hash, ':id'=>$id]);
    } else {
        // Study query: Prepared SQL: updates existing row(s) in USERS. Placeholders keep user/form values separate from the SQL text.
        $pdo->prepare("UPDATE users SET full_name=:n, email=:e, username=:u, role=:r, office_id=:o, status=:s WHERE user_id=:id")
            ->execute([':n'=>$fullName, ':e'=>$email, ':u'=>$username, ':r'=>$role, ':o'=>$officeId, ':s'=>$status, ':id'=>$id]);
    }
    return null;
}

/**
 * Toggle user active/inactive status. Returns new status.
 */
function toggleUserStatus(PDO $pdo, int $id): string {
    // Study query: Prepared SQL: reads rows from users for lookup, validation, or display. Placeholders keep user/form values separate from the SQL text.
    $stmt = $pdo->prepare("SELECT status FROM users WHERE user_id = :id");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetchColumn();
    $new = $current === 'active' ? 'inactive' : 'active';
    // Study query: Prepared SQL: updates existing row(s) in USERS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("UPDATE users SET status = :s WHERE user_id = :id")->execute([':s' => $new, ':id' => $id]);
    return $new;
}

/**
 * Delete a user by ID.
 */
function deleteUser(PDO $pdo, int $id): void {
    // Study query: Prepared SQL: deletes row(s) from USERS. Placeholders keep user/form values separate from the SQL text.
    $pdo->prepare("DELETE FROM users WHERE user_id = :id")->execute([':id' => $id]);
}

/**
 * Fetch all active offices for dropdown menus.
 */
function getActiveOfficesForDropdown(PDO $pdo): array {
    // Study query: SQL query: reads rows from offices for lookup, validation, or display.
    return $pdo->query("SELECT office_id, office_name FROM offices WHERE status='active' ORDER BY office_name")->fetchAll();
}

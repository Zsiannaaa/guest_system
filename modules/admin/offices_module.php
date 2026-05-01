<?php
/**
 * modules/admin/offices_module.php — Office Model
 *
 * Contains ALL database logic for office management.
 * No HTML, no session checks — pure data functions.
 * Called by: public/admin/offices.php, edit_office.php
 */

/**
 * Generate a unique office code from an office name.
 */
function generateUniqueOfficeCode(PDO $pdo, string $name): string {
    $base = preg_replace('/[^A-Z0-9]+/', '', strtoupper($name));
    $base = substr($base ?: 'OFFICE', 0, 20);
    $code = $base;
    $suffix = 1;

    $stmt = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE office_code = :code");
    do {
        $stmt->execute([':code' => $code]);
        if ((int) $stmt->fetchColumn() === 0) {
            return $code;
        }
        $code = substr($base, 0, 20) . $suffix++;
    } while (true);
}

/**
 * Fetch all offices with staff count.
 */
function getOffices(PDO $pdo): array {
    return $pdo->query("
        SELECT o.*,
               (SELECT COUNT(*) FROM users u WHERE u.office_id = o.office_id AND u.status = 'active') AS staff_count
        FROM offices o
        ORDER BY o.office_name
    ")->fetchAll();
}

/**
 * Fetch a single office by ID.
 */
function getOfficeById(PDO $pdo, int $id): array|false {
    $stmt = $pdo->prepare("SELECT * FROM offices WHERE office_id = :id");
    $stmt->execute([':id' => $id]);
    return $stmt->fetch();
}

/**
 * Add a new office. Returns error string or null on success.
 */
function addOffice(PDO $pdo, string $name, string $location): ?string {
    if (empty($name)) return 'Office name is required.';

    $code = generateUniqueOfficeCode($pdo, $name);
    $pdo->prepare("INSERT INTO offices (office_name, office_code, office_location, status) VALUES (:n, :c, :l, 'active')")
        ->execute([':n' => $name, ':c' => $code, ':l' => $location ?: null]);
    return null;
}

/**
 * Update an existing office. Returns error string or null on success.
 */
function updateOffice(PDO $pdo, int $id, string $name, string $code, string $location,
                      int $needsConfirmation, string $status): ?string {
    if (empty($name)) return 'Office name is required.';
    if (empty($code)) return 'Office code is required.';

    // Check code uniqueness
    $chk = $pdo->prepare("SELECT COUNT(*) FROM offices WHERE office_code = :c AND office_id != :id");
    $chk->execute([':c' => $code, ':id' => $id]);
    if ($chk->fetchColumn() > 0) return "Office code '{$code}' is already used.";

    $pdo->prepare("
        UPDATE offices SET office_name=:n, office_code=:c, office_location=:l,
                           requires_arrival_confirmation=:conf, status=:s
        WHERE office_id=:id
    ")->execute([':n'=>$name, ':c'=>$code, ':l'=>$location ?: null,
                 ':conf'=>$needsConfirmation, ':s'=>$status, ':id'=>$id]);
    return null;
}

/**
 * Toggle office active/inactive.
 */
function toggleOfficeStatus(PDO $pdo, int $id): void {
    $stmt = $pdo->prepare("SELECT status FROM offices WHERE office_id = :id");
    $stmt->execute([':id' => $id]);
    $current = $stmt->fetchColumn();
    $new = $current === 'active' ? 'inactive' : 'active';
    $pdo->prepare("UPDATE offices SET status = :s WHERE office_id = :id")->execute([':s' => $new, ':id' => $id]);
}

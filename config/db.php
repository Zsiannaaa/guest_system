<?php
/**
 * config/db.php
 * PDO Database Connection
 * University Guest Monitoring & Visitor Management System
 */

define('DB_HOST', 'localhost');
define('DB_NAME', 'guest_system');
define('DB_USER', 'root');
define('DB_PASS', '');          // Default XAMPP MySQL password is empty
define('DB_CHARSET', 'utf8mb4');

/**
 * Returns a PDO connection instance.
 * Uses a static variable so only one connection is created per request.
 */
function getDB(): PDO {
    static $pdo = null;

    if ($pdo === null) {
        $dsn = 'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET;

        $options = [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,  // Throw exceptions on error
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,         // Return assoc arrays
            PDO::ATTR_EMULATE_PREPARES   => false,                    // Use real prepared statements
        ];

        try {
            $pdo = new PDO($dsn, DB_USER, DB_PASS, $options);
        } catch (PDOException $e) {
            // In production, log this error instead of displaying it
            error_log('DB Connection Failed: ' . $e->getMessage());
            die(json_encode([
                'error' => 'Database connection failed. Please contact the system administrator.'
            ]));
        }
    }

    return $pdo;
}

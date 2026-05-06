<?php
/**
 * STUDY NOTES FOR REVIEW
 * Purpose: Creates the shared PDO database connection used by pages, includes, and module functions.
 * Flow: Loaded first by other PHP files; it does not render a page by itself.
 * Security: PDO is configured to throw exceptions and use real prepared statements where supported.
 */
/**
 * config/db.php
 * PDO Database Connection
 * University Guest Monitoring & Visitor Management System
 */

$dbConfig = [
    'host' => 'localhost',
    'name' => 'guest_system',
    'user' => 'root',
    'pass' => '',          // Default XAMPP MySQL password is empty
    'charset' => 'utf8mb4',
];

$localConfigFile = __DIR__ . '/db.local.php';
if (is_file($localConfigFile)) {
    $localConfig = require $localConfigFile;
    if (is_array($localConfig)) {
        $dbConfig = array_replace($dbConfig, array_filter(
            $localConfig,
            static fn($value) => $value !== null
        ));
    }
}

define('DB_HOST', $dbConfig['host']);
define('DB_NAME', $dbConfig['name']);
define('DB_USER', $dbConfig['user']);
define('DB_PASS', $dbConfig['pass']);
define('DB_CHARSET', $dbConfig['charset']);

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

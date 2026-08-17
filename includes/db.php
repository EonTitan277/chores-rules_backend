<?php
/**
 * db.php — PDO database connection helper.
 * Other scripts should call getDb() rather than opening their own connection.
 */

declare(strict_types=1);

/**
 * Return the shared PDO instance, creating it on first call.
 *
 * @throws PDOException If the connection cannot be established.
 */
function getDb(): PDO
{
    static $pdo = null;

    if ($pdo instanceof PDO) {
        return $pdo;
    }

    // Read credentials from the global config array populated by config.php.
    // Fall back to the matching constants (also defined by config.php)
    $host = $GLOBALS['CONFIG']['MYSQL_HOST'] ?? (defined('MYSQL_HOST') ? MYSQL_HOST : '');
    $db   = $GLOBALS['CONFIG']['MYSQL_DB']   ?? (defined('MYSQL_DB')   ? MYSQL_DB   : '');
    $user = $GLOBALS['CONFIG']['MYSQL_USER'] ?? (defined('MYSQL_USER') ? MYSQL_USER : '');
    $pass = $GLOBALS['CONFIG']['MYSQL_PASSWORD'] ?? (defined('MYSQL_PASSWORD') ? MYSQL_PASSWORD : '');

    if ($host === '' || $db === '' || $user === '') {
        throw new PDOException(
            'Database configuration is incomplete: MYSQL_HOST, MYSQL_DB, and MYSQL_USER are required. ' .
            'Ensure includes/config.php is loaded before calling getDb().'
        );
    }

    $dsn = "mysql:host={$host};dbname={$db};charset=utf8mb4";

    $options = [
        // Throw exceptions on error so problems surface immediately.
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        // Fetch associative arrays by default.
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        // Use real prepared statements (emulation off) for safer parameter binding.
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];

    $pdo = new PDO($dsn, $user, $pass, $options);

    return $pdo;
}

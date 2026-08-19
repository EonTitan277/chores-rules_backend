<?php
/**
 * Database Setup Script for Laragon
 * Run this once to create the database and tables
 * Usage: php setup-database.php
 */

require_once __DIR__ . '/includes/config.php';

echo "Setting up database for Chores & Rules app...\n\n";

try {
    // Connect to MySQL server (without database)
    // Handle empty password for Laragon default root user
    $mysqlHost = env('MYSQL_HOST');
    $mysqlDb = env('MYSQL_DB');
    $mysqlUser = env('MYSQL_USER');
    $mysqlPassword = env('MYSQL_PASSWORD', ''); // Can be empty string
    $admin1 = env('ADMIN_1');
    $passwordHash1 = env('PASSWORD_HASH_1');
    $kidUser = env('KIDUSER');
    $kidPasswordHash = env('KIDPASS_HASH');
    $readonlyUser = env('READONLYUSER');
    $readonlyPasswordHash = env('READONLYPASS_HASH');

    foreach ([
        'MYSQL_HOST' => $mysqlHost,
        'MYSQL_DB' => $mysqlDb,
        'MYSQL_USER' => $mysqlUser,
        'ADMIN_1' => $admin1,
        'PASSWORD_HASH_1' => $passwordHash1,
        'KIDUSER' => $kidUser,
        'KIDPASS_HASH' => $kidPasswordHash,
    ] as $key => $value) {
        if ($value === null || $value === '') {
            throw new RuntimeException("Required environment variable {$key} is missing or empty.");
        }
    }

    $dsn = "mysql:host={$mysqlHost};charset=utf8mb4";
    $username = $mysqlUser;
    $password = $mysqlPassword;
    
    $pdo = new PDO(
        $dsn,
        $username,
        $password,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );
    
    echo "✓ Connected to MySQL server\n";
    
    // Create database if not exists
    $pdo->exec("CREATE DATABASE IF NOT EXISTS `" . $mysqlDb . "` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    echo "✓ Database '" . $mysqlDb . "' created/verified\n";
    
    // Select the database
    $pdo->exec("USE `" . $mysqlDb . "`");

    // Create login-attempt tracking table used by the login rate limiter.
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `login_attempts` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `ip` VARCHAR(45) NOT NULL,
            `attempted_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            KEY `idx_login_attempts_ip_time` (`ip`, `attempted_at`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Login attempts table created/verified\n";
    
    // Create users table
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS `users` (
            `id` INT UNSIGNED NOT NULL AUTO_INCREMENT,
            `username` VARCHAR(64) NOT NULL,
            `password_hash` VARCHAR(255) NOT NULL,
            `role` ENUM('kid', 'admin', 'readonly') NOT NULL DEFAULT 'kid',
            `created_at` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY (`id`),
            UNIQUE KEY `uq_users_username` (`username`)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci
    ");
    echo "✓ Users table created/verified\n";

    // Upgrade databases created before the read-only role was introduced.
    $pdo->exec("ALTER TABLE `users` MODIFY `role` ENUM('kid', 'admin', 'readonly') NOT NULL DEFAULT 'kid'");
    
    // Insert default users if they don't exist. ADMIN_2 is optional.
    $users = [
        ['username' => $admin1, 'password_hash' => $passwordHash1, 'role' => 'admin'],
        ['username' => $kidUser, 'password_hash' => $kidPasswordHash, 'role' => 'kid'],
    ];

    $admin2 = env('ADMIN_2');
    $passwordHash2 = env('PASSWORD_HASH_2');
    if (($admin2 === null) !== ($passwordHash2 === null)) {
        throw new RuntimeException('ADMIN_2 and PASSWORD_HASH_2 must either both be configured or both be omitted.');
    }
    if ($admin2 !== null && $passwordHash2 !== null) {
        $users[] = ['username' => $admin2, 'password_hash' => $passwordHash2, 'role' => 'admin'];
    }

    if (($readonlyUser === null) !== ($readonlyPasswordHash === null)) {
        throw new RuntimeException('READONLYUSER and READONLYPASS_HASH must either both be configured or both be omitted.');
    }
    if ($readonlyUser !== null && $readonlyPasswordHash !== null) {
        $users[] = ['username' => $readonlyUser, 'password_hash' => $readonlyPasswordHash, 'role' => 'readonly'];
    }
    
    $stmt = $pdo->prepare("
        INSERT IGNORE INTO `users` (`username`, `password_hash`, `role`) 
        VALUES (?, ?, ?)
    ");
    
    foreach ($users as $user) {
        $stmt->execute([$user['username'], $user['password_hash'], $user['role']]);
        echo "✓ User '{$user['username']}' ({$user['role']}) created/verified\n";
    }
    
    // Verify users
    $stmt = $pdo->query("SELECT username, role FROM users");
    $rows = $stmt->fetchAll();
    
    echo "\n--- Current Users ---\n";
    foreach ($rows as $row) {
        echo "  {$row['username']} ({$row['role']})\n";
    }
    
    echo "\n✅ Database setup complete!\n";
    echo "You can now access the application at http://chores-app.test\n";
    
} catch (PDOException | RuntimeException $e) {
    echo "❌ Database error: " . $e->getMessage() . "\n";
    echo "\nTroubleshooting:\n";
    echo "1. Make sure MySQL is running in Laragon\n";
    echo "2. Check your .env file has correct MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD\n";
    echo "3. For Laragon default: MYSQL_HOST=localhost, MYSQL_USER=root, MYSQL_PASSWORD= (empty)\n";
    exit(1);
}
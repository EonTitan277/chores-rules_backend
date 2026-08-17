<?php
/**
 * db-check.php — CLI sanity check for the database connection and seed.
 *
 * Verifies that:
 *   1. includes/config.php loads the required DB credentials.
 *   2. includes/db.php's getDb() returns a working PDO connection.
 *   3. The `users` table exists and the seeded accounts are present.
 *
 * Usage:
 *     php db-check.php
 *
 * Exit codes:
 *   0 — all checks passed
 *   1 — one or more checks failed
 */

declare(strict_types=1);

// Refuse to run from a web request — this is a CLI diagnostic only.
if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Forbidden: this script is CLI-only and may not be run over HTTP.\n";
    exit(1);
}

require_once __DIR__ . '/includes/config.php';
require_once __DIR__ . '/includes/db.php';

echo "Chores & Rules — database sanity check\n";
echo "--------------------------------------\n";

$failures = 0;

// --- Check 1: required DB config is present -------------------------------
$required = ['MYSQL_HOST', 'MYSQL_DB', 'MYSQL_USER', 'MYSQL_PASSWORD'];
echo "1) Checking DB configuration... ";
$missing = [];
foreach ($required as $key) {
    $val = $GLOBALS['CONFIG'][$key] ?? (defined($key) ? constant($key) : '');
    if ($val === null || $val === '') {
        // MYSQL_PASSWORD may legitimately be empty for local dev; skip it.
        if ($key === 'MYSQL_PASSWORD') {
            continue;
        }
        $missing[] = $key;
    }
}
if ($missing !== []) {
    echo "FAIL\n";
    fwrite(STDERR, "   Missing required env vars: " . implode(', ', $missing) . "\n");
    $failures++;
} else {
    echo "OK\n";
    echo "   host=" . ($GLOBALS['CONFIG']['MYSQL_HOST'] ?? '') .
         " db=" . ($GLOBALS['CONFIG']['MYSQL_DB'] ?? '') .
         " user=" . ($GLOBALS['CONFIG']['MYSQL_USER'] ?? '') . "\n";
}

// --- Check 2: getDb() returns a live PDO connection -----------------------
echo "2) Connecting via getDb()... ";
try {
    $pdo = getDb();
    $pdo->query('SELECT 1')->fetchColumn();
    echo "OK\n";
} catch (Throwable $e) {
    echo "FAIL\n";
    fwrite(STDERR, "   " . $e->getMessage() . "\n");
    $failures++;
    echo "\n❌ Database sanity check failed ($failures failure(s)).\n";
    exit(1);
}

// --- Check 3: users table exists and seeded accounts are present ----------
echo "3) Verifying `users` table and seeded accounts... ";
try {
    $count = (int) $pdo->query('SELECT COUNT(*) FROM `users`')->fetchColumn();
    echo "OK\n";
    echo "   users table present, $count row(s) found.\n";

    $rows = $pdo->query('SELECT `username`, `role` FROM `users` ORDER BY `id`')->fetchAll();
    if ($rows === []) {
        echo "   WARN: users table is empty — the seed script may not have run.\n";
        $failures++;
    } else {
        echo "   --- Seeded users ---\n";
        foreach ($rows as $row) {
            echo "   • {$row['username']} ({$row['role']})\n";
        }
    }
} catch (Throwable $e) {
    echo "FAIL\n";
    fwrite(STDERR, "   " . $e->getMessage() . "\n");
    fwrite(STDERR, "   Does the `users` table exist? Check docker/init/001-schema.sql ran on first DB start.\n");
    $failures++;
}

// --- Result ---------------------------------------------------------------
echo "--------------------------------------\n";
if ($failures === 0) {
    echo "✅ All checks passed.\n";
    exit(0);
}
echo "❌ Database sanity check failed ($failures failure(s)).\n";
exit(1);

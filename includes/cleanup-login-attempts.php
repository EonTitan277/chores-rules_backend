<?php

declare(strict_types=1);

if (php_sapi_name() !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';

try {
    $deleted = getDb()->exec(
        'DELETE FROM login_attempts WHERE attempted_at < NOW() - INTERVAL 1 DAY'
    );

    printf("Cleanup complete: %d login attempt(s) deleted.\n", $deleted);
} catch (Throwable $e) {
    fwrite(STDERR, "Cleanup failed: {$e->getMessage()}\n");
    exit(1);
}
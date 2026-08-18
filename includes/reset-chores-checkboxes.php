<?php

declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    echo "This script can only be run from the command line.\n";
    exit(1);
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/json-store.php';

$statePath = rtrim((string) env('DATA_PATH'), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'chores-checkbox-states.json';

try {
    $state = readJsonState($statePath);
    $resetCount = 0;
    foreach ($state as $key => $value) {
        if (is_bool($value)) {
            $state[$key] = false;
            $resetCount++;
        }
    }
    writeJsonState($statePath, $state);
    printf("Chore reset complete: %d checkbox(es) reset.\n", $resetCount);
} catch (Throwable $exception) {
    fwrite(STDERR, "Chore reset failed: {$exception->getMessage()}\n");
    exit(1);
}

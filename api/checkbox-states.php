<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/cache.php';
sendNoCacheHeaders();

define('API_JSON_AUTH', true);
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/json-store.php';

$statePath = rtrim((string) env('DATA_PATH'), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'chores-checkbox-states.json';

handleJsonStateRequest(
    $statePath,
    static fn (mixed $value): bool => is_bool($value),
);
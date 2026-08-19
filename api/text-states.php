<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/cache.php';
sendNoCacheHeaders();

define('API_JSON_AUTH', true);
require_once __DIR__ . '/../includes/auth-check.php';
require_once __DIR__ . '/../includes/json-store.php';

if ($_SERVER['REQUEST_METHOD'] === 'PUT') {
    requireStateWriteAccess();
}

handleJsonStateRequest(
    rtrim((string) env('DATA_PATH'), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'text-states.json',
    static fn (mixed $value): bool => is_string($value),
);
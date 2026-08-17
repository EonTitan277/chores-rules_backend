<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/checkbox-state-files.php';

$statePath = rtrim((string) env('DATA_PATH'), DIRECTORY_SEPARATOR)
    . DIRECTORY_SEPARATOR . 'chores-checkbox-states.json';

try {
    $state = readJsonState($statePath);
    foreach ($state as $key => $value) {
        if (is_bool($value)) {
            $state[$key] = false;
        }
    }
    writeJsonState($statePath, $state);
} catch (RuntimeException $exception) {
    error_log('Unable to reset chores checkboxes: ' . $exception->getMessage());
    exit(1);
}
<?php
/**
 * Shared locked JSON state storage for the persistence API.
 */

declare(strict_types=1);

/**
 * Reject state writes from users who are authenticated only for viewing.
 */
function requireStateWriteAccess(): void
{
    if (($_SESSION['role'] ?? null) === 'readonly') {
        http_response_code(403);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Read-only users cannot modify state'], JSON_THROW_ON_ERROR);
        exit;
    }
}

/**
 * Read a JSON object while holding the same lock used for updates.
 *
 * @return array<string, mixed>
 */
function readJsonState(string $path): array
{
    $lock = openJsonStateLock($path);

    try {
        return readJsonStateUnlocked($path);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/**
 * Update one value using a lock and an atomic same-filesystem rename.
 */
function updateJsonState(string $path, string $key, bool|string $value): void
{
    $lock = openJsonStateLock($path);

    try {
        $state = readJsonStateUnlocked($path);
        $state[$key] = $value;
        writeJsonStateUnlocked($path, $state);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** @return resource */
function openJsonStateLock(string $path)
{
    $lock = @fopen($path . '.lock', 'c');
    if ($lock === false || !flock($lock, LOCK_EX)) {
        if (is_resource($lock)) {
            fclose($lock);
        }
        throw new RuntimeException('Unable to lock the state file');
    }

    return $lock;
}

/**
 * @return array<string, mixed>
 */
function readJsonStateUnlocked(string $path): array
{
    if (!is_file($path)) {
        return [];
    }

    $contents = file_get_contents($path);
    if ($contents === false) {
        throw new RuntimeException('Unable to read the state file');
    }

    try {
        $state = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
    } catch (JsonException $exception) {
        throw new RuntimeException('The state file contains invalid JSON', 0, $exception);
    }

    if (!is_array($state) || array_is_list($state)) {
        throw new RuntimeException('The state file must contain a JSON object');
    }

    return $state;
}

function sendJsonResponse(array $body, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($body, JSON_THROW_ON_ERROR | JSON_FORCE_OBJECT);
    exit;
}

/**
 * Handle the common persistence endpoint flow.
 *
 * @param callable(mixed): bool $valueValidator
 */
function handleJsonStateRequest(string $path, callable $valueValidator): never
{
    try {
        if ($_SERVER['REQUEST_METHOD'] === 'GET') {
            sendJsonResponse(readJsonState($path));
        }

        if ($_SERVER['REQUEST_METHOD'] !== 'PUT') {
            header('Allow: GET, PUT');
            sendJsonResponse(['error' => 'Method not allowed'], 405);
        }

        $body = file_get_contents('php://input');
        $payload = $body === false
            ? null
            : json_decode($body, true, 512, JSON_THROW_ON_ERROR);

        if (!is_array($payload)
            || array_is_list($payload)
            || !array_key_exists('key', $payload)
            || !is_string($payload['key'])
            || trim($payload['key']) === ''
            || !array_key_exists('value', $payload)
            || !$valueValidator($payload['value'])
        ) {
            sendJsonResponse(['error' => 'Expected a non-empty string key and a valid value'], 400);
        }

        updateJsonState($path, $payload['key'], $payload['value']);
        sendJsonResponse(['ok' => true]);
    } catch (JsonException $exception) {
        sendJsonResponse(['error' => 'Malformed JSON request body'], 400);
    } catch (RuntimeException $exception) {
        sendJsonResponse(['error' => $exception->getMessage()], 500);
    }
}

/**
 * Replace a JSON object while holding the same lock used for updates.
 *
 * @param array<string, mixed> $state
 */
function writeJsonState(string $path, array $state): void
{
    $lock = openJsonStateLock($path);

    try {
        writeJsonStateUnlocked($path, $state);
    } finally {
        flock($lock, LOCK_UN);
        fclose($lock);
    }
}

/** @param array<string, mixed> $state */
function writeJsonStateUnlocked(string $path, array $state): void
{
    $directory = dirname($path);
    $temporaryPath = tempnam($directory, basename($path) . '.tmp-');
    if ($temporaryPath === false) {
        throw new RuntimeException('Unable to create a temporary state file');
    }

    try {
        $json = json_encode($state, JSON_THROW_ON_ERROR | JSON_PRETTY_PRINT) . PHP_EOL;
        if (file_put_contents($temporaryPath, $json, LOCK_EX) === false) {
            throw new RuntimeException('Unable to write the state file');
        }
        if (!rename($temporaryPath, $path)) {
            // Windows (including Laragon) does not replace an existing file
            // with rename(), even while the application lock is held.
            if (DIRECTORY_SEPARATOR !== '\\'
                || !is_file($path)
                || !unlink($path)
                || !rename($temporaryPath, $path)
            ) {
                throw new RuntimeException('Unable to replace the state file');
            }
        }
    } finally {
        if (is_file($temporaryPath)) {
            unlink($temporaryPath);
        }
    }
}
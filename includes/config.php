<?php
/**
 * config.php — Environment loading and app-wide configuration.
 *
 * Loads configuration from (in order of precedence):
 *   1. Real environment variables (injected by Docker, etc.) — read via getenv().
 *   2. A local .env file at the project root, parsed with a minimal loader.
 *
 * Exposes all values through the global $CONFIG array and as PHP constants
 * for the keys listed in $REQUIRED_KEYS. Also performs startup sanity checks
 * (writable data/ and logs/ directories).
 *
 * This file is meant to be included once at the top of every entry point:
 *     require_once __DIR__ . '/../includes/config.php';
 */

declare(strict_types=1);

$ENV_CACHE = [];

/**
 * Minimal .env parser. Reads a .env file and stores the key/value pairs in
 * the in-process $ENV_CACHE and the superglobals. Does NOT overwrite values
 * that are already present in the real environment (Docker-injected values win).
 */
function load_env_file(string $path): void
{
    global $ENV_CACHE;

    if (!is_file($path)) {
        return;
    }

    $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if ($lines === false) {
        return;
    }

    foreach ($lines as $line) {
        $line = trim($line);

        // Skip comments and empty lines.
        if ($line === '' || $line[0] === '#') {
            continue;
        }

        // Split on the first '='.
        $eqPos = strpos($line, '=');
        if ($eqPos === false) {
            continue;
        }

        $key   = trim(substr($line, 0, $eqPos));
        $value = trim(substr($line, $eqPos + 1));

        if ($key === '') {
            continue;
        }

        // Strip surrounding quotes (single or double) if present.
        if (strlen($value) >= 2) {
            $first = $value[0];
            $last  = $value[strlen($value) - 1];
            if (($first === '"' && $last === '"') || ($first === "'" && $last === "'")) {
                $value = substr($value, 1, -1);
            }
        }

        // A value already present in the real environment (e.g. Docker) wins.
        $realEnv = getenv($key);
        if ($realEnv !== false && $realEnv !== '') {
            $ENV_CACHE[$key] = $realEnv;
            continue;
        }

        $ENV_CACHE[$key] = $value;
        $_ENV[$key]    = $value;
        $_SERVER[$key] = $value;
        // Keep putenv() for any third-party code that reads via getenv(), but
        // do not rely on it for our own env() lookups (see $ENV_CACHE docs).
        @putenv($key . '=' . $value);
    }
}

/**
 * Resolve a config value: prefer the in-process cache (always consistent
 * within a request), then the real environment, then fall back to a default.
 */
function env(string $key, ?string $default = null): ?string
{
    global $ENV_CACHE;

    if (isset($ENV_CACHE[$key]) && $ENV_CACHE[$key] !== '') {
        return $ENV_CACHE[$key];
    }

    $val = getenv($key);
    if ($val === false || $val === '') {
        return $default;
    }
    return $val;
}

// --- Load .env -------------------------------------------------------------

// Project root is one level up from this includes/ directory.
$projectRoot = dirname(__DIR__);
load_env_file($projectRoot . '/.env');

// --- Required keys ---------------------------------------------------------

$REQUIRED_KEYS = [
    'HOST_PORT',
    'SESSION_SECRET',
    'WEBROOT_PATH',
    'MYSQL_HOST',
    'MYSQL_DB',
    'MYSQL_USER',
    'MYSQL_PASSWORD',
    'COUNCIL_NAME',
    'COUNCIL_PHONE',
    'LOGS_PATH',
    'DATA_PATH',
    // User credentials (uncommend before running setup-database.php)
    // 'ADMIN_1',
    // 'PASSWORD_HASH_1',
    // 'ADMIN_2',
    // 'PASSWORD_HASH_2',
    // 'KIDUSER',
    // 'KIDPASS_HASH',
];

// --- Build the global config array -----------------------------------------

$CONFIG = [];
foreach ($REQUIRED_KEYS as $key) {
    $CONFIG[$key] = env($key);
}

// Expose as constants for convenient reuse. Constants can't be undefined, so
// missing values become the empty string here; the validation below catches
// truly missing required values and fails loudly.
foreach ($REQUIRED_KEYS as $key) {
    if (!defined($key)) {
        define($key, $CONFIG[$key] ?? '');
    }
}

// --- Startup checks --------------------------------------------------------

/**
 * Fail loudly if a required environment variable is missing or empty.
 */
$missing = [];
foreach ($REQUIRED_KEYS as $key) {
    // Allow MYSQL_PASSWORD to be empty for local development
    if ($key === 'MYSQL_PASSWORD') {
        continue;
    }
    if ($CONFIG[$key] === null || $CONFIG[$key] === '') {
        $missing[] = $key;
    }
}
if ($missing !== []) {
    http_response_code(500);
    header('Content-Type: text/plain; charset=utf-8');
    echo "Server configuration error: the following required environment variables are missing or empty:\n";
    echo implode("\n", $missing) . "\n";
    echo "Copy .env.example to .env and fill in the values, or inject them via the container environment.\n";
    exit(1);
}

/**
 * Verify that the data/ and logs/ directories exist and are writable by the
 * PHP process user. Fail loudly with a clear message rather than failing
 * silently later when a write is attempted.
 */
function check_writable_dir(string $label, string $path): void
{
    if ($path === '') {
        return;
    }

    if (!is_dir($path)) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Server configuration error: the {$label} directory does not exist: {$path}\n";
        echo "Ensure it is mounted/created before starting the app.\n";
        exit(1);
    }

    if (!is_writable($path)) {
        $user = get_current_user();
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Server configuration error: the {$label} directory is not writable by the PHP process user ({$user}): {$path}\n";
        echo "Adjust ownership/permissions (e.g. chown the directory to the web server user) and restart.\n";
        exit(1);
    }
}

/**
 * Ensure a log file exists and can be appended to by the PHP process.
 */
function check_writable_log_file(string $path): void
{
    $handle = @fopen($path, 'ab');
    if ($handle === false) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "Server configuration error: the authentication log is not writable: {$path}\n";
        echo "Adjust ownership/permissions so the PHP process can append to the file and restart.\n";
        exit(1);
    }

    fclose($handle);
}

$dataPath = (string) ($CONFIG['DATA_PATH'] ?? '');
$logsPath = (string) ($CONFIG['LOGS_PATH'] ?? '');
check_writable_dir('data', $dataPath);
check_writable_dir('logs', $logsPath);
check_writable_log_file(rtrim($logsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'auth.log');

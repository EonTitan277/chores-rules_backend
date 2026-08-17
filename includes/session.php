<?php
/**
 * session.php — Application session configuration and startup.
 *
 * PHP sessions are server-side by default, so SESSION_SECRET is loaded and
 * validated by config.php but is not used as a cookie value or session ID.
 */

declare(strict_types=1);

require_once __DIR__ . '/config.php';

const SESSION_LIFETIME = 30 * 24 * 60 * 60; // 30 days.

function startAppSession(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;
    }

    $sessionName = env('SESSION_NAME', 'CHORES_SESSION');
    $secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (int) ($_SERVER['SERVER_PORT'] ?? 0) === 443;

    session_name($sessionName);
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.cookie_httponly', '1');
    ini_set('session.cookie_secure', $secure ? '1' : '0');
    ini_set('session.cookie_samesite', 'Lax');
    ini_set('session.cookie_lifetime', (string) SESSION_LIFETIME);
    // Keep server-side session data for at least as long as the browser cookie.
    ini_set('session.gc_maxlifetime', (string) SESSION_LIFETIME);

    session_set_cookie_params([
        'lifetime' => SESSION_LIFETIME,
        'path' => '/',
        'secure' => $secure,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);

    session_start();

    $now = time();

    if (isset($_SESSION['user_id'], $_SESSION['session_expires_at'])
        && is_int($_SESSION['session_expires_at'])
        && $now >= $_SESSION['session_expires_at']
    ) {
        session_unset();
        session_destroy();
        session_start();
    }

    if (isset($_SESSION['user_id']) && !isset($_SESSION['session_expires_at'])) {
        $_SESSION['session_expires_at'] = $now + SESSION_LIFETIME;
    }
}
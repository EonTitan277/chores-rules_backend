<?php
/**
 * Reusable login rate-limit helpers.
 */

declare(strict_types=1);

const LOGIN_ATTEMPT_LIMIT = 4;
const LOGIN_ATTEMPT_WINDOW_MINUTES = 10;

/**
 * Return the number of recent failed attempts for an IP address.
 */
function countRecentLoginAttempts(PDO $pdo, string $ip): int
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) FROM login_attempts '
        . 'WHERE ip = :ip AND attempted_at >= (CURRENT_TIMESTAMP - INTERVAL 10 MINUTE)'
    );
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();

    return (int) $stmt->fetchColumn();
}

/**
 * Record a failed login attempt for an IP address.
 */
function recordLoginAttempt(PDO $pdo, string $ip): void
{
    // Stale rows are a future cleanup-job candidate, e.g. DELETE WHERE
    // attempted_at < NOW() - INTERVAL 1 DAY. Do not delete them per request.
    $stmt = $pdo->prepare('INSERT INTO login_attempts (ip) VALUES (:ip)');
    $stmt->bindValue(':ip', $ip, PDO::PARAM_STR);
    $stmt->execute();
}

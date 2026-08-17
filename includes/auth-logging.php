<?php
/**
 * Authentication failure logging.
 */

declare(strict_types=1);

/**
 * Append a Fail2Ban-friendly failed-login record without logging passwords.
 */
function logFailedLogin(string $clientIp, string $username): void
{
    $logsPath = $GLOBALS['CONFIG']['LOGS_PATH'] ?? '';
    if (!is_string($logsPath) || $logsPath === '') {
        return;
    }
    $logPath = rtrim($logsPath, DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'auth.log';

    // Keep fields on one line so the log remains easy for Fail2Ban to parse.
    $safeIp = preg_replace('/[\x00-\x1F\x7F\s]+/', '_', $clientIp) ?? '_';
    $safeUsername = preg_replace('/[\x00-\x1F\x7F\s]+/', '_', $username) ?? '_';
    $safeIp = $safeIp === '' ? '_' : $safeIp;
    $safeUsername = $safeUsername === '' ? '_' : $safeUsername;

    $line = sprintf(
        "[%s] FAILED_LOGIN ip=%s user=%s%s",
        date('Y-m-d H:i:s'),
        $safeIp,
        $safeUsername,
        PHP_EOL
    );

    // file_put_contents creates auth.log when it does not exist.
    @file_put_contents($logPath, $line, FILE_APPEND | LOCK_EX);
}

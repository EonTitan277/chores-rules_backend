<?php
/**
 * Resolve the originating client IP address.
 *
 * Nginx Proxy Manager forwards the client address in X-Forwarded-For. The
 * first address is the original client; fall back to REMOTE_ADDR when the
 * forwarding header is unavailable or empty.
 */

declare(strict_types=1);

function getClientIp(): string
{
    $forwardedFor = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? '';

    if (is_string($forwardedFor) && trim($forwardedFor) !== '') {
        $forwardedAddresses = explode(',', $forwardedFor);
        $clientIp = trim($forwardedAddresses[0]);

        if ($clientIp !== '') {
            return $clientIp;
        }
    }

    $remoteAddress = $_SERVER['REMOTE_ADDR'] ?? '';

    return is_string($remoteAddress) ? trim($remoteAddress) : '';
}

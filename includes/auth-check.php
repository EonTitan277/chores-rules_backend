<?php
/**
 * auth-check.php — Require an authenticated session for protected pages.
 *
 * Include this file before any output in every protected PHP page or API
 * endpoint:
 *     require_once __DIR__ . '/../includes/auth-check.php';
 */

declare(strict_types=1);

require_once __DIR__ . '/session.php';
require_once __DIR__ . '/cache.php';
sendNoCacheHeaders();
startAppSession();

if (!isset($_SESSION['user_id'])) {
    if (defined('API_JSON_AUTH')) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode(['error' => 'Authentication required'], JSON_THROW_ON_ERROR);
        exit;
    }

    header('Location: /login.php');
    exit;
}

if (defined('API_JSON_AUTH') && session_status() === PHP_SESSION_ACTIVE) {
    session_write_close();
}
<?php

declare(strict_types=1);

require_once __DIR__ . '/../includes/cache.php';
sendNoCacheHeaders();

const API_JSON_AUTH = true;
require_once __DIR__ . '/../includes/auth-check.php';

$role = $_SESSION['role'] ?? null;
if (!is_string($role) || !in_array($role, ['kid', 'admin'], true)) {
    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => 'User role is unavailable'], JSON_THROW_ON_ERROR);
    exit;
}

header('Content-Type: application/json; charset=utf-8');
echo json_encode(['role' => $role], JSON_THROW_ON_ERROR);

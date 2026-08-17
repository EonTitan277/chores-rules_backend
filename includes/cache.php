<?php
/**
 * cache.php — Headers for responses containing authenticated application data.
 */

declare(strict_types=1);

function sendNoCacheHeaders(): void
{
    header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
    header('Pragma: no-cache');
    header('Expires: 0');
}
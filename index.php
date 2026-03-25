<?php
declare(strict_types=1);

require __DIR__ . '/config.php';

if (empty($siteEnabled)) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/maintenance.php';
    exit;
}

if (!isset($pdo) || !$pdo instanceof PDO) {
    http_response_code(503);
    header('Content-Type: text/html; charset=UTF-8');
    require __DIR__ . '/unavailable.php';
    exit;
}

require __DIR__ . '/order-form.php';

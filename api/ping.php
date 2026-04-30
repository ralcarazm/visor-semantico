<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Support\JsonResponse;

$paths = km_config('paths', []);
$writable = [];

foreach ($paths as $name => $path) {
    $writable[$name] = [
        'path' => $path,
        'exists' => is_dir($path),
        'writable' => is_dir($path) && is_writable($path),
    ];
}

JsonResponse::send([
    'status' => 'ok',
    'app' => km_config('app.name'),
    'version' => km_config('app.version'),
    'php_version' => PHP_VERSION,
    'time' => date(DATE_ATOM),
    'http' => [
        'curl_available' => function_exists('curl_init'),
        'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
    ],
    'paths' => $writable,
]);

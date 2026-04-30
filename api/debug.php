<?php
declare(strict_types=1);

require dirname(__DIR__) . '/app/bootstrap.php';

use KnowledgeMap\Support\JsonResponse;

function km_debug_path_status(string $path): array
{
    return [
        'path' => $path,
        'exists' => file_exists($path),
        'is_dir' => is_dir($path),
        'writable' => is_writable($path),
        'readable' => is_readable($path),
    ];
}

try {
    $root = dirname(__DIR__);
    $sampleTtl = $root . '/samples/hokusai.ttl';
    $sampleCsv = $root . '/samples/hokusai.csv';
    $sampleSorolla = $root . '/samples/sorolla.ttl';

    $extensions = [];
    foreach (['curl', 'json', 'mbstring', 'openssl', 'xml', 'simplexml', 'dom'] as $extension) {
        $extensions[$extension] = extension_loaded($extension);
    }

    JsonResponse::send([
        'status' => 'ok',
        'app' => km_config('app.name', 'visorsemanticoisor Semántico'),
        'version' => km_config('app.version', '1.0'),
        'php_version' => PHP_VERSION,
        'time' => date(DATE_ATOM),
        'http' => [
            'curl_available' => function_exists('curl_init'),
            'allow_url_fopen' => filter_var(ini_get('allow_url_fopen'), FILTER_VALIDATE_BOOLEAN),
            'openssl_loaded' => extension_loaded('openssl'),
        ],
        'extensions' => $extensions,
        'limits' => [
            'upload_max_filesize' => ini_get('upload_max_filesize'),
            'post_max_size' => ini_get('post_max_size'),
            'memory_limit' => ini_get('memory_limit'),
            'max_execution_time' => ini_get('max_execution_time'),
        ],
        'paths' => [
            'storage' => km_debug_path_status((string) km_config('paths.storage')),
            'uploads' => km_debug_path_status((string) km_config('paths.uploads')),
            'maps' => km_debug_path_status((string) km_config('paths.maps')),
            'entity_cache' => km_debug_path_status((string) km_config('paths.entity_cache')),
            'image_cache' => km_debug_path_status((string) km_config('paths.image_cache')),
        ],
        'samples' => [
            'hokusai_ttl' => [
                'path' => $sampleTtl,
                'exists' => is_file($sampleTtl),
                'readable' => is_readable($sampleTtl),
                'size_bytes' => is_file($sampleTtl) ? filesize($sampleTtl) : null,
            ],
            'hokusai_csv' => [
                'path' => $sampleCsv,
                'exists' => is_file($sampleCsv),
                'readable' => is_readable($sampleCsv),
                'size_bytes' => is_file($sampleCsv) ? filesize($sampleCsv) : null,
            ],
            'sorolla_ttl' => [
                'path' => $sampleSorolla,
                'exists' => is_file($sampleSorolla),
                'readable' => is_readable($sampleSorolla),
                'size_bytes' => is_file($sampleSorolla) ? filesize($sampleSorolla) : null,
            ],
        ],
        'composer' => [
            'autoload_exists' => is_file($root . '/vendor/autoload.php'),
            'easyrdf_available' => class_exists('EasyRdf\\Graph'),
        ],
    ]);
} catch (Throwable $exception) {
    JsonResponse::error('No se ha podido ejecutar el diagnóstico.', 500, [
        'exception' => $exception->getMessage(),
    ]);
}
